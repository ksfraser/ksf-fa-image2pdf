<?php
/**
 * I2pJobsController — list jobs, download PDF, attach to ksf_FA_Attachments.
 */

class I2pJobsController
{
    public function run(): void
    {
        global $path_to_root;

        $uploadDir = i2p_get_pref('i2p_upload_dir') ?? $path_to_root . '/modules/ksf_FA_image2pdf/uploads';
        $outputDir = i2p_get_pref('i2p_output_dir') ?? $path_to_root . '/modules/ksf_FA_image2pdf/outputs';
        $ocrContainerUrl = i2p_get_pref('i2p_ocr_container_url') ?? '';

        $service = new \Ksfraser\FA_Image2Pdf\Service\Image2PdfService(
            $uploadDir,
            $outputDir,
            null,
            null,
            'eng',
            false,
            $ocrContainerUrl
        );

        $msg = '';

        // Attach a job
        if (isset($_POST['i2p_attach'])) {
            check_token();
            $jobId = (int)($_POST['job_id'] ?? 0);
            $sourceType = $_POST['attach_source_type'] ?? 'image2pdf';
            $sourceId = (int)($_POST['attach_source_id'] ?? 0);
            $uploadedBy = $_SESSION['user'] ?? 'system';

            $job = $service->getJob($jobId);
            if ($job === null) {
                display_error(_('Job not found.'));
            } else {
                $res = $service->attachPdf($job, $sourceType, $sourceId, $uploadedBy);
                if ($res !== null && empty($res['error'])) {
                    display_notification(_('PDF attached to ') . $sourceType . ' #' . $sourceId);
                    $msg = _('Attached.');
                } else {
                    $err = $res !== null && isset($res['error']) ? $res['error'] : _('Attachments module not available.');
                    display_error(_('Attach failed: ') . $err);
                }
            }
        }

        $jobs = $service->listJobs(100);

        display_heading(_('Image → PDF Jobs'));

        if ($msg !== '') {
            display_notification($msg);
        }

        // Attach form (standalone, for attaching any job)
        start_table(TABLESTYLE);
        table_section_title(_('Attach a Job to FA'));

        start_form();
        echo "<tr><td class='label'>" . _('Job #:') . "</td><td>";
        echo '<select name="job_id">';
        foreach ($jobs as $j) {
            $sel = (isset($_POST['job_id']) && (int)$_POST['job_id'] === (int)$j['id']) ? ' selected' : '';
            echo "<option value='{$j['id']}'$sel>#{$j['id']} — " . htmlspecialchars(basename($j['source_image'])) . " ({$j['status']})</option>";
        }
        echo '</select></td></tr>';

        echo "<tr><td class='label'>" . _('Attach to type:') . "</td><td>";
        echo '<input type="text" name="attach_source_type" value="image2pdf" size="30">';
        echo '<span style="color:#999;font-size:11px;"> ' . _('e.g. stock_item, invoice, customer') . '</span>';
        echo '</td></tr>';

        echo "<tr><td class='label'>" . _('Attach to id:') . "</td><td>";
        echo '<input type="text" name="attach_source_id" value="0" size="10">';
        echo '</td></tr>';

        end_table(1);
        br();
        submit_center_first('i2p_attach', _('Attach PDF'));
        end_form();

        br(2);

        // Job list
        start_table(TABLESTYLE);
        table_section_title(_('All Jobs'));

        if (empty($jobs)) {
            echo "<tr><td colspan='8'>" . _('No jobs yet. Upload an image on the Upload tab.') . "</td></tr>";
        } else {
            $th = [
                _('Job #'), _('Source Image'), _('Status'),
                _('OCR'), _('PDF'), _('Size'), _('Created'), _('Actions'),
            ];
            table_header($th);

            $k = 0;
            foreach ($jobs as $j) {
                alt_table_row_color($k);
                label_cell((int)$j['id']);
                label_cell(htmlspecialchars(basename($j['source_image'])));
                $statusColor = $j['status'] === 'completed' ? 'green' : ($j['status'] === 'failed' ? 'red' : '#999');
                label_cell("<span style='color:$statusColor;'><strong>{$j['status']}</strong></span>");
                label_cell(!empty($j['ocr_text']) ? _('Yes') : _('No'));
                if (!empty($j['pdf_path']) && file_exists($j['pdf_path'])) {
                    $dl = $path_to_root . '/modules/ksf_FA_image2pdf/index.php?tab=download&id=' . (int)$j['id'];
                    label_cell("<a href='" . htmlspecialchars($dl) . "'>" . _('Download PDF') . "</a>");
                } else {
                    label_cell(_('—'));
                }
                label_cell((string)($j['pdf_size'] ?? 0) . ' B');
                label_cell(date('Y-m-d H:i', strtotime($j['created_ts'])));

                // Actions
                echo '<td>';
                echo '<form method="post" style="display:inline;margin:0;">';
                echo '<input type="hidden" name="i2p_attach" value="1">';
                echo '<input type="hidden" name="job_id" value="' . (int)$j['id'] . '">';
                echo '<input type="hidden" name="attach_source_type" value="image2pdf">';
                echo '<input type="hidden" name="attach_source_id" value="0">';
                echo '<input type="submit" value="' . _('Attach') . '" style="font-size:10px;padding:2px 6px;">';
                echo '</form>';
                echo '</td>';
                end_row();
                $k = 1 - $k;
            }
        }
        end_table(1);

        page_end();
    }
}
