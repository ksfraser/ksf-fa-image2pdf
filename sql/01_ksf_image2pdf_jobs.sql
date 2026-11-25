CREATE TABLE IF NOT EXISTS `0_ksf_image2pdf_jobs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `job_ref` VARCHAR(32) NOT NULL,
  `source_image` VARCHAR(255) NOT NULL,
  `source_mime` VARCHAR(64) NOT NULL,
  `source_size` INT(11) NOT NULL DEFAULT 0,
  `pdf_path` VARCHAR(255) NULL DEFAULT NULL,
  `pdf_size` INT(11) NULL DEFAULT NULL,
  `ocr_text` TEXT NULL DEFAULT NULL,
  `ocr_engine` VARCHAR(32) NULL DEFAULT NULL,
  `ocr_language` VARCHAR(16) NULL DEFAULT NULL,
  `converter` VARCHAR(32) NULL DEFAULT NULL,
  `status` ENUM('uploaded','converting','completed','failed','attached') NOT NULL DEFAULT 'uploaded',
  `attached_to_type` VARCHAR(64) NULL DEFAULT NULL,
  `attached_to_id` INT(11) NULL DEFAULT NULL,
  `attachment_id` INT(11) NULL DEFAULT NULL,
  `error_message` TEXT NULL DEFAULT NULL,
  `created_ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_ts` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_ref` (`job_ref`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_ts`)
);

CREATE TABLE IF NOT EXISTS `0_ksf_image2pdf_prefs` (
  `pref_key` VARCHAR(64) NOT NULL,
  `pref_value` TEXT NOT NULL,
  `updated_ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pref_key`)
);
