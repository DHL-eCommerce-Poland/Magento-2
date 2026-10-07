<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use ArrayIterator;
use Ideo\Dhl\Model\Config\Source\CustomsTemplate;
use Ideo\Dhl\Model\CustomsTemplate as CustomsTemplateModel;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate\Collection;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory;
use PHPUnit\Framework\TestCase;

class CustomsTemplateTest extends TestCase
{
    public function testToOptionArrayReturnsFormattedCustomsTemplates(): void
    {
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collection = $this->createStub(Collection::class);

        $item1 = $this->createStub(CustomsTemplateModel::class);
        $item1->method('getTemplateName')->willReturn('EU Standard Template');
        $item1->method('getEntityId')->willReturn(10);

        $item2 = $this->createStub(CustomsTemplateModel::class);
        $item2->method('getTemplateName')->willReturn('Individual Export Template');
        $item2->method('getEntityId')->willReturn(20);

        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([$item1, $item2]));
        $collectionFactory->method('create')->willReturn($collection);

        $source = new CustomsTemplate($collectionFactory);
        $options = $source->toOptionArray();

        $this->assertCount(2, $options);
        $this->assertEquals('10', $options[0]['value']);
        $this->assertEquals('EU Standard Template', $options[0]['label']);
        $this->assertEquals('20', $options[1]['value']);
        $this->assertEquals('Individual Export Template', $options[1]['label']);
    }
}
