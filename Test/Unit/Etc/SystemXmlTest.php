<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class SystemXmlTest extends TestCase
{
    /**
     * Verify general group and method groups under dhlpoland carrier group.
     */
    public function testDhlSystemXmlStructure(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/../../../etc/adminhtml/system.xml');
        self::assertInstanceOf(SimpleXMLElement::class, $xml);

        $groups = $xml->xpath('/config/system/section[@id="carriers"]/group[@id="dhlpoland"]/group');
        self::assertIsArray($groups);
        self::assertCount(5, $groups);

        // General group assertion
        $generalGroups = $xml->xpath(
            '/config/system/section[@id="carriers"]/group[@id="dhlpoland"]/group[@id="general"]'
        );
        self::assertCount(1, $generalGroups);
        $generalFields = [
            'active' => 'carriers/dhlpoland/active',
            'sandbox' => 'carriers/dhlpoland/sandbox',
            'api_login' => 'carriers/dhlpoland/api_login',
            'api_password' => 'carriers/dhlpoland/api_password',
            'api_login_sandbox' => 'carriers/dhlpoland/api_login_sandbox',
            'api_password_sandbox' => 'carriers/dhlpoland/api_password_sandbox',
            'import_tablerates' => 'carriers/dhlpoland/import_tablerates',
            'export_tablerates' => '',
        ];
        foreach ($generalFields as $fieldId => $expectedPath) {
            $fields = $generalGroups[0]->xpath('./field[@id="' . $fieldId . '"]');
            self::assertCount(1, $fields);
            if ($expectedPath !== '') {
                self::assertSame($expectedPath, (string)$fields[0]->config_path);
            }
        }

        // Test dynamic depends logic and field types for prod vs sandbox fields
        $apiLoginField = $generalGroups[0]->xpath('./field[@id="api_login"]')[0];
        self::assertSame('0', (string)$apiLoginField->depends->field);
        self::assertSame('sandbox', (string)$apiLoginField->depends->field['id']);
        self::assertSame('text', (string)$apiLoginField['type']);
        self::assertEmpty($apiLoginField->xpath('./backend_model'));

        $apiPasswordField = $generalGroups[0]->xpath('./field[@id="api_password"]')[0];
        self::assertSame('0', (string)$apiPasswordField->depends->field);
        self::assertSame('sandbox', (string)$apiPasswordField->depends->field['id']);
        self::assertSame('obscure', (string)$apiPasswordField['type']);
        self::assertSame(
            'Magento\Config\Model\Config\Backend\Encrypted',
            (string)$apiPasswordField->backend_model
        );

        $apiLoginSandboxField = $generalGroups[0]->xpath('./field[@id="api_login_sandbox"]')[0];
        self::assertSame('1', (string)$apiLoginSandboxField->depends->field);
        self::assertSame('sandbox', (string)$apiLoginSandboxField->depends->field['id']);
        self::assertSame('text', (string)$apiLoginSandboxField['type']);
        self::assertEmpty($apiLoginSandboxField->xpath('./backend_model'));

        $apiPasswordSandboxField = $generalGroups[0]->xpath('./field[@id="api_password_sandbox"]')[0];
        self::assertSame('1', (string)$apiPasswordSandboxField->depends->field);
        self::assertSame('sandbox', (string)$apiPasswordSandboxField->depends->field['id']);
        self::assertSame('obscure', (string)$apiPasswordSandboxField['type']);
        self::assertSame(
            'Magento\Config\Model\Config\Backend\Encrypted',
            (string)$apiPasswordSandboxField->backend_model
        );

        // Method groups assertions
        $methodCodes = [
            'parcel_polska' => 'parcel_polska',
            'parcel_polska_cod' => 'parcel_polska_cod',
            'premium' => 'premium',
            'premium_cod' => 'premium_cod',
            'parcel_9' => 'parcel_9',
            'parcel_9_cod' => 'parcel_9_cod',
            'parcel_12' => 'parcel_12',
            'parcel_12_cod' => 'parcel_12_cod',
            'economy_all' => 'economy_all',
            'economy_all_cod' => 'economy_all_cod',
            'economy_pop' => 'economy_pop',
            'economy_pop_cod' => 'economy_pop_cod',
            'economy_box' => 'economy_box',
            'economy_box_cod' => 'economy_box_cod',
            'max_paleta' => 'max_paleta',
            'max_paleta_cod' => 'max_paleta_cod',
            'connect' => 'connect',
            'connect_cod' => 'connect_cod',
            'connect_parcelshop' => 'connect_parcelshop',
            'connect_parcelshop_cod' => 'connect_parcelshop_cod',
            'connect_packstation' => 'connect_packstation',
            'connect_packstation_cod' => 'connect_packstation_cod',
            'connect_plus' => 'connect_plus',
            'connect_plus_cod' => 'connect_plus_cod',
            'connect_pallet' => 'connect_pallet',
            'connect_pallet_cod' => 'connect_pallet_cod',
        ];

        foreach ($methodCodes as $groupId => $methodCode) {
            $methodGroups = $xml->xpath(
                '/config/system/section[@id="carriers"]/group[@id="dhlpoland"]//group[@id="' . $groupId . '"]'
            );
            self::assertCount(1, $methodGroups);
            self::assertFalse(isset($methodGroups[0]['expanded']));

            $expectedFields = [
                'active',
                'title',
                'pricing_type',
                'price',
                'free_shipping_enable',
                'free_shipping_subtotal',
                'sallowspecific',
                'specificcountry',
                'showmethod',
                'specificerrmsg',
                'sort_order',
            ];
            foreach ($expectedFields as $field) {
                $fields = $methodGroups[0]->xpath('./field[@id="' . $field . '"]');
                self::assertCount(1, $fields);
                self::assertSame(
                    'carriers/dhlpoland/method_' . $methodCode . '_' . $field,
                    (string)$fields[0]->config_path
                );
            }
        }

        $oldEconomyXpath = '/config/system/section[@id="carriers"]/group[@id="dhlpoland"]//group[@id="economy"]';
        self::assertCount(0, $xml->xpath($oldEconomyXpath));
        $oldEconomyCodXpath = '/config/system/section[@id="carriers"]/group[@id="dhlpoland"]//group[@id="economy_cod"]';
        self::assertCount(0, $xml->xpath($oldEconomyCodXpath));

        $economyAllGroup = $xml->xpath(
            '/config/system/section[@id="carriers"]/group[@id="dhlpoland"]//group[@id="economy_all"]'
        );
        self::assertCount(1, $economyAllGroup);
        self::assertCount(1, $economyAllGroup[0]->xpath('./field[@id="point_delivery_mode"]'));

        self::assertCount(0, $xml->xpath('//field[@id="point_map_type"]'));

        $connectGroup = $xml->xpath(
            '/config/system/section[@id="carriers"]/group[@id="dhlpoland"]//group[@id="connect"]'
        );
        self::assertCount(1, $connectGroup);

        $connectParcelShopGroup = $xml->xpath(
            '/config/system/section[@id="carriers"]/group[@id="dhlpoland"]//group[@id="connect_parcelshop"]'
        );
        self::assertCount(1, $connectParcelShopGroup);

        $connectPackstationGroup = $xml->xpath(
            '/config/system/section[@id="carriers"]/group[@id="dhlpoland"]//group[@id="connect_packstation"]'
        );
        self::assertCount(1, $connectPackstationGroup);
    }

    /**
     * Verify default credentials configuration in config.xml.
     */
    public function testConfigXmlCredentialsStructure(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/../../../etc/config.xml');
        self::assertInstanceOf(SimpleXMLElement::class, $xml);

        $dhlNode = $xml->xpath('/config/default/carriers/dhlpoland');
        self::assertCount(1, $dhlNode);

        $apiLogin = $dhlNode[0]->xpath('./api_login')[0];
        self::assertNull($apiLogin['backend_model']);

        $apiLoginSandbox = $dhlNode[0]->xpath('./api_login_sandbox')[0];
        self::assertNull($apiLoginSandbox['backend_model']);

        $apiPassword = $dhlNode[0]->xpath('./api_password')[0];
        self::assertSame(
            'Magento\Config\Model\Config\Backend\Encrypted',
            (string)$apiPassword['backend_model']
        );

        $apiPasswordSandbox = $dhlNode[0]->xpath('./api_password_sandbox')[0];
        self::assertSame(
            'Magento\Config\Model\Config\Backend\Encrypted',
            (string)$apiPasswordSandbox['backend_model']
        );
    }
}
