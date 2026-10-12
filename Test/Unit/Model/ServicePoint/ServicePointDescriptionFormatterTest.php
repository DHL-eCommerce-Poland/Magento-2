<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\ServicePoint;

use DhlPoland\Dhl\Model\ServicePoint\ServicePointDescriptionFormatter;
use DhlPoland\Dhl\Model\ServicePoint\ServicePointValueObject;
use PHPUnit\Framework\TestCase;

class ServicePointDescriptionFormatterTest extends TestCase
{
    /**
     * @var ServicePointDescriptionFormatter
     */
    private $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ServicePointDescriptionFormatter();
    }

    public function testFormatAppendsPointInfoToBaseDescription(): void
    {
        $point = new ServicePointValueObject(
            'POP-123',
            'Żabka',
            'Kwiatowa 5',
            '00-001',
            'Warszawa'
        );

        $result = $this->formatter->format('DHL Parcel - DHL Parcel Economy', $point);

        $this->assertStringContainsString('DHL Parcel - DHL Parcel Economy', $result);
        $this->assertStringContainsString('Żabka', $result);
        $this->assertStringContainsString('POP-123', $result);
        $this->assertStringContainsString('Kwiatowa 5', $result);
        $this->assertStringContainsString('00-001', $result);
        $this->assertStringContainsString('Warszawa', $result);
    }

    public function testFormatReturnsBaseDescriptionIfPointIsInvalid(): void
    {
        $invalidPoint = new ServicePointValueObject('');
        $result = $this->formatter->format('DHL Parcel - DHL Parcel Economy', $invalidPoint);

        $this->assertEquals('DHL Parcel - DHL Parcel Economy', $result);
    }

    public function testFormatTruncatesDescriptionToMax255CharactersSafely(): void
    {
        $veryLongStreet = str_repeat('Konstantynopolitańczykowianeczka ', 10);
        $point = new ServicePointValueObject(
            'POP-999999999',
            'Bardzo Długa Nazwa Punktu Odbioru Przesyłek DHL Parcel',
            $veryLongStreet,
            '99-999',
            'Super Długie Nazwisko Miejscowości'
        );

        $result = $this->formatter->format('DHL Parcel - DHL Parcel Economy', $point);

        $this->assertLessThanOrEqual(255, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testFormatIncludesPostnummerForGermanPackstation(): void
    {
        $point = new ServicePointValueObject(
            'PACK-101',
            'Packstation 101',
            'Keetstr. 1',
            '49393',
            'Lohne',
            true,
            false,
            '12345678',
            'DE'
        );

        $result = $this->formatter->format('DHL Connect Packstation', $point);
        $this->assertStringContainsString('Postnummer: 12345678', $result);
    }

    public function testFormatExcludesPostnummerForNonGermanPackstation(): void
    {
        $point = new ServicePointValueObject(
            'PACK-202',
            'Poststation Wien',
            'Hauptstr. 10',
            '1010',
            'Wien',
            true,
            false,
            '12345678',
            'AT'
        );

        $result = $this->formatter->format('DHL Connect Packstation', $point);
        $this->assertStringNotContainsString('Postnummer', $result);
    }

    public function testFormatExcludesPostnummerForGermanParcelshop(): void
    {
        $point = new ServicePointValueObject(
            'SHOP-303',
            'ParcelShop 501',
            'Keetstr. 20',
            '49393',
            'Lohne',
            false,
            true,
            '12345678',
            'DE'
        );

        $result = $this->formatter->format('DHL Connect ParcelShop', $point);
        $this->assertStringNotContainsString('Postnummer', $result);
    }
}
