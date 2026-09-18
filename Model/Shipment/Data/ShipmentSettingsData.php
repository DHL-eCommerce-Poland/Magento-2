<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment\Data;

use Ideo\Dhl\Model\MethodCatalog;

class ShipmentSettingsData
{
    /** @var PackageData[] */
    private array $packages = [];

    /**
     * @param string $serviceType
     * @param string $pickupType
     * @param string $content
     * @param string $mpk
     * @param string $comment
     * @param string $reference
     * @param string $shipmentDate
     * @param string|null $pickupHourFrom
     * @param string|null $pickupHourTo
     * @param string $packageType
     * @param float $weight
     * @param float|null $width
     * @param float|null $height
     * @param float|null $length
     * @param int $packageQuantity
     * @param string $labelType
     * @param bool $isNonStandard
     * @param bool $hasEuroReturn
     * @param bool $autoCreateReturnShipment
     * @param string|null $returnLabelType
     * @param string|null $returnQrCodeType
     * @param int|null $returnLabelExpDays
     * @param int|null $returnBookCourier
     * @param int|null $dropOffPointId
     * @param string|null $addressType
     * @param PackageData[] $packages
     */
    public function __construct(
        private string $serviceType = '',
        private string $pickupType = 'COURIER',
        private string $content = '',
        private string $mpk = '',
        private string $comment = '',
        private string $reference = '',
        private string $shipmentDate = '',
        private ?string $pickupHourFrom = null,
        private ?string $pickupHourTo = null,
        private string $packageType = 'PACKAGE',
        private float $weight = 1.0,
        private ?float $width = null,
        private ?float $height = null,
        private ?float $length = null,
        private int $packageQuantity = 1,
        private string $labelType = 'BLP',
        private bool $isNonStandard = false,
        private bool $hasEuroReturn = false,
        private bool $autoCreateReturnShipment = false,
        private ?string $returnLabelType = null,
        private ?string $returnQrCodeType = null,
        private ?int $returnLabelExpDays = null,
        private ?int $returnBookCourier = null,
        private ?int $dropOffPointId = null,
        private ?string $addressType = 'N',
        array $packages = []
    ) {
        if (!empty($packages)) {
            foreach ($packages as $pkg) {
                if ($pkg instanceof PackageData) {
                    $this->packages[] = $pkg;
                } elseif (is_array($pkg)) {
                    $this->packages[] = PackageData::fromArray($pkg);
                }
            }
        }

        if (empty($this->packages)) {
            $this->packages[] = new PackageData(
                packageType: $this->packageType,
                quantity: $this->packageQuantity,
                weight: $this->weight,
                width: $this->width,
                length: $this->length,
                height: $this->height,
                isNonStandard: $this->isNonStandard,
                hasEuroReturn: $this->hasEuroReturn
            );
        }
    }

    /**
     * Get DHL service type (e.g. AH, 09, 12).
     *
     * @return string
     */
    public function getServiceType(): string
    {
        return $this->serviceType;
    }

    /**
     * Get pickup type (e.g. COURIER, DROP_OFF).
     *
     * @return string
     */
    public function getPickupType(): string
    {
        return $this->pickupType;
    }

    /**
     * Get package contents description.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get cost center code (MPK).
     *
     * @return string
     */
    public function getMpk(): string
    {
        return $this->mpk;
    }

    /**
     * Get additional comments.
     *
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Get shipment reference number.
     *
     * @return string
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * Get shipment date string (YYYY-MM-DD).
     *
     * @return string
     */
    public function getShipmentDate(): string
    {
        return $this->shipmentDate;
    }

    /**
     * Get pickup window start hour.
     *
     * @return string|null
     */
    public function getPickupHourFrom(): ?string
    {
        return $this->pickupHourFrom;
    }

    /**
     * Get pickup window end hour.
     *
     * @return string|null
     */
    public function getPickupHourTo(): ?string
    {
        return $this->pickupHourTo;
    }

    /**
     * Get first package's type (for backwards compatibility).
     *
     * @return string
     */
    public function getPackageType(): string
    {
        return $this->packages[0]->getPackageType();
    }

    /**
     * Get first package's weight (for backwards compatibility).
     *
     * @return float
     */
    public function getWeight(): float
    {
        return $this->packages[0]->getWeight();
    }

    /**
     * Get first package's width (for backwards compatibility).
     *
     * @return float|null
     */
    public function getWidth(): ?float
    {
        return $this->packages[0]->getWidth();
    }

    /**
     * Get first package's height (for backwards compatibility).
     *
     * @return float|null
     */
    public function getHeight(): ?float
    {
        return $this->packages[0]->getHeight();
    }

    /**
     * Get first package's length (for backwards compatibility).
     *
     * @return float|null
     */
    public function getLength(): ?float
    {
        return $this->packages[0]->getLength();
    }

    /**
     * Get total package quantity across all packages.
     *
     * @return int
     */
    public function getPackageQuantity(): int
    {
        $totalQty = 0;
        foreach ($this->packages as $pkg) {
            $totalQty += $pkg->getQuantity();
        }
        return max(1, $totalQty);
    }

    /**
     * Get label format (e.g. BLP, EPL, ZPL).
     *
     * @return string
     */
    public function getLabelType(): string
    {
        return $this->labelType;
    }

    /**
     * Check if first package is non-standard (for backwards compatibility).
     *
     * @return bool
     */
    public function isNonStandard(): bool
    {
        return $this->packages[0]->isNonStandard();
    }

    /**
     * Check if first package has euro pallet return (for backwards compatibility).
     *
     * @return bool
     */
    public function hasEuroReturn(): bool
    {
        return $this->packages[0]->hasEuroReturn();
    }

