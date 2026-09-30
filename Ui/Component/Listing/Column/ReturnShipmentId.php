<?php

declare(strict_types=1);

namespace Ideo\Dhl\Ui\Component\Listing\Column;

use Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ReturnShipmentId extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ShipmentDataPayloadReader $payloadReader
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ShipmentDataPayloadReader $payloadReader,
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
        $direct = $item['return_shipment_id'] ?? null;
        if ((is_string($direct) || is_int($direct) || is_float($direct)) && !empty($direct)) {
            return (string) $direct;
        }

        $data = $this->payloadReader->read($item['shipment_data'] ?? null);
        $returnIds = $this->payloadReader->getReturnShipmentIds($data);
        if (!empty($returnIds)) {
            return implode(', ', $returnIds);
        }

        return '';
    }
}
