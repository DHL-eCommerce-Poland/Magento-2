<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Backend;

use Ideo\Dhl\Model\Config\Backend\Tablerate;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\MediaStorage\Model\File\UploaderFactory;
use PHPUnit\Framework\TestCase;

class TablerateTest extends TestCase
{
    /**
     * @var Tablerate
     */
    private Tablerate $backendModel;

    protected function setUp(): void
    {
        $connectionStub = $this->createStub(AdapterInterface::class);
        $resourceConnectionStub = $this->createStub(ResourceConnection::class);

        $resourceConnectionStub
            ->method('getConnection')
            ->willReturn($connectionStub);

        $resourceConnectionStub
            ->method('getTableName')
            ->willReturn('dhlparcel_tablerate');

        $context = $this->createStub(Context::class);
        $registry = $this->createStub(Registry::class);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $cacheTypeList = $this->createStub(TypeListInterface::class);
        $uploaderFactory = $this->createStub(UploaderFactory::class);
        $requestData = $this->createStub(RequestDataInterface::class);
        $filesystem = $this->createStub(Filesystem::class);

        $this->backendModel = new Tablerate(
            $context,
            $registry,
            $scopeConfig,
            $cacheTypeList,
            $uploaderFactory,
            $requestData,
            $filesystem,
            $resourceConnectionStub
        );
    }

    public function testParseCsvRowsThrowsExceptionWhenHeaderIsInvalid(): void
    {
        $csvContent = "Invalid,Header,Row\n1,parcel_polska,weight,0,5,15.0";

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid CSV header structure.');

        $this->backendModel->parseCsvContent($csvContent);
    }

    public function testParseCsvRowsParsesValidRowsCorrectly(): void
    {
        $csvContent = "Website,Method Code,Condition Type,Value From,Value To,Price\n" .
            "1,parcel_polska,weight,0.0,5.0,15.00\n" .
            "0,premium,subtotal,0.0,100.0,10.00";

        $rows = $this->backendModel->parseCsvContent($csvContent);

        $this->assertCount(2, $rows);
        $this->assertEquals([
            'website_id' => 1,
            'method_code' => 'parcel_polska',
            'condition_type' => 'weight',
            'condition_from' => 0.0,
            'condition_to' => 5.0,
            'price' => 15.00,
        ], $rows[0]);
    }
}
