<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\CustomsTemplate;

use Exception;
use Ideo\Dhl\Model\CustomsTemplateFactory;
use Ideo\Dhl\Model\CustomsTemplateRepository;
use Ideo\Dhl\Model\Source\CustomsCurrency;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::customs_template';

    /**
     * @param Context $context
     * @param CustomsTemplateRepository $repository
     * @param CustomsTemplateFactory $templateFactory
     * @param SerializerInterface $serializer
     * @param CustomsCurrency $customsCurrency
     */
    public function __construct(
        Context $context,
        private readonly CustomsTemplateRepository $repository,
        private readonly CustomsTemplateFactory $templateFactory,
        private readonly SerializerInterface $serializer,
        private readonly CustomsCurrency $customsCurrency
    ) {
        parent::__construct($context);
    }

    /**
     * Save action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/index');
        }

        $id = (int)$this->getRequest()->getParam('entity_id');

        try {
            if ($id) {
                $template = $this->repository->getById($id);
            } else {
                $template = $this->templateFactory->create();
            }

            if (!empty($data['customs_type']) && !empty($data['currency'])) {
                $customsType = (string)$data['customs_type'];
                $currency = (string)$data['currency'];
                if (!$this->customsCurrency->isCurrencyAllowed($customsType, $currency)) {
                    throw new LocalizedException(
                        __(
                            'Currency "%1" is not allowed for customs clearance type "%2". Allowed currencies: %3.',
                            $data['currency'],
                            $data['customs_type'],
                            implode(', ', $this->customsCurrency->getCurrenciesForType((string)$data['customs_type']))
                        )
                    );
                }
            }

            if (isset($data['customs_items']) && is_array($data['customs_items'])) {
                $data['customs_items'] = $this->serializer->serialize(array_values($data['customs_items']));
            }

            if (isset($data['invoice_file']) && is_array($data['invoice_file'])) {
                if (!empty($data['invoice_file'][0]['name'])) {
                    $data['invoice_file'] = $data['invoice_file'][0]['name'];
                } elseif (!empty($data['invoice_file'][0]['file'])) {
                    $data['invoice_file'] = $data['invoice_file'][0]['file'];
                } else {
                    $data['invoice_file'] = null;
                }
            }

            $template->addData($data);
            $this->repository->save($template);

            $this->messageManager->addSuccessMessage(__('You saved the Customs Clearance.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $template->getId()]);
            }
            return $resultRedirect->setPath('*/*/index');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while saving the Customs Clearance.')
            );
        }

        if ($id) {
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
        }
        return $resultRedirect->setPath('*/*/new');
    }
}
