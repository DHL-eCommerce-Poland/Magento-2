<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

interface ClientInterface
{
    /**
     * Test API connection and credentials.
     *
     * @param Credentials $credentials
     *
     * @return TestConnectionResult
     */
    public function testConnection(Credentials $credentials): TestConnectionResult;
}
