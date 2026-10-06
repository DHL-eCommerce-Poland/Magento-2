<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
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
     * Retrieve the DHL service type stored with a shipment entity.
     *
     * @param ShipmentDataInterface $shipment
     *
     * @return string|null
     */
    public function getServiceTypeFromEntity(ShipmentDataInterface $shipment): ?string
    {
        $data = $this->read($shipment->getShipmentData());
        $shipmentSettings = $data['shipmentSettings'] ?? null;
        if (!is_array($shipmentSettings)) {
            return null;
        }

        $serviceType = $shipmentSettings['serviceType'] ?? null;
        if (!is_string($serviceType)) {
            return null;
        }

        $serviceType = trim($serviceType);

        return $serviceType !== '' ? $serviceType : null;
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
                foreach (explode(',', $returnId) as $part) {
                    $trimmedPart = trim($part);
                    if ($trimmedPart !== '') {
                        $returnIds[] = $trimmedPart;
                    }
                }
            }
        }

        return array_values(array_unique($returnIds));
    }

    /**
     * Retrieve all unique return shipment identifiers for a shipment entity.
     *
     * @param ShipmentDataInterface $shipment
     *
     * @return string[]
     */
    public function getReturnShipmentIdsFromEntity(ShipmentDataInterface $shipment): array
    {
        $returnIds = [];
        $directReturnId = $shipment->getReturnShipmentId();
        if ($directReturnId !== null && trim($directReturnId) !== '') {
            foreach (explode(',', $directReturnId) as $id) {
                $trimmed = trim($id);
                if ($trimmed !== '') {
                    $returnIds[] = $trimmed;
                }
            }
        }

        $payload = $this->read($shipment->getShipmentData());
        foreach ($this->getReturnShipmentIds($payload) as $id) {
            if ($id !== '') {
                $returnIds[] = $id;
            }
        }

        return array_values(array_unique($returnIds));
    }
}
