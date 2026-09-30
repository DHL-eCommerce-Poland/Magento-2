<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ResourceModel\ShipmentData\Grid;

use Ideo\Dhl\Model\ResourceModel\ShipmentData;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

class Collection extends SearchResult
{
    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param string $mainTable
     * @param string $resourceModel
     * @param string|null $identifierName
     * @param string|null $connectionName
     * @throws LocalizedException
     */
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable = 'dhlpoland_shipment',
        $resourceModel = ShipmentData::class,
        $identifierName = null,
        $connectionName = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    /**
     * Init select query and join sales_order table for increment_id.
     *
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['sales_order' => $this->getTable('sales_order')],
            'main_table.order_id = sales_order.entity_id',
            ['order_increment_id' => 'sales_order.increment_id']
        );
        $this->addFilterToMap('order_increment_id', 'sales_order.increment_id');
        $this->addFilterToMap('entity_id', 'main_table.entity_id');
        $this->addFilterToMap('return_shipment_id', 'main_table.return_shipment_id');
        $this->addFilterToMap('package_numbers', 'main_table.package_numbers');
        $this->addFilterToMap('status', 'main_table.status');
        $this->addFilterToMap('created_at', 'main_table.created_at');
        $this->addFilterToMap('updated_at', 'main_table.updated_at');

        return $this;
    }

    /**
     * Add field filter to collection with LIKE support for tracking/package numbers and JSON payload fallback.
     *
     * @param string|array $field
     * @param string|array|null $condition
     *
     * @return $this
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if (is_string($field)) {
            $value = null;
            if (is_string($condition)) {
                $value = trim($condition);
            } elseif (is_array($condition)) {
                $value = isset($condition['like'])
                    ? trim(str_replace('%', '', (string)$condition['like']))
                    : (isset($condition['eq']) ? trim((string)$condition['eq']) : null);
            }

            if (!empty($value)) {
                $searchTerm = '%' . $value . '%';

                $targetFields = ['package_numbers', 'return_shipment_id', 'shipment_id', 'dispatch_number'];
                if (in_array($field, $targetFields, true)) {
                    $whereExpr = $this->getConnection()->quoteInto(
                        'main_table.' . $field . ' LIKE ?',
                        $searchTerm
                    );
                    $this->getSelect()->where($whereExpr);
                    return $this;
                }
            }
        }

        return parent::addFieldToFilter($field, $condition);
    }
}
