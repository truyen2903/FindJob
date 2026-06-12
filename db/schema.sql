-- ===============================
-- 📘 JOBFINDER DATABASE (Full Version 2025)
-- ===============================
CREATE DATABASE IF NOT EXISTS `jobfinder` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jobfinder`;

-- ========== ROLES ==========
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ========== PERMISSIONS ==========
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

-- ========== ROLE - PERMISSION ==========
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== USERS ==========
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT NOT NULL DEFAULT 3,
  `name` VARCHAR(150) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `avatar_path` VARCHAR(255) DEFAULT NULL, -- ảnh đại diện
  `company_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB;

-- ========== EMPLOYERS ==========
CREATE TABLE IF NOT EXISTS `employers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `about` TEXT DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL, -- logo công ty
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== CANDIDATES ==========
CREATE TABLE IF NOT EXISTS `candidates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `headline` VARCHAR(255) DEFAULT NULL,
  `summary` TEXT DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `skills` JSON DEFAULT NULL,
  `experience` JSON DEFAULT NULL,
  `profile_picture` VARCHAR(255) DEFAULT NULL, -- ảnh hồ sơ
  `cv_path` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== JOBS ==========
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employer_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `salary` VARCHAR(100) DEFAULT NULL,
  `employment_type` VARCHAR(50) DEFAULT NULL,
  `banner_image` VARCHAR(255) DEFAULT NULL, -- ảnh minh họa job
  `status` ENUM('draft','published','closed') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`employer_id`) REFERENCES `employers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== APPLICATIONS ==========
CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT NOT NULL,
  `candidate_id` INT NOT NULL,
  `cover_letter` TEXT DEFAULT NULL,
  `resume_snapshot` TEXT DEFAULT NULL,
  `status` ENUM('applied','viewed','shortlisted','rejected','hired') DEFAULT 'applied',
  `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`candidate_id`) REFERENCES `candidates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== SESSIONS ==========
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(128) PRIMARY KEY,
  `data` TEXT,
  `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ========== JOB CATEGORIES ==========
CREATE TABLE IF NOT EXISTS `job_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ========== JOB CATEGORY MAP ==========
CREATE TABLE IF NOT EXISTS `job_category_map` (
  `job_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  PRIMARY KEY (`job_id`,`category_id`),
  FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `job_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== SAVED JOBS ==========
CREATE TABLE IF NOT EXISTS `saved_jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `candidate_id` INT NOT NULL,
  `job_id` INT NOT NULL,
  `saved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (`candidate_id`,`job_id`),
  FOREIGN KEY (`candidate_id`) REFERENCES `candidates`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== NOTIFICATIONS ==========
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `icon_path` VARCHAR(255) DEFAULT NULL, -- biểu tượng
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== COMPANY REVIEWS ==========
CREATE TABLE IF NOT EXISTS `company_reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employer_id` INT NOT NULL,
  `candidate_id` INT NOT NULL,
  `rating` TINYINT CHECK (`rating` BETWEEN 1 AND 5),
  `comment` TEXT,
  `screenshot_path` VARCHAR(255) DEFAULT NULL, -- ảnh minh chứng
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employer_id`) REFERENCES `employers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`candidate_id`) REFERENCES `candidates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== JOB VIEWS ==========
CREATE TABLE IF NOT EXISTS `job_views` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT NOT NULL,
  `viewer_ip` VARCHAR(45) DEFAULT NULL,
  `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== RESUMES ==========
CREATE TABLE IF NOT EXISTS `resumes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `candidate_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `thumbnail_path` VARCHAR(255) DEFAULT NULL, -- ảnh preview
  `title` VARCHAR(150) DEFAULT NULL,
  `is_default` BOOLEAN DEFAULT FALSE,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`candidate_id`) REFERENCES `candidates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== MESSAGES ==========
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_read` BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== SEED DATA ==========
INSERT IGNORE INTO `roles` (`id`,`name`,`description`) VALUES
(1,'admin','Administrator with full access'),
(2,'employer','Employer account'),
(3,'candidate','Candidate account');

INSERT IGNORE INTO `permissions` (`name`,`description`) VALUES
('manage_users','Create/update/delete users'),
('manage_jobs','Create/update/delete job posts'),
('apply_jobs','Apply to jobs'),
('view_applications','View job applications');

INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE (r.name='admin')
   OR (r.name='employer' AND p.name IN ('manage_jobs','view_applications'))
   OR (r.name='candidate' AND p.name IN ('apply_jobs'));

-- Sample Categories
INSERT INTO job_categories (name, description) VALUES
('Công nghệ thông tin', 'Lập trình, kiểm thử, hệ thống'),
('Marketing', 'Quảng cáo, SEO, sáng tạo nội dung'),
('Kinh doanh', 'Bán hàng, tư vấn khách hàng');


-- Update November 2025: Add quantity and deadline to jobs
ALTER TABLE jobs
ADD COLUMN `quantity` INT DEFAULT 1 AFTER `employment_type`,
ADD COLUMN `deadline` DATE DEFAULT NULL AFTER `quantity`,
ADD COLUMN `job_requirements` TEXT AFTER `description`;

