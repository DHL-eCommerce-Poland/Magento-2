<?php

declare(strict_types=1);

namespace Ideo\Dhl\Service\Customs;

use Exception;
use Ideo\Dhl\Model\CustomsTemplate;
use Ideo\Dhl\Model\CustomsTemplateRepository;
use Ideo\Dhl\Model\Source\CustomsType;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\SerializerInterface;

class CustomsDataBuilder
{
    /**
     * @param CustomsTemplateRepository $templateRepository
     * @param SerializerInterface $serializer
     * @param FileDriver $fileDriver
     */
    public function __construct(
        private readonly CustomsTemplateRepository $templateRepository,
        private readonly SerializerInterface $serializer,
        private readonly FileDriver $fileDriver
    ) {
    }

    /**
     * Build CustomsData array structure from customs template.
     *
     * @param int|null $customsTemplateId
     *
     * @return array
     * @throws LocalizedException
     */
    public function buildCustomsData(?int $customsTemplateId = null): array
    {
        $template = $this->resolveTemplate($customsTemplateId);
        $customsType = $template->getCustomsType() ?: CustomsType::TYPE_SIMPLIFIED;

        $customsData = [
            'customsType' => $customsType,
            'costsOfShipment' => (int)round((float)$template->getCostsOfShipment()),
            'currency' => (string)$template->getCurrency(),
            'categoryOfItem' => (string)$template->getCategoryOfItem(),
            'countryOfOrigin' => (string)$template->getCountryOfOrigin(),
            'grossWeight' => (float)$template->getGrossWeight(),
            'firstName' => (string)$template->getContactFirstName(),
            'secondaryName' => (string)$template->getContactLastName(),
            'shipperEori' => (string)$template->getShipperEori(),
            'shipperNip' => (string)$template->getShipperNip(),
            'vatRegistrationNumber' => (string)$template->getShipperVatNo(),
        ];

        if (!empty($template->getAdditionalInfo())) {
            $customsData['additionalInfo'] = $template->getAdditionalInfo();
        }

        if (!empty($template->getReceiverEori())) {
            $customsData['eoriNrReceiver'] = $template->getReceiverEori();
        }

        if ($customsType === CustomsType::TYPE_INDIVIDUAL) {
            $customsData = array_merge($customsData, $this->buildIndividualInvoiceData($template));
        }

        $customsData['customsItem'] = $this->buildCustomsItems($template);
        $customsData['customAgreements'] = $this->buildCustomsAgreements($customsType, $template);

        return $customsData;
    }

    /**
     * Resolve customs template to use.
     *
     * @param int|null $customsTemplateId
     *
     * @return CustomsTemplate
     * @throws LocalizedException
     */
    private function resolveTemplate(?int $customsTemplateId): CustomsTemplate
    {
        if ($customsTemplateId) {
            try {
                return $this->templateRepository->getById($customsTemplateId);
            } catch (Exception $e) {
                // Fall back to default template if specified ID is missing
                unset($e);
            }
        }

        $defaultTemplate = $this->templateRepository->getDefaultTemplate();
        if (!$defaultTemplate) {
            throw new LocalizedException(
                __(
                    'No Customs Clearance configuration found for international shipment. '
                    . 'Please configure default Customs.'
                )
            );
        }

        return $defaultTemplate;
    }

    /**
     * Build invoice PDF data for Individual Clearance (I).
     *
     * @param CustomsTemplate $template
     *
     * @return array
     * @throws LocalizedException
     */
    private function buildIndividualInvoiceData(CustomsTemplate $template): array
    {
        $base64Invoice = '';
        if (!empty($template->getInvoiceFile())) {
            $filePath = $template->getInvoiceFile();
            if ($this->fileDriver->isExists($filePath)) {
                $base64Invoice = base64_encode($this->fileDriver->fileGetContents($filePath));
            }
        }

        if (empty($base64Invoice)) {
            throw new LocalizedException(
                __(
                    'A VAT Invoice PDF file is required for individual customs clearance (I). '
                    . 'Please upload invoice in Customs Template.'
                )
            );
        }

        $invoiceDate = !empty($template->getInvoiceDate())
            ? $template->getInvoiceDate()
            : date('Y-m-d');

        $data = [
            'invoiceNr' => 'INV-' . date('Ymd'),
            'invoiceDate' => $invoiceDate,
            'invoice' => $base64Invoice,
            'nipNr' => (string)$template->getShipperNip(),
            'eoriNr' => (string)$template->getShipperEori(),
            'vatRegistrationNumberReceiver' => (string)$template->getReceiverVatNo(),
        ];

        if (!empty($template->getReceiverEori())) {
            $data['eoriNrReceiver'] = $template->getReceiverEori();
        }

        return $data;
    }

    /**
     * Build customs item list directly from template.
     *
     * @param CustomsTemplate $template
     *
     * @return array
     */
    private function buildCustomsItems(CustomsTemplate $template): array
    {
        $templateItems = $template->getCustomsItems();
        if (is_string($templateItems) && !empty($templateItems)) {
            $decoded = $this->serializer->unserialize($templateItems);
            if (is_array($decoded) && !empty($decoded)) {
                $defaultTariffCode = (string)($template->getDefaultTariffCode() ?? '');
                $countryOfOrigin = (string)($template->getCountryOfOrigin() ?: 'PL');

                return array_map(
                    function ($item) use ($defaultTariffCode, $countryOfOrigin) {
                        $itemTariffCode = !empty($item['tariffCode'])
                            ? (string)$item['tariffCode']
                            : $defaultTariffCode;
                        $itemCountryOfOrigin = !empty($item['countryOfOrigin'])
                            ? (string)$item['countryOfOrigin']
                            : $countryOfOrigin;

                        return [
                            'namePl' => mb_substr((string)($item['namePl'] ?? ''), 0, 35),
                            'nameEn' => mb_substr((string)($item['nameEn'] ?? ''), 0, 35),
                            'quantity' => (float)($item['quantity'] ?? 1.0),
                            'weight' => (float)($item['weight'] ?? 0.0),
                            'value' => (float)($item['value'] ?? 0.0),
                            'tariffCode' => mb_substr($itemTariffCode, 0, 10),
                            'countryOfOrigin' => mb_substr($itemCountryOfOrigin, 0, 35),
                        ];
                    },
                    $decoded
                );
            }
        }

        return [];
    }

    /**
     * Build customs agreement data.
     *
     * @param string $customsType
     * @param CustomsTemplate $template
     *
     * @return array
     */
    private function buildCustomsAgreements(string $customsType, CustomsTemplate $template): array
    {
        $notExceed = $template->getNotExceedValue() !== null
            ? (bool)$template->getNotExceedValue()
            : ($customsType === CustomsType::TYPE_SIMPLIFIED);

        $notProhibited = !($template->getNotProhibitedGoods() !== null) || (bool)$template->getNotProhibitedGoods();
        $notRestricted = !($template->getNotRestrictedGoods() !== null) || (bool)$template->getNotRestrictedGoods();

        return [
            'notExceedValue' => $notExceed,
            'notProhibitedGoods' => $notProhibited,
            'notRestrictedGoods' => $notRestricted,
            'invoiceContent' => ($customsType === CustomsType::TYPE_INDIVIDUAL),
        ];
    }
}
