<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\CustomsTemplate;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate as CustomsTemplateResource;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CustomsTemplateTest extends TestCase
{
    /**
     * @var CustomsTemplate
     */
    private $model;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $eventManager = $this->createStub(ManagerInterface::class);
        $context->method('getEventDispatcher')->willReturn($eventManager);

        $registry = $this->createStub(Registry::class);
        $resource = $this->createStub(CustomsTemplateResource::class);
        $serializer = new Json();

        $this->model = new CustomsTemplate(
            $context,
            $registry,
            $serializer,
            $resource
        );
    }

    public function testGettersAndSetters(): void
    {
        $this->model->setTemplateName('EU Default Customs');
        $this->assertSame('EU Default Customs', $this->model->getTemplateName());

        $this->model->setCustomsType('U');
        $this->assertSame('U', $this->model->getCustomsType());

        $this->model->setCategoryOfItem('11');
        $this->assertSame('11', $this->model->getCategoryOfItem());

        $this->model->setCurrency('PLN');
        $this->assertSame('PLN', $this->model->getCurrency());

        $this->model->setDefaultTariffCode('62046200');
        $this->assertSame('62046200', $this->model->getDefaultTariffCode());

        $this->model->setShipperEori('PL12345678900000');
        $this->assertSame('PL12345678900000', $this->model->getShipperEori());

        $this->model->setShipperNip('1234567890');
        $this->assertSame('1234567890', $this->model->getShipperNip());

        $this->model->setShipperVatNo('PL1234567890');
        $this->assertSame('PL1234567890', $this->model->getShipperVatNo());

        $this->model->setCountryOfOrigin('PL');
        $this->assertSame('PL', $this->model->getCountryOfOrigin());

        $this->model->setCostsOfShipment(15.50);
        $this->assertSame(15.50, $this->model->getCostsOfShipment());

        $this->model->setGrossWeight(2.5);
        $this->assertSame(2.5, $this->model->getGrossWeight());

        $this->model->setReceiverEori('GB123456789');
        $this->assertSame('GB123456789', $this->model->getReceiverEori());

        $this->model->setReceiverVatNo('GB987654321');
        $this->assertSame('GB987654321', $this->model->getReceiverVatNo());

        $this->model->setContactFirstName('John');
        $this->assertSame('John', $this->model->getContactFirstName());

        $this->model->setContactLastName('Doe');
        $this->assertSame('Doe', $this->model->getContactLastName());

        $this->model->setAdditionalInfo('Sample customs note');
        $this->assertSame('Sample customs note', $this->model->getAdditionalInfo());

        $this->model->setNotExceedValue(true);
        $this->assertTrue($this->model->getNotExceedValue());

        $this->model->setNotProhibitedGoods(true);
        $this->assertTrue($this->model->getNotProhibitedGoods());

        $this->model->setNotRestrictedGoods(true);
        $this->assertTrue($this->model->getNotRestrictedGoods());

        $items = [['nameEn' => 'Phone', 'quantity' => 1]];
        $this->model->setCustomsItems($items);
        $this->assertSame(json_encode($items), $this->model->getCustomsItems());

        $this->model->setIsDefault(true);
        $this->assertTrue($this->model->isDefault());

        $this->model->setIsDefault(0);
        $this->assertFalse($this->model->isDefault());
    }
}
