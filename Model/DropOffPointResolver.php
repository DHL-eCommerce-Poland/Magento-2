<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Ideo\Dhl\Api\Data\DropOffPointInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;

class DropOffPointResolver
{
    /**
     * @var array<int, DropOffPointInterface|null>
     */
    private array $resolvedDropOffPoints = [];

    /**
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     */
    public function __construct(
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository
    ) {
    }

    /**
     * Resolve default drop-off point for the given website ID with in-memory caching.
     *
     * @param int $websiteId
     *
     * @return DropOffPointInterface|null
     */
    public function resolveForWebsite(int $websiteId): ?DropOffPointInterface
    {
        if (!array_key_exists($websiteId, $this->resolvedDropOffPoints)) {
            $this->resolvedDropOffPoints[$websiteId] = $this->dropOffPointRepository
                ->getDefaultByWebsiteId($websiteId);
        }

        return $this->resolvedDropOffPoints[$websiteId];
    }
}
