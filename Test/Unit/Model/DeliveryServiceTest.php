<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\DeliveryService;
use Ideo\Dhl\Model\ResourceModel\DeliveryService as DeliveryServiceResource;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use PHPUnit\Framework\TestCase;

class DeliveryServiceTest extends TestCase
{
    /**
     * @var DeliveryService
     */
    private $model;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $eventManager = $this->createStub(ManagerInterface::class);
        $context->method('getEventDispatcher')->willReturn($eventManager);

        $registry = $this->createStub(\Magento\Framework\Registry::class);
        $resource = $this->createStub(DeliveryServiceResource::class);

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();

        $this->model = new DeliveryService(
            $context,
            $registry,
            $serializer,
            $resource
        );
    }

    public function testGettersAndSetters(): void
    {
        $this->model->setTemplateName('Standard Shipping');
        $this->assertSame('Standard Shipping', $this->model->getTemplateName());

        $this->model->setMethodCode('dhl_courier');
        $this->assertSame('dhl_courier', $this->model->getMethodCode());

        $this->model->setContent('Books');
        $this->assertSame('Books', $this->model->getContent());

        $this->model->setComment('Handle with care');
        $this->assertSame('Handle with care', $this->model->getComment());

        $this->model->setReference('REF123');
        $this->assertSame('REF123', $this->model->getReference());

        $this->model->setMpk('MPK001');
        $this->assertSame('MPK001', $this->model->getMpk());

        $this->model->setAdditionalServices(['insurance' => '1']);
        $this->assertSame(['insurance' => '1'], $this->model->getAdditionalServices());

        $this->model->setLabelType('BLP');
        $this->assertSame('BLP', $this->model->getLabelType());

        $packagesArr = [
            ['package_type' => 'PACKAGE', 'weight' => 2.5, 'width' => 20, 'length' => 30, 'height' => 15]
        ];
        $this->model->setPackages($packagesArr);
        $this->assertSame($packagesArr, $this->model->getPackages());

        $this->model->setAddressType('B');
        $this->assertSame('B', $this->model->getAddressType());

        $this->model->setAutoCreateReturnShipment(true);
        $this->assertTrue($this->model->getAutoCreateReturnShipment());

        $this->model->setReturnLabelType('BLP');
        $this->assertSame('BLP', $this->model->getReturnLabelType());

        $this->model->setReturnQrCodeType('QR_PDF');
        $this->assertSame('QR_PDF', $this->model->getReturnQrCodeType());

        $this->model->setReturnLabelExpDays(14);
        $this->assertSame(14, $this->model->getReturnLabelExpDays());

        $this->model->setReturnBookCourier(0);
        $this->assertSame(0, $this->model->getReturnBookCourier());
    }

    public function testSetMethodCodeAcceptsArrayAndImplodesToString(): void
    {
        $methods = [
            'parcel_polska',
            'parcel_polska_cod',
            'premium',
            'premium_cod',
            'parcel_9',
            'parcel_9_cod',
            'parcel_12',
            'parcel_12_cod',
            'economy_box',
            'economy_box_cod',
        ];
        $this->model->setMethodCode($methods);
        $expected = implode(',', $methods);
        $this->assertSame($expected, $this->model->getMethodCode());
    }

    public function testBeforeSaveConvertsArrayMethodCodeToString(): void
    {
        $methods = [
            'parcel_polska',
            'parcel_polska_cod',
            'premium',
            'premium_cod',
            'parcel_9',
            'parcel_9_cod',
            'parcel_12',
            'parcel_12_cod',
            'economy_box',
            'economy_box_cod',
        ];
        $this->model->setData('method_code', $methods);
        $this->model->beforeSave();

        $expected = implode(',', $methods);
        $this->assertSame($expected, $this->model->getData('method_code'));
    }

    public function testBeforeSaveSerializesPackagesArray(): void
    {
        $this->model->setData('packages', [
            ['package_type' => 'PACKAGE', 'quantity' => 1, 'weight' => 5]
        ]);
        $this->model->beforeSave();

        $this->assertSame(
            '[{"package_type":"PACKAGE","quantity":1,"weight":5}]',
            $this->model->getData('packages')
        );
    }

    public function testBeforeSaveSetsPackagesToNullWhenEmpty(): void
    {
        $this->model->setData('packages', []);
        $this->model->beforeSave();

        $this->assertNull($this->model->getData('packages'));
    }
}
