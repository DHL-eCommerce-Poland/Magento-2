<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\CountryRateResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class CountryRateResolverTest extends TestCase
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
            ->willReturn('35.0000');

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlpoland_country_rate');

        $resolver = new CountryRateResolver($resourceConnectionStub);
        $result = $resolver->resolveRate(1, 'connect', 'DE');

        $this->assertSame(35.0, $result);
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
        $resourceConnectionStub->method('getTableName')->willReturn('dhlpoland_country_rate');

        $resolver = new CountryRateResolver($resourceConnectionStub);
        $result = $resolver->resolveRate(1, 'connect', 'ES');

        $this->assertNull($result);
    }

    public function testResolveRateNormalizesCountryCodeToUppercase(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('order')->willReturnSelf();
        $selectMock->method('limit')->willReturnSelf();
        $selectMock->expects($this->exactly(3))
            ->method('where')
            ->willReturnSelf();

        $connectionMock->method('select')->willReturn($selectMock);
        $connectionMock->expects($this->once())
            ->method('fetchOne')
            ->with($this->isInstanceOf(Select::class))
            ->willReturn('45.0000');

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlpoland_country_rate');

        $resolver = new CountryRateResolver($resourceConnectionStub);
        $result = $resolver->resolveRate(1, 'connect', 'fr');

        $this->assertSame(45.0, $result);
    }

    public function testGetRatesForExportReturnsWebsiteRates(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $selectStub->method('where')->willReturnSelf();
        $selectStub->method('order')->willReturnSelf();

        $expectedRows = [
            [
                'method_code' => 'connect',
                'country_id' => 'DE',
                'price' => 40.0,
            ],
        ];

        $connectionMock->method('select')->willReturn($selectStub);
        $connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRows);

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlpoland_country_rate');

        $resolver = new CountryRateResolver($resourceConnectionStub);
        $rows = $resolver->getRatesForExport(1);

        $this->assertSame($expectedRows, $rows);
    }

    public function testGetRatesForExportFallsBackToDefaultWhenEmpty(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectStub1 = $this->createStub(Select::class);
        $selectStub1->method('from')->willReturnSelf();
        $selectStub1->method('where')->willReturnSelf();
        $selectStub1->method('order')->willReturnSelf();

        $selectStub2 = $this->createStub(Select::class);
        $selectStub2->method('from')->willReturnSelf();
        $selectStub2->method('where')->willReturnSelf();
        $selectStub2->method('order')->willReturnSelf();

        $defaultRows = [
            [
                'method_code' => 'connect',
                'country_id' => 'FR',
                'price' => 50.0,
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
        $resourceConnectionStub->method('getTableName')->willReturn('dhlpoland_country_rate');

        $resolver = new CountryRateResolver($resourceConnectionStub);
        $rows = $resolver->getRatesForExport(2, true);

        $this->assertSame($defaultRows, $rows);
    }

    public function testGetRatesForExportDoesNotFallbackWhenDisabled(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $selectStub->method('where')->willReturnSelf();
        $selectStub->method('order')->willReturnSelf();

        $connectionMock->expects($this->once())
            ->method('select')
            ->willReturn($selectStub);

        $connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlpoland_country_rate');

        $resolver = new CountryRateResolver($resourceConnectionStub);
        $rows = $resolver->getRatesForExport(2, false);

        $this->assertSame([], $rows);
    }
}
