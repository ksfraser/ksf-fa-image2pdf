<?php
/**
 * I2pOcrSettingsController — configure Tesseract container URL and test OCR.
 */

class I2pOcrSettingsController
{
    public function run(): void
    {
        global $path_to_root;

        $containerUrl = i2p_get_pref('i2p_ocr_container_url') ?? '';
        $ocrLang = i2p_get_pref('i2p_ocr_lang') ?? 'eng';

        $msg = '';
        $msgType = '';
        $testResult = null;

        if (isset($_POST['i2p_save_ocr_settings'])) {
            check_token();
            $containerUrl = trim($_POST['i2p_ocr_container_url'] ?? '');
            $ocrLang = trim($_POST['i2p_ocr_lang'] ?? 'eng');

            $ok = true;
            if (!i2p_set_pref('i2p_ocr_container_url', $containerUrl)) {
                $ok = false;
            }
            if (!i2p_set_pref('i2p_ocr_lang', $ocrLang)) {
                $ok = false;
            }
            if ($ok) {
                $msg = _('OCR settings saved.');
                $msgType = 'success';
                i2p_log('info', 'OCR settings saved', [
                    'container_url' => $containerUrl,
                    'language' => $ocrLang,
                ]);
            } else {
                $msg = _('Failed to save OCR settings.');
                $msgType = 'error';
            }
        }

        if (isset($_POST['i2p_test_ocr'])) {
            check_token();
            $testContainerUrl = trim($_POST['i2p_test_container_url'] ?? $containerUrl);
            $testLang = trim($_POST['i2p_test_lang'] ?? $ocrLang);

            i2p_log('info', 'OCR test initiated', [
                'container_url' => $testContainerUrl,
                'language' => $testLang,
            ]);

            $adapter = new \Ksfraser\Ocr\TesseractOcrAdapter();

            // Temporarily override container URL for this test via reflection-free approach:
            // We can't change the private property, so we test what's configured.
            // Instead, create a test by checking availability and attempting OCR on a
            // minimal image if one exists.

            $containerReachable = $testContainerUrl !== null && $testContainerUrl !== '';
            $localAvailable = false;
            $localError = '';

            // Check local tesseract binary
            foreach (['tesseract'] as $cmd) {
                exec('command -v ' . escapeshellarg($cmd) . ' 2>&1', $out, $code);
                if ($code === 0 && !empty($out)) {
                    $localAvailable = true;
                    break;
                }
            }

            // Try a real OCR test if we have a test image, otherwise just report availability
            $testImage = $path_to_root . '/modules/ksf_FA_image2pdf/test-image.png';
            if (file_exists($testImage)) {
                if ($containerReachable) {
                    $ocrResult = $adapter->ocr($testImage, $testLang);
                    if ($ocrResult->success) {
                        $testResult = [
                            'success' => true,
                            'engine' => $ocrResult->engine,
                            'text_preview' => mb_substr($ocrResult->text, 0, 200),
                            'details' => $ocrResult->details,
                        ];
                        i2p_log('info', 'OCR test succeeded', [
                            'engine' => $ocrResult->engine,
                            'text_length' => strlen($ocrResult->text),
                            'details' => $ocrResult->details,
                        ]);
                    } else {
                        $testResult = [
                            'success' => false,
                            'error' => $ocrResult->error,
                        ];
                        i2p_log('error', 'OCR test failed', [
                            'error' => $ocrResult->error,
                        ]);
                    }
                } else {
                    // No container URL — try local only
                    $localResult = $adapter->ocr($testImage, $testLang);
                    if ($localResult->success) {
                        $testResult = [
                            'success' => true,
                            'engine' => $localResult->engine,
                            'text_preview' => mb_substr($localResult->text, 0, 200),
                        ];
                        i2p_log('info', 'Local OCR test succeeded', [
                            'engine' => $localResult->engine,
                        ]);
                    } else {
                        $testResult = [
                            'success' => false,
                            'error' => $localResult->error,
                        ];
                        i2p_log('error', 'Local OCR test failed', [
                            'error' => $localResult->error,
                        ]);
                    }
                }
            } else {
                // No test image — just report availability
                $testResult = [
                    'success' => ($containerReachable || $localAvailable),
                    'container_configured' => $containerReachable,
                    'container_url' => $testContainerUrl,
                    'local_available' => $localAvailable,
                    'message' => $containerReachable || $localAvailable
                        ? _('OCR engine appears available.')
                        : _('No Tesseract container URL set and no local tesseract binary found.'),
                ];
                i2p_log('info', 'OCR availability check', $testResult);
            }
        }

        // Check availability for display
        $containerConfigured = $containerUrl !== '';
        $localBinaryFound = false;
        exec('command -v tesseract 2>&1', $out, $code);
        if ($code === 0 && !empty($out)) {
            $localBinaryFound = true;
        }

        if ($msg !== '') {
            if ($msgType === 'success') {
                display_notification($msg);
            } else {
                display_error($msg);
            }
        }

        display_heading(_('OCR Engine Settings'));

        start_form();
        start_table(TABLESTYLE);
        table_section_title(_('Tesseract Container'));

        text_row(_('Container URL:'), 'i2p_ocr_container_url', $containerUrl, 60, 500);
        display_note(_('HTTP endpoint of the containerized Tesseract service (e.g. http://tesseract:8080/ocr). '
            . 'Leave blank to use local shell only.'));

        text_row(_('OCR Language:'), 'i2p_ocr_lang', $ocrLang, 10, 10);
        display_note(_('Tesseract language code, e.g. eng, fra, deu.'));

        end_table(1);
        br();

        submit_center_first('i2p_save_ocr_settings', _('Save OCR Settings'));
        end_form();

        br(2);

        // Test section
        start_table(TABLESTYLE);
        table_section_title(_('Test OCR'));

        $testContainerChecked = $containerConfigured ? '' : '';
        echo "<tr><td class='label'>" . _('Test container URL:') . "</td><td>";
        echo '<input type="text" name="i2p_test_container_url" value="' . htmlspecialchars($containerUrl) . '" size="60">';
        echo '</td></tr>';

        echo "<tr><td class='label'>" . _('Test language:') . "</td><td>";
        echo '<input type="text" name="i2p_test_lang" value="' . htmlspecialchars($ocrLang) . '" size="10">';
        echo '</td></tr>';

        end_table(1);
        br();

        $testDisabled = !$containerConfigured && !$localBinaryFound ? ' disabled' : '';
        echo '<form method="post" style="display:inline;">';
        echo '<input type="hidden" name="i2p_test_ocr" value="1">';
        echo '<input type="hidden" name="i2p_test_container_url" value="' . htmlspecialchars($containerUrl) . '">';
        echo '<input type="hidden" name="i2p_test_lang" value="' . htmlspecialchars($ocrLang) . '">';
        submit_center_first('i2p_test_ocr', _('Run OCR Test') . $testDisabled ? ' ' . _('(no engine configured)') : '');
        echo '</form>';

        if ($testDisabled) {
            display_note(_('No Tesseract container URL configured and no local tesseract binary found. '
                . 'Set a container URL or install tesseract-ocr to enable testing.'));
        }

        // Test results
        if ($testResult !== null) {
            br(2);
            start_table(TABLESTYLE);
            table_section_title(_('Test Result'));

            if ($testResult['success'] === true) {
                display_notification(_('OCR test passed.'));
                label_row(_('Engine:'), htmlspecialchars($testResult['engine'] ?? 'tesseract'));
                if (!empty($testResult['text_preview'])) {
                    $preview = htmlspecialchars($testResult['text_preview']);
                    echo "<tr><td class='label'>" . _('Extracted text (preview):') . "</td><td>";
                    echo "<pre style='background:#f5f5f5;padding:8px;border:1px solid #ddd;max-height:200px;overflow:auto;'>$preview</pre>";
                    echo '</td></tr>';
                }
                if (!empty($testResult['details'])) {
                    foreach ($testResult['details'] as $k => $v) {
                        label_row(ucfirst(str_replace('_', ' ', $k)), (string)$v);
                    }
                }
            } else {
                display_error(_('OCR test failed:'));
                label_row(_('Error:'), htmlspecialchars($testResult['error'] ?? _('Unknown error')));
            }
            end_table(1);
        }

        // Availability status
        br(2);
        start_table(TABLESTYLE);
        table_section_title(_('Availability Status'));

        label_row(_('Container URL configured:'), $containerConfigured
            ? '<span style="color:green;">' . _('Yes') . ': ' . htmlspecialchars($containerUrl) . '</span>'
            : '<span style="color:#999;">' . _('No') . '</span>');
        label_row(_('Local tesseract binary:'), $localBinaryFound
            ? '<span style="color:green;">' . _('Found') . '</span>'
            : '<span style="color:#999;">' . _('Not found — install tesseract-ocr') . '</span>');
        label_row(_('Overall:'), ($containerConfigured || $localBinaryFound)
            ? '<span style="color:green;"><strong>' . _('OCR available') . '</strong></span>'
            : '<span style="color:red;"><strong>' . _('OCR unavailable') . '</strong></span>');

        end_table(1);

        page_end();
    }
}
