<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Adminhtml;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class ShipmentFormUiTest extends TestCase
{
    /**
     * Verify customs clearance fieldset is collapsed by default in shipment form.
     */
    public function testCustomsClearanceFieldsetIsCollapsedByDefaultInShipmentForm(): void
    {
        $modulePath = dirname(__DIR__, 3);
        $xmlContent = file_get_contents(
            $modulePath . '/view/adminhtml/ui_component/dhlpoland_shipment_form.xml'
        );
        self::assertIsString($xmlContent);

        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);
        $xpath = new DOMXPath($dom);

        $openedNodes = $xpath->query('//fieldset[@name="customs_fieldset"]/settings/opened');
        self::assertNotNull($openedNodes);
        self::assertSame(1, $openedNodes->length, 'customs_fieldset must have opened setting');
        self::assertSame(
            'false',
            trim($openedNodes->item(0)->textContent),
            'customs_fieldset must be collapsed by default'
        );

        $collapsibleNodes = $xpath->query('//fieldset[@name="customs_fieldset"]/settings/collapsible');
        self::assertNotNull($collapsibleNodes);
        self::assertSame(1, $collapsibleNodes->length, 'customs_fieldset must have collapsible setting');
        self::assertSame('true', trim($collapsibleNodes->item(0)->textContent), 'customs_fieldset must be collapsible');
    }
}
