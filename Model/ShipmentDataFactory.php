<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Magento\Framework\ObjectManagerInterface;

class ShipmentDataFactory
{
    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * Create instance of ShipmentData model.
     *
     * @param array $data
     *
     * @return ShipmentDataInterface
     */
    public function create(array $data = []): ShipmentDataInterface
    {
        return $this->objectManager->create(ShipmentData::class, $data);
    }
}
