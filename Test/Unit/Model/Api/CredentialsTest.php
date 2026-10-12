<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\Credentials;
use PHPUnit\Framework\TestCase;

class CredentialsTest extends TestCase
{
    /**
     * Test credentials getters and immutability.
     *
     * @return void
     */
    public function testCredentialsProperties(): void
    {
        $credentials = new Credentials('test_user', 'secret_pass', true);

        $this->assertEquals('test_user', $credentials->getUsername());
        $this->assertEquals('secret_pass', $credentials->getPassword());
        $this->assertTrue($credentials->isSandbox());
    }

    /**
     * Test credentials for production environment.
     *
     * @return void
     */
    public function testProductionCredentials(): void
    {
        $credentials = new Credentials('prod_user', 'prod_pass', false);

        $this->assertEquals('prod_user', $credentials->getUsername());
        $this->assertEquals('prod_pass', $credentials->getPassword());
        $this->assertFalse($credentials->isSandbox());
    }
}
