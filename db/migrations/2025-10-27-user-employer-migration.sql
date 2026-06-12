-- Migration: user & employer schema improvements
-- Date: 2025-10-27
-- WARNING: Review and BACKUP your DB before running. Run in a maintenance window.

-- 1) Add user management columns
ALTER TABLE `users`
  ADD COLUMN `is_active` TINYINT(1) DEFAULT 1,
  ADD COLUMN `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `last_login` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `profile_completed` TINYINT(1) DEFAULT 0;

-- 2) Add employer metadata columns
ALTER TABLE `employers`
  ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `status` ENUM('pending','active','suspended','disabled') DEFAULT 'pending',
  ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `contact_phone` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN `company_slug` VARCHAR(255) DEFAULT NULL;

-- 3) Ensure one-to-one between users and employers
-- Check for duplicates first:
SELECT user_id, COUNT(*) AS cnt FROM employers GROUP BY user_id HAVING cnt > 1;
-- If the above returns rows, clean/merge duplicates BEFORE continuing.
ALTER TABLE `employers` ADD UNIQUE KEY `uq_employers_user_id` (`user_id`);

-- 4) Ensure candidate profile is one-to-one (if desired)
SELECT user_id, COUNT(*) AS cnt FROM candidates GROUP BY user_id HAVING cnt > 1;
-- Clean duplicates if any, then:
ALTER TABLE `candidates` ADD UNIQUE KEY `uq_candidates_user_id` (`user_id`);

-- 5) Optionally add FK from users.company_id -> employers.id
-- Ensure all company_id values point to valid employers first:
SELECT u.id,u.company_id FROM users u LEFT JOIN employers e ON e.id = u.company_id WHERE u.company_id IS NOT NULL AND e.id IS NULL;
-- If safe, add FK. If not used, consider dropping `company_id`.
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `employers`(`id`) ON DELETE SET NULL;

-- 6) Indexes for performance
ALTER TABLE `jobs` ADD INDEX `idx_jobs_status` (`status`);
ALTER TABLE `jobs` ADD INDEX `idx_jobs_title` (`title`(100));

-- 7) Messages FK change (manual review required)
-- If you want to preserve messages after user deletion, change FKs on messages to SET NULL.
-- Manual steps recommended: inspect existing FK constraint names in information_schema and ALTER accordingly.

-- End of migration
