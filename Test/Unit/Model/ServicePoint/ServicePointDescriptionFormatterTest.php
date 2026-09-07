<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\ServicePoint;

use Ideo\Dhl\Model\ServicePoint\ServicePointDescriptionFormatter;
use Ideo\Dhl\Model\ServicePoint\ServicePointValueObject;
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
}