    /**
     * Check if return shipment label should be auto-created.
     *
     * @return bool
     */
    public function autoCreateReturnShipment(): bool
    {
        return $this->autoCreateReturnShipment;
    }

    /**
     * Get return label format type.
     *
     * @return string|null
     */
    public function getReturnLabelType(): ?string
    {
        return $this->returnLabelType;
    }

    /**
     * Get return QR code format type.
     *
     * @return string|null
     */
    public function getReturnQrCodeType(): ?string
    {
        return $this->returnQrCodeType;
    }

    /**
     * Get return label expiration days.
     *
     * @return int|null
     */
    public function getReturnLabelExpDays(): ?int
    {
        return $this->returnLabelExpDays;
    }

    /**
     * Get return book courier method (0 = Drop-off point, 1 = Drop-off or courier).
     *
     * @return int|null
     */
    public function getReturnBookCourier(): ?int
    {
        return $this->returnBookCourier;
    }

    /**
     * Get selected drop off point ID.
     *
     * @return int|null
     */
    public function getDropOffPointId(): ?int
    {
        return $this->dropOffPointId;
    }

    /**
     * Get address type setting (B, C, N).
     *
     * @return string|null
     */
    public function getAddressType(): ?string
    {
        return $this->addressType;
    }

    /**
     * Get list of package data items.
     *
     * @return PackageData[]
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * Convert shipment settings object to array format.
     *
     * @return array
     */
    public function toArray(): array
    {
        $firstPkg = $this->packages[0] ?? new PackageData();

        return [
            'serviceType' => $this->serviceType,
            'pickupType' => $this->pickupType,
            'content' => $this->content,
            'mpk' => $this->mpk,
            'comment' => $this->comment,
            'reference' => $this->reference,
            'shipmentDate' => $this->shipmentDate,
            'pickupHourFrom' => $this->pickupHourFrom,
            'pickupHourTo' => $this->pickupHourTo,
            'packageType' => $firstPkg->getPackageType(),
            'weight' => $firstPkg->getWeight(),
            'width' => $firstPkg->getWidth(),
            'height' => $firstPkg->getHeight(),
            'length' => $firstPkg->getLength(),
            'packageQuantity' => $this->getPackageQuantity(),
            'labelType' => $this->labelType,
            'isNonStandard' => $firstPkg->isNonStandard(),
            'hasEuroReturn' => $firstPkg->hasEuroReturn(),
            'autoCreateReturnShipment' => $this->autoCreateReturnShipment,
            'returnLabelType' => $this->returnLabelType,
            'returnQrCodeType' => $this->returnQrCodeType,
            'returnLabelExpDays' => $this->returnLabelExpDays,
            'returnBookCourier' => $this->returnBookCourier,
            'dropOffPointId' => $this->dropOffPointId,
            'addressType' => $this->addressType,
            'packages' => array_map(fn (PackageData $p) => $p->toArray(), $this->packages),
        ];
    }

    /**
     * Create ShipmentSettingsData object from array representation.
     *
     * @param array $data
     *
     * @return self
     */
    // phpcs:disable Generic.Files.LineLength.TooLong
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    public static function fromArray(array $data): self
    {
        $packagesRaw = $data['packages'] ?? [];
        $packages = [];

        if (is_array($packagesRaw) && !empty($packagesRaw)) {
            foreach ($packagesRaw as $pkgItem) {
                if (is_array($pkgItem)) {
                    $packages[] = PackageData::fromArray($pkgItem);
                }
            }
        }

        return new self(
            serviceType: (string)($data['serviceType'] ?? ''),
            pickupType: (string)($data['pickupType'] ?? 'COURIER'),
            content: (string)($data['content'] ?? ''),
            mpk: (string)($data['mpk'] ?? ''),
            comment: (string)($data['comment'] ?? ''),
            reference: (string)($data['reference'] ?? ''),
            shipmentDate: (string)($data['shipmentDate'] ?? ''),
            pickupHourFrom: $data['pickupHourFrom'] ?? null,
            pickupHourTo: $data['pickupHourTo'] ?? null,
            packageType: (string)($data['packageType'] ?? 'PACKAGE'),
            weight: (float)($data['weight'] ?? 1.0),
            width: isset($data['width']) ? (float)$data['width'] : null,
            height: isset($data['height']) ? (float)$data['height'] : null,
            length: isset($data['length']) ? (float)$data['length'] : null,
            packageQuantity: (int)($data['packageQuantity'] ?? 1),
            labelType: (string)($data['labelType'] ?? 'BLP'),
            isNonStandard: filter_var($data['isNonStandard'] ?? false, FILTER_VALIDATE_BOOLEAN),
            hasEuroReturn: filter_var($data['hasEuroReturn'] ?? false, FILTER_VALIDATE_BOOLEAN),
            autoCreateReturnShipment: filter_var($data['autoCreateReturnShipment'] ?? false, FILTER_VALIDATE_BOOLEAN),
            returnLabelType: isset($data['returnLabelType']) && $data['returnLabelType'] !== '' ? (string)$data['returnLabelType'] : null,
            returnQrCodeType: isset($data['returnQrCodeType']) && $data['returnQrCodeType'] !== '' ? (string)$data['returnQrCodeType'] : null,
            returnLabelExpDays: isset($data['returnLabelExpDays']) && $data['returnLabelExpDays'] !== '' ? (int)$data['returnLabelExpDays'] : null,
            returnBookCourier: isset($data['returnBookCourier']) && $data['returnBookCourier'] !== '' ? (int)$data['returnBookCourier'] : null,
            dropOffPointId: isset($data['dropOffPointId']) ? (int)$data['dropOffPointId'] : null,
            addressType: (string)($data['addressType'] ?? 'N'),
            packages: $packages
        );
    }
}
