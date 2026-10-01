<?php

declare(strict_types=1);

namespace Ideo\Dhl\Plugin\Quote;

use Ideo\Dhl\Model\ServicePoint\ServicePointDescriptionFormatter;
use Ideo\Dhl\Model\ServicePoint\ServicePointValidator;
use Ideo\Dhl\Model\ServicePoint\ServicePointValueObject;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\ToOrder;
use Magento\Sales\Model\Order;

/**
 * Plugin to enrich order shipping description with localized service point details.
 */
class EnrichOrderShippingDescription
{
    /**
     * @param ServicePointValidator $validator
     * @param ServicePointDescriptionFormatter $formatter
     */
    public function __construct(
        private readonly ServicePointValidator $validator,
        private readonly ServicePointDescriptionFormatter $formatter
    ) {
    }

    /**
     * Enrich shipping description after quote address is converted to order.
     *
     * @param ToOrder $subject
     * @param Order $result
     * @param QuoteAddress $quoteAddress
     *
     * @return Order
     */
    public function afterConvert(
        ToOrder $subject,
        Order $result,
        QuoteAddress $quoteAddress
    ): Order {
        $shippingMethod = (string) ($quoteAddress->getShippingMethod()
            ?? $quoteAddress->getData('shipping_method')
            ?? '');

        if ($shippingMethod === '') {
            return $result;
        }

        $parts = explode('_', $shippingMethod, 2);
        $carrierCode = (string) ($quoteAddress->getData('shipping_carrier_code')
            ?? $quoteAddress->getShippingCarrierCode()
            ?? $parts[0]
            ?? '');

        if ($carrierCode !== 'dhlpoland') {
            return $result;
        }

        $methodCode = $parts[1] ?? $shippingMethod;

        $postnummer = (string) ($quoteAddress->getData('dhl_postnummer') ?? '');
        $country = (string) ($quoteAddress->getCountryId() ?? '');

        $servicePoint = new ServicePointValueObject(
            (string) ($quoteAddress->getData('dhl_service_point_id') ?? ''),
            (string) ($quoteAddress->getData('dhl_service_point_name') ?? ''),
            (string) ($quoteAddress->getData('dhl_service_point_street') ?? ''),
            (string) ($quoteAddress->getData('dhl_service_point_postcode') ?? ''),
            (string) ($quoteAddress->getData('dhl_service_point_city') ?? ''),
            (bool) ($quoteAddress->getData('dhl_is_packstation') ?? false),
            (bool) ($quoteAddress->getData('dhl_is_postfiliale') ?? false),
            $postnummer !== '' ? $postnummer : null,
            $country
        );

        if (!$this->validator->isPointSelectionRequired($carrierCode, $methodCode)
            || !$servicePoint->isValid()
        ) {
            return $result;
        }

        $baseDescription = (string) ($result->getShippingDescription() ?? '');
        $enrichedDescription = $this->formatter->format($baseDescription, $servicePoint);

        if ($enrichedDescription !== $baseDescription) {
            $result->setShippingDescription($enrichedDescription);
        }

        return $result;
    }
}
