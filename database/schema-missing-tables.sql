
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




