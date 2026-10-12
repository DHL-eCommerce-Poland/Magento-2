<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\ResourceModel\DeliveryService\Grid;

use DhlPoland\Dhl\Model\ResourceModel\DeliveryService\Grid\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testCollectionInstantiation(): void
    {
        $collection = $this->createStub(Collection::class);
        self::assertInstanceOf(Collection::class, $collection);
    }
}
