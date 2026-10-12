<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Throwable;

class CredentialsProvider
{
    private const XML_PATH_SANDBOX = 'carriers/dhlpoland/sandbox';
    private const XML_PATH_API_LOGIN = 'carriers/dhlpoland/api_login';
    private const XML_PATH_API_PASSWORD = 'carriers/dhlpoland/api_password';
    private const XML_PATH_API_LOGIN_SANDBOX = 'carriers/dhlpoland/api_login_sandbox';
    private const XML_PATH_API_PASSWORD_SANDBOX = 'carriers/dhlpoland/api_password_sandbox';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Get API credentials for environment with optional form overrides.
     *
     * @param string|null $environment ('sandbox', 'production', '1', '0', or null to read from config)
     * @param string|null $overrideUsername
     * @param string|null $overridePassword
     *
     * @return Credentials
     */
    public function getCredentials(
        ?string $environment = null,
        ?string $overrideUsername = null,
        ?string $overridePassword = null
    ): Credentials {
        if ($environment === null) {
            $isSandbox = (bool) $this->scopeConfig->getValue(self::XML_PATH_SANDBOX);
        } else {
            $isSandbox = ($environment === 'sandbox' || $environment === '1');
        }

        $loginPath = $isSandbox ? self::XML_PATH_API_LOGIN_SANDBOX : self::XML_PATH_API_LOGIN;
        $passwordPath = $isSandbox ? self::XML_PATH_API_PASSWORD_SANDBOX : self::XML_PATH_API_PASSWORD;

        $username = $this->filterValue($overrideUsername);
        if ($username === null) {
            $username = (string) $this->scopeConfig->getValue($loginPath);
        }

        $password = $this->filterValue($overridePassword);
        if ($password === null) {
            $rawPassword = (string) $this->scopeConfig->getValue($passwordPath);
            $password = $this->decryptValue($rawPassword);
        }

        return new Credentials($username, $password, $isSandbox);
    }

    /**
     * Decrypt value if encrypted by Magento Encryptor, otherwise return as plain text.
     *
     * @param string $value
     * @return string
     */
    public function decryptValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+:(?:\d+:)?[a-zA-Z0-9_\+\/=\-\.]+$/', $value)) {
            try {
                $decrypted = $this->encryptor->decrypt($value);
                if ($decrypted !== '' && mb_check_encoding($decrypted, 'UTF-8')) {
                    return $decrypted;
                }
            } catch (Throwable $e) {
                $decrypted = null;
            }
        }

        return $value;
    }

    /**
     * Filter out empty strings and Magento obscure placeholder values (e.g. '******').
     *
     * @param string|null $value
     * @return string|null
     */
    private function filterValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || preg_match('/^\*+$/', $trimmed)) {
            return null;
        }

        return $trimmed;
    }
}
