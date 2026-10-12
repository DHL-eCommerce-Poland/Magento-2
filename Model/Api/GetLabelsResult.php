<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Api;

class GetLabelsResult
{
    public const LABEL_TYPE_BLP = 'BLP';
    public const LABEL_TYPE_LBLP = 'LBLP';
    public const LABEL_TYPE_ZBLP = 'ZBLP';
    public const LABEL_TYPE_ZBLP300 = 'ZBLP300';
    /**
     * @param bool $success
     * @param array $items
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly array $items = [],
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null
    ) {
    }

    /**
     * Whether the getLabels request succeeded.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get label items array.
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get error code if label retrieval failed.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get error message if label retrieval failed.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
