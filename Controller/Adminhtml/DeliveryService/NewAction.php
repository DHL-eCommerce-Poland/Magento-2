<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\DeliveryService;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;

class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::delivery_service';

    /**
     * @param Action\Context $context
     * @param ForwardFactory $resultForwardFactory
     */
    public function __construct(
        Action\Context $context,
        private readonly ForwardFactory $resultForwardFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Forward to edit action.
     *
     * @return Forward
     */
    public function execute(): Forward
    {
        $resultForward = $this->resultForwardFactory->create();
        return $resultForward->forward('edit');
    }
}
