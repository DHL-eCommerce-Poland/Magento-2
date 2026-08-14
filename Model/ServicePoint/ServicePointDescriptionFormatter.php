<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ServicePoint;

class ServicePointDescriptionFormatter
{
    private const MAX_LENGTH = 255;

    /**
     * Format shipping description by appending localized service point details and truncating to max 255 characters.
     *
     * @param string $baseDescription
     * @param ServicePointValueObject $point
     *
     * @return string
     */
    public function format(string $baseDescription, ServicePointValueObject $point): string
    {
        if (!$point->isValid()) {
            return $baseDescription;
        }

        $addressParts = array_filter([
            $point->getStreet(),
            trim($point->getPostcode() . ' ' . $point->getCity()),
        ]);
        $formattedAddress = implode(', ', $addressParts);

        $pointDetails = sprintf(
            '%s (%s)',
            $point->getName() ?: $point->getId(),
            $point->getId()
        );

        if ($formattedAddress !== '') {
            $pointDetails .= ', ' . $formattedAddress;
        }

        $fullDescription = sprintf(
            '%s (%s: %s)',
            $baseDescription,
            __('Pickup Point'),
            $pointDetails
        );

        if (mb_strlen($fullDescription) <= self::MAX_LENGTH) {
            return $fullDescription;
        }

        return mb_strimwidth($fullDescription, 0, self::MAX_LENGTH, '...');
    }
}
