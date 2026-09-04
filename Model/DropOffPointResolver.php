<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Ideo\Dhl\Api\Data\DropOffPointInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;

class DropOffPointResolver
{
    private ?DropOffPointInterface $resolvedDefault = null;
    private bool $isResolved = false;

    /**
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     */
    public function __construct(
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository
    ) {
    }

    /**
     * Resolve default drop-off point with in-memory caching.
     *
     * @return DropOffPointInterface|null
     */
    public function resolveDefault(): ?DropOffPointInterface
    {
        if (!$this->isResolved) {
            $this->resolvedDefault = $this->dropOffPointRepository->getDefault();
            $this->isResolved = true;
        }

        return $this->resolvedDefault;
    }
}
