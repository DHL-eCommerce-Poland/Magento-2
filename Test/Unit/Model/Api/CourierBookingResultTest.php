<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\CourierBookingResult;
use PHPUnit\Framework\TestCase;

class CourierBookingResultTest extends TestCase
{
    public function testGettersReturnExpectedValues(): void
    {
        $result = new CourierBookingResult(true, ['ORDER_123']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['ORDER_123'], $result->getCourierOrderIds());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testErrorResult(): void
    {
        $result = new CourierBookingResult(false, [], 'ERR_BOOKING', 'Failed');

        $this->assertFalse($result->isSuccess());
        $this->assertSame([], $result->getCourierOrderIds());
        $this->assertEquals('ERR_BOOKING', $result->getErrorCode());
        $this->assertEquals('Failed', $result->getErrorMessage());
    }
}
