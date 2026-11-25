<?php
/**
 * I2pDownloadController — stream a generated PDF for download (no attach).
 */

class I2pDownloadController
{
    public function run(): void
    {
        global $path_to_root;

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            display_error(_('No job id supplied.'));
            page_end();
            return;
        }

        $uploadDir = i2p_get_pref('i2p_upload_dir') ?? $path_to_root . '/modules/ksf_FA_image2pdf/uploads';
        $outputDir = i2p_get_pref('i2p_output_dir') ?? $path_to_root . '/modules/ksf_FA_image2pdf/outputs';
        $ocrContainerUrl = i2p_get_pref('i2p_ocr_container_url') ?? '';

        $service = new \Ksfraser\FA_Image2Pdf\Service\Image2PdfService($uploadDir, $outputDir, null, null, 'eng', false, $ocrContainerUrl);
        $job = $service->getJob($id);

        if ($job === null) {
            display_error(_('Job not found.'));
            page_end();
            return;
        }

        $pdfPath = $job['pdf_path'] ?? '';
        if ($pdfPath === '' || !file_exists($pdfPath)) {
            display_error(_('PDF not found for this job.'));
            page_end();
            return;
        }

        $fileSize = (int)filesize($pdfPath);
        $basename = basename($pdfPath);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $basename . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, max-age=0');
        header('Pragma: public');

        readfile($pdfPath);
        exit;
    }
}
