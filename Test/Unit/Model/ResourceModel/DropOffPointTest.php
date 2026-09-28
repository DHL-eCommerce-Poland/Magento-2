<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\ResourceModel;

use Ideo\Dhl\Model\ResourceModel\DropOffPoint;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\Context;
use PHPUnit\Framework\TestCase;

class DropOffPointTest extends TestCase
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

        $resource = new DropOffPoint($contextMock);

        $reflection = new \ReflectionClass(DropOffPoint::class);
        $mainTableProperty = $reflection->getProperty('_mainTable');
        $idFieldNameProperty = $reflection->getProperty('_idFieldName');

        $this->assertEquals('dhlpoland_drop_off_point_template', $mainTableProperty->getValue($resource));
        $this->assertEquals('entity_id', $idFieldNameProperty->getValue($resource));
    }
}
