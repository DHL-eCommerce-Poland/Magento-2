<?php

declare(strict_types=1);

namespace Ideo\Dhl\Ui\Component\Listing\Column;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Throwable;

class PackageNumbers extends Column
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
     * Prepare Data Source for package numbers column.
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name') ?: 'package_numbers';

            foreach ($dataSource['data']['items'] as &$item) {
                $item[$fieldName] = $this->formatPackageNumbers($item);
            }
        }

        return $dataSource;
    }

    /**
     * Format package numbers or package details for a grid row item.
     *
     * @param array $item
     *
     * @return string
     */
    public function formatPackageNumbers(array $item): string
    {
        $shipmentDataPayload = $item['shipment_data'] ?? null;
        if (empty($shipmentDataPayload)) {
            return '';
        }

        try {
            $data = is_array($shipmentDataPayload)
                ? $shipmentDataPayload
                : $this->serializer->unserialize((string) $shipmentDataPayload);
        } catch (Throwable) {
            return '';
        }

        if (!is_array($data)) {
            return '';
        }

        // 1. Check for stored package numbers (blpPieceId / piece IDs)
        if (!empty($data['packageNumbers']) && is_array($data['packageNumbers'])) {
            $filtered = array_filter(array_map('trim', $data['packageNumbers']));
            if (!empty($filtered)) {
                return implode(', ', $filtered);
            }
        }

        // 2. Check for pieceList / package definitions in shipment data
        if (!empty($data['pieceList']) && is_array($data['pieceList'])) {
            $piecesSummary = [];
            foreach ($data['pieceList'] as $piece) {
                if (is_array($piece)) {
                    $type = $piece['type'] ?? $piece['packageType'] ?? 'PACKAGE';
                    $qty = (int) ($piece['quantity'] ?? 1);
                    $dims = array_filter([
                        $piece['length'] ?? null,
                        $piece['width'] ?? null,
                        $piece['height'] ?? null,
                    ]);
                    $dimStr = !empty($dims) ? implode('x', $dims) . ' cm' : '';
                    $piecesSummary[] = trim(sprintf('%dx %s %s', $qty, $type, $dimStr));
                }
            }
            if (!empty($piecesSummary)) {
                return implode(', ', $piecesSummary);
            }
        }

        // 3. Fallback to packageType if available
        if (!empty($data['packageType'])) {
            $qty = (int) ($data['quantity'] ?? 1);
            return sprintf('%dx %s', $qty, (string) $data['packageType']);
        }

        return '';
    }
}
