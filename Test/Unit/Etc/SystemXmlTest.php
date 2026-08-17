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
        self::assertCount(16, $groups);

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
            'customer_number' => 'carriers/dhlparcel/customer_number',
            'api_login_sandbox' => 'carriers/dhlparcel/api_login_sandbox',
            'api_password_sandbox' => 'carriers/dhlparcel/api_password_sandbox',
            'customer_number_sandbox' => 'carriers/dhlparcel/customer_number_sandbox',
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

        $customerNumberField = $generalGroups[0]->xpath('./field[@id="customer_number"]')[0];
        self::assertSame('0', (string)$customerNumberField->depends->field);
        self::assertSame('sandbox', (string)$customerNumberField->depends->field['id']);

        $apiLoginSandboxField = $generalGroups[0]->xpath('./field[@id="api_login_sandbox"]')[0];
        self::assertSame('1', (string)$apiLoginSandboxField->depends->field);
        self::assertSame('sandbox', (string)$apiLoginSandboxField->depends->field['id']);

        $apiPasswordSandboxField = $generalGroups[0]->xpath('./field[@id="api_password_sandbox"]')[0];
        self::assertSame('1', (string)$apiPasswordSandboxField->depends->field);
        self::assertSame('sandbox', (string)$apiPasswordSandboxField->depends->field['id']);

        $customerNumberSandboxField = $generalGroups[0]->xpath('./field[@id="customer_number_sandbox"]')[0];
        self::assertSame('1', (string)$customerNumberSandboxField->depends->field);
        self::assertSame('sandbox', (string)$customerNumberSandboxField->depends->field['id']);

        // Method groups assertions
        $methodCodes = [
            'parcel_polska' => 'parcel_polska',
            'premium' => 'premium',
            'parcel_9' => 'parcel_9',
            'parcel_12' => 'parcel_12',
            'return_polska' => 'return_polska',
            'economy' => 'economy',
            'max_paczka' => 'max_paczka',
            'max_polpaleta' => 'max_polpaleta',
            'max_paleta' => 'max_paleta',
            'connect' => 'connect',
            'international' => 'international',
            'connect_plus' => 'connect_plus',
            'return_connect' => 'return_connect',
            'connect_polpaleta' => 'connect_polpaleta',
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
    }
}
