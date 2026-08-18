<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\DeliveryService;
use Ideo\Dhl\Model\ResourceModel\DeliveryService as DeliveryServiceResource;
use Magento\Framework\Model\Context;
use PHPUnit\Framework\TestCase;

class DeliveryServiceTest extends TestCase
{
    private DeliveryService $model;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $registry = $this->createStub(\Magento\Framework\Registry::class);
        $resource = $this->createStub(DeliveryServiceResource::class);

        $this->model = new DeliveryService(
            $context,
            $registry,
            $resource
        );
    }

    public function testGettersAndSetters(): void
    {
        $this->model->setWebsiteId(1);
        $this->assertEquals(1, $this->model->getWebsiteId());

        $this->model->setTemplateName('Standard Shipping');
        $this->assertEquals('Standard Shipping', $this->model->getTemplateName());

        $this->model->setServiceCode('AH');
        $this->assertEquals('AH', $this->model->getServiceCode());

        $this->model->setMethodCode('dhl_courier');
        $this->assertEquals('dhl_courier', $this->model->getMethodCode());

        $this->model->setPackageType('PACKAGE');
        $this->assertEquals('PACKAGE', $this->model->getPackageType());

        $this->model->setWeight(2.5);
        $this->assertEquals(2.5, $this->model->getWeight());

        $this->model->setLength(30.0);
        $this->assertEquals(30.0, $this->model->getLength());

        $this->model->setWidth(20.0);
        $this->assertEquals(20.0, $this->model->getWidth());

        $this->model->setHeight(15.0);
        $this->assertEquals(15.0, $this->model->getHeight());

        $this->model->setIsNonStandard(true);
        $this->assertTrue($this->model->getIsNonStandard());

        $this->model->setHasEuroReturn(false);
        $this->assertFalse($this->model->getHasEuroReturn());

        $this->model->setContent('Books');
        $this->assertEquals('Books', $this->model->getContent());

        $this->model->setComment('Handle with care');
        $this->assertEquals('Handle with care', $this->model->getComment());

        $this->model->setReference('REF123');
        $this->assertEquals('REF123', $this->model->getReference());

        $this->model->setMpk('MPK001');
        $this->assertEquals('MPK001', $this->model->getMpk());

        $this->model->setAdditionalServices(['insurance' => '1']);
        $this->assertEquals(['insurance' => '1'], $this->model->getAdditionalServices());

        $this->model->setLabelType('BLP');
        $this->assertEquals('BLP', $this->model->getLabelType());

        $this->model->setDropOffPointId(10);
        $this->assertEquals(10, $this->model->getDropOffPointId());

        $this->model->setAddressType('B');
        $this->assertEquals('B', $this->model->getAddressType());

        $this->model->setAutoCreateReturnShipment(true);
        $this->assertTrue($this->model->getAutoCreateReturnShipment());
    }
}
