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

        $this->controller = new TestConnection(
            $this->contextStub,
            $this->resultJsonFactoryMock,
            $this->credentialsProviderMock,
            $this->clientMock
        );
    }

    /**
     * Test successful execution of connection test.
     *
     * @return void
     */
    public function testExecuteSuccess(): void
    {
        $this->requestStub
            ->method('getParam')
            ->willReturnMap([
                ['environment', 'production', 'sandbox'],
                ['api_login_sandbox', null, 'user_sb'],
                ['api_password_sandbox', null, 'pass_sb'],
            ]);

        $credentials = new Credentials('user_sb', 'pass_sb', true);
        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->with('sandbox', 'user_sb', 'pass_sb')
            ->willReturn($credentials);

        $testResult = new TestConnectionResult(true, 'Connection successful.');
        $this->clientMock->expects($this->once())
            ->method('testConnection')
            ->with($credentials)
            ->willReturn($testResult);

        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => true,
                'message' => 'Connection successful.',
            ])
            ->willReturnSelf();

        $this->resultJsonFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($jsonMock);

        $result = $this->controller->execute();
        $this->assertSame($jsonMock, $result);
    }
}
