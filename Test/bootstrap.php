<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for Ideo_Dhl module tests.
 *
 * Modeled after Magento core dev/tests/unit/framework/bootstrap.php with adaptive autoloading
 * for standalone CI and full Magento environments.
 */

// 1. Locate Composer autoloader
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    // Fallback when running inside full Magento (app/code/Ideo/Dhl)
    $parentVendor = dirname(__DIR__, 4) . '/vendor/autoload.php';
    if (file_exists($parentVendor)) {
        $vendorAutoload = $parentVendor;
    }
}

if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// 2. Define temp directory for generated test code
if (!defined('TESTS_TEMP_DIR')) {
    define('TESTS_TEMP_DIR', sys_get_temp_dir() . '/magento_unit_tests_' . hash('sha256', __DIR__));
}

// 3. Load generated code autoloader (factories, proxies, extension attributes)
require_once __DIR__ . '/autoload.php';

// 4. Custom error handler (converts PHP errors/warnings to exceptions, as in Magento core)
if (!function_exists('magentoUnitTestsPhpErrorHandler')) {
    function magentoUnitTestsPhpErrorHandler(int $errNo, string $errStr, string $errFile, int $errLine): bool
    {
        $errLevel = error_reporting();
        if (($errLevel & $errNo) !== 0) {
            $errorNames = [
                E_ERROR => 'Error',
                E_WARNING => 'Warning',
                E_PARSE => 'Parse',
                E_NOTICE => 'Notice',
                E_CORE_ERROR => 'Core Error',
                E_CORE_WARNING => 'Core Warning',
                E_COMPILE_ERROR => 'Compile Error',
                E_COMPILE_WARNING => 'Compile Warning',
                E_USER_ERROR => 'User Error',
                E_USER_WARNING => 'User Warning',
                E_USER_NOTICE => 'User Notice',
                E_RECOVERABLE_ERROR => 'Recoverable Error',
                E_DEPRECATED => 'Deprecated',
                E_USER_DEPRECATED => 'User Deprecated',
            ];

            $errName = $errorNames[$errNo] ?? '';

            throw new \PHPUnit\Framework\Exception(
                sprintf('%s: %s in %s:%s.', $errName, $errStr, $errFile, $errLine),
                $errNo
            );
        }

        return false;
    }
}

set_error_handler('magentoUnitTestsPhpErrorHandler');

// 5. Configure Phrase renderer for translations
if (class_exists(\Magento\Framework\Phrase::class)) {
    \Magento\Framework\Phrase::setRenderer(new \Magento\Framework\Phrase\Renderer\Placeholder());
}

// 6. PHP environment settings (exact values from Magento core bootstrap)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('precision', '14');
ini_set('serialize_precision', '14');

if (extension_loaded('xdebug')) {
    ini_set('xdebug.max_nesting_level', '200');
}
