<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\MapPointType;
use PHPUnit\Framework\TestCase;

class MapPointTypeTest extends TestCase
{
    /**
     * @var MapPointType
     */
    private MapPointType $sourceModel;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->sourceModel = new MapPointType();
    }

    /**
     * Verify toOptionArray returns expected options.
     *
     * @return void
     */
    public function testToOptionArrayReturnsExpectedOptions(): void
    {
        $options = $this->sourceModel->toOptionArray();

        $this->assertCount(3, $options);
        $this->assertEquals(MapPointType::TYPE_ALL, $options[0]['value']);
        $this->assertEquals(MapPointType::TYPE_BOX, $options[1]['value']);
        $this->assertEquals(MapPointType::TYPE_POP, $options[2]['value']);
    }
}
