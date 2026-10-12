<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model;

use DhlPoland\Dhl\Api\Data\DropOffPointInterface;
use DhlPoland\Dhl\Api\DropOffPointRepositoryInterface;
use DhlPoland\Dhl\Model\DropOffPointResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DropOffPointResolverTest extends TestCase
{
    /**
     * @var DropOffPointRepositoryInterface|MockObject
     */
    private $repositoryMock;

    /**
     * @var DropOffPointResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(DropOffPointRepositoryInterface::class);
        $this->resolver = new DropOffPointResolver(
            $this->repositoryMock
        );
    }

    public function testResolveDefaultReturnsResolvedDropOffPoint(): void
    {
        $dropOffPointMock = $this->createStub(DropOffPointInterface::class);

        $this->repositoryMock->expects($this->once())
            ->method('getDefault')
            ->willReturn($dropOffPointMock);

        $result = $this->resolver->resolveDefault();

        $this->assertSame($dropOffPointMock, $result);
    }

    public function testResolveDefaultCachesResultInMemory(): void
    {
        $dropOffPointMock = $this->createStub(DropOffPointInterface::class);

        $this->repositoryMock->expects($this->once())
            ->method('getDefault')
            ->willReturn($dropOffPointMock);

        $firstResult = $this->resolver->resolveDefault();
        $secondResult = $this->resolver->resolveDefault();

        $this->assertSame($dropOffPointMock, $firstResult);
        $this->assertSame($dropOffPointMock, $secondResult);
    }

    public function testResolveDefaultReturnsNullWhenNoDefaultFound(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('getDefault')
            ->willReturn(null);

        $result = $this->resolver->resolveDefault();

        $this->assertNull($result);
    }
}
