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

        $this->expectNotToPerformAssertions();
        $validator->validate('flatrate', 'flatrate', null);
    }

    public function testValidationPassesForDhlNonPointMethod(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $validator = new ServicePointValidator($scopeConfigStub);

        $this->expectNotToPerformAssertions();
        $validator->validate(Carrier::CODE, 'courier_express', null);
    }

    public function testValidationPassesForEconomyWhenNearestModeConfigured(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('nearest');

        $validator = new ServicePointValidator($scopeConfigStub);
        $this->expectNotToPerformAssertions();
        $validator->validate(Carrier::CODE, 'economy_all', null);
    }

    public function testValidationThrowsExceptionWhenPointMissingForMapMode(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('map');

        $validator = new ServicePointValidator($scopeConfigStub);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please select a DHL pickup point on the map.');

        $validator->validate(Carrier::CODE, 'economy_all', null);
    }

    public function testValidationThrowsExceptionWhenPointMissingForBoxMode(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('map');

        $validator = new ServicePointValidator($scopeConfigStub);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please select a DHL pickup point on the map.');

        $validator->validate(Carrier::CODE, 'economy_box', null);
    }

    public function testValidationThrowsExceptionWhenPointMissingForPopMode(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('map');

        $validator = new ServicePointValidator($scopeConfigStub);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please select a DHL pickup point on the map.');

        $validator->validate(Carrier::CODE, 'economy_pop', null);
    }

    public function testValidationThrowsExceptionWhenPointInvalid(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('map');

        $validator = new ServicePointValidator($scopeConfigStub);
        $invalidPoint = new ServicePointValueObject('');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please select a DHL pickup point on the map.');

        $validator->validate(Carrier::CODE, 'economy_all', $invalidPoint);
    }

    public function testValidationPassesWhenValidPointProvided(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigStub->method('getValue')->willReturn('map');

        $validator = new ServicePointValidator($scopeConfigStub);
        $validPoint = new ServicePointValueObject('SAP123', 'Żabka');

        $this->expectNotToPerformAssertions();
        $validator->validate(Carrier::CODE, 'economy_all', $validPoint);
        $validator->validate(Carrier::CODE, 'economy_box', $validPoint);
        $validator->validate(Carrier::CODE, 'economy_pop', $validPoint);
    }

    public function testValidationThrowsExceptionForGermanPackstationWithoutPostnummer(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);

        $validator = new ServicePointValidator($scopeConfigStub);
        $point = new ServicePointValueObject(
            'DE123',
            'Packstation 101',
            'Keetstr.',
            '49393',
            'Lohne',
            true,
            false,
            null,
            'DE'
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'German Customer Number (Postnummer) is required for delivery to a German Packstation.'
        );

        $validator->validate(Carrier::CODE, 'connect', $point);
    }

    public function testValidationThrowsExceptionForInvalidPostnummerFormat(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);

        $validator = new ServicePointValidator($scopeConfigStub);
        $point = new ServicePointValueObject(
            'DE123',
            'Packstation 101',
            'Keetstr.',
            '49393',
            'Lohne',
            true,
            false,
            '123',
            'DE'
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Invalid German Customer Number (Postnummer). It must contain between 6 and 10 digits.'
        );

        $validator->validate(Carrier::CODE, 'connect', $point);
    }

    public function testValidationPassesForGermanPackstationWithValidPostnummer(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);

        $validator = new ServicePointValidator($scopeConfigStub);
        $point = new ServicePointValueObject(
            'DE123',
            'Packstation 101',
            'Keetstr.',
            '49393',
            'Lohne',
            true,
            false,
            '12345678',
            'DE'
        );

        $this->expectNotToPerformAssertions();
        $validator->validate(Carrier::CODE, 'connect', $point);
    }

    public function testValidationThrowsExceptionForConnectPackstationMethodWithoutPostnummer(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);

        $validator = new ServicePointValidator($scopeConfigStub);
        $point = new ServicePointValueObject(
            'DE123',
            'Point Name',
            'Keetstr.',
            '49393',
            'Lohne',
            false,
            false,
            null,
            'DE'
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'German Customer Number (Postnummer) is required for delivery to a German Packstation.'
        );

        $validator->validate(Carrier::CODE, 'connect_packstation', $point);
    }
}
