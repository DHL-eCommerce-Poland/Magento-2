<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\CustomsTemplate;

use Ideo\Dhl\Model\CustomsTemplate;
use Ideo\Dhl\Model\CustomsTemplate\DataProvider;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate\Collection;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactoryMock;

    /**
     * @var Collection
     */
    private $collectionMock;

    /**
     * @var SerializerInterface
     */
    private $serializerMock;

    /**
     * @var File
     */
    private $fileIoMock;

    /**
     * @var DataProvider
     */
    private DataProvider $dataProvider;

    protected function setUp(): void
    {
        $this->collectionFactoryMock = $this->createStub(CollectionFactory::class);
        $this->collectionMock = $this->createStub(Collection::class);
        $this->serializerMock = $this->createStub(SerializerInterface::class);
        $this->fileIoMock = $this->createStub(File::class);

        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);

        $this->dataProvider = new DataProvider(
            'dhlparcel_customs_template_form_data_source',
            'entity_id',
            'entity_id',
            $this->collectionFactoryMock,
            $this->serializerMock,
            $this->fileIoMock
        );
    }

    public function testGetDataUnwrapsNestedCustomsItems(): void
    {
        $templateMock = $this->createStub(CustomsTemplate::class);
        $templateMock->method('getId')->willReturn(5);
        $templateMock->method('getData')->willReturn([
            'entity_id' => 5,
            'template_name' => 'Template 1',
            'customs_items' => '[[{"nameEn":"Phone","namePl":"Telefon"}]]',
        ]);

        $this->collectionMock->method('getItems')->willReturn([$templateMock]);

        $this->serializerMock->method('unserialize')
            ->willReturn([[
                ['nameEn' => 'Phone', 'namePl' => 'Telefon'],
            ]]);

        $data = $this->dataProvider->getData();

        $this->assertArrayHasKey(5, $data);
        $this->assertSame(
            [['nameEn' => 'Phone', 'namePl' => 'Telefon']],
            $data[5]['customs_items']
        );
    }

    public function testGetDataHandlesKeyedCustomsItems(): void
    {
        $templateMock = $this->createStub(CustomsTemplate::class);
        $templateMock->method('getId')->willReturn(6);
        $templateMock->method('getData')->willReturn([
            'entity_id' => 6,
            'customs_items' => '{"customs_items":[{"nameEn":"TV"}]}',
        ]);

        $this->collectionMock->method('getItems')->willReturn([$templateMock]);

        $this->serializerMock->method('unserialize')
            ->willReturn([
                'customs_items' => [
                    ['nameEn' => 'TV'],
                ],
            ]);

        $data = $this->dataProvider->getData();

        $this->assertArrayHasKey(6, $data);
        $this->assertSame(
            [['nameEn' => 'TV']],
            $data[6]['customs_items']
        );
    }

    public function testGetDataFormatsInvoiceFile(): void
    {
        $templateMock = $this->createStub(CustomsTemplate::class);
        $templateMock->method('getId')->willReturn(7);
        $templateMock->method('getData')->willReturn([
            'entity_id' => 7,
            'invoice_file' => 'dhl/invoices/test.pdf',
        ]);

        $this->collectionMock->method('getItems')->willReturn([$templateMock]);
        $this->fileIoMock->method('getPathInfo')
            ->willReturn(['basename' => 'test.pdf']);

        $data = $this->dataProvider->getData();

        $this->assertArrayHasKey(7, $data);
        $this->assertSame(
            [
                [
                    'name' => 'test.pdf',
                    'file' => 'dhl/invoices/test.pdf',
                    'size' => 0,
                    'type' => 'application/pdf',
                ],
            ],
            $data[7]['invoice_file']
        );
    }
}
