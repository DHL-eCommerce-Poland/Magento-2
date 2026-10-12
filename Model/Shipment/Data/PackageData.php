<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment\Data;

class PackageData
{
    /**
     * @param string $packageType
     * @param int $quantity
     * @param float $weight
     * @param float|null $width
     * @param float|null $length
     * @param float|null $height
     * @param bool $isNonStandard
     * @param bool $hasEuroReturn
     */
    public function __construct(
        private string $packageType = 'PACKAGE',
        private int $quantity = 1,
        private float $weight = 0.0,
        private ?float $width = null,
        private ?float $length = null,
        private ?float $height = null,
        private bool $isNonStandard = false,
        private bool $hasEuroReturn = false
    ) {
        if ($this->packageType !== 'PALLET') {
            $this->hasEuroReturn = false;
        }
    }

    /**
     * Get package type (e.g. PACKAGE, ENVELOPE, PALLET).
     *
     * @return string
     */
    public function getPackageType(): string
    {
        return $this->packageType;
    }

    /**
     * Get quantity of packages for this row.
     *
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Get weight in kilograms.
     *
     * @return float
     */
    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * Get width in centimeters.
     *
     * @return float|null
     */
    public function getWidth(): ?float
    {
        return $this->width;
    }

    /**
     * Get length in centimeters.
     *
     * @return float|null
     */
    public function getLength(): ?float
    {
        return $this->length;
    }

    /**
     * Get height in centimeters.
     *
     * @return float|null
     */
    public function getHeight(): ?float
    {
        return $this->height;
    }

    /**
     * Check if package is non-standard (NST).
     *
     * @return bool
     */
    public function isNonStandard(): bool
    {
        return $this->isNonStandard;
    }

    /**
     * Check if euro pallet return is requested.
     *
     * @return bool
     */
    public function hasEuroReturn(): bool
    {
        return $this->hasEuroReturn;
    }

    /**
     * Convert value object properties to array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'packageType' => $this->packageType,
            'quantity' => $this->quantity,
            'weight' => $this->weight,
            'width' => $this->width,
            'length' => $this->length,
            'height' => $this->height,
            'isNonStandard' => $this->isNonStandard,
            'hasEuroReturn' => $this->hasEuroReturn,
        ];
    }

    /**
     * Create PackageData value object from array.
     *
     * @param array $data
     *
     * @return self
     */
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    public static function fromArray(array $data): self
    {
        $toFloat = static function (mixed $val): ?float {
            if ($val === null || $val === '') {
                return null;
            }
            if (is_string($val)) {
                $val = str_replace(',', '.', trim($val));
            }
            return (float)$val;
        };

        return new self(
            (string)($data['packageType'] ?? $data['package_type'] ?? 'PACKAGE'),
            isset($data['quantity']) ? (int)str_replace(',', '.', (string)$data['quantity']) : 1,
            $toFloat($data['weight'] ?? 0.0) ?? 0.0,
            isset($data['width']) && $data['width'] !== null ? $toFloat($data['width']) : null,
            isset($data['length']) && $data['length'] !== null ? $toFloat($data['length']) : null,
            isset($data['height']) && $data['height'] !== null ? $toFloat($data['height']) : null,
            filter_var($data['isNonStandard'] ?? $data['is_non_standard'] ?? false, FILTER_VALIDATE_BOOLEAN),
            filter_var($data['hasEuroReturn'] ?? $data['has_euro_return'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );
    }
}
