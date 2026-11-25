<?php
declare(strict_types=1);

namespace Ksfraser\FA_Image2Pdf\Service;

use Ksfraser\Image2Pdf\FileSystemImageToPdfAdapter;
use Ksfraser\Image2Pdf\ImageToPdfConverterInterface;
use Ksfraser\Ocr\OcrEngineInterface;
use Ksfraser\Ocr\TesseractOcrAdapter;

/**
 * Orchestrates image upload → optional OCR → PDF conversion → optional attach.
 *
 * Dependencies (converter / OCR engine) are injected so they can be stubbed
 * in tests. Falls back to default implementations when none are given.
 *
 * Attachment to ksf_FA_Attachments is attempted via the
 * `image2pdf_add_attachment` hook first, then via direct
 * AttachmentService::addAttachment() when its class is loadable.
 *
 * All major steps are logged via i2p_log() (ksf_FA_Logging).
 */
class Image2PdfService
{
    /** @var string */
    private $uploadDir;
    /** @var string */
    private $outputDir;
    /** @var ImageToPdfConverterInterface|null */
    private $converter;
    /** @var OcrEngineInterface|null */
    private $ocrEngine;
    /** @var string|null */
    private $ocrLanguage;
    /** @var bool */
    private $ocrEnabled;
    /** @var string|null */
    private $ocrContainerUrl;
    /** @var string */
    private $tablePrefix;

    public function __construct(
        string $uploadDir,
        string $outputDir,
        ?ImageToPdfConverterInterface $converter = null,
        ?OcrEngineInterface $ocrEngine = null,
        string $ocrLanguage = 'eng',
        bool $ocrEnabled = false,
        ?string $ocrContainerUrl = null
    ) {
        $this->uploadDir = rtrim($uploadDir, '/');
        $this->outputDir = rtrim($outputDir, '/');
        $this->converter = $converter ?? new FileSystemImageToPdfAdapter();
        $this->ocrEngine = $ocrEngine ?? new TesseractOcrAdapter($ocrContainerUrl, $ocrLanguage);
        $this->ocrLanguage = $ocrLanguage;
        $this->ocrEnabled = $ocrEnabled;
        $this->ocrContainerUrl = $ocrContainerUrl;
        $this->tablePrefix = defined('TB_PREF') ? (string)TB_PREF : '0_';

        @mkdir($this->uploadDir, 0775, true);
        @mkdir($this->outputDir, 0775, true);

        i2p_log('debug', 'Image2PdfService initialized', [
            'upload_dir' => $this->uploadDir,
            'output_dir' => $this->outputDir,
            'ocr_enabled' => $ocrEnabled,
            'ocr_language' => $ocrLanguage,
            'ocr_container_url' => $ocrContainerUrl,
        ]);
    }

