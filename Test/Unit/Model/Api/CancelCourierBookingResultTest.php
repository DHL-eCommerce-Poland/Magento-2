<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\CancelCourierBookingResult;
use PHPUnit\Framework\TestCase;

class CancelCourierBookingResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $result = new CancelCourierBookingResult(true, '90018060826WWW');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('90018060826WWW', $result->getDispatchNumber());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testErrorResult(): void
    {
        $result = new CancelCourierBookingResult(false, '90018060826WWW', 'CANCEL_ERROR', 'Order cannot be cancelled.');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('90018060826WWW', $result->getDispatchNumber());
        $this->assertEquals('CANCEL_ERROR', $result->getErrorCode());
        $this->assertEquals('Order cannot be cancelled.', $result->getErrorMessage());
    }
}
