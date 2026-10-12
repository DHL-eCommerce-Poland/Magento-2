<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Plugin\Adminhtml\Order\Create;

use DhlPoland\Dhl\ViewModel\Adminhtml\Order\Create\ShippingMethodFormViewModel;
use Magento\Sales\Block\Adminhtml\Order\Create\Shipping\Method\Form as ShippingMethodForm;

/**
 * Plugin to enforce DhlPoland_Dhl shipping method form template and inject ViewModel on admin order creation.
 */
class AddDhlServicePointToShippingMethodFormPlugin
{
    /**
     * @param ShippingMethodFormViewModel $viewModel
     */
    public function __construct(
        private readonly ShippingMethodFormViewModel $viewModel
    ) {
    }

    /**
     * Set DhlPoland_Dhl template and ViewModel right before block HTML is rendered.
     *
     * @param ShippingMethodForm $subject
     *
     * @return void
     */
    public function beforeToHtml(ShippingMethodForm $subject): void
    {
        $subject->setData('viewModel', $this->viewModel);
        $subject->setTemplate('DhlPoland_Dhl::order/create/shipping/method/form.phtml');
    }
}
