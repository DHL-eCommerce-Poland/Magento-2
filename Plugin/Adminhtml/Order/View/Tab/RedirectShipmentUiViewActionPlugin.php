<?php

declare(strict_types=1);

namespace Ideo\Dhl\Plugin\Adminhtml\Order\View\Tab;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Ui\Component\Listing\Column\ViewAction;
use Psr\Log\LoggerInterface;
use Throwable;

class RedirectShipmentUiViewActionPlugin
{
    /**
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Redirect ViewAction URL in shipment grids to DHL shipment edit page if DHL entity exists.
     *
     * @param ViewAction $subject
     * @param array $dataSource
     *
     * @return array
     */
    public function afterPrepareDataSource(ViewAction $subject, array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || !is_array($dataSource['data']['items'])) {
            return $dataSource;
        }

        $viewUrlPath = (string)$subject->getData('config/viewUrlPath');
        if (!str_contains($viewUrlPath, 'order_shipment/view')) {
            return $dataSource;
        }

        $columnName = (string)$subject->getData('name');
        if (!$columnName) {
            return $dataSource;
        }

        $magentoShipmentIds = [];
        foreach ($dataSource['data']['items'] as $item) {
            $magentoShipmentId = (int) ($item['entity_id'] ?? 0);
            if ($magentoShipmentId > 0) {
                $magentoShipmentIds[] = $magentoShipmentId;
            }
        }

        $magentoShipmentIds = array_values(array_unique($magentoShipmentIds));
        if ($magentoShipmentIds === []) {
            return $dataSource;
        }

        try {
            $dhlShipments = $this->shipmentDataRepository->getListByMagentoShipmentIds($magentoShipmentIds);
        } catch (Throwable $exception) {
            $this->logger->error(
                sprintf(
                    'Error loading DHL shipment links for Magento shipment IDs: %s',
                    implode(', ', $magentoShipmentIds)
                ),
                ['exception' => $exception]
            );

            return $dataSource;
        }

        $dhlShipmentsByMagentoShipmentId = [];
        foreach ($dhlShipments as $dhlShipment) {
            $magentoShipmentId = $dhlShipment->getMagentoShipmentId();
            if ($magentoShipmentId !== null && !isset($dhlShipmentsByMagentoShipmentId[$magentoShipmentId])) {
                $dhlShipmentsByMagentoShipmentId[$magentoShipmentId] = $dhlShipment;
            }
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $magentoShipmentId = (int)($item['entity_id'] ?? 0);
            if ($magentoShipmentId <= 0 || !isset($dhlShipmentsByMagentoShipmentId[$magentoShipmentId])) {
                continue;
            }

            $dhlShipment = $dhlShipmentsByMagentoShipmentId[$magentoShipmentId];
            if ($dhlShipment->getEntityId() && isset($item[$columnName]['view']['href'])) {
                try {
                    $item[$columnName]['view']['href'] = $this->urlBuilder->getUrl(
                        'dhlparcel/shipment/edit',
                        ['entity_id' => $dhlShipment->getEntityId()]
                    );
                } catch (Throwable $exception) {
                    $this->logger->error(
                        sprintf(
                            'Error modifying ViewAction URL for shipment #%d.',
                            $magentoShipmentId
                        ),
                        ['exception' => $exception]
                    );
                }
            }
        }
        unset($item);

        return $dataSource;
    }
}
