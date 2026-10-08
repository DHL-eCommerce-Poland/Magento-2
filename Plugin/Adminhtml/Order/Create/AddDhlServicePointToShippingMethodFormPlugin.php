<?php

declare(strict_types=1);

namespace Ideo\Dhl\Plugin\Adminhtml\Order\Create;

use Ideo\Dhl\ViewModel\Adminhtml\Order\Create\ShippingMethodFormViewModel;
use Magento\Sales\Block\Adminhtml\Order\Create\Shipping\Method\Form as ShippingMethodForm;

/**
 * Plugin to enforce Ideo_Dhl shipping method form template and inject ViewModel on admin order creation.
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
     * Set Ideo_Dhl template and ViewModel right before block HTML is rendered.
     *
     * @param ShippingMethodForm $subject
     *
     * @return void
     */
    public function beforeToHtml(ShippingMethodForm $subject): void
    {
        $subject->setData('viewModel', $this->viewModel);
        $subject->setTemplate('Ideo_Dhl::order/create/shipping/method/form.phtml');
    }
}
