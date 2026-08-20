<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class Credentials
{
    /**
     * @param string $username
     * @param string $password
     * @param bool $isSandbox
     */
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly bool $isSandbox
    ) {
    }

    /**
     * Get API username.
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Get API password.
     *
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Check if environment is Sandbox.
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->isSandbox;
    }
}
