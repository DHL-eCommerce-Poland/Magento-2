<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\TrackingResult;
use PHPUnit\Framework\TestCase;

class TrackingResultTest extends TestCase
{
    public function testSuccessfulTrackingResult(): void
    {
        $events = [
            [
                'status' => 'DWP',
                'description' => 'Przesyłka w drodze do punktu',
                'terminal' => 'Warszawa',
                'timestamp' => '2026-08-28 10:00:00',
            ],
            [
                'status' => 'DOR',
                'description' => 'Doręczono',
                'terminal' => 'Warszawa',
                'timestamp' => '2026-08-28 14:00:00',
            ],
        ];

        $result = new TrackingResult(
            success: true,
            shipmentId: '123456789',
            receivedBy: 'Jan Kowalski',
            events: $events
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('123456789', $result->getShipmentId());
        $this->assertEquals('Jan Kowalski', $result->getReceivedBy());
        $this->assertCount(2, $result->getEvents());
        $this->assertEquals('Doręczono', $result->getLatestStatusDescription());
        $this->assertEquals('DOR', $result->getLatestStatusCode());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testFailedTrackingResult(): void
    {
        $result = new TrackingResult(
            success: false,
            shipmentId: '123456789',
            errorCode: 'SOAP_FAULT',
            errorMessage: 'Shipment not found'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('123456789', $result->getShipmentId());
        $this->assertNull($result->getReceivedBy());
        $this->assertEmpty($result->getEvents());
        $this->assertNull($result->getLatestStatusDescription());
        $this->assertNull($result->getLatestStatusCode());
        $this->assertEquals('SOAP_FAULT', $result->getErrorCode());
        $this->assertEquals('Shipment not found', $result->getErrorMessage());
    }

    public function testEmptyEventsTrackingResult(): void
    {
        $result = new TrackingResult(
            success: true,
            shipmentId: '987654321',
            receivedBy: null,
            events: []
        );

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->getLatestStatusDescription());
        $this->assertNull($result->getLatestStatusCode());
    }
}
