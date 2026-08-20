<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\TestConnectionResult;
use PHPUnit\Framework\TestCase;

class TestConnectionResultTest extends TestCase
{
    /**
     * Test success result instantiation.
     *
     * @return void
     */
    public function testSuccessResult(): void
    {
        $result = new TestConnectionResult(true, 'Connection successful.');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Connection successful.', $result->getMessage());
        $this->assertNull($result->getErrorCode());
    }

    /**
     * Test failure result instantiation with error code.
     *
     * @return void
     */
    public function testFailureResult(): void
    {
        $result = new TestConnectionResult(false, 'Auth failed', 'AUTH_ERROR');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Auth failed', $result->getMessage());
        $this->assertEquals('AUTH_ERROR', $result->getErrorCode());
    }
}
