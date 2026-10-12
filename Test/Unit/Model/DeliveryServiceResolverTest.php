<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model;

use DhlPoland\Dhl\Api\Data\DeliveryServiceInterface;
use DhlPoland\Dhl\Api\DeliveryServiceRepositoryInterface;
use DhlPoland\Dhl\Model\DeliveryServiceResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeliveryServiceResolverTest extends TestCase
{
    /**
     * @var DeliveryServiceRepositoryInterface|MockObject
     */
    private $repositoryMock;

    /**
     * @var DeliveryServiceResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(DeliveryServiceRepositoryInterface::class);
        $this->resolver = new DeliveryServiceResolver($this->repositoryMock);
    }

    public function testResolveForMethodReturnsResolvedService(): void
    {
        $methodCode = 'courier';
        $serviceMock = $this->createStub(DeliveryServiceInterface::class);

        $this->repositoryMock->expects($this->once())
            ->method('getByMethod')
            ->with($methodCode)
            ->willReturn($serviceMock);

        $result = $this->resolver->resolveForMethod($methodCode);

        $this->assertSame($serviceMock, $result);
    }

    public function testResolveForMethodCachesResultInMemory(): void
    {
        $methodCode = 'courier';
        $serviceMock = $this->createStub(DeliveryServiceInterface::class);

        $this->repositoryMock->expects($this->once())
            ->method('getByMethod')
            ->with($methodCode)
            ->willReturn($serviceMock);

        $firstResult = $this->resolver->resolveForMethod($methodCode);
        $secondResult = $this->resolver->resolveForMethod($methodCode);

        $this->assertSame($serviceMock, $firstResult);
        $this->assertSame($serviceMock, $secondResult);
    }

    public function testResolveForMethodReturnsNullWhenNotFound(): void
    {
        $methodCode = 'unknown';

        $this->repositoryMock->expects($this->once())
            ->method('getByMethod')
            ->with($methodCode)
            ->willReturn(null);

        $result = $this->resolver->resolveForMethod($methodCode);

        $this->assertNull($result);
    }
}
