<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\Shipment\Data\PackageData;
use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Ideo\Dhl\Model\Shipment\Data\SenderData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;
use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
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
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly StreetAddressParser $streetAddressParser,
        private readonly OrderWeightAggregator $weightAggregator,
        private readonly TemplateVariableResolver $variableResolver,
        private readonly SerializerInterface $serializer,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Resolve text value for Package Contents Description based on template setting and order data.
     *
     * @param string|null $contentSetting
     * @param OrderInterface $order
     *
     * @return string
     */
    public function resolveContentValue(?string $contentSetting, OrderInterface $order): string
    {
        if (empty($contentSetting)) {
            return $this->variableResolver->resolve('{product_names}', $order);
        }

        return match ($contentSetting) {
            'ORDER_NUMBER' => $this->variableResolver->resolve('{order_increment_id}', $order),
            'ORDER_ID' => (string)$order->getEntityId(),
            'INVOICE_NUMBER' => $this->getInvoiceNumberFromOrder($order),
            'STATIC_VALUE' => '',
            default => $this->variableResolver->resolve($contentSetting, $order),
        };
    }

    /**
     * Extract first invoice increment ID from order if available.
     *
     * @param OrderInterface $order
     *
     * @return string
     */
    private function getInvoiceNumberFromOrder(OrderInterface $order): string
    {
        try {
            $invoices = $order->getInvoiceCollection();
            if ($invoices && $invoices->getSize() > 0) {
                $firstInvoice = $invoices->getFirstItem();
                return (string)($firstInvoice->getIncrementId() ?? '');
            }
        } catch (Throwable $e) {
            $this->logger?->warning(
                'Error fetching invoice collection for order: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
        return '';
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
            popName: $popName
        );

        // 2. Sender mapping
        $senderData = SenderData::fromArray($defaultSenderDefaults);

        // 3. Weight calculation
        $calculatedWeight = $this->weightAggregator->calculateTotalWeight($order);
        $finalWeight = isset($defaultShipmentDefaults['weight']) && (float)$defaultShipmentDefaults['weight'] > 0
            ? (float)$defaultShipmentDefaults['weight']
            : $calculatedWeight;

        // 4. Resolving dynamic content & template variables
        $rawContent = (string)($defaultShipmentDefaults['content'] ?? 'ORDER_NUMBER');
        $resolvedContent = $this->resolveContentValue($rawContent, $order);

        $commentTemplate = (string)($defaultShipmentDefaults['comment'] ?? '{customer_name}');
        $referenceTemplate = (string)($defaultShipmentDefaults['reference'] ?? '{order_increment_id}');
        $mpkTemplate = (string)($defaultShipmentDefaults['mpk'] ?? '');

        $resolvedComment = $this->variableResolver->resolve($commentTemplate, $order);
        $resolvedReference = $this->variableResolver->resolve($referenceTemplate, $order);
        $resolvedMpk = $this->variableResolver->resolve($mpkTemplate, $order);

        // 5. Special services & COD detection
        $isCod = false;
        $codAmount = null;
        $paymentCode = strtolower((string)($order->getPayment()?->getMethod() ?? ''));
        $paymentTitle = strtolower((string)($order->getPayment()?->getAdditionalInformation('method_title') ?? ''));
        if (str_contains($paymentCode, 'cod')
            || str_contains($paymentCode, 'cashondelivery')
            || str_contains($paymentTitle, 'pobrani')
        ) {
            $isCod = true;
            $codAmount = (float)$order->getGrandTotal();
        }

        $presetSS = $defaultShipmentDefaults['specialServices'] ?? [];
        if (is_string($presetSS)) {
            try {
                $decoded = $this->serializer->unserialize($presetSS);
                $presetSS = is_array($decoded) ? $decoded : [];
            } catch (InvalidArgumentException $e) {
                $presetSS = [];
            }
        }
        if (!is_array($presetSS)) {
            $presetSS = [];
        }

        $isInsurance = !empty($presetSS['insurance']) || !empty($presetSS['service_UBEZP']);
        $insuranceValue = isset($presetSS['insuranceValue'])
            ? (float)$presetSS['insuranceValue']
            : (isset($presetSS['serviceValueUbez']) ? (float)$presetSS['serviceValueUbez'] : null);
        $isEvening = !empty($presetSS['eveningDelivery'])
            || !empty($presetSS['deliveryHours'])
            || !empty($presetSS['service_1722']);
        $isSaturdayDelivery = !empty($presetSS['saturdayDelivery']) || !empty($presetSS['service_SOBOTA']);
        $isSaturdayDispatch = !empty($presetSS['saturdayDispatch'])
            || !empty($presetSS['pickupOnSaturday'])
            || !empty($presetSS['service_NAD_SOBOTA']);
        $isPdi = !empty($presetSS['pdi'])
            || !empty($presetSS['predeliveryInformation'])
            || !empty($presetSS['service_PDI']);
        $isRod = !empty($presetSS['rod'])
            || !empty($presetSS['returnOnDelivery'])
            || !empty($presetSS['service_ROD']);
        $rodDocumentText = (string)($presetSS['rodDocumentText'] ?? $presetSS['textInstruction'] ?? '');
        $isPod = !empty($presetSS['pod'])
            || !empty($presetSS['proofOfDelivery'])
            || !empty($presetSS['service_POD']);
        $isAgever = !empty($presetSS['agever']) || !empty($presetSS['service_AGEVER']);
        $isOdb = !empty($presetSS['odb']) || !empty($presetSS['service_ODB']);

        $presetCodAmount = isset($presetSS['codAmount'])
            ? (float)$presetSS['codAmount']
            : (isset($presetSS['serviceValueCOD']) ? (float)$presetSS['serviceValueCOD'] : null);

        $specialServices = new SpecialServicesData(
            deliveryHours: $isEvening ? '1722' : null,
            saturdayDelivery: $isSaturdayDelivery,
            saturdayDispatch: $isSaturdayDispatch,
            insurance: $isInsurance,
            insuranceValue: $insuranceValue,
            cod: $isCod || !empty($presetSS['cod']) || !empty($presetSS['service_COD']),
            codAmount: $isCod ? $codAmount : $presetCodAmount,
            codCurrency: (string)($order->getOrderCurrencyCode() ?? 'PLN'),
            pdi: $isPdi,
            rod: $isRod,
            rodDocumentText: $rodDocumentText,
            pod: $isPod,
            agever: $isAgever,
            odb: $isOdb
        );

        // 6. Packages
        $widthVal = isset($defaultShipmentDefaults['width']) && $defaultShipmentDefaults['width'] !== null
            ? (float)number_format((float)$defaultShipmentDefaults['width'], 4, '.', '')
            : null;
        $lengthVal = isset($defaultShipmentDefaults['length']) && $defaultShipmentDefaults['length'] !== null
            ? (float)number_format((float)$defaultShipmentDefaults['length'], 4, '.', '')
            : null;
        $heightVal = isset($defaultShipmentDefaults['height']) && $defaultShipmentDefaults['height'] !== null
            ? (float)number_format((float)$defaultShipmentDefaults['height'], 4, '.', '')
            : null;

        $initialPackage = new PackageData(
            packageType: (string)($defaultShipmentDefaults['packageType'] ?? 'PACKAGE'),
            quantity: 1,
            weight: (float)number_format($finalWeight, 4, '.', ''),
            width: $widthVal,
            length: $lengthVal,
            height: $heightVal,
            isNonStandard: !empty($defaultShipmentDefaults['isNonStandard']),
            hasEuroReturn: !empty($defaultShipmentDefaults['hasEuroReturn'])
        );

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
        $sDropOff = isset($defaultShipmentDefaults['dropOffPointId'])
            ? (int)$defaultShipmentDefaults['dropOffPointId']
            : null;

        $shipmentSettings = new ShipmentSettingsData(
            serviceType: (string)($defaultShipmentDefaults['serviceType'] ?? 'AH'),
            pickupType: (string)($defaultShipmentDefaults['pickupType'] ?? 'COURIER'),
            content: $resolvedContent,
            mpk: (string)($defaultShipmentDefaults['mpk'] ?? $resolvedMpk),
            comment: (string)($defaultShipmentDefaults['comment'] ?? $resolvedComment),
            reference: (string)($defaultShipmentDefaults['reference'] ?? $resolvedReference),
            dispatchDate: date('Y-m-d'),
            packageType: (string)($defaultShipmentDefaults['packageType'] ?? 'PACKAGE'),
            weight: (float)number_format($finalWeight, 4, '.', ''),
            width: $sWidth,
            height: $sHeight,
            length: $sLength,
            labelType: (string)($defaultShipmentDefaults['labelType'] ?? 'BLP'),
            isNonStandard: !empty($defaultShipmentDefaults['isNonStandard']),
            hasEuroReturn: !empty($defaultShipmentDefaults['hasEuroReturn']),
            autoCreateReturnShipment: !empty($defaultShipmentDefaults['autoCreateReturnShipment']),
            dropOffPointId: $sDropOff,
            addressType: (string)($defaultShipmentDefaults['addressType'] ?? 'N'),
            packages: [$initialPackage]
        );

        return new DhlShipmentData(
            orderId: $order->getEntityId() ? (int)$order->getEntityId() : null,
            sender: $senderData,
            recipient: $recipientData,
            specialServices: $specialServices,
            shipmentSettings: $shipmentSettings
        );
    }
}
