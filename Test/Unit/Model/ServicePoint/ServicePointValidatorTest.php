<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\ServicePoint;

use Ideo\Dhl\Model\Carrier;
use Ideo\Dhl\Model\ServicePoint\ServicePointValidator;
use Ideo\Dhl\Model\ServicePoint\ServicePointValueObject;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ServicePointValidatorTest extends TestCase
{
    public function testValidationPassesForNonDhlCarrier(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $validator = new ServicePointValidator($scopeConfigStub);

        $validator->validate('flatrate', 'flatrate', null);
        $this->assertTrue(true);
    }

    public function testValidationPassesForDhlNonPointMethod(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $validator = new ServicePointValidator($scopeConfigStub);

        $validator->validate(Carrier::CODE, 'courier_express', null);
        $this->assertTrue(true);
    }

    public function testValidationPassesForEconomyWhenNearestModeConfigured(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('nearest');

        $validator = new ServicePointValidator($scopeConfigStub);
        $validator->validate(Carrier::CODE, 'economy', null);
        $this->assertTrue(true);
    }

    public function testValidationThrowsExceptionWhenPointMissingForMapMode(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('map');

        $validator = new ServicePointValidator($scopeConfigStub);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please select a DHL pickup point on the map.');

        $validator->validate(Carrier::CODE, 'economy', null);
    }

    public function testValidationThrowsExceptionWhenPointInvalid(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('map');

        $validator = new ServicePointValidator($scopeConfigStub);
        $invalidPoint = new ServicePointValueObject('');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please select a DHL pickup point on the map.');

        $validator->validate(Carrier::CODE, 'economy', $invalidPoint);
    }

    public function testValidationPassesWhenValidPointProvided(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('map');

        $validator = new ServicePointValidator($scopeConfigStub);
        $validPoint = new ServicePointValueObject('SAP123', 'Żabka');

        $validator->validate(Carrier::CODE, 'economy', $validPoint);
        $this->assertTrue(true);
    }
}
