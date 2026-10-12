<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\CustomsTemplate;

use Exception;
use DhlPoland\Dhl\Model\CustomsTemplateFactory;
use DhlPoland\Dhl\Model\CustomsTemplateRepository;
use DhlPoland\Dhl\Model\Source\CategoryOfItem;
use DhlPoland\Dhl\Model\Source\CustomsCurrency;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::customs_template';

    /**
     * @param Context $context
     * @param CustomsTemplateRepository $repository
     * @param CustomsTemplateFactory $templateFactory
     * @param SerializerInterface $serializer
     * @param CustomsCurrency $customsCurrency
     * @param CategoryOfItem $categoryOfItem
     */
    public function __construct(
        Context $context,
        private readonly CustomsTemplateRepository $repository,
        private readonly CustomsTemplateFactory $templateFactory,
        private readonly SerializerInterface $serializer,
        private readonly CustomsCurrency $customsCurrency,
        private readonly CategoryOfItem $categoryOfItem
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

        $id = (int)($this->getRequest()->getParam('entity_id') ?: ($data['entity_id'] ?? 0));
        if (empty($data['entity_id'])) {
            unset($data['entity_id']);
        }

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

            if (!empty($data['customs_type']) && !empty($data['category_of_item'])) {
                $customsType = (string)$data['customs_type'];
                $category = (string)$data['category_of_item'];
                if (!$this->categoryOfItem->isCategoryAllowed($customsType, $category)) {
                    throw new LocalizedException(
                        __(
                            'Category of item "%1" is not allowed for customs clearance type "%2". '
                            . 'Allowed categories: %3.',
                            $data['category_of_item'],
                            $data['customs_type'],
                            implode(', ', $this->categoryOfItem->getCategoriesForType((string)$data['customs_type']))
                        )
                    );
                }
            }

            if (isset($data['customs_items']) && is_array($data['customs_items'])) {
                $items = $data['customs_items'];
                if (isset($items['customs_items']) && is_array($items['customs_items'])) {
                    $items = $items['customs_items'];
                } elseif (count($items) === 1
                    && isset($items[0])
                    && is_array($items[0])
                    && !isset($items[0]['nameEn'])
                    && !isset($items[0]['namePl'])
                ) {
                    $items = $items[0];
                }
                $data['customs_items'] = $this->serializer->serialize(array_values($items));
            }

            if (isset($data['country_id']) && is_array($data['country_id'])) {
                $cleanCountries = array_filter(array_map('trim', $data['country_id']));
                $data['country_id'] = !empty($cleanCountries) ? implode(',', $cleanCountries) : null;
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
