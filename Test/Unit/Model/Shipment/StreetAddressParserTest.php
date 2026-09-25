<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Shipment\StreetAddressParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StreetAddressParserTest extends TestCase
{
    /**
     * @var StreetAddressParser
     */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new StreetAddressParser();
    }

    #[DataProvider('addressDataProvider')]
    public function testParseAddress(
        string|array $input,
        string $expectedStreet,
        string $expectedHouse,
        string $expectedFlat
    ): void {
        $result = $this->parser->parse($input);

        $this->assertSame($expectedStreet, $result['street']);
        $this->assertSame($expectedHouse, $result['houseNumber']);
        $this->assertSame($expectedFlat, $result['flatNumber']);
    }

    public static function addressDataProvider(): array
    {
        return [
            'warszawska slash separated' => ['Warszawska 29/44', 'Warszawska', '29/44', ''],
            'slash separated' => ['Marszałkowska 10/12', 'Marszałkowska', '10/12', ''],
            'slash with spaces' => ['ul. 1 Maja 5 / 2', 'ul. 1 Maja', '5/2', ''],
            'm dot indicator' => ['Al. Jerozolimskie 123 m. 4', 'Al. Jerozolimskie', '123', '4'],
            'lokal indicator' => ['Polna 12 lok. 4A', 'Polna', '12', '4A'],
            'house only' => ['Krakowskie Przedmieście 14', 'Krakowskie Przedmieście', '14', ''],
            'house with letter' => ['Długa 15a', 'Długa', '15a', ''],
            'array input 2 lines' => [['Marszałkowska', '10/12'], 'Marszałkowska', '10/12', ''],
            'array input 1 line' => [['Marszałkowska 10/12'], 'Marszałkowska', '10/12', ''],
            'empty string' => ['', '', '', ''],
            'unparseable single text' => ['BezNumeru', 'BezNumeru', '', ''],
        ];
    }
}
