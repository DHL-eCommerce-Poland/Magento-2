<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\GetScanResult;
use PHPUnit\Framework\TestCase;

class GetScanResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $result = new GetScanResult(true, 'binary_pdf_content', 'application/pdf');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('binary_pdf_content', $result->getFileData());
        $this->assertEquals('application/pdf', $result->getMimeType());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testErrorResult(): void
    {
        $result = new GetScanResult(false, null, null, 'SOAP_FAULT', 'DHL Error');

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getFileData());
        $this->assertNull($result->getMimeType());
        $this->assertEquals('SOAP_FAULT', $result->getErrorCode());
        $this->assertEquals('DHL Error', $result->getErrorMessage());
    }
}
