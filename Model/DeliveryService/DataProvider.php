<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\DeliveryService;

use DhlPoland\Dhl\Model\ResourceModel\DeliveryService\CollectionFactory;
use DhlPoland\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use InvalidArgumentException;
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
     * @param SerializerInterface $serializer
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
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
                } catch (InvalidArgumentException $e) {
                    unset($e);
                }
            }
            $packages = $item->getPackages();
            if (is_array($packages)) {
                foreach ($packages as $k => &$pkg) {
                    if (is_array($pkg)) {
                        $pkg['record_id'] = (string)$k;
                    }
                }
                unset($pkg);
            }
            $data['packages'] = $packages;
            if (isset($data['method_code']) && is_string($data['method_code']) && $data['method_code'] !== '') {
                $data['method_code'] = explode(',', $data['method_code']);
            }
            if (empty($data['return_label_exp_days']) || (int)$data['return_label_exp_days'] <= 0) {
                $data['return_label_exp_days'] = ShipmentSettingsData::DEFAULT_RETURN_LABEL_EXP_DAYS;
            }
            $this->loadedData[$item->getId()] = $data;
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

        $tagsNotice = __(
            'Available tags: {item_id}, {order_id}, {quote_item_id}, {product_id}, {sku}, {product_name}, '
            . '{order_description}'
        );

        $contentConfig = &$meta['general']['children']['content']['arguments']['data']['config'];
        $contentConfig['notice'] = $tagsNotice;

        $commentConfig = &$meta['general']['children']['comment']['arguments']['data']['config'];
        $commentConfig['notice'] = $tagsNotice;

        $refConfig = &$meta['general']['children']['reference']['arguments']['data']['config'];
        $refConfig['notice'] = $tagsNotice;

        $mpkConfig = &$meta['general']['children']['mpk']['arguments']['data']['config'];
        $mpkConfig['notice'] = __(
            'Cost center code. Available tags: {item_id}, {order_id}, {quote_item_id}, {product_id}, {sku}, '
            . '{product_name}, {order_description}'
        );

        return $meta;
    }
}
