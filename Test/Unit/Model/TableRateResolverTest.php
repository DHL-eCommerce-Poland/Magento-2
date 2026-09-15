<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\TableRateResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class TableRateResolverTest extends TestCase
{
    public function testResolveRateReturnsPriceWhenRateFound(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $selectStub->method('where')->willReturnSelf();
        $selectStub->method('order')->willReturnSelf();
        $selectStub->method('limit')->willReturnSelf();

        $connectionMock->method('select')->willReturn($selectStub);
        $connectionMock->expects($this->once())
            ->method('fetchOne')
            ->with($this->isInstanceOf(Select::class))
            ->willReturn('18.5000');

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlparcel_tablerate');

        $resolver = new TableRateResolver($resourceConnectionStub);
        $result = $resolver->resolveRate(1, 'parcel_polska', 'weight', 4.5);

        $this->assertSame(18.5, $result);
    }

    public function testResolveRateReturnsNullWhenRateNotFound(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $selectStub->method('where')->willReturnSelf();
        $selectStub->method('order')->willReturnSelf();
        $selectStub->method('limit')->willReturnSelf();

        $connectionMock->method('select')->willReturn($selectStub);
        $connectionMock->expects($this->once())
            ->method('fetchOne')
            ->with($this->isInstanceOf(Select::class))
            ->willReturn(false);

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlparcel_tablerate');

        $resolver = new TableRateResolver($resourceConnectionStub);
        $result = $resolver->resolveRate(1, 'parcel_polska', 'weight', 99.0);

        $this->assertNull($result);
    }

    public function testGetRatesForExportReturnsWebsiteRates(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $selectStub->method('where')->willReturnSelf();

        $expectedRows = [
            [
                'website_id' => 1,
                'method_code' => 'parcel_polska',
                'condition_type' => 'weight',
                'condition_from' => 0.0,
                'condition_to' => 5.0,
                'price' => 15.0,
            ],
        ];

        $connectionMock->method('select')->willReturn($selectStub);
        $connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRows);

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlparcel_tablerate');

        $resolver = new TableRateResolver($resourceConnectionStub);
        $rows = $resolver->getRatesForExport(1);

        $this->assertSame($expectedRows, $rows);
    }

    public function testGetRatesForExportFallsBackToDefaultWhenEmpty(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectStub1 = $this->createStub(Select::class);
        $selectStub1->method('from')->willReturnSelf();
        $selectStub1->method('where')->willReturnSelf();

        $selectStub2 = $this->createStub(Select::class);
        $selectStub2->method('from')->willReturnSelf();
        $selectStub2->method('where')->willReturnSelf();

        $defaultRows = [
            [
                'website_id' => 0,
                'method_code' => 'parcel_polska',
                'condition_type' => 'weight',
                'condition_from' => 0.0,
                'condition_to' => 10.0,
                'price' => 20.0,
            ],
        ];

        $connectionMock->expects($this->exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls($selectStub1, $selectStub2);

        $connectionMock->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls([], $defaultRows);

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlparcel_tablerate');

        $resolver = new TableRateResolver($resourceConnectionStub);
        $rows = $resolver->getRatesForExport(2, true);

        $this->assertSame($defaultRows, $rows);
    }

    public function testGetRatesForExportDoesNotFallbackWhenDisabledOrWebsiteIsZero(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $selectStub->method('where')->willReturnSelf();

        $connectionMock->expects($this->once())
            ->method('select')
            ->willReturn($selectStub);

        $connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlparcel_tablerate');

        $resolver = new TableRateResolver($resourceConnectionStub);
        $rows = $resolver->getRatesForExport(2, false);

        $this->assertSame([], $rows);
    }
}
