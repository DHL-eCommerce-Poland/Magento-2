<?php

declare(strict_types=1);

namespace Ideo\Dhl\Ui\Component\Listing\Column;

use Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class PackageNumbers extends Column
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
        $direct = $item['package_numbers'] ?? null;
        if ($this->isDisplayValue($direct) && trim((string) $direct) !== '') {
            return (string) $direct;
        }

        $data = $this->payloadReader->read($item['shipment_data'] ?? null);

        // 1. Check for stored package numbers (piece IDs from getPieceId)
        if (!empty($data['packageNumbers']) && is_array($data['packageNumbers'])) {
            $filtered = $this->normalizePackageNumbers($data['packageNumbers']);
            if (!empty($filtered)) {
                return implode(', ', $filtered);
            }
        }

        // 2. Check for packageNumber / pieceId on individual package items
        $packageNumbers = [];
        foreach ($this->payloadReader->getPackages($data) as $package) {
            if (!is_array($package)) {
                continue;
            }

            foreach (['packageNumber', 'pieceId'] as $key) {
                $number = $package[$key] ?? null;
                if ($this->isDisplayValue($number) && trim((string) $number) !== '') {
                    $packageNumbers[] = trim((string) $number);
                    break;
                }
            }
        }
        $filteredNums = array_values(array_unique($packageNumbers));
        if (!empty($filteredNums)) {
            return implode(', ', $filteredNums);
        }

        // 3. Check for pieceList / package definitions in shipment data
        if (!empty($data['pieceList']) && is_array($data['pieceList'])) {
            $piecesSummary = [];
            foreach ($data['pieceList'] as $piece) {
                if (is_array($piece)) {
                    $typeValue = $piece['type'] ?? $piece['packageType'] ?? 'PACKAGE';
                    $type = $this->isDisplayValue($typeValue) ? (string) $typeValue : 'PACKAGE';
                    $quantity = $piece['quantity'] ?? 1;
                    $qty = is_numeric($quantity) ? (int) $quantity : 1;
                    $dims = [];
                    foreach (['length', 'width', 'height'] as $dimension) {
                        $value = $piece[$dimension] ?? null;
                        if ($this->isDisplayValue($value) && !empty($value)) {
                            $dims[] = (string) $value;
                        }
                    }
                    $dimStr = !empty($dims) ? implode('x', $dims) . ' cm' : '';
                    $piecesSummary[] = trim(sprintf('%dx %s %s', $qty, $type, $dimStr));
                }
            }
            if (!empty($piecesSummary)) {
                return implode(', ', $piecesSummary);
            }
        }

        // 4. Fallback to packageType if available
        $packageType = $data['packageType'] ?? null;
        if ($this->isDisplayValue($packageType) && trim((string) $packageType) !== '') {
            $quantity = $data['quantity'] ?? 1;
            $qty = is_numeric($quantity) ? (int) $quantity : 1;
            return sprintf('%dx %s', $qty, (string) $packageType);
        }

        return '';
    }

    /**
     * Normalize scalar package numbers and discard invalid or empty entries.
     *
     * @param array $values
     *
     * @return string[]
     */
    private function normalizePackageNumbers(array $values): array
    {
        $numbers = [];
        foreach ($values as $value) {
            if (!$this->isDisplayValue($value)) {
                continue;
            }

            $number = trim((string) $value);
            if ($number !== '') {
                $numbers[] = $number;
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Check whether a value can safely be converted to display text.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isDisplayValue(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value);
    }
}
