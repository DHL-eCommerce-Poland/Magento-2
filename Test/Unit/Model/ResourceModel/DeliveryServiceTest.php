<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\ResourceModel;

use DhlPoland\Dhl\Model\ResourceModel\DeliveryService;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\Context;
use PHPUnit\Framework\TestCase;

class DeliveryServiceTest extends TestCase
{
    /**
     * @return void
     */
    public function testConstructSetsTableAndPrimaryKey(): void
    {
        $contextMock = $this->createStub(Context::class);
        $resourcesMock = $this->createStub(ResourceConnection::class);
        $connectionMock = $this->createStub(AdapterInterface::class);

        $resourcesMock->method('getConnection')->willReturn($connectionMock);
        $contextMock->method('getResources')->willReturn($resourcesMock);

        $resource = new DeliveryService($contextMock);

        $reflection = new \ReflectionClass(DeliveryService::class);
        $mainTableProperty = $reflection->getProperty('_mainTable');
        $idFieldNameProperty = $reflection->getProperty('_idFieldName');

        $this->assertEquals('dhlpoland_delivery_service_template', $mainTableProperty->getValue($resource));
        $this->assertEquals('entity_id', $idFieldNameProperty->getValue($resource));
    }
}
