<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\System\Config;

use Ideo\Dhl\Controller\Adminhtml\System\Config\TestConnection;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\TestConnectionResult;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class TestConnectionTest extends TestCase
{
    /**
     * @var Context|MockObject
     */
    private $contextStub;

    /**
     * @var HttpRequest|MockObject
     */
    private $requestStub;

    /**
     * @var JsonFactory|MockObject
     */
    private $resultJsonFactoryMock;

    /**
     * @var CredentialsProvider|MockObject
     */
    private $credentialsProviderMock;

    /**
     * @var ClientInterface|MockObject
     */
    private $clientMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var TestConnection
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(HttpRequest::class);

        $this->contextStub
            ->method('getRequest')
            ->willReturn($this->requestStub);

        $this->resultJsonFactoryMock = $this->createMock(JsonFactory::class);
        $this->credentialsProviderMock = $this->createMock(CredentialsProvider::class);
        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->controller = new TestConnection(
            $this->contextStub,
            $this->resultJsonFactoryMock,
            $this->credentialsProviderMock,
            $this->clientMock,
            $this->loggerMock
        );
    }

    /**
     * Test successful execution of connection test.
     *
     * @return void
     */
    public function testExecuteSuccess(): void
    {
        $this->configureRequest([
            'environment' => 'sandbox',
            'api_login_sandbox' => 'user_sb',
            'api_password_sandbox' => 'pass_sb',
        ]);

        $credentials = new Credentials('user_sb', 'pass_sb', true);
        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->with('sandbox', 'user_sb', 'pass_sb')
            ->willReturn($credentials);

        $testResult = new TestConnectionResult(true, '<img src=x onerror=alert(1)>');
        $this->clientMock->expects($this->once())
            ->method('testConnection')
            ->with($credentials)
            ->willReturn($testResult);

        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => true,
                'message' => 'Connection to DHL WebAPI2 succeeded. Authentication valid.',
            ])
            ->willReturnSelf();

        $this->resultJsonFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($jsonMock);
        $this->loggerMock->expects($this->never())->method('error');

        $result = $this->controller->execute();
        $this->assertSame($jsonMock, $result);
    }

    public function testExecuteFailureReturnsGenericMessageInsteadOfClientMessage(): void
    {
        $this->configureRequest([
            'environment' => 'sandbox',
            'api_login_sandbox' => 'user_sb',
            'api_password_sandbox' => 'pass_sb',
        ]);

        $credentials = new Credentials('user_sb', 'pass_sb', true);
        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->with('sandbox', 'user_sb', 'pass_sb')
            ->willReturn($credentials);
        $this->clientMock->expects($this->once())
            ->method('testConnection')
            ->with($credentials)
            ->willReturn(new TestConnectionResult(false, '<script>alert("secret")</script>'));
        $this->loggerMock->expects($this->never())->method('error');

        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'DHL connection failed. Verify the API credentials and try again.',
            ])
            ->willReturnSelf();
        $this->resultJsonFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($jsonMock);

        $this->assertSame($jsonMock, $this->controller->execute());
    }

    public function testExecuteLogsUnexpectedErrorsAndReturnsGenericMessage(): void
    {
        $this->configureRequest([]);

        $exception = new RuntimeException('Sensitive internal detail password123.');
        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->with('production', null, null)
            ->willThrowException($exception);
        $this->clientMock->expects($this->never())->method('testConnection');
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('DHL connection test failed unexpectedly.', [
                'exception_type' => RuntimeException::class,
                'exception_code' => 0,
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
            ]);

        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'DHL connection failed. Verify the API credentials and try again.',
            ])
            ->willReturnSelf();
        $this->resultJsonFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($jsonMock);

        $this->assertSame($jsonMock, $this->controller->execute());
    }

    /**
     * Configure request parameter values while retaining Magento's default parameter behavior.
     *
     * @param array $parameters
     *
     * @return void
     */
    private function configureRequest(array $parameters): void
    {
        $this->requestStub->method('getParam')->willReturnCallback(
            static function (string $key, mixed $default = null) use ($parameters) {
                return array_key_exists($key, $parameters) ? $parameters[$key] : $default;
            }
        );
    }
}
