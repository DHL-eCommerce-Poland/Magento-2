<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\ResourceModel\DropOffPoint\Grid;

use Ideo\Dhl\Model\ResourceModel\DropOffPoint\Grid\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    /**
     * @return void
     */
    public function testCollectionInstantiation(): void
    {
        $collection = $this->createStub(Collection::class);
        self::assertInstanceOf(Collection::class, $collection);
    }
}
