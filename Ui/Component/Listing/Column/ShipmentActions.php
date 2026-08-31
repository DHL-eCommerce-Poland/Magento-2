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
    private const URL_PATH_UPDATE_STATUS = 'ideo_dhl/shipment/updateStatus';
    private const URL_PATH_DELETE = 'ideo_dhl/shipment/delete';
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

                    if (!empty($item['shipment_id'])) {
                        $actions['print_label'] = [
                            'href' => $this->urlBuilder->getUrl(
                                'ideo_dhl/shipment/printLabel',
                                ['entity_id' => $item['entity_id']]
                            ),
                            'label' => __('Print Label'),
                            'target' => '_blank',
                        ];

                        $actions['update_status'] = [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_UPDATE_STATUS,
                                ['entity_id' => $item['entity_id']]
                            ),
                            'label' => __('Update Status'),
                        ];
                    }

                    if (empty($item['dispatch_number'])) {
                        $actions['book_courier'] = [
                            'href' => '#',
                            'label' => __('Order Courier'),
                            'callback' => [
                                'provider' => 'ideo_dhl_shipment_listing.ideo_dhl_shipment_listing.ideo_dhl_shipment_columns.actions',
                                'target' => 'bookCourierRow',
                            ],
                        ];
                    } else {
                        $actions['cancel_courier'] = [
                            'href' => $this->urlBuilder->getUrl(
                                'ideo_dhl/shipment/cancelCourierBooking',
                                ['entity_id' => $item['entity_id']]
                            ),
                            'label' => __('Cancel Courier'),
                            'confirm' => [
                                'title' => __('Cancel Courier Booking'),
                                'message' => __(
                                    'Are you sure you want to cancel courier booking for this shipment?'
                                ),
                            ],
                            'post' => true,
                        ];
                    }

                    if (isset($item['order_id']) && (int)$item['order_id'] > 0) {
                        $actions['view_order'] = [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_VIEW_ORDER,
                                ['order_id' => $item['order_id']]
                            ),
                            'label' => __('View Order'),
                        ];
                    }

                    if (empty($item['dispatch_number'])) {
                        $actions['delete'] = [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_DELETE,
                                ['entity_id' => $item['entity_id']]
                            ),
                            'label' => __('Delete'),
                            'confirm' => [
                                'title' => __('Delete Shipment'),
                                'message' => __(
                                    'Are you sure you want to delete this shipment? It will be cancelled in DHL and removed from Magento.'
                                ),
                            ],
                            'post' => true,
                        ];
                    }

                    $item[$this->getData('name')] = $actions;
                }
            }
        }

        return $dataSource;
    }
}
