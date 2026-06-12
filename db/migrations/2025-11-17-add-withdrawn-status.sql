-- Migration: Add 'withdrawn' to applications.status enum
-- Run on the jobfinder database as a user with ALTER privileges

USE `jobfinder`;

-- This alters the enum to include 'withdrawn'. If your MySQL version or environment
-- doesn't allow MODIFY COLUMN on ENUM directly, export and re-create the column via temp column.
ALTER TABLE applications
  MODIFY COLUMN status ENUM('applied','viewed','shortlisted','rejected','hired','withdrawn') DEFAULT 'applied';

-- Optional: convert any existing empty-string statuses to 'withdrawn'
UPDATE applications SET status = 'withdrawn' WHERE status = '' OR status IS NULL;
