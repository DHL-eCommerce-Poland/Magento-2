<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Ui\Component\Listing\Column;

use Ideo\Dhl\Ui\Component\Listing\Column\DeliveryServiceActions;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

class DeliveryServiceActionsTest extends TestCase
{
    public function testPrepareDataSourceAppendsActions(): void
    {
        $context = $this->createStub(ContextInterface::class);
        $uiComponentFactory = $this->createStub(UiComponentFactory::class);
        $urlBuilder = $this->createMock(UrlInterface::class);

        $urlBuilder->expects($this->exactly(2))
            ->method('getUrl')
            ->willReturnMap([
                ['ideo_dhl/deliveryservice/edit', ['entity_id' => 1], 'http://example.com/edit/1'],
                ['ideo_dhl/deliveryservice/delete', ['entity_id' => 1], 'http://example.com/delete/1'],
            ]);

        $actionColumn = new DeliveryServiceActions(
            $context,
            $uiComponentFactory,
            $urlBuilder,
            [],
            ['name' => 'actions']
        );

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 1,
                        'template_name' => 'Standard Package',
                    ],
                ],
            ],
        ];

        $result = $actionColumn->prepareDataSource($dataSource);

        $this->assertArrayHasKey('actions', $result['data']['items'][0]);
        $actions = $result['data']['items'][0]['actions'];
        $this->assertEquals('http://example.com/edit/1', $actions['edit']['href']);
        $this->assertEquals('http://example.com/delete/1', $actions['delete']['href']);
    }
}
