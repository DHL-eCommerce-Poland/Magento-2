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
    private DeliveryService $model;

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

        $this->model->setServiceCode('AH');
        $this->assertSame('AH', $this->model->getServiceCode());

        $this->model->setMethodCode('dhl_courier');
        $this->assertSame('dhl_courier', $this->model->getMethodCode());

        $this->model->setPackageType('PACKAGE');
        $this->assertSame('PACKAGE', $this->model->getPackageType());

        $this->model->setWeight(2.5);
        $this->assertSame(2.5, $this->model->getWeight());

        $this->model->setLength(30.0);
        $this->assertSame(30.0, $this->model->getLength());

        $this->model->setWidth(20.0);
        $this->assertSame(20.0, $this->model->getWidth());

        $this->model->setHeight(15.0);
        $this->assertSame(15.0, $this->model->getHeight());

        $this->model->setIsNonStandard(true);
        $this->assertTrue($this->model->getIsNonStandard());

        $this->model->setHasEuroReturn(false);
        $this->assertFalse($this->model->getHasEuroReturn());

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

        $this->model->setDropOffPointId(10);
        $this->assertSame(10, $this->model->getDropOffPointId());

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
        $this->model->setMethodCode(['dhl_courier', 'dhl_express']);
        $this->assertSame('dhl_courier,dhl_express', $this->model->getMethodCode());
    }

    public function testBeforeSaveConvertsArrayMethodCodeToString(): void
    {
        $this->model->setData('method_code', ['dhl_courier', 'dhl_express']);
        $this->model->beforeSave();

        $this->assertSame('dhl_courier,dhl_express', $this->model->getData('method_code'));
    }
}
