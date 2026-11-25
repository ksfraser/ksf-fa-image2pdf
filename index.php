<?php
/**
 * ksf_FA_image2pdf — single front controller (entry point for all UI).
 *
 * Routes ?tab=upload|jobs|settings|download to SRP controller classes under
 * src/Ksfraser/FA_Image2Pdf/Controller/. Security area is resolved centrally.
 * Subdirectories are protected by .htaccess.
 *
 * @package ksf_FA_image2pdf
 */

declare(strict_types=1);

$path_to_root = '../..';

include_once $path_to_root . '/includes/session.inc';
include_once $path_to_root . '/includes/ui.inc';

require_once __DIR__ . '/includes/db.inc';

$controllerDir = __DIR__ . '/src/Ksfraser/FA_Image2Pdf/Controller';

$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'upload';

// One place for all access levels.
$securityMap = [
    'upload'        => 'SA_IMAGE2PDF_MANAGE',
    'jobs'          => 'SA_IMAGE2PDF_VIEW',
    'settings'      => 'SA_IMAGE2PDF_MANAGE',
    'ocr_settings'  => 'SA_IMAGE2PDF_MANAGE',
    'download'      => 'SA_IMAGE2PDF_VIEW',
];

$page_security = isset($securityMap[$tab]) ? $securityMap[$tab] : 'SA_IMAGE2PDF_VIEW';

page(_('Image → PDF'), false, false, '', '');

$controllerClass = 'I2p' . ucfirst($tab) . 'Controller';
$controllerFile  = $controllerDir . '/' . $controllerClass . '.php';

if (is_file($controllerFile)) {
    require_once $controllerFile;
    if (class_exists($controllerClass)) {
        $controller = new $controllerClass();
        $controller->run();
    } else {
        display_error(_('Controller class not found: ') . htmlspecialchars($controllerClass));
    }
} else {
    display_error(_('Unknown tab: ') . htmlspecialchars($tab));
}

page_end();
