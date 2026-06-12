-- Migration: add CV metadata columns to candidates
-- Run this after backups. Tested with MySQL 8+

ALTER TABLE `candidates`
  ADD COLUMN IF NOT EXISTS `cv_path` VARCHAR(255) DEFAULT NULL AFTER `profile_picture`,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
