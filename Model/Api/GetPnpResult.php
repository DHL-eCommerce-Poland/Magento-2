<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class GetPnpResult
{
    /**
     * @param bool $success
     * @param string|null $fileData Binary PnP report content
     * @param string|null $fileName Suggested filename for the report
     * @param string|null $mimeType File MIME type
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $fileData = null,
        private readonly ?string $fileName = null,
        private readonly ?string $mimeType = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null
    ) {
    }

    /**
     * Whether the getPnp request succeeded.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get binary PnP report content.
     *
     * @return string|null
     */
    public function getFileData(): ?string
    {
        return $this->fileData;
    }

    /**
     * Get suggested filename for the report.
     *
     * @return string|null
     */
    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    /**
     * Get MIME type of the report document.
     *
     * @return string|null
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Get error code if PnP report retrieval failed.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get error message if PnP report retrieval failed.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
