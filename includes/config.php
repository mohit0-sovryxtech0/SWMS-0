<?php
// SMWMS - Smart Water Management System
// Configuration File

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Base URL (Update this for production)
define('BASE_URL', 'http://localhost/CMS%20000/');
define('ADMIN_URL', BASE_URL . 'admin/');
define('CITIZEN_URL', BASE_URL . 'citizen/');
define('API_URL', BASE_URL . 'api/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Physical Paths
define('ROOT_PATH', realpath(__DIR__ . '/../') . '/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('ADMIN_PATH', ROOT_PATH . 'admin/');
define('CITIZEN_PATH', ROOT_PATH . 'citizen/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('LOGS_PATH', ROOT_PATH . 'logs/');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'swms_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'Smart Water Management System');
define('APP_SHORT', 'SWMS');
define('APP_VERSION', '1.0.0');
define('APP_ORG', 'Drinking Water & Sanitation Consumer Committee');
define('APP_ADDRESS', 'Kathmandu, Nepal');
define('APP_PHONE', '01-4XXXXXX');
define('APP_COUNTRY', 'Nepal');

// Security
define('ENCRYPTION_KEY', 'swms_enc_key_2026_secure_hash');
define('TOKEN_EXPIRY', 3600);
define('SESSION_LIFETIME', 86400);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900);

// Session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_start();

// Timezone
date_default_timezone_set('Asia/Kathmandu');

// Upload Limits
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,csv');

// Pagination
define('RECORDS_PER_PAGE', 25);

// Email (configure for production)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@swms.gov.np');
define('SMTP_FROM_NAME', APP_NAME);

// SMS (configure for production)
define('SMS_API_KEY', '');
define('SMS_API_URL', '');
define('SMS_SENDER_ID', 'SWMS');

// Payment Gateway (configure for production)
define('ESEWA_MERCHANT_ID', '');
define('ESEWA_SECRET_KEY', '');
define('KHALTI_MERCHANT_ID', '');
define('KHALTI_SECRET_KEY', '');
define('FONEPAY_MERCHANT_ID', '');
define('FONEPAY_SECRET_KEY', '');

// GIS
define('MAP_CENTER_LAT', 27.7172);
define('MAP_CENTER_LNG', 85.3240);
define('MAP_ZOOM', 13);

// Default Pagination
define('DEFAULT_PAGE_SIZE', 25);

// Auto-load helpers
require_once INCLUDES_PATH . 'database.php';
require_once INCLUDES_PATH . 'functions.php';
require_once INCLUDES_PATH . 'auth.php';
require_once INCLUDES_PATH . 'rbac.php';
require_once INCLUDES_PATH . 'security.php';
require_once INCLUDES_PATH . 'validation.php';
