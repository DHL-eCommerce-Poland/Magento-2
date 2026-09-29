<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use DateTime;
use Ideo\Dhl\Model\ServicePoint\NearestPointResolver;
use Ideo\Dhl\Model\Shipment\OrderToShipmentDataMapper;
use Ideo\Dhl\Model\Shipment\OrderWeightAggregator;
use Ideo\Dhl\Model\Shipment\StreetAddressParser;
use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OrderToShipmentDataMapperTest extends TestCase
{
    /**
     * @var OrderToShipmentDataMapper
     */
    private $mapper;

    /**
     * @var NearestPointResolver|\PHPUnit\Framework\MockObject\MockObject
     */
    private $nearestPointResolverMock;

    protected function setUp(): void
    {
        $addressParser = new StreetAddressParser();
        $weightAggregator = new OrderWeightAggregator();
        $variableResolver = new TemplateVariableResolver();
        $serializer = new \Magento\Framework\Serialize\Serializer\Json();

        $timezoneMock = $this->createMock(TimezoneInterface::class);
        $timezoneMock->method('scopeDate')
            ->willReturn(new DateTime('2026-09-02'));

        $this->nearestPointResolverMock = $this->createMock(NearestPointResolver::class);

        $this->mapper = new OrderToShipmentDataMapper(
            $addressParser,
            $weightAggregator,
            $variableResolver,
            $serializer,
            $timezoneMock,
            $this->nearestPointResolverMock,
            new \Ideo\Dhl\Model\MethodCatalog()
        );
    }

    /**
     * Test mapping consumer order to DhlShipmentData object.
     *
     * @return void
     */
    public function testMapConsumerOrder(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getFirstname')->willReturn('Jan');
        $address->method('getLastname')->willReturn('Kowalski');
        $address->method('getCompany')->willReturn('');
        $address->method('getStreet')->willReturn(['Marszałkowska 10/12']);
        $address->method('getCity')->willReturn('Warszawa');
        $address->method('getPostcode')->willReturn('00-001');
        $address->method('getCountryId')->willReturn('PL');
        $address->method('getTelephone')->willReturn('500600700');
        $address->method('getEmail')->willReturn('jan@example.com');

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getEntityId')->willReturn(101);
        $order->method('getIncrementId')->willReturn('100000101');
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getItems')->willReturn([]);
        $order->method('getGrandTotal')->willReturn(150.00);
        $order->method('getOrderCurrencyCode')->willReturn('PLN');

        $shipmentData = $this->mapper->map($order);

        $this->assertSame(101, $shipmentData->getOrderId());
        $this->assertSame('', $shipmentData->getRecipient()->getName());
        $this->assertSame('Jan Kowalski', $shipmentData->getRecipient()->getContactPerson());
        $this->assertSame('C', $shipmentData->getRecipient()->getType());
        $this->assertSame('Marszałkowska', $shipmentData->getRecipient()->getStreet());
        $this->assertSame('10/12', $shipmentData->getRecipient()->getHouseNumber());
        $this->assertSame('', $shipmentData->getRecipient()->getFlatNumber());
        $this->assertSame('Warszawa', $shipmentData->getRecipient()->getCity());
        $this->assertSame('00001', $shipmentData->getRecipient()->getPostalCode());
        $this->assertSame('500600700', $shipmentData->getRecipient()->getPhone());
        $this->assertSame('jan@example.com', $shipmentData->getRecipient()->getEmail());
    }

    /**
     * Test mapping business company order with COD payment method.
     *
     * @return void
     */
    public function testMapCompanyOrderWithCod(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getFirstname')->willReturn('Adam');
        $address->method('getLastname')->willReturn('Nowak');
        $address->method('getCompany')->willReturn('Firma ABC Sp. z o.o.');
        $address->method('getStreet')->willReturn(['Polna 5']);
        $address->method('getCity')->willReturn('Kraków');
        $address->method('getPostcode')->willReturn('30-001');
        $address->method('getCountryId')->willReturn('PL');

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn('cashondelivery');

        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getEntityId')->willReturn(102);
        $order->method('getIncrementId')->willReturn('100000102');
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getShippingMethod')->willReturn('dhlparcel_parcel_polska_cod');
        $order->method('getPayment')->willReturn($payment);
        $order->method('getItems')->willReturn([]);
        $order->method('getGrandTotal')->willReturn(299.99);

        $shipmentData = $this->mapper->map($order);

        $this->assertSame('Firma ABC Sp. z o.o.', $shipmentData->getRecipient()->getName());
        $this->assertSame('B', $shipmentData->getRecipient()->getType());
        $this->assertSame('Adam Nowak', $shipmentData->getRecipient()->getContactPerson());
        $this->assertTrue($shipmentData->getSpecialServices()->isCollectOnDelivery());
        $this->assertSame(299.99, $shipmentData->getSpecialServices()->getCollectOnDeliveryValue());
    }

    /**
     * Test mapping shipping method to DHL API service type.
     *
     * @return void
     */
    public function testMapOrderShippingMethodToServiceType(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getStreet')->willReturn(['Main St 1']);
        $address->method('getCity')->willReturn('Warszawa');
        $address->method('getPostcode')->willReturn('00-001');

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getEntityId')->willReturn(103);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getShippingMethod')->willReturn('dhlparcel_premium');

        $shipmentData = $this->mapper->map($order);

        $this->assertSame('PR', $shipmentData->getShipmentSettings()->getServiceType());
    }

    /**
     * Test mapping order with a COD shipping method triggers COD special service.
     *
     * @return void
     */
    public function testMapOrderWithCodShippingMethodTriggersCodSpecialService(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getStreet')->willReturn(['Main St 1']);
        $address->method('getCity')->willReturn('Warszawa');
        $address->method('getPostcode')->willReturn('00-001');

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getEntityId')->willReturn(104);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getShippingMethod')->willReturn('dhlparcel_parcel_polska_cod');
        $order->method('getGrandTotal')->willReturn(450.50);

        $shipmentData = $this->mapper->map($order);

        $this->assertSame('AH', $shipmentData->getShipmentSettings()->getServiceType());
        $this->assertTrue($shipmentData->getSpecialServices()->isCollectOnDelivery());
        $this->assertSame(450.50, $shipmentData->getSpecialServices()->getCollectOnDeliveryValue());
    }

    /**
     * Test that mapping a virtual order throws LocalizedException.
     *
     * @return void
     */
    public function testVirtualOrderThrowsException(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(true);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('DHL shipping cannot be created for virtual orders.');

        $this->mapper->map($order);
    }

    /**
     * Test mapping order with packages provided as serialized JSON string.
     *
     * @return void
     */
    public function testMapWithSerializedPackagesJson(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getFirstname')->willReturn('Jan');
        $address->method('getLastname')->willReturn('Kowalski');
        $address->method('getStreet')->willReturn(['Testowa 1']);
        $address->method('getCity')->willReturn('Warszawa');
        $address->method('getPostcode')->willReturn('00-001');
        $address->method('getCountryId')->willReturn('PL');
        $address->method('getTelephone')->willReturn('500600700');
        $address->method('getEmail')->willReturn('jan@example.com');

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getEntityId')->willReturn(102);
        $order->method('getIncrementId')->willReturn('100000102');
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getItems')->willReturn([]);

        $packagesJson = json_encode([
            [
                'package_type' => 'PALLET',
                'quantity' => 1,
                'weight' => 25.0,
                'width' => 80.0,
                'length' => 120.0,
                'height' => 160.0,
                'is_non_standard' => 1,
                'has_euro_return' => 1,
            ],
            [
                'package_type' => 'PACKAGE',
                'quantity' => 2,
                'weight' => 5.0,
                'width' => 20.0,
                'length' => 30.0,
                'height' => 15.0,
                'is_non_standard' => 0,
                'has_euro_return' => 0,
            ],
        ]);

        $shipmentDefaults = [
            'packages' => $packagesJson,
        ];

        $shipmentData = $this->mapper->map($order, [], $shipmentDefaults);
        $packages = $shipmentData->getShipmentSettings()->getPackages();

        $this->assertCount(2, $packages);
        $this->assertSame('PALLET', $packages[0]->getPackageType());
        $this->assertSame(25.0, $packages[0]->getWeight());
        $this->assertTrue($packages[0]->hasEuroReturn());
        $this->assertSame('PACKAGE', $packages[1]->getPackageType());
        $this->assertSame(2, $packages[1]->getQuantity());
        $this->assertSame(5.0, $packages[1]->getWeight());
    }

    public function testMapWithoutTemplatePackagesReturnsEmptyPackagesList(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getStreet')->willReturn(['Main St 1']);
        $address->method('getCity')->willReturn('Warszawa');
        $address->method('getPostcode')->willReturn('00-001');

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getEntityId')->willReturn(107);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getShippingMethod')->willReturn('dhlparcel_parcel_polska');
        $order->method('getPayment')->willReturn($payment);
        $order->method('getItems')->willReturn([]);
        $order->method('getGrandTotal')->willReturn(100.00);

        $shipmentData = $this->mapper->map($order);
        $packages = $shipmentData->getShipmentSettings()->getPackages();

        $this->assertCount(0, $packages);
    }

    /**
     * Test mapping order when template has insurance enabled without explicit insurance value.
     *
     * @return void
     */
    public function testMapInsuranceDefaultValuePopulatedWithGrandTotal(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getStreet')->willReturn(['Main St 1']);
        $address->method('getCity')->willReturn('Warszawa');
        $address->method('getPostcode')->willReturn('00-001');

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getEntityId')->willReturn(108);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getShippingMethod')->willReturn('dhlparcel_parcel_polska');
        $order->method('getGrandTotal')->willReturn(350.75);

        $shipmentDefaults = [
            'specialServices' => [
                'insurance' => true,
            ]
        ];

        $shipmentData = $this->mapper->map($order, [], $shipmentDefaults);

        $this->assertEquals(350.75, $shipmentData->getOrderGrandTotal());
        $this->assertTrue($shipmentData->getSpecialServices()->isInsurance());
        $this->assertEquals(350.75, $shipmentData->getSpecialServices()->getInsuranceValue());
    }

    /**
     * Test mapping international order preserves hyphen in postcode for non-PL country.
     *
     * @return void
     */
    public function testMapInternationalOrderPreservesHyphenInPostcode(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getFirstname')->willReturn('Hans');
        $address->method('getLastname')->willReturn('Müller');
        $address->method('getStreet')->willReturn(['Hauptstraße 12']);
        $address->method('getCity')->willReturn('Berlin');
        $address->method('getPostcode')->willReturn('12-345');
        $address->method('getCountryId')->willReturn('DE');

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getEntityId')->willReturn(109);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getShippingMethod')->willReturn('dhlparcel_parcel_polska');

        $shipmentData = $this->mapper->map($order);

        $this->assertSame('DE', $shipmentData->getRecipient()->getCountry());
        $this->assertSame('12-345', $shipmentData->getRecipient()->getPostalCode());
    }

    /**
     * Test mapping order with French shipping address format correctly splits prefix house number.
     *
     * @return void
     */
    public function testMapFrenchAddressOrderSplitsStreetAndHouseNumber(): void
    {
        $address = $this->createStub(Address::class);
        $address->method('getFirstname')->willReturn('Pierre');
        $address->method('getLastname')->willReturn('Dubois');
        $address->method('getStreet')->willReturn(['30 Rue des Buissons']);
        $address->method('getCity')->willReturn('Paris');
        $address->method('getPostcode')->willReturn('75001');
        $address->method('getCountryId')->willReturn('FR');

        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $order = $this->createStub(Order::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getEntityId')->willReturn(110);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getShippingMethod')->willReturn('dhlparcel_parcel_polska');

        $shipmentData = $this->mapper->map($order);

        $this->assertSame('FR', $shipmentData->getRecipient()->getCountry());
        $this->assertSame('Rue des Buissons', $shipmentData->getRecipient()->getStreet());
        $this->assertSame('30', $shipmentData->getRecipient()->getHouseNumber());
    }
}
