-- ============================================================================
-- TimeOff Management System - Complete MySQL Database Schema for PHP 8.2
-- Compatible with XAMPP 8.2 (MySQL 8.0+) and Ubuntu/MariaDB
-- ============================================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- Companies Table
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `country` varchar(2) NOT NULL DEFAULT 'US',
  `start_of_new_year` int(11) NOT NULL DEFAULT 1 COMMENT 'Month when fiscal year starts (1-12)',
  `share_all_absences` tinyint(1) NOT NULL DEFAULT 0,
  `is_team_view_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `ldap_auth_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `ldap_auth_config` text DEFAULT NULL COMMENT 'JSON config for LDAP',
  `date_format` varchar(20) NOT NULL DEFAULT 'YYYY-MM-DD',
  `company_wide_message` text DEFAULT NULL,
  `company_wide_message_text_color` varchar(7) DEFAULT '#000000',
  `company_wide_message_bg_color` varchar(7) DEFAULT '#f2dede',
  `mode` int(11) NOT NULL DEFAULT 1 COMMENT '1=normal, 2=readonly holidays',
  `timezone` varchar(100) NOT NULL DEFAULT 'America/New_York',
  `payroll_close_time` int(11) NOT NULL DEFAULT 10 COMMENT 'Hour in UTC when payroll closes on Monday',
  `integration_api_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `integration_api_token` char(36) DEFAULT NULL COMMENT 'UUID for API access',
  `carry_over` int(11) NOT NULL DEFAULT 0 COMMENT 'Max days to carry to next year',
  `last_name_first` tinyint(1) NOT NULL DEFAULT 0,
  `first_day_of_week` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=Sunday, 1=Monday',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_api_token` (`integration_api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `allowance` decimal(5,1) NOT NULL DEFAULT 20.0 COMMENT 'Annual vacation days',
  `personal` decimal(5,1) NOT NULL DEFAULT 5.0 COMMENT 'Personal days',
  `include_public_holidays` tinyint(1) NOT NULL DEFAULT 1,
  `is_accrued_allowance` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Allowance accrues over time',
  `company_id` int(11) NOT NULL,
  `manager_id` int(11) DEFAULT NULL COMMENT 'Department manager',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_manager` (`manager_id`),
  CONSTRAINT `fk_dept_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dept_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `slack_username` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash',
  `name` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `activated` tinyint(1) NOT NULL DEFAULT 0,
  `admin` tinyint(1) NOT NULL DEFAULT 0,
  `manager` tinyint(1) NOT NULL DEFAULT 0,
  `auto_approve` tinyint(1) NOT NULL DEFAULT 0,
  `reset_password_token` varchar(64) DEFAULT NULL,
  `reset_password_expires` datetime DEFAULT NULL,
  `start_date` date NOT NULL DEFAULT (CURRENT_DATE),
  `end_date` date DEFAULT NULL COMMENT 'Employment end date, NULL = active',
  `company_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_company` (`company_id`),
  KEY `idx_department` (`department_id`),
  KEY `idx_lastname` (`lastname`),
  KEY `idx_reset_token` (`reset_password_token`),
  CONSTRAINT `fk_user_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leave Types Table
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#ffffff',
  `use_allowance` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Counts toward vacation allowance',
  `use_personal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Counts as personal leave',
  `limit` int(11) NOT NULL DEFAULT 0 COMMENT 'Max days per year, 0=unlimited',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `auto_approve` tinyint(1) NOT NULL DEFAULT 0,
  `manager_only` tinyint(1) NOT NULL DEFAULT 0,
  `is_special` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Show in team view popup',
  `company_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_sort` (`sort_order`),
  CONSTRAINT `fk_leavetype_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leaves Table
CREATE TABLE IF NOT EXISTS `leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` tinyint(1) NOT NULL COMMENT '1=new, 2=approved, 3=rejected, 4=pended_revoke, 5=canceled',
  `employee_comment` text DEFAULT NULL,
  `approver_comment` text DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `date_start` date NOT NULL,
  `day_part_start` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=all_day, 2=morning, 3=afternoon',
  `date_end` date NOT NULL,
  `day_part_end` tinyint(1) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL COMMENT 'Employee requesting leave',
  `approver_id` int(11) DEFAULT NULL COMMENT 'User who approved/rejected',
  `leave_type_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_approver` (`approver_id`),
  KEY `idx_leave_type` (`leave_type_id`),
  KEY `idx_dates` (`date_start`, `date_end`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_leave_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bank Holidays Table
CREATE TABLE IF NOT EXISTS `bank_holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `company_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_date` (`date`),
  CONSTRAINT `fk_bankholiday_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schedules Table
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `monday` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=works_whole_day, 2=works_none, 3=works_morning, 4=works_afternoon',
  `tuesday` tinyint(1) NOT NULL DEFAULT 1,
  `wednesday` tinyint(1) NOT NULL DEFAULT 1,
  `thursday` tinyint(1) NOT NULL DEFAULT 1,
  `friday` tinyint(1) NOT NULL DEFAULT 1,
  `saturday` tinyint(1) NOT NULL DEFAULT 2,
  `sunday` tinyint(1) NOT NULL DEFAULT 2,
  `company_id` int(11) DEFAULT NULL COMMENT 'Company-wide schedule',
  `user_id` int(11) DEFAULT NULL COMMENT 'User-specific schedule',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_schedule_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedule_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_schedule_relation` CHECK (
    (`company_id` IS NOT NULL AND `user_id` IS NULL) OR
    (`company_id` IS NULL AND `user_id` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Allowance Adjustments Table
CREATE TABLE IF NOT EXISTS `user_allowance_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL,
  `adjustment` decimal(5,1) NOT NULL DEFAULT 0 COMMENT 'General allowance adjustment',
  `personal_adjustment` decimal(5,1) NOT NULL DEFAULT 0 COMMENT 'Personal days adjustment',
  `carried_over_allowance` decimal(5,1) NOT NULL DEFAULT 0 COMMENT 'Days carried from previous year',
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_year` (`user_id`, `year`),
  CONSTRAINT `fk_adjustment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Department Supervisors Table (Junction)
CREATE TABLE IF NOT EXISTS `department_supervisors` (
  `department_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`department_id`, `user_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_depsup_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_depsup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Feeds Table (Calendar exports)
CREATE TABLE IF NOT EXISTS `user_feeds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `feed_token` char(36) NOT NULL COMMENT 'UUID for feed access',
  `type` enum('calendar','wallchart','teamview','company') NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`feed_token`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_feed_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Audit Table
CREATE TABLE IF NOT EXISTS `email_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `subject` text NOT NULL,
  `body` mediumtext NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_emailaudit_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_emailaudit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments Table
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL COMMENT 'LEAVE, etc',
  `entity_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `company_id` int(11) NOT NULL,
  `by_user_id` int(11) NOT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_user` (`by_user_id`),
  CONSTRAINT `fk_comment_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_user` FOREIGN KEY (`by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Table
CREATE TABLE IF NOT EXISTS `audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `attribute` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `by_user_id` int(11) DEFAULT NULL,
  `at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_user` (`by_user_id`),
  CONSTRAINT `fk_audit_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Messages Table
CREATE TABLE IF NOT EXISTS `user_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','in-progress','resolved') NOT NULL DEFAULT 'new',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_message_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions Table (for PHP session storage)
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) NOT NULL,
  `data` mediumtext NOT NULL,
  `expires` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT DATA INSERTION
-- ============================================================================

-- Insert default company (example)
INSERT INTO `companies` (`name`, `country`, `timezone`) VALUES
('Demo Company', 'US', 'America/New_York');

SET @company_id = LAST_INSERT_ID();

-- Insert default department
INSERT INTO `departments` (`name`, `allowance`, `personal`, `company_id`) VALUES
('Sales', 20.0, 5.0, @company_id);

SET @dept_id = LAST_INSERT_ID();

-- Insert default admin user (password: admin123456789)
-- Password hash for 'admin123456789' using bcrypt cost 12
INSERT INTO `users` (`email`, `password`, `name`, `lastname`, `admin`, `company_id`, `department_id`) VALUES
('admin@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyB4VlvfHGaq', 'Admin', 'User', 1, @company_id, @dept_id);

-- Insert default leave types
INSERT INTO `leave_types` (`name`, `color`, `use_allowance`, `company_id`) VALUES
('Holiday', '#22AA66', 1, @company_id),
('Sick Leave', '#459FF3', 0, @company_id);

UPDATE `leave_types` SET `limit` = 10 WHERE `name` = 'Sick Leave';

-- Insert default schedule (Monday-Friday working)
INSERT INTO `schedules` (`monday`, `tuesday`, `wednesday`, `thursday`, `friday`, `saturday`, `sunday`, `company_id`) VALUES
(1, 1, 1, 1, 1, 2, 2, @company_id);

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================

-- Additional composite indexes for common queries
ALTER TABLE `leaves` ADD INDEX `idx_user_status_dates` (`user_id`, `status`, `date_start`, `date_end`);
ALTER TABLE `users` ADD INDEX `idx_company_active` (`company_id`, `end_date`);

SET FOREIGN_KEY_CHECKS=1;
COMMIT;

-- ============================================================================
-- VIEWS FOR COMMON QUERIES
-- ============================================================================

-- Active users view
CREATE OR REPLACE VIEW `v_active_users` AS
SELECT u.*, d.name as department_name, c.name as company_name
FROM `users` u
INNER JOIN `departments` d ON u.department_id = d.id
INNER JOIN `companies` c ON u.company_id = c.id
WHERE u.end_date IS NULL OR u.end_date >= CURDATE();

-- Pending leave requests view
CREATE OR REPLACE VIEW `v_pending_leaves` AS
SELECT l.*, u.name as user_name, u.lastname as user_lastname,
       lt.name as leave_type_name, d.name as department_name
FROM `leaves` l
INNER JOIN `users` u ON l.user_id = u.id
INNER JOIN `leave_types` lt ON l.leave_type_id = lt.id
INNER JOIN `departments` d ON u.department_id = d.id
WHERE l.status = 1 AND l.decided_at IS NULL;

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
