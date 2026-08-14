<?php

declare(strict_types=1);

namespace Ideo\Dhl\Plugin\Quote;

use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\ToOrderAddress;
use Magento\Sales\Api\Data\OrderAddressInterface;

/**
 * Plugin to copy DHL service point attributes from quote address to order address during conversion.
 */
class CopyServicePointToOrderAddress
{
    private const SERVICE_POINT_KEYS = [
        'dhl_service_point_id',
        'dhl_service_point_name',
        'dhl_service_point_street',
        'dhl_service_point_postcode',
        'dhl_service_point_city',
    ];

    /**
     * Copy DHL service point fields after quote address is converted to order address.
     *
     * @param ToOrderAddress $subject
     * @param OrderAddressInterface $result
     * @param QuoteAddress $quoteAddress
     *
     * @return OrderAddressInterface
     */
    public function afterConvert(
        ToOrderAddress $subject,
        OrderAddressInterface $result,
        QuoteAddress $quoteAddress
    ): OrderAddressInterface {
        foreach (self::SERVICE_POINT_KEYS as $key) {
            $value = $quoteAddress->getData($key);
            if ($value !== null) {
                $result->setData($key, $value);
            }
        }

        return $result;
    }
}
