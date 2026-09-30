<?php

declare(strict_types=1);

namespace Ideo\Dhl\Plugin\Checkout;

use Ideo\Dhl\Model\Carrier;
use Ideo\Dhl\Model\MethodCatalog;
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
        $servicePoint = $this->extractPointFromExtensionAttributes($extAttributes, $shippingAddress);

        $cleanMethodCode = strtolower(trim($methodCode));
        if (str_starts_with($cleanMethodCode, Carrier::CODE_PREFIX)) {
            $cleanMethodCode = substr($cleanMethodCode, strlen(Carrier::CODE_PREFIX));
        }
        $isPackstationMethod = in_array(
            $cleanMethodCode,
            [
                MethodCatalog::METHOD_CONNECT_PACKSTATION,
                MethodCatalog::METHOD_CONNECT_PACKSTATION_COD,
            ],
            true
        );

        $isParcelShopMethod = in_array(
            $cleanMethodCode,
            [
                MethodCatalog::METHOD_CONNECT_PARCELSHOP,
                MethodCatalog::METHOD_CONNECT_PARCELSHOP_COD,
            ],
            true
        );

        $isPointMethod = $isPackstationMethod || $isParcelShopMethod || in_array(
            $cleanMethodCode,
            [
                MethodCatalog::METHOD_ECONOMY_ALL,
                MethodCatalog::METHOD_ECONOMY_ALL_COD,
                MethodCatalog::METHOD_ECONOMY_POP,
                MethodCatalog::METHOD_ECONOMY_POP_COD,
                MethodCatalog::METHOD_ECONOMY_BOX,
                MethodCatalog::METHOD_ECONOMY_BOX_COD,
            ],
            true
        );

        if (!$isPointMethod) {
            $servicePoint = null;
        }

        if ($servicePoint !== null) {
            $isPackstation = $isPackstationMethod;
            $isPostfiliale = $isParcelShopMethod;

            if ($servicePoint->isPackstation() !== $isPackstation || $servicePoint->isPostfiliale() !== $isPostfiliale) {
                $servicePoint = new ServicePointValueObject(
                    $servicePoint->getId(),
                    $servicePoint->getName(),
                    $servicePoint->getStreet(),
                    $servicePoint->getPostcode(),
                    $servicePoint->getCity(),
                    $isPackstation,
                    $isPostfiliale,
                    $servicePoint->getPostnummer(),
                    $servicePoint->getCountry()
                );
            }
        }

        // Validate selection requirement
        $this->validator->validate($carrierCode, $methodCode, $servicePoint);

        if ($servicePoint && $servicePoint->isValid()) {
            $shippingAddress->setData('dhl_service_point_id', $servicePoint->getId());
            $shippingAddress->setData('dhl_service_point_name', $servicePoint->getName());
            $shippingAddress->setData('dhl_service_point_street', $servicePoint->getStreet());
            $shippingAddress->setData('dhl_service_point_postcode', $servicePoint->getPostcode());
            $shippingAddress->setData('dhl_service_point_city', $servicePoint->getCity());
            $shippingAddress->setData('dhl_is_packstation', $servicePoint->isPackstation() ? 1 : 0);
            $shippingAddress->setData('dhl_is_postfiliale', $servicePoint->isPostfiliale() ? 1 : 0);
            $shippingAddress->setData('dhl_postnummer', $servicePoint->getPostnummer());
        } else {
            $shippingAddress->setData('dhl_service_point_id', null);
            $shippingAddress->setData('dhl_service_point_name', null);
            $shippingAddress->setData('dhl_service_point_street', null);
            $shippingAddress->setData('dhl_service_point_postcode', null);
            $shippingAddress->setData('dhl_service_point_city', null);
            $shippingAddress->setData('dhl_is_packstation', 0);
            $shippingAddress->setData('dhl_is_postfiliale', 0);
            $shippingAddress->setData('dhl_postnummer', null);
        }

        return [$cartId, $addressInformation];
    }

    /**
     * Extract ServicePointValueObject from extension attributes interface.
     *
     * @param mixed $extAttributes
     * @param mixed $shippingAddress
     *
     * @return ServicePointValueObject|null
     */
    private function extractPointFromExtensionAttributes(
        mixed $extAttributes,
        mixed $shippingAddress = null
    ): ?ServicePointValueObject {
        if (!$extAttributes && (!$shippingAddress || !is_object($shippingAddress))) {
            return null;
        }

        $id = '';
        $name = '';
        $street = '';
        $postcode = '';
        $city = '';
        $isPackstation = false;
        $isPostfiliale = false;
        $postnummer = null;

        if (is_object($extAttributes)) {
            $id = (string) (is_callable([$extAttributes, 'getDhlServicePointId']) ? $extAttributes->getDhlServicePointId() : ($extAttributes->dhl_service_point_id ?? ''));
            $name = (string) (is_callable([$extAttributes, 'getDhlServicePointName']) ? $extAttributes->getDhlServicePointName() : ($extAttributes->dhl_service_point_name ?? ''));
            $street = (string) (is_callable([$extAttributes, 'getDhlServicePointStreet']) ? $extAttributes->getDhlServicePointStreet() : ($extAttributes->dhl_service_point_street ?? ''));
            $postcode = (string) (is_callable([$extAttributes, 'getDhlServicePointPostcode']) ? $extAttributes->getDhlServicePointPostcode() : ($extAttributes->dhl_service_point_postcode ?? ''));
            $city = (string) (is_callable([$extAttributes, 'getDhlServicePointCity']) ? $extAttributes->getDhlServicePointCity() : ($extAttributes->dhl_service_point_city ?? ''));
            $isPackstation = (bool) (is_callable([$extAttributes, 'getDhlIsPackstation']) ? $extAttributes->getDhlIsPackstation() : ($extAttributes->dhl_is_packstation ?? false));
            $isPostfiliale = (bool) (is_callable([$extAttributes, 'getDhlIsPostfiliale']) ? $extAttributes->getDhlIsPostfiliale() : ($extAttributes->dhl_is_postfiliale ?? false));
            $postnummerStr = (string) (is_callable([$extAttributes, 'getDhlPostnummer']) ? $extAttributes->getDhlPostnummer() : ($extAttributes->dhl_postnummer ?? ''));
            $postnummer = $postnummerStr !== '' ? $postnummerStr : null;
        }

        if (trim($id) === '' && !$extAttributes && $shippingAddress && is_object($shippingAddress) && method_exists($shippingAddress, 'getData')) {
            $id = (string) ($shippingAddress->getData('dhl_service_point_id') ?? '');
            $name = (string) ($shippingAddress->getData('dhl_service_point_name') ?? '');
            $street = (string) ($shippingAddress->getData('dhl_service_point_street') ?? '');
            $postcode = (string) ($shippingAddress->getData('dhl_service_point_postcode') ?? '');
            $city = (string) ($shippingAddress->getData('dhl_service_point_city') ?? '');
            $isPackstation = (bool) ($shippingAddress->getData('dhl_is_packstation') ?? false);
            $isPostfiliale = (bool) ($shippingAddress->getData('dhl_is_postfiliale') ?? false);
            $postnummerStr = (string) ($shippingAddress->getData('dhl_postnummer') ?? '');
            $postnummer = $postnummerStr !== '' ? $postnummerStr : null;
        }

        if (trim($id) === '') {
            return null;
        }

        $country = '';
        if ($shippingAddress && is_object($shippingAddress)) {
            if (method_exists($shippingAddress, 'getCountryId')) {
                $country = (string) $shippingAddress->getCountryId();
            }
        }

        return new ServicePointValueObject(
            $id,
            $name,
            $street,
            $postcode,
            $city,
            $isPackstation,
            $isPostfiliale,
            $postnummer,
            $country
        );
    }
}
