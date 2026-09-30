<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\CustomsTemplate;

use Ideo\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var array
     */
    protected array $loadedData = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param SerializerInterface $serializer
     * @param File $fileIo
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly SerializerInterface $serializer,
        private readonly File $fileIo,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data.
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
            if (!empty($data['customs_items']) && is_string($data['customs_items'])) {
                $decoded = $this->serializer->unserialize($data['customs_items']);
                if (is_array($decoded)) {
                    if (isset($decoded['customs_items']) && is_array($decoded['customs_items'])) {
                        $decoded = $decoded['customs_items'];
                    } elseif (count($decoded) === 1
                        && isset($decoded[0])
                        && is_array($decoded[0])
                        && !isset($decoded[0]['nameEn'])
                        && !isset($decoded[0]['namePl'])
                    ) {
                        $decoded = $decoded[0];
                    }
                    $data['customs_items'] = array_values($decoded);
                }
            }
            if (isset($data['country_id']) && is_string($data['country_id']) && $data['country_id'] !== '') {
                $data['country_id'] = array_values(array_filter(array_map('trim', explode(',', $data['country_id']))));
            }
            $this->loadedData[$item->getId()] = $data;
        }

        return $this->loadedData;
    }
}
