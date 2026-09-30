<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Plugin\Checkout;

use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\ServicePoint\ServicePointValidator;
use Ideo\Dhl\Plugin\Checkout\SaveServicePointToQuoteAddress;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;

class SaveServicePointToQuoteAddressTest extends TestCase
{
    public function testBeforeSaveAddressInformationAttachesPointDataToAddress(): void
    {
        $validatorStub = $this->createStub(ServicePointValidator::class);
        $plugin = new SaveServicePointToQuoteAddress($validatorStub, new MethodCatalog());

        $managementStub = $this->createStub(ShippingInformationManagementInterface::class);
        $informationStub = $this->createStub(ShippingInformationInterface::class);
        $addressMock = $this->createStub(Address::class);

        $extAttributesStub = new DataObject([
            'dhl_service_point_id' => 'SAP123',
            'dhl_service_point_name' => 'Żabka Test',
            'dhl_service_point_street' => 'Polna 1',
            'dhl_service_point_postcode' => '00-111',
            'dhl_service_point_city' => 'Warszawa',
            'dhl_is_packstation' => true,
            'dhl_is_postfiliale' => false,
            'dhl_postnummer' => '123456',
        ]);

        $addressMock->method('getExtensionAttributes')->willReturn($extAttributesStub);

        $savedData = [];
        $addressMock->method('setData')->willReturnCallback(
            static function ($key, $value = null) use (&$savedData) {
                if (is_string($key)) {
                    $savedData[$key] = $value;
                }
                return null;
            }
        );

        $informationStub->method('getShippingAddress')->willReturn($addressMock);
        $informationStub->method('getShippingCarrierCode')->willReturn('dhlparcel');
        $informationStub->method('getShippingMethodCode')->willReturn(MethodCatalog::METHOD_CONNECT_PACKSTATION);

        $result = $plugin->beforeSaveAddressInformation($managementStub, 1, $informationStub);

        $this->assertEquals([1, $informationStub], $result);
        $this->assertEquals('SAP123', $savedData['dhl_service_point_id']);
        $this->assertEquals('Żabka Test', $savedData['dhl_service_point_name']);
        $this->assertEquals('Polna 1', $savedData['dhl_service_point_street']);
        $this->assertEquals('00-111', $savedData['dhl_service_point_postcode']);
        $this->assertEquals('Warszawa', $savedData['dhl_service_point_city']);
        $this->assertEquals(1, $savedData['dhl_is_packstation']);
        $this->assertEquals(0, $savedData['dhl_is_postfiliale']);
        $this->assertEquals('123456', $savedData['dhl_postnummer']);
    }

    public function testBeforeSaveAddressInformationFallbackToAddressData(): void
    {
        $validatorStub = $this->createStub(ServicePointValidator::class);
        $plugin = new SaveServicePointToQuoteAddress($validatorStub, new MethodCatalog());

        $managementStub = $this->createStub(ShippingInformationManagementInterface::class);
        $informationStub = $this->createStub(ShippingInformationInterface::class);
        $addressMock = $this->createStub(Address::class);

        $addressMock->method('getExtensionAttributes')->willReturn(null);

        $savedData = [
            'dhl_service_point_id' => 'SAP999',
            'dhl_service_point_name' => 'Żabka Fallback',
            'dhl_service_point_street' => 'Długa 2',
            'dhl_service_point_postcode' => '38-542',
            'dhl_service_point_city' => 'Rzepedź',
            'dhl_is_packstation' => false,
            'dhl_is_postfiliale' => true,
        ];

        $addressMock->method('getData')->willReturnCallback(
            static function ($key = null) use (&$savedData) {
                if ($key === null) {
                    return $savedData;
                }
                return $savedData[$key] ?? null;
            }
        );

        $addressMock->method('setData')->willReturnCallback(
            static function ($key, $value = null) use (&$savedData) {
                if (is_string($key)) {
                    $savedData[$key] = $value;
                }
                return null;
            }
        );

        $informationStub->method('getShippingAddress')->willReturn($addressMock);
        $informationStub->method('getShippingCarrierCode')->willReturn('dhlparcel');
        $informationStub->method('getShippingMethodCode')->willReturn(MethodCatalog::METHOD_ECONOMY_ALL);

        $result = $plugin->beforeSaveAddressInformation($managementStub, 1, $informationStub);

        $this->assertEquals([1, $informationStub], $result);
        $this->assertEquals('SAP999', $savedData['dhl_service_point_id']);
        $this->assertEquals('Żabka Fallback', $savedData['dhl_service_point_name']);
    }

    public function testBeforeSaveAddressInformationClearsPointDataWhenExtAttributesHasNoPoint(): void
    {
        $validatorStub = $this->createStub(ServicePointValidator::class);
        $plugin = new SaveServicePointToQuoteAddress($validatorStub, new MethodCatalog());

        $managementStub = $this->createStub(ShippingInformationManagementInterface::class);
        $informationStub = $this->createStub(ShippingInformationInterface::class);
        $addressMock = $this->createStub(Address::class);

        $extAttributesStub = new DataObject([]);
        $addressMock->method('getExtensionAttributes')->willReturn($extAttributesStub);

        $savedData = [
            'dhl_service_point_id' => 'OLD_SAP',
            'dhl_service_point_name' => 'Old Point',
        ];

        $addressMock->method('setData')->willReturnCallback(
            static function ($key, $value = null) use (&$savedData) {
                if (is_string($key)) {
                    $savedData[$key] = $value;
                }
                return null;
            }
        );

        $informationStub->method('getShippingAddress')->willReturn($addressMock);
        $informationStub->method('getShippingCarrierCode')->willReturn('dhlparcel');
        $informationStub->method('getShippingMethodCode')->willReturn('courier');

        $result = $plugin->beforeSaveAddressInformation($managementStub, 1, $informationStub);

        $this->assertEquals([1, $informationStub], $result);
        $this->assertNull($savedData['dhl_service_point_id']);
        $this->assertNull($savedData['dhl_service_point_name']);
    }

