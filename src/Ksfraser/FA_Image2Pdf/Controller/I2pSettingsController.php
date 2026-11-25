<?php
/**
 * I2pSettingsController — upload/output dirs, OCR toggle, language.
 */

class I2pSettingsController
{
    public function run(): void
    {
        global $path_to_root;

        $msg = '';
        $msgType = '';

        if (isset($_POST['i2p_save_settings'])) {
            check_token();
            $uploadDir = $_POST['i2p_upload_dir'] ?? '';
            $outputDir = $_POST['i2p_output_dir'] ?? '';
            $ocrEnabled = isset($_POST['i2p_ocr_enabled']) ? '1' : '0';
            $ocrLang = $_POST['i2p_ocr_lang'] ?? 'eng';

            $ok = true;
            foreach (['i2p_upload_dir' => $uploadDir, 'i2p_output_dir' => $outputDir,
                      'i2p_ocr_enabled' => $ocrEnabled, 'i2p_ocr_lang' => $ocrLang] as $k => $v) {
                if (!i2p_set_pref($k, (string)$v)) {
                    $ok = false;
                }
            }
            if ($ok) {
                $msg = _('Settings saved.');
                $msgType = 'success';
            } else {
                $msg = _('Failed to save one or more settings.');
                $msgType = 'error';
            }
        }

        $uploadDir = i2p_get_pref('i2p_upload_dir') ?? $path_to_root . '/modules/ksf_FA_image2pdf/uploads';
        $outputDir = i2p_get_pref('i2p_output_dir') ?? $path_to_root . '/modules/ksf_FA_image2pdf/outputs';
        $ocrEnabled = i2p_get_pref('i2p_ocr_enabled') ?? '0';
        $ocrLang = i2p_get_pref('i2p_ocr_lang') ?? 'eng';

        if ($msg !== '') {
            if ($msgType === 'success') {
                display_notification($msg);
            } else {
                display_error($msg);
            }
        }

        // Converter / OCR availability
        $converter = class_exists(\Ksfraser\Image2Pdf\FileSystemImageToPdfAdapter::class)
            ? new \Ksfraser\Image2Pdf\FileSystemImageToPdfAdapter()
            : null;
        $ocr = class_exists(\Ksfraser\Ocr\TesseractOcrAdapter::class)
            ? new \Ksfraser\Ocr\TesseractOcrAdapter()
            : null;

        display_heading(_('Image → PDF Settings'));

        start_form();
        start_table(TABLESTYLE);
        table_section_title(_('Directories'));

        text_row(_('Upload directory:'), 'i2p_upload_dir', $uploadDir, 60, 500);
        display_note(_('Uploaded images are stored here. Must be writable by the web server.'));

        text_row(_('Output directory:'), 'i2p_output_dir', $outputDir, 60, 500);
        display_note(_('Generated PDFs are written here.'));

        $ocrChecked = $ocrEnabled === '1' ? ' checked' : '';
        echo "<tr><td class='label'>" . _('OCR enabled:') . "</td><td>";
        echo "<label><input type='checkbox' name='i2p_ocr_enabled' value='1'$ocrChecked> " . _('Run OCR on upload') . "</label>";
        echo '</td></tr>';

        text_row(_('OCR language:'), 'i2p_ocr_lang', $ocrLang, 10, 10);
        display_note(_('Tesseract language code, e.g. eng, fra, deu.'));

        end_table(1);
        br();

        submit_center_first('i2p_save_settings', _('Save Settings'));
        end_form();

        br(2);

        start_table(TABLESTYLE);
        table_section_title(_('Tool Availability'));

        $convOk = $converter !== null;
        label_row(_('Image→PDF converter class'), $convOk ? _('loadable') : _('not loadable'));
        // Check underlying binary
        $cmdOk = false;
        if ($convOk) {
            // Use reflection-free probe: just check if any converter binary exists
            foreach (['magick', 'convert', 'img2pdf'] as $c) {
                exec('command -v ' . escapeshellarg($c) . ' 2>&1', $out, $code);
                if ($code === 0 && !empty($out)) {
                    $cmdOk = true;
                    break;
                }
            }
        }
        label_row(_('Converter binary (magick/convert/img2pdf)'), $cmdOk ? _('found') : _('not found — install ImageMagick or img2pdf'));

        $ocrOk = $ocr !== null && $ocr->isAvailable();
        label_row(_('OCR engine (Tesseract)'), $ocrOk ? _('available') : _('not available — install tesseract-ocr'));

        end_table(1);

        page_end();
    }
}
