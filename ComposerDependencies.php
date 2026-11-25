<?php

declare(strict_types=1);

/**
 * Composer bootstrap guard — per-module copy for ksf_FA_image2pdf.
 *
 * Call at the very top of hooks.php, before any other requires:
 *
 *     require_once __DIR__ . '/ComposerDependencies.php';
 *     \ksfraser\FrontAccounting\Image2Pdf\Utils\ComposerDependencies::ensure(__DIR__);
 */

namespace ksfraser\FrontAccounting\Image2Pdf\Utils;

$ksfModuleComposerDepsNamespace = __NAMESPACE__;
$ksfModuleComposerDepsClass = 'ComposerDependencies';
$ksfModuleComposerDepsSentinel = 'KSF_FA_COMPOSER_DEPENDENCIES_' . md5($ksfModuleComposerDepsNamespace);
$ksfModuleComposerDepsWasDeclared = class_exists($ksfModuleComposerDepsNamespace . '\\' . $ksfModuleComposerDepsClass, false);

if (!defined($ksfModuleComposerDepsSentinel) && !$ksfModuleComposerDepsWasDeclared) {
    define($ksfModuleComposerDepsSentinel, true);

    final class ComposerDependencies
    {
        public static function ensure(string $moduleDir): bool
        {
            $autoloadPath = $moduleDir . '/vendor/autoload.php';
            if (file_exists($autoloadPath)) {
                return true;
            }

            $composerPath = $moduleDir . '/composer.json';
            if (!file_exists($composerPath)) {
                return false;
            }

            chdir($moduleDir);
            $output = [];
            $returnCode = 0;
            exec('composer install --no-interaction --prefer-dist 2>&1', $output, $returnCode);
            if ($returnCode !== 0) {
                error_log('KSF Module ksf_FA_image2pdf: composer install failed: ' . implode("\n", $output));
            }

            return file_exists($autoloadPath);
        }
    }
}
