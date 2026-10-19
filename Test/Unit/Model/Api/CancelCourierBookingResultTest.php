<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\CancelCourierBookingResult;
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
        $this->assertSame([], $result->getCancelledDispatchNumbers());
        $this->assertSame([], $result->getErrors());
    }

    public function testErrorResult(): void
    {
        $result = new CancelCourierBookingResult(false, '90018060826WWW', 'CANCEL_ERROR', 'Order cannot be cancelled.');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('90018060826WWW', $result->getDispatchNumber());
        $this->assertEquals('CANCEL_ERROR', $result->getErrorCode());
        $this->assertEquals('Order cannot be cancelled.', $result->getErrorMessage());
        $this->assertSame([], $result->getCancelledDispatchNumbers());
        $this->assertSame([], $result->getErrors());
    }

    public function testAggregateResult(): void
    {
        $result = new CancelCourierBookingResult(
            false,
            '90018060826WWW',
            'PARTIAL_ERROR',
            'Error on 90018060827WWW',
            ['90018060826WWW'],
            ['90018060827WWW' => 'Error on 90018060827WWW']
        );

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('90018060826WWW', $result->getDispatchNumber());
        $this->assertEquals('PARTIAL_ERROR', $result->getErrorCode());
        $this->assertEquals('Error on 90018060827WWW', $result->getErrorMessage());
        $this->assertSame(['90018060826WWW'], $result->getCancelledDispatchNumbers());
        $this->assertSame(['90018060827WWW' => 'Error on 90018060827WWW'], $result->getErrors());
    }
}