    /**
     * Handle an uploaded image: move it into the upload dir, optionally OCR,
     * convert to PDF, return a job array.
     *
     * @param array $file       $_FILES entry
     * @param bool  $doOcr      Whether to run OCR
     * @param string|null $language OCR language override
     * @param string $uploadedBy User id / name
     * @return array ['job'=>array, 'ocr'=>OcrResult|null, 'pdf'=>PdfResult|null, 'error'=>string|null]
     */
    public function processUpload(array $file, bool $doOcr = false, ?string $language = null, string $uploadedBy = 'system'): array
    {
        $result = ['job' => null, 'ocr' => null, 'pdf' => null, 'error' => null];

        $name = basename($file['name'] ?? '');
        $tmp  = $file['tmp_name'] ?? '';
        $size = (int)($file['size'] ?? 0);
        $mime = $file['type'] ?? '';

        i2p_log('info', 'Image upload received', [
            'name' => $name,
            'size' => $size,
            'mime' => $mime,
            'uploaded_by' => $uploadedBy,
            'do_ocr' => $doOcr,
        ]);

        if ($name === '' || $tmp === '' || !is_uploaded_file($tmp)) {
            i2p_log('error', 'Upload rejected: no valid uploaded file', [
                'name' => $name,
                'tmp' => $tmp,
            ]);
            $result['error'] = 'No valid uploaded file';
            return $result;
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/x-png' => 'png'];
        $ext = $allowed[$mime] ?? null;
        if ($ext === null) {
            i2p_log('warning', 'Upload rejected: unsupported MIME type', [
                'name' => $name,
                'mime' => $mime,
            ]);
            $result['error'] = 'Only JPG and PNG images are allowed';
            return $result;
        }

        $jobRef = 'i2p_' . bin2hex(random_bytes(6));
        $sourcePath = $this->uploadDir . '/' . $jobRef . '.' . $ext;

        if (!move_uploaded_file($tmp, $sourcePath)) {
            i2p_log('error', 'Upload rejected: failed to store image', [
                'name' => $name,
                'target' => $sourcePath,
            ]);
            $result['error'] = 'Failed to store uploaded image';
            return $result;
        }

        i2p_log('info', 'Image stored', [
            'job_ref' => $jobRef,
            'source_path' => $sourcePath,
            'size' => $size,
        ]);

        // Persist job row
        $jobId = $this->createJobRow($jobRef, $sourcePath, $mime, $size);

        // Optional OCR
        $ocrResult = null;
        if ($doOcr && $this->ocrEnabled) {
            i2p_log('info', 'OCR started', [
                'job_id' => $jobId,
                'job_ref' => $jobRef,
                'language' => $language ?? $this->ocrLanguage,
                'container_url' => $this->ocrContainerUrl,
            ]);

            $ocrLang = $language ?? $this->ocrLanguage;
            if ($this->ocrEngine && $this->ocrEngine->isAvailable()) {
                $ocrResult = $this->ocrEngine->ocr($sourcePath, $ocrLang);
                $this->updateJobOcr($jobId, $ocrResult);

                if ($ocrResult->success) {
                    i2p_log('info', 'OCR completed', [
                        'job_id' => $jobId,
                        'engine' => $ocrResult->engine,
                        'text_length' => strlen($ocrResult->text),
                        'details' => $ocrResult->details,
                    ]);
                } else {
                    i2p_log('error', 'OCR failed', [
                        'job_id' => $jobId,
                        'error' => $ocrResult->error,
                    ]);
                }
            } else {
                i2p_log('warning', 'OCR requested but engine not available', [
                    'job_id' => $jobId,
                ]);
            }
        }

        // Convert to PDF
        i2p_log('info', 'PDF conversion started', [
            'job_id' => $jobId,
            'job_ref' => $jobRef,
        ]);

        $pdfPath = $this->outputDir . '/' . $jobRef . '.pdf';
        $pdfResult = $this->converter->convert([$sourcePath], $pdfPath);

        if ($pdfResult->success) {
            $this->markCompleted($jobId, $pdfResult);
            i2p_log('info', 'PDF conversion completed', [
                'job_id' => $jobId,
                'pdf_path' => $pdfResult->pdfPath,
                'file_size' => $pdfResult->fileSize,
            ]);
            $result['job'] = $this->getJob($jobId);
            $result['ocr'] = $ocrResult;
            $result['pdf'] = $pdfResult;
        } else {
            $this->markFailed($jobId, $pdfResult->error);
            i2p_log('error', 'PDF conversion failed', [
                'job_id' => $jobId,
                'error' => $pdfResult->error,
            ]);
            $result['job'] = $this->getJob($jobId);
            $result['error'] = $pdfResult->error;
        }

        return $result;
    }

    /**
     * Attach a completed job's PDF to ksf_FA_Attachments.
     *
     * Tries the `image2pdf_add_attachment` hook first (so other modules can
     * intercept), then falls back to direct AttachmentService::addAttachment()
     * when its class is loadable.
     *
     * @param array $job Job row from getJob()
     * @param string $sourceType Attachment source type
     * @param int $sourceId Attachment source id
     * @param string $uploadedBy
     * @return array|null Attachment result or null when not attached
     */
    public function attachPdf(array $job, string $sourceType, int $sourceId, string $uploadedBy = 'system'): ?array
    {
        if (empty($job['pdf_path']) || !file_exists($job['pdf_path'])) {
            i2p_log('warning', 'Attach skipped: no PDF file', [
                'job_id' => $job['id'] ?? 0,
            ]);
            return null;
        }

        i2p_log('info', 'PDF attach started', [
            'job_id' => $job['id'],
            'job_ref' => $job['job_ref'],
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);

        $payload = [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'filename' => basename($job['pdf_path']),
            'file_path' => $job['pdf_path'],
            'file_size' => $job['pdf_size'] ?? filesize($job['pdf_path']),
            'mime_type' => 'application/pdf',
            'uploaded_by' => $uploadedBy,
            'fa_source_ref' => 'image2pdf:' . $job['job_ref'],
        ];

        // 1) Hook path — lets other modules (or ksf_FA_Attachments responder) handle it.
        if (function_exists('hook_invoke')) {
            $hookData = $payload;
            $ok = hook_invoke('ksf_FA_Attachments', 'image2pdf_add_attachment', $hookData, null);
            if ($ok === true && isset($hookData['attachment_id'])) {
                $this->markAttached((int)$job['id'], (int)$hookData['attachment_id'], $sourceType, $sourceId);
                i2p_log('info', 'PDF attached via hook', [
                    'job_id' => $job['id'],
                    'attachment_id' => $hookData['attachment_id'],
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ]);
                return $hookData;
            }
        }

        // 2) Direct service path — ksf_FA_Attachments must be active with vendor autoload.
        $candidates = [
            __DIR__ . '/../../../../ksf_FA_Attachments/vendor/autoload.php',
            dirname(__FILE__, 4) . '/ksf_FA_Attachments/vendor/autoload.php',
        ];
        $autoloadLoaded = false;
        foreach ($candidates as $autoloadPath) {
            if (is_file($autoloadPath)) {
                require_once $autoloadPath;
                $autoloadLoaded = true;
                break;
            }
        }

        if (!$autoloadLoaded) {
            i2p_log('warning', 'Attach skipped: ksf_FA_Attachments autoload not found', [
                'job_id' => $job['id'],
            ]);
            return null;
        }

        if (class_exists(\Ksfraser\Attachment\Service\AttachmentService::class)) {
            try {
                $service = new \Ksfraser\Attachment\Service\AttachmentService();
                $att = $service->addAttachment($sourceType, $sourceId, $payload);
                $res = $att->toArray();
                $this->markAttached((int)$job['id'], $att->getId(), $sourceType, $sourceId);
                i2p_log('info', 'PDF attached via service', [
                    'job_id' => $job['id'],
                    'attachment_id' => $att->getId(),
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ]);
                return $res;
            } catch (\Throwable $e) {
                i2p_log('error', 'PDF attach failed via service', [
                    'job_id' => $job['id'],
                    'error' => $e->getMessage(),
                ]);
                return ['error' => $e->getMessage()];
            }
        }

        i2p_log('warning', 'Attach skipped: AttachmentService class not found', [
            'job_id' => $job['id'],
        ]);
        return null;
    }

    /** @return array|null */
    public function getJob(int $id): ?array
    {
        $row = $this->dbFetchOne("SELECT * FROM {$this->tablePrefix}ksf_image2pdf_jobs WHERE id = :id", [':id' => $id]);
        return $row;
    }

    /** @return array */
    public function listJobs(int $limit = 50): array
    {
        return $this->dbFetchAll("SELECT * FROM {$this->tablePrefix}ksf_image2pdf_jobs ORDER BY created_ts DESC LIMIT :limit", [':limit' => (int)$limit]);
    }

    /** @return string|null PDF path or null */
    public function pdfPath(int $id): ?string
    {
        $job = $this->getJob($id);
        if ($job === null || empty($job['pdf_path'])) {
            return null;
        }
        return $job['pdf_path'];
    }

    // ── private DB helpers ──
    private function createJobRow(string $jobRef, string $sourcePath, string $mime, int $size): int
    {
        $sql = "INSERT INTO {$this->tablePrefix}ksf_image2pdf_jobs
                (job_ref, source_image, source_mime, source_size, status, created_ts)
                VALUES (:ref, :src, :mime, :size, 'uploaded', NOW())";
        $this->dbExecute($sql, [
            ':ref' => $jobRef, ':src' => $sourcePath, ':mime' => $mime, ':size' => $size,
        ]);
        return (int)db_insert_id();
    }

    private function updateJobOcr(int $jobId, \Ksfraser\Ocr\OcrResult $ocr): void
    {
        $sql = "UPDATE {$this->tablePrefix}ksf_image2pdf_jobs
                SET ocr_text = :text, ocr_engine = :engine, ocr_language = :lang
                WHERE id = :id";
        $this->dbExecute($sql, [
            ':text' => $ocr->text,
            ':engine' => $ocr->engine,
            ':lang' => $ocr->engine,
            ':id' => $jobId,
        ]);
    }

    private function markCompleted(int $jobId, \Ksfraser\Image2Pdf\PdfResult $pdf): void
    {
        $sql = "UPDATE {$this->tablePrefix}ksf_image2pdf_jobs
                SET pdf_path = :path, pdf_size = :size, status = 'completed', completed_ts = NOW()
                WHERE id = :id";
        $this->dbExecute($sql, [
            ':path' => $pdf->pdfPath,
            ':size' => $pdf->fileSize,
            ':id' => $jobId,
        ]);
    }

    private function markFailed(int $jobId, string $error): void
    {
        $sql = "UPDATE {$this->tablePrefix}ksf_image2pdf_jobs
                SET status = 'failed', error_message = :err WHERE id = :id";
        $this->dbExecute($sql, [':err' => $error, ':id' => $jobId]);
    }

    private function markAttached(int $jobId, int $attachmentId, string $sourceType, int $sourceId): void
    {
        $sql = "UPDATE {$this->tablePrefix}ksf_image2pdf_jobs
                SET status = 'attached', attachment_id = :aid, attached_to_type = :st, attached_to_id = :sid
                WHERE id = :id";
        $this->dbExecute($sql, [
            ':aid' => $attachmentId, ':st' => $sourceType, ':sid' => $sourceId, ':id' => $jobId,
        ]);
    }

    private function dbExecute(string $sql, array $params = []): void
    {
        $sql = $this->substituteParams($sql, $params);
        db_query($sql, 'image2pdf DB error');
    }

    /** @return array|null */
    private function dbFetchOne(string $sql, array $params = []): ?array
    {
        $sql = $this->substituteParams($sql, $params);
        $result = db_query($sql, 'image2pdf DB error');
        if ($result === false) {
            return null;
        }
        $row = db_fetch_assoc($result);
        return $row === false ? null : $row;
    }

    /** @return array */
    private function dbFetchAll(string $sql, array $params = []): array
    {
        $sql = $this->substituteParams($sql, $params);
        $result = db_query($sql, 'image2pdf DB error');
        $rows = [];
        if ($result === false) {
            return $rows;
        }
        while ($row = db_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function substituteParams(string $sql, array $params): string
    {
        return preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function (array $m) use ($params) {
            $key = $m[1];
            if (!array_key_exists($key, $params)) {
                return $m[0];
            }
            $v = $params[$key];
            if ($v === null) {
                return 'NULL';
            }
            if (is_bool($v)) {
                return $v ? '1' : '0';
            }
            if (is_numeric($v)) {
                return (string)$v;
            }
            return db_escape((string)$v);
        }, $sql);
    }
}
