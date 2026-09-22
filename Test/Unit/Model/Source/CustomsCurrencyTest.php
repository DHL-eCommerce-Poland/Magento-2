<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Source;

use Ideo\Dhl\Model\Source\CustomsCurrency;
use Ideo\Dhl\Model\Source\CustomsType;
use Magento\Framework\Locale\ListsInterface;
use PHPUnit\Framework\TestCase;

class CustomsCurrencyTest extends TestCase
{
    private ListsInterface $localeLists;
    private CustomsCurrency $source;

    protected function setUp(): void
    {
        $this->localeLists = $this->createStub(ListsInterface::class);
        $this->localeLists->method('getOptionAllCurrencies')->willReturn([
            ['value' => 'USD', 'label' => 'US Dollar'],
            ['value' => 'EUR', 'label' => 'Euro'],
            ['value' => 'CHF', 'label' => 'Swiss Franc'],
            ['value' => 'GBP', 'label' => 'British Pound'],
            ['value' => 'PLN', 'label' => 'Polish Zloty'],
            ['value' => 'JPY', 'label' => 'Japanese Yen'],
        ]);

        $this->source = new CustomsCurrency($this->localeLists);
    }

    public function testToOptionArrayFiltersFromMagentoLocaleListAndPreservesLabels(): void
    {
        $options = $this->source->toOptionArray();

        $this->assertCount(5, $options);
        $this->assertSame([
            ['value' => 'GBP', 'label' => 'British Pound'],
            ['value' => 'PLN', 'label' => 'Polish Zloty'],
            ['value' => 'EUR', 'label' => 'Euro'],
            ['value' => 'CHF', 'label' => 'Swiss Franc'],
            ['value' => 'USD', 'label' => 'US Dollar'],
        ], $options);
    }

    public function testGetCurrenciesForTypeIndividual(): void
    {
        $currencies = $this->source->getCurrenciesForType(CustomsType::TYPE_INDIVIDUAL);
        $this->assertSame(['GBP', 'EUR'], $currencies);
    }

    public function testGetCurrenciesForTypeSimplified(): void
    {
        $currencies = $this->source->getCurrenciesForType(CustomsType::TYPE_SIMPLIFIED);
        $this->assertSame(['GBP', 'EUR', 'USD', 'PLN', 'CHF'], $currencies);
    }

    public function testGetCurrenciesForTypeShortDeclaration(): void
    {
        $currencies = $this->source->getCurrenciesForType(CustomsType::TYPE_SHORT_DECLARATION);
        $this->assertSame(['GBP', 'EUR', 'USD'], $currencies);
    }

    public function testGetCurrenciesForTypeInvalidReturnsEmpty(): void
    {
        $currencies = $this->source->getCurrenciesForType('NON_EXISTING');
        $this->assertSame([], $currencies);
    }

    public function testIsCurrencyAllowed(): void
    {
        $cases = [
            // Individual clearance (I): only GBP, EUR
            'I - GBP' => [CustomsType::TYPE_INDIVIDUAL, 'GBP', true],
            'I - EUR' => [CustomsType::TYPE_INDIVIDUAL, 'EUR', true],
            'I - USD' => [CustomsType::TYPE_INDIVIDUAL, 'USD', false],
            'I - PLN' => [CustomsType::TYPE_INDIVIDUAL, 'PLN', false],
            'I - CHF' => [CustomsType::TYPE_INDIVIDUAL, 'CHF', false],
            'I - JPY' => [CustomsType::TYPE_INDIVIDUAL, 'JPY', false],

            // Simplified clearance (U): GBP, EUR, USD, PLN, CHF
            'U - GBP' => [CustomsType::TYPE_SIMPLIFIED, 'GBP', true],
            'U - EUR' => [CustomsType::TYPE_SIMPLIFIED, 'EUR', true],
            'U - USD' => [CustomsType::TYPE_SIMPLIFIED, 'USD', true],
            'U - PLN' => [CustomsType::TYPE_SIMPLIFIED, 'PLN', true],
            'U - CHF' => [CustomsType::TYPE_SIMPLIFIED, 'CHF', true],
            'U - CAD' => [CustomsType::TYPE_SIMPLIFIED, 'CAD', false],

            // Short declaration (S): GBP, EUR, USD
            'S - GBP' => [CustomsType::TYPE_SHORT_DECLARATION, 'GBP', true],
            'S - EUR' => [CustomsType::TYPE_SHORT_DECLARATION, 'EUR', true],
            'S - USD' => [CustomsType::TYPE_SHORT_DECLARATION, 'USD', true],
            'S - PLN' => [CustomsType::TYPE_SHORT_DECLARATION, 'PLN', false],
            'S - CHF' => [CustomsType::TYPE_SHORT_DECLARATION, 'CHF', false],

            // Unknown type
            'Unknown type' => ['UNKNOWN', 'EUR', false],
        ];

        foreach ($cases as $name => [$type, $currency, $expected]) {
            $this->assertSame(
                $expected,
                $this->source->isCurrencyAllowed($type, $currency),
                "Failed asserting currency allowance for case: {$name}"
            );
        }
    }

    public function testGetOptionArrayForType(): void
    {
        $individualOptions = $this->source->getOptionArrayForType(CustomsType::TYPE_INDIVIDUAL);
        $this->assertSame([
            ['value' => 'GBP', 'label' => 'British Pound'],
            ['value' => 'EUR', 'label' => 'Euro'],
        ], $individualOptions);

        $shortOptions = $this->source->getOptionArrayForType(CustomsType::TYPE_SHORT_DECLARATION);
        $this->assertSame([
            ['value' => 'GBP', 'label' => 'GBP' ? 'British Pound' : ''],
            ['value' => 'EUR', 'label' => 'Euro'],
            ['value' => 'USD', 'label' => 'US Dollar'],
        ], $shortOptions);

        $simplifiedOptions = $this->source->getOptionArrayForType(CustomsType::TYPE_SIMPLIFIED);
        $this->assertSame([
            ['value' => 'GBP', 'label' => 'British Pound'],
            ['value' => 'EUR', 'label' => 'Euro'],
            ['value' => 'USD', 'label' => 'US Dollar'],
            ['value' => 'PLN', 'label' => 'Polish Zloty'],
            ['value' => 'CHF', 'label' => 'Swiss Franc'],
        ], $simplifiedOptions);
    }
}
