<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Ui\Component\Listing\Column;

use Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use Ideo\Dhl\Ui\Component\Listing\Column\RecipientAddress;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

class RecipientAddressTest extends TestCase
{
    /**
     * @var RecipientAddress
     */
    private RecipientAddress $column;

    protected function setUp(): void
    {
        $this->column = new RecipientAddress(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            new ShipmentDataPayloadReader(new Json()),
            new Escaper(),
            [],
            ['name' => 'recipient_address']
        );
    }

    /**
     * Test full recipient details with person, company, address and verify phone/email are excluded.
     *
     * @return void
     */
    public function testFormatWithFullRecipientDetails(): void
    {
        $payload = [
            'recipient' => [
                'firstName' => 'Jan',
                'lastName' => 'Kowalski',
                'name' => 'Acme Corp Sp. z o.o.',
                'street' => 'ul. Kwiatowa',
                'houseNumber' => '10',
                'flatNumber' => '2',
                'postalCode' => '00-001',
                'city' => 'Warszawa',
                'country' => 'PL',
                'phone' => '123456789',
                'email' => 'jan.kowalski@example.com',
            ],
        ];

        $html = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload)]);

        $this->assertStringContainsString('Jan Kowalski', $html);
        $this->assertStringNotContainsString('<strong>', $html);
        $this->assertStringContainsString('Acme Corp Sp. z o.o.', $html);
        $this->assertStringContainsString('ul. Kwiatowa 10/2', $html);
        $this->assertStringContainsString('00-001 Warszawa, PL', $html);
        $this->assertStringNotContainsString('123456789', $html);
        $this->assertStringNotContainsString('jan.kowalski@example.com', $html);
    }

    /**
     * Test formatting when only individual person name is provided.
     *
     * @return void
     */
    public function testFormatWithOnlyPerson(): void
    {
        $payload = [
            'recipient' => [
                'firstName' => 'Anna',
                'lastName' => 'Nowak',
                'name' => '',
                'street' => 'Główna',
                'houseNumber' => '5',
                'flatNumber' => '',
                'postalCode' => '30-001',
                'city' => 'Kraków',
                'country' => 'PL',
            ],
        ];

        $html = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload)]);

        $this->assertStringContainsString('Anna Nowak', $html);
        $this->assertStringContainsString('Główna 5', $html);
        $this->assertStringContainsString('30-001 Kraków, PL', $html);
        $this->assertStringNotContainsString('(', $html);
    }

    /**
     * Test formatting when only company name is provided.
     *
     * @return void
     */
    public function testFormatWithOnlyCompany(): void
    {
        $payload = [
            'recipient' => [
                'firstName' => '',
                'lastName' => '',
                'name' => 'Tech Services Ltd',
                'street' => 'Industrial Ave',
                'houseNumber' => '100',
                'postalCode' => 'SW1A 1AA',
                'city' => 'London',
                'country' => 'GB',
            ],
        ];

        $html = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload)]);

        $this->assertStringContainsString('Tech Services Ltd', $html);
        $this->assertStringContainsString('Industrial Ave 100', $html);
        $this->assertStringContainsString('SW1A 1AA London, GB', $html);
    }

    /**
     * Test formatting when person name and company name are identical.
     *
     * @return void
     */
    public function testFormatWithSamePersonAndCompany(): void
    {
        $payload = [
            'recipient' => [
                'firstName' => 'Jan',
                'lastName' => 'Kowalski',
                'name' => 'Jan Kowalski',
                'street' => 'Prosta 1',
                'city' => 'Warszawa',
                'country' => 'PL',
            ],
        ];

        $html = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload)]);

        $this->assertStringContainsString('Jan Kowalski', $html);
        $this->assertStringNotContainsString('(Jan Kowalski)', $html);
    }

    /**
     * Test formatting with POP point information.
     *
     * @return void
     */
    public function testFormatWithPopPoint(): void
    {
        $payload = [
            'recipient' => [
                'firstName' => 'Piotr',
                'lastName' => 'Zieliński',
                'street' => 'Długa',
                'houseNumber' => '15',
                'postalCode' => '80-001',
                'city' => 'Gdańsk',
                'country' => 'PL',
                'popId' => 'POP-GDA001',
                'popName' => 'Sklep Żabka',
            ],
        ];

        $html = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload)]);

        $this->assertStringContainsString('POP-GDA001', $html);
        $this->assertStringContainsString('Sklep Żabka', $html);
    }

    /**
     * Test formatting with Packstation and Postnummer.
     *
     * @return void
     */
    public function testFormatWithPackstation(): void
    {
        $payload = [
            'recipient' => [
                'firstName' => 'Hans',
                'lastName' => 'Müller',
                'street' => 'Packstation',
                'houseNumber' => '102',
                'postalCode' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'isPackstation' => true,
                'popId' => '102',
                'postnummer' => '987654321',
            ],
        ];

        $html = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload)]);

        $this->assertStringContainsString('Packstation: 102', $html);
        $this->assertStringContainsString('Postnummer: 987654321', $html);
    }

    /**
     * Test that dangerous characters are properly escaped in the HTML output.
     *
     * @return void
     */
    public function testFormatEscapesHtmlEntities(): void
    {
        $payload = [
            'recipient' => [
                'firstName' => '<script>alert("xss")</script>',
                'lastName' => 'Test & Co',
                'name' => '<b>Bold Corp</b>',
                'street' => 'Street <foo>',
                'houseNumber' => '1',
                'city' => 'City',
                'country' => 'PL',
            ],
        ];

        $html = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload)]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Test &amp; Co', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold Corp&lt;/b&gt;', $html);
        $this->assertStringContainsString('Street &lt;foo&gt;', $html);
    }

    /**
     * Test that empty or invalid payload returns an empty string.
     *
     * @return void
     */
    public function testFormatEmptyPayloadReturnsEmptyString(): void
    {
        $this->assertSame('', $this->column->formatRecipientAddress([]));
        $this->assertSame('', $this->column->formatRecipientAddress(['shipment_data' => '']));
        $this->assertSame('', $this->column->formatRecipientAddress(['shipment_data' => 'invalid-json']));
        $this->assertSame('', $this->column->formatRecipientAddress(['shipment_data' => '{}']));
    }

    /**
     * Test prepareDataSource populates the configured column name.
     *
     * @return void
     */
    public function testPrepareDataSource(): void
    {
        $payload = [
            'recipient' => [
                'firstName' => 'Jan',
                'lastName' => 'Kowalski',
                'street' => 'Polna',
                'houseNumber' => '1',
                'city' => 'Poznań',
                'country' => 'PL',
            ],
        ];

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 1,
                        'shipment_data' => json_encode($payload),
                    ],
                ],
            ],
        ];

        $result = $this->column->prepareDataSource($dataSource);

        $this->assertArrayHasKey('recipient_address', $result['data']['items'][0]);
        $this->assertStringContainsString('Jan Kowalski', $result['data']['items'][0]['recipient_address']);
        $this->assertStringContainsString('Polna 1', $result['data']['items'][0]['recipient_address']);
    }

    /**
     * Test formatting when various fields are missing, null, or have unexpected types.
     *
     * @return void
     */
    public function testFormatHandlesMissingFieldsGracefully(): void
    {
        // 1. Array street lines (common in Magento address data)
        $payload1 = [
            'recipient' => [
                'street' => ['ul. Morska', '15'],
                'houseNumber' => '',
                'city' => 'Gdynia',
            ],
        ];
        $html1 = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload1)]);
        $this->assertStringContainsString('ul. Morska 15', $html1);
        $this->assertStringContainsString('Gdynia', $html1);

        // 2. Only house number without street
        $payload2 = [
            'recipient' => [
                'houseNumber' => '42',
                'flatNumber' => '3',
                'city' => 'Wrocław',
            ],
        ];
        $html2 = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload2)]);
        $this->assertStringContainsString('42/3', $html2);
        $this->assertStringContainsString('Wrocław', $html2);

        // 3. Integer postal code and null fields
        $payload3 = [
            'recipient' => [
                'firstName' => null,
                'lastName' => null,
                'name' => null,
                'postalCode' => 12345,
                'city' => null,
                'country' => null,
            ],
        ];
        $html3 = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload3)]);
        $this->assertSame('12345', $html3);

        // 4. All fields null or empty inside recipient
        $payload4 = [
            'recipient' => [
                'firstName' => null,
                'lastName' => '',
                'name' => null,
                'street' => '',
                'city' => null,
            ],
        ];
        $html4 = $this->column->formatRecipientAddress(['shipment_data' => json_encode($payload4)]);
        $this->assertSame('', $html4);
    }
}