    public function testBeforeSaveAddressInformationSetsPostfilialeForNonDeInternationalPoint(): void
    {
        $validatorStub = $this->createStub(ServicePointValidator::class);
        $plugin = new SaveServicePointToQuoteAddress($validatorStub, new MethodCatalog());

        $managementStub = $this->createStub(ShippingInformationManagementInterface::class);
        $informationStub = $this->createStub(ShippingInformationInterface::class);
        $addressMock = $this->createStub(Address::class);

        $extAttributesStub = new DataObject([
            'dhl_service_point_id' => 'AT123',
            'dhl_service_point_name' => 'Postamt Wien',
            'dhl_service_point_street' => 'Hauptstrasse 10',
            'dhl_service_point_postcode' => '1010',
            'dhl_service_point_city' => 'Wien',
            'dhl_is_packstation' => false,
            'dhl_is_postfiliale' => false,
        ]);

        $addressMock->method('getExtensionAttributes')->willReturn($extAttributesStub);
        $addressMock->method('getCountryId')->willReturn('AT');

        $savedData = [];
        $addressMock->method('setData')->willReturnCallback(
            static function ($key, $value = null) use (&$savedData) {
                if (is_string($key)) {
                    $savedData[$key] = $value;
                }
                return null;
            }
        );

        $informationStub->method('getShippingAddress')->willReturn($addressMock);
        $informationStub->method('getShippingCarrierCode')->willReturn('dhlparcel');
        $informationStub->method('getShippingMethodCode')->willReturn(MethodCatalog::METHOD_CONNECT_PARCELSHOP);

        $plugin->beforeSaveAddressInformation($managementStub, 1, $informationStub);

        $this->assertEquals(0, $savedData['dhl_is_packstation']);
        $this->assertEquals(1, $savedData['dhl_is_postfiliale']);
    }

    public function testBeforeSaveAddressInformationSetsPostfilialeFalseForDomesticPointMethod(): void
    {
        $validatorStub = $this->createStub(ServicePointValidator::class);
        $plugin = new SaveServicePointToQuoteAddress($validatorStub, new MethodCatalog());

        $managementStub = $this->createStub(ShippingInformationManagementInterface::class);
        $informationStub = $this->createStub(ShippingInformationInterface::class);
        $addressMock = $this->createStub(Address::class);

        $extAttributesStub = new DataObject([
            'dhl_service_point_id' => 'PL123',
            'dhl_service_point_name' => 'POP Zabka',
            'dhl_service_point_street' => 'Warszawska 1',
            'dhl_service_point_postcode' => '00-001',
            'dhl_service_point_city' => 'Warszawa',
            'dhl_is_packstation' => false,
            'dhl_is_postfiliale' => false,
        ]);

        $addressMock->method('getExtensionAttributes')->willReturn($extAttributesStub);
        $addressMock->method('getCountryId')->willReturn('PL');

        $savedData = [];
        $addressMock->method('setData')->willReturnCallback(
            static function ($key, $value = null) use (&$savedData) {
                if (is_string($key)) {
                    $savedData[$key] = $value;
                }
                return null;
            }
        );

        $informationStub->method('getShippingAddress')->willReturn($addressMock);
        $informationStub->method('getShippingCarrierCode')->willReturn('dhlparcel');
        $informationStub->method('getShippingMethodCode')->willReturn(MethodCatalog::METHOD_ECONOMY_POP);

        $plugin->beforeSaveAddressInformation($managementStub, 1, $informationStub);

        $this->assertEquals(0, $savedData['dhl_is_packstation']);
        $this->assertEquals(0, $savedData['dhl_is_postfiliale']);
    }

    public function testBeforeSaveAddressInformationClearsPointForNonDhlCarrierWithMatchingMethodCode(): void
    {
        $validatorStub = $this->createStub(ServicePointValidator::class);
        $plugin = new SaveServicePointToQuoteAddress($validatorStub, new MethodCatalog());

        $managementStub = $this->createStub(ShippingInformationManagementInterface::class);
        $informationStub = $this->createStub(ShippingInformationInterface::class);
        $addressMock = $this->createStub(Address::class);
        $addressMock->method('getExtensionAttributes')->willReturn(new DataObject([
            'dhl_service_point_id' => 'OLD_POINT',
            'dhl_service_point_name' => 'Old Point',
        ]));

        $savedData = [];
        $addressMock->method('setData')->willReturnCallback(
            static function ($key, $value = null) use (&$savedData) {
                if (is_string($key)) {
                    $savedData[$key] = $value;
                }
                return null;
            }
        );

        $informationStub->method('getShippingAddress')->willReturn($addressMock);
        $informationStub->method('getShippingCarrierCode')->willReturn('other_carrier');
        $informationStub->method('getShippingMethodCode')->willReturn(MethodCatalog::METHOD_ECONOMY_ALL);

        $plugin->beforeSaveAddressInformation($managementStub, 1, $informationStub);

        $this->assertNull($savedData['dhl_service_point_id']);
        $this->assertSame(0, $savedData['dhl_is_packstation']);
        $this->assertSame(0, $savedData['dhl_is_postfiliale']);
    }
}
