<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\ResourceModel\CustomsTemplate\Grid;

use DhlPoland\Dhl\Model\ResourceModel\CustomsTemplate;
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
        $mainTable = 'dhlpoland_customs_template',
        $resourceModel = CustomsTemplate::class,
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
}
