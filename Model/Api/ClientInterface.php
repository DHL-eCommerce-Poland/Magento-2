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
     * Get international shipment parameters via DHL WebAPI2 getInternationalParams2 operation.
     *
     * @param Credentials $credentials
     *
     * @return array
     */
    public function getInternationalParams2(Credentials $credentials): array;

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
    ): CreateShipmentResult;

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
    ): GetPnpResult;

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
    ): CreateShipmentReturnResult;

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
    ): GetNearestServicepointsResult;

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
    ): array;
}
