<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Adminhtml;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class CustomsAgreementUiTest extends TestCase
{
    /**
     * Verify the agreement is hidden outside individual customs clearance in each admin UI.
     *
     * @return void
     */
    public function testAgreementIsConditionalInAdminFormsAndPreparationModal(): void
    {
        $modulePath = dirname(__DIR__, 3);
        $templateForm = file_get_contents(
            $modulePath . '/view/adminhtml/ui_component/dhlpoland_customs_template_form.xml'
        );
        $shipmentForm = file_get_contents($modulePath . '/view/adminhtml/ui_component/dhlpoland_shipment_form.xml');
        $prepareModal = file_get_contents($modulePath . '/view/adminhtml/templates/shipment/prepare_modal.phtml');
        $prepareModalJs = file_get_contents($modulePath . '/view/adminhtml/web/js/shipment/prepare-modal.js');

        $this->assertIsString($templateForm);
        $this->assertSwitcherControlsVisibility(
            $templateForm,
            'customs_type',
            'dhlpoland_customs_template_form.dhlpoland_customs_template_form.agreements.invoice_content_agreement'
        );
        $this->assertIsString($shipmentForm);
        $this->assertSwitcherControlsVisibility(
            $shipmentForm,
            'customs[customsType]',
            'dhlpoland_shipment_form.dhlpoland_shipment_form.customs_fieldset.invoice_content_agreement'
        );
        $this->assertIsString($prepareModal);
        $this->assertStringContainsString("<!-- ko if: customsType() === 'I' -->", $prepareModal);
        $this->assertStringContainsString('checked: invoiceContent', $prepareModal);
        $this->assertIsString($prepareModalJs);
        $this->assertStringContainsString('invoiceContent: ko.observable', $prepareModalJs);
    }

    /**
     * Verify the agreement has the requested Polish and English translations.
     *
     * @return void
     */
    public function testAgreementLabelHasPolishAndEnglishTranslations(): void
    {
        $modulePath = dirname(__DIR__, 3);
        $this->assertSame(
            'Oświadczam, że dane na załączonej fakturze są zgodne z danymi celnymi wprowadzonymi w celu ' .
            'wygenerowania przesyłki',
            $this->getTranslation($modulePath . '/i18n/pl_PL.csv')
        );
        $this->assertSame(
            'I declare that the information on the attached invoice is consistent with the customs data entered ' .
            'to generate the shipment',
            $this->getTranslation($modulePath . '/i18n/en_US.csv')
        );
    }

    /**
     * Read the English-keyed translation for the new agreement.
     *
     * @param string $filePath
     *
     * @return string|null
     */
    private function getTranslation(string $filePath): ?string
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return null;
        }

        $expectedDeclaration = 'I declare that the information on the attached invoice is consistent '
            . 'with the customs data entered to generate the shipment';
        try {
            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                if (($row[0] ?? null) === $expectedDeclaration) {
                    return $row[1] ?? null;
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    /**
     * Verify the admin type switcher shows the agreement only for individual clearance.
     *
     * @param string $xml
     * @param string $customsTypeField
     * @param string $target
     *
     * @return void
     */
    private function assertSwitcherControlsVisibility(
        string $xml,
        string $customsTypeField,
        string $target
    ): void {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml));
        $xpath = new DOMXPath($document);

        foreach (['I' => 'true', 'U' => 'false', 'S' => 'false'] as $type => $visible) {
            $nodes = $xpath->query(
                sprintf(
                    '//field[@name="%s"]/settings/switcherConfig/rules/rule[value="%s"]'
                    . '/actions/action[target="%s"]/params/param[@name="0"]',
                    $customsTypeField,
                    $type,
                    $target
                )
            );

            $this->assertNotFalse($nodes);
            $this->assertCount(1, $nodes);
            $this->assertSame($visible, $nodes->item(0)?->textContent);
        }
    }
}
