<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Setup\Patch\Data;

use Ideo\Dhl\Model\Config\Backend\CountryRate;
use Ideo\Dhl\Setup\Patch\Data\PopulateDefaultCountryRates;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\File\Csv;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use PHPUnit\Framework\TestCase;

class PopulateDefaultCountryRatesTest extends TestCase
{
    public function testApplyImportsDefaultRatesWhenTableIsEmpty(): void
    {
        $moduleDirReaderMock = $this->createMock(ModuleDirReader::class);
        $csvReaderMock = $this->createMock(Csv::class);
        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $countryRateBackendMock = $this->createMock(CountryRate::class);

        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $connectionMock->method('select')->willReturn($selectStub);

        $resourceConnectionStub
            ->method('getConnection')
            ->willReturn($connectionMock);

        $resourceConnectionStub
            ->method('getTableName')
            ->willReturn('dhlparcel_country_rate');

        $moduleDirReaderMock
            ->expects($this->once())
            ->method('getModuleDir')
            ->with('etc', 'Ideo_Dhl')
            ->willReturn('/path/to/module/etc');

        $csvReaderMock
            ->expects($this->once())
            ->method('getData')
            ->with('/path/to/module/etc/dhl_international_rates_default.csv')
            ->willReturn([
                ['Method Code', 'Country', 'Price'],
                ['connect', 'DE', '45.00'],
            ]);

        $connectionMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(0);

        $countryRateBackendMock
            ->expects($this->once())
            ->method('parseCsvContent')
            ->willReturn([
                [
                    'method_code' => 'connect',
                    'country_id' => 'DE',
                    'price' => 45.00,
                ],
            ]);

        $countryRateBackendMock
            ->expects($this->once())
            ->method('saveCountryRates')
            ->with(0, [
                [
                    'method_code' => 'connect',
                    'country_id' => 'DE',
                    'price' => 45.00,
                ],
            ]);

        $countryRateBackendMock
            ->expects($this->once())
            ->method('syncConfigSpecificCountries')
            ->with([
                [
                    'method_code' => 'connect',
                    'country_id' => 'DE',
                    'price' => 45.00,
                ],
            ]);

        $patch = new PopulateDefaultCountryRates(
            $moduleDirReaderMock,
            $csvReaderMock,
            $resourceConnectionStub,
            $countryRateBackendMock
        );

        $result = $patch->apply();
        $this->assertSame($patch, $result);
    }

    public function testApplySkipsImportWhenTableIsNotEmpty(): void
    {
        $moduleDirReaderStub = $this->createStub(ModuleDirReader::class);
        $csvReaderStub = $this->createStub(Csv::class);
        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $countryRateBackendMock = $this->createMock(CountryRate::class);

        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $connectionMock->method('select')->willReturn($selectStub);

        $resourceConnectionStub
            ->method('getConnection')
            ->willReturn($connectionMock);

        $resourceConnectionStub
            ->method('getTableName')
            ->willReturn('dhlparcel_country_rate');

        $connectionMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(10);

        $countryRateBackendMock
            ->expects($this->never())
            ->method('saveCountryRates');

        $patch = new PopulateDefaultCountryRates(
            $moduleDirReaderStub,
            $csvReaderStub,
            $resourceConnectionStub,
            $countryRateBackendMock
        );

        $result = $patch->apply();
        $this->assertSame($patch, $result);
    }

    public function testRevertDeletesAllRows(): void
    {
        $moduleDirReaderStub = $this->createStub(ModuleDirReader::class);
        $csvReaderStub = $this->createStub(Csv::class);
        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $countryRateBackendStub = $this->createStub(CountryRate::class);

        $resourceConnectionStub
            ->method('getConnection')
            ->willReturn($connectionMock);

        $resourceConnectionStub
            ->method('getTableName')
            ->willReturn('dhlparcel_country_rate');

        $connectionMock
            ->expects($this->once())
            ->method('delete')
            ->with('dhlparcel_country_rate');

        $patch = new PopulateDefaultCountryRates(
            $moduleDirReaderStub,
            $csvReaderStub,
            $resourceConnectionStub,
            $countryRateBackendStub
        );

        $patch->revert();
    }

    public function testGetDependenciesAndAliases(): void
    {
        $this->assertSame([], PopulateDefaultCountryRates::getDependencies());

        $moduleDirReaderStub = $this->createStub(ModuleDirReader::class);
        $csvReaderStub = $this->createStub(Csv::class);
        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $countryRateBackendStub = $this->createStub(CountryRate::class);

        $patch = new PopulateDefaultCountryRates(
            $moduleDirReaderStub,
            $csvReaderStub,
            $resourceConnectionStub,
            $countryRateBackendStub
        );

        $this->assertSame([], $patch->getAliases());
    }
}
