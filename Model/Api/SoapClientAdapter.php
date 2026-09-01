<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;
use Throwable;

class SoapClientAdapter implements ClientInterface
{
    private const WSDL_URL_PRODUCTION = 'https://dhl24.com.pl/webapi2';
    private const WSDL_URL_SANDBOX = 'https://sandbox.dhl24.com.pl/webapi2';
    private const CONNECTION_TIMEOUT = 10;
    private const XML_PATH_API_ORIGIN = 'carriers/dhlparcel/api_origin';

    /**
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param FileDriver|null $fileDriver
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ?FileDriver $fileDriver = null
    ) {
    }

    /**
     * Test API connection and credentials using getPostalCodeServices operation.
     *
     * @param Credentials $credentials
     *
     * @return TestConnectionResult
     */
    public function testConnection(Credentials $credentials): TestConnectionResult
    {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'postCode' => '00001',
                'pickupDate' => date('Y-m-d'),
            ];

            $client->getPostalCodeServices($params);

            $this->logSoapCommunication($client, 'getPostalCodeServices');

            return new TestConnectionResult(
                true,
                'Connection to DHL WebAPI2 succeeded. Authentication valid.'
            );
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during testConnection: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new TestConnectionResult(
                false,
                'DHL connection error: ' . $fault->getMessage(),
                $fault->faultcode ?? 'SOAP_FAULT'
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during testConnection: %s',
                $e->getMessage()
            ));

            return new TestConnectionResult(
                false,
                'An error occurred while connecting to DHL: ' . $e->getMessage(),
                'CONNECTION_ERROR'
            );
        }
    }

    /**
     * Create shipments via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param array $shipmentsFullData
     * @param int|null $orderId Optional Magento order ID for diagnostic logging context
     *
     * @return CreateShipmentsResult
     */
    public function createShipments(
        Credentials $credentials,
        array $shipmentsFullData,
        ?int $orderId = null
    ): CreateShipmentsResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;
        $orderContext = $orderId !== null ? sprintf(' (Order #%d)', $orderId) : '';

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'shipments' => [
                    'item' => $shipmentsFullData,
                ],
            ];

            $response = $client->createShipments($params);
            $this->logSoapCommunication($client, 'createShipments' . $orderContext);

            $shipmentResult = $response->createShipmentsResult->item ?? $response->createShipmentsResult ?? null;
            if (is_array($shipmentResult)) {
                $shipmentResult = $shipmentResult[0] ?? null;
            }

            if (is_object($shipmentResult) && !empty($shipmentResult->shipmentId)) {
                $orderStatus = !empty($shipmentResult->orderStatus) ? (string) $shipmentResult->orderStatus : null;
                return new CreateShipmentsResult(true, false, (string) $shipmentResult->shipmentId, null, null, $orderStatus);
            }

            $errorMsg = is_object($shipmentResult) && !empty($shipmentResult->error)
                ? (string) $shipmentResult->error
                : 'DHL API returned an invalid response structure.';

            $this->logger->error(sprintf(
                '[DHL WebAPI2] createShipments failed%s: %s',
                $orderContext,
                $errorMsg
            ));

            return new CreateShipmentsResult(false, false, null, 'CREATE_SHIPMENT_ERROR', $errorMsg);
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during createShipments%s: [%s] %s',
                $orderContext,
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            $isTimeout = $this->isTimeoutException($fault);

            return new CreateShipmentsResult(
                false,
                $isTimeout,
                null,
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during createShipments%s: %s',
                $orderContext,
                $e->getMessage()
            ));

            $isTimeout = $this->isTimeoutException($e);

            return new CreateShipmentsResult(
                false,
                $isTimeout,
                null,
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check if exception message or code indicates a network/SOAP timeout.
     *
     * @param Throwable $e
     * @return bool
     */
    private function isTimeoutException(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'could not connect')
            || str_contains($message, 'connection refused');
    }

    /**
     * Delete shipment via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $shipmentId
     *
     * @return DeleteShipmentResult
     */
    public function deleteShipment(
        Credentials $credentials,
        string $shipmentId
    ): DeleteShipmentResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'shipments' => [
                    'item' => $this->ensureUtf8($shipmentId),
                ],
            ];

            $response = $client->deleteShipments($params);
            $this->logSoapCommunication($client, 'deleteShipments');

            $resultItem = $response->deleteShipmentsResult->item ?? $response->deleteShipmentsResult ?? null;
            if (is_array($resultItem)) {
                $resultItem = $resultItem[0] ?? null;
            }

            if (is_object($resultItem)) {
                if (!empty($resultItem->error)) {
                    return new DeleteShipmentResult(false, $shipmentId, 'DELETE_SHIPMENT_ERROR', (string) $resultItem->error);
                }

                if (!empty($resultItem->id) || !empty($resultItem->shipmentIdentification) || (isset($resultItem->result) && (bool) $resultItem->result === true)) {
                    return new DeleteShipmentResult(true, $shipmentId);
                }
            }

            if (is_bool($resultItem) && $resultItem === true) {
                return new DeleteShipmentResult(true, $shipmentId);
            }

            $errorMsg = is_object($resultItem) && !empty($resultItem->error)
                ? (string) $resultItem->error
                : 'DHL API returned an error or invalid response during deleteShipments.';

            return new DeleteShipmentResult(false, $shipmentId, 'DELETE_SHIPMENT_ERROR', $errorMsg);
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during deleteShipment: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new DeleteShipmentResult(
                false,
                $shipmentId,
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during deleteShipment: %s',
                $e->getMessage()
            ));

            return new DeleteShipmentResult(
                false,
                $shipmentId,
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get postal code services and pickup windows via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $postCode
     * @param string $pickupDate Format YYYY-MM-DD
     *
     * @return PostalCodeServicesResult
     */
    public function getPostalCodeServices(
        Credentials $credentials,
        string $postCode,
        string $pickupDate
    ): PostalCodeServicesResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $cleanPostCode = (string) preg_replace('/[^0-9]/', '', $postCode);
            if (empty($cleanPostCode) || $cleanPostCode === '00000') {
                return new PostalCodeServicesResult(
                    success: false,
                    errorCode: 'INVALID_POSTAL_CODE',
                    errorMessage: 'Sender postal code is invalid or missing.'
                );
            }
            if (strlen($cleanPostCode) < 5) {
                $cleanPostCode = str_pad($cleanPostCode, 5, '0', STR_PAD_LEFT);
            }

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'postCode' => $this->ensureUtf8($cleanPostCode),
                'pickupDate' => $this->ensureUtf8($pickupDate),
            ];

            $response = $client->getPostalCodeServices($params);
            $this->logSoapCommunication($client, 'getPostalCodeServices');

            $result = $response->getPostalCodeServicesResult ?? null;

            if (is_object($result)) {
                $drFrom = (!empty($result->drPickupFrom) && (string)$result->drPickupFrom !== 'brak') ? (string) $result->drPickupFrom : null;
                $drTo = (!empty($result->drPickupTo) && (string)$result->drPickupTo !== 'brak') ? (string) $result->drPickupTo : null;
                $exFrom = (!empty($result->exPickupFrom) && (string)$result->exPickupFrom !== 'brak') ? (string) $result->exPickupFrom : null;
                $exTo = (!empty($result->exPickupTo) && (string)$result->exPickupTo !== 'brak') ? (string) $result->exPickupTo : null;
                $sat = !empty($result->pickupOnSaturday) && (bool) $result->pickupOnSaturday;

                return new PostalCodeServicesResult(
                    true,
                    $drFrom,
                    $drTo,
                    $exFrom,
                    $exTo,
                    $sat
                );
            }

            return new PostalCodeServicesResult(
                false,
                null,
                null,
                null,
                null,
                false,
                'INVALID_RESPONSE',
                'DHL WebAPI2 returned invalid response for getPostalCodeServices.'
            );
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during getPostalCodeServices: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new PostalCodeServicesResult(
                false,
                null,
                null,
                null,
                null,
                false,
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during getPostalCodeServices: %s',
                $e->getMessage()
            ));

            return new PostalCodeServicesResult(
                false,
                null,
                null,
                null,
                null,
                false,
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Book courier for existing generated shipments via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string[] $shipmentIds
     * @param string $pickupDate Format YYYY-MM-DD
     * @param string $pickupTimeFrom Format HH:MM
     * @param string $pickupTimeTo Format HH:MM
     * @param string|null $additionalInfo
     *
     * @return CourierBookingResult
     */
    public function bookCourier(
        Credentials $credentials,
        array $shipmentIds,
        string $pickupDate,
        string $pickupTimeFrom,
        string $pickupTimeTo,
        ?string $additionalInfo = null
    ): CourierBookingResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $formattedShipmentIds = array_map([$this, 'ensureUtf8'], array_values($shipmentIds));

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'pickupDate' => $this->ensureUtf8($pickupDate),
                'pickupTimeFrom' => $this->ensureUtf8($pickupTimeFrom),
                'pickupTimeTo' => $this->ensureUtf8($pickupTimeTo),
                'shipmentIdList' => [
                    'item' => $formattedShipmentIds,
                ],
            ];

            if ($additionalInfo !== null && trim($additionalInfo) !== '') {
                $params['additionalInfo'] = $this->ensureUtf8($additionalInfo);
            }

            $response = $client->bookCourier($params);
            $this->logSoapCommunication($client, 'bookCourier');

            $result = $response->bookCourierResult ?? null;

            if (is_object($result)) {
                if (!empty($result->error)) {
                    return new CourierBookingResult(
                        false,
                        null,
                        'BOOK_COURIER_ERROR',
                        (string) $result->error
                    );
                }

                $orderId = null;
                $orderStatus = null;

                if (!empty($result->id)) {
                    $orderId = (string) $result->id;
                } elseif (!empty($result->item)) {
                    $item = is_array($result->item) ? $result->item[0] : $result->item;
                    if (is_object($item)) {
                        $orderId = !empty($item->id) ? (string) $item->id : null;
                        $orderStatus = !empty($item->orderStatus) ? (string) $item->orderStatus : null;
                    } elseif (is_string($item)) {
                        $orderId = $item;
                    }
                }

                if (empty($orderStatus) && !empty($result->orderStatus)) {
                    $orderStatus = (string) $result->orderStatus;
                }

                if (empty($orderStatus)) {
                    $orderStatus = (string) __('Kurier zamówiony');
                }

                return new CourierBookingResult(true, $orderId, null, null, $orderStatus);
            }

            if (is_array($result) && !empty($result)) {
                $orderId = is_string($result[0]) ? $result[0] : (string) ($result[0]->id ?? '');
                $orderStatus = (string) __('Kurier zamówiony');
                return new CourierBookingResult(true, $orderId, null, null, $orderStatus);
            }

            return new CourierBookingResult(
                false,
                null,
                'INVALID_RESPONSE',
                'DHL WebAPI2 returned invalid response for bookCourier.'
            );
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during bookCourier: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new CourierBookingResult(
                false,
                null,
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during bookCourier: %s',
                $e->getMessage()
            ));

            return new CourierBookingResult(
                false,
                null,
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Cancel existing courier booking via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string[] $dispatchNumbers
     *
     * @return CancelCourierBookingResult
     */
    public function cancelCourierBooking(
        Credentials $credentials,
        array $dispatchNumbers
    ): CancelCourierBookingResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $formattedNumbers = array_map([$this, 'ensureUtf8'], array_values($dispatchNumbers));

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'orders' => [
                    'item' => $formattedNumbers,
                ],
            ];

            $response = $client->cancelCourierBooking($params);
            $this->logSoapCommunication($client, 'cancelCourierBooking');

            $result = $response->cancelCourierBookingResult ?? null;
            $items = $result->item ?? $result ?? null;

            if (is_array($items)) {
                $items = $items[0] ?? null;
            }

            if (is_object($items)) {
                $isSuccess = isset($items->result) ? (bool) $items->result : true;
                $errorMsg = !empty($items->error) ? (string) $items->error : null;
                $id = !empty($items->id) ? (string) $items->id : ($dispatchNumbers[0] ?? null);

                if ($isSuccess && empty($errorMsg)) {
                    return new CancelCourierBookingResult(true, $id);
                }

                return new CancelCourierBookingResult(
                    false,
                    $id,
                    'CANCEL_COURIER_ERROR',
                    $errorMsg ?: 'Failed to cancel courier booking.'
                );
            }

            if (is_bool($items) && $items === true) {
                return new CancelCourierBookingResult(true, $dispatchNumbers[0] ?? null);
            }

            return new CancelCourierBookingResult(
                false,
                $dispatchNumbers[0] ?? null,
                'INVALID_RESPONSE',
                'DHL WebAPI2 returned invalid response for cancelCourierBooking.'
            );
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during cancelCourierBooking: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new CancelCourierBookingResult(
                false,
                $dispatchNumbers[0] ?? null,
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during cancelCourierBooking: %s',
                $e->getMessage()
            ));

            return new CancelCourierBookingResult(
                false,
                $dispatchNumbers[0] ?? null,
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get tracking and status history info for a shipment via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $shipmentId
     *
     * @return TrackingResult
     */
    public function getTrackAndTraceInfo(
        Credentials $credentials,
        string $shipmentId
    ): TrackingResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'shipmentId' => $this->ensureUtf8($shipmentId),
            ];

            $response = $client->getTrackAndTraceInfo($params);
            $this->logSoapCommunication($client, 'getTrackAndTraceInfo');

            $result = $response->getTrackAndTraceInfoResult ?? null;

            if (is_object($result)) {
                $receivedBy = !empty($result->receivedBy) ? (string) $result->receivedBy : null;
                $shipmentIdResult = !empty($result->shipmentId) ? (string) $result->shipmentId : $shipmentId;
                $rawEvents = $result->events->item ?? $result->events ?? [];

                if (is_object($rawEvents)) {
                    if (isset($rawEvents->status) || isset($rawEvents->description) || isset($rawEvents->timestamp)) {
                        $rawEvents = [$rawEvents];
                    } else {
                        $rawEvents = [];
                    }
                }

                $events = [];
                if (is_array($rawEvents)) {
                    foreach ($rawEvents as $event) {
                        if (is_object($event) && (!empty($event->status) || !empty($event->description))) {
                            $events[] = [
                                'status' => !empty($event->status) ? (string) $event->status : '',
                                'description' => !empty($event->description) ? (string) $event->description : '',
                                'terminal' => !empty($event->terminal) ? (string) $event->terminal : '',
                                'timestamp' => !empty($event->timestamp) ? (string) $event->timestamp : '',
                            ];
                        }
                    }
                }

                return new TrackingResult(
                    true,
                    $shipmentIdResult,
                    $receivedBy,
                    $events
                );
            }

            return new TrackingResult(
                false,
                $shipmentId,
                null,
                [],
                'INVALID_RESPONSE',
                'DHL WebAPI2 returned invalid response for getTrackAndTraceInfo.'
            );
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during getTrackAndTraceInfo: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new TrackingResult(
                false,
                $shipmentId,
                null,
                [],
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during getTrackAndTraceInfo: %s',
                $e->getMessage()
            ));

            return new TrackingResult(
                false,
                $shipmentId,
                null,
                [],
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get shipment labels via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param array $itemsToPrint Array of items to print [['shipmentId' => string, 'labelType' => string]]
     *
     * @return GetLabelsResult
     */
    public function getLabels(Credentials $credentials, array $itemsToPrint): GetLabelsResult
    {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $formattedItems = [];
            foreach ($itemsToPrint as $item) {
                if (!empty($item['shipmentId'])) {
                    $formattedItems[] = [
                        'labelType' => $item['labelType'] ?? 'BLP',
                        'shipmentId' => (string) $item['shipmentId'],
                    ];
                }
            }

            if (empty($formattedItems)) {
                return new GetLabelsResult(false, [], 'INVALID_INPUT', 'No valid shipments provided for label printing.');
            }

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'itemsToPrint' => $formattedItems,
            ];

            $response = $client->getLabels($params);
            $this->logSoapCommunication($client, 'getLabels');

            $result = $response->getLabelsResult ?? null;

            if ($result === null) {
                return new GetLabelsResult(false, [], 'INVALID_RESPONSE', 'DHL WebAPI2 returned invalid response for getLabels.');
            }

            $itemsRaw = [];
            if (isset($result->item)) {
                $itemsRaw = is_array($result->item) ? $result->item : [$result->item];
            }

            $parsedItems = [];
            foreach ($itemsRaw as $itemObj) {
                $shipmentId = (string) ($itemObj->shipmentId ?? '');
                $labelType = (string) ($itemObj->labelType ?? 'BLP');
                $rawBase64 = (string) ($itemObj->labelData ?? '');
                $labelBinary = !empty($rawBase64) ? base64_decode($rawBase64) : '';
                $mimeType = (string) ($itemObj->labelMimeType ?? $itemObj->mimeType ?? (str_contains($labelType, 'ZBLP') ? 'text/plain' : (str_contains($labelType, 'IMG') ? 'image/png' : 'application/pdf')));

                if (!empty($labelBinary)) {
                    $parsedItems[] = [
                        'shipmentId' => $shipmentId,
                        'labelType' => $labelType,
                        'labelData' => $labelBinary,
                        'mimeType' => $mimeType,
                    ];
                }
            }

            if (empty($parsedItems)) {
                return new GetLabelsResult(false, [], 'EMPTY_LABEL', 'DHL WebAPI2 returned no label data.');
            }

            return new GetLabelsResult(true, $parsedItems);
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during getLabels: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new GetLabelsResult(
                false,
                [],
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during getLabels: %s',
                $e->getMessage()
            ));

            return new GetLabelsResult(
                false,
                [],
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get consignment note scan via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $shipmentId
     *
     * @return GetScanResult
     */
    public function getShipmentScan(Credentials $credentials, string $shipmentId): GetScanResult
    {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'shipmentId' => $this->ensureUtf8($shipmentId),
            ];

            $response = $client->getShipmentScan($params);
            $this->logSoapCommunication($client, 'getShipmentScan');

            $scanResult = $response->getShipmentScanResult ?? null;

            if ($scanResult === null) {
                return new GetScanResult(false, null, null, 'INVALID_RESPONSE', 'DHL WebAPI2 returned invalid response for getShipmentScan.');
            }

            $rawBase64 = (string) ($scanResult->scanData ?? '');
            $fileData = !empty($rawBase64) ? base64_decode($rawBase64) : null;
            $mimeType = (string) ($scanResult->scanMimeType ?? 'application/pdf');

            if (empty($fileData)) {
                return new GetScanResult(false, null, null, 'EMPTY_SCAN', 'DHL WebAPI2 returned no scan data for shipment ID ' . $shipmentId);
            }

            return new GetScanResult(true, $fileData, $mimeType);
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during getShipmentScan: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new GetScanResult(
                false,
                null,
                null,
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during getShipmentScan: %s',
                $e->getMessage()
            ));

            return new GetScanResult(
                false,
                null,
                null,
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get electronic proof of delivery (ePOD) via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $shipmentId
     *
     * @return GetEpodResult
     */
    public function getEpod(Credentials $credentials, string $shipmentId): GetEpodResult
    {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'shipmentId' => $this->ensureUtf8($shipmentId),
            ];

            $response = $client->getEpod($params);
            $this->logSoapCommunication($client, 'getEpod');

            $epodResult = $response->getEpodResult ?? null;

            if ($epodResult === null) {
                return new GetEpodResult(false, null, null, 'INVALID_RESPONSE', 'DHL WebAPI2 returned invalid response for getEpod.');
            }

            $rawBase64 = (string) ($epodResult->scanData ?? '');
            $fileData = !empty($rawBase64) ? base64_decode($rawBase64) : null;
            $mimeType = (string) ($epodResult->scanMimeType ?? 'application/pdf');

            if (empty($fileData)) {
                return new GetEpodResult(false, null, null, 'EMPTY_EPOD', 'DHL WebAPI2 returned no ePOD data for shipment ID ' . $shipmentId);
            }

            return new GetEpodResult(true, $fileData, $mimeType);
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during getEpod: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new GetEpodResult(
                false,
                null,
                null,
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during getEpod: %s',
                $e->getMessage()
            ));

            return new GetEpodResult(
                false,
                null,
                null,
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get courier confirmation report (PnP) via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $date Format YYYY-MM-DD
     * @param string $typePackage EX, DR, ALL, 2EUROPE, PR
     *
     * @return GetPnpResult
     */
    public function getPnp(
        Credentials $credentials,
        string $date,
        string $typePackage
    ): GetPnpResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'pnpRequest' => [
                    'authData' => [
                        'username' => $this->ensureUtf8($credentials->getUsername()),
                        'password' => $this->ensureUtf8($credentials->getPassword()),
                    ],
                    'date' => $this->ensureUtf8($date),
                    'type' => $this->ensureUtf8($typePackage),
                ],
            ];

            $response = $client->getPnp($params);
            $this->logSoapCommunication($client, 'getPnp');

            $pnpResult = $response->getPnpResult ?? null;

            if ($pnpResult === null) {
                return new GetPnpResult(false, null, null, null, 'INVALID_RESPONSE', 'DHL WebAPI2 returned invalid response for getPnp.');
            }

            $rawBase64 = (string) ($pnpResult->fileData ?? $pnpResult->labelData ?? '');
            $fileData = !empty($rawBase64) ? base64_decode($rawBase64) : null;
            $fileName = (string) ($pnpResult->fileName ?? sprintf('PNP_%s.pdf', $date));
            $mimeType = (string) ($pnpResult->fileMimeType ?? 'application/pdf');

            if (empty($fileData)) {
                return new GetPnpResult(false, null, null, null, 'EMPTY_PNP', 'DHL WebAPI2 returned no PnP report data for date ' . $date);
            }

            return new GetPnpResult(true, $fileData, $fileName, $mimeType);
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during getPnp: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new GetPnpResult(
                false,
                null,
                null,
                null,
                $fault->faultcode ?? 'SOAP_FAULT',
                $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during getPnp: %s',
                $e->getMessage()
            ));

            return new GetPnpResult(
                false,
                null,
                null,
                null,
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Create SoapClient instance with custom timeout, DHLApiOrgin header and WSDL caching rules.
     *
     * @param string $wsdlUrl
     *
     * @return SoapClient
     * @throws SoapFault
     */
    protected function createSoapClient(string $wsdlUrl): SoapClient
    {
        $tempDir = sys_get_temp_dir();
        $isWritable = false;

        try {
            $driver = $this->fileDriver ?? new FileDriver();
            $isWritable = $driver->isWritable($tempDir);
        } catch (Throwable) {
            $isWritable = false;
        }

        $cacheWsdl = $isWritable ? WSDL_CACHE_BOTH : WSDL_CACHE_NONE;

        $apiOrigin = 'Ideo_Magento';
        if ($this->scopeConfig !== null) {
            $configuredOrigin = (string) $this->scopeConfig->getValue(self::XML_PATH_API_ORIGIN);
            if (trim($configuredOrigin) !== '') {
                $apiOrigin = trim($configuredOrigin);
            }
        }

        $context = stream_context_create([
            'http' => [
                'header' => 'DHLApiOrgin: ' . $apiOrigin,
                'user_agent' => 'PHP-SOAP/' . PHP_VERSION . ' (Magento 2 Ideo_Dhl)',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $options = [
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => self::CONNECTION_TIMEOUT,
            'keep-alive' => true,
            'stream_context' => $context,
            'cache_wsdl' => $cacheWsdl,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'encoding' => 'UTF-8',
        ];

        $finalWsdlUrl = !str_contains($wsdlUrl, '?wsdl') && !str_starts_with($wsdlUrl, 'http://localhost')
            ? $wsdlUrl . '?wsdl'
            : $wsdlUrl;

        return new SoapClient($finalWsdlUrl, $options);
    }

    /**
     * Sanitize XML content by masking sensitive auth values.
     *
     * @param string $xml
     *
     * @return string
     */
    public function sanitizeXml(string $xml): string
    {
        if (empty($xml)) {
            return '';
        }

        $xml = $this->ensureUtf8($xml);

        $xml = (string) preg_replace(
            '/(<(?:[a-zA-Z0-9_]+:)?(?:password|username)>)[^<]+(<\/(?:[a-zA-Z0-9_]+:)?(?:password|username)>)/i',
            '$1***MASKED***$2',
            $xml
        );

        return (string) preg_replace(
            '/(<(?:[a-zA-Z0-9_]+:)?(?:labelData|scanData|fileData|cn23Data|fvProformaData|primaryFile)>)[^<]{30,}(<\/(?:[a-zA-Z0-9_]+:)?(?:labelData|scanData|fileData|cn23Data|fvProformaData|primaryFile)>)/i',
            '$1***BINARY DATA TRUNCATED***$2',
            $xml
        );
    }

    /**
     * Ensure string is valid UTF-8 for SOAP transport.
     *
     * @param string $string
     * @return string
     */
    public function ensureUtf8(string $string): string
    {
        if (mb_check_encoding($string, 'UTF-8')) {
            return $string;
        }

        $converted = mb_convert_encoding($string, 'UTF-8', ['ISO-8859-2', 'Windows-1252', 'UTF-8', 'ASCII']);
        if (mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        $result = iconv('UTF-8', 'UTF-8//IGNORE', $string);

        return $result !== false ? $result : $string;
    }

    /**
     * Log SOAP request and response dumps to dhl_api.log with sanitization.
     *
     * @param SoapClient $client
     * @param string $action
     *
     * @return void
     */
    private function logSoapCommunication(SoapClient $client, string $action): void
    {
        try {
            $isDebug = false;
            if ($this->scopeConfig !== null) {
                $isDebug = (bool) $this->scopeConfig->getValue('carriers/dhlparcel/debug');
            }

            if ($isDebug) {
                $requestHeaders = (string) $client->__getLastRequestHeaders();
                if (!empty($requestHeaders)) {
                    $this->logger->info(sprintf('[DHL SOAP Request Headers - %s] %s', $action, trim($requestHeaders)));
                }
            }

            $requestXml = $this->sanitizeXml((string) $client->__getLastRequest());
            $this->logger->info(sprintf('[DHL SOAP Request - %s] %s', $action, $requestXml));

            if ($isDebug) {
                $responseHeaders = (string) $client->__getLastResponseHeaders();
                if (!empty($responseHeaders)) {
                    $this->logger->info(sprintf('[DHL SOAP Response Headers - %s] %s', $action, trim($responseHeaders)));
                }
            }

            $responseXml = $this->sanitizeXml((string) $client->__getLastResponse());
            $this->logger->info(sprintf('[DHL SOAP Response - %s] %s', $action, $responseXml));
        } catch (Throwable $e) {
            $this->logger->warning('Failed to log SOAP communication: ' . $e->getMessage());
        }
    }

    /**
     * Create return shipment via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param array $returnShipmentFullData
     * @param int|null $orderId Optional Magento order ID for diagnostic logging context
     *
     * @return CreateShipmentReturnResult
     */
    public function createShipmentReturn(
        Credentials $credentials,
        array $returnShipmentFullData,
        ?int $orderId = null
    ): CreateShipmentReturnResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;
        $orderContext = $orderId !== null ? sprintf(' (Order #%d)', $orderId) : '';

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'shipment' => $returnShipmentFullData,
            ];

            $response = $client->createShipmentReturn($params);
            $this->logSoapCommunication($client, 'createShipmentReturn' . $orderContext);

            $result = $response->createShipmentReturnResult->item ?? $response->createShipmentReturnResult ?? null;
            if (is_array($result)) {
                $result = $result[0] ?? null;
            }

            if (is_object($result)) {
                if (!empty($result->error)) {
                    return new CreateShipmentReturnResult(
                        false,
                        false,
                        null,
                        null,
                        null,
                        null,
                        'CREATE_RETURN_ERROR',
                        (string) $result->error
                    );
                }

                $shipmentId = !empty($result->shipmentNotificationNumber)
                    ? (string) $result->shipmentNotificationNumber
                    : (!empty($result->shipmentIdentification)
                        ? (string) $result->shipmentIdentification
                        : (!empty($result->shipmentId) ? (string) $result->shipmentId : null));

                $waybill = !empty($result->waybill) ? (string) $result->waybill : $shipmentId;

                $labelData = null;
                $labelMimeType = null;

                if (!empty($result->label)) {
                    $labelObj = $result->label;
                    if (!empty($labelObj->labelContent)) {
                        $labelData = base64_decode((string) $labelObj->labelContent);
                    }
                    if (!empty($labelObj->labelMimeType)) {
                        $labelMimeType = (string) $labelObj->labelMimeType;
                    }
                }

                if (!empty($shipmentId)) {
                    return new CreateShipmentReturnResult(
                        true,
                        false,
                        $shipmentId,
                        $waybill,
                        $labelData,
                        $labelMimeType
                    );
                }
            }

            $errorMsg = is_object($result) && !empty($result->error)
                ? (string) $result->error
                : 'DHL API returned an invalid response structure for createShipmentReturn.';

            return new CreateShipmentReturnResult(false, false, null, null, null, null, 'CREATE_RETURN_ERROR', $errorMsg);
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during createShipmentReturn%s: [%s] %s',
                $orderContext,
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            $isTimeout = $this->isTimeoutException($fault);

            return new CreateShipmentReturnResult(
                false,
                $isTimeout,
                null,
                null,
                null,
                null,
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during createShipmentReturn%s: %s',
                $orderContext,
                $e->getMessage()
            ));

            $isTimeout = $this->isTimeoutException($e);

            return new CreateShipmentReturnResult(
                false,
                $isTimeout,
                null,
                null,
                null,
                null,
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }
}
