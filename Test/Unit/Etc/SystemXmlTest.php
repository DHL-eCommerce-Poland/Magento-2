<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class SystemXmlTest extends TestCase
{
    /**
     * Verify general group and method groups under dhlparcel carrier group.
     */
    public function testDhlSystemXmlStructure(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/../../../etc/adminhtml/system.xml');
        self::assertInstanceOf(SimpleXMLElement::class, $xml);

        $groups = $xml->xpath('/config/system/section[@id="carriers"]/group[@id="dhlparcel"]/group');
        self::assertIsArray($groups);
        self::assertCount(12, $groups);

        // General group assertion
        $generalGroups = $xml->xpath(
            '/config/system/section[@id="carriers"]/group[@id="dhlparcel"]/group[@id="general"]'
        );
        self::assertCount(1, $generalGroups);
        $generalFields = [
            'active' => 'carriers/dhlparcel/active',
            'sandbox' => 'carriers/dhlparcel/sandbox',
            'api_login' => 'carriers/dhlparcel/api_login',
            'api_password' => 'carriers/dhlparcel/api_password',
            'api_login_sandbox' => 'carriers/dhlparcel/api_login_sandbox',
            'api_password_sandbox' => 'carriers/dhlparcel/api_password_sandbox',
            'import_tablerates' => 'carriers/dhlparcel/import_tablerates',
            'export_tablerates' => '',
        ];
        foreach ($generalFields as $fieldId => $expectedPath) {
            $fields = $generalGroups[0]->xpath('./field[@id="' . $fieldId . '"]');
            self::assertCount(1, $fields);
            if ($expectedPath !== '') {
                self::assertSame($expectedPath, (string)$fields[0]->config_path);
            }
        }

        // Test dynamic depends logic for prod vs sandbox fields
        $apiLoginField = $generalGroups[0]->xpath('./field[@id="api_login"]')[0];
        self::assertSame('0', (string)$apiLoginField->depends->field);
        self::assertSame('sandbox', (string)$apiLoginField->depends->field['id']);

        $apiPasswordField = $generalGroups[0]->xpath('./field[@id="api_password"]')[0];
        self::assertSame('0', (string)$apiPasswordField->depends->field);
        self::assertSame('sandbox', (string)$apiPasswordField->depends->field['id']);

        $apiLoginSandboxField = $generalGroups[0]->xpath('./field[@id="api_login_sandbox"]')[0];
        self::assertSame('1', (string)$apiLoginSandboxField->depends->field);
        self::assertSame('sandbox', (string)$apiLoginSandboxField->depends->field['id']);

        $apiPasswordSandboxField = $generalGroups[0]->xpath('./field[@id="api_password_sandbox"]')[0];
        self::assertSame('1', (string)$apiPasswordSandboxField->depends->field);
        self::assertSame('sandbox', (string)$apiPasswordSandboxField->depends->field['id']);

        // Method groups assertions
        $methodCodes = [
            'parcel_polska' => 'parcel_polska',
            'premium' => 'premium',
            'parcel_9' => 'parcel_9',
            'parcel_12' => 'parcel_12',
            'economy' => 'economy',
            'max_paleta' => 'max_paleta',
            'connect' => 'connect',
            'connect_parcel_shop' => 'connect_parcel_shop',
            'connect_packstation' => 'connect_packstation',
            'connect_plus' => 'connect_plus',
            'connect_pallet' => 'connect_pallet',
        ];

        foreach ($methodCodes as $groupId => $methodCode) {
            $methodGroups = $xml->xpath(
                '/config/system/section[@id="carriers"]/group[@id="dhlparcel"]/group[@id="' . $groupId . '"]'
            );
            self::assertCount(1, $methodGroups);
            self::assertFalse(isset($methodGroups[0]['expanded']));

            foreach ([
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
                'sort_order'
            ] as $field) {
                $fields = $methodGroups[0]->xpath('./field[@id="' . $field . '"]');
                self::assertCount(1, $fields);
                self::assertSame(
                    'carriers/dhlparcel/method_' . $methodCode . '_' . $field,
                    (string)$fields[0]->config_path
                );
            }
        }

        $economyGroup = $xml->xpath(
            '/config/system/section[@id="carriers"]/group[@id="dhlparcel"]/group[@id="economy"]'
        );
        self::assertCount(1, $economyGroup);
        self::assertCount(1, $economyGroup[0]->xpath('./field[@id="point_delivery_mode"]'));
        self::assertCount(1, $economyGroup[0]->xpath('./field[@id="point_map_type"]'));

        $connectParcelShopGroup = $xml->xpath(
            '/config/system/section[@id="carriers"]/group[@id="dhlparcel"]/group[@id="connect_parcel_shop"]'
        );
        self::assertCount(1, $connectParcelShopGroup);
        self::assertCount(0, $connectParcelShopGroup[0]->xpath('./field[@id="point_map_type"]'));

        $connectPackstationGroup = $xml->xpath(
            '/config/system/section[@id="carriers"]/group[@id="dhlparcel"]/group[@id="connect_packstation"]'
        );
        self::assertCount(1, $connectPackstationGroup);
        self::assertCount(0, $connectPackstationGroup[0]->xpath('./field[@id="point_map_type"]'));
    }
}
