<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\GetEpodResult;
use PHPUnit\Framework\TestCase;

class GetEpodResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $result = new GetEpodResult(true, 'binary_epod_content', 'application/pdf');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('binary_epod_content', $result->getFileData());
        $this->assertEquals('application/pdf', $result->getMimeType());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testErrorResult(): void
    {
        $result = new GetEpodResult(false, null, null, 'NO_DATA', 'ePOD not available');

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getFileData());
        $this->assertNull($result->getMimeType());
        $this->assertEquals('NO_DATA', $result->getErrorCode());
        $this->assertEquals('ePOD not available', $result->getErrorMessage());
    }
}
