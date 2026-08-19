<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Api\Data\DeliveryServiceInterface;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Model\DeliveryServiceResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeliveryServiceResolverTest extends TestCase
{
    /**
     * @var DeliveryServiceRepositoryInterface|MockObject
     */
    private DeliveryServiceRepositoryInterface|MockObject $repositoryMock;

    /**
     * @var DeliveryServiceResolver
     */
    private DeliveryServiceResolver $resolver;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(DeliveryServiceRepositoryInterface::class);
        $this->resolver = new DeliveryServiceResolver($this->repositoryMock);
    }

    public function testResolveForMethodReturnsResolvedService(): void
    {
        $websiteId = 1;
        $methodCode = 'courier';
        $serviceMock = $this->createStub(DeliveryServiceInterface::class);

        $this->repositoryMock->expects($this->once())
            ->method('getByWebsiteAndMethod')
            ->with($websiteId, $methodCode)
            ->willReturn($serviceMock);

        $result = $this->resolver->resolveForMethod($websiteId, $methodCode);

        $this->assertSame($serviceMock, $result);
    }

    public function testResolveForMethodCachesResultInMemory(): void
    {
        $websiteId = 1;
        $methodCode = 'courier';
        $serviceMock = $this->createStub(DeliveryServiceInterface::class);

        $this->repositoryMock->expects($this->once())
            ->method('getByWebsiteAndMethod')
            ->with($websiteId, $methodCode)
            ->willReturn($serviceMock);

        $firstResult = $this->resolver->resolveForMethod($websiteId, $methodCode);
        $secondResult = $this->resolver->resolveForMethod($websiteId, $methodCode);

        $this->assertSame($serviceMock, $firstResult);
        $this->assertSame($serviceMock, $secondResult);
    }

    public function testResolveForMethodReturnsNullWhenNotFound(): void
    {
        $websiteId = 1;
        $methodCode = 'unknown';

        $this->repositoryMock->expects($this->once())
            ->method('getByWebsiteAndMethod')
            ->with($websiteId, $methodCode)
            ->willReturn(null);

        $result = $this->resolver->resolveForMethod($websiteId, $methodCode);

        $this->assertNull($result);
    }
}
