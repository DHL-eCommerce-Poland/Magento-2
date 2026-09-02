<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Ui\Component\Listing\Column;

use Ideo\Dhl\Ui\Component\Listing\Column\PackageNumbers;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PackageNumbersTest extends TestCase
{
    private ContextInterface&MockObject $contextMock;
    private UiComponentFactory&MockObject $uiComponentFactoryMock;
    private SerializerInterface&MockObject $serializerMock;
    private PackageNumbers $packageNumbersColumn;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(ContextInterface::class);
        $this->uiComponentFactoryMock = $this->createMock(UiComponentFactory::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);

        $this->packageNumbersColumn = new PackageNumbers(
            $this->contextMock,
            $this->uiComponentFactoryMock,
            $this->serializerMock,
            [],
            ['name' => 'package_numbers']
        );
    }

    /**
     * Test prepareDataSource with explicit stored package numbers.
     *
     * @return void
     */
    public function testPrepareDataSourceWithPackageNumbers(): void
    {
        $payload = ['packageNumbers' => ['BLP10001', 'BLP10002']];
        $jsonPayload = '{"packageNumbers":["BLP10001","BLP10002"]}';

        $this->serializerMock->expects($this->once())
            ->method('unserialize')
            ->with($jsonPayload)
            ->willReturn($payload);

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 1,
                        'shipment_data' => $jsonPayload,
                    ]
                ]
            ]
        ];

        $result = $this->packageNumbersColumn->prepareDataSource($dataSource);
        $this->assertEquals('BLP10001, BLP10002', $result['data']['items'][0]['package_numbers']);
    }

    /**
     * Test prepareDataSource with pieceList fallback.
     *
     * @return void
     */
    public function testPrepareDataSourceWithPieceListFallback(): void
    {
        $payload = [
            'pieceList' => [
                [
                    'type' => 'PACKAGE',
                    'quantity' => 1,
                    'length' => 30,
                    'width' => 20,
                    'height' => 10,
                ]
            ]
        ];
        $jsonPayload = '{"pieceList":[...]}';

        $this->serializerMock->expects($this->once())
            ->method('unserialize')
            ->with($jsonPayload)
            ->willReturn($payload);

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 2,
                        'shipment_data' => $jsonPayload,
                    ]
                ]
            ]
        ];

        $result = $this->packageNumbersColumn->prepareDataSource($dataSource);
        $this->assertEquals('1x PACKAGE 30x20x10 cm', $result['data']['items'][0]['package_numbers']);
    }

    /**
     * Test prepareDataSource with empty/invalid payload.
     *
     * @return void
     */
    public function testPrepareDataSourceWithEmptyPayload(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 3,
                        'shipment_data' => '',
                    ]
                ]
            ]
        ];

        $result = $this->packageNumbersColumn->prepareDataSource($dataSource);
        $this->assertEquals('', $result['data']['items'][0]['package_numbers']);
    }
}
