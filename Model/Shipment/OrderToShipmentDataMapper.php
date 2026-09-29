<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\ServicePoint\NearestPointResolver;
use Ideo\Dhl\Model\Shipment\Data\PackageData;
use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Ideo\Dhl\Model\Shipment\Data\SenderData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;
use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class OrderToShipmentDataMapper
{
    /**
     * @param StreetAddressParser $streetAddressParser
     * @param OrderWeightAggregator $weightAggregator
     * @param TemplateVariableResolver $variableResolver
     * @param SerializerInterface $serializer
     * @param TimezoneInterface $timezone
     * @param NearestPointResolver $nearestPointResolver
     * @param MethodCatalog $methodCatalog
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly StreetAddressParser $streetAddressParser,
        private readonly OrderWeightAggregator $weightAggregator,
        private readonly TemplateVariableResolver $variableResolver,
        private readonly SerializerInterface $serializer,
        private readonly TimezoneInterface $timezone,
        private readonly NearestPointResolver $nearestPointResolver,
        private readonly MethodCatalog $methodCatalog,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Map Magento Order into a DhlShipmentData object.
     *
     * @param OrderInterface $order
     * @param array $defaultSenderDefaults
     * @param array $defaultShipmentDefaults
     *
     * @return DhlShipmentData
     * @throws LocalizedException
     */
    public function map(
        OrderInterface $order,
        array $defaultSenderDefaults = [],
        array $defaultShipmentDefaults = []
    ): DhlShipmentData {
        if ($order->getIsVirtual()) {
            throw new LocalizedException(__('DHL shipping cannot be created for virtual orders.'));
        }

        $shippingAddress = $order->getShippingAddress();

        // 1. Recipient mapping
        $company = '';
        $firstname = '';
        $lastname = '';
        $contactPerson = '';
        $streetLines = [];
        $city = '';
        $postcode = '';
        $country = 'PL';
        $phone = '';
        $email = (string)($order->getCustomerEmail() ?? '');

        if ($shippingAddress) {
            $firstname = (string)($shippingAddress->getFirstname() ?? '');
            $lastname = (string)($shippingAddress->getLastname() ?? '');
            $company = (string)($shippingAddress->getCompany() ?? '');
            $contactPerson = trim($firstname . ' ' . $lastname);
            $streetLines = (array)($shippingAddress->getStreet() ?? []);
            $city = (string)($shippingAddress->getCity() ?? '');
            $postcode = (string)($shippingAddress->getPostcode() ?? '');
            $country = (string)($shippingAddress->getCountryId() ?? 'PL');
            if (strtoupper($country) === 'PL') {
                $postcode = str_replace('-', '', $postcode);
            }
            $phone = (string)($shippingAddress->getTelephone() ?? '');
            if (empty($email)) {
                $email = (string)($shippingAddress->getEmail() ?? '');
            }
        }

        $addressParsed = $this->streetAddressParser->parse($streetLines);

        // Address Type (B - Business, C - Consumer/Private, N - Based on Cart/Order)
        $templateAddressType = (string)($defaultShipmentDefaults['addressType'] ?? 'N');
        if ($templateAddressType === 'B') {
            $recipientType = 'B';
        } elseif ($templateAddressType === 'C') {
            $recipientType = 'C';
        } else {
            $recipientType = !empty($company) ? 'B' : 'C';
        }

        // Check POP Service Point ID & Address from shipping address or order shipping description
        $popId = null;
        $popName = null;

        if ($shippingAddress) {
            $servicePointId = (string)($shippingAddress->getData('dhl_service_point_id') ?? '');
            if (!empty($servicePointId)) {
                $popId = $servicePointId;
                $spName = (string)($shippingAddress->getData('dhl_service_point_name') ?? '');
                $spStreet = (string)($shippingAddress->getData('dhl_service_point_street') ?? '');
                $spPostcode = (string)($shippingAddress->getData('dhl_service_point_postcode') ?? '');
                $spCity = (string)($shippingAddress->getData('dhl_service_point_city') ?? '');

                $addressParts = array_filter([$spStreet, trim($spPostcode . ' ' . $spCity)]);
                $fullAddressStr = implode(', ', $addressParts);

                if (!empty($spName) && !empty($fullAddressStr)) {
                    $popName = sprintf('%s - %s', $spName, $fullAddressStr);
                } elseif (!empty($fullAddressStr)) {
                    $popName = $fullAddressStr;
                } else {
                    $popName = $spName ?: $popId;
                }
            }
        }

        if (empty($popId)) {
            $shippingMethod = (string)($order->getShippingMethod() ?? '');
            if (str_contains(strtolower($shippingMethod), 'pop')
                || str_contains(strtolower($shippingMethod), 'servicepoint')
            ) {
                if (preg_match(
                    '/(?:POP|Point)\s*:?\s*([A-Z0-9\-]+)/i',
                    (string)($order->getShippingDescription() ?? ''),
                    $matches
                )) {
                    $popId = $matches[1];
                    $popName = (string)$order->getShippingDescription();
                }
            }
        }

        $isPackstation = false;
        $isPostfiliale = false;
        $postnummer = null;

        if ($shippingAddress && strtoupper($country) === 'DE') {
            $isPackstation = (bool)($shippingAddress->getData('dhl_is_packstation') ?? false);
            $isPostfiliale = (bool)($shippingAddress->getData('dhl_is_postfiliale') ?? false);
            $postnummerVal = (string)($shippingAddress->getData('dhl_postnummer') ?? '');
            $postnummer = $postnummerVal !== '' ? $postnummerVal : null;
        }

        $recipientData = new RecipientData(
            name: $company,
            type: $recipientType,
            firstName: $firstname,
            lastName: $lastname,
            contactPerson: $contactPerson,
            street: $addressParsed['street'],
            houseNumber: $addressParsed['houseNumber'],
            flatNumber: $addressParsed['flatNumber'],
            city: $city,
            postalCode: $postcode,
            country: $country,
            phone: $phone,
            email: $email,
            popId: $popId,
            popName: $popName,
            isPackstation: $isPackstation,
            isPostfiliale: $isPostfiliale,
            postnummer: $postnummer
        );

        // 2. Sender mapping
        $senderData = SenderData::fromArray($defaultSenderDefaults);

        // 3. Weight calculation
        $calculatedWeight = $this->weightAggregator->calculateTotalWeight($order);
        $finalWeight = isset($defaultShipmentDefaults['weight']) && (float)$defaultShipmentDefaults['weight'] > 0
            ? (float)$defaultShipmentDefaults['weight']
            : $calculatedWeight;

        // 4. Resolving dynamic content & template variables
        $rawContent = isset($defaultShipmentDefaults['content']) ? (string)$defaultShipmentDefaults['content'] : '';
        $resolvedContent = $rawContent !== '' ? $this->resolveContentValue($rawContent, $order) : '';

        $commentTemplate = isset($defaultShipmentDefaults['comment']) ? (string)$defaultShipmentDefaults['comment'] : '';
        $referenceTemplate = isset($defaultShipmentDefaults['reference']) ? (string)$defaultShipmentDefaults['reference'] : '';
        $mpkTemplate = isset($defaultShipmentDefaults['mpk']) ? (string)$defaultShipmentDefaults['mpk'] : '';

        $resolvedComment = $commentTemplate !== '' ? $this->variableResolver->resolve($commentTemplate, $order) : '';
        $resolvedReference = $referenceTemplate !== '' ? $this->variableResolver->resolve($referenceTemplate, $order) : '';
        $resolvedMpk = $mpkTemplate !== '' ? $this->variableResolver->resolve($mpkTemplate, $order) : '';

        // 5. Special services & COD detection
        $isCod = false;
        $codAmount = null;
        $shippingMethod = (string)($order->getShippingMethod() ?? '');
        $grandTotal = (float)($order->getGrandTotal() ?? 0.0);

        if ($this->methodCatalog->isCodMethod($shippingMethod)) {
            $isCod = true;
            $codAmount = $grandTotal;
        }

        $presetSS = $defaultShipmentDefaults['specialServices'] ?? [];
        if (is_string($presetSS)) {
            try {
                $decoded = $this->serializer->unserialize($presetSS);
                $presetSS = is_array($decoded) ? $decoded : [];
            } catch (InvalidArgumentException) {
                $presetSS = [];
            }
        }
        if (!is_array($presetSS)) {
            $presetSS = [];
        }

        $isInsurance = !empty($presetSS['insurance']);
        $insuranceValue = isset($presetSS['insuranceValue']) && $presetSS['insuranceValue'] !== null && $presetSS['insuranceValue'] !== ''
            ? (float)$presetSS['insuranceValue']
            : ($isInsurance ? $grandTotal : null);
        $isEvening = !empty($presetSS['deliveryEvening']) || !empty($presetSS['deliveryHours']);
        $isSaturdayDelivery = !empty($presetSS['deliveryOnSaturday']);
        $isSaturdayDispatch = !empty($presetSS['pickupOnSaturday']);
        $isPdi = !empty($presetSS['predeliveryInformation']);
        $isRod = !empty($presetSS['returnOnDelivery']);
        $rodDocumentText = (string)($presetSS['returnOnDeliveryReference'] ?? '');
        $isPod = !empty($presetSS['proofOfDelivery']);
        $isAgever = !empty($presetSS['ageVer']);
        $isOdb = !empty($presetSS['selfCollect']);

        $presetCodAmount = isset($presetSS['collectOnDeliveryValue']) && $presetSS['collectOnDeliveryValue'] !== null && $presetSS['collectOnDeliveryValue'] !== ''
            ? (float)$presetSS['collectOnDeliveryValue']
            : $grandTotal;

        $hasCod = $isCod || !empty($presetSS['collectOnDelivery']);

        $specialServices = new SpecialServicesData(
            deliveryHours: $isEvening ? '1722' : null,
            deliveryEvening: $isEvening,
            deliveryOnSaturday: $isSaturdayDelivery,
            pickupOnSaturday: $isSaturdayDispatch,
            insurance: $isInsurance,
            insuranceValue: $insuranceValue,
            collectOnDelivery: $hasCod,
            collectOnDeliveryValue: $hasCod ? ($codAmount ?? $presetCodAmount) : null,
            predeliveryInformation: $isPdi,
            returnOnDelivery: $isRod,
            returnOnDeliveryReference: $rodDocumentText,
            proofOfDelivery: $isPod,
            ageVer: $isAgever,
            selfCollect: $isOdb
        );

        // 6. Packages
        $packagesList = [];
        $packagesRaw = $defaultShipmentDefaults['packages'] ?? null;
        if (is_string($packagesRaw) && $packagesRaw !== '') {
            try {
                $packagesRaw = $this->serializer->unserialize($packagesRaw);
            } catch (InvalidArgumentException $e) {
                $packagesRaw = [];
            }
        }
        if (!empty($packagesRaw) && is_array($packagesRaw)) {
            foreach ($packagesRaw as $pkgDef) {
                if ($pkgDef instanceof PackageData) {
                    $packagesList[] = $pkgDef;
                } elseif (is_array($pkgDef)) {
                    $packagesList[] = PackageData::fromArray($pkgDef);
                }
            }
        }

        // 7. Shipment Settings Data
        $sWidth = isset($defaultShipmentDefaults['width']) && $defaultShipmentDefaults['width'] !== null
            ? (float)$defaultShipmentDefaults['width']
            : null;
        $sHeight = isset($defaultShipmentDefaults['height']) && $defaultShipmentDefaults['height'] !== null
            ? (float)$defaultShipmentDefaults['height']
            : null;
        $sLength = isset($defaultShipmentDefaults['length']) && $defaultShipmentDefaults['length'] !== null
            ? (float)$defaultShipmentDefaults['length']
            : null;

        $pickupHourFrom = !empty($defaultSenderDefaults['pickup_time_from'])
            ? (string)$defaultSenderDefaults['pickup_time_from']
            : null;
        $pickupHourTo = !empty($defaultSenderDefaults['pickup_time_to'])
            ? (string)$defaultSenderDefaults['pickup_time_to']
            : null;

        $shippingMethod = (string)($order->getShippingMethod() ?? '');
        $serviceType = $this->methodCatalog->getServiceCodeForMethod($shippingMethod);

        $isSpOrPsOrEk = in_array(
            strtoupper(trim((string)$serviceType)),
            [MethodCatalog::SERVICE_SP, MethodCatalog::SERVICE_PS, MethodCatalog::SERVICE_EK],
            true
        );
        if ($isSpOrPsOrEk
            && (empty($popId) || trim((string)$popId) === '')
            && $this->nearestPointResolver !== null
        ) {
            try {
                $recipientData = $this->nearestPointResolver->resolveForRecipient($recipientData);
            } catch (Throwable $e) {
                $this->logger?->warning('Could not resolve nearest point during OrderToShipmentDataMapper::map: ' . $e->getMessage());
            }
        }

        $shipmentSettings = new ShipmentSettingsData(
            serviceType: $serviceType,
            pickupType: (string)($defaultShipmentDefaults['pickupType'] ?? 'COURIER'),
            content: $resolvedContent,
            mpk: (string)($defaultShipmentDefaults['mpk'] ?? $resolvedMpk),
            comment: (string)($defaultShipmentDefaults['comment'] ?? $resolvedComment),
            reference: (string)($defaultShipmentDefaults['reference'] ?? $resolvedReference),
            shipmentDate: $this->timezone->scopeDate()->format('Y-m-d'),
            pickupHourFrom: $pickupHourFrom,
            pickupHourTo: $pickupHourTo,
            packageType: (string)($defaultShipmentDefaults['packageType'] ?? 'PACKAGE'),
            weight: (float)number_format($finalWeight, 4, '.', ''),
            width: $sWidth,
            height: $sHeight,
            length: $sLength,
            labelType: (string)($defaultShipmentDefaults['labelType'] ?? 'BLP'),
            isNonStandard: !empty($defaultShipmentDefaults['isNonStandard']),
            hasEuroReturn: !empty($defaultShipmentDefaults['hasEuroReturn']),
            autoCreateReturnShipment: !empty($defaultShipmentDefaults['autoCreateReturnShipment']),
            returnLabelType: isset($defaultShipmentDefaults['returnLabelType']) && (string)$defaultShipmentDefaults['returnLabelType'] !== '' ? (string)$defaultShipmentDefaults['returnLabelType'] : null,
            returnQrCodeType: isset($defaultShipmentDefaults['returnQrCodeType']) && (string)$defaultShipmentDefaults['returnQrCodeType'] !== '' ? (string)$defaultShipmentDefaults['returnQrCodeType'] : null,
            returnLabelExpDays: isset($defaultShipmentDefaults['returnLabelExpDays']) && $defaultShipmentDefaults['returnLabelExpDays'] !== '' && $defaultShipmentDefaults['returnLabelExpDays'] !== null ? (int)$defaultShipmentDefaults['returnLabelExpDays'] : null,
            returnBookCourier: isset($defaultShipmentDefaults['returnBookCourier']) && $defaultShipmentDefaults['returnBookCourier'] !== '' && $defaultShipmentDefaults['returnBookCourier'] !== null ? (int)$defaultShipmentDefaults['returnBookCourier'] : null,
            addressType: (string)($defaultShipmentDefaults['addressType'] ?? 'N'),
            packages: $packagesList
        );

        return new DhlShipmentData(
            orderId: $order->getEntityId() ? (int)$order->getEntityId() : null,
            sender: $senderData,
            recipient: $recipientData,
            specialServices: $specialServices,
            shipmentSettings: $shipmentSettings,
            orderGrandTotal: $grandTotal
        );
    }
}
