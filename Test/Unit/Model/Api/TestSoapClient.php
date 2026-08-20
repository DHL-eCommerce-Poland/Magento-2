<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use SoapClient;
use SoapFault;

class TestSoapClient extends SoapClient
{
    /**
     * @param bool $shouldThrowFault
     */
    public function __construct(
        private readonly bool $shouldThrowFault = false
    ) {
        parent::__construct(null, [
            'location' => 'http://localhost',
            'uri' => 'http://localhost',
            'exceptions' => true,
        ]);
    }

    /**
     * Dummy getPostalCodeServices method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function getPostalCodeServices(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('AUTH_ERR', 'Invalid credentials');
        }

        return (object) ['getPostalCodeServicesResult' => (object) ['item' => []]];
    }

    /**
     * Dummy createShipments method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function createShipments(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('CREATE_ERR', 'Error creating shipment');
        }

        return (object) [
            'createShipmentsResult' => (object) [
                'item' => [
                    (object) ['shipmentId' => '1234567890'],
                ],
            ],
        ];
    }

    /**
     * Dummy __getLastRequest method.
     *
     * @return string
     */
    public function __getLastRequest(): string
    {
        return '<xml><password>secret</password></xml>';
    }

    /**
     * Dummy __getLastResponse method.
     *
     * @return string
     */
    public function __getLastResponse(): string
    {
        return '<xml>response</xml>';
    }
}
