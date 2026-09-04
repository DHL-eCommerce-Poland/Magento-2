<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Ideo\Dhl\Api\Data\DeliveryServiceInterface;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;

class DeliveryServiceResolver
{
    /**
     * @var array<string, DeliveryServiceInterface|null>
     */
    private array $resolvedServices = [];

    /**
     * @param DeliveryServiceRepositoryInterface $deliveryServiceRepository
     */
    public function __construct(
        private readonly DeliveryServiceRepositoryInterface $deliveryServiceRepository
    ) {
    }

    /**
     * Resolve delivery service for the given method code with in-memory caching.
     *
     * @param string $methodCode
     *
     * @return DeliveryServiceInterface|null
     */
    public function resolveForMethod(string $methodCode): ?DeliveryServiceInterface
    {
        if (!array_key_exists($methodCode, $this->resolvedServices)) {
            $this->resolvedServices[$methodCode] = $this->deliveryServiceRepository
                ->getByMethod($methodCode);
        }

        return $this->resolvedServices[$methodCode];
    }
}
