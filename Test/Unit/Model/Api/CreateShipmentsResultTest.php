<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\CreateShipmentsResult;
use PHPUnit\Framework\TestCase;

class CreateShipmentsResultTest extends TestCase
{
    /**
     * Test creating a successful result.
     *
     * @return void
     */
    public function testSuccessfulResult(): void
    {
        $result = new CreateShipmentsResult(true, false, '1234567890');

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isTimeout());
        $this->assertEquals('1234567890', $result->getShipmentId());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
        $this->assertEmpty($result->getPackageNumbers());
    }

    /**
     * Test result with package numbers.
     *
     * @return void
     */
    public function testResultWithPackageNumbers(): void
    {
        $packageNumbers = ['BLP1001', 'BLP1002'];
        $result = new CreateShipmentsResult(true, false, '1234567890', 'Nowa', $packageNumbers);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('1234567890', $result->getShipmentId());
        $this->assertEquals('Nowa', $result->getOrderStatus());
        $this->assertEquals($packageNumbers, $result->getPackageNumbers());
    }

    /**
     * Test creating an error result.
     *
     * @return void
     */
    public function testErrorResult(): void
    {
        $result = new CreateShipmentsResult(false, false, null, null, [], 'ERR_POSTCODE', 'Invalid postal code');

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isTimeout());
        $this->assertNull($result->getShipmentId());
        $this->assertEquals('ERR_POSTCODE', $result->getErrorCode());
        $this->assertEquals('Invalid postal code', $result->getErrorMessage());
    }

    /**
     * Test creating a timeout result.
     *
     * @return void
     */
    public function testTimeoutResult(): void
    {
        $result = new CreateShipmentsResult(false, true, null, null, [], 'TIMEOUT', 'Connection timed out');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isTimeout());
        $this->assertNull($result->getShipmentId());
        $this->assertEquals('TIMEOUT', $result->getErrorCode());
        $this->assertEquals('Connection timed out', $result->getErrorMessage());
    }
}
