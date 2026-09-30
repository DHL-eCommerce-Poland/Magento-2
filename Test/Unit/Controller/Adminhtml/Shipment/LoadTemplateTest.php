<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Api\Data\DeliveryServiceInterface;
use Ideo\Dhl\Api\Data\DropOffPointInterface;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Controller\Adminhtml\Shipment\LoadTemplate;
use Ideo\Dhl\Model\CustomsTemplate;
use Ideo\Dhl\Model\CustomsTemplateRepository;
use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\Template\TemplateManager;
use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LoadTemplateTest extends TestCase
{
    private $context;
    private $request;
    private $dropOffPointRepository;
    private $deliveryServiceRepository;
    private $orderRepository;
    private $templateManager;
    private $jsonFactory;
    private $jsonResult;
    private $variableResolver;
    private $methodCatalog;
    private $customsTemplateRepository;
    private $serializer;
    private LoadTemplate $controller;

    protected function setUp(): void
    {
        $this->context = $this->createStub(Context::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->context->method('getRequest')->willReturn($this->request);

        $this->dropOffPointRepository = $this->createMock(DropOffPointRepositoryInterface::class);
        $this->deliveryServiceRepository = $this->createMock(DeliveryServiceRepositoryInterface::class);
        $this->orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $this->templateManager = $this->createStub(TemplateManager::class);
        $this->jsonFactory = $this->createStub(JsonFactory::class);
        $this->jsonResult = $this->createMock(Json::class);
        $this->jsonFactory->method('create')->willReturn($this->jsonResult);
        $this->variableResolver = $this->createStub(TemplateVariableResolver::class);
        $this->methodCatalog = $this->createStub(MethodCatalog::class);
        $this->customsTemplateRepository = $this->createMock(CustomsTemplateRepository::class);
        $this->serializer = $this->createMock(SerializerInterface::class);

        $this->controller = new LoadTemplate(
            $this->context,
            $this->dropOffPointRepository,
            $this->deliveryServiceRepository,
            $this->orderRepository,
            $this->templateManager,
            $this->jsonFactory,
            $this->variableResolver,
            $this->methodCatalog,
            $this->customsTemplateRepository,
            $this->serializer,
            null
        );
    }

    /**
     * Test execute ignores preset parameter aliases.
     *
     * @return void
     */
    public function testExecuteDoesNotAcceptPresetParameterAliases(): void
    {
        $this->request->method('getParam')->willReturnCallback(fn ($key) => match ($key) {
            'preset_id' => '3',
            'preset_type' => 'sender',
            default => null,
        });

        $this->dropOffPointRepository->expects($this->never())->method('getById');
        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with(self::callback(fn (array $data) => ($data['success'] ?? true) === false))
            ->willReturnSelf();

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    public function testExecuteLoadsSenderTemplate(): void
    {
        $this->request->method('getParam')->willReturnCallback(fn ($key) => match ($key) {
            'template_id' => '3',
            'template_type' => 'sender',
            default => null,
        });

        $point = $this->createMock(DropOffPointInterface::class);
        $point->method('getEntityId')->willReturn(3);
        $point->method('getSapNumber')->willReturn('SAP001');
        $point->method('getSenderName')->willReturn('Warehouse 1');
        $this->dropOffPointRepository->method('getById')->with(3)->willReturn($point);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with(self::callback(function ($data) {
                return ($data['success'] ?? false) === true
                    && ($data['data']['sapNumber'] ?? '') === 'SAP001'
                    && ($data['data']['name'] ?? '') === 'Warehouse 1';
            }))
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }

    public function testExecuteLoadsCustomsTemplate(): void
    {
        $this->request->method('getParam')->willReturnCallback(fn ($key) => match ($key) {
            'template_id' => '12',
            'template_type' => 'customs',
            default => null,
        });

        $customsTemplate = $this->createMock(CustomsTemplate::class);
        $customsTemplate->method('getId')->willReturn(12);
        $customsTemplate->method('getCustomsType')->willReturn('U');
        $customsTemplate->method('getCurrency')->willReturn('EUR');
        $customsTemplate->method('getCategoryOfItem')->willReturn('11');
        $customsTemplate->method('getCountryOfOrigin')->willReturn('PL');
        $customsTemplate->method('getCostsOfShipment')->willReturn(15.0);
        $customsTemplate->method('getGrossWeight')->willReturn(2.5);
        $customsTemplate->method('getContactFirstName')->willReturn('Adam');
        $customsTemplate->method('getContactLastName')->willReturn('Nowak');
        $customsTemplate->method('getShipperNip')->willReturn('5218487281');
        $customsTemplate->method('getShipperEori')->willReturn('PL52184872810000');
        $customsTemplate->method('getShipperVatNo')->willReturn('PL5218487281');
        $customsTemplate->method('getCustomsItems')->willReturn(json_encode([
            [
                'namePl' => 'Produkt A',
                'nameEn' => 'Product A',
                'quantity' => 1,
                'weight' => 0.5,
                'value' => 20.0,
                'tariffCode' => '1234567890',
            ]
        ]));

        $this->serializer->method('unserialize')->willReturn([
            [
                'namePl' => 'Produkt A',
                'nameEn' => 'Product A',
                'quantity' => 1,
                'weight' => 0.5,
                'value' => 20.0,
                'tariffCode' => '1234567890',
            ]
        ]);

        $this->customsTemplateRepository->expects($this->once())
            ->method('getById')
            ->with(12)
            ->willReturn($customsTemplate);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with(self::callback(function ($data) {
                return ($data['success'] ?? false) === true
                    && ($data['data']['selectedCustomsTemplateId'] ?? null) === 12
                    && ($data['data']['customsType'] ?? '') === 'U'
                    && ($data['data']['currency'] ?? '') === 'EUR'
                    && ($data['data']['nipNr'] ?? '') === '5218487281'
                    && ($data['data']['eoriNr'] ?? '') === 'PL52184872810000'
                    && count($data['data']['customsItems'] ?? []) === 1
                    && ($data['data']['customsItems'][0]['namePl'] ?? '') === 'Produkt A';
            }))
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }
}
