<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment\Data;

use Ideo\Dhl\Model\Shipment\Data\CustomsData;
use PHPUnit\Framework\TestCase;

class CustomsDataTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $customs = new CustomsData();

        $this->assertNull($customs->getSelectedCustomsTemplateId());
        $this->assertSame('U', $customs->getCustomsType());
        $this->assertSame('11', $customs->getCategoryOfItem());
        $this->assertSame('EUR', $customs->getCurrency());
        $this->assertSame('PL', $customs->getCountryOfOrigin());
        $this->assertNull($customs->getDefaultTariffCode());
        $this->assertNull($customs->getCostsOfShipment());
        $this->assertNull($customs->getGrossWeight());
        $this->assertNull($customs->getFirstName());
        $this->assertNull($customs->getSecondaryName());
        $this->assertNull($customs->getNipNr());
        $this->assertNull($customs->getEoriNr());
        $this->assertNull($customs->getVatRegistrationNumber());
        $this->assertNull($customs->getEoriNrReceiver());
        $this->assertNull($customs->getVatRegistrationNumberReceiver());
        $this->assertNull($customs->getInvoiceNr());
        $this->assertNull($customs->getInvoiceDate());
        $this->assertNull($customs->getInvoiceFile());
        $this->assertNull($customs->getAdditionalInfo());
        $this->assertTrue($customs->isNotExceedValue());
        $this->assertTrue($customs->isNotProhibitedGoods());
        $this->assertTrue($customs->isNotRestrictedGoods());
        $this->assertTrue($customs->isInvoiceContent());
        $this->assertSame([], $customs->getCustomsItems());
    }

    public function testToArrayAndFromArray(): void
    {
        $data = [
            'selectedCustomsTemplateId' => 5,
            'customsType' => 'I',
            'categoryOfItem' => '21',
            'currency' => 'GBP',
            'countryOfOrigin' => 'DE',
            'defaultTariffCode' => '61091000',
            'costsOfShipment' => 25.50,
            'grossWeight' => 3.25,
            'firstName' => 'Jan',
            'secondaryName' => 'Kowalski',
            'nipNr' => '1234567890',
            'eoriNr' => 'PL12345678900000',
            'vatRegistrationNumber' => 'PL1234567890',
            'eoriNrReceiver' => 'GB987654321000',
            'vatRegistrationNumberReceiver' => 'GB987654321',
            'invoiceNr' => 'INV/2026/09/001',
            'invoiceDate' => '2026-09-22',
            'invoiceFile' => '/media/dhl/invoice.pdf',
            'additionalInfo' => 'Sample info',
            'notExceedValue' => false,
            'notProhibitedGoods' => true,
            'notRestrictedGoods' => true,
            'invoiceContent' => true,
            'customsItems' => [
                [
                    'namePl' => 'Koszulka',
                    'nameEn' => 'T-Shirt',
                    'quantity' => 2,
                    'weight' => 0.5,
                    'value' => 49.99,
                    'tariffCode' => '61091000',
                    'countryOfOrigin' => 'PL',
                ],
            ],
        ];

        $customs = CustomsData::fromArray($data);

        $this->assertSame(5, $customs->getSelectedCustomsTemplateId());
        $this->assertSame('I', $customs->getCustomsType());
        $this->assertSame('21', $customs->getCategoryOfItem());
        $this->assertSame('GBP', $customs->getCurrency());
        $this->assertSame('DE', $customs->getCountryOfOrigin());
        $this->assertSame('61091000', $customs->getDefaultTariffCode());
        $this->assertSame(25.50, $customs->getCostsOfShipment());
        $this->assertSame(3.25, $customs->getGrossWeight());
        $this->assertSame('Jan', $customs->getFirstName());
        $this->assertSame('Kowalski', $customs->getSecondaryName());
        $this->assertSame('1234567890', $customs->getNipNr());
        $this->assertSame('PL12345678900000', $customs->getEoriNr());
        $this->assertSame('PL1234567890', $customs->getVatRegistrationNumber());
        $this->assertSame('GB987654321000', $customs->getEoriNrReceiver());
        $this->assertSame('GB987654321', $customs->getVatRegistrationNumberReceiver());
        $this->assertSame('INV/2026/09/001', $customs->getInvoiceNr());
        $this->assertSame('2026-09-22', $customs->getInvoiceDate());
        $this->assertSame('/media/dhl/invoice.pdf', $customs->getInvoiceFile());
        $this->assertSame('Sample info', $customs->getAdditionalInfo());
        $this->assertFalse($customs->isNotExceedValue());
        $this->assertTrue($customs->isNotProhibitedGoods());
        $this->assertTrue($customs->isNotRestrictedGoods());
        $this->assertTrue($customs->isInvoiceContent());
        $this->assertCount(1, $customs->getCustomsItems());

        $array = $customs->toArray();
        $this->assertSame(5, $array['selectedCustomsTemplateId']);
        $this->assertSame('I', $array['customsType']);
        $this->assertSame('Koszulka', $array['customsItems'][0]['namePl']);
    }

    /**
     * Verify a newly configured individual clearance defaults the agreement to checked.
     *
     * @return void
     */
    public function testFromArrayDefaultsInvoiceContentAgreementToChecked(): void
    {
        $customs = CustomsData::fromArray(['customsType' => 'I']);

        $this->assertTrue($customs->isInvoiceContent());
    }

    /**
     * Verify empty or whitespace receiver EORI string is normalized to null.
     *
     * @return void
     */
    public function testFromArrayNormalizesEmptyReceiverEoriToNull(): void
    {
        $customsWithEmptyString = CustomsData::fromArray(['eoriNrReceiver' => '   ']);
        $this->assertNull($customsWithEmptyString->getEoriNrReceiver());

        $customsWithEmptyReceiverEori = CustomsData::fromArray(['receiverEori' => '']);
        $this->assertNull($customsWithEmptyReceiverEori->getEoriNrReceiver());
    }
}
