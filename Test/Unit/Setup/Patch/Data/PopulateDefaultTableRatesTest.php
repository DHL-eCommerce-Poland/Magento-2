<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Setup\Patch\Data;

use Ideo\Dhl\Model\Config\Backend\Tablerate;
use Ideo\Dhl\Setup\Patch\Data\PopulateDefaultTableRates;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\File\Csv;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use PHPUnit\Framework\TestCase;

class PopulateDefaultTableRatesTest extends TestCase
{
    public function testApplyImportsDefaultRatesWhenTableIsEmpty(): void
    {
        $moduleDirReaderMock = $this->createMock(ModuleDirReader::class);
        $csvReaderMock = $this->createMock(Csv::class);
        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $tablerateBackendMock = $this->createMock(Tablerate::class);

        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $connectionMock->method('select')->willReturn($selectStub);

        $resourceConnectionStub
            ->method('getConnection')
            ->willReturn($connectionMock);

        $resourceConnectionStub
            ->method('getTableName')
            ->willReturn('ideo_dhl_tablerate');

        $moduleDirReaderMock
            ->expects($this->once())
            ->method('getModuleDir')
            ->with('etc', 'Ideo_Dhl')
            ->willReturn('/path/to/module/etc');

        $csvReaderMock
            ->expects($this->once())
            ->method('getData')
            ->with('/path/to/module/etc/dhl_tablerates_default.csv')
            ->willReturn([
                ['Website', 'Method Code', 'Condition Type', 'Value From', 'Value To', 'Price'],
                ['0', 'parcel_polska', 'weight', '0.0000', '1.0000', '16.1000'],
            ]);

        $connectionMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(0);

        $tablerateBackendMock
            ->expects($this->once())
            ->method('parseCsvContent')
            ->willReturn([
                [
                    'website_id' => 0,
                    'method_code' => 'parcel_polska',
                    'condition_type' => 'weight',
                    'condition_from' => 0.0,
                    'condition_to' => 1.0,
                    'price' => 16.10,
                ],
            ]);

        $tablerateBackendMock
            ->expects($this->once())
            ->method('saveTableRates');

        $patch = new PopulateDefaultTableRates(
            $moduleDirReaderMock,
            $csvReaderMock,
            $resourceConnectionStub,
            $tablerateBackendMock
        );

        $patch->apply();
    }

    public function testApplySkipsImportWhenTableIsNotEmpty(): void
    {
        $moduleDirReaderStub = $this->createStub(ModuleDirReader::class);
        $csvReaderStub = $this->createStub(Csv::class);
        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $tablerateBackendMock = $this->createMock(Tablerate::class);

        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $connectionMock->method('select')->willReturn($selectStub);

        $resourceConnectionStub
            ->method('getConnection')
            ->willReturn($connectionMock);

        $resourceConnectionStub
            ->method('getTableName')
            ->willReturn('ideo_dhl_tablerate');

        $connectionMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(5);

        $tablerateBackendMock
            ->expects($this->never())
            ->method('saveTableRates');

        $patch = new PopulateDefaultTableRates(
            $moduleDirReaderStub,
            $csvReaderStub,
            $resourceConnectionStub,
            $tablerateBackendMock
        );

        $patch->apply();
    }
}
