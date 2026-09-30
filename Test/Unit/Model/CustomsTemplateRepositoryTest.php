<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Exception;
use Ideo\Dhl\Model\CustomsTemplate;
use Ideo\Dhl\Model\CustomsTemplateFactory;
use Ideo\Dhl\Model\CustomsTemplateRepository;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate as CustomsTemplateResource;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate\Collection;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;

class CustomsTemplateRepositoryTest extends TestCase
{
    public function testSaveSuccess(): void
    {
        $templateFactoryStub = $this->createStub(CustomsTemplateFactory::class);
        $templateResourceMock = $this->createMock(CustomsTemplateResource::class);
        $collectionFactoryStub = $this->createStub(CollectionFactory::class);

        $repository = new CustomsTemplateRepository(
            $templateFactoryStub,
            $templateResourceMock,
            $collectionFactoryStub
        );

        $templateStub = $this->createStub(CustomsTemplate::class);
        $templateResourceMock->expects($this->once())
            ->method('save')
            ->with($templateStub);

        $result = $repository->save($templateStub);
        $this->assertSame($templateStub, $result);
    }

    public function testSaveExceptionThrowsCouldNotSaveException(): void
    {
        $templateFactoryStub = $this->createStub(CustomsTemplateFactory::class);
        $templateResourceMock = $this->createMock(CustomsTemplateResource::class);
        $collectionFactoryStub = $this->createStub(CollectionFactory::class);

        $repository = new CustomsTemplateRepository(
            $templateFactoryStub,
            $templateResourceMock,
            $collectionFactoryStub
        );

        $templateStub = $this->createStub(CustomsTemplate::class);
        $templateResourceMock->expects($this->once())
            ->method('save')
            ->with($templateStub)
            ->willThrowException(new Exception('DB Error'));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not save Customs Clearance: DB Error');

        $repository->save($templateStub);
    }

    public function testGetByIdSuccess(): void
    {
        $templateFactoryMock = $this->createMock(CustomsTemplateFactory::class);
        $templateResourceMock = $this->createMock(CustomsTemplateResource::class);
        $collectionFactoryStub = $this->createStub(CollectionFactory::class);

        $repository = new CustomsTemplateRepository(
            $templateFactoryMock,
            $templateResourceMock,
            $collectionFactoryStub
        );

        $templateStub = $this->createStub(CustomsTemplate::class);
        $templateStub->method('getId')->willReturn(5);

        $templateFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($templateStub);

        $templateResourceMock->expects($this->once())
            ->method('load')
            ->with($templateStub, 5);

        $result = $repository->getById(5);
        $this->assertSame($templateStub, $result);
    }

    public function testGetByIdNotFoundThrowsNoSuchEntityException(): void
    {
        $templateFactoryMock = $this->createMock(CustomsTemplateFactory::class);
        $templateResourceMock = $this->createMock(CustomsTemplateResource::class);
        $collectionFactoryStub = $this->createStub(CollectionFactory::class);

        $repository = new CustomsTemplateRepository(
            $templateFactoryMock,
            $templateResourceMock,
            $collectionFactoryStub
        );

        $templateStub = $this->createStub(CustomsTemplate::class);
        $templateStub->method('getId')->willReturn(null);

        $templateFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($templateStub);

        $templateResourceMock->expects($this->once())
            ->method('load')
            ->with($templateStub, 999);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Customs Clearance with ID "999" does not exist.');

        $repository->getById(999);
    }

    public function testDeleteSuccess(): void
    {
        $templateFactoryStub = $this->createStub(CustomsTemplateFactory::class);
        $templateResourceMock = $this->createMock(CustomsTemplateResource::class);
        $collectionFactoryStub = $this->createStub(CollectionFactory::class);

        $repository = new CustomsTemplateRepository(
            $templateFactoryStub,
            $templateResourceMock,
            $collectionFactoryStub
        );

        $templateStub = $this->createStub(CustomsTemplate::class);
        $templateResourceMock->expects($this->once())
            ->method('delete')
            ->with($templateStub);

        $result = $repository->delete($templateStub);
        $this->assertTrue($result);
    }

    public function testDeleteExceptionThrowsCouldNotDeleteException(): void
    {
        $templateFactoryStub = $this->createStub(CustomsTemplateFactory::class);
        $templateResourceMock = $this->createMock(CustomsTemplateResource::class);
        $collectionFactoryStub = $this->createStub(CollectionFactory::class);

        $repository = new CustomsTemplateRepository(
            $templateFactoryStub,
            $templateResourceMock,
            $collectionFactoryStub
        );

        $templateStub = $this->createStub(CustomsTemplate::class);
        $templateResourceMock->expects($this->once())
            ->method('delete')
            ->with($templateStub)
            ->willThrowException(new Exception('Delete Error'));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Could not delete Customs Clearance: Delete Error');

        $repository->delete($templateStub);
    }

    public function testDeleteByIdSuccess(): void
    {
        $templateFactoryMock = $this->createMock(CustomsTemplateFactory::class);
        $templateResourceMock = $this->createMock(CustomsTemplateResource::class);
        $collectionFactoryStub = $this->createStub(CollectionFactory::class);

        $repository = new CustomsTemplateRepository(
            $templateFactoryMock,
            $templateResourceMock,
            $collectionFactoryStub
        );

        $templateStub = $this->createStub(CustomsTemplate::class);
        $templateStub->method('getId')->willReturn(10);

        $templateFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($templateStub);

        $templateResourceMock->expects($this->once())
            ->method('load')
            ->with($templateStub, 10);

        $templateResourceMock->expects($this->once())
            ->method('delete')
            ->with($templateStub);

        $result = $repository->deleteById(10);
        $this->assertTrue($result);
    }
}
