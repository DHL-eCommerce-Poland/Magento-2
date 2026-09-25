<?php

declare(strict_types=1);

namespace Ideo\Dhl\Ui\Component\Listing\Column;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Throwable;

class ReturnShipmentId extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param SerializerInterface $serializer
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly SerializerInterface $serializer,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source for return shipment ID column.
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name') ?: 'return_shipment_id';

            foreach ($dataSource['data']['items'] as &$item) {
                $item[$fieldName] = $this->formatReturnShipmentId($item);
            }
        }

        return $dataSource;
    }

    /**
     * Format return shipment IDs for a grid row item.
     *
     * @param array $item
     *
     * @return string
     */
    public function formatReturnShipmentId(array $item): string
    {
        // 1. Direct return_shipment_id column value
        if (!empty($item['return_shipment_id'])) {
            return (string) $item['return_shipment_id'];
        }

        // 2. Fallback to package returnShipmentId in shipment_data JSON
        $shipmentDataPayload = $item['shipment_data'] ?? null;
        if (empty($shipmentDataPayload)) {
            return '';
        }

        try {
            $data = is_array($shipmentDataPayload)
                ? $shipmentDataPayload
                : $this->serializer->unserialize((string) $shipmentDataPayload);

            $packages = $data['packages'] ?? ($data['shipmentSettings']['packages'] ?? []);
            if (is_array($packages) && !empty($packages)) {
                $returnIds = [];
                foreach ($packages as $pkg) {
                    if (is_array($pkg) && !empty($pkg['returnShipmentId'])) {
                        $returnIds[] = (string) $pkg['returnShipmentId'];
                    }
                }
                $filtered = array_values(array_unique(array_filter($returnIds)));
                if (!empty($filtered)) {
                    return implode(', ', $filtered);
                }
            }
        } catch (Throwable) {
            return '';
        }

        return '';
    }
}
