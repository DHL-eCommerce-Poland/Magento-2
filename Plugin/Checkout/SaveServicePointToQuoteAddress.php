<?php

declare(strict_types=1);

namespace Ideo\Dhl\Plugin\Checkout;

use Ideo\Dhl\Model\ServicePoint\ServicePointValidator;
use Ideo\Dhl\Model\ServicePoint\ServicePointValueObject;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Plugin to validate and save DHL service point data to quote address before address information is saved.
 */
class SaveServicePointToQuoteAddress
{
    /**
     * @param ServicePointValidator $validator
     */
    public function __construct(
        private readonly ServicePointValidator $validator
    ) {
    }

    /**
     * Intercept saveAddressInformation to validate and attach DHL service point data to shipping address.
     *
     * @param ShippingInformationManagementInterface $subject
     * @param int|string $cartId
     * @param ShippingInformationInterface $addressInformation
     *
     * @throws LocalizedException
     *
     * @return array
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        int|string $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $shippingAddress = $addressInformation->getShippingAddress();
        if (!$shippingAddress) {
            return [$cartId, $addressInformation];
        }

        $carrierCode = (string) $addressInformation->getShippingCarrierCode();
        $methodCode = (string) $addressInformation->getShippingMethodCode();

        $extAttributes = $shippingAddress->getExtensionAttributes();
        $servicePoint = $this->extractPointFromExtensionAttributes($extAttributes);

        // Validate selection requirement
        $this->validator->validate($carrierCode, $methodCode, $servicePoint);

        if ($servicePoint && $servicePoint->isValid()) {
            $shippingAddress->setData('dhl_service_point_id', $servicePoint->getId());
            $shippingAddress->setData('dhl_service_point_name', $servicePoint->getName());
            $shippingAddress->setData('dhl_service_point_street', $servicePoint->getStreet());
            $shippingAddress->setData('dhl_service_point_postcode', $servicePoint->getPostcode());
            $shippingAddress->setData('dhl_service_point_city', $servicePoint->getCity());
        } else {
            $shippingAddress->setData('dhl_service_point_id', null);
            $shippingAddress->setData('dhl_service_point_name', null);
            $shippingAddress->setData('dhl_service_point_street', null);
            $shippingAddress->setData('dhl_service_point_postcode', null);
            $shippingAddress->setData('dhl_service_point_city', null);
        }

        return [$cartId, $addressInformation];
    }

    /**
     * Extract ServicePointValueObject from extension attributes interface.
     *
     * @param mixed $extAttributes
     *
     * @return ServicePointValueObject|null
     */
    private function extractPointFromExtensionAttributes(mixed $extAttributes): ?ServicePointValueObject
    {
        if (!$extAttributes) {
            return null;
        }

        $id = '';
        $name = '';
        $street = '';
        $postcode = '';
        $city = '';

        if (is_object($extAttributes)) {
            $id = $this->getAttrValue($extAttributes, 'getDhlServicePointId');
            $name = $this->getAttrValue($extAttributes, 'getDhlServicePointName');
            $street = $this->getAttrValue($extAttributes, 'getDhlServicePointStreet');
            $postcode = $this->getAttrValue($extAttributes, 'getDhlServicePointPostcode');
            $city = $this->getAttrValue($extAttributes, 'getDhlServicePointCity');
        }

        if (trim($id) === '') {
            return null;
        }

        return new ServicePointValueObject($id, $name, $street, $postcode, $city);
    }

    /**
     * Read attribute value if method exists or is callable.
     *
     * @param object $object
     * @param string $method
     *
     * @return string
     */
    private function getAttrValue(object $object, string $method): string
    {
        if (method_exists($object, $method) || is_callable([$object, $method])) {
            return (string) $object->$method();
        }

        return '';
    }
}
