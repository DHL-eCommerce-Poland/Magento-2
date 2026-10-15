<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Shipment;

use DhlPoland\Dhl\Model\Api\CreateShipmentResult;
use DhlPoland\Dhl\Model\Shipment\CustomsDocumentService;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class CustomsDocumentServiceTest extends TestCase
{
    /**
     * Test that saveDocuments does nothing when no customs documents are present.
     *
     * @return void
     */
    public function testSaveDocumentsDoesNothingWhenNoCustomsDocuments(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $connectionMock->expects($this->never())->method('delete');
        $connectionMock->expects($this->never())->method('insertMultiple');

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);

        $service = new CustomsDocumentService($resourceConnectionStub);
        $emptyResult = new CreateShipmentResult(true, false, '123456');

        $service->saveDocuments(10, $emptyResult);
    }

    /**
     * Test saveDocuments persists decoded CN23 and Proforma documents into database.
     *
     * @return void
     */
    public function testSaveDocumentsInsertsDecodedBinaryRows(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $connectionMock->expects($this->once())
            ->method('delete')
            ->with('dhlpoland_shipment_customs_document', ['shipment_entity_id = ?' => 42]);

        $insertedRows = [];
        $connectionMock->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'dhlpoland_shipment_customs_document',
                $this->callback(function (array $rows) use (&$insertedRows) {
                    $insertedRows = $rows;
                    return count($rows) === 2;
                })
            );

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')
            ->willReturn('dhlpoland_shipment_customs_document');

        $service = new CustomsDocumentService($resourceConnectionStub);

        $result = new CreateShipmentResult(
            success: true,
            timeout: false,
            shipmentId: '90040340516',
            orderStatus: 'Nowa',
            packageNumbers: [],
            errorCode: null,
            errorMessage: null,
            labelContent: null,
            labelFormat: null,
            labelType: null,
            entityId: 42,
            cn23Content: base64_encode('PDF_CN23_BINARY'),
            cn23MimeType: 'application/pdf',
            fvProformaContent: base64_encode('PDF_PROFORMA_BINARY'),
            fvProformaMimeType: 'application/pdf',
            fvProformaNumber: 'DHL-FV-900003-10-2026'
        );

        $service->saveDocuments(42, $result);

        $this->assertCount(2, $insertedRows);

        $this->assertEquals(42, $insertedRows[0]['shipment_entity_id']);
        $this->assertEquals(CustomsDocumentService::TYPE_CN23, $insertedRows[0]['document_type']);
        $this->assertNull($insertedRows[0]['document_number']);
        $this->assertEquals('application/pdf', $insertedRows[0]['mime_type']);
        $this->assertEquals('PDF_CN23_BINARY', $insertedRows[0]['content']);

        $this->assertEquals(42, $insertedRows[1]['shipment_entity_id']);
        $this->assertEquals(CustomsDocumentService::TYPE_PROFORMA, $insertedRows[1]['document_type']);
        $this->assertEquals('DHL-FV-900003-10-2026', $insertedRows[1]['document_number']);
        $this->assertEquals('application/pdf', $insertedRows[1]['mime_type']);
        $this->assertEquals('PDF_PROFORMA_BINARY', $insertedRows[1]['content']);
    }

    /**
     * Test getDocumentsForShipments loads and groups rows by shipment entity ID.
     *
     * @return void
     */
    public function testGetDocumentsForShipmentsLoadsAndGroupsRows(): void
    {
        $selectStub = $this->createStub(Select::class);
        $selectStub->method('from')->willReturnSelf();
        $selectStub->method('where')->willReturnSelf();
        $selectStub->method('order')->willReturnSelf();

        $rows = [
            [
                'document_id' => 1,
                'shipment_entity_id' => 10,
                'document_type' => CustomsDocumentService::TYPE_CN23,
                'document_number' => null,
                'mime_type' => 'application/pdf',
                'content' => 'PDF_CN23_10',
            ],
            [
                'document_id' => 2,
                'shipment_entity_id' => 10,
                'document_type' => CustomsDocumentService::TYPE_PROFORMA,
                'document_number' => 'DHL-FV-001',
                'mime_type' => 'application/pdf',
                'content' => 'PDF_PROFORMA_10',
            ],
            [
                'document_id' => 3,
                'shipment_entity_id' => 20,
                'document_type' => CustomsDocumentService::TYPE_CN23,
                'document_number' => null,
                'mime_type' => 'application/pdf',
                'content' => 'PDF_CN23_20',
            ],
        ];

        $connectionMock = $this->createMock(AdapterInterface::class);
        $connectionMock->method('select')->willReturn($selectStub);
        $connectionMock->method('fetchAll')->with($selectStub)->willReturn($rows);

        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlpoland_shipment_customs_document');

        $service = new CustomsDocumentService($resourceConnectionStub);

        $grouped = $service->getDocumentsForShipments([10, 20]);

        $this->assertCount(2, $grouped);
        $this->assertArrayHasKey(10, $grouped);
        $this->assertArrayHasKey(20, $grouped);

        $this->assertCount(2, $grouped[10]);
        $this->assertEquals(CustomsDocumentService::TYPE_CN23, $grouped[10][0]['document_type']);
        $this->assertEquals('PDF_CN23_10', $grouped[10][0]['content']);
        $this->assertEquals(CustomsDocumentService::TYPE_PROFORMA, $grouped[10][1]['document_type']);
        $this->assertEquals('DHL-FV-001', $grouped[10][1]['document_number']);

        $this->assertCount(1, $grouped[20]);
        $this->assertEquals('PDF_CN23_20', $grouped[20][0]['content']);

        // Empty IDs
        $this->assertEmpty($service->getDocumentsForShipments([]));
    }
}
