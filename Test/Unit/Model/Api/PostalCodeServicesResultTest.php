<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\PostalCodeServicesResult;
use PHPUnit\Framework\TestCase;

class PostalCodeServicesResultTest extends TestCase
{
    public function testGettersReturnExpectedValues(): void
    {
        $result = new PostalCodeServicesResult(
            true,
            '10:00',
            '16:00',
            '09:00',
            '15:00',
            true
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('10:00', $result->getDrPickupFrom());
        $this->assertEquals('16:00', $result->getDrPickupTo());
        $this->assertEquals('09:00', $result->getExPickupFrom());
        $this->assertEquals('15:00', $result->getExPickupTo());
        $this->assertTrue($result->isPickupOnSaturday());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testErrorResult(): void
    {
        $result = new PostalCodeServicesResult(
            false,
            null,
            null,
            null,
            null,
            false,
            'ERR_CODE',
            'Error message'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('ERR_CODE', $result->getErrorCode());
        $this->assertEquals('Error message', $result->getErrorMessage());
    }
}
