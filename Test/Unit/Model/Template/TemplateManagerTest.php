<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Template;

use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Model\Config\Source\AddressType as AddressTypeSource;
use Ideo\Dhl\Model\Config\Source\InternationalMapPointType;
use Ideo\Dhl\Model\Config\Source\LabelType as LabelTypeSource;
use Ideo\Dhl\Model\Config\Source\PackageType as PackageTypeSource;
use Ideo\Dhl\Model\Config\Source\ReturnBookCourier as ReturnBookCourierSource;
use Ideo\Dhl\Model\Config\Source\ReturnQrCodeType as ReturnQrCodeTypeSource;
use Ideo\Dhl\Model\Config\Source\ServiceCode as ServiceCodeSource;
use Ideo\Dhl\Model\Config\Source\ShipmentAddressType as ShipmentAddressTypeSource;
use Ideo\Dhl\Model\DeliveryService;
use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory as CustomsTemplateCollectionFactory;
use Ideo\Dhl\Model\ResourceModel\DeliveryService\Collection as DeliveryServiceCollection;
use Ideo\Dhl\Model\ResourceModel\DeliveryService\CollectionFactory as DeliveryServiceCollectionFactory;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint\CollectionFactory as DropOffPointCollectionFactory;
use Ideo\Dhl\Model\Shipment\OrderToShipmentDataMapper;
use Ideo\Dhl\Model\Source\CategoryOfItem;
use Ideo\Dhl\Model\Source\CustomsCurrency;
use Ideo\Dhl\Model\Source\CustomsType;
use Ideo\Dhl\Model\Template\TemplateManager;
use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use Magento\Directory\Model\Config\Source\Country as CountrySource;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TemplateManagerTest extends TestCase
{
    /**
     * @var DropOffPointCollectionFactory|MockObject
     */
    private $dropOffPointCollectionFactory;

    /**
     * @var DeliveryServiceCollectionFactory|MockObject
     */
    private $deliveryServiceCollectionFactory;

    /**
     * @var DropOffPointRepositoryInterface|MockObject
     */
    private $dropOffPointRepository;

    /**
     * @var DeliveryServiceRepositoryInterface|MockObject
     */
    private $deliveryServiceRepository;

    /**
     * @var CountrySource|MockObject
     */
    private $countrySource;

    /**
     * @var ServiceCodeSource|MockObject
     */
    private $serviceCodeSource;

    /**
     * @var LabelTypeSource|MockObject
     */
    private $labelTypeSource;

    /**
     * @var PackageTypeSource|MockObject
     */
    private $packageTypeSource;

    /**
     * @var AddressTypeSource|MockObject
     */
    private $addressTypeSource;

    /**
     * @var ShipmentAddressTypeSource|MockObject
     */
    private $shipmentAddressTypeSource;

    /**
     * @var ReturnQrCodeTypeSource|MockObject
     */
    private $returnQrCodeTypeSource;

    /**
     * @var ReturnBookCourierSource|MockObject
     */
    private $returnBookCourierSource;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    /**
     * @var OrderToShipmentDataMapper|MockObject
     */
    private $mapper;

    /**
     * @var TemplateVariableResolver|MockObject
     */
    private $variableResolver;

    /**
     * @var SerializerInterface|MockObject
     */
    private $serializer;

    /**
     * @var TimezoneInterface|MockObject
     */
    private $timezone;

    /**
     * @var MethodCatalog|MockObject
     */
    private $methodCatalog;

    /**
     * @var CustomsTemplateCollectionFactory|MockObject
     */
    private $customsTemplateCollectionFactory;

    /**
     * @var CustomsType|MockObject
     */
    private $customsTypeSource;

    /**
     * @var CategoryOfItem|MockObject
     */
    private $categoryOfItemSource;

    /**
     * @var CustomsCurrency|MockObject
     */
    private $customsCurrencySource;

    /**
     * @var InternationalMapPointType|MockObject
     */
    private $internationalMapPointTypeSource;

    /**
     * @var TemplateManager
     */
    private TemplateManager $templateManager;

    protected function setUp(): void
    {
        $this->dropOffPointCollectionFactory = $this->createStub(DropOffPointCollectionFactory::class);
        $this->deliveryServiceCollectionFactory = $this->createMock(DeliveryServiceCollectionFactory::class);
        $this->dropOffPointRepository = $this->createStub(DropOffPointRepositoryInterface::class);
        $this->deliveryServiceRepository = $this->createStub(DeliveryServiceRepositoryInterface::class);
        $this->countrySource = $this->createStub(CountrySource::class);
        $this->serviceCodeSource = $this->createStub(ServiceCodeSource::class);
        $this->labelTypeSource = $this->createStub(LabelTypeSource::class);
        $this->packageTypeSource = $this->createStub(PackageTypeSource::class);
        $this->addressTypeSource = $this->createStub(AddressTypeSource::class);
        $this->shipmentAddressTypeSource = $this->createStub(ShipmentAddressTypeSource::class);
        $this->returnQrCodeTypeSource = $this->createStub(ReturnQrCodeTypeSource::class);
        $this->returnBookCourierSource = $this->createStub(ReturnBookCourierSource::class);
        $this->orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $this->variableResolver = $this->createStub(TemplateVariableResolver::class);
        $this->serializer = $this->createStub(SerializerInterface::class);
        $this->timezone = $this->createStub(TimezoneInterface::class);
        $this->methodCatalog = $this->createStub(MethodCatalog::class);
        $this->customsTemplateCollectionFactory = $this->createStub(CustomsTemplateCollectionFactory::class);
        $this->customsTypeSource = $this->createStub(CustomsType::class);
        $this->categoryOfItemSource = $this->createStub(CategoryOfItem::class);
        $this->customsCurrencySource = $this->createStub(CustomsCurrency::class);
        $this->internationalMapPointTypeSource = $this->createStub(InternationalMapPointType::class);

        $this->templateManager = new TemplateManager(
            $this->dropOffPointCollectionFactory,
            $this->deliveryServiceCollectionFactory,
            $this->dropOffPointRepository,
            $this->deliveryServiceRepository,
            $this->countrySource,
            $this->serviceCodeSource,
            $this->labelTypeSource,
            $this->packageTypeSource,
            $this->addressTypeSource,
            $this->shipmentAddressTypeSource,
            $this->returnQrCodeTypeSource,
            $this->returnBookCourierSource,
            $this->orderRepository,
            $this->variableResolver,
            $this->serializer,
            $this->timezone,
            $this->methodCatalog,
            $this->customsTemplateCollectionFactory,
            $this->customsTypeSource,
            $this->categoryOfItemSource,
            $this->customsCurrencySource,
            $this->internationalMapPointTypeSource
        );
    }

    #[DataProvider('matchingMethodsDataProvider')]
    public function testIsMethodMatching(string $templateMethods, string $orderMethod, bool $expected): void
    {
        $result = $this->templateManager->isMethodMatching($templateMethods, $orderMethod);
        $this->assertSame($expected, $result);
    }

    public static function matchingMethodsDataProvider(): array
    {
        return [
            'exact match without prefix' => ['parcel_polska', 'parcel_polska', true],
            'exact match with order dhlpoland prefix' => ['parcel_polska', 'dhlpoland_parcel_polska', true],
            'template has dhlpoland prefix' => ['dhlpoland_parcel_polska', 'dhlpoland_parcel_polska', true],
            'template list first matches' => ['parcel_polska,premium', 'dhlpoland_parcel_polska', true],
            'template list second matches' => ['parcel_polska,premium', 'dhlpoland_premium', true],
            'case insensitive matching' => ['Parcel_Polska', 'DHLPOLAND_PARCEL_POLSKA', true],
            'reject partial match connect vs connect_plus' => ['connect', 'dhlpoland_connect_plus', false],
            'reject partial match connect_plus vs connect' => ['connect_plus', 'dhlpoland_connect', false],
            'reject partial match parcel_polska vs cod' => ['parcel_polska', 'dhlpoland_parcel_polska_cod', false],
            'match cod method when template has cod' => ['parcel_polska_cod', 'dhlpoland_parcel_polska_cod', true],
            'reject non dhl method' => ['parcel_polska', 'flatrate_flatrate', false],
            'reject empty order shipping method' => ['parcel_polska', '', false],
            'reject empty template method' => ['', 'dhlpoland_parcel_polska', false],
        ];
    }

    public function testGetShipmentTemplatesOptionsReturnsAllWhenNoMethodProvided(): void
    {
        $service1 = $this->createMock(DeliveryService::class);
        $service1->method('getId')->willReturn(1);
        $service1->method('getEntityId')->willReturn(1);
        $service1->method('getTemplateName')->willReturn('Standard POLSKA');
        $service1->method('getMethodCode')->willReturn('parcel_polska');

        $service2 = $this->createMock(DeliveryService::class);
        $service2->method('getId')->willReturn(2);
        $service2->method('getEntityId')->willReturn(2);
        $service2->method('getTemplateName')->willReturn('COD POLSKA');
        $service2->method('getMethodCode')->willReturn('parcel_polska_cod');

        $collection = $this->createMock(DeliveryServiceCollection::class);
        $collection->method('getItems')->willReturn([$service1, $service2]);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$service1, $service2]));

        $this->deliveryServiceCollectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $options = $this->templateManager->getShipmentTemplatesOptions();

        $expected = [
            ['value' => 1, 'label' => 'Standard POLSKA'],
            ['value' => 2, 'label' => 'COD POLSKA'],
        ];
        $this->assertSame($expected, $options);
    }

    public function testGetShipmentTemplatesOptionsFiltersByShippingMethod(): void
    {
        $service1 = $this->createMock(DeliveryService::class);
        $service1->method('getId')->willReturn(1);
        $service1->method('getEntityId')->willReturn(1);
        $service1->method('getTemplateName')->willReturn('Standard POLSKA');
        $service1->method('getMethodCode')->willReturn('parcel_polska');

        $service2 = $this->createMock(DeliveryService::class);
        $service2->method('getId')->willReturn(2);
        $service2->method('getEntityId')->willReturn(2);
        $service2->method('getTemplateName')->willReturn('COD POLSKA');
        $service2->method('getMethodCode')->willReturn('parcel_polska_cod');

        $collection = $this->createMock(DeliveryServiceCollection::class);
        $collection->method('getItems')->willReturn([$service1, $service2]);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$service1, $service2]));

        $this->deliveryServiceCollectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $options = $this->templateManager->getShipmentTemplatesOptions('dhlpoland_parcel_polska');

        $expected = [
            ['value' => 1, 'label' => 'Standard POLSKA'],
        ];
        $this->assertSame($expected, $options);
    }

    public function testGetMatchingDeliveryServiceIdForOrder(): void
    {
        $service1 = $this->createMock(DeliveryService::class);
        $service1->method('getId')->willReturn(1);
        $service1->method('getEntityId')->willReturn(1);
        $service1->method('getTemplateName')->willReturn('Standard POLSKA');
        $service1->method('getMethodCode')->willReturn('parcel_polska');

        $collection = $this->createMock(DeliveryServiceCollection::class);
        $collection->method('getItems')->willReturn([$service1]);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$service1]));

        $this->deliveryServiceCollectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $order = $this->createConfiguredMock(Order::class, [
            'getShippingMethod' => 'dhlpoland_parcel_polska',
        ]);

        $matchedId = $this->templateManager->getMatchingDeliveryServiceIdForOrder($order);
        $this->assertSame(1, $matchedId);
    }

    public function testGetCustomsTemplatesOptions(): void
    {
        $template = $this->createStub(\Ideo\Dhl\Model\CustomsTemplate::class);
        $template->method('getId')->willReturn(7);
        $template->method('getEntityId')->willReturn(7);
        $template->method('getTemplateName')->willReturn('EU Standard Export');

        $collection = $this->createMock(\Ideo\Dhl\Model\ResourceModel\CustomsTemplate\Collection::class);
        $collection->method('getItems')->willReturn([$template]);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$template]));

        $factory = $this->createMock(\Ideo\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($collection);

        $typeSource = $this->createMock(\Ideo\Dhl\Model\Source\CustomsType::class);
        $typeSource->method('toOptionArray')->willReturn([['value' => 'U', 'label' => 'Simplified']]);

        $catSource = $this->createMock(\Ideo\Dhl\Model\Source\CategoryOfItem::class);
        $catSource->method('toOptionArray')->willReturn([['value' => '11', 'label' => 'Sales']]);

        $currSource = $this->createMock(\Ideo\Dhl\Model\Source\CustomsCurrency::class);
        $currSource->method('toOptionArray')->willReturn([['value' => 'EUR', 'label' => 'EUR']]);

        $manager = new TemplateManager(
            $this->dropOffPointCollectionFactory,
            $this->deliveryServiceCollectionFactory,
            $this->dropOffPointRepository,
            $this->deliveryServiceRepository,
            $this->countrySource,
            $this->serviceCodeSource,
            $this->labelTypeSource,
            $this->packageTypeSource,
            $this->addressTypeSource,
            $this->shipmentAddressTypeSource,
            $this->returnQrCodeTypeSource,
            $this->returnBookCourierSource,
            $this->orderRepository,
            $this->variableResolver,
            $this->serializer,
            $this->timezone,
            $this->methodCatalog,
            $factory,
            $typeSource,
            $catSource,
            $currSource,
            $this->internationalMapPointTypeSource
        );

        $options = $manager->getCustomsTemplatesOptions();
        $this->assertSame([['value' => 7, 'label' => 'EU Standard Export']], $options);
        $this->assertSame([['value' => 'U', 'label' => 'Simplified']], $manager->getCustomsTypeOptions());
        $this->assertSame([['value' => '11', 'label' => 'Sales']], $manager->getCategoryOfItemOptions());
        $this->assertSame([['value' => 'EUR', 'label' => 'EUR']], $manager->getCustomsCurrencyOptions());
    }

    public function testGetMatchingCustomsTemplateIdForCountry(): void
    {
        $template1 = $this->createStub(\Ideo\Dhl\Model\CustomsTemplate::class);
        $template1->method('getEntityId')->willReturn(10);
        $template1->method('getCountryId')->willReturn('GB, US');

        $template2 = $this->createStub(\Ideo\Dhl\Model\CustomsTemplate::class);
        $template2->method('getEntityId')->willReturn(20);
        $template2->method('getCountryId')->willReturn('GB, DE');

        $collection = $this->createMock(\Ideo\Dhl\Model\ResourceModel\CustomsTemplate\Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$template1, $template2]));

        $factory = $this->createMock(\Ideo\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $manager = new TemplateManager(
            $this->dropOffPointCollectionFactory,
            $this->deliveryServiceCollectionFactory,
            $this->dropOffPointRepository,
            $this->deliveryServiceRepository,
            $this->countrySource,
            $this->serviceCodeSource,
            $this->labelTypeSource,
            $this->packageTypeSource,
            $this->addressTypeSource,
            $this->shipmentAddressTypeSource,
            $this->returnQrCodeTypeSource,
            $this->returnBookCourierSource,
            $this->orderRepository,
            $this->variableResolver,
            $this->serializer,
            $this->timezone,
            $this->methodCatalog,
            $factory,
            $this->createStub(\Ideo\Dhl\Model\Source\CustomsType::class),
            $this->createStub(\Ideo\Dhl\Model\Source\CategoryOfItem::class),
            $this->createStub(\Ideo\Dhl\Model\Source\CustomsCurrency::class),
            $this->internationalMapPointTypeSource
        );

        // GB matches template1 first -> returns 10
        $this->assertSame(10, $manager->getMatchingCustomsTemplateIdForCountry('gb'));

        // US matches template1 -> returns 10
        $this->assertSame(10, $manager->getMatchingCustomsTemplateIdForCountry('US'));

        // DE matches template2 -> returns 20
        $this->assertSame(20, $manager->getMatchingCustomsTemplateIdForCountry('DE'));

        // FR does not match any template -> returns null
        $this->assertNull($manager->getMatchingCustomsTemplateIdForCountry('FR'));

        // Empty country -> returns null
        $this->assertNull($manager->getMatchingCustomsTemplateIdForCountry(''));
    }

    public function testGetMatchingCustomsTemplateIdForOrder(): void
    {
        $template = $this->createStub(\Ideo\Dhl\Model\CustomsTemplate::class);
        $template->method('getEntityId')->willReturn(15);
        $template->method('getCountryId')->willReturn('CH, NO');

        $collection = $this->createMock(\Ideo\Dhl\Model\ResourceModel\CustomsTemplate\Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$template]));

        $factory = $this->createMock(\Ideo\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $manager = new TemplateManager(
            $this->dropOffPointCollectionFactory,
            $this->deliveryServiceCollectionFactory,
            $this->dropOffPointRepository,
            $this->deliveryServiceRepository,
            $this->countrySource,
            $this->serviceCodeSource,
            $this->labelTypeSource,
            $this->packageTypeSource,
            $this->addressTypeSource,
            $this->shipmentAddressTypeSource,
            $this->returnQrCodeTypeSource,
            $this->returnBookCourierSource,
            $this->orderRepository,
            $this->variableResolver,
            $this->serializer,
            $this->timezone,
            $this->methodCatalog,
            $factory,
            $this->createStub(\Ideo\Dhl\Model\Source\CustomsType::class),
            $this->createStub(\Ideo\Dhl\Model\Source\CategoryOfItem::class),
            $this->createStub(\Ideo\Dhl\Model\Source\CustomsCurrency::class),
            $this->internationalMapPointTypeSource
        );

        $address = $this->createStub(\Magento\Sales\Model\Order\Address::class);
        $address->method('getCountryId')->willReturn('CH');

        $order = $this->createStub(\Magento\Sales\Model\Order::class);
        $order->method('getShippingAddress')->willReturn($address);

        $this->assertSame(15, $manager->getMatchingCustomsTemplateIdForOrder($order));

        $orderNoAddress = $this->createStub(\Magento\Sales\Model\Order::class);
        $orderNoAddress->method('getShippingAddress')->willReturn(null);

        $this->assertNull($manager->getMatchingCustomsTemplateIdForOrder($orderNoAddress));
    }

    public function testGetSpecialServicesTemplateReturnsNullInsuranceValue(): void
    {
        $service = $this->createMock(DeliveryService::class);
        $service->method('getAdditionalServices')->willReturn([
            'insurance' => 1,
            'collectOnDelivery' => 1
        ]);

        $this->deliveryServiceRepository->method('getById')->willReturn($service);

        $result = $this->templateManager->getSpecialServicesTemplate(5);

        $this->assertIsArray($result);
        $this->assertTrue($result['insurance']);
        $this->assertNull($result['insuranceValue']);
        $this->assertTrue($result['collectOnDelivery']);
        $this->assertNull($result['collectOnDeliveryValue']);
    }
}
