<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Shipment\DhlShipmentRestrictions;
use PHPUnit\Framework\TestCase;

class DhlShipmentRestrictionsTest extends TestCase
{
    /**
     * Test single piece only restrictions for SP, PS, LM, and POP ID.
     *
     * @return void
     */
    public function testIsSinglePieceOnly(): void
    {
        $this->assertTrue(DhlShipmentRestrictions::isSinglePieceOnly('SP'));
        $this->assertTrue(DhlShipmentRestrictions::isSinglePieceOnly('ps'));
        $this->assertTrue(DhlShipmentRestrictions::isSinglePieceOnly('LM'));
        $this->assertTrue(DhlShipmentRestrictions::isSinglePieceOnly('AH', 'POP-12345'));
        $this->assertTrue(DhlShipmentRestrictions::isSinglePieceOnly(null, '4529508'));

        $this->assertFalse(DhlShipmentRestrictions::isSinglePieceOnly('AH'));
        $this->assertFalse(DhlShipmentRestrictions::isSinglePieceOnly('09'));
        $this->assertFalse(DhlShipmentRestrictions::isSinglePieceOnly(null));
        $this->assertFalse(DhlShipmentRestrictions::isSinglePieceOnly(''));
    }

    /**
     * Test non-standard forbidden restrictions for SP, PS, LM, and POP ID.
     *
     * @return void
     */
    public function testIsNonStandardForbidden(): void
    {
        $this->assertTrue(DhlShipmentRestrictions::isNonStandardForbidden('SP'));
        $this->assertTrue(DhlShipmentRestrictions::isNonStandardForbidden('ps'));
        $this->assertTrue(DhlShipmentRestrictions::isNonStandardForbidden('LM'));
        $this->assertTrue(DhlShipmentRestrictions::isNonStandardForbidden('AH', 'POP-12345'));
        $this->assertTrue(DhlShipmentRestrictions::isNonStandardForbidden(null, '4529508'));

        $this->assertFalse(DhlShipmentRestrictions::isNonStandardForbidden('AH'));
        $this->assertFalse(DhlShipmentRestrictions::isNonStandardForbidden('12'));
        $this->assertFalse(DhlShipmentRestrictions::isNonStandardForbidden(null));
        $this->assertFalse(DhlShipmentRestrictions::isNonStandardForbidden(''));
    }
}
