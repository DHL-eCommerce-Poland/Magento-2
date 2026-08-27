<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

interface ClientInterface
{
    /**
     * Test API connection and credentials.
     *
     * @param Credentials $credentials
     *
     * @return TestConnectionResult
     */
    public function testConnection(Credentials $credentials): TestConnectionResult;

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
    ): CreateShipmentsResult;

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
    ): DeleteShipmentResult;
}

