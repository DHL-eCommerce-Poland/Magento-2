<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Api\Data\DropOffPointInterface;
use Ideo\Dhl\Model\Config\Source\DropOffPoint;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint\Collection;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint\CollectionFactory;
use PHPUnit\Framework\TestCase;

class DropOffPointTest extends TestCase
{
    public function testToOptionArrayReturnsDropOffPoints(): void
    {
        $factoryMock = $this->createStub(CollectionFactory::class);
        $collectionMock = $this->createStub(Collection::class);

        $itemMock = $this->createStub(DropOffPointInterface::class);
        $itemMock->method('getEntityId')->willReturn(5);
        $itemMock->method('getTemplateName')->willReturn('Warehouse Drop-Off');

        $collectionMock->method('getIterator')->willReturn(new \ArrayIterator([$itemMock]));
        $factoryMock->method('create')->willReturn($collectionMock);

        $source = new DropOffPoint($factoryMock);
        $options = $source->toOptionArray();

        $this->assertCount(1, $options);
        $this->assertEquals(['value' => '5', 'label' => 'Warehouse Drop-Off (ID: 5)'], $options[0]);
    }
}
