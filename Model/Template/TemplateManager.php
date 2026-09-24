<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Template;

use Exception;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Model\Carrier;
use Ideo\Dhl\Model\Config\Source\AddressType as AddressTypeSource;
use Ideo\Dhl\Model\Config\Source\LabelType as LabelTypeSource;
use Ideo\Dhl\Model\Config\Source\PackageType as PackageTypeSource;
use Ideo\Dhl\Model\Config\Source\ReturnBookCourier as ReturnBookCourierSource;
use Ideo\Dhl\Model\Config\Source\ReturnQrCodeType as ReturnQrCodeTypeSource;
use Ideo\Dhl\Model\Config\Source\ServiceCode as ServiceCodeSource;
use Ideo\Dhl\Model\Config\Source\ShipmentAddressType as ShipmentAddressTypeSource;
use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory as CustomsTemplateCollectionFactory;
use Ideo\Dhl\Model\ResourceModel\DeliveryService\CollectionFactory as DeliveryServiceCollectionFactory;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint\CollectionFactory as DropOffPointCollectionFactory;
use Ideo\Dhl\Model\Shipment\Data\SenderData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\OrderToShipmentDataMapper;
use Ideo\Dhl\Model\Source\CategoryOfItem;
use Ideo\Dhl\Model\Source\CustomsCurrency;
use Ideo\Dhl\Model\Source\CustomsType;
use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use InvalidArgumentException;
use Magento\Directory\Model\Config\Source\Country as CountrySource;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class TemplateManager
{
    /**
     * @param DropOffPointCollectionFactory $dropOffPointCollectionFactory
     * @param DeliveryServiceCollectionFactory $deliveryServiceCollectionFactory
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     * @param DeliveryServiceRepositoryInterface $deliveryServiceRepository
     * @param CountrySource $countrySource
     * @param ServiceCodeSource $serviceCodeSource
     * @param LabelTypeSource $labelTypeSource
     * @param PackageTypeSource $packageTypeSource
     * @param AddressTypeSource $addressTypeSource
     * @param ShipmentAddressTypeSource $shipmentAddressTypeSource
     * @param ReturnQrCodeTypeSource $returnQrCodeTypeSource
     * @param ReturnBookCourierSource $returnBookCourierSource
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderToShipmentDataMapper $mapper
     * @param TemplateVariableResolver $variableResolver
     * @param SerializerInterface $serializer
     * @param TimezoneInterface $timezone
     * @param MethodCatalog $methodCatalog
     * @param CustomsTemplateCollectionFactory $customsTemplateCollectionFactory
     * @param CustomsType $customsTypeSource
     * @param CategoryOfItem $categoryOfItemSource
     * @param CustomsCurrency $customsCurrencySource
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly DropOffPointCollectionFactory $dropOffPointCollectionFactory,
        private readonly DeliveryServiceCollectionFactory $deliveryServiceCollectionFactory,
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository,
        private readonly DeliveryServiceRepositoryInterface $deliveryServiceRepository,
        private readonly CountrySource $countrySource,
        private readonly ServiceCodeSource $serviceCodeSource,
        private readonly LabelTypeSource $labelTypeSource,
        private readonly PackageTypeSource $packageTypeSource,
        private readonly AddressTypeSource $addressTypeSource,
        private readonly ShipmentAddressTypeSource $shipmentAddressTypeSource,
        private readonly ReturnQrCodeTypeSource $returnQrCodeTypeSource,
        private readonly ReturnBookCourierSource $returnBookCourierSource,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderToShipmentDataMapper $mapper,
        private readonly TemplateVariableResolver $variableResolver,
        private readonly SerializerInterface $serializer,
        private readonly TimezoneInterface $timezone,
        private readonly MethodCatalog $methodCatalog,
        private readonly CustomsTemplateCollectionFactory $customsTemplateCollectionFactory,
        private readonly CustomsType $customsTypeSource,
        private readonly CategoryOfItem $categoryOfItemSource,
        private readonly CustomsCurrency $customsCurrencySource,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Helper to safely convert mixed values to boolean.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isTrueValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value > 0;
        }
        if (is_string($value)) {
            $trimmed = strtolower(trim($value));
            return in_array($trimmed, ['1', 'true', 'yes', 'on'], true);
        }
        return false;
    }

    /**
     * Check if a delivery service template matches the given shipping method.
     *
     * @param string $templateMethodCodes
     * @param string $shippingMethod
     *
     * @return bool
     */
    public function isMethodMatching(string $templateMethodCodes, string $shippingMethod): bool
    {
        $orderMethod = $this->normalizeMethodCode($shippingMethod);
        if ($orderMethod === '') {
            return false;
        }

        $templateCodes = array_filter(array_map('trim', explode(',', $templateMethodCodes)));
        foreach ($templateCodes as $code) {
            if ($this->normalizeMethodCode($code) === $orderMethod) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize shipping method code by stripping carrier prefix and trimming.
     *
     * @param string $methodCode
     *
     * @return string
     */
    private function normalizeMethodCode(string $methodCode): string
    {
        $clean = strtolower(trim($methodCode));
        if (str_starts_with($clean, Carrier::CODE_PREFIX)) {
            $clean = substr($clean, strlen(Carrier::CODE_PREFIX));
        }

        return $clean;
    }

    /**
     * Get matching DeliveryService ID for an order based on its shipping method.
     *
     * Returns the FIRST matching DeliveryService template ID.
     *
     * @param OrderInterface $order
     *
     * @return int|null
     */
    public function getMatchingDeliveryServiceIdForOrder(OrderInterface $order): ?int
    {
        $shippingMethod = (string)($order->getShippingMethod() ?? '');
        if (empty($shippingMethod)) {
            return null;
        }

        $collection = $this->deliveryServiceCollectionFactory->create();
        foreach ($collection as $item) {
            if ($this->isMethodMatching((string)$item->getMethodCode(), $shippingMethod)) {
                return (int)$item->getEntityId();
            }
        }

        return null;
    }

    /**
     * Get list of country options for country select dropdowns.
     *
     * @return array
     */
    public function getCountryOptions(): array
    {
        $options = $this->countrySource->toOptionArray(false);
        return array_values(array_filter($options, fn ($opt) => !empty($opt['value'])));
    }

    /**
     * Get Service Code options from source model.
     *
     * @return array
     */
    public function getServiceCodeOptions(): array
    {
        $options = $this->serviceCodeSource->toOptionArray();
        return array_values(array_filter($options, fn ($opt) => !empty($opt['value'])));
    }

    /**
     * Get Label Type options from source model.
     *
     * @return array
     */
    public function getLabelTypeOptions(): array
    {
        $options = $this->labelTypeSource->toOptionArray();
        return array_values(array_filter($options, fn ($opt) => !empty($opt['value'])));
    }

    /**
     * Get Package Type options from source model.
     *
     * @return array
     */
    public function getPackageTypeOptions(): array
    {
        $options = $this->packageTypeSource->toOptionArray();
        return array_values(array_filter($options, fn ($opt) => !empty($opt['value'])));
    }

    /**
     * Get Address Type options from source model.
     *
     * @return array
     */
    public function getAddressTypeOptions(): array
    {
        $options = $this->addressTypeSource->toOptionArray();
        return array_values(array_filter($options, fn ($opt) => !empty($opt['value'])));
    }

    /**
     * Get Shipment Recipient Address Type options (Consumer vs Business).
     *
     * @return array
     */
    public function getShipmentAddressTypeOptions(): array
    {
        return $this->shipmentAddressTypeSource->toOptionArray();
    }



    /**
     * Get Return QR Code Type options from source model.
     *
     * @return array
     */
    public function getReturnQrCodeTypeOptions(): array
    {
        $options = $this->returnQrCodeTypeSource->toOptionArray();
        return array_values(array_filter($options, fn ($opt) => isset($opt['value']) && $opt['value'] !== ''));
    }

    /**
     * Get Return Book Courier options from source model.
     *
     * @return array
     */
    public function getReturnBookCourierOptions(): array
    {
        $options = $this->returnBookCourierSource->toOptionArray();
        return array_values(array_filter($options, fn ($opt) => isset($opt['value']) && $opt['value'] !== ''));
    }

    /**
     * Get list of sender templates options (from Drop-Off Points) with asterisk for default.
     *
     * @return array
     */
    public function getSenderTemplatesOptions(): array
    {
        $collection = $this->dropOffPointCollectionFactory->create();
        $options = [];

        foreach ($collection as $item) {
            $nameStr = $item->getTemplateName() ?: $item->getSenderName();
            $label = sprintf('%s (%s, %s)', $nameStr, $item->getCity(), $item->getStreet());
            if ($item->getIsDefault()) {
                $label .= ' *';
            }
            $options[] = [
                'value' => (int)$item->getEntityId(),
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * Get default Drop-Off Point ID if available.
     *
     * @return int|null
     */
    public function getDefaultDropOffPointId(): ?int
    {
        $collection = $this->dropOffPointCollectionFactory->create();
        $collection->addFieldToFilter('is_default', 1);
        $point = $collection->getFirstItem();

        if ($point && $point->getEntityId()) {
            return (int)$point->getEntityId();
        }

        return null;
    }

    /**
     * Get list of shipment templates options, optionally filtered by order shipping method.
     *
     * @param string|null $shippingMethod
     *
     * @return array
     */
    public function getShipmentTemplatesOptions(?string $shippingMethod = null): array
    {
        $collection = $this->deliveryServiceCollectionFactory->create();
        $options = [];

        foreach ($collection as $item) {
            if ($shippingMethod !== null && !$this->isMethodMatching((string)$item->getMethodCode(), $shippingMethod)) {
                continue;
            }

            $defaultLabel = $item->getMethodCode() ?: (string)__('Template #%1', $item->getEntityId());
            $label = $item->getTemplateName() ?: $defaultLabel;
            $options[] = [
                'value' => (int)$item->getEntityId(),
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * Get list of customs clearance templates options.
     *
     * @return array
     */
    public function getCustomsTemplatesOptions(): array
    {
        $collection = $this->customsTemplateCollectionFactory->create();
        $options = [];

        foreach ($collection as $item) {
            $label = (string)($item->getTemplateName() ?: __('Template #%1', $item->getEntityId()));
            if ($item->getIsDefault()) {
                $label .= ' *';
            }
            $options[] = [
                'value' => (int)$item->getEntityId(),
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * Get Customs Type options from source model.
     *
     * @return array
     */
    public function getCustomsTypeOptions(): array
    {
        return $this->customsTypeSource->toOptionArray();
    }

    /**
     * Get Category Of Item options from source model.
     *
     * @return array
     */
    public function getCategoryOfItemOptions(): array
    {
        return $this->categoryOfItemSource->toOptionArray();
    }

    /**
     * Get Customs Currency options from source model.
     *
     * @return array
     */
    public function getCustomsCurrencyOptions(): array
    {
        return $this->customsCurrencySource->toOptionArray();
    }

    /**
     * Get SenderData by Drop-Off Point template ID.
     *
     * @param int $templateId
     *
     * @return SenderData|null
     */
    public function getSenderTemplate(int $templateId): ?SenderData
    {
        try {
            $point = $this->dropOffPointRepository->getById($templateId);
            $sapNumber = (string)($point->getSapNumber() ?? '');

            return new SenderData(
                payer: 'SENDER',
                sapNumber: $sapNumber,
                name: (string)($point->getSenderName() ?? ''),
                firstName: (string)($point->getFirstName() ?? ''),
                lastName: (string)($point->getLastName() ?? ''),
                street: (string)($point->getStreet() ?? ''),
                houseNumber: (string)($point->getHouseNumber() ?? ''),
                city: (string)($point->getCity() ?? ''),
                postalCode: (string)($point->getPostalCode() ?? ''),
                country: 'PL',
                phone: (string)($point->getPhone() ?? ''),
                email: (string)($point->getEmail() ?? '')
            );
        } catch (Exception $e) {
            $this->logger?->error('Error loading sender template: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Get default SenderData.
     *
     * @return SenderData
     */
    public function getDefaultSenderTemplate(): SenderData
    {
        $defaultPointId = $this->getDefaultDropOffPointId();
        if ($defaultPointId) {
            $template = $this->getSenderTemplate($defaultPointId);
            if ($template) {
                return $template;
            }
        }

        return new SenderData(
            payer: 'SENDER',
            sapNumber: '',
            country: 'PL'
        );
    }

    /**
     * Get ShipmentSettingsData by shipment template (Delivery Service) ID and optional order ID.
     *
     * @param int $templateId
     * @param int|null $orderId
     *
     * @return ShipmentSettingsData|null
     */
    public function getShipmentTemplate(int $templateId, ?int $orderId = null): ?ShipmentSettingsData
    {
        try {
            $service = $this->deliveryServiceRepository->getById($templateId);
            $rawContent = (string)($service->getContent() ?? '');

            $shippingMethod = null;
            if ($orderId) {
                $order = $this->orderRepository->get($orderId);
                $contentVal = $rawContent !== '' ? $this->variableResolver->resolve($rawContent, $order) : '';
                $shippingMethod = (string)($order->getShippingMethod() ?? '');
            } else {
                $contentVal = $rawContent;
            }

            $packages = $service->getPackages();
            $packagesData = [];
            if (!empty($packages)) {
                foreach ($packages as $pkg) {
                    if (is_array($pkg)) {
                        $packagesData[] = PackageData::fromArray($pkg);
                    }
                }
            }

            $firstPkg = $packagesData[0] ?? null;

            return new ShipmentSettingsData(
                serviceType: $this->methodCatalog->getServiceCodeForMethod($shippingMethod),
                pickupType: 'COURIER',
                content: $contentVal,
                mpk: (string)($service->getMpk() ?? ''),
                comment: (string)($service->getComment() ?? ''),
                reference: (string)($service->getReference() ?? ''),
                shipmentDate: $this->timezone->scopeDate()->format('Y-m-d'),
                packageType: $firstPkg ? $firstPkg->getPackageType() : 'PACKAGE',
                weight: $firstPkg ? $firstPkg->getWeight() : 1.0,
                width: $firstPkg ? $firstPkg->getWidth() : null,
                height: $firstPkg ? $firstPkg->getHeight() : null,
                length: $firstPkg ? $firstPkg->getLength() : null,
                labelType: (string)($service->getLabelType() ?: 'BLP'),
                isNonStandard: $firstPkg ? $firstPkg->isNonStandard() : false,
                hasEuroReturn: $firstPkg ? $firstPkg->hasEuroReturn() : false,
                autoCreateReturnShipment: $service->getAutoCreateReturnShipment(),
                returnLabelType: $service->getReturnLabelType(),
                returnQrCodeType: $service->getReturnQrCodeType(),
                returnLabelExpDays: $service->getReturnLabelExpDays(),
                returnBookCourier: $service->getReturnBookCourier(),
                addressType: (string)($service->getAddressType() ?: 'N'),
                packages: $packagesData
            );
        } catch (Exception $e) {
            $this->logger?->error('Error loading shipment template: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Get SpecialServicesData array mapped from DeliveryService model additional_services column.
     *
     * @param int $templateId
     *
     * @return array|null
     */
    public function getSpecialServicesTemplate(int $templateId): ?array
    {
        try {
            $service = $this->deliveryServiceRepository->getById($templateId);
            $addServicesRaw = $service->getAdditionalServices();
            if (is_string($addServicesRaw)) {
                try {
                    $decoded = $this->serializer->unserialize($addServicesRaw);
                    $addServices = is_array($decoded) ? $decoded : [];
                } catch (InvalidArgumentException $e) {
                    $addServices = [];
                }
            } elseif (is_array($addServicesRaw)) {
                $addServices = $addServicesRaw;
            } else {
                $addServices = [];
            }

            $insVal = isset($addServices['insuranceValue'])
                ? (float)$addServices['insuranceValue']
                : (isset($addServices['serviceValueUbez']) ? (float)$addServices['serviceValueUbez'] : null);
            $codVal = isset($addServices['codAmount'])
                ? (float)$addServices['codAmount']
                : (isset($addServices['serviceValueCOD']) ? (float)$addServices['serviceValueCOD'] : null);
            $isEve = $this->isTrueValue($addServices['eveningDelivery'] ?? $addServices['service_1722'] ?? false);
            $isSat = $this->isTrueValue($addServices['saturdayDelivery'] ?? $addServices['service_SOBOTA'] ?? false);
            $isSatPickup = $this->isTrueValue(
                $addServices['pickupOnSaturday']
                ?? $addServices['saturdayDispatch']
                ?? $addServices['service_NAD_SOBOTA']
                ?? false
            );
            $isPdiVal = $this->isTrueValue(
                $addServices['predeliveryInformation']
                ?? $addServices['pdi']
                ?? $addServices['service_PDI']
                ?? false
            );
            $isRodVal = $this->isTrueValue(
                $addServices['returnOnDelivery']
                ?? $addServices['rod']
                ?? $addServices['service_ROD']
                ?? false
            );
            $isPodVal = $this->isTrueValue(
                $addServices['proofOfDelivery']
                ?? $addServices['pod']
                ?? $addServices['service_POD']
                ?? false
            );

            return [
                'insurance' => $this->isTrueValue($addServices['insurance'] ?? $addServices['service_UBEZP'] ?? false),
                'insuranceValue' => $insVal,
                'cod' => $this->isTrueValue($addServices['cod'] ?? $addServices['service_COD'] ?? false),
                'codAmount' => $codVal,
                'deliveryHours' => $isEve ? '1722' : null,
                'eveningDelivery' => $isEve,
                'saturdayDelivery' => $isSat,
                'saturdayDispatch' => $isSatPickup,
                'pdi' => $isPdiVal,
                'rod' => $isRodVal,
                'rodDocumentText' => (string)($addServices['rodDocumentText'] ?? $addServices['textInstruction'] ?? ''),
                'pod' => $isPodVal,
                'agever' => $this->isTrueValue($addServices['agever'] ?? $addServices['service_AGEVER'] ?? false),
                'odb' => $this->isTrueValue($addServices['odb'] ?? $addServices['service_ODB'] ?? false),
            ];
        } catch (Exception $e) {
            $this->logger?->error('Error loading special services template: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }
}
