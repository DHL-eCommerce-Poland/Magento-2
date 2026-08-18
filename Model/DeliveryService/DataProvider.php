<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\DeliveryService;

use Ideo\Dhl\Model\ResourceModel\DeliveryService\CollectionFactory;
use Magento\Framework\App\RequestInterface;
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
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
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
                $decoded = json_decode($data['additional_services'], true);
                if (is_array($decoded)) {
                    $data['additional_services'] = $decoded;
                }
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
}
