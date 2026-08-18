<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\DropOffPoint;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint as DropOffPointResource;
use Magento\Framework\Model\Context;
use PHPUnit\Framework\TestCase;

class DropOffPointTest extends TestCase
{
    private DropOffPoint $model;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $registry = $this->createStub(\Magento\Framework\Registry::class);
        $resource = $this->createStub(DropOffPointResource::class);

        $this->model = new DropOffPoint(
            $context,
            $registry,
            $resource
        );
    }

    public function testGettersAndSetters(): void
    {
        $this->model->setWebsiteId(2);
        $this->assertEquals(2, $this->model->getWebsiteId());

        $this->model->setTemplateName('Main Hub');
        $this->assertEquals('Main Hub', $this->model->getTemplateName());

        $this->model->setSenderName('Jan Kowalski');
        $this->assertEquals('Jan Kowalski', $this->model->getSenderName());

        $this->model->setFirstName('Jan');
        $this->assertEquals('Jan', $this->model->getFirstName());

        $this->model->setLastName('Kowalski');
        $this->assertEquals('Kowalski', $this->model->getLastName());

        $this->model->setStreet('Marszalkowska');
        $this->assertEquals('Marszalkowska', $this->model->getStreet());

        $this->model->setHouseNumber('10/12');
        $this->assertEquals('10/12', $this->model->getHouseNumber());

        $this->model->setPostalCode('00-001');
        $this->assertEquals('00-001', $this->model->getPostalCode());

        $this->model->setCity('Warszawa');
        $this->assertEquals('Warszawa', $this->model->getCity());

        $this->model->setCountryId('PL');
        $this->assertEquals('PL', $this->model->getCountryId());

        $this->model->setEmail('test@example.com');
        $this->assertEquals('test@example.com', $this->model->getEmail());

        $this->model->setPhone('123456789');
        $this->assertEquals('123456789', $this->model->getPhone());

        $this->model->setSapNumber('SAP12345');
        $this->assertEquals('SAP12345', $this->model->getSapNumber());

        $this->model->setPickupTimeFrom('09:00');
        $this->assertEquals('09:00', $this->model->getPickupTimeFrom());

        $this->model->setPickupTimeTo('17:00');
        $this->assertEquals('17:00', $this->model->getPickupTimeTo());

        $this->model->setIsDefault(true);
        $this->assertTrue($this->model->getIsDefault());
    }
}
