<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use DateTime;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\PostalCodeServicesResult;
use Ideo\Dhl\Model\Shipment\CourierPickupTimeResolver;
use Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CourierPickupTimeResolverTest extends TestCase
{
    private ClientInterface|MockObject $clientMock;
    private CredentialsProvider|Stub $credentialsProviderStub;
    private TimezoneInterface|MockObject $timezoneMock;
    private CourierPickupTimeResolver $resolver;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->timezoneMock = $this->createMock(TimezoneInterface::class);

        $this->timezoneMock->method('scopeDate')
            ->willReturn(new DateTime('today'));

        $this->timezoneMock->method('date')
            ->willReturnCallback(fn ($d) => new DateTime((string)$d));

        $this->credentialsProviderStub->method('getCredentials')
            ->willReturn(new Credentials('user', 'pass', true));

        $dateSanitizer = new ShipmentDateSanitizer($this->timezoneMock);

        $this->resolver = new CourierPickupTimeResolver(
            $this->clientMock,
            $this->credentialsProviderStub,
            $this->timezoneMock,
            $dateSanitizer
        );
    }

    public function testResolveAvailableHoursSuccessForToday(): void
    {
        $today = date('Y-m-d');
        $apiResult = new PostalCodeServicesResult(
            true,
            '10:00',
            '16:00',
            '10:00',
            '15:00',
            false
        );

        $this->clientMock->expects($this->once())
            ->method('getPostalCodeServices')
            ->with($this->anything(), '00-001', $today)
            ->willReturn($apiResult);

        $result = $this->resolver->resolveAvailableHours('00-001', $today, 'sandbox', '09:00');

        $this->assertTrue($result['is_today_possible']);
        $this->assertTrue($result['is_date_available']);
        $this->assertNull($result['message']);
        $this->assertNotEmpty($result['available_hours']);
        $this->assertEquals('10:00', $result['dr_pickup_from']);
        $this->assertEquals('16:00', $result['dr_pickup_to']);
    }

    public function testResolveAvailableHoursReturnsWarningWhenPastCutoffForToday(): void
    {
        $today = date('Y-m-d');
        $apiResult = new PostalCodeServicesResult(
            true,
            '10:00',
            '14:00',
            '10:00',
            '14:00',
            false
        );

        $this->clientMock->expects($this->atLeastOnce())
            ->method('getPostalCodeServices')
            ->willReturn($apiResult);

        // Current time is 15:00, which is past drPickupTo 14:00
        $result = $this->resolver->resolveAvailableHours('00-001', $today, 'sandbox', '15:00');

        $this->assertFalse($result['is_today_possible']);
        $this->assertFalse($result['is_date_available']);
        $this->assertEquals(
            'Courier pickup is not available for the selected date. Please select another date in the calendar.',
            $result['message']
        );
        $this->assertEmpty($result['available_hours']);
    }

    public function testResolveAvailableHoursReturnsWarningWhenApiFails(): void
    {
        $today = date('Y-m-d');
        $apiResult = new PostalCodeServicesResult(
            false,
            null,
            null,
            null,
            null,
            false,
            'API_ERROR',
            'Service unavailable'
        );

        $this->clientMock->expects($this->once())
            ->method('getPostalCodeServices')
            ->willReturn($apiResult);

        $result = $this->resolver->resolveAvailableHours('00-001', $today, 'sandbox', '10:00');

        $this->assertFalse($result['is_today_possible']);
        $this->assertEquals(
            'Courier pickup is not available for the selected date. Please select another date in the calendar.',
            $result['message']
        );
    }

    public function testResolveAvailableHoursForMultiplePostalCodesIntersection(): void
    {
        $today = date('Y-m-d');
        $result1 = new PostalCodeServicesResult(true, '10:00', '16:00', '10:00', '16:00', false);
        $result2 = new PostalCodeServicesResult(true, '12:00', '17:00', '12:00', '17:00', false);

        $this->clientMock->expects($this->exactly(2))
            ->method('getPostalCodeServices')
            ->willReturnOnConsecutiveCalls($result1, $result2);

        $result = $this->resolver->resolveAvailableHoursForPostalCodes(['00-001', '35-234'], $today, 'sandbox', '09:00');

        $this->assertTrue($result['is_today_possible']);
        $this->assertEquals('12:00', $result['dr_pickup_from']);
        $this->assertEquals('16:00', $result['dr_pickup_to']);
        $this->assertEquals([['from' => '12:00', 'to' => '16:00', 'label' => '12:00 - 16:00']], $result['available_hours']);
    }

    public function testResolveAvailableHoursNormalizesLocaleDateStringToIsoFormat(): void
    {
        $apiResult = new PostalCodeServicesResult(true, '10:00', '16:00', '10:00', '16:00', false);

        $this->clientMock->expects($this->once())
            ->method('getPostalCodeServices')
            ->with($this->anything(), '00-001', '2026-09-02')
            ->willReturn($apiResult);

        // Pass locale date string "09/02/2026"
        $result = $this->resolver->resolveAvailableHours('00-001', '09/02/2026', 'sandbox', '08:00');

        $this->assertTrue($result['is_date_available']);
        $this->assertEquals('10:00', $result['dr_pickup_from']);
    }
}
