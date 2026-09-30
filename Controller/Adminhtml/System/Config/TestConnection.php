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
use Psr\Log\LoggerInterface;
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
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ClientInterface $client,
        private readonly LoggerInterface $logger
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
            $success = $testResult->isSuccess();

            return $resultJson->setData([
                'success' => $success,
                'message' => $success
                    ? (string) __('Connection to DHL WebAPI2 succeeded. Authentication valid.')
                    : (string) __('DHL connection failed. Verify the API credentials and try again.'),
            ]);
        } catch (Throwable $e) {
            $this->logger->error('DHL connection test failed unexpectedly.', [
                'exception_type' => $e::class,
                'exception_code' => $e->getCode(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]);

            return $resultJson->setData([
                'success' => false,
                'message' => (string) __('DHL connection failed. Verify the API credentials and try again.'),
            ]);
        }
    }
}
