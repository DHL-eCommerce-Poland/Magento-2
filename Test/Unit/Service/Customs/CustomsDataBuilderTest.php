<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Service\Customs;

use Ideo\Dhl\Model\CustomsTemplate;
use Ideo\Dhl\Model\CustomsTemplateRepository;
use Ideo\Dhl\Model\Source\CustomsType;
use Ideo\Dhl\Service\Customs\CustomsDataBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CustomsDataBuilderTest extends TestCase
{
    public function testBuildCustomsDataSimplifiedClearanceSuccess(): void
    {
        $templateRepositoryMock = $this->createMock(CustomsTemplateRepository::class);
        $serializer = new Json();
        $fileDriver = $this->createStub(FileDriver::class);

        $presetItems = [
            [
                'nameEn' => 'Custom T-Shirt En',
                'namePl' => 'Custom T-Shirt Pl',
                'quantity' => 2,
                'weight' => 0.5,
                'value' => 49.99
            ]
        ];

        $templateMock = $this->createStub(CustomsTemplate::class);
        $templateMock->method('getCustomsType')->willReturn(CustomsType::TYPE_SIMPLIFIED);
        $templateMock->method('getCurrency')->willReturn('USD');
        $templateMock->method('getCategoryOfItem')->willReturn('11');
        $templateMock->method('getCountryOfOrigin')->willReturn('PL');
        $templateMock->method('getDefaultTariffCode')->willReturn('61091000');
        $templateMock->method('getCostsOfShipment')->willReturn(16.00);
        $templateMock->method('getGrossWeight')->willReturn(1.0);
        $templateMock->method('getContactFirstName')->willReturn('John');
        $templateMock->method('getContactLastName')->willReturn('Doe');
        $templateMock->method('getShipperEori')->willReturn('PL12345678900000');
        $templateMock->method('getShipperNip')->willReturn('1234567890');
        $templateMock->method('getShipperVatNo')->willReturn('PL1234567890');
        $templateMock->method('getCustomsItems')->willReturn(json_encode($presetItems));

        $templateRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(2)
            ->willReturn($templateMock);

        $builder = new CustomsDataBuilder(
            $templateRepositoryMock,
            $serializer,
            $fileDriver
        );

        $customsData = $builder->buildCustomsData(2);

        $this->assertSame(CustomsType::TYPE_SIMPLIFIED, $customsData['customsType']);
        $this->assertSame(16, $customsData['costsOfShipment']);
        $this->assertSame('USD', $customsData['currency']);
        $this->assertSame('11', $customsData['categoryOfItem']);
        $this->assertSame('PL', $customsData['countryOfOrigin']);
        $this->assertSame(1.0, $customsData['grossWeight']);
        $this->assertSame('John', $customsData['firstName']);
        $this->assertSame('Doe', $customsData['secondaryName']);
        $this->assertSame('PL12345678900000', $customsData['shipperEori']);
        $this->assertSame('1234567890', $customsData['shipperNip']);
        $this->assertSame('PL1234567890', $customsData['vatRegistrationNumber']);

        $this->assertCount(1, $customsData['customsItem']);
        $this->assertSame('Custom T-Shirt Pl', $customsData['customsItem'][0]['namePl']);
        $this->assertSame('Custom T-Shirt En', $customsData['customsItem'][0]['nameEn']);
        $this->assertSame(2.0, $customsData['customsItem'][0]['quantity']);
        $this->assertSame(0.5, $customsData['customsItem'][0]['weight']);
        $this->assertSame(49.99, $customsData['customsItem'][0]['value']);
        $this->assertSame('61091000', $customsData['customsItem'][0]['tariffCode']);
        $this->assertSame('PL', $customsData['customsItem'][0]['countryOfOrigin']);

        $this->assertTrue($customsData['customAgreements']['notExceedValue']);
        $this->assertTrue($customsData['customAgreements']['notProhibitedGoods']);
        $this->assertTrue($customsData['customAgreements']['notRestrictedGoods']);
        $this->assertFalse($customsData['customAgreements']['invoiceContent']);
    }

    public function testBuildCustomsDataThrowsExceptionWhenNoDefaultTemplate(): void
    {
        $templateRepositoryMock = $this->createMock(CustomsTemplateRepository::class);
        $serializer = new Json();
        $fileDriver = $this->createStub(FileDriver::class);

        $templateRepositoryMock->expects($this->once())
            ->method('getDefaultTemplate')
            ->willReturn(null);

        $builder = new CustomsDataBuilder(
            $templateRepositoryMock,
            $serializer,
            $fileDriver
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'No Customs Clearance configuration found for international shipment. Please configure default Customs.'
        );

        $builder->buildCustomsData(null);
    }

    public function testBuildCustomsDataIndividualClearanceThrowsExceptionWhenNoInvoiceFile(): void
    {
        $templateRepositoryMock = $this->createMock(CustomsTemplateRepository::class);
        $serializer = new Json();
        $fileDriver = $this->createStub(FileDriver::class);

        $templateMock = $this->createStub(CustomsTemplate::class);
        $templateMock->method('getCustomsType')->willReturn(CustomsType::TYPE_INDIVIDUAL);
        $templateMock->method('getInvoiceFile')->willReturn(null);

        $templateRepositoryMock->expects($this->once())
            ->method('getDefaultTemplate')
            ->willReturn($templateMock);

        $builder = new CustomsDataBuilder(
            $templateRepositoryMock,
            $serializer,
            $fileDriver
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'A VAT Invoice PDF file is required for individual customs clearance (I). ' .
            'Please upload invoice in Customs Template.'
        );

        $builder->buildCustomsData(null);
    }

    public function testBuildCustomsDataIndividualClearanceSuccess(): void
    {
        $templateRepositoryMock = $this->createMock(CustomsTemplateRepository::class);
        $serializer = new Json();
        $fileDriver = $this->createStub(FileDriver::class);

        $presetItems = [
            [
                'nameEn' => 'Sample Product En',
                'namePl' => 'Sample Product Pl',
                'quantity' => 1,
                'weight' => 1.0,
                'value' => 100.0
            ]
        ];

        $templateMock = $this->createStub(CustomsTemplate::class);
        $templateMock->method('getCustomsType')->willReturn(CustomsType::TYPE_INDIVIDUAL);
        $templateMock->method('getCurrency')->willReturn('EUR');
        $templateMock->method('getCategoryOfItem')->willReturn('11');
        $templateMock->method('getCountryOfOrigin')->willReturn('PL');
        $templateMock->method('getDefaultTariffCode')->willReturn('12345678');
        $templateMock->method('getShipperEori')->willReturn('PL9999999999');
        $templateMock->method('getShipperNip')->willReturn('9999999999');
        $templateMock->method('getReceiverVatNo')->willReturn('DE987654321');
        $templateMock->method('getInvoiceDate')->willReturn('2026-09-04');
        $templateMock->method('getInvoiceFile')->willReturn('/media/dhl/customs_templates/invoice.pdf');
        $templateMock->method('getCustomsItems')->willReturn(json_encode($presetItems));

        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturn('DUMMY_PDF_CONTENT');

        $templateRepositoryMock->expects($this->once())
            ->method('getDefaultTemplate')
            ->willReturn($templateMock);

        $builder = new CustomsDataBuilder(
            $templateRepositoryMock,
            $serializer,
            $fileDriver
        );

        $customsData = $builder->buildCustomsData(null);

        $this->assertSame(CustomsType::TYPE_INDIVIDUAL, $customsData['customsType']);
        $this->assertSame('2026-09-04', $customsData['invoiceDate']);
        $this->assertSame(base64_encode('DUMMY_PDF_CONTENT'), $customsData['invoice']);
        $this->assertSame('9999999999', $customsData['nipNr']);
        $this->assertSame('PL9999999999', $customsData['eoriNr']);
        $this->assertSame('DE987654321', $customsData['vatRegistrationNumberReceiver']);

        $this->assertFalse($customsData['customAgreements']['notExceedValue']);
        $this->assertTrue($customsData['customAgreements']['invoiceContent']);

        $this->assertSame('12345678', $customsData['customsItem'][0]['tariffCode']);
        $this->assertSame('PL', $customsData['customsItem'][0]['countryOfOrigin']);
    }

    public function testBuildCustomsDataWithTemplateOverrides(): void
    {
        $templateRepositoryMock = $this->createMock(CustomsTemplateRepository::class);
        $serializer = new Json();
        $fileDriver = $this->createStub(FileDriver::class);

        $templateMock = $this->createStub(CustomsTemplate::class);
        $templateMock->method('getCustomsType')->willReturn(CustomsType::TYPE_SIMPLIFIED);
        $templateMock->method('getCurrency')->willReturn('EUR');
        $templateMock->method('getCategoryOfItem')->willReturn('11');
        $templateMock->method('getCountryOfOrigin')->willReturn('PL');
        $templateMock->method('getDefaultTariffCode')->willReturn('11223344');
        $templateMock->method('getCostsOfShipment')->willReturn(25.00);
        $templateMock->method('getGrossWeight')->willReturn(3.5);
        $templateMock->method('getContactFirstName')->willReturn('ContactFirst');
        $templateMock->method('getContactLastName')->willReturn('ContactLast');
        $templateMock->method('getReceiverEori')->willReturn('GB888888');
        $templateMock->method('getReceiverVatNo')->willReturn('GB777777');
        $templateMock->method('getAdditionalInfo')->willReturn('Custom note');
        $templateMock->method('getNotExceedValue')->willReturn(true);
        $templateMock->method('getNotProhibitedGoods')->willReturn(true);
        $templateMock->method('getNotRestrictedGoods')->willReturn(true);

        $presetItems = [
            [
                'nameEn' => 'Preset Goods En',
                'namePl' => 'Preset Goods Pl',
                'quantity' => 5,
                'weight' => 2.0,
                'value' => 50.0
            ]
        ];
        $templateMock->method('getCustomsItems')->willReturn(json_encode($presetItems));

        $templateRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(3)
            ->willReturn($templateMock);

        $builder = new CustomsDataBuilder(
            $templateRepositoryMock,
            $serializer,
            $fileDriver
        );

        $customsData = $builder->buildCustomsData(3);

        $this->assertSame(25, $customsData['costsOfShipment']);
        $this->assertSame(3.5, $customsData['grossWeight']);
        $this->assertSame('ContactFirst', $customsData['firstName']);
        $this->assertSame('ContactLast', $customsData['secondaryName']);
        $this->assertSame('GB888888', $customsData['eoriNrReceiver']);
        $this->assertSame('Custom note', $customsData['additionalInfo']);

        $this->assertCount(1, $customsData['customsItem']);
        $this->assertSame('Preset Goods En', $customsData['customsItem'][0]['nameEn']);
        $this->assertSame('Preset Goods Pl', $customsData['customsItem'][0]['namePl']);
        $this->assertSame(5.0, $customsData['customsItem'][0]['quantity']);
        $this->assertSame(2.0, $customsData['customsItem'][0]['weight']);
        $this->assertSame(50.0, $customsData['customsItem'][0]['value']);
        $this->assertSame('11223344', $customsData['customsItem'][0]['tariffCode']);
        $this->assertSame('PL', $customsData['customsItem'][0]['countryOfOrigin']);
    }

    public function testBuildCustomsDataUsesItemLevelTariffCodeAndCountryOfOrigin(): void
    {
        $templateRepositoryMock = $this->createMock(CustomsTemplateRepository::class);
        $serializer = new Json();
        $fileDriver = $this->createStub(FileDriver::class);

        $templateMock = $this->createStub(CustomsTemplate::class);
        $templateMock->method('getCustomsType')->willReturn(CustomsType::TYPE_SIMPLIFIED);
        $templateMock->method('getCostsOfShipment')->willReturn(10.0);
        $templateMock->method('getCurrency')->willReturn('PLN');
        $templateMock->method('getCategoryOfItem')->willReturn('11');
        $templateMock->method('getCountryOfOrigin')->willReturn('PL');
        $templateMock->method('getDefaultTariffCode')->willReturn('DEFAULT123');
        $templateMock->method('getGrossWeight')->willReturn(1.0);
        $templateMock->method('getContactFirstName')->willReturn('Jan');
        $templateMock->method('getContactLastName')->willReturn('Kowalski');
        $templateMock->method('getShipperEori')->willReturn('PL123');
        $templateMock->method('getShipperNip')->willReturn('123');
        $templateMock->method('getShipperVatNo')->willReturn('PL123');

        $itemsWithCustomCodes = [
            [
                'nameEn' => 'Item 1',
                'namePl' => 'Przedmiot 1',
                'quantity' => 1.0,
                'weight' => 0.5,
                'value' => 20.0,
                'tariffCode' => 'ITEM_CODE1',
                'countryOfOrigin' => 'DE',
            ],
            [
                'nameEn' => 'Item 2',
                'namePl' => 'Przedmiot 2',
                'quantity' => 2.0,
                'weight' => 0.5,
                'value' => 30.0,
                // tariffCode and countryOfOrigin omitted -> fallback to template
            ],
        ];
        $templateMock->method('getCustomsItems')->willReturn(json_encode($itemsWithCustomCodes));

        $templateRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(5)
            ->willReturn($templateMock);

        $builder = new CustomsDataBuilder(
            $templateRepositoryMock,
            $serializer,
            $fileDriver
        );

        $customsData = $builder->buildCustomsData(5);

        $this->assertCount(2, $customsData['customsItem']);
        // Item 1 uses its own tariffCode and countryOfOrigin
        $this->assertSame('ITEM_CODE1', $customsData['customsItem'][0]['tariffCode']);
        $this->assertSame('DE', $customsData['customsItem'][0]['countryOfOrigin']);

        // Item 2 falls back to template defaultTariffCode and countryOfOrigin
        $this->assertSame('DEFAULT123', $customsData['customsItem'][1]['tariffCode']);
        $this->assertSame('PL', $customsData['customsItem'][1]['countryOfOrigin']);
    }
}
