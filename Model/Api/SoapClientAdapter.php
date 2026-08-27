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

        return new SoapClient($wsdlUrl, $options);
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

        return (string) preg_replace(
            '/(<(?:[a-zA-Z0-9_]+:)?(?:password|username)>)[^<]+(<\/(?:[a-zA-Z0-9_]+:)?(?:password|username)>)/i',
            '$1***MASKED***$2',
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
}
