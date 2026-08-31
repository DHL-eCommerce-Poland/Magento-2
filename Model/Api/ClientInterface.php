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
    ): PostalCodeServicesResult;

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
    ): CourierBookingResult;

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
    ): CancelCourierBookingResult;

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
    ): TrackingResult;

    /**
     * Get shipment labels via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param array $itemsToPrint Array of items to print [['shipmentId' => string, 'labelType' => string]]
     *
     * @return GetLabelsResult
     */
    public function getLabels(
        Credentials $credentials,
        array $itemsToPrint
    ): GetLabelsResult;

    /**
     * Get shipment consignment note scan via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $shipmentId
     *
     * @return GetScanResult
     */
    public function getShipmentScan(
        Credentials $credentials,
        string $shipmentId
    ): GetScanResult;

    /**
     * Get electronic proof of delivery (ePOD) via DHL WebAPI2 API.
     *
     * @param Credentials $credentials
     * @param string $shipmentId
     *
     * @return GetEpodResult
     */
    public function getEpod(
        Credentials $credentials,
        string $shipmentId
    ): GetEpodResult;
}
