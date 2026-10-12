<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model;

use Exception;
use DhlPoland\Dhl\Model\ResourceModel\CustomsTemplate as CustomsTemplateResource;
use DhlPoland\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class CustomsTemplateRepository
{
    /**
     * @param CustomsTemplateFactory $templateFactory
     * @param CustomsTemplateResource $templateResource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CustomsTemplateFactory $templateFactory,
        private readonly CustomsTemplateResource $templateResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Save Customs Template.
     *
     * @param CustomsTemplate $template
     *
     * @return CustomsTemplate
     * @throws CouldNotSaveException
     */
    public function save(CustomsTemplate $template): CustomsTemplate
    {
        try {
            $this->templateResource->save($template);
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not save Customs Clearance: %1', $e->getMessage()), $e);
        }
        return $template;
    }

    /**
     * Get Customs Template by ID.
     *
     * @param int $id
     *
     * @return CustomsTemplate
     * @throws NoSuchEntityException
     */
    public function getById(int $id): CustomsTemplate
    {
        $template = $this->templateFactory->create();
        $this->templateResource->load($template, $id);
        if (!$template->getId()) {
            throw new NoSuchEntityException(__('Customs Clearance with ID "%1" does not exist.', $id));
        }
        return $template;
    }

    /**
     * Delete Customs Template.
     *
     * @param CustomsTemplate $template
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(CustomsTemplate $template): bool
    {
        try {
            $this->templateResource->delete($template);
        } catch (Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete Customs Clearance: %1', $e->getMessage()), $e);
        }
        return true;
    }

    /**
     * Delete Customs Template by ID.
     *
     * @param int $id
     *
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }
}
