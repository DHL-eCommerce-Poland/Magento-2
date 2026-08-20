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
