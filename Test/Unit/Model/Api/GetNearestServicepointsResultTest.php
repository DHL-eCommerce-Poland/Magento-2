<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\GetNearestServicepointsResult;
use PHPUnit\Framework\TestCase;

class GetNearestServicepointsResultTest extends TestCase
{
    public function testSuccessResultReturnsPointsAndClosestPoint(): void
    {
        $points = [
            [
                'sap' => 'POP-WAW-100',
                'name' => 'Żabka 100',
                'type' => 'POP',
                'description' => 'Test description',
                'longitude' => '21.01',
                'latitude' => '52.23',
            ],
            [
                'sap' => 'POP-WAW-101',
                'name' => 'Żabka 101',
                'type' => 'POP',
                'description' => 'Test description 2',
                'longitude' => '21.02',
                'latitude' => '52.24',
            ],
        ];

        $result = new GetNearestServicepointsResult(true, $points);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(2, $result->getPoints());
        $this->assertEquals($points, $result->getPoints());
        $this->assertNotNull($result->getClosestPoint());
        $this->assertEquals('POP-WAW-100', $result->getClosestPoint()['sap']);
        $this->assertNull($result->getErrorCode());
        $this->assertNull($result->getErrorMessage());
    }

    public function testFailedResultReturnsErrorDetails(): void
    {
        $result = new GetNearestServicepointsResult(
            false,
            [],
            'NO_POINTS_FOUND',
            'No points found within search radius.'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertEmpty($result->getPoints());
        $this->assertNull($result->getClosestPoint());
        $this->assertEquals('NO_POINTS_FOUND', $result->getErrorCode());
        $this->assertEquals('No points found within search radius.', $result->getErrorMessage());
    }
}
