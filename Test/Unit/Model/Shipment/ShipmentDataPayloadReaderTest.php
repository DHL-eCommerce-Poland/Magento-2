<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ShipmentDataPayloadReaderTest extends TestCase
{
    /**
     * @var ShipmentDataPayloadReader
     */
    private ShipmentDataPayloadReader $reader;

    protected function setUp(): void
    {
        $this->reader = new ShipmentDataPayloadReader(new Json());
    }

    public function testReadReturnsDecodedArrayPayload(): void
    {
        $payload = ['packageNumbers' => ['BLP10001']];

        $this->assertSame($payload, $this->reader->read($payload));
    }

    public function testReadDeserializesValidJson(): void
    {
        $this->assertSame(
            ['packageNumbers' => ['BLP10001']],
            $this->reader->read('{"packageNumbers":["BLP10001"]}')
        );
    }

    public function testReadReturnsEmptyArrayForInvalidOrNonArrayPayload(): void
    {
        $this->assertSame([], $this->reader->read('{invalid'));
        $this->assertSame([], $this->reader->read('"scalar"'));
        $this->assertSame([], $this->reader->read(null));
    }

    public function testGetPackagesPrefersRootPackages(): void
    {
        $rootPackages = [['pieceId' => 'BLP10001']];

        $packages = $this->reader->getPackages([
            'packages' => $rootPackages,
            'shipmentSettings' => ['packages' => [['pieceId' => 'BLP10002']]],
        ]);

        $this->assertSame($rootPackages, $packages);
    }

    public function testGetPackagesReadsNestedPackagesWhenRootIsUnavailable(): void
    {
        $packages = [['pieceId' => 'BLP10002']];

        $this->assertSame(
            $packages,
            $this->reader->getPackages(['shipmentSettings' => ['packages' => $packages]])
        );
        $this->assertSame(
            $packages,
            $this->reader->getPackages([
                'packages' => 'invalid',
                'shipmentSettings' => ['packages' => $packages],
            ])
        );
    }

    public function testGetPackagesReturnsEmptyArrayForInvalidShape(): void
    {
        $this->assertSame([], $this->reader->getPackages(['shipmentSettings' => 'invalid']));
        $this->assertSame([], $this->reader->getPackages(['packages' => 'invalid']));
    }

    public function testGetReturnShipmentIdsNormalizesAndDeduplicatesPackageValues(): void
    {
        $returnIds = $this->reader->getReturnShipmentIds([
            'shipmentSettings' => [
                'packages' => [
                    ['returnShipmentId' => ' 90040025505 '],
                    ['returnShipmentId' => ['invalid']],
                    ['returnShipmentId' => '90040025505'],
                    ['returnShipmentId' => 90040025516],
                ],
            ],
        ]);

        $this->assertSame(['90040025505', '90040025516'], $returnIds);
    }

    public function testGetReturnShipmentIdsFromEntityCombinesAndDeduplicatesDirectAndPayload(): void
    {
        $entityStub = $this->createStub(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $entityStub->method('getReturnShipmentId')->willReturn('90040025505, 90040025506');
        $entityStub->method('getShipmentData')->willReturn(json_encode([
            'packages' => [
                ['returnShipmentId' => '90040025506'],
                ['returnShipmentId' => '90040025507'],
            ],
        ]));

        $result = $this->reader->getReturnShipmentIdsFromEntity($entityStub);

        $this->assertSame(['90040025505', '90040025506', '90040025507'], $result);
    }

    public function testGetReturnShipmentIdsFromEntityReturnsEmptyArrayWhenNoReturnIds(): void
    {
        $entityStub = $this->createStub(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $entityStub->method('getReturnShipmentId')->willReturn(null);
        $entityStub->method('getShipmentData')->willReturn(null);

        $result = $this->reader->getReturnShipmentIdsFromEntity($entityStub);

        $this->assertSame([], $result);
    }
}

