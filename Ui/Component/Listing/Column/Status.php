<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Ui\Component\Listing\Column;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Status extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source for status column.
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name') ?: ShipmentDataInterface::STATUS;

            foreach ($dataSource['data']['items'] as &$item) {
                $status = (string) ($item[$fieldName] ?? '');
                $item[$fieldName] = $this->formatStatus($status);
            }
        }

        return $dataSource;
    }

    /**
     * Format raw status value to localized display label.
     *
     * @param string $status
     *
     * @return string
     */
    public function formatStatus(string $status): string
    {
        $label = match ($status) {
            ShipmentDataInterface::STATUS_NEW => (string) __('New'),
            ShipmentDataInterface::STATUS_COURIER_BOOKED => (string) __('Courier Booked'),
            default => (string) __($status),
        };

        return $this->escaper->escapeHtml($label);
    }
}
