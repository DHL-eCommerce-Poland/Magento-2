<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\System\Config;

use Ideo\Dhl\Controller\Adminhtml\System\Config\ExportTablerates;
use Ideo\Dhl\Model\MethodCatalog;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportTableratesTest extends TestCase
{
    /**
     * @var FileFactory|MockObject
     */
    private $fileFactoryMock;

    /**
     * @var ExportTablerates
     */
    private $controller;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $this->fileFactoryMock = $this->createMock(FileFactory::class);
        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $connectionStub = $this->createStub(AdapterInterface::class);

        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();

        $connectionStub->method('select')->willReturn($selectStub);
        $connectionStub->method('fetchAll')->willReturn([
            [
                'website_id' => 1,
                'method_code' => 'parcel_polska',
                'condition_type' => 'weight',
                'condition_from' => 0.0,
                'condition_to' => 5.0,
                'price' => 15.0,
            ],
        ]);

        $resourceConnectionStub
            ->method('getConnection')
            ->willReturn($connectionStub);

        $resourceConnectionStub
            ->method('getTableName')
            ->willReturn('dhlparcel_tablerate');

        $this->controller = new ExportTablerates(
            $context,
            $this->fileFactoryMock,
            $resourceConnectionStub,
            new MethodCatalog()
        );
    }

    public function testExecuteGeneratesCsvFile(): void
    {
        $responseStub = $this->createStub(ResponseInterface::class);
        $expectedCsvContent = "Website,Method Code,Condition Type,Value From,Value To,Price\n"
            . "1,parcel_polska,weight,0.0000,5.0000,15.0000";

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
}
