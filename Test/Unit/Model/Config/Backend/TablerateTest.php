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
    private $backendModel;

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
        $csvContent = "Invalid,Header,Row\nparcel_polska,weight,0,5,15.0";

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid CSV header structure.');

        $this->backendModel->parseCsvContent($csvContent);
    }

    public function testParseCsvRowsParsesWithoutWebsiteColumnInDefaultScope(): void
    {
        $csvContent = "Method Code,Condition Type,Value From,Value To,Price\n" .
            "parcel_polska,weight,0.0,5.0,15.00\n" .
            "premium,subtotal,0.0,100.0,10.00";

        $rows = $this->backendModel->parseCsvContent($csvContent);

        $this->assertCount(2, $rows);
        $this->assertEquals([
            'website_id' => 0,
            'method_code' => 'parcel_polska',
            'condition_type' => 'weight',
            'condition_from' => 0.0,
            'condition_to' => 5.0,
            'price' => 15.00,
        ], $rows[0]);
        $this->assertEquals(0, $rows[1]['website_id']);
    }

    public function testParseCsvRowsParsesWithExplicitWebsiteId(): void
    {
        $csvContent = "Method Code,Condition Type,Value From,Value To,Price\n" .
            "parcel_polska,weight,0.0,5.0,15.00";

        $rows = $this->backendModel->parseCsvContent($csvContent, 2);

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['website_id']);
    }

    public function testParseCsvRowsParsesWithWebsiteColumnForBackwardCompatibility(): void
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

    public function testResolveCurrentWebsiteIdReturnsScopeIdWhenWebsiteScope(): void
    {
        $this->backendModel->setScope(\Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES);
        $this->backendModel->setScopeId('3');

        $this->assertSame(3, $this->backendModel->resolveCurrentWebsiteId());
    }

    public function testResolveCurrentWebsiteIdReturnsZeroForDefaultScope(): void
    {
        $this->backendModel->setScope('default');
        $this->backendModel->setScopeId('0');

        $this->assertSame(0, $this->backendModel->resolveCurrentWebsiteId());
    }
}
