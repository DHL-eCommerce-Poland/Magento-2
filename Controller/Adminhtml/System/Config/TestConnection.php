<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\System\Config;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Throwable;

class TestConnection extends Action implements HttpPostActionInterface
{
    /**
     * Authorization resource ID.
     */
    public const ADMIN_RESOURCE = 'Ideo_Dhl::config';

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CredentialsProvider $credentialsProvider
     * @param ClientInterface $client
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ClientInterface $client
    ) {
        parent::__construct($context);
    }

    /**
     * Process AJAX request to test DHL API connection.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $environment = (string) $this->getRequest()->getParam('environment', 'production');
            $isSandbox = ($environment === 'sandbox' || $environment === '1');

            $loginKey = $isSandbox ? 'api_login_sandbox' : 'api_login';
            $passwordKey = $isSandbox ? 'api_password_sandbox' : 'api_password';

            $typedLogin = $this->getRequest()->getParam($loginKey);
            $typedPassword = $this->getRequest()->getParam($passwordKey);

            $loginStr = (string) $typedLogin;
            $passStr = (string) $typedPassword;

            $overrideLogin = ($typedLogin !== null && trim($loginStr) !== '') ? $loginStr : null;
            $overridePassword = ($typedPassword !== null && trim($passStr) !== '') ? $passStr : null;

            $credentials = $this->credentialsProvider->getCredentials(
                $environment,
                $overrideLogin,
                $overridePassword
            );

            $testResult = $this->client->testConnection($credentials);

            return $resultJson->setData([
                'success' => $testResult->isSuccess(),
                'message' => $testResult->getMessage(),
            ]);
        } catch (Throwable $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Execution error: %1', $e->getMessage()),
            ]);
        }
    }
}
