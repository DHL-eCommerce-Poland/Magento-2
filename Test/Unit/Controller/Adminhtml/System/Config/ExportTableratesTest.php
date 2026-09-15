<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\System\Config;

use Ideo\Dhl\Controller\Adminhtml\System\Config\ExportTablerates;
use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\TableRateResolver;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportTableratesTest extends TestCase
{
    /**
     * @var FileFactory|MockObject
     */
    private $fileFactoryMock;

    /**
     * @var TableRateResolver|MockObject
     */
    private $tableRateResolverMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManagerMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var ExportTablerates
     */
    private $controller;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $context->method('getRequest')->willReturn($this->requestMock);

        $this->fileFactoryMock = $this->createMock(FileFactory::class);
        $this->tableRateResolverMock = $this->createMock(TableRateResolver::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);

        $this->controller = new ExportTablerates(
            $context,
            $this->fileFactoryMock,
            new MethodCatalog(),
            $this->tableRateResolverMock,
            $this->storeManagerMock
        );
    }

    public function testExecuteGeneratesCsvFile(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('website')
            ->willReturn(null);

        $this->storeManagerMock->expects($this->never())
            ->method('getWebsite');

        $this->tableRateResolverMock->expects($this->once())
            ->method('getRatesForExport')
            ->with(0)
            ->willReturn([
                [
                    'website_id' => 1,
                    'method_code' => 'parcel_polska',
                    'condition_type' => 'weight',
                    'condition_from' => 0.0,
                    'condition_to' => 5.0,
                    'price' => 15.0,
                ],
                [
                    'website_id' => 1,
                    'method_code' => 'unknown_custom_method',
                    'condition_type' => 'weight',
                    'condition_from' => 0.0,
                    'condition_to' => 5.0,
                    'price' => 20.0,
                ],
            ]);

        $responseStub = $this->createStub(ResponseInterface::class);
        $expectedCsvContent = "Method Code,Condition Type,Value From,Value To,Price\n"
            . "parcel_polska,weight,0.0000,5.0000,15.0000\n"
            . "unknown_custom_method,weight,0.0000,5.0000,20.0000\n";

        $this->fileFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with(
                'dhl_tablerates.csv',
                $this->callback(static function ($content) use ($expectedCsvContent): bool {
                    return is_array($content)
                        && isset($content['type'], $content['value'], $content['rm'])
                        && $content['type'] === 'string'
                        && $content['rm'] === true
                        && str_contains($content['value'], $expectedCsvContent);
                }),
                'tmp',
                'text/csv'
            )
            ->willReturn($responseStub);

        $result = $this->controller->execute();

        $this->assertSame($responseStub, $result);
    }

    public function testExecuteResolvesWebsiteByParam(): void
    {
        $responseStub = $this->createStub(ResponseInterface::class);
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('website')
            ->willReturn('base');

        $websiteStub = $this->createStub(WebsiteInterface::class);
        $websiteStub->method('getId')->willReturn(2);

        $this->storeManagerMock->expects($this->once())
            ->method('getWebsite')
            ->with('base')
            ->willReturn($websiteStub);

        $this->tableRateResolverMock->expects($this->once())
            ->method('getRatesForExport')
            ->with(2)
            ->willReturn([]);

        $this->fileFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($responseStub);

        $this->assertSame($responseStub, $this->controller->execute());
    }
}
