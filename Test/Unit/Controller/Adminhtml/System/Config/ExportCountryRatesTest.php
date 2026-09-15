<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\System\Config;

use Ideo\Dhl\Controller\Adminhtml\System\Config\ExportCountryRates;
use Ideo\Dhl\Model\CountryRateResolver;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportCountryRatesTest extends TestCase
{
    /**
     * @var FileFactory|MockObject
     */
    private $fileFactoryMock;

    /**
     * @var CountryRateResolver|MockObject
     */
    private $countryRateResolverMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManagerMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var ExportCountryRates
     */
    private $controller;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->fileFactoryMock = $this->createMock(FileFactory::class);
        $this->countryRateResolverMock = $this->createMock(CountryRateResolver::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);

        $context->method('getRequest')->willReturn($this->requestMock);

        $this->controller = new ExportCountryRates(
            $context,
            $this->fileFactoryMock,
            $this->countryRateResolverMock,
            $this->storeManagerMock
        );
    }

    public function testExecuteGeneratesCountryRatesCsvFile(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('website')
            ->willReturn(null);

        $this->storeManagerMock->expects($this->never())
            ->method('getWebsite');

        $this->countryRateResolverMock->expects($this->once())
            ->method('getRatesForExport')
            ->with(0)
            ->willReturn([
                [
                    'method_code' => 'connect',
                    'country_id' => 'DE',
                    'price' => 43.0,
                ],
                [
                    'method_code' => 'connect_pallet',
                    'country_id' => 'CZ',
                    'price' => 460.0,
                ],
            ]);

        $responseStub = $this->createStub(ResponseInterface::class);

        $expectedCsvContent = "Method Code,Country,Price\n"
            . "connect,DE,43.0000\n"
            . "connect_pallet,CZ,460.0000\n";

        $this->fileFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with(
                'dhl_international_rates.csv',
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

        $this->countryRateResolverMock->expects($this->once())
            ->method('getRatesForExport')
            ->with(2)
            ->willReturn([]);

        $this->fileFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($responseStub);

        $this->assertSame($responseStub, $this->controller->execute());
    }
}
