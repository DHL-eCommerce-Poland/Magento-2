<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class GetEpodResult
{
    /**
     * @param bool $success
     * @param string|null $fileData Binary ePOD content
     * @param string|null $mimeType File MIME type
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $fileData = null,
        private readonly ?string $mimeType = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null
    ) {
    }

    /**
     * Whether the getEpod request succeeded.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get binary ePOD content.
     *
     * @return string|null
     */
    public function getFileData(): ?string
    {
        return $this->fileData;
    }

    /**
     * Get MIME type of the ePOD document.
     *
     * @return string|null
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Get error code if ePOD retrieval failed.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get error message if ePOD retrieval failed.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
