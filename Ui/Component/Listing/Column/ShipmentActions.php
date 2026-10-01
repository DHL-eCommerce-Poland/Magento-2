<?php

declare(strict_types=1);

namespace Ideo\Dhl\Ui\Component\Listing\Column;

use Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ShipmentActions extends Column
{
    private const URL_PATH_EDIT = 'dhlpoland/shipment/edit';
    private const URL_PATH_CREATE_RETURN = 'dhlpoland/shipment/createReturnShipment';
    private const URL_PATH_PRINT_RETURN_LABEL = 'dhlpoland/shipment/printReturnLabel';
    private const URL_PATH_PRINT_RETURN_QR = 'dhlpoland/shipment/printReturnQrCode';
    private const URL_PATH_UPDATE_STATUS = 'dhlpoland/shipment/updateStatus';
    private const URL_PATH_DELETE = 'dhlpoland/shipment/delete';
    private const URL_PATH_VIEW_ORDER = 'sales/order/view';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param ShipmentDataPayloadReader $payloadReader
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly ShipmentDataPayloadReader $payloadReader,
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
                                'dhlpoland/shipment/printLabel',
                                ['entity_id' => $item['entity_id']]
                            ),
                            'label' => __('Print Label'),
                            'target' => '_blank',
                        ];

                        if (!$this->hasReturnShipment($item)) {
                            $actions['create_return'] = [
                                'href' => $this->urlBuilder->getUrl(
                                    self::URL_PATH_CREATE_RETURN,
                                    ['entity_id' => $item['entity_id']]
                                ),
                                'label' => __('Create Return Shipment'),
                                'confirm' => [
                                    'title' => __('Create Return Shipment'),
                                    'message' => __(
                                        'Are you sure you want to create a return shipment for this parcel?'
                                    ),
                                ],
                                'post' => true,
                            ];
                        } else {
                            $actions['print_return_label'] = [
                                'href' => $this->urlBuilder->getUrl(
                                    self::URL_PATH_PRINT_RETURN_LABEL,
                                    ['entity_id' => $item['entity_id']]
                                ),
                                'label' => __('Print Return Label'),
                                'target' => '_blank',
                            ];

                            $actions['print_return_qr'] = [
                                'href' => $this->urlBuilder->getUrl(
                                    self::URL_PATH_PRINT_RETURN_QR,
                                    ['entity_id' => $item['entity_id']]
                                ),
                                'label' => __('Print Return QR Code'),
                                'target' => '_blank',
                            ];
                        }

                        $actions['download_scan'] = [
                            'href' => $this->urlBuilder->getUrl(
                                'dhlpoland/shipment/downloadScan',
                                ['entity_id' => $item['entity_id']]
                            ),
                            'label' => __('Download Scan'),
                            'target' => '_blank',
                        ];

                        $actions['download_epod'] = [
                            'href' => $this->urlBuilder->getUrl(
                                'dhlpoland/shipment/downloadEpod',
                                ['entity_id' => $item['entity_id']]
                            ),
                            'label' => __('Download ePOD'),
                            'target' => '_blank',
                        ];

                        $actions['update_status'] = [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_UPDATE_STATUS,
                                ['entity_id' => $item['entity_id']]
                            ),
                            'label' => __('Update Status'),
                            'post' => true,
                        ];
                    }

                    if (empty($item['dispatch_number'])) {
                        $providerPath = 'dhlpoland_shipment_listing.dhlpoland_shipment_listing.'
                            . 'dhlpoland_shipment_columns.actions';
                        $actions['book_courier'] = [
                            'href' => '#',
                            'label' => __('Order Courier'),
                            'callback' => [
                                'provider' => $providerPath,
                                'target' => 'bookCourierRow',
                            ],
                        ];
                    } else {
                        $actions['cancel_courier'] = [
                            'href' => $this->urlBuilder->getUrl(
                                'dhlpoland/shipment/cancelCourierBooking',
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
                                    'Are you sure you want to delete this shipment?'
                                    . ' It will be cancelled in DHL and removed from Magento.'
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

    /**
     * Check if shipment entity data contains any return shipment IDs in package dynamic rows.
     *
     * @param array $item
     *
     * @return bool
     */
    private function hasReturnShipment(array $item): bool
    {
        $directReturnId = $item['return_shipment_id'] ?? null;
        if ((is_string($directReturnId) || is_int($directReturnId) || is_float($directReturnId))
            && trim((string) $directReturnId) !== ''
        ) {
            return true;
        }

        $payload = $this->payloadReader->read($item['shipment_data'] ?? null);
        return !empty($this->payloadReader->getReturnShipmentIds($payload));
    }
}
