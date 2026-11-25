<?php
/**
 * I2pUploadController — upload image, optional OCR, convert to PDF.
 */

class I2pUploadController
{
    public function run(): void
    {
        global $path_to_root;

        $uploadDir = i2p_get_pref('i2p_upload_dir') ?? $path_to_root . '/modules/ksf_FA_image2pdf/uploads';
        $outputDir = i2p_get_pref('i2p_output_dir') ?? $path_to_root . '/modules/ksf_FA_image2pdf/outputs';
        $ocrEnabled = (string)(i2p_get_pref('i2p_ocr_enabled') ?? '') === '1';
        $ocrLang = i2p_get_pref('i2p_ocr_lang') ?? 'eng';
        $ocrContainerUrl = i2p_get_pref('i2p_ocr_container_url') ?? '';

        $service = new \Ksfraser\FA_Image2Pdf\Service\Image2PdfService(
            $uploadDir,
            $outputDir,
            null,
            null,
            $ocrLang,
            $ocrEnabled,
            $ocrContainerUrl
        );

        $msg = '';
        $job = null;
        $ocr = null;
        $pdf = null;

        if (isset($_POST['i2p_upload'])) {
            check_token();
            $doOcr = isset($_POST['i2p_ocr']) && $_POST['i2p_ocr'] === '1';
            $lang = $_POST['i2p_ocr_lang'] ?? $ocrLang;
            $uploadedBy = $_SESSION['user'] ?? 'system';

            $result = $service->processUpload($_FILES['i2p_image'] ?? [], $doOcr, $lang, $uploadedBy);

            if ($result['error'] !== null) {
                display_error($result['error']);
            } else {
                $job = $result['job'];
                $ocr = $result['ocr'];
                $pdf = $result['pdf'];
                if ($job !== null) {
                    display_notification(_('Image processed. Job #') . (int)$job['id'] . ' — status: ' . $job['status']);
                }
            }
        }

        display_heading(_('Upload Image → PDF'));

        // OCR availability notice
        $ocrAvailable = class_exists(\Ksfraser\Ocr\TesseractOcrAdapter::class)
            ? ((new \Ksfraser\Ocr\TesseractOcrAdapter())->isAvailable() ? _('available') : _('not installed'))
            : _('class not loadable');
        display_note(_('OCR engine (Tesseract): ') . $ocrAvailable . ' — ' . _('Enable OCR in Settings to use it.'));

        start_form();
        start_table(TABLESTYLE);
        table_section_title(_('Upload Image'));

        echo "<tr><td class='label'>" . _('Image (JPG/PNG):') . "</td><td>";
        echo '<input type="file" name="i2p_image" accept="image/jpeg,image/png" required>';
        echo '</td></tr>';

        $ocrChecked = $ocrEnabled ? ' checked' : '';
        echo "<tr><td class='label'>" . _('Run OCR:') . "</td><td>";
        echo "<label><input type='checkbox' name='i2p_ocr' value='1'$ocrChecked> " . _('Extract text from image before converting') . "</label>";
        echo '</td></tr>';

        echo "<tr><td class='label'>" . _('OCR Language:') . "</td><td>";
        echo '<input type="text" name="i2p_ocr_lang" value="' . htmlspecialchars($ocrLang) . '" size="10">';
        echo '<span style="color:#999;font-size:11px;"> ' . _('e.g. eng, fra, deu') . '</span>';
        echo '</td></tr>';

        end_table(1);
        br();

        submit_center_first('i2p_upload', _('Upload & Convert'));
        end_form();

        // Results
        if ($job !== null) {
            br(2);
            start_table(TABLESTYLE);
            table_section_title(_('Result'));

            label_row(_('Job #'), (string)$job['id']);
            label_row(_('Status'), $job['status']);
            label_row(_('Source image'), htmlspecialchars(basename($job['source_image'])));
            label_row(_('Source MIME'), $job['source_mime']);
            label_row(_('Source size'), (string)$job['source_size'] . ' bytes');

            if ($ocr !== null) {
                label_row(_('OCR engine'), $ocr->engine);
                label_row(_('OCR success'), $ocr->success ? _('Yes') : _('No'));
                if ($ocr->error !== '') {
                    label_row(_('OCR error'), $ocr->error);
                }
            }

            if ($pdf !== null && $pdf->success) {
                label_row(_('PDF created'), _('Yes'));
                label_row(_('PDF size'), (string)$pdf->fileSize . ' bytes');
                $downloadUrl = $path_to_root . '/modules/ksf_FA_image2pdf/index.php?tab=download&id=' . (int)$job['id'];
                echo "<tr><td class='label'>" . _('Download PDF:') . "</td><td>";
                echo "<a href='" . htmlspecialchars($downloadUrl) . "' class='button'>" . _('Download PDF') . "</a>";
                echo '</td></tr>';
            } elseif ($pdf !== null && $pdf->error !== '') {
                label_row(_('PDF error'), $pdf->error);
            }

            end_table(1);
        }

        page_end();
    }
}
