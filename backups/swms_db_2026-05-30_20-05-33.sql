-- SWMS Database Backup
-- Host: localhost | Database: swms_db
-- Generated: 2026-05-30 20:05:33

CREATE DATABASE IF NOT EXISTS `swms_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `swms_db`;


DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_module` (`module`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `module`, `description`, `data`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'auth', 'User logged in', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 12:40:47'),
(2, 1, 'login', 'auth', 'Login successful', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 12:40:47'),
(3, 1, 'login', 'auth', 'User logged in', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 12:41:19'),
(4, 1, 'login', 'auth', 'Login successful', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 12:41:19'),
(5, 1, 'update', 'Settings', 'Updated billing settings', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 13:28:26'),
(6, 1, 'login', 'auth', 'User logged in', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 13:29:38'),
(7, 1, 'login', 'auth', 'Login successful', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 13:29:38'),
(8, 1, 'login', 'auth', 'User logged in', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 15:59:33'),
(9, 1, 'login', 'auth', 'Login successful', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 15:59:33'),
(10, 1, 'update', 'Settings', 'Updated billing settings', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 16:06:01'),
(11, 1, 'login', 'auth', 'User logged in', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 17:36:30'),
(12, 1, 'login', 'auth', 'Login successful', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 17:36:30'),
(13, 1, 'create', 'consumers', 'Registered consumer: SWM-0001 - krish', '{"consumer_id":"1"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 17:55:01'),
(14, 1, 'login', 'auth', 'User logged in', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 19:48:11'),
(15, 1, 'login', 'auth', 'Login successful', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 19:48:11'),
(16, 1, 'login', 'auth', 'User logged in', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 19:48:19'),
(17, 1, 'login', 'auth', 'Login successful', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 19:48:19');


DROP TABLE IF EXISTS `asset_categories`;
CREATE TABLE `asset_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `asset_maintenance`;
CREATE TABLE `asset_maintenance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `maintenance_type` enum('routine','repair','emergency','overhaul') DEFAULT 'routine',
  `title` varchar(300) NOT NULL,
  `description` text DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT 0.00,
  `performed_by` varchar(200) DEFAULT NULL,
  `vendor` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `asset_maintenance_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `asset_repairs`;
CREATE TABLE `asset_repairs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `repair_date` date NOT NULL,
  `description` text NOT NULL,
  `cost` decimal(12,2) DEFAULT 0.00,
  `vendor` varchar(200) DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `downtime_hours` int(11) DEFAULT NULL,
  `reported_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `reported_by` (`reported_by`),
  CONSTRAINT `asset_repairs_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_repairs_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `assets`;
CREATE TABLE `assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(100) NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `asset_type` enum('water_tank','pipeline','pump','valve','meter','vehicle','building','equipment','other') NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `location` text DEFAULT NULL,
  `ward_no` int(11) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(14,2) DEFAULT 0.00,
  `current_value` decimal(14,2) DEFAULT 0.00,
  `warranty_expiry` date DEFAULT NULL,
  `life_span_years` int(11) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `model_no` varchar(100) DEFAULT NULL,
  `serial_no` varchar(100) DEFAULT NULL,
  `capacity` varchar(100) DEFAULT NULL,
  `status` enum('operational','maintenance','damaged','decommissioned','under_construction') DEFAULT 'operational',
  `image` varchar(255) DEFAULT NULL,
  `documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`documents`)),
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  KEY `category_id` (`category_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `idx_asset_code` (`asset_code`),
  KEY `idx_asset_type` (`asset_type`),
  KEY `idx_asset_status` (`status`),
  KEY `idx_asset_location` (`latitude`,`longitude`),
  CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `assets_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('present','absent','late','half_day','leave','holiday') DEFAULT 'present',
  `remarks` text DEFAULT NULL,
  `marked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`employee_id`,`date`),
  KEY `marked_by` (`marked_by`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `audit_trail`;
CREATE TABLE `audit_trail` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(100) NOT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_audit_module` (`module`),
  KEY `idx_audit_reference` (`reference_type`,`reference_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `backup_logs`;
CREATE TABLE `backup_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `backup_type` enum('full','partial','manual') DEFAULT 'full',
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `status` enum('success','failed','in_progress') DEFAULT 'in_progress',
  `error_message` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `backup_logs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `backup_logs` (`id`, `backup_type`, `file_name`, `file_size`, `status`, `error_message`, `created_by`, `created_at`) VALUES
(1, 'manual', 'swms_db_2026-05-30_20-05-33.sql', NULL, 'in_progress', NULL, 1, '2026-05-30 20:05:33');


DROP TABLE IF EXISTS `bill_payments`;
CREATE TABLE `bill_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bill_payment` (`bill_id`,`payment_id`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `bill_payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`),
  CONSTRAINT `bill_payments_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `bills`;
CREATE TABLE `bills` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bill_no` varchar(100) NOT NULL,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `meter_id` bigint(20) unsigned DEFAULT NULL,
  `tariff_id` bigint(20) unsigned DEFAULT NULL,
  `fiscal_year_id` bigint(20) unsigned DEFAULT NULL,
  `billing_period_start` date NOT NULL,
  `billing_period_end` date NOT NULL,
  `due_date` date NOT NULL,
  `previous_reading` decimal(10,2) DEFAULT 0.00,
  `current_reading` decimal(10,2) DEFAULT 0.00,
  `consumption` decimal(10,2) DEFAULT 0.00,
  `base_fee` decimal(12,2) DEFAULT 0.00,
  `consumption_charge` decimal(12,2) DEFAULT 0.00,
  `meter_rent` decimal(12,2) DEFAULT 0.00,
  `sewerage_fee` decimal(12,2) DEFAULT 0.00,
  `vat_amount` decimal(12,2) DEFAULT 0.00,
  `penalty_amount` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `due_amount` decimal(12,2) DEFAULT 0.00,
  `bill_type` enum('metered','flat','estimated') DEFAULT 'metered',
  `status` enum('pending','paid','partial','overdue','cancelled') DEFAULT 'pending',
  `is_read` tinyint(1) DEFAULT 0,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_no` (`bill_no`),
  KEY `meter_id` (`meter_id`),
  KEY `tariff_id` (`tariff_id`),
  KEY `fiscal_year_id` (`fiscal_year_id`),
  KEY `generated_by` (`generated_by`),
  KEY `idx_bill_no` (`bill_no`),
  KEY `idx_bill_consumer` (`consumer_id`),
  KEY `idx_bill_status` (`status`),
  KEY `idx_bill_due_date` (`due_date`),
  KEY `idx_bill_period` (`billing_period_start`,`billing_period_end`),
  CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`),
  CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bills_ibfk_3` FOREIGN KEY (`tariff_id`) REFERENCES `tariffs` (`id`),
  CONSTRAINT `bills_ibfk_4` FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`),
  CONSTRAINT `bills_ibfk_5` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `complaint_categories`;
CREATE TABLE `complaint_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sla_hours` int(11) DEFAULT 24,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `complaint_feedback`;
CREATE TABLE `complaint_feedback` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `complaint_id` bigint(20) unsigned NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `feedback` text DEFAULT NULL,
  `submitted_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `complaint_id` (`complaint_id`),
  CONSTRAINT `complaint_feedback_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `complaint_updates`;
CREATE TABLE `complaint_updates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `complaint_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `complaint_updates_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  CONSTRAINT `complaint_updates_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `complaints`;
CREATE TABLE `complaints` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_no` varchar(100) NOT NULL,
  `consumer_id` bigint(20) unsigned DEFAULT NULL,
  `citizen_name` varchar(200) DEFAULT NULL,
  `citizen_phone` varchar(20) DEFAULT NULL,
  `citizen_email` varchar(200) DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `subject` varchar(300) NOT NULL,
  `description` text NOT NULL,
  `location` text DEFAULT NULL,
  `ward_no` int(11) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed','reopened') DEFAULT 'open',
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `closing_notes` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_no` (`ticket_no`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `closed_by` (`closed_by`),
  KEY `idx_ticket_no` (`ticket_no`),
  KEY `idx_complaint_status` (`status`),
  KEY `idx_complaint_priority` (`priority`),
  KEY `idx_complaint_consumer` (`consumer_id`),
  KEY `idx_complaint_assigned` (`assigned_to`),
  CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`id`),
  CONSTRAINT `complaints_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `complaints_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `complaints_ibfk_5` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `consumer_categories`;
CREATE TABLE `consumer_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `consumer_categories` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'General Household', 'general-household', 'General household consumers', '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(2, 'Low Income', 'low-income', 'Low income household consumers', '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(3, 'Commercial', 'commercial', 'Commercial establishments', '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(4, 'Institutional', 'institutional', 'Government and institutional', '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(5, 'Industrial', 'industrial', 'Industrial consumers', '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(6, 'Bulk', 'bulk', 'Bulk water consumers', '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL);


DROP TABLE IF EXISTS `consumer_documents`;
CREATE TABLE `consumer_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `document_type` enum('citizenship','land_ownership','agreement','photo','application','bill_copy','other') NOT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consumer_id` (`consumer_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `consumer_documents_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consumer_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `consumer_history`;
CREATE TABLE `consumer_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consumer_id` (`consumer_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `consumer_history_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consumer_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `consumer_history` (`id`, `consumer_id`, `action`, `description`, `old_value`, `new_value`, `changed_by`, `created_at`) VALUES
(1, 1, 'created', NULL, NULL, '{"consumer_no":"SWM-0001","full_name":"krish","father_name":"kundan","mother_name":"laxmi","spouse_name":"mohit devi","grandfather_name":"krish","gender":"","date_of_birth":"","citizenship_no":"0000-00-00-00","citizenship_issued_district":"","phone":"","mobile":"9704674787","email":"","permanent_province":"","permanent_district":"","permanent_municipality":"","permanent_ward":"","permanent_tole":"","temporary_address":"","ward_no":"1","tole":"","house_no":"","street":"","landmark":"","category_id":3,"connection_type":"household","property_type":"","family_members":0,"tap_connection_date":"","connection_size":"","pipe_size":"","latitude":"26.478374","longitude":"87.269859","status":"active"}', 1, '2026-05-30 17:55:01');


DROP TABLE IF EXISTS `consumers`;
CREATE TABLE `consumers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_no` varchar(50) NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `connection_type` enum('household','commercial','institutional') NOT NULL DEFAULT 'household',
  `full_name` varchar(200) NOT NULL,
  `father_name` varchar(200) DEFAULT NULL,
  `mother_name` varchar(200) DEFAULT NULL,
  `spouse_name` varchar(200) DEFAULT NULL,
  `grandfather_name` varchar(200) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT 'male',
  `date_of_birth` date DEFAULT NULL,
  `citizenship_no` varchar(100) DEFAULT NULL,
  `citizenship_issued_district` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) NOT NULL,
  `email` varchar(200) DEFAULT NULL,
  `permanent_province` varchar(100) DEFAULT NULL,
  `permanent_district` varchar(100) DEFAULT NULL,
  `permanent_municipality` varchar(200) DEFAULT NULL,
  `permanent_ward` int(11) DEFAULT NULL,
  `permanent_tole` varchar(200) DEFAULT NULL,
  `temporary_address` text DEFAULT NULL,
  `occupation` varchar(200) DEFAULT NULL,
  `ward_no` int(11) NOT NULL,
  `tole` varchar(200) DEFAULT NULL,
  `house_no` varchar(50) DEFAULT NULL,
  `street` varchar(200) DEFAULT NULL,
  `landmark` varchar(200) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `property_type` enum('owned','rented','other') DEFAULT 'owned',
  `water_usage_purpose` text DEFAULT NULL,
  `family_members` int(11) DEFAULT 1,
  `tap_connection_date` date DEFAULT NULL,
  `connection_size` varchar(50) DEFAULT NULL,
  `pipe_size` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','suspended','disconnected') DEFAULT 'active',
  `registration_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consumer_no` (`consumer_no`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_consumer_no` (`consumer_no`),
  KEY `idx_consumer_ward` (`ward_no`),
  KEY `idx_consumer_status` (`status`),
  KEY `idx_consumer_type` (`connection_type`),
  KEY `idx_consumer_mobile` (`mobile`),
  KEY `idx_consumer_location` (`latitude`,`longitude`),
  CONSTRAINT `consumers_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `consumer_categories` (`id`),
  CONSTRAINT `consumers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `consumers` (`id`, `consumer_no`, `category_id`, `connection_type`, `full_name`, `father_name`, `mother_name`, `spouse_name`, `grandfather_name`, `gender`, `date_of_birth`, `citizenship_no`, `citizenship_issued_district`, `phone`, `mobile`, `email`, `permanent_province`, `permanent_district`, `permanent_municipality`, `permanent_ward`, `permanent_tole`, `temporary_address`, `occupation`, `ward_no`, `tole`, `house_no`, `street`, `landmark`, `latitude`, `longitude`, `property_type`, `water_usage_purpose`, `family_members`, `tap_connection_date`, `connection_size`, `pipe_size`, `status`, `registration_date`, `remarks`, `photo`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'SWM-0001', 3, 'household', 'krish', 'kundan', 'laxmi', 'mohit devi', 'krish', NULL, NULL, '0000-00-00-00', NULL, NULL, 9704674787, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 26.4783740, 87.2698590, NULL, NULL, 0, NULL, NULL, NULL, 'active', '2026-05-30', NULL, NULL, 1, '2026-05-30 17:55:01', '2026-05-30 17:55:01', NULL);


DROP TABLE IF EXISTS `defaulters`;
CREATE TABLE `defaulters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `bill_id` bigint(20) unsigned NOT NULL,
  `total_due` decimal(12,2) NOT NULL,
  `months_overdue` int(11) DEFAULT 0,
  `notice_sent` tinyint(1) DEFAULT 0,
  `notice_sent_date` date DEFAULT NULL,
  `disconnection_notice` tinyint(1) DEFAULT 0,
  `disconnection_date` date DEFAULT NULL,
  `action_taken` varchar(255) DEFAULT NULL,
  `status` enum('pending','noticed','disconnected','settled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consumer_id` (`consumer_id`),
  KEY `bill_id` (`bill_id`),
  CONSTRAINT `defaulters_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`),
  CONSTRAINT `defaulters_ibfk_2` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `designations`;
CREATE TABLE `designations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `designations_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `email_logs`;
CREATE TABLE `email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient` varchar(200) NOT NULL,
  `subject` varchar(300) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('sent','failed') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(50) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `designation_id` bigint(20) unsigned DEFAULT NULL,
  `full_name` varchar(200) NOT NULL,
  `father_name` varchar(200) DEFAULT NULL,
  `mother_name` varchar(200) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT 'male',
  `date_of_birth` date DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT 'single',
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `citizenship_no` varchar(100) DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `temporary_address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `employment_type` enum('permanent','temporary','contract','part_time','volunteer') DEFAULT 'permanent',
  `salary` decimal(12,2) DEFAULT 0.00,
  `bank_name` varchar(200) DEFAULT NULL,
  `bank_account_no` varchar(50) DEFAULT NULL,
  `pan_no` varchar(50) DEFAULT NULL,
  `emergency_contact_name` varchar(200) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `education` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `status` enum('active','inactive','resigned','terminated') DEFAULT 'active',
  `resignation_date` date DEFAULT NULL,
  `resignation_reason` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `designation_id` (`designation_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`),
  CONSTRAINT `employees_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `fiscal_years`;
CREATE TABLE `fiscal_years` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `year_code` varchar(20) NOT NULL,
  `label` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `status` enum('active','closed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `year_code` (`year_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `fiscal_years` (`id`, `year_code`, `label`, `start_date`, `end_date`, `is_current`, `status`, `created_at`) VALUES
(1, '2082-83', 'Fiscal Year 2082/83', '2025-07-17', '2026-07-16', 1, 'active', '2026-05-29 21:48:11');


DROP TABLE IF EXISTS `gis_layers`;
CREATE TABLE `gis_layers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `layer_type` enum('consumer','pipeline','tank','pump','valve','service_area','ward_boundary') NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#181CB8',
  `is_visible` tinyint(1) DEFAULT 1,
  `icon` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `gis_layers` (`id`, `name`, `layer_type`, `description`, `color`, `is_visible`, `icon`, `created_at`, `updated_at`) VALUES
(1, 'Consumer Locations', 'consumer', NULL, '#181CB8', 1, NULL, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(2, 'Pipeline Network', 'pipeline', NULL, '#2196F3', 1, NULL, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(3, 'Water Tanks', 'tank', NULL, '#4CAF50', 1, NULL, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(4, 'Pump Stations', 'pump', NULL, '#FF9800', 1, NULL, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(5, 'Valves', 'valve', NULL, '#F44336', 1, NULL, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(6, 'Service Area', 'service_area', NULL, '#9C27B0', 1, NULL, '2026-05-29 21:48:11', '2026-05-29 21:48:11');


DROP TABLE IF EXISTS `gis_markers`;
CREATE TABLE `gis_markers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `layer_id` bigint(20) unsigned NOT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `label` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `popup_content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `layer_id` (`layer_id`),
  KEY `idx_gis_location` (`latitude`,`longitude`),
  KEY `idx_gis_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `gis_markers_ibfk_1` FOREIGN KEY (`layer_id`) REFERENCES `gis_layers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `gis_shapes`;
CREATE TABLE `gis_shapes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `layer_id` bigint(20) unsigned NOT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `shape_type` enum('polygon','polyline','circle','rectangle') NOT NULL,
  `coordinates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`coordinates`)),
  `style` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`style`)),
  `label` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `layer_id` (`layer_id`),
  CONSTRAINT `gis_shapes_ibfk_1` FOREIGN KEY (`layer_id`) REFERENCES `gis_layers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_code` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('pipe','valve','fitting','meter','pump','chemical','tool','safety_equipment','office_supply','other') NOT NULL,
  `unit` varchar(50) NOT NULL,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `reorder_level` int(11) DEFAULT 10,
  `current_stock` decimal(12,2) DEFAULT 0.00,
  `min_stock` decimal(12,2) DEFAULT 0.00,
  `max_stock` decimal(12,2) DEFAULT 0.00,
  `location` varchar(200) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `idx_item_code` (`item_code`),
  KEY `idx_item_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(200) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_username` (`username`),
  KEY `idx_login_attempted` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `user_agent`, `success`, `attempted_at`) VALUES
(5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1, '2026-05-30 12:40:47'),
(6, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1, '2026-05-30 12:41:19'),
(7, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1, '2026-05-30 13:29:38'),
(8, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1, '2026-05-30 15:59:33'),
(9, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1, '2026-05-30 17:36:30'),
(10, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1, '2026-05-30 19:48:11'),
(11, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 1, '2026-05-30 19:48:19');


DROP TABLE IF EXISTS `meter_readings`;
CREATE TABLE `meter_readings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `meter_id` bigint(20) unsigned NOT NULL,
  `reading_date` date NOT NULL,
  `previous_reading` decimal(10,2) DEFAULT 0.00,
  `current_reading` decimal(10,2) NOT NULL,
  `consumption` decimal(10,2) DEFAULT 0.00,
  `reading_source` enum('manual','pos','automated') DEFAULT 'manual',
  `meter_photo` varchar(255) DEFAULT NULL,
  `gps_latitude` decimal(10,7) DEFAULT NULL,
  `gps_longitude` decimal(10,7) DEFAULT NULL,
  `is_estimated` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `read_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `read_by` (`read_by`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_reading_consumer` (`consumer_id`),
  KEY `idx_reading_date` (`reading_date`),
  KEY `idx_reading_meter` (`meter_id`),
  CONSTRAINT `meter_readings_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`),
  CONSTRAINT `meter_readings_ibfk_2` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`),
  CONSTRAINT `meter_readings_ibfk_3` FOREIGN KEY (`read_by`) REFERENCES `users` (`id`),
  CONSTRAINT `meter_readings_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `meter_replacements`;
CREATE TABLE `meter_replacements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `old_meter_id` bigint(20) unsigned NOT NULL,
  `new_meter_id` bigint(20) unsigned NOT NULL,
  `old_reading` decimal(10,2) NOT NULL,
  `new_reading` decimal(10,2) NOT NULL,
  `replacement_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `done_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consumer_id` (`consumer_id`),
  KEY `old_meter_id` (`old_meter_id`),
  KEY `new_meter_id` (`new_meter_id`),
  KEY `done_by` (`done_by`),
  CONSTRAINT `meter_replacements_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`),
  CONSTRAINT `meter_replacements_ibfk_2` FOREIGN KEY (`old_meter_id`) REFERENCES `meters` (`id`),
  CONSTRAINT `meter_replacements_ibfk_3` FOREIGN KEY (`new_meter_id`) REFERENCES `meters` (`id`),
  CONSTRAINT `meter_replacements_ibfk_4` FOREIGN KEY (`done_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `meters`;
CREATE TABLE `meters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meter_no` varchar(100) NOT NULL,
  `consumer_id` bigint(20) unsigned DEFAULT NULL,
  `meter_type` enum('domestic','commercial','bulk') DEFAULT 'domestic',
  `meter_brand` varchar(100) DEFAULT NULL,
  `meter_model` varchar(100) DEFAULT NULL,
  `meter_size` varchar(50) DEFAULT NULL,
  `initial_reading` decimal(10,2) DEFAULT 0.00,
  `installation_date` date DEFAULT NULL,
  `last_reading_date` timestamp NULL DEFAULT NULL,
  `last_reading` decimal(10,2) DEFAULT 0.00,
  `gps_latitude` decimal(10,7) DEFAULT NULL,
  `gps_longitude` decimal(10,7) DEFAULT NULL,
  `meter_photo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','defective','replaced','damaged') DEFAULT 'active',
  `qr_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meter_no` (`meter_no`),
  KEY `idx_meter_no` (`meter_no`),
  KEY `idx_meter_consumer` (`consumer_id`),
  KEY `idx_meter_status` (`status`),
  CONSTRAINT `meters_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `consumer_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('sms','email','system','bill_reminder','payment','complaint','service','alert') NOT NULL,
  `title` varchar(300) NOT NULL,
  `message` text NOT NULL,
  `channel` enum('sms','email','both','system') DEFAULT 'system',
  `reference_type` varchar(100) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','sent','failed','delivered') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consumer_id` (`consumer_id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_read` (`is_read`),
  KEY `idx_notif_type` (`type`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(300) NOT NULL,
  `short_name` varchar(100) DEFAULT NULL,
  `registration_no` varchar(100) DEFAULT NULL,
  `pan_no` varchar(50) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `ward_no` int(11) DEFAULT NULL,
  `municipality` varchar(200) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `fiscal_year_start` varchar(10) DEFAULT '01-17',
  `fiscal_year_end` varchar(10) DEFAULT '07-16',
  `currency_symbol` varchar(10) DEFAULT 'NRs.',
  `date_format` varchar(20) DEFAULT 'Y-m-d',
  `timezone` varchar(50) DEFAULT 'Asia/Kathmandu',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `organizations` (`id`, `name`, `short_name`, `registration_no`, `pan_no`, `logo`, `address`, `ward_no`, `municipality`, `district`, `province`, `phone`, `email`, `website`, `fiscal_year_start`, `fiscal_year_end`, `currency_symbol`, `date_format`, `timezone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Drinking Water & Sanitation Consumer Committee', 'DWSCC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01-17', '07-16', 'NRs.', 'Y-m-d', 'Asia/Kathmandu', 'active', '2026-05-29 21:48:11', '2026-05-29 21:48:11');


DROP TABLE IF EXISTS `ownership_transfers`;
CREATE TABLE `ownership_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `old_owner_id` bigint(20) unsigned DEFAULT NULL,
  `new_owner_name` varchar(200) NOT NULL,
  `new_owner_mobile` varchar(20) NOT NULL,
  `new_owner_citizenship` varchar(100) DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consumer_id` (`consumer_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `ownership_transfers_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ownership_transfers_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `payment_gateways`;
CREATE TABLE `payment_gateways` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(100) NOT NULL,
  `gateway_type` enum('esewa','khalti','fonepay','qr') NOT NULL,
  `merchant_id` varchar(255) DEFAULT NULL,
  `secret_key` varchar(255) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `api_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `is_test_mode` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(100) NOT NULL,
  `bill_id` bigint(20) unsigned DEFAULT NULL,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `discount` decimal(12,2) DEFAULT 0.00,
  `penalty_waived` decimal(12,2) DEFAULT 0.00,
  `net_amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','bank','esewa','khalti','fonepay','qr','cheque','online') DEFAULT 'cash',
  `payment_mode` enum('office','online','pos','agent') DEFAULT 'office',
  `bank_name` varchar(200) DEFAULT NULL,
  `cheque_no` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(200) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `reference_no` varchar(200) DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('completed','pending','failed','refunded') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `received_by` (`received_by`),
  KEY `idx_receipt_no` (`receipt_no`),
  KEY `idx_payment_bill` (`bill_id`),
  KEY `idx_payment_consumer` (`consumer_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_transaction_id` (`transaction_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`),
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `module` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`, `created_at`) VALUES
(1, 'View Dashboard', 'dashboard.view', 'Dashboard', 'View main dashboard', '2026-05-29 21:48:11'),
(2, 'View Analytics', 'analytics.view', 'Dashboard', 'View analytics charts', '2026-05-29 21:48:11'),
(3, 'Export Reports', 'reports.export', 'Dashboard', 'Export dashboard reports', '2026-05-29 21:48:11'),
(4, 'View Users', 'users.view', 'Users', 'View user list', '2026-05-29 21:48:11'),
(5, 'Create Users', 'users.create', 'Users', 'Create new users', '2026-05-29 21:48:11'),
(6, 'Edit Users', 'users.edit', 'Users', 'Edit existing users', '2026-05-29 21:48:11'),
(7, 'Delete Users', 'users.delete', 'Users', 'Delete users', '2026-05-29 21:48:11'),
(8, 'View Roles', 'roles.view', 'Users', 'View roles', '2026-05-29 21:48:11'),
(9, 'Create Roles', 'roles.create', 'Users', 'Create roles', '2026-05-29 21:48:11'),
(10, 'Edit Roles', 'roles.edit', 'Users', 'Edit roles', '2026-05-29 21:48:11'),
(11, 'Delete Roles', 'roles.delete', 'Users', 'Delete roles', '2026-05-29 21:48:11'),
(12, 'Assign Permissions', 'permissions.assign', 'Users', 'Assign permissions', '2026-05-29 21:48:11'),
(13, 'View Consumers', 'consumers.view', 'Consumers', 'View consumer list', '2026-05-29 21:48:11'),
(14, 'Create Consumers', 'consumers.create', 'Consumers', 'Register new consumers', '2026-05-29 21:48:11'),
(15, 'Edit Consumers', 'consumers.edit', 'Consumers', 'Edit consumer details', '2026-05-29 21:48:11'),
(16, 'Delete Consumers', 'consumers.delete', 'Consumers', 'Delete consumers', '2026-05-29 21:48:11'),
(17, 'Transfer Ownership', 'consumers.transfer', 'Consumers', 'Transfer consumer ownership', '2026-05-29 21:48:11'),
(18, 'View Employees', 'employees.view', 'Employees', 'View employee list', '2026-05-29 21:48:11'),
(19, 'Create Employees', 'employees.create', 'Employees', 'Create employees', '2026-05-29 21:48:11'),
(20, 'Edit Employees', 'employees.edit', 'Employees', 'Edit employees', '2026-05-29 21:48:11'),
(21, 'Delete Employees', 'employees.delete', 'Employees', 'Delete employees', '2026-05-29 21:48:11'),
(22, 'Mark Attendance', 'attendance.mark', 'Employees', 'Mark attendance', '2026-05-29 21:48:11'),
(23, 'View Bills', 'bills.view', 'Billing', 'View billing list', '2026-05-29 21:48:11'),
(24, 'Generate Bills', 'bills.generate', 'Billing', 'Generate bills', '2026-05-29 21:48:11'),
(25, 'Edit Bills', 'bills.edit', 'Billing', 'Edit bills', '2026-05-29 21:48:11'),
(26, 'Cancel Bills', 'bills.cancel', 'Billing', 'Cancel bills', '2026-05-29 21:48:11'),
(27, 'Record Payments', 'payments.record', 'Billing', 'Record payments', '2026-05-29 21:48:11'),
(28, 'View Payments', 'payments.view', 'Billing', 'View payments', '2026-05-29 21:48:11'),
(29, 'Refund Payments', 'payments.refund', 'Billing', 'Process refunds', '2026-05-29 21:48:11'),
(30, 'Manage Tariffs', 'tariffs.manage', 'Billing', 'Manage tariff rates', '2026-05-29 21:48:11'),
(31, 'View Defaulters', 'defaulters.view', 'Billing', 'View defaulter list', '2026-05-29 21:48:11'),
(32, 'View Meter Readings', 'readings.view', 'Meter', 'View meter readings', '2026-05-29 21:48:11'),
(33, 'Enter Readings', 'readings.enter', 'Meter', 'Enter meter readings', '2026-05-29 21:48:11'),
(34, 'Verify Readings', 'readings.verify', 'Meter', 'Verify meter readings', '2026-05-29 21:48:11'),
(35, 'View Complaints', 'complaints.view', 'Complaints', 'View complaints', '2026-05-29 21:48:11'),
(36, 'Create Complaints', 'complaints.create', 'Complaints', 'Register complaints', '2026-05-29 21:48:11'),
(37, 'Assign Complaints', 'complaints.assign', 'Complaints', 'Assign complaints', '2026-05-29 21:48:11'),
(38, 'Resolve Complaints', 'complaints.resolve', 'Complaints', 'Resolve complaints', '2026-05-29 21:48:11'),
(39, 'View Work Orders', 'workorders.view', 'Complaints', 'View work orders', '2026-05-29 21:48:11'),
(40, 'Create Work Orders', 'workorders.create', 'Complaints', 'Create work orders', '2026-05-29 21:48:11'),
(41, 'View Inventory', 'inventory.view', 'Inventory', 'View inventory', '2026-05-29 21:48:11'),
(42, 'Manage Items', 'inventory.items', 'Inventory', 'Manage inventory items', '2026-05-29 21:48:11'),
(43, 'Manage Stock In', 'stock.in', 'Inventory', 'Manage stock in', '2026-05-29 21:48:11'),
(44, 'Manage Stock Out', 'stock.out', 'Inventory', 'Manage stock out', '2026-05-29 21:48:11'),
(45, 'Manage Suppliers', 'suppliers.manage', 'Inventory', 'Manage suppliers', '2026-05-29 21:48:11'),
(46, 'View Assets', 'assets.view', 'Assets', 'View assets', '2026-05-29 21:48:11'),
(47, 'Create Assets', 'assets.create', 'Assets', 'Create assets', '2026-05-29 21:48:11'),
(48, 'Edit Assets', 'assets.edit', 'Assets', 'Edit assets', '2026-05-29 21:48:11'),
(49, 'Schedule Maintenance', 'maintenance.schedule', 'Assets', 'Schedule maintenance', '2026-05-29 21:48:11'),
(50, 'View GIS', 'gis.view', 'GIS', 'View GIS maps', '2026-05-29 21:48:11'),
(51, 'Edit GIS', 'gis.edit', 'GIS', 'Edit GIS data', '2026-05-29 21:48:11'),
(52, 'View Reports', 'reports.view', 'Reports', 'View reports', '2026-05-29 21:48:11'),
(53, 'Generate Reports', 'reports.generate', 'Reports', 'Generate reports', '2026-05-29 21:48:11'),
(54, 'Export PDF', 'exports.pdf', 'Reports', 'Export to PDF', '2026-05-29 21:48:11'),
(55, 'Export Excel', 'exports.excel', 'Reports', 'Export to Excel', '2026-05-29 21:48:11'),
(56, 'Export CSV', 'exports.csv', 'Reports', 'Export to CSV', '2026-05-29 21:48:11'),
(57, 'View Settings', 'settings.view', 'Settings', 'View system settings', '2026-05-29 21:48:11'),
(58, 'Edit Settings', 'settings.edit', 'Settings', 'Edit system settings', '2026-05-29 21:48:11'),
(59, 'View Audit Logs', 'audit.view', 'Settings', 'View audit logs', '2026-05-29 21:48:11'),
(60, 'Manage Backup', 'backup.manage', 'Settings', 'Manage backups', '2026-05-29 21:48:11');


DROP TABLE IF EXISTS `pipelines`;
CREATE TABLE `pipelines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned DEFAULT NULL,
  `pipe_type` enum('distribution','transmission','service') DEFAULT 'distribution',
  `material` enum('hdpe','gi','pvc','ductile_iron','steel','asbestos') DEFAULT 'hdpe',
  `diameter_mm` decimal(10,2) DEFAULT NULL,
  `length_meters` decimal(10,2) DEFAULT NULL,
  `start_location` text DEFAULT NULL,
  `end_location` text DEFAULT NULL,
  `start_latitude` decimal(10,7) DEFAULT NULL,
  `start_longitude` decimal(10,7) DEFAULT NULL,
  `end_latitude` decimal(10,7) DEFAULT NULL,
  `end_longitude` decimal(10,7) DEFAULT NULL,
  `installation_year` int(11) DEFAULT NULL,
  `pressure_rating` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','leak','damaged') DEFAULT 'active',
  `ward_no` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `pipelines_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `pos_sessions`;
CREATE TABLE `pos_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `total_readings` int(11) DEFAULT 0,
  `total_collection` decimal(12,2) DEFAULT 0.00,
  `status` enum('active','closed') DEFAULT 'active',
  `device_id` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `pos_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES
(1, 1, 2, '2026-05-29 21:48:11'),
(2, 1, 47, '2026-05-29 21:48:11'),
(3, 1, 48, '2026-05-29 21:48:11'),
(4, 1, 46, '2026-05-29 21:48:11'),
(5, 1, 22, '2026-05-29 21:48:11'),
(6, 1, 59, '2026-05-29 21:48:11'),
(7, 1, 60, '2026-05-29 21:48:11'),
(8, 1, 26, '2026-05-29 21:48:11'),
(9, 1, 25, '2026-05-29 21:48:11'),
(10, 1, 24, '2026-05-29 21:48:11'),
(11, 1, 23, '2026-05-29 21:48:11'),
(12, 1, 37, '2026-05-29 21:48:11'),
(13, 1, 36, '2026-05-29 21:48:11'),
(14, 1, 38, '2026-05-29 21:48:11'),
(15, 1, 35, '2026-05-29 21:48:11'),
(16, 1, 14, '2026-05-29 21:48:11'),
(17, 1, 16, '2026-05-29 21:48:11'),
(18, 1, 15, '2026-05-29 21:48:11'),
(19, 1, 17, '2026-05-29 21:48:11'),
(20, 1, 13, '2026-05-29 21:48:11'),
(21, 1, 1, '2026-05-29 21:48:11'),
(22, 1, 31, '2026-05-29 21:48:11'),
(23, 1, 19, '2026-05-29 21:48:11'),
(24, 1, 21, '2026-05-29 21:48:11'),
(25, 1, 20, '2026-05-29 21:48:11'),
(26, 1, 18, '2026-05-29 21:48:11'),
(27, 1, 56, '2026-05-29 21:48:11'),
(28, 1, 55, '2026-05-29 21:48:11'),
(29, 1, 54, '2026-05-29 21:48:11'),
(30, 1, 51, '2026-05-29 21:48:11'),
(31, 1, 50, '2026-05-29 21:48:11'),
(32, 1, 42, '2026-05-29 21:48:11'),
(33, 1, 41, '2026-05-29 21:48:11'),
(34, 1, 49, '2026-05-29 21:48:11'),
(35, 1, 27, '2026-05-29 21:48:11'),
(36, 1, 29, '2026-05-29 21:48:11'),
(37, 1, 28, '2026-05-29 21:48:11'),
(38, 1, 12, '2026-05-29 21:48:11'),
(39, 1, 33, '2026-05-29 21:48:11'),
(40, 1, 34, '2026-05-29 21:48:11'),
(41, 1, 32, '2026-05-29 21:48:11'),
(42, 1, 3, '2026-05-29 21:48:11'),
(43, 1, 53, '2026-05-29 21:48:11'),
(44, 1, 52, '2026-05-29 21:48:11'),
(45, 1, 9, '2026-05-29 21:48:11'),
(46, 1, 11, '2026-05-29 21:48:11'),
(47, 1, 10, '2026-05-29 21:48:11'),
(48, 1, 8, '2026-05-29 21:48:11'),
(49, 1, 58, '2026-05-29 21:48:11'),
(50, 1, 57, '2026-05-29 21:48:11'),
(51, 1, 43, '2026-05-29 21:48:11'),
(52, 1, 44, '2026-05-29 21:48:11'),
(53, 1, 45, '2026-05-29 21:48:11'),
(54, 1, 30, '2026-05-29 21:48:11'),
(55, 1, 5, '2026-05-29 21:48:11'),
(56, 1, 7, '2026-05-29 21:48:11'),
(57, 1, 6, '2026-05-29 21:48:11'),
(58, 1, 4, '2026-05-29 21:48:11'),
(59, 1, 40, '2026-05-29 21:48:11'),
(60, 1, 39, '2026-05-29 21:48:11');


DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Super Admin', 'super_admin', 'Full system access', 1, '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(2, 'Committee Admin', 'committee_admin', 'Committee level administration', 1, '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(3, 'Manager', 'manager', 'Operational management', 1, '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(4, 'Billing Officer', 'billing_officer', 'Billing operations', 1, '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(5, 'Meter Reader', 'meter_reader', 'Meter reading tasks', 1, '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(6, 'Technician', 'technician', 'Technical maintenance', 1, '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(7, 'Accountant', 'accountant', 'Financial management', 1, '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL),
(8, 'Citizen', 'citizen', 'Public user portal access', 1, '2026-05-29 21:48:11', '2026-05-29 21:48:11', NULL);


DROP TABLE IF EXISTS `security_logs`;
CREATE TABLE `security_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_security_event` (`event`),
  KEY `idx_security_created` (`created_at`),
  CONSTRAINT `security_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `security_logs` (`id`, `event`, `details`, `ip_address`, `user_agent`, `user_id`, `created_at`) VALUES
(1, 'failed_login', '{"username":"admin@swms.gov.np"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-30 12:36:44'),
(2, 'failed_login', '{"username":"admin@swms.gov.np"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-30 12:37:01'),
(3, 'failed_login', '{"username":"admin@swms.gov.np"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-30 12:37:51'),
(4, 'failed_login', '{"username":"admin@swms.gov.np"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-30 12:38:56');


DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_session_user` (`user_id`),
  KEY `idx_last_activity` (`last_activity`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `sms_logs`;
CREATE TABLE `sms_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `sender_id` varchar(20) DEFAULT NULL,
  `gateway_response` text DEFAULT NULL,
  `status` enum('sent','failed','delivered') DEFAULT 'sent',
  `cost` decimal(8,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sms_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `stock_in`;
CREATE TABLE `stock_in` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(100) NOT NULL,
  `supplier_id` bigint(20) unsigned DEFAULT NULL,
  `bill_no` varchar(100) DEFAULT NULL,
  `bill_date` date DEFAULT NULL,
  `received_date` date NOT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `supplier_id` (`supplier_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `stock_in_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `stock_in_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `stock_in_items`;
CREATE TABLE `stock_in_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_in_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `stock_in_id` (`stock_in_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `stock_in_items_ibfk_1` FOREIGN KEY (`stock_in_id`) REFERENCES `stock_in` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_in_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `stock_out`;
CREATE TABLE `stock_out` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `issue_no` varchar(100) NOT NULL,
  `issued_to` varchar(200) DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `work_order_id` bigint(20) unsigned DEFAULT NULL,
  `issue_date` date NOT NULL,
  `issued_by` bigint(20) unsigned DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `issue_no` (`issue_no`),
  KEY `department_id` (`department_id`),
  KEY `employee_id` (`employee_id`),
  KEY `work_order_id` (`work_order_id`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `stock_out_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `stock_out_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `stock_out_ibfk_3` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`),
  CONSTRAINT `stock_out_ibfk_4` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `stock_out_items`;
CREATE TABLE `stock_out_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_out_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `total_price` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `stock_out_id` (`stock_out_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `stock_out_items_ibfk_1` FOREIGN KEY (`stock_out_id`) REFERENCES `stock_out` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_out_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_code` varchar(100) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `pan_no` varchar(50) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `payment_terms` varchar(200) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_code` (`supplier_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(200) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_encrypted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`),
  KEY `idx_setting_group` (`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `is_encrypted`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Smart Water Management System', 'general', 'Site title', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(2, 'site_description', 'Drinking Water & Sanitation Consumer Committee Management System', 'general', 'Site description', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(3, 'default_currency', 'NRs.', 'billing', 'Default currency symbol', 0, '2026-05-29 21:48:11', '2026-05-30 16:06:01'),
(4, 'billing_cycle_days', 30, 'billing', 'Billing cycle in days', 0, '2026-05-29 21:48:11', '2026-05-30 16:06:01'),
(5, 'due_date_days', 15, 'billing', 'Due date days after billing', 0, '2026-05-29 21:48:11', '2026-05-30 16:06:01'),
(6, 'penalty_percent', 5.00, 'billing', 'Late payment penalty percentage', 0, '2026-05-29 21:48:11', '2026-05-30 16:06:01'),
(7, 'vat_percent', 00, 'billing', 'VAT percentage', 0, '2026-05-29 21:48:11', '2026-05-30 16:06:01'),
(8, 'meter_rent', 50.00, 'billing', 'Default meter rent', 0, '2026-05-29 21:48:11', '2026-05-30 16:06:01'),
(9, 'sewerage_fee', 0.00, 'billing', 'Default sewerage fee', 0, '2026-05-29 21:48:11', '2026-05-30 16:06:01'),
(10, 'smtp_host', '', 'email', 'SMTP host', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(11, 'smtp_port', 587, 'email', 'SMTP port', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(12, 'smtp_username', '', 'email', 'SMTP username', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(13, 'smtp_password', '', 'email', 'SMTP password', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(14, 'sms_api_key', '', 'sms', 'SMS API key', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(15, 'sms_sender_id', 'SWMS', 'sms', 'SMS sender ID', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(16, 'map_center_lat', 27.7172, 'gis', 'Map center latitude', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(17, 'map_center_lng', 85.3240, 'gis', 'Map center longitude', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(18, 'map_zoom', 13, 'gis', 'Default map zoom level', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(19, 'default_page_size', 25, 'general', 'Default pagination size', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(20, 'date_format', 'Y-m-d', 'general', 'Date format', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(21, 'timezone', 'Asia/Kathmandu', 'general', 'System timezone', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11'),
(22, 'maintenance_mode', 0, 'general', 'Maintenance mode', 0, '2026-05-29 21:48:11', '2026-05-29 21:48:11');


DROP TABLE IF EXISTS `tariffs`;
CREATE TABLE `tariffs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `connection_type` enum('household','commercial','institutional','all') DEFAULT 'all',
  `min_consumption` decimal(10,2) DEFAULT 0.00,
  `max_consumption` decimal(10,2) DEFAULT 999999.00,
  `base_fee` decimal(10,2) DEFAULT 0.00,
  `rate_per_unit` decimal(10,2) DEFAULT 0.00,
  `min_charge` decimal(10,2) DEFAULT 0.00,
  `meter_rent` decimal(10,2) DEFAULT 0.00,
  `sewerage_fee` decimal(10,2) DEFAULT 0.00,
  `vat_percent` decimal(5,2) DEFAULT 0.00,
  `penalty_percent` decimal(5,2) DEFAULT 5.00,
  `penalty_days` int(11) DEFAULT 15,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tariff_category` (`category_id`),
  KEY `idx_tariff_type` (`connection_type`),
  CONSTRAINT `tariffs_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `consumer_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `email` varchar(200) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT 'male',
  `address` text DEFAULT NULL,
  `designation` varchar(200) DEFAULT NULL,
  `department` varchar(200) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `is_locked` tinyint(1) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_expires` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `role_id` (`role_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `username`, `password`, `phone`, `avatar`, `gender`, `address`, `designation`, `department`, `status`, `is_locked`, `locked_until`, `login_attempts`, `last_login`, `last_ip`, `remember_token`, `remember_expires`, `reset_token`, `reset_expires`, `email_verified_at`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Super Admin', 'admin@swms.gov.np', 'admin', '$2y$12$N9/xfECHl4cPWmUSJP62L.uqi0rcQdF3qf./W83rmpKqFmf7VEAC.', NULL, NULL, 'male', NULL, NULL, NULL, 'active', 0, NULL, 0, '2026-05-30 19:48:19', '::1', '$2y$10$7aRnTByjSbJzVM5Uxd.eNOnNVhmVmcbRLDBm6vjOri7ZQ83BwpQIa', '2026-06-29 15:59:32', NULL, NULL, NULL, NULL, '2026-05-29 21:48:11', '2026-05-30 19:48:19', NULL);


DROP TABLE IF EXISTS `water_quality`;
CREATE TABLE `water_quality` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `sample_date` date NOT NULL,
  `test_date` date DEFAULT NULL,
  `ph_level` decimal(4,2) DEFAULT NULL,
  `turbidity` decimal(8,2) DEFAULT NULL,
  `chlorine` decimal(8,2) DEFAULT NULL,
  `total_coliform` int(11) DEFAULT NULL,
  `e_coli` int(11) DEFAULT NULL,
  `result` enum('satisfactory','unsatisfactory','needs_attention') DEFAULT 'satisfactory',
  `tested_by` varchar(200) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `water_tanks`;
CREATE TABLE `water_tanks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned DEFAULT NULL,
  `tank_type` enum('overhead','underground','ground','elevated') DEFAULT 'overhead',
  `capacity_liters` decimal(14,2) DEFAULT NULL,
  `height_meters` decimal(10,2) DEFAULT NULL,
  `diameter_meters` decimal(10,2) DEFAULT NULL,
  `material` enum('rcc','steel','plastic','brick') DEFAULT 'rcc',
  `water_source` varchar(200) DEFAULT NULL,
  `ward_no` int(11) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `status` enum('operational','maintenance','damaged') DEFAULT 'operational',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `water_tanks_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `work_orders`;
CREATE TABLE `work_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `complaint_id` bigint(20) unsigned DEFAULT NULL,
  `work_order_no` varchar(100) NOT NULL,
  `title` varchar(300) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `completion_notes` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `materials_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`materials_used`)),
  `cost_estimate` decimal(12,2) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_order_no` (`work_order_no`),
  KEY `complaint_id` (`complaint_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `work_orders_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `work_orders_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

