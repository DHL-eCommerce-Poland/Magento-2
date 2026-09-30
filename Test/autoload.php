<?php

declare(strict_types=1);

/**
 * Autoloader for Magento Unit Tests.
 *
 * Modeled after Magento's core dev/tests/unit/framework/autoload.php.
 * Dynamically generates ExtensionAttributes, Factories, and Proxies during unit tests.
 */

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesInterfaceGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\ProxyGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;

if (class_exists(GeneratedClassesAutoloader::class)) {
    $generators = [];
    if (class_exists(ExtensionAttributesGenerator::class)) {
        $generators[] = new ExtensionAttributesGenerator();
    }
    if (class_exists(ExtensionAttributesInterfaceGenerator::class)) {
        $generators[] = new ExtensionAttributesInterfaceGenerator();
    }
    if (class_exists(FactoryGenerator::class)) {
        $generators[] = new FactoryGenerator();
    }
    if (class_exists(ProxyGenerator::class)) {
        $generators[] = new ProxyGenerator();
    }

    if (!empty($generators)) {
        $tempDir = defined('TESTS_TEMP_DIR') ? TESTS_TEMP_DIR : sys_get_temp_dir() . '/magento_unit_tests';
        $generatedPath = class_exists(DirectoryList::class)
            ? DirectoryList::getDefaultConfig()[DirectoryList::GENERATED_CODE][DirectoryList::PATH]
            : 'generated/code';

        $generatorIo = new Io(
            new File(),
            $tempDir . '/' . $generatedPath
        );

        $generatedCodeAutoloader = new GeneratedClassesAutoloader(
            $generators,
            $generatorIo
        );

        spl_autoload_register([$generatedCodeAutoloader, 'load']);
    }
}
