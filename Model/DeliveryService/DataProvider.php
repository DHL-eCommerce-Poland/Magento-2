<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\DeliveryService;

use Ideo\Dhl\Model\ResourceModel\DeliveryService\CollectionFactory;
use Ideo\Dhl\Model\Shipment\DhlShipmentRestrictions;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param SerializerInterface $serializer
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly SerializerInterface $serializer,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * Get data for form binding.
     *
     * @return array
     */
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $item) {
            $data = $item->getData();
            if (isset($data['additional_services']) && is_string($data['additional_services'])) {
                try {
                    $decoded = $this->serializer->unserialize($data['additional_services']);
                    if (is_array($decoded)) {
                        $data['additional_services'] = $decoded;
                    }
                } catch (\InvalidArgumentException $e) {
                    unset($e);
                }
            }
            if (isset($data['method_code']) && is_string($data['method_code']) && $data['method_code'] !== '') {
                $data['method_code'] = explode(',', $data['method_code']);
            }
            $this->loadedData[$item->getId()] = $data;
        }

        // Scope support: if adding a new delivery service, pre-bind website_id from URL parameter
        $websiteParam = $this->request->getParam('website');
        if ($websiteParam !== null) {
            $this->loadedData['']['website_id'] = (int)$websiteParam;
        }

        return $this->loadedData;
    }

    /**
     * Get meta configuration.
     *
     * @return array
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();

        $maxComment = DhlShipmentRestrictions::MAX_LENGTH_COMMENT;
        $commentConfig = &$meta['general']['children']['comment']['arguments']['data']['config'];
        $commentConfig['validation']['max_text_length'] = $maxComment;
        $commentConfig['notice'] = __(
            'Available tags: {order_increment_id}, {product_names}, {sku_list}, '
            . '{qty_total}, {customer_name}, {payment_method} (max %1 chars)',
            $maxComment
        );

        $maxRef = DhlShipmentRestrictions::MAX_LENGTH_REFERENCE;
        $refConfig = &$meta['general']['children']['reference']['arguments']['data']['config'];
        $refConfig['validation']['max_text_length'] = $maxRef;
        $refConfig['notice'] = __(
            'Available tags: {order_increment_id}, {product_names}, {sku_list}, '
            . '{qty_total}, {customer_name}, {payment_method} (max %1 chars)',
            $maxRef
        );

        return $meta;
    }
}
