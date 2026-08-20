<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Shipment\ShipmentStatus;
use PHPUnit\Framework\TestCase;

class ShipmentStatusTest extends TestCase
{
    /**
     * Test status constants and getAllStatuses helper method.
     *
     * @return void
     */
    public function testStatusConstantsAndGetAllStatuses(): void
    {
        $this->assertEquals('dispatched', ShipmentStatus::DISPATCHED);
        $this->assertEquals('pending_verification', ShipmentStatus::PENDING_VERIFICATION);
        $this->assertEquals('error_sync', ShipmentStatus::ERROR_SYNC);
        $this->assertEquals('cancelled', ShipmentStatus::CANCELLED);

        $statuses = ShipmentStatus::getAllStatuses();
        $this->assertCount(4, $statuses);
        $this->assertContains(ShipmentStatus::DISPATCHED, $statuses);
        $this->assertContains(ShipmentStatus::PENDING_VERIFICATION, $statuses);
    }
}
