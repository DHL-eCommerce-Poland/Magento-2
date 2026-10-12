<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CredentialsProviderTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var EncryptorInterface|MockObject
     */
    private $encryptorMock;

    /**
     * @var CredentialsProvider
     */
    private $credentialsProvider;

    /**
     * Set up test dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->encryptorMock = $this->createMock(EncryptorInterface::class);

        $this->credentialsProvider = new CredentialsProvider(
            $this->scopeConfigMock,
            $this->encryptorMock
        );
    }

    /**
     * Test getting credentials from config for production.
     *
     * @return void
     */
    public function testGetCredentialsFromConfigProduction(): void
    {
        $this->scopeConfigMock->expects(self::exactly(2))
            ->method('getValue')
            ->willReturnCallback(fn(string $path) => match ($path) {
                'carriers/dhlpoland/api_login' => '0:3:encrypted_user',
                'carriers/dhlpoland/api_password' => '0:3:encrypted_pass',
                default => null,
            });

        $this->encryptorMock->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnCallback(fn(string $val) => match ($val) {
                '0:3:encrypted_user' => 'decrypted_user',
                '0:3:encrypted_pass' => 'decrypted_pass',
                default => '',
            });

        $credentials = $this->credentialsProvider->getCredentials('production');

        $this->assertEquals('decrypted_user', $credentials->getUsername());
        $this->assertEquals('decrypted_pass', $credentials->getPassword());
        $this->assertFalse($credentials->isSandbox());
    }

    /**
     * Test getting credentials from config for sandbox.
     *
     * @return void
     */
    public function testGetCredentialsFromConfigSandbox(): void
    {
        $this->scopeConfigMock->expects(self::exactly(2))
            ->method('getValue')
            ->willReturnCallback(fn(string $path) => match ($path) {
                'carriers/dhlpoland/api_login_sandbox' => '0:3:enc_sb_user',
                'carriers/dhlpoland/api_password_sandbox' => '0:3:enc_sb_pass',
                default => null,
            });

        $this->encryptorMock->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnCallback(fn(string $val) => match ($val) {
                '0:3:enc_sb_user' => 'sb_user',
                '0:3:enc_sb_pass' => 'sb_pass',
                default => '',
            });

        $credentials = $this->credentialsProvider->getCredentials('sandbox');

        $this->assertEquals('sb_user', $credentials->getUsername());
        $this->assertEquals('sb_pass', $credentials->getPassword());
        $this->assertTrue($credentials->isSandbox());
    }

    /**
     * Test override credentials provided from form (Test before save).
     *
     * @return void
     */
    public function testGetCredentialsWithOverrides(): void
    {
        $this->scopeConfigMock->expects(self::never())->method('getValue');
        $this->encryptorMock->expects(self::never())->method('decrypt');

        $credentials = $this->credentialsProvider->getCredentials('sandbox', 'override_user', 'override_pass');

        $this->assertEquals('override_user', $credentials->getUsername());
        $this->assertEquals('override_pass', $credentials->getPassword());
        $this->assertTrue($credentials->isSandbox());
    }

    /**
     * Test filtering out Magento obscure dummy asterisks values (e.g. '******').
     *
     * @return void
     */
    public function testGetCredentialsFiltersObscurePlaceholder(): void
    {
        $this->scopeConfigMock->expects(self::exactly(2))
            ->method('getValue')
            ->willReturnCallback(fn(string $path) => match ($path) {
                'carriers/dhlpoland/api_login' => '0:3:encrypted_user',
                'carriers/dhlpoland/api_password' => '0:3:encrypted_pass',
                default => null,
            });

        $this->encryptorMock->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnCallback(fn(string $val) => match ($val) {
                '0:3:encrypted_user' => 'decrypted_user',
                '0:3:encrypted_pass' => 'decrypted_pass',
                default => '',
            });

        $credentials = $this->credentialsProvider->getCredentials('production', '******', '******');

        $this->assertEquals('decrypted_user', $credentials->getUsername());
        $this->assertEquals('decrypted_pass', $credentials->getPassword());
        $this->assertFalse($credentials->isSandbox());
    }

    /**
     * Test that getCredentials resolves configured sandbox when environment is null.
     *
     * @return void
     */
    public function testGetCredentialsResolvesConfiguredSandboxWhenEnvironmentIsNull(): void
    {
        $this->scopeConfigMock->expects(self::exactly(3))
            ->method('getValue')
            ->willReturnCallback(fn(string $path) => match ($path) {
                'carriers/dhlpoland/sandbox' => '1',
                'carriers/dhlpoland/api_login_sandbox' => 'sb_user',
                'carriers/dhlpoland/api_password_sandbox' => 'sb_pass',
                default => null,
            });
        $this->encryptorMock->expects(self::never())->method('decrypt');

        $credentials = $this->credentialsProvider->getCredentials();

        $this->assertEquals('sb_user', $credentials->getUsername());
        $this->assertEquals('sb_pass', $credentials->getPassword());
        $this->assertTrue($credentials->isSandbox());
    }

    /**
     * Test that getCredentials resolves configured production when environment is null.
     *
     * @return void
     */
    public function testGetCredentialsResolvesConfiguredProductionWhenEnvironmentIsNull(): void
    {
        $this->scopeConfigMock->expects(self::exactly(3))
            ->method('getValue')
            ->willReturnCallback(fn(string $path) => match ($path) {
                'carriers/dhlpoland/sandbox' => '0',
                'carriers/dhlpoland/api_login' => 'prod_user',
                'carriers/dhlpoland/api_password' => 'prod_pass',
                default => null,
            });
        $this->encryptorMock->expects(self::never())->method('decrypt');

        $credentials = $this->credentialsProvider->getCredentials();

        $this->assertEquals('prod_user', $credentials->getUsername());
        $this->assertEquals('prod_pass', $credentials->getPassword());
        $this->assertFalse($credentials->isSandbox());
    }
}
