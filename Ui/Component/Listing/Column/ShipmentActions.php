<?php

declare(strict_types=1);

namespace Ideo\Dhl\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ShipmentActions extends Column
{
    private const URL_PATH_EDIT = 'ideo_dhl/shipment/edit';
    private const URL_PATH_VIEW_ORDER = 'sales/order/view';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source for shipment grid action column.
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['entity_id'])) {
                    $actions = [
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_EDIT,
                                ['entity_id' => $item['entity_id']]
                            ),
                            'label' => __('Details'),
                        ],
                    ];

                    if (isset($item['order_id']) && (int)$item['order_id'] > 0) {
                        $actions['view_order'] = [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_VIEW_ORDER,
                                ['order_id' => $item['order_id']]
                            ),
                            'label' => __('View Order'),
                        ];
                    }

                    $item[$this->getData('name')] = $actions;
                }
            }
        }

        return $dataSource;
    }
}
