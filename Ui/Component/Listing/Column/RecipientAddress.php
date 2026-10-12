<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Ui\Component\Listing\Column;

use DhlPoland\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class RecipientAddress extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ShipmentDataPayloadReader $payloadReader
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ShipmentDataPayloadReader $payloadReader,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source for recipient address column.
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name') ?: 'recipient_address';

            foreach ($dataSource['data']['items'] as &$item) {
                $item[$fieldName] = $this->formatRecipientAddress($item);
            }
        }

        return $dataSource;
    }

    /**
     * Format recipient address details into clean multi-line HTML.
     *
     * @param array $item
     *
     * @return string
     */
    public function formatRecipientAddress(array $item): string
    {
        $payload = $this->payloadReader->read($item['shipment_data'] ?? null);
        $recipient = $payload['recipient'] ?? null;

        if (!is_array($recipient) || empty($recipient)) {
            return '';
        }

        $lines = [];

        $nameLine = $this->formatNameLine($recipient);
        if ($nameLine !== '') {
            $lines[] = $nameLine;
        }

        $streetAddress = $this->formatStreetAddress($recipient);
        if ($streetAddress !== '') {
            $lines[] = $this->escaper->escapeHtml($streetAddress);
        }

        $cityLine = $this->formatCityLine($recipient);
        if ($cityLine !== '') {
            $lines[] = $this->escaper->escapeHtml($cityLine);
        }

        $pointLine = $this->formatPointLine($recipient);
        if ($pointLine !== '') {
            $lines[] = '<small class="recipient-point">' . $this->escaper->escapeHtml($pointLine) . '</small>';
        }

        return implode('<br/>', $lines);
    }

    /**
     * Format recipient person and company name line.
     *
     * @param array $recipient
     *
     * @return string
     */
    private function formatNameLine(array $recipient): string
    {
        $firstName = $this->getCleanString($recipient['firstName'] ?? null);
        $lastName = $this->getCleanString($recipient['lastName'] ?? null);
        $fullName = trim($firstName . ' ' . $lastName);
        $company = $this->getCleanString($recipient['name'] ?? null);

        if ($fullName !== '' && $company !== '') {
            if (mb_strtolower($fullName) === mb_strtolower($company)) {
                return $this->escaper->escapeHtml($fullName);
            }

            return $this->escaper->escapeHtml($fullName)
                . ' <span class="recipient-company">(' . $this->escaper->escapeHtml($company) . ')</span>';
        }

        if ($fullName !== '') {
            return $this->escaper->escapeHtml($fullName);
        }

        if ($company !== '') {
            return $this->escaper->escapeHtml($company);
        }

        return '';
    }

    /**
     * Format street address with house and flat number.
     *
     * @param array $recipient
     *
     * @return string
     */
    private function formatStreetAddress(array $recipient): string
    {
        $street = $this->getCleanString($recipient['street'] ?? null);
        $houseNumber = $this->getCleanString($recipient['houseNumber'] ?? null);
        $flatNumber = $this->getCleanString($recipient['flatNumber'] ?? null);

        if ($street === '') {
            if ($houseNumber !== '') {
                return $houseNumber . ($flatNumber !== '' ? '/' . $flatNumber : '');
            }
            return '';
        }

        $address = $street;
        if ($houseNumber !== '') {
            $address .= ' ' . $houseNumber;
            if ($flatNumber !== '') {
                $address .= '/' . $flatNumber;
            }
        } elseif ($flatNumber !== '') {
            $address .= '/' . $flatNumber;
        }

        return $address;
    }

    /**
     * Format postal code, city and country code line.
     *
     * @param array $recipient
     *
     * @return string
     */
    private function formatCityLine(array $recipient): string
    {
        $postalCode = $this->getCleanString($recipient['postalCode'] ?? null);
        $city = $this->getCleanString($recipient['city'] ?? null);
        $country = strtoupper($this->getCleanString($recipient['country'] ?? null));

        $parts = [];
        if ($postalCode !== '' && $city !== '') {
            $parts[] = $postalCode . ' ' . $city;
        } elseif ($city !== '') {
            $parts[] = $city;
        } elseif ($postalCode !== '') {
            $parts[] = $postalCode;
        }

        if ($country !== '') {
            $parts[] = $country;
        }

        return implode(', ', $parts);
    }

    /**
     * Format delivery point / packstation details.
     *
     * @param array $recipient
     *
     * @return string
     */
    private function formatPointLine(array $recipient): string
    {
        $popId = $this->getCleanString($recipient['popId'] ?? null);
        $isPackstation = !empty($recipient['isPackstation']);
        $postnummer = $this->getCleanString($recipient['postnummer'] ?? null);

        if ($popId === '' && $postnummer === '') {
            return '';
        }

        $pointParts = [];
        if ($isPackstation) {
            if ($popId !== '') {
                $pointParts[] = __('Packstation: %1', $popId)->render();
            }
            if ($postnummer !== '') {
                $pointParts[] = __('Postnummer: %1', $postnummer)->render();
            }
        } elseif ($popId !== '') {
            $popName = $this->getCleanString($recipient['popName'] ?? null);
            if ($popName !== '' && $popName !== $popId) {
                $pointParts[] = __('POP: %1 (%2)', $popId, $popName)->render();
            } else {
                $pointParts[] = __('POP: %1', $popId)->render();
            }
        }

        return implode(' | ', $pointParts);
    }

    /**
     * Safely extract a clean trimmed string from a scalar or array value.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function getCleanString(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string)$value);
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_string($item) || is_numeric($item)) {
                    $trimmed = trim((string)$item);
                    if ($trimmed !== '') {
                        $parts[] = $trimmed;
                    }
                }
            }
            return implode(' ', $parts);
        }

        return '';
    }
}
