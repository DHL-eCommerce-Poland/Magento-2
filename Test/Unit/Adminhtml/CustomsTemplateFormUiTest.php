<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Adminhtml;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class CustomsTemplateFormUiTest extends TestCase
{
    /**
     * Verify costs_of_shipment and gross_weight do not require entry in customs template form.
     */
    public function testGrossCostsAndGrossWeightAreNotRequiredInCustomsTemplateForm(): void
    {
        $modulePath = dirname(__DIR__, 3);
        $xmlContent = file_get_contents(
            $modulePath . '/view/adminhtml/ui_component/dhlpoland_customs_template_form.xml'
        );
        self::assertIsString($xmlContent);

        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);
        $xpath = new DOMXPath($dom);

        // Check costs_of_shipment
        $costsRequired = $xpath->query('//field[@name="costs_of_shipment"]//validation/rule[@name="required-entry"]');
        self::assertNotNull($costsRequired);
        self::assertSame(0, $costsRequired->length, 'costs_of_shipment must not have required-entry validation');

        $costsNumber = $xpath->query('//field[@name="costs_of_shipment"]//validation/rule[@name="validate-number"]');
        self::assertNotNull($costsNumber);
        self::assertSame(1, $costsNumber->length, 'costs_of_shipment should retain validate-number rule');

        // Check gross_weight
        $weightRequired = $xpath->query('//field[@name="gross_weight"]//validation/rule[@name="required-entry"]');
        self::assertNotNull($weightRequired);
        self::assertSame(0, $weightRequired->length, 'gross_weight must not have required-entry validation');

        $weightNumber = $xpath->query('//field[@name="gross_weight"]//validation/rule[@name="validate-number"]');
        self::assertNotNull($weightNumber);
        self::assertSame(1, $weightNumber->length, 'gross_weight should retain validate-number rule');
    }
}
