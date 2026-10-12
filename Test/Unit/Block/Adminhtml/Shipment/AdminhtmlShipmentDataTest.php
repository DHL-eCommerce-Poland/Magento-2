<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Block\Adminhtml\Shipment;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class AdminhtmlShipmentDataTest extends TestCase
{
    public function testLoadsCurrentShipmentOnlyOnce(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn('42');

        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getById')
            ->with(42)
            ->willReturn($shipment);

        $reader = new AdminhtmlShipmentData($request, $repository, $this->createStub(LoggerInterface::class));

        $this->assertSame(42, $reader->getEntityId());
        $this->assertSame($shipment, $reader->getShipment());
        $this->assertSame($shipment, $reader->getShipment());
    }

    public function testDoesNotLoadWhenEntityIdIsInvalid(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn(['42']);

        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->expects($this->never())->method('getById');

        $reader = new AdminhtmlShipmentData($request, $repository, $this->createStub(LoggerInterface::class));

        $this->assertNull($reader->getEntityId());
        $this->assertNull($reader->getShipment());
    }

    public function testReturnsNullWithoutLoggingWhenEntityDoesNotExist(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn(42);

        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getById')
            ->with(42)
            ->willThrowException(new NoSuchEntityException(__('Missing shipment.')));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $reader = new AdminhtmlShipmentData($request, $repository, $logger);

        $this->assertNull($reader->getShipment());
    }

    public function testLogsUnexpectedRepositoryErrorsAndReturnsNull(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn(42);

        $exception = new RuntimeException('Database unavailable.');
        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getById')
            ->with(42)
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to load DHL shipment data for the admin shipment form.',
                ['entity_id' => 42, 'exception' => $exception]
            );

        $reader = new AdminhtmlShipmentData($request, $repository, $logger);

        $this->assertNull($reader->getShipment());
    }
}
