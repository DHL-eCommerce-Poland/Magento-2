<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\DeliveryMode;
use PHPUnit\Framework\TestCase;

class DeliveryModeTest extends TestCase
{
    /**
     * @var DeliveryMode
     */
    private DeliveryMode $sourceModel;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->sourceModel = new DeliveryMode();
    }

    /**
     * Verify toOptionArray returns expected options.
     *
     * @return void
     */
    public function testToOptionArrayReturnsExpectedOptions(): void
    {
        $options = $this->sourceModel->toOptionArray();

        $this->assertCount(2, $options);
        $this->assertEquals(DeliveryMode::MODE_MAP, $options[0]['value']);
        $this->assertEquals(DeliveryMode::MODE_NEAREST, $options[1]['value']);
    }
}
