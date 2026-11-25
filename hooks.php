<?php
/**
 * ksf_FA_image2pdf — FA module hooks for image → PDF conversion.
 *
 * Security areas:
 *   SA_IMAGE2PDF_VIEW  — view jobs, download PDFs
 *   SA_IMAGE2PDF_MANAGE — upload, convert, OCR, attach
 */

define('SS_ksf_FA_Image2Pdf', 131 << 8);

require_once __DIR__ . '/ComposerDependencies.php';
\ksfraser\FrontAccounting\Image2Pdf\Utils\ComposerDependencies::ensure(__DIR__);

$autoload_path = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

require_once __DIR__ . '/includes/db.inc';

class hooks_ksf_FA_Image2Pdf extends hooks
{
    var $module_name = 'ksf_FA_Image2Pdf';

    function install_tabs($app)
    {
        set_ext_domain('modules/ksf_FA_image2pdf');
        $app->add_application(new image2pdf_app());
        set_ext_domain();
    }

    function install_options($app)
    {
    }

    function preload()
    {
        global $security_areas;

        if (!isset($security_areas['SA_IMAGE2PDF_VIEW'])) {
            $security_areas['SA_IMAGE2PDF_VIEW'] = [SS_ksf_FA_Image2Pdf | 1, _("View image→PDF jobs")];
            $security_areas['SA_IMAGE2PDF_MANAGE'] = [SS_ksf_FA_Image2Pdf | 2, _("Manage image→PDF jobs")];
        }
    }

    function activate_extension($company, $check_only = true)
    {
        if (!$check_only) {
            $this->ensure_schema();
        }
        return true;
    }

    function deactivate_extension($company, $check_only = true)
    {
        return true;
    }

    function install_access()
    {
        $security_areas = [
            'SA_IMAGE2PDF_VIEW'  => [SS_ksf_FA_Image2Pdf | 1, _("View image→PDF jobs")],
            'SA_IMAGE2PDF_MANAGE'=> [SS_ksf_FA_Image2Pdf | 2, _("Manage image→PDF jobs")],
        ];
        $security_sections = [
            SS_ksf_FA_Image2Pdf => _("Image → PDF"),
        ];
        return [$security_areas, $security_sections];
    }

    private function ensure_schema()
    {
        $sqlFile = dirname(__FILE__) . '/sql/01_ksf_image2pdf_jobs.sql';
        if (!file_exists($sqlFile)) {
            return;
        }
        $sql = file_get_contents($sqlFile);
        if ($sql === false || $sql === '') {
            return;
        }
        $statements = explode(';', $sql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }
            db_query($stmt, 'Could not execute ksf_FA_image2pdf schema statement');
        }
    }
}

class image2pdf_app extends application
{
    function __construct()
    {
        parent::__construct("Image → PDF", _("Image → PDF"));

        $this->add_module(_("Setup"));

        $menu = new FAModuleMenu('modules/ksf_FA_image2pdf/index.php', 'tab', '');
        $menu->addItem('upload', _("&Upload"), MENU_MAINTENANCE)
             ->addItem('jobs', _("&Jobs"), MENU_INQUIRY)
             ->addItem('settings', _("&Settings"), MENU_ENTRY)
             ->addItem('ocr_settings', _("OCR &Settings"), MENU_ENTRY);

        $menu->registerWithApp($this, 'SA_IMAGE2PDF_VIEW');

        $this->add_extensions();
    }
}
