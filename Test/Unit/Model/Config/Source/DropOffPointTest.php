<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\DropOffPoint;
use Ideo\Dhl\Model\DropOffPoint as DropOffPointModel;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint\Collection;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint\CollectionFactory;
use PHPUnit\Framework\TestCase;

class DropOffPointTest extends TestCase
{
    public function testToOptionArrayReturnsFormattedDropOffPoints(): void
    {
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collection = $this->createStub(Collection::class);

        $item1 = $this->createStub(DropOffPointModel::class);
        $item1->method('getTemplateName')->willReturn('Main Warehouse');
        $item1->method('getCity')->willReturn('Warszawa');
        $item1->method('getStreet')->willReturn('Nad Przyrwą 13');
        $item1->method('getIsDefault')->willReturn(true);
        $item1->method('getEntityId')->willReturn(1);

        $item2 = $this->createStub(DropOffPointModel::class);
        $item2->method('getTemplateName')->willReturn('');
        $item2->method('getSenderName')->willReturn('Ideo Sp. z o.o.');
        $item2->method('getCity')->willReturn('Rzeszów');
        $item2->method('getStreet')->willReturn('Rejtana 1');
        $item2->method('getIsDefault')->willReturn(false);
        $item2->method('getEntityId')->willReturn(2);

        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$item1, $item2]));
        $collectionFactory->method('create')->willReturn($collection);

        $source = new DropOffPoint($collectionFactory);
        $options = $source->toOptionArray();

        $this->assertCount(2, $options);
        $this->assertEquals('1', $options[0]['value']);
        $this->assertEquals('Main Warehouse (Warszawa, Nad Przyrwą 13) *', $options[0]['label']);
        $this->assertEquals('2', $options[1]['value']);
        $this->assertEquals('Ideo Sp. z o.o. (Rzeszów, Rejtana 1)', $options[1]['label']);
    }
}
