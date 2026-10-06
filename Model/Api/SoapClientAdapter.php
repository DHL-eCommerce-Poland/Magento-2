<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

use Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer;
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
    private const API_ORIGIN = 'Ideo_M2';
    private const MAX_ITEMS_TO_PRINT_PER_REQUEST = 3;

    /**
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param ShipmentDateSanitizer $dateSanitizer
     * @param FileDriver $fileDriver
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ShipmentDateSanitizer $dateSanitizer,
        private readonly FileDriver $fileDriver
    ) {
    }

    /**
     * Test API connection and credentials using getInternationalParams2 operation.
     *
     * @param Credentials $credentials
     *
     * @return TestConnectionResult
     */
    public function testConnection(Credentials $credentials): TestConnectionResult
    {
        try {
            $this->getInternationalParams2($credentials);

            return new TestConnectionResult(
                true,
                (string) __('Connection to DHL WebAPI2 succeeded. Authentication valid.')
            );
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during testConnection: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $this->redactCredentials($fault->getMessage(), $credentials)
            ));

            return new TestConnectionResult(
                false,
                (string) __('DHL connection failed. Verify the API credentials and try again.'),
                $fault->faultcode ?? 'SOAP_FAULT'
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during testConnection: %s',
                $this->redactCredentials($e->getMessage(), $credentials)
            ));

            return new TestConnectionResult(
                false,
                (string) __('DHL connection failed. Verify the API credentials and try again.'),
                'CONNECTION_ERROR'
            );
        }
    }

    /**
     * Get international shipment parameters via DHL WebAPI2 getInternationalParams2 operation.
     *
     * @param Credentials $credentials
     *
     * @return array
     * @throws SoapFault|Throwable
     */
    public function getInternationalParams2(Credentials $credentials): array
    {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;
        $client = $this->createSoapClient($wsdlUrl);

        $params = [
            'authData' => [
                'username' => $this->ensureUtf8($credentials->getUsername()),
                'password' => $this->ensureUtf8($credentials->getPassword()),
            ],
        ];

        $response = $client->getInternationalParams2($params);
        $this->logSoapCommunication($client, 'getInternationalParams2', true);

        return (array) ($response->getInternationalParams2Result ?? []);
    }

    /**
     * Create shipment via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param array $shipmentData
     * @param int|null $orderId
     *
     * @return CreateShipmentResult
     */
    public function createShipment(
        Credentials $credentials,
        array $shipmentData,
        ?int $orderId = null
    ): CreateShipmentResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;
        $orderContext = $orderId !== null ? sprintf(' (Order #%d)', $orderId) : '';

        if (!isset($shipmentData['shipmentInfo']['dropOffType'])) {
            $shipmentData['shipmentInfo']['dropOffType'] = CreateShipmentResult::DROP_OFF_TYPE_REGULAR_PICKUP;
        }

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'shipment' => $this->preserveFractionalPackageMeasurements($shipmentData),
            ];

            $response = $client->createShipment($params);
            $this->logSoapCommunication($client, 'createShipment' . $orderContext);

            $shipmentResult = $response->createShipmentResult ?? $response->createShipmentResponse ?? $response ?? null;
            if (is_array($shipmentResult)) {
                $shipmentResult = $shipmentResult[0] ?? null;
            }

            $shipmentId = !empty($shipmentResult->shipmentNotificationNumber)
                ? (string) $shipmentResult->shipmentNotificationNumber
                : (!empty($shipmentResult->shipmentId) ? (string) $shipmentResult->shipmentId : null);

            if (is_object($shipmentResult) && !empty($shipmentId)) {
                $orderStatus = !empty($shipmentResult->orderStatus) ? (string) $shipmentResult->orderStatus : null;
                $labelContent = !empty($shipmentResult->labelContent) ? (string) $shipmentResult->labelContent : null;
                $labelFormat = !empty($shipmentResult->labelFormat) ? (string) $shipmentResult->labelFormat : null;
                $labelType = !empty($shipmentResult->labelType) ? (string) $shipmentResult->labelType : null;

                return new CreateShipmentResult(
                    success: true,
                    timeout: false,
                    shipmentId: $shipmentId,
                    orderStatus: $orderStatus,
                    packageNumbers: [],
                    errorCode: null,
                    errorMessage: null,
                    labelContent: $labelContent,
                    labelFormat: $labelFormat,
                    labelType: $labelType
                );
            }

            $errorMsg = is_object($shipmentResult) && !empty($shipmentResult->error)
                ? (string) $shipmentResult->error
                : 'DHL API returned an invalid response structure.';

            $this->logger->error(sprintf(
                '[DHL WebAPI2] createShipment failed%s: %s',
                $orderContext,
                $errorMsg
            ));

            return new CreateShipmentResult(
                success: false,
                errorCode: 'CREATE_SHIPMENT_ERROR',
                errorMessage: $errorMsg
            );
        } catch (SoapFault $fault) {
            $this->logSoapCommunication($client ?? null, 'createShipment' . $orderContext);
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during createShipment%s: [%s] %s',
                $orderContext,
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            $isTimeout = $this->isTimeoutException($fault);

            return new CreateShipmentResult(
                success: false,
                timeout: $isTimeout,
                errorCode: $fault->faultcode ?? 'SOAP_FAULT',
                errorMessage: 'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logSoapCommunication($client ?? null, 'createShipment' . $orderContext);
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during createShipment%s: %s',
                $orderContext,
                $e->getMessage()
            ));

            $isTimeout = $this->isTimeoutException($e);

            return new CreateShipmentResult(
                success: false,
                timeout: $isTimeout,
                errorCode: 'CONNECTION_ERROR',
                errorMessage: 'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Delete shipment and optional return shipments via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $shipmentId
     * @param string[] $returnShipmentIds
     *
     * @return DeleteShipmentResult
     */
    public function deleteShipment(
        Credentials $credentials,
        string $shipmentId,
        array $returnShipmentIds = []
    ): DeleteShipmentResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $rawShipmentIds = array_merge([$shipmentId], $returnShipmentIds);
            $cleanShipmentIds = [];
            foreach ($rawShipmentIds as $id) {
                if (is_string($id) && trim($id) !== '') {
                    $cleanShipmentIds[] = trim($id);
                }
            }
            $cleanShipmentIds = array_values(array_unique($cleanShipmentIds));
            $formattedShipmentIds = array_map([$this, 'ensureUtf8'], $cleanShipmentIds);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'shipments' => [
                    'item' => count($formattedShipmentIds) === 1
                        ? $formattedShipmentIds[0]
                        : $formattedShipmentIds,
                ],
            ];

            $response = $client->deleteShipments($params);
            $this->logSoapCommunication($client, 'deleteShipments');

            $resultItem = $response->deleteShipmentsResult->item ?? $response->deleteShipmentsResult ?? null;
            $items = is_array($resultItem) ? $resultItem : ($resultItem !== null ? [$resultItem] : []);

            $errors = [];
            $hasSuccess = false;
            $allSuccess = !empty($items);

            foreach ($items as $item) {
                if (is_object($item)) {
                    if (!empty($item->error)) {
                        $allSuccess = false;
                        $ident = $item->shipmentIdentification ?? $item->id ?? null;
                        $errors[] = $ident ? sprintf('[%s] %s', $ident, $item->error) : (string) $item->error;
                    } else {
                        $hasId = !empty($item->id)
                            || !empty($item->shipmentIdentification)
                            || (isset($item->result) && (bool) $item->result === true);
                        if ($hasId) {
                            $hasSuccess = true;
                        } else {
                            $allSuccess = false;
                        }
                    }
                } elseif (is_bool($item) && $item === true) {
                    $hasSuccess = true;
                } else {
                    $allSuccess = false;
                }
            }

            if ($allSuccess && $hasSuccess) {
                return new DeleteShipmentResult(true, $shipmentId);
            }

            $errorMsg = !empty($errors)
                ? implode('; ', $errors)
                : 'DHL API returned an error or invalid response during deleteShipments.';

            return new DeleteShipmentResult(false, $shipmentId, 'DELETE_SHIPMENT_ERROR', $errorMsg);
        } catch (SoapFault $fault) {
            $this->logSoapCommunication($client ?? null, 'deleteShipments');
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
            $this->logSoapCommunication($client ?? null, 'deleteShipments');
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

            $cleanDate = $this->dateSanitizer->sanitize($pickupDate);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'pickupDate' => $this->ensureUtf8($cleanDate),
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
                        'labelType' => $item['labelType'] ?? GetLabelsResult::LABEL_TYPE_BLP,
                        'shipmentId' => (string) $item['shipmentId'],
                    ];
                }
            }

            if (empty($formattedItems)) {
                return new GetLabelsResult(
                    false,
                    [],
                    'INVALID_INPUT',
                    'No valid shipments provided for label printing.'
                );
            }

            $chunks = array_chunk($formattedItems, self::MAX_ITEMS_TO_PRINT_PER_REQUEST);
            $parsedItems = [];

            foreach ($chunks as $chunkIndex => $chunk) {
                $params = [
                    'authData' => [
                        'username' => $this->ensureUtf8($credentials->getUsername()),
                        'password' => $this->ensureUtf8($credentials->getPassword()),
                    ],
                    'itemsToPrint' => $chunk,
                ];

                $response = $client->getLabels($params);
                $this->logSoapCommunication(
                    $client,
                    count($chunks) > 1 ? sprintf('getLabels_batch_%d', $chunkIndex + 1) : 'getLabels'
                );

                $result = $response->getLabelsResult ?? null;

                if ($result === null) {
                    return new GetLabelsResult(
                        false,
                        [],
                        'INVALID_RESPONSE',
                        'DHL WebAPI2 returned invalid response for getLabels.'
                    );
                }

                $itemsRaw = [];
                if (isset($result->item)) {
                    $itemsRaw = is_array($result->item) ? $result->item : [$result->item];
                }

                foreach ($itemsRaw as $itemObj) {
                    $shipmentId = (string) ($itemObj->shipmentId ?? '');
                    $labelType = (string) ($itemObj->labelType ?? GetLabelsResult::LABEL_TYPE_BLP);
                    $rawBase64 = (string) ($itemObj->labelData ?? '');
                    // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                    $labelBinary = !empty($rawBase64) ? base64_decode($rawBase64) : '';

                    $defaultMime = str_contains($labelType, GetLabelsResult::LABEL_TYPE_ZBLP)
                        ? 'text/plain'
                        : (str_contains($labelType, 'IMG') ? 'image/png' : 'application/pdf');
                    $mimeType = (string) ($itemObj->labelMimeType ?? ($itemObj->mimeType ?? $defaultMime));

                    if (!empty($labelBinary)) {
                        $parsedItems[] = [
                            'shipmentId' => $shipmentId,
                            'labelType' => $labelType,
                            'labelData' => $labelBinary,
                            'mimeType' => $mimeType,
                        ];
                    }
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
                return new GetScanResult(
                    false,
                    null,
                    null,
                    'INVALID_RESPONSE',
                    'DHL WebAPI2 returned invalid response for getShipmentScan.'
                );
            }

            $rawBase64 = (string) ($scanResult->scanData ?? '');
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $fileData = !empty($rawBase64) ? base64_decode($rawBase64) : null;
            $mimeType = (string) ($scanResult->scanMimeType ?? 'application/pdf');

            if (empty($fileData)) {
                return new GetScanResult(
                    false,
                    null,
                    null,
                    'EMPTY_SCAN',
                    'DHL WebAPI2 returned no scan data for shipment ID ' . $shipmentId
                );
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
                return new GetEpodResult(
                    false,
                    null,
                    null,
                    'INVALID_RESPONSE',
                    'DHL WebAPI2 returned invalid response for getEpod.'
                );
            }

            $rawBase64 = (string) ($epodResult->scanData ?? '');
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $fileData = !empty($rawBase64) ? base64_decode($rawBase64) : null;
            $mimeType = (string) ($epodResult->scanMimeType ?? 'application/pdf');

            if (empty($fileData)) {
                return new GetEpodResult(
                    false,
                    null,
                    null,
                    'EMPTY_EPOD',
                    'DHL WebAPI2 returned no ePOD data for shipment ID ' . $shipmentId
                );
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
                return new GetPnpResult(
                    false,
                    null,
                    null,
                    null,
                    'INVALID_RESPONSE',
                    'DHL WebAPI2 returned invalid response for getPnp.'
                );
            }

            $rawBase64 = (string) ($pnpResult->fileData ?? ($pnpResult->labelData ?? ''));
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $fileData = !empty($rawBase64) ? base64_decode($rawBase64) : null;
            $fileName = (string) ($pnpResult->fileName ?? sprintf('PNP_%s.pdf', $date));
            $mimeType = (string) ($pnpResult->fileMimeType ?? 'application/pdf');

            if (empty($fileData)) {
                return new GetPnpResult(
                    false,
                    null,
                    null,
                    null,
                    'EMPTY_PNP',
                    'DHL WebAPI2 returned no PnP report data for date ' . $date
                );
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
            '/(<(?:[a-zA-Z0-9_]+:)?password>)[^<]+(<\/(?:[a-zA-Z0-9_]+:)?password>)/i',
            '$1***MASKED***$2',
            $xml
        );

        $binPattern = '/(<(?:[a-zA-Z0-9_]+:)?'
            . '(?:labelData|labelContent|scanData|fileData|cn23Data|fvProformaData|primaryFile|invoice)>)'
            . '[^<]{30,}'
            . '(<\/(?:[a-zA-Z0-9_]+:)?'
            . '(?:labelData|labelContent|scanData|fileData|cn23Data|fvProformaData|primaryFile|invoice)>)/i';

        $xml = (string) preg_replace($binPattern, '$1***BINARY DATA TRUNCATED***$2', $xml);

        return trim((string) preg_replace('/\s+/', ' ', $xml));
    }

    /**
     * Ensure string is valid UTF-8 for SOAP transport.
     *
     * @param string $string
     *
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

            $rawResult = $response->createShipmentReturnResult->item ?? $response->createShipmentReturnResult ?? null;
            $rawItems = is_array($rawResult) ? $rawResult : ($rawResult !== null ? [$rawResult] : []);

            $parsedItems = [];
            $firstError = null;

            foreach ($rawItems as $itemObj) {
                if (!is_object($itemObj)) {
                    continue;
                }

                if (!empty($itemObj->error)) {
                    $firstError = (string) $itemObj->error;
                    continue;
                }

                $shipmentId = !empty($itemObj->shipmentNotificationNumber)
                    ? (string) $itemObj->shipmentNotificationNumber
                    : (!empty($itemObj->shipmentTrackingNumber)
                        ? (string) $itemObj->shipmentTrackingNumber
                        : (!empty($itemObj->shipmentIdentification)
                            ? (string) $itemObj->shipmentIdentification
                            : (!empty($itemObj->shipmentId) ? (string) $itemObj->shipmentId : null)));

                if (empty($shipmentId)) {
                    continue;
                }

                $waybill = !empty($itemObj->waybill)
                    ? (string) $itemObj->waybill
                    : (!empty($itemObj->shipmentTrackingNumber)
                        ? (string) $itemObj->shipmentTrackingNumber
                        : $shipmentId);

                $labelData = null;
                $labelMimeType = null;

                if (!empty($itemObj->label)) {
                    $labelObj = $itemObj->label;
                    if (!empty($labelObj->labelContent)) {
                        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                        $labelData = base64_decode((string) $labelObj->labelContent);
                    }
                    $labelMimeType = !empty($labelObj->labelFormat)
                        ? (string) $labelObj->labelFormat
                        : (!empty($labelObj->labelMimeType) ? (string) $labelObj->labelMimeType : null);
                }

                $parsedItems[] = [
                    'shipmentId' => $shipmentId,
                    'waybill' => $waybill,
                    'labelData' => $labelData,
                    'labelMimeType' => $labelMimeType,
                ];
            }

            if (!empty($parsedItems)) {
                return new CreateShipmentReturnResult(
                    success: true,
                    timeout: false,
                    items: $parsedItems
                );
            }

            $errorMsg = $firstError
                ?: 'DHL API returned an invalid response structure for createShipmentReturn.';

            return new CreateShipmentReturnResult(
                success: false,
                timeout: false,
                items: [],
                errorCode: 'CREATE_RETURN_ERROR',
                errorMessage: $errorMsg
            );
        } catch (SoapFault $fault) {
            $this->logSoapCommunication($client ?? null, 'createShipmentReturn' . $orderContext);
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during createShipmentReturn%s: [%s] %s',
                $orderContext,
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            $isTimeout = $this->isTimeoutException($fault);

            return new CreateShipmentReturnResult(
                success: false,
                timeout: $isTimeout,
                items: [],
                errorCode: $fault->faultcode ?? 'SOAP_FAULT',
                errorMessage: 'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logSoapCommunication($client ?? null, 'createShipmentReturn' . $orderContext);
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during createShipmentReturn%s: %s',
                $orderContext,
                $e->getMessage()
            ));

            $isTimeout = $this->isTimeoutException($e);

            return new CreateShipmentReturnResult(
                success: false,
                timeout: $isTimeout,
                items: [],
                errorCode: 'CONNECTION_ERROR',
                errorMessage: 'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get nearest DHL service points for recipient address via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $country
     * @param string $postCode
     * @param string $city
     * @param int $radius
     *
     * @return GetNearestServicepointsResult
     */
    public function getNearestServicepoints(
        Credentials $credentials,
        string $country,
        string $postCode,
        string $city = '',
        int $radius = 15
    ): GetNearestServicepointsResult {
        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $cleanCountry = strtoupper(trim($country)) ?: 'PL';
            if ($cleanCountry === 'PL') {
                $cleanPostCode = (string) preg_replace('/[^0-9]/', '', $postCode);
                if (strlen($cleanPostCode) < 5 && !empty($cleanPostCode)) {
                    $cleanPostCode = str_pad($cleanPostCode, 5, '0', STR_PAD_LEFT);
                }
            } else {
                $cleanPostCode = trim($postCode);
            }

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'structure' => [
                    'country' => $this->ensureUtf8($cleanCountry),
                    'postcode' => $this->ensureUtf8($cleanPostCode),
                    'city' => $this->ensureUtf8($city),
                    'radius' => $radius,
                ],
            ];

            $response = $client->getNearestServicepoints($params);
            $this->logSoapCommunication($client, 'getNearestServicepoints');

            $result = $response->getNearestServicepointsResult ?? null;

            if (is_object($result) && isset($result->points)) {
                $rawPoints = $result->points->item ?? $result->points ?? [];
                if (is_object($rawPoints)) {
                    $rawPoints = [$rawPoints];
                }

                $pointsArr = [];
                if (is_array($rawPoints)) {
                    foreach ($rawPoints as $pointObj) {
                        if (is_object($pointObj)) {
                            $addr = $pointObj->address ?? null;
                            $pointsArr[] = [
                                'sap' => (string) ($pointObj->sap ?? ''),
                                'name' => (string) ($pointObj->name ?? ''),
                                'type' => (string) ($pointObj->type ?? ''),
                                'description' => (string) ($pointObj->description ?? ''),
                                'longitude' => (string) ($pointObj->longitude ?? ''),
                                'latitude' => (string) ($pointObj->latitude ?? ''),
                                'street' => is_object($addr) ? (string) ($addr->street ?? '') : '',
                                'houseNumber' => is_object($addr) ? (string) ($addr->houseNumber ?? '') : '',
                                'postcode' => is_object($addr) ? (string) ($addr->postcode ?? '') : '',
                                'city' => is_object($addr) ? (string) ($addr->city ?? '') : '',
                            ];
                        }
                    }
                }

                if (empty($pointsArr)) {
                    return new GetNearestServicepointsResult(
                        false,
                        [],
                        'NO_POINTS_FOUND',
                        'No DHL service points found for given postal code.'
                    );
                }

                return new GetNearestServicepointsResult(true, $pointsArr);
            }

            return new GetNearestServicepointsResult(
                false,
                [],
                'INVALID_RESPONSE',
                'DHL WebAPI2 returned invalid response for getNearestServicepoints.'
            );
        } catch (SoapFault $fault) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] SoapFault during getNearestServicepoints: [%s] %s',
                $fault->faultcode ?? 'FAULT',
                $fault->getMessage()
            ));

            return new GetNearestServicepointsResult(
                false,
                [],
                $fault->faultcode ?? 'SOAP_FAULT',
                'DHL API error: ' . $fault->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during getNearestServicepoints: %s',
                $e->getMessage()
            ));

            return new GetNearestServicepointsResult(
                false,
                [],
                'CONNECTION_ERROR',
                'An error occurred while connecting to DHL: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get piece IDs / package tracking numbers for a shipment via DHL WebAPI2 getPieceId endpoint.
     *
     * @param Credentials $credentials
     * @param string $shipmentId
     *
     * @return string[]
     */
    public function getPieceId(
        Credentials $credentials,
        string $shipmentId
    ): array {
        if (empty(trim($shipmentId))) {
            return [];
        }

        $wsdlUrl = $credentials->isSandbox() ? self::WSDL_URL_SANDBOX : self::WSDL_URL_PRODUCTION;

        try {
            $client = $this->createSoapClient($wsdlUrl);

            $params = [
                'authData' => [
                    'username' => $this->ensureUtf8($credentials->getUsername()),
                    'password' => $this->ensureUtf8($credentials->getPassword()),
                ],
                'request' => [
                    'items' => [
                        'item' => [
                            [
                                'shipmentNumber' => $this->ensureUtf8($shipmentId),
                            ],
                        ],
                    ],
                ],
            ];

            $response = $client->getPieceId($params);
            $this->logSoapCommunication($client, 'getPieceId');

            $resultItem = $response->getPieceIdResult->shipments->item
                ?? $response->getPieceIdResult->shipments
                ?? $response->getPieceIdResult->item
                ?? $response->getPieceIdResult
                ?? null;
            if (is_array($resultItem)) {
                $resultItem = $resultItem[0] ?? null;
            }

            return $this->extractPieceIds($resultItem);
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL WebAPI2] Exception during getPieceId for shipment #%s: %s',
                $shipmentId,
                $e->getMessage()
            ));
            return [];
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
            $isWritable = $this->fileDriver->isWritable($tempDir);
        } catch (Throwable) {
            $isWritable = false;
        }

        $cacheWsdl = $isWritable ? WSDL_CACHE_BOTH : WSDL_CACHE_NONE;

        try {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $context = stream_context_create([
                'http' => [
                    'header' => 'DHLApiOrgin: ' . self::API_ORIGIN,
                    'user_agent' => 'PHP-SOAP/' . PHP_VERSION . ' (Magento 2 Ideo_Dhl)',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
        } catch (Throwable) {
            $context = null;
        }

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
     * Log SOAP request and response dumps to dhl_api.log with sanitization.
     *
     * @param SoapClient|null $client
     * @param string $action
     * @param bool $skipResponseBody Option to skip logging response body (e.g. for connection test endpoints)
     *
     * @return void
     */
    private function logSoapCommunication(
        ?SoapClient $client,
        string $action,
        bool $skipResponseBody = false
    ): void {
        if ($client === null) {
            return;
        }
        try {
            $isDebug = false;
            if ($this->scopeConfig !== null) {
                $isDebug = (bool) $this->scopeConfig->getValue('carriers/dhlpoland/debug');
            }

            if ($isDebug) {
                $requestHeaders = (string) $client->__getLastRequestHeaders();
                if (!empty($requestHeaders)) {
                    $logMsg = sprintf('[DHL SOAP Request Headers - %s] %s', $action, trim($requestHeaders));
                    $this->logger->info($logMsg);
                }
            }

            $lastRequest = (string) $client->__getLastRequest();
            if (!empty($lastRequest)) {
                $requestXml = $this->sanitizeXml($lastRequest);
                $this->logger->info(sprintf('[DHL SOAP Request - %s] %s', $action, $requestXml));
            }

            if ($isDebug) {
                $responseHeaders = (string) $client->__getLastResponseHeaders();
                if (!empty($responseHeaders)) {
                    $logMsg = sprintf('[DHL SOAP Response Headers - %s] %s', $action, trim($responseHeaders));
                    $this->logger->info($logMsg);
                }
            }

            $lastResponse = (string) $client->__getLastResponse();
            if (!empty($lastResponse)) {
                if ($skipResponseBody) {
                    $this->logger->info(sprintf('[DHL SOAP Response - %s] [RESPONSE_BODY_OMITTED]', $action));
                } else {
                    $responseXml = $this->sanitizeXml($lastResponse);
                    $this->logger->info(sprintf('[DHL SOAP Response - %s] %s', $action, $responseXml));
                }
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to log SOAP communication: ' . $e->getMessage());
        }
    }

    /**
     * Helper to extract piece ID strings from getPieceId result item.
     *
     * @param mixed $resultItem
     *
     * @return string[]
     */
    private function extractPieceIds(mixed $resultItem): array
    {
        if (!is_object($resultItem) && !is_array($resultItem)) {
            return [];
        }

        $pieceList = [];
        if (is_object($resultItem)) {
            $rawPieces = $resultItem->packages->item
                ?? $resultItem->packages
                ?? $resultItem->abstractPiece->item
                ?? $resultItem->abstractPiece
                ?? $resultItem->pieces->item
                ?? $resultItem->pieces
                ?? $resultItem->pieceList->item
                ?? $resultItem->pieceList
                ?? null;
            if ($rawPieces !== null) {
                $pieceList = is_array($rawPieces) ? $rawPieces : [$rawPieces];
            }
        } elseif (is_array($resultItem)) {
            $pieceList = $resultItem;
        }

        $packageNumbers = [];
        foreach ($pieceList as $piece) {
            if (is_object($piece)) {
                $pieceId = $piece->blpPieceId ?? $piece->pieceId ?? $piece->packageNumber ?? $piece->id ?? null;
                if ($pieceId !== null && (string)$pieceId !== '') {
                    $packageNumbers[] = (string)$pieceId;
                }
            } elseif (is_string($piece) || is_numeric($piece)) {
                if (trim((string)$piece) !== '') {
                    $packageNumbers[] = trim((string)$piece);
                }
            }
        }

        return array_values(array_unique($packageNumbers));
    }

    /**
     * Check if exception message or code indicates a network/SOAP timeout.
     *
     * @param Throwable $e
     *
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
     * Remove DHL credentials from connection-test diagnostic messages.
     *
     * @param string $message
     * @param Credentials $credentials
     *
     * @return string
     */
    private function redactCredentials(string $message, Credentials $credentials): string
    {
        foreach ([$credentials->getUsername(), $credentials->getPassword()] as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[redacted]', $message);
            }
        }

        return $message;
    }

    /**
     * Force fractional package measurements to be serialized as XML floats for createShipment.
     *
     * DHL's WSDL declares these fields as integers, which makes SoapClient truncate fractional values.
     *
     * @param array $shipmentData
     *
     * @return array
     */
    private function preserveFractionalPackageMeasurements(array $shipmentData): array
    {
        $packageItems = $shipmentData['pieceList']['item'] ?? null;
        if (!is_array($packageItems)) {
            return $shipmentData;
        }

        foreach ($packageItems as $packageIndex => $package) {
            if (!is_array($package)) {
                continue;
            }

            foreach (['weight', 'width', 'height', 'length'] as $measurement) {
                $value = $package[$measurement] ?? null;
                if ($value !== null && $value !== '' && is_numeric($value)) {
                    $package[$measurement] = new \SoapVar((float) $value, XSD_FLOAT);
                }
            }

            $packageItems[$packageIndex] = $package;
        }

        $shipmentData['pieceList']['item'] = $packageItems;

        return $shipmentData;
    }
}
