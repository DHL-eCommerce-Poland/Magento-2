<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\GetLabelsResult;
use PHPUnit\Framework\TestCase;

class GetLabelsResultTest extends TestCase
{
    public function testSuccessfulResult(): void
    {
        $items = [
            [
                'shipmentId' => '123456789',
                'labelType' => 'BLP',
                'labelData' => '%PDF-1.4 ...',
                'mimeType' => 'application/pdf',
            ],
        ];

        $result = new GetLabelsResult(true, $items);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($items, $result->getItems());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testFailedResult(): void
    {
        $result = new GetLabelsResult(false, [], 'SOAP_FAULT', 'DHL Error');

        $this->assertFalse($result->isSuccess());
        $this->assertEmpty($result->getItems());
        $this->assertEquals('SOAP_FAULT', $result->getErrorCode());
        $this->assertEquals('DHL Error', $result->getErrorMessage());
    }
}
