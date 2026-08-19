<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Api\Data\DropOffPointInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Model\DropOffPointResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DropOffPointResolverTest extends TestCase
{
    /**
     * @var DropOffPointRepositoryInterface|MockObject
     */
    private DropOffPointRepositoryInterface|MockObject $repositoryMock;

    /**
     * @var DropOffPointResolver
     */
    private DropOffPointResolver $resolver;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(DropOffPointRepositoryInterface::class);
        $this->resolver = new DropOffPointResolver(
            $this->repositoryMock
        );
    }

    public function testResolveForWebsiteReturnsResolvedDropOffPoint(): void
    {
        $websiteId = 1;
        $dropOffPointMock = $this->createStub(DropOffPointInterface::class);

        $this->repositoryMock->expects($this->once())
            ->method('getDefaultByWebsiteId')
            ->with($websiteId)
            ->willReturn($dropOffPointMock);

        $result = $this->resolver->resolveForWebsite($websiteId);

        $this->assertSame($dropOffPointMock, $result);
    }

    public function testResolveForWebsiteCachesResultInMemory(): void
    {
        $websiteId = 1;
        $dropOffPointMock = $this->createStub(DropOffPointInterface::class);

        $this->repositoryMock->expects($this->once())
            ->method('getDefaultByWebsiteId')
            ->with($websiteId)
            ->willReturn($dropOffPointMock);

        $firstResult = $this->resolver->resolveForWebsite($websiteId);
        $secondResult = $this->resolver->resolveForWebsite($websiteId);

        $this->assertSame($dropOffPointMock, $firstResult);
        $this->assertSame($dropOffPointMock, $secondResult);
    }

    public function testResolveForWebsiteReturnsNullWhenNoDefaultFound(): void
    {
        $websiteId = 2;

        $this->repositoryMock->expects($this->once())
            ->method('getDefaultByWebsiteId')
            ->with($websiteId)
            ->willReturn(null);

        $result = $this->resolver->resolveForWebsite($websiteId);

        $this->assertNull($result);
    }
}
