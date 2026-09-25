<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\CreateShipmentReturnResult;
use PHPUnit\Framework\TestCase;

class CreateShipmentReturnResultTest extends TestCase
{
    public function testSingleItemResult(): void
    {
        $items = [
            [
                'shipmentId' => '90040025505',
                'waybill' => '90040025505',
                'packageTrackingNumber' => 'JJD000030025688110000000807',
                'labelData' => 'PDF_CONTENT_1',
                'labelMimeType' => 'application/pdf',
            ],
        ];

        $result = new CreateShipmentReturnResult(
            success: true,
            timeout: false,
            items: $items
        );

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isTimeout());
        $this->assertCount(1, $result->getItems());
        $this->assertEquals(['90040025505'], $result->getShipmentIds());
        $this->assertEquals('90040025505', $result->getPrimaryShipmentId());
        $this->assertEquals('90040025505', $result->getShipmentId());
        $this->assertEquals('90040025505', $result->getWaybill());
        $this->assertEquals('PDF_CONTENT_1', $result->getLabelData());
        $this->assertEquals('application/pdf', $result->getLabelMimeType());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testMultipleItemsResult(): void
    {
        $items = [
            [
                'shipmentId' => '90040025505',
                'waybill' => '90040025505',
                'packageTrackingNumber' => 'JJD000030025688110000000807',
                'labelData' => 'PDF_CONTENT_1',
                'labelMimeType' => 'application/pdf',
            ],
            [
                'shipmentId' => '90040025516',
                'waybill' => '90040025516',
                'packageTrackingNumber' => 'JJD000030025688110000000818',
                'labelData' => 'PDF_CONTENT_2',
                'labelMimeType' => 'application/pdf',
            ],
            [
                'shipmentId' => '90040025527',
                'waybill' => '90040025527',
                'packageTrackingNumber' => 'JJD000030025688110000000829',
                'labelData' => 'PDF_CONTENT_3',
                'labelMimeType' => 'application/pdf',
            ],
        ];

        $result = new CreateShipmentReturnResult(
            success: true,
            timeout: false,
            items: $items
        );

        $this->assertTrue($result->isSuccess());
        $this->assertCount(3, $result->getItems());
        $this->assertEquals(['90040025505', '90040025516', '90040025527'], $result->getShipmentIds());
        $this->assertEquals('90040025505', $result->getPrimaryShipmentId());
        $this->assertEquals('90040025505, 90040025516, 90040025527', $result->getShipmentId());
        $this->assertEquals('90040025505, 90040025516, 90040025527', $result->getWaybill());
        $this->assertEquals('PDF_CONTENT_1PDF_CONTENT_2PDF_CONTENT_3', $result->getLabelData());
        $this->assertEquals('application/pdf', $result->getLabelMimeType());
    }

    public function testFailureResult(): void
    {
        $result = new CreateShipmentReturnResult(
            success: false,
            timeout: true,
            items: [],
            errorCode: 'SOAP_FAULT',
            errorMessage: 'Connection timeout'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isTimeout());
        $this->assertEmpty($result->getItems());
        $this->assertEmpty($result->getShipmentIds());
        $this->assertNull($result->getPrimaryShipmentId());
        $this->assertNull($result->getShipmentId());
        $this->assertNull($result->getWaybill());
        $this->assertEquals('SOAP_FAULT', $result->getErrorCode());
        $this->assertEquals('Connection timeout', $result->getErrorMessage());
    }
}
