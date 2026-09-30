<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use InvalidArgumentException;
use Magento\Framework\Serialize\SerializerInterface;

class ShipmentDataPayloadReader
{
    /**
     * @param SerializerInterface $serializer
     */
    public function __construct(private readonly SerializerInterface $serializer)
    {
    }

    /**
     * Read shipment data from an already decoded array or a serialized payload.
     *
     * @param mixed $payload
     *
     * @return array
     */
    public function read(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        try {
            $data = $this->serializer->unserialize($payload);
        } catch (InvalidArgumentException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Retrieve packages from the supported shipment data locations.
     *
     * @param array $data
     *
     * @return array
     */
    public function getPackages(array $data): array
    {
        $packages = $data['packages'] ?? null;
        if (is_array($packages)) {
            return $packages;
        }

        $shipmentSettings = $data['shipmentSettings'] ?? null;
        if (!is_array($shipmentSettings)) {
            return [];
        }

        $packages = $shipmentSettings['packages'] ?? [];

        return is_array($packages) ? $packages : [];
    }

    /**
     * Retrieve unique return shipment identifiers from package data.
     *
     * @param array $data
     *
     * @return string[]
     */
    public function getReturnShipmentIds(array $data): array
    {
        $returnIds = [];
        foreach ($this->getPackages($data) as $package) {
            if (!is_array($package)) {
                continue;
            }

            $returnId = $package['returnShipmentId'] ?? null;
            if (!is_string($returnId) && !is_int($returnId) && !is_float($returnId)) {
                continue;
            }

            $returnId = trim((string) $returnId);
            if ($returnId !== '') {
                $returnIds[] = $returnId;
            }
        }

        return array_values(array_unique($returnIds));
    }
}
