<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Ui\Component\Listing\Column;

use Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use Ideo\Dhl\Ui\Component\Listing\Column\PackageNumbers;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

class PackageNumbersTest extends TestCase
{
    private PackageNumbers $packageNumbersColumn;

    protected function setUp(): void
    {
        $this->packageNumbersColumn = new PackageNumbers(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            new ShipmentDataPayloadReader(new Json()),
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
        $jsonPayload = '{"packageNumbers":["BLP10001","BLP10002"]}';

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
        $jsonPayload = '{"pieceList":[{"type":"PACKAGE","quantity":1,"length":30,"width":20,"height":10}]}';

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

    public function testPrepareDataSourceIgnoresInvalidPackageNumberValues(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 4,
                        'shipment_data' => '{"packageNumbers":[" BLP10001 ",["invalid"],null,"BLP10001",0]}',
                    ],
                ],
            ],
        ];

        $result = $this->packageNumbersColumn->prepareDataSource($dataSource);

        $this->assertSame('BLP10001, 0', $result['data']['items'][0]['package_numbers']);
    }

    public function testPrepareDataSourceUsesNestedPackagesWhenRootPackageListIsMalformed(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 5,
                        'shipment_data' => '{"packages":"invalid","shipmentSettings":'
                            . '{"packages":[{"packageNumber":["invalid"],"pieceId":"BLP10002"}]}}',
                    ],
                ],
            ],
        ];

        $result = $this->packageNumbersColumn->prepareDataSource($dataSource);

        $this->assertSame('BLP10002', $result['data']['items'][0]['package_numbers']);
    }

    public function testPrepareDataSourceReturnsEmptyForMalformedJson(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    ['entity_id' => 6, 'shipment_data' => '{invalid'],
                ],
            ],
        ];

        $result = $this->packageNumbersColumn->prepareDataSource($dataSource);

        $this->assertSame('', $result['data']['items'][0]['package_numbers']);
    }
}
