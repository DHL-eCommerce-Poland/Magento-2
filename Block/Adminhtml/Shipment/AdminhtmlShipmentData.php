<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Throwable;

class AdminhtmlShipmentData
{
    /**
     * @var bool
     */
    private bool $shipmentResolved = false;

    /**
     * @var ShipmentDataInterface|null
     */
    private ?ShipmentDataInterface $shipmentData = null;

    /**
     * @var bool
     */
    private bool $entityIdResolved = false;

    /**
     * @var int|null
     */
    private ?int $entityId = null;

    /**
     * @param RequestInterface $request
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get the current admin request's DHL shipment entity ID.
     *
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        if ($this->entityIdResolved) {
            return $this->entityId;
        }

        $this->entityIdResolved = true;
        $entityId = filter_var(
            $this->request->getParam('entity_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($entityId !== false) {
            $this->entityId = $entityId;
        }

        return $this->entityId;
    }

    /**
     * Get the current admin request's DHL shipment entity, loading it at most once.
     *
     * @return ShipmentDataInterface|null
     */
    public function getShipment(): ?ShipmentDataInterface
    {
        if ($this->shipmentResolved) {
            return $this->shipmentData;
        }

        $this->shipmentResolved = true;
        $entityId = $this->getEntityId();
        if ($entityId === null) {
            return null;
        }

        try {
            $this->shipmentData = $this->shipmentDataRepository->getById($entityId);
        } catch (NoSuchEntityException) {
            return null;
        } catch (Throwable $exception) {
            $this->logger->error(
                'Failed to load DHL shipment data for the admin shipment form.',
                ['entity_id' => $entityId, 'exception' => $exception]
            );
        }

        return $this->shipmentData;
    }
}
