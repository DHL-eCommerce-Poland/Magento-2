<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

class ShipmentDeletionResult
{
    /**
     * @param bool $success Whether the shipment was deleted from DHL and Magento.
     * @param string|null $errorMessage Reason the deletion could not be completed.
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $errorMessage = null
    ) {
    }

    /**
     * Check whether the shipment deletion completed successfully.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get the reason the shipment could not be deleted.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
