<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\GetPnpResult;
use PHPUnit\Framework\TestCase;

class GetPnpResultTest extends TestCase
{
    public function testSuccessResultGetters(): void
    {
        $result = new GetPnpResult(
            true,
            'binary-content',
            'PNP_2026-09-01.pdf',
            'application/pdf'
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('binary-content', $result->getFileData());
        $this->assertEquals('PNP_2026-09-01.pdf', $result->getFileName());
        $this->assertEquals('application/pdf', $result->getMimeType());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testErrorResultGetters(): void
    {
        $result = new GetPnpResult(
            false,
            null,
            null,
            null,
            'ERR_NOT_FOUND',
            'No shipments found'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getFileData());
        $this->assertNull($result->getFileName());
        $this->assertNull($result->getMimeType());
        $this->assertEquals('ERR_NOT_FOUND', $result->getErrorCode());
        $this->assertEquals('No shipments found', $result->getErrorMessage());
    }
}
