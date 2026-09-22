<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Block\Adminhtml\Button\Delete;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeleteTest extends TestCase
{
    /**
     * @var Context
     */
    private Context $contextMock;

    /**
     * @var Escaper
     */
    private Escaper $escaperMock;

    /**
     * @var RequestInterface&MockObject
     */
    private $requestMock;

    /**
     * @var UrlInterface&MockObject
     */
    private $urlBuilderMock;

    protected function setUp(): void
    {
        $this->contextMock = $this->createStub(Context::class);
        $this->escaperMock = $this->createStub(Escaper::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);

        $this->contextMock->method('getRequest')->willReturn($this->requestMock);
        $this->contextMock->method('getUrlBuilder')->willReturn($this->urlBuilderMock);
    }

    public function testGetButtonDataReturnsEmptyWhenNoEntityId(): void
    {
        $this->requestMock->method('getParam')->with('entity_id')->willReturn(null);
        $this->urlBuilderMock->expects($this->never())->method('getUrl');

        $button = new Delete($this->contextMock, $this->escaperMock);
        $this->assertSame([], $button->getButtonData());
    }

    public function testGetButtonDataReturnsConfiguredButtonWhenEntityIdExists(): void
    {
        $this->requestMock->method('getParam')->with('entity_id')->willReturn('12');
        $this->escaperMock->method('escapeHtml')->willReturn('Are you sure you want to delete this record?');
        $this->escaperMock->method('escapeJs')->willReturn('Are you sure you want to delete this record?');
        $this->urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with('*/*/delete', ['entity_id' => 12])
            ->willReturn('http://localhost/admin/dhlparcel/customstemplate/delete/entity_id/12');

        $button = new Delete($this->contextMock, $this->escaperMock);
        $data = $button->getButtonData();

        $this->assertIsArray($data);
        $this->assertSame('Delete', (string)$data['label']);
        $this->assertSame('delete', $data['class']);
        $this->assertSame(20, $data['sort_order']);
        $expectedOnClick = "deleteConfirm('Are you sure you want to delete this record?', "
            . "'http://localhost/admin/dhlparcel/customstemplate/delete/entity_id/12', {data: {}})";
        $this->assertSame($expectedOnClick, $data['on_click']);
    }
}
