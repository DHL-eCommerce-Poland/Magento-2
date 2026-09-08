<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\ServicePoint;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\GetNearestServicepointsResult;
use Ideo\Dhl\Model\ServicePoint\NearestPointResolver;
use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class NearestPointResolverTest extends TestCase
{
    private CredentialsProvider $credentialsProviderMock;
    private ClientInterface $clientMock;
    private LoggerInterface $loggerMock;
    private Credentials $credentials;
    private NearestPointResolver $resolver;

    protected function setUp(): void
    {
        $this->credentialsProviderMock = $this->createStub(CredentialsProvider::class);
        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->loggerMock = $this->createStub(LoggerInterface::class);
        $this->credentials = new Credentials('user', 'pass', true);

        $this->credentialsProviderMock->method('getCredentials')
            ->willReturn($this->credentials);

        $this->resolver = new NearestPointResolver(
            $this->credentialsProviderMock,
            $this->clientMock,
            $this->loggerMock
        );
    }

    public function testReturnsRecipientUnchangedIfPopIdAlreadySet(): void
    {
        $recipient = new RecipientData(
            name: 'Jan Kowalski',
            type: 'C',
            firstName: 'Jan',
            lastName: 'Kowalski',
            contactPerson: 'Jan Kowalski',
            street: 'Prosta',
            houseNumber: '1',
            flatNumber: '',
            city: 'Warszawa',
            postalCode: '00001',
            country: 'PL',
            phone: '123456789',
            email: 'test@example.com',
            popId: 'POP-WAW-999',
            popName: 'Existing Point'
        );

        $this->clientMock->expects($this->never())->method('getNearestServicepoints');

        $resultRecipient = $this->resolver->resolveForRecipient($recipient, 'sandbox');

        $this->assertEquals('POP-WAW-999', $resultRecipient->getPopId());
        $this->assertEquals('Existing Point', $resultRecipient->getPopName());
    }

    public function testResolvesNearestPointSuccessfullyWhenPopIdIsEmpty(): void
    {
        $recipient = new RecipientData(
            name: 'Jan Kowalski',
            type: 'C',
            firstName: 'Jan',
            lastName: 'Kowalski',
            contactPerson: 'Jan Kowalski',
            street: 'Prosta',
            houseNumber: '1',
            flatNumber: '',
            city: 'Warszawa',
            postalCode: '00001',
            country: 'PL',
            phone: '123456789',
            email: 'test@example.com',
            popId: null,
            popName: null
        );

        $apiResult = new GetNearestServicepointsResult(true, [
            [
                'sap' => 'POP-WAW-123',
                'name' => 'Żabka',
                'type' => 'POP',
                'description' => '',
                'longitude' => '21.01',
                'latitude' => '52.23',
                'street' => 'Prosta',
                'houseNumber' => '1',
                'postcode' => '00-001',
                'city' => 'Warszawa',
            ],
        ]);

        $this->clientMock->expects($this->once())
            ->method('getNearestServicepoints')
            ->with($this->credentials, 'PL', '00001', 'Warszawa', 15)
            ->willReturn($apiResult);

        $resultRecipient = $this->resolver->resolveForRecipient($recipient, 'sandbox');

        $this->assertEquals('POP-WAW-123', $resultRecipient->getPopId());
        $this->assertEquals('Żabka - Prosta 1, 00-001 Warszawa', $resultRecipient->getPopName());
    }

    public function testThrowsLocalizedExceptionWhenApiFailsToFindPoints(): void
    {
        $recipient = new RecipientData(
            name: 'Jan Kowalski',
            type: 'C',
            firstName: 'Jan',
            lastName: 'Kowalski',
            contactPerson: 'Jan Kowalski',
            street: 'Prosta',
            houseNumber: '1',
            flatNumber: '',
            city: 'Warszawa',
            postalCode: '00001',
            country: 'PL',
            phone: '123456789',
            email: 'test@example.com'
        );

        $apiResult = new GetNearestServicepointsResult(false, [], 'NO_POINTS', 'No points found');

        $this->clientMock->expects($this->once())
            ->method('getNearestServicepoints')
            ->willReturn($apiResult);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not resolve nearest DHL Service Point');

        $this->resolver->resolveForRecipient($recipient, 'sandbox');
    }
}
