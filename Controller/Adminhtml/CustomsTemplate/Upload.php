<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\CustomsTemplate;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;

class Upload extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::customs_template';

    /**
     * @param Context $context
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Upload action.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'invoice_file']);
            $uploader->setAllowedExtensions(['pdf']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);

            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $targetPath = $mediaDirectory->getAbsolutePath('dhl/customs_templates');

            $uploadResult = $uploader->save($targetPath);
            if (!$uploadResult) {
                throw new \Magento\Framework\Exception\LocalizedException(__('File cannot be saved.'));
            }

            $uploadResult['cookie'] = [
                'name' => $this->_session->getName(),
                'value' => $this->_session->getSessionId(),
                'lifetime' => $this->_session->getCookieLifetime(),
                'path' => $this->_session->getCookiePath(),
                'domain' => $this->_session->getCookieDomain(),
            ];

            return $result->setData($uploadResult);
        } catch (Exception $e) {
            return $result->setData([
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode(),
            ]);
        }
    }
}
