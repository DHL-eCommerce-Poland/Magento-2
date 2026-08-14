<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Plugin\Checkout;

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
        $plugin = new SaveServicePointToQuoteAddress($validatorStub);

        $managementStub = $this->createStub(ShippingInformationManagementInterface::class);
        $informationStub = $this->createStub(ShippingInformationInterface::class);
        $addressMock = $this->createStub(Address::class);

        $extAttributesStub = new DataObject([
            'dhl_service_point_id' => 'SAP123',
            'dhl_service_point_name' => 'Żabka Test',
            'dhl_service_point_street' => 'Polna 1',
            'dhl_service_point_postcode' => '00-111',
            'dhl_service_point_city' => 'Warszawa',
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
        $informationStub->method('getShippingMethodCode')->willReturn('economy');

        $result = $plugin->beforeSaveAddressInformation($managementStub, 1, $informationStub);

        $this->assertEquals([1, $informationStub], $result);
        $this->assertEquals('SAP123', $savedData['dhl_service_point_id']);
        $this->assertEquals('Żabka Test', $savedData['dhl_service_point_name']);
        $this->assertEquals('Polna 1', $savedData['dhl_service_point_street']);
        $this->assertEquals('00-111', $savedData['dhl_service_point_postcode']);
        $this->assertEquals('Warszawa', $savedData['dhl_service_point_city']);
    }
}
