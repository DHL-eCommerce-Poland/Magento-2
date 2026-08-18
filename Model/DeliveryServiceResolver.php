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
     * Resolve delivery service for the given website ID and method code with in-memory caching.
     *
     * @param int $websiteId
     * @param string $methodCode
     *
     * @return DeliveryServiceInterface|null
     */
    public function resolveForMethod(int $websiteId, string $methodCode): ?DeliveryServiceInterface
    {
        $cacheKey = $websiteId . '_' . $methodCode;
        if (!array_key_exists($cacheKey, $this->resolvedServices)) {
            $this->resolvedServices[$cacheKey] = $this->deliveryServiceRepository
                ->getByWebsiteAndMethod($websiteId, $methodCode);
        }

        return $this->resolvedServices[$cacheKey];
    }
}
