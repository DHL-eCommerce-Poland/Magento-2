<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Plugin\Adminhtml\Order\Create;

use DhlPoland\Dhl\Plugin\Adminhtml\Order\Create\AddDhlServicePointToShippingMethodFormPlugin;
use DhlPoland\Dhl\ViewModel\Adminhtml\Order\Create\ShippingMethodFormViewModel;
use Magento\Sales\Block\Adminhtml\Order\Create\Shipping\Method\Form as ShippingMethodForm;
use PHPUnit\Framework\TestCase;

class AddDhlServicePointToShippingMethodFormPluginTest extends TestCase
{
    public function testBeforeToHtmlSetsDhlTemplateAndViewModelOnShippingMethodForm(): void
    {
        $viewModelStub = $this->createStub(ShippingMethodFormViewModel::class);
        $formMock = $this->createMock(ShippingMethodForm::class);

        $formMock->expects($this->once())
            ->method('setData')
            ->with('viewModel', $viewModelStub);

        $formMock->expects($this->once())
            ->method('setTemplate')
            ->with('DhlPoland_Dhl::order/create/shipping/method/form.phtml');

        $plugin = new AddDhlServicePointToShippingMethodFormPlugin($viewModelStub);
        $plugin->beforeToHtml($formMock);
    }
}
