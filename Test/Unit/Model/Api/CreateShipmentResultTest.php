<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\CreateShipmentResult;
use PHPUnit\Framework\TestCase;

class CreateShipmentResultTest extends TestCase
{
    /**
     * Test creating a successful result.
     *
     * @return void
     */
    public function testSuccessfulResult(): void
    {
        $result = new CreateShipmentResult(true, false, '1234567890');

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isTimeout());
        $this->assertEquals('1234567890', $result->getShipmentId());
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
        $this->assertEmpty($result->getPackageNumbers());
    }

    /**
     * Test result with package numbers and label data.
     *
     * @return void
     */
    public function testResultWithPackageNumbersAndLabel(): void
    {
        $packageNumbers = ['BLP1001', 'BLP1002'];
        $result = new CreateShipmentResult(
            true,
            false,
            '1234567890',
            'Nowa',
            $packageNumbers,
            null,
            null,
            'BASE64LABEL',
            'application/pdf',
            'BLP'
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('1234567890', $result->getShipmentId());
        $this->assertEquals('Nowa', $result->getOrderStatus());
        $this->assertEquals($packageNumbers, $result->getPackageNumbers());
        $this->assertEquals('BASE64LABEL', $result->getLabelContent());
        $this->assertEquals('application/pdf', $result->getLabelFormat());
        $this->assertEquals('BLP', $result->getLabelType());
    }

    /**
     * Test creating an error result.
     *
     * @return void
     */
    public function testErrorResult(): void
    {
        $result = new CreateShipmentResult(false, false, null, null, [], 'ERR_POSTCODE', 'Invalid postal code');

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
        $result = new CreateShipmentResult(false, true, null, null, [], 'TIMEOUT', 'Connection timed out');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isTimeout());
        $this->assertNull($result->getShipmentId());
        $this->assertEquals('TIMEOUT', $result->getErrorCode());
        $this->assertEquals('Connection timed out', $result->getErrorMessage());
    }

    /**
     * Test result with entity ID and withEntityId immutability.
     *
     * @return void
     */
    public function testResultWithEntityIdAndWithEntityId(): void
    {
        $result = new CreateShipmentResult(true, false, '1234567890', null, [], null, null, null, null, null, 42);

        $this->assertEquals(42, $result->getEntityId());

        $updatedResult = $result->withEntityId(99);
        $this->assertNotSame($result, $updatedResult);
        $this->assertEquals(42, $result->getEntityId());
        $this->assertEquals(99, $updatedResult->getEntityId());
        $this->assertEquals('1234567890', $updatedResult->getShipmentId());
        $this->assertTrue($updatedResult->isSuccess());
    }

    /**
     * Test result with customs documents and withEntityId preservation.
     *
     * @return void
     */
    public function testResultWithCustomsDocuments(): void
    {
        $resultWithoutCustoms = new CreateShipmentResult(true, false, '1234567890');
        $this->assertFalse($resultWithoutCustoms->hasCustomsDocuments());
        $this->assertNull($resultWithoutCustoms->getCn23Content());
        $this->assertNull($resultWithoutCustoms->getCn23MimeType());
        $this->assertNull($resultWithoutCustoms->getFvProformaContent());
        $this->assertNull($resultWithoutCustoms->getFvProformaMimeType());
        $this->assertNull($resultWithoutCustoms->getFvProformaNumber());

        $result = new CreateShipmentResult(
            success: true,
            timeout: false,
            shipmentId: '1234567890',
            orderStatus: 'Nowa',
            packageNumbers: ['BLP1001'],
            errorCode: null,
            errorMessage: null,
            labelContent: 'BASE64LABEL',
            labelFormat: 'application/pdf',
            labelType: 'BLP',
            entityId: 10,
            cn23Content: 'BASE64CN23',
            cn23MimeType: 'application/pdf',
            fvProformaContent: 'BASE64PROFORMA',
            fvProformaMimeType: 'application/pdf',
            fvProformaNumber: 'DHL-FV-900003-10-2026'
        );

        $this->assertTrue($result->hasCustomsDocuments());
        $this->assertEquals('BASE64CN23', $result->getCn23Content());
        $this->assertEquals('application/pdf', $result->getCn23MimeType());
        $this->assertEquals('BASE64PROFORMA', $result->getFvProformaContent());
        $this->assertEquals('application/pdf', $result->getFvProformaMimeType());
        $this->assertEquals('DHL-FV-900003-10-2026', $result->getFvProformaNumber());

        $updated = $result->withEntityId(25);
        $this->assertEquals(25, $updated->getEntityId());
        $this->assertTrue($updated->hasCustomsDocuments());
        $this->assertEquals('BASE64CN23', $updated->getCn23Content());
        $this->assertEquals('application/pdf', $updated->getCn23MimeType());
        $this->assertEquals('BASE64PROFORMA', $updated->getFvProformaContent());
        $this->assertEquals('application/pdf', $updated->getFvProformaMimeType());
        $this->assertEquals('DHL-FV-900003-10-2026', $updated->getFvProformaNumber());
    }
}
