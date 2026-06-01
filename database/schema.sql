-- ============================================================
-- SMART WATER MANAGEMENT SYSTEM (SWMS)
-- Complete Database Schema
-- Version: 1.0.0
-- Engine: MySQL 8+
-- ============================================================

CREATE DATABASE IF NOT EXISTS swms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE swms_db;

-- ============================================================
-- 1. USER MANAGEMENT TABLES (7 tables)
-- ============================================================

-- 1.1 Roles
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- 3.8 Water Samples / Quality
CREATE TABLE water_quality (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consumer_id BIGINT UNSIGNED,
    location VARCHAR(255),
    sample_date DATE NOT NULL,
    test_date DATE,
    ph_level DECIMAL(4,2),
    turbidity DECIMAL(8,2),
    chlorine DECIMAL(8,2),
    total_coliform INT,
    e_coli INT,
    result ENUM('satisfactory', 'unsatisfactory', 'needs_attention') DEFAULT 'satisfactory',
    tested_by VARCHAR(200),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 4. EMPLOYEE MANAGEMENT TABLES (4 tables)
-- ============================================================

-- 4.1 Departments
CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50) UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- 4.2 Designations
CREATE TABLE designations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    department_id BIGINT UNSIGNED,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

-- 4.3 Employees
CREATE TABLE employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(50) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED,
    department_id BIGINT UNSIGNED,
    designation_id BIGINT UNSIGNED,
    full_name VARCHAR(200) NOT NULL,
    father_name VARCHAR(200),
    mother_name VARCHAR(200),
    gender ENUM('male', 'female', 'other') DEFAULT 'male',
    date_of_birth DATE,
    marital_status ENUM('single', 'married', 'divorced', 'widowed') DEFAULT 'single',
    phone VARCHAR(20),
    mobile VARCHAR(20),
    email VARCHAR(200),
    citizenship_no VARCHAR(100),
    permanent_address TEXT,
    temporary_address TEXT,
    photo VARCHAR(255),
    joining_date DATE,
    employment_type ENUM('permanent', 'temporary', 'contract', 'part_time', 'volunteer') DEFAULT 'permanent',
    salary DECIMAL(12,2) DEFAULT 0,
    bank_name VARCHAR(200),
    bank_account_no VARCHAR(50),
    pan_no VARCHAR(50),
    emergency_contact_name VARCHAR(200),
    emergency_contact_phone VARCHAR(20),
    education TEXT,
    experience TEXT,
    skills TEXT,
    status ENUM('active', 'inactive', 'resigned', 'terminated') DEFAULT 'active',
    resignation_date DATE,
    resignation_reason TEXT,
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (designation_id) REFERENCES designations(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 4.4 Attendance
CREATE TABLE attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    check_in TIME,
    check_out TIME,
    status ENUM('present', 'absent', 'late', 'half_day', 'leave', 'holiday') DEFAULT 'present',
    remarks TEXT,
    marked_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (marked_by) REFERENCES users(id),
    UNIQUE KEY unique_attendance (employee_id, date)
) ENGINE=InnoDB;

-- ============================================================
-- 5. BILLING & REVENUE TABLES (9 tables)
-- ============================================================

-- 5.1 Tariff / Rate Plans
CREATE TABLE tariffs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    category_id BIGINT UNSIGNED,
    connection_type ENUM('household', 'commercial', 'institutional', 'all') DEFAULT 'all',
    min_consumption DECIMAL(10,2) DEFAULT 0,
    max_consumption DECIMAL(10,2) DEFAULT 999999,
    base_fee DECIMAL(10,2) DEFAULT 0,
    rate_per_unit DECIMAL(10,2) DEFAULT 0,
    min_charge DECIMAL(10,2) DEFAULT 0,
    meter_rent DECIMAL(10,2) DEFAULT 0,
    sewerage_fee DECIMAL(10,2) DEFAULT 0,
    vat_percent DECIMAL(5,2) DEFAULT 0,
    penalty_percent DECIMAL(5,2) DEFAULT 5.00,
    penalty_days INT DEFAULT 15,
    effective_from DATE NOT NULL,
    effective_to DATE,
    is_current TINYINT(1) DEFAULT 1,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (category_id) REFERENCES consumer_categories(id),
    INDEX idx_tariff_category (category_id),
    INDEX idx_tariff_type (connection_type)
) ENGINE=InnoDB;

-- 5.2 Bills
CREATE TABLE bills (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_no VARCHAR(100) NOT NULL UNIQUE,
    consumer_id BIGINT UNSIGNED NOT NULL,
    meter_id BIGINT UNSIGNED,
    tariff_id BIGINT UNSIGNED,
    fiscal_year_id BIGINT UNSIGNED,
    bill_date DATE,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    due_date DATE NOT NULL,
    previous_reading DECIMAL(10,2) DEFAULT 0,
    current_reading DECIMAL(10,2) DEFAULT 0,
    consumption DECIMAL(10,2) DEFAULT 0,
    base_fee DECIMAL(12,2) DEFAULT 0,
    consumption_charge DECIMAL(12,2) DEFAULT 0,
    meter_rent DECIMAL(12,2) DEFAULT 0,
    sewerage_fee DECIMAL(12,2) DEFAULT 0,
    vat_amount DECIMAL(12,2) DEFAULT 0,
    penalty_amount DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    due_amount DECIMAL(12,2) DEFAULT 0,
    bill_type ENUM('metered', 'flat', 'estimated') DEFAULT 'metered',
    status ENUM('pending', 'paid', 'partial', 'overdue', 'cancelled') DEFAULT 'pending',
    is_read TINYINT(1) DEFAULT 0,
    generated_by BIGINT UNSIGNED,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    cancel_reason TEXT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id),
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE SET NULL,
    FOREIGN KEY (tariff_id) REFERENCES tariffs(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    FOREIGN KEY (generated_by) REFERENCES users(id),
    INDEX idx_bill_no (bill_no),
    INDEX idx_bill_consumer (consumer_id),
    INDEX idx_bill_status (status),
    INDEX idx_bill_due_date (due_date),
    INDEX idx_bill_period (billing_period_start, billing_period_end)
) ENGINE=InnoDB;

-- 5.3 Payments
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(100) NOT NULL UNIQUE,
    bill_id BIGINT UNSIGNED,
    consumer_id BIGINT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    discount DECIMAL(12,2) DEFAULT 0,
    penalty_waived DECIMAL(12,2) DEFAULT 0,
    net_amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'bank', 'esewa', 'khalti', 'fonepay', 'qr', 'cheque', 'online') DEFAULT 'cash',
    payment_mode ENUM('office', 'online', 'pos', 'agent') DEFAULT 'office',
    bank_name VARCHAR(200),
    cheque_no VARCHAR(100),
    transaction_id VARCHAR(200),
    gateway_response JSON,
    reference_no VARCHAR(200),
    received_by BIGINT UNSIGNED,
    remarks TEXT,
    status ENUM('completed', 'pending', 'failed', 'refunded') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE SET NULL,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_receipt_no (receipt_no),
    INDEX idx_payment_bill (bill_id),
    INDEX idx_payment_consumer (consumer_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_payment_method (payment_method),
    INDEX idx_transaction_id (transaction_id)
) ENGINE=InnoDB;

-- 5.4 Payment Gateways
CREATE TABLE payment_gateways (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway_name VARCHAR(100) NOT NULL,
    gateway_type ENUM('esewa', 'khalti', 'fonepay', 'qr') NOT NULL,
    merchant_id VARCHAR(255),
    secret_key VARCHAR(255),
    api_key VARCHAR(255),
    api_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 0,
    is_test_mode TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5.5 Bill Payments (bill-payment pivot for partial/full)
CREATE TABLE bill_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    UNIQUE KEY unique_bill_payment (bill_id, payment_id)
) ENGINE=InnoDB;

-- 5.6 Defaulters
CREATE TABLE defaulters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consumer_id BIGINT UNSIGNED NOT NULL,
    bill_id BIGINT UNSIGNED NOT NULL,
    total_due DECIMAL(12,2) NOT NULL,
    months_overdue INT DEFAULT 0,
    notice_sent TINYINT(1) DEFAULT 0,
    notice_sent_date DATE,
    disconnection_notice TINYINT(1) DEFAULT 0,
    disconnection_date DATE,
    action_taken VARCHAR(255),
    status ENUM('pending', 'noticed', 'disconnected', 'settled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id),
    FOREIGN KEY (bill_id) REFERENCES bills(id)
) ENGINE=InnoDB;

-- 5.7 Meter Readings
CREATE TABLE meter_readings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consumer_id BIGINT UNSIGNED NOT NULL,
    meter_id BIGINT UNSIGNED NOT NULL,
    reading_date DATE NOT NULL,
    previous_reading DECIMAL(10,2) DEFAULT 0,
    current_reading DECIMAL(10,2) NOT NULL,
    consumption DECIMAL(10,2) DEFAULT 0,
    consumption_flag ENUM('normal', 'high', 'low', 'zero') DEFAULT 'normal',
    reading_source ENUM('manual', 'pos', 'automated') DEFAULT 'manual',
    meter_photo VARCHAR(255),
    gps_latitude DECIMAL(10,7),
    gps_longitude DECIMAL(10,7),
    is_estimated TINYINT(1) DEFAULT 0,
    is_verified TINYINT(1) DEFAULT 0,
    verified_by BIGINT UNSIGNED,
    verified_at TIMESTAMP NULL,
    remarks TEXT,
    read_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id),
    FOREIGN KEY (meter_id) REFERENCES meters(id),
    FOREIGN KEY (read_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_reading_consumer (consumer_id),
    INDEX idx_reading_date (reading_date),
    INDEX idx_reading_meter (meter_id)
) ENGINE=InnoDB;

-- 5.8 POS Sessions
CREATE TABLE pos_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    total_readings INT DEFAULT 0,
    total_collection DECIMAL(12,2) DEFAULT 0,
    status ENUM('active', 'closed') DEFAULT 'active',
    device_id VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. COMPLAINT MANAGEMENT TABLES (5 tables)
-- ============================================================

-- 6.1 Complaint Categories
CREATE TABLE complaint_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE,
    description TEXT,
    sla_hours INT DEFAULT 24,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- 6.2 Complaints
CREATE TABLE complaints (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(100) NOT NULL UNIQUE,
    consumer_id BIGINT UNSIGNED,
    citizen_name VARCHAR(200),
    citizen_phone VARCHAR(20),
    citizen_email VARCHAR(200),
    category_id BIGINT UNSIGNED,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    subject VARCHAR(300) NOT NULL,
    description TEXT NOT NULL,
    location TEXT,
    ward_no INT,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    attachment VARCHAR(255),
    assigned_to BIGINT UNSIGNED,
    assigned_at DATETIME,
    resolved_at DATETIME,
    resolution_notes TEXT,
    status ENUM('open', 'in_progress', 'resolved', 'closed', 'reopened') DEFAULT 'open',
    closed_by BIGINT UNSIGNED,
    closing_notes TEXT,
    is_public TINYINT(1) DEFAULT 0,
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES complaint_categories(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (closed_by) REFERENCES users(id),
    INDEX idx_ticket_no (ticket_no),
    INDEX idx_complaint_status (status),
    INDEX idx_complaint_priority (priority),
    INDEX idx_complaint_consumer (consumer_id),
    INDEX idx_complaint_assigned (assigned_to)
) ENGINE=InnoDB;

-- 6.3 Complaint Updates
CREATE TABLE complaint_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    complaint_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED,
    status VARCHAR(50),
    message TEXT NOT NULL,
    is_public TINYINT(1) DEFAULT 1,
    attachment VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- 6.4 Work Orders
CREATE TABLE work_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    complaint_id BIGINT UNSIGNED,
    work_order_no VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(300) NOT NULL,
    description TEXT,
    assigned_to BIGINT UNSIGNED,
    assigned_by BIGINT UNSIGNED,
    assigned_at DATETIME,
    start_date DATE,
    end_date DATE,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    completion_notes TEXT,
    completed_at DATETIME,
    materials_used JSON,
    cost_estimate DECIMAL(12,2),
    actual_cost DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 6.5 Complaint Feedback
CREATE TABLE complaint_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    complaint_id BIGINT UNSIGNED NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    feedback TEXT,
    submitted_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 7. INVENTORY MANAGEMENT TABLES (6 tables)
-- ============================================================

-- 7.1 Inventory Items
CREATE TABLE inventory_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category ENUM('pipe', 'valve', 'fitting', 'meter', 'pump', 'chemical', 'tool', 'safety_equipment', 'office_supply', 'other') NOT NULL,
    unit VARCHAR(50) NOT NULL,
    unit_price DECIMAL(12,2) DEFAULT 0,
    reorder_level INT DEFAULT 10,
    current_stock DECIMAL(12,2) DEFAULT 0,
    min_stock DECIMAL(12,2) DEFAULT 0,
    max_stock DECIMAL(12,2) DEFAULT 0,
    location VARCHAR(200),
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_item_code (item_code),
    INDEX idx_item_category (category)
) ENGINE=InnoDB;

-- 7.2 Suppliers
CREATE TABLE suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(100) UNIQUE,
    name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(200),
    phone VARCHAR(20),
    mobile VARCHAR(20),
    email VARCHAR(200),
    address TEXT,
    pan_no VARCHAR(50),
    website VARCHAR(200),
    contract_start_date DATE,
    contract_end_date DATE,
    payment_terms VARCHAR(200),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- 7.3 Stock In
CREATE TABLE stock_in (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(100) NOT NULL UNIQUE,
    supplier_id BIGINT UNSIGNED,
    bill_no VARCHAR(100),
    bill_date DATE,
    received_date DATE NOT NULL,
    received_by BIGINT UNSIGNED,
    notes TEXT,
    total_amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 7.4 Stock In Items
CREATE TABLE stock_in_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_in_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    batch_no VARCHAR(100),
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_in_id) REFERENCES stock_in(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB;

-- 7.5 Stock Out
CREATE TABLE stock_out (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_no VARCHAR(100) NOT NULL UNIQUE,
    issued_to VARCHAR(200),
    department_id BIGINT UNSIGNED,
    employee_id BIGINT UNSIGNED,
    work_order_id BIGINT UNSIGNED,
    issue_date DATE NOT NULL,
    issued_by BIGINT UNSIGNED,
    purpose TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (work_order_id) REFERENCES work_orders(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 7.6 Stock Out Items
CREATE TABLE stock_out_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_out_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) DEFAULT 0,
    total_price DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_out_id) REFERENCES stock_out(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB;

-- ============================================================
-- 8. ASSET MANAGEMENT TABLES (6 tables)
-- ============================================================

-- 8.1 Asset Categories
CREATE TABLE asset_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- 8.2 Assets
CREATE TABLE assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(100) NOT NULL UNIQUE,
    category_id BIGINT UNSIGNED NOT NULL,
    asset_type ENUM('water_tank', 'pipeline', 'pump', 'valve', 'meter', 'vehicle', 'building', 'equipment', 'other') NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    location TEXT,
    ward_no INT,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    purchase_date DATE,
    purchase_cost DECIMAL(14,2) DEFAULT 0,
    current_value DECIMAL(14,2) DEFAULT 0,
    warranty_expiry DATE,
    life_span_years INT,
    installation_date DATE,
    manufacturer VARCHAR(200),
    model_no VARCHAR(100),
    serial_no VARCHAR(100),
    capacity VARCHAR(100),
    status ENUM('operational', 'maintenance', 'damaged', 'decommissioned', 'under_construction') DEFAULT 'operational',
    image VARCHAR(255),
    documents JSON,
    assigned_to BIGINT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (category_id) REFERENCES asset_categories(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    INDEX idx_asset_code (asset_code),
    INDEX idx_asset_type (asset_type),
    INDEX idx_asset_status (status),
    INDEX idx_asset_location (latitude, longitude)
) ENGINE=InnoDB;

-- 8.3 Asset Maintenance
CREATE TABLE asset_maintenance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    maintenance_type ENUM('routine', 'repair', 'emergency', 'overhaul') DEFAULT 'routine',
    title VARCHAR(300) NOT NULL,
    description TEXT,
    scheduled_date DATE,
    completion_date DATE,
    cost DECIMAL(12,2) DEFAULT 0,
    performed_by VARCHAR(200),
    vendor VARCHAR(200),
    notes TEXT,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8.4 Asset Repair History
CREATE TABLE asset_repairs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    repair_date DATE NOT NULL,
    description TEXT NOT NULL,
    cost DECIMAL(12,2) DEFAULT 0,
    vendor VARCHAR(200),
    parts_replaced TEXT,
    downtime_hours INT,
    reported_by BIGINT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 8.5 Pipeline Network
CREATE TABLE pipelines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED,
    pipe_type ENUM('distribution', 'transmission', 'service') DEFAULT 'distribution',
    material ENUM('hdpe', 'gi', 'pvc', 'ductile_iron', 'steel', 'asbestos') DEFAULT 'hdpe',
    diameter_mm DECIMAL(10,2),
    length_meters DECIMAL(10,2),
    start_location TEXT,
    end_location TEXT,
    start_latitude DECIMAL(10,7),
    start_longitude DECIMAL(10,7),
    end_latitude DECIMAL(10,7),
    end_longitude DECIMAL(10,7),
    installation_year INT,
    pressure_rating VARCHAR(50),
    status ENUM('active', 'inactive', 'leak', 'damaged') DEFAULT 'active',
    ward_no INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8.6 Water Tanks
CREATE TABLE water_tanks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED,
    tank_type ENUM('overhead', 'underground', 'ground', 'elevated') DEFAULT 'overhead',
    capacity_liters DECIMAL(14,2),
    height_meters DECIMAL(10,2),
    diameter_meters DECIMAL(10,2),
    material ENUM('rcc', 'steel', 'plastic', 'brick') DEFAULT 'rcc',
    water_source VARCHAR(200),
    ward_no INT,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    status ENUM('operational', 'maintenance', 'damaged') DEFAULT 'operational',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 9. GIS / MAPPING TABLES (3 tables)
-- ============================================================

-- 9.1 GIS Layers
CREATE TABLE gis_layers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    layer_type ENUM('consumer', 'pipeline', 'tank', 'pump', 'valve', 'service_area', 'ward_boundary') NOT NULL,
    description TEXT,
    color VARCHAR(20) DEFAULT '#181CB8',
    is_visible TINYINT(1) DEFAULT 1,
    icon VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 9.2 GIS Markers
CREATE TABLE gis_markers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    layer_id BIGINT UNSIGNED NOT NULL,
    reference_id BIGINT UNSIGNED,
    reference_type VARCHAR(100),
    label VARCHAR(200),
    description TEXT,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    icon VARCHAR(100),
    color VARCHAR(20),
    popup_content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (layer_id) REFERENCES gis_layers(id) ON DELETE CASCADE,
    INDEX idx_gis_location (latitude, longitude),
    INDEX idx_gis_reference (reference_type, reference_id)
) ENGINE=InnoDB;

-- 9.3 GIS Shapes (polygons/lines)
CREATE TABLE gis_shapes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    layer_id BIGINT UNSIGNED NOT NULL,
    reference_id BIGINT UNSIGNED,
    shape_type ENUM('polygon', 'polyline', 'circle', 'rectangle') NOT NULL,
    coordinates JSON NOT NULL,
    style JSON,
    label VARCHAR(200),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (layer_id) REFERENCES gis_layers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 10. NOTIFICATION TABLES (3 tables)
-- ============================================================

-- 10.1 Notifications
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED,
    consumer_id BIGINT UNSIGNED,
    type ENUM('sms', 'email', 'system', 'bill_reminder', 'payment', 'complaint', 'service', 'alert') NOT NULL,
    title VARCHAR(300) NOT NULL,
    message TEXT NOT NULL,
    channel ENUM('sms', 'email', 'both', 'system') DEFAULT 'system',
    reference_type VARCHAR(100),
    reference_id BIGINT UNSIGNED,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    status ENUM('pending', 'sent', 'failed', 'delivered') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id) ON DELETE SET NULL,
    INDEX idx_notif_user (user_id),
    INDEX idx_notif_read (is_read),
    INDEX idx_notif_type (type)
) ENGINE=InnoDB;

-- 10.2 SMS Logs
CREATE TABLE sms_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    sender_id VARCHAR(20),
    gateway_response TEXT,
    status ENUM('sent', 'failed', 'delivered') DEFAULT 'sent',
    cost DECIMAL(8,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_phone (phone)
) ENGINE=InnoDB;

-- 10.3 Email Logs
CREATE TABLE email_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(200) NOT NULL,
    subject VARCHAR(300),
    message TEXT,
    attachment VARCHAR(255),
    status ENUM('sent', 'failed') DEFAULT 'sent',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 11. SYSTEM SETTINGS TABLES (3 tables)
-- ============================================================

-- 11.1 System Settings
CREATE TABLE system_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(200) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(100) NOT NULL,
    description TEXT,
    is_encrypted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key),
    INDEX idx_setting_group (setting_group)
) ENGINE=InnoDB;

-- 11.2 Audit Trail (for system changes)
CREATE TABLE audit_trail (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100) NOT NULL,
    reference_type VARCHAR(100),
    reference_id BIGINT UNSIGNED,
    description TEXT,
    old_value JSON,
    new_value JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_module (module),
    INDEX idx_audit_reference (reference_type, reference_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- 11.3 Backup Logs
CREATE TABLE backup_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('full', 'partial', 'manual') DEFAULT 'full',
    file_name VARCHAR(255),
    file_size BIGINT,
    status ENUM('success', 'failed', 'in_progress') DEFAULT 'in_progress',
    error_message TEXT,
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- 12. SESSIONS TABLE
-- ============================================================
CREATE TABLE sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_session_user (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default Roles
INSERT INTO roles (name, slug, description, is_system) VALUES
('Super Admin', 'super_admin', 'Full system access', 1),
('Committee Admin', 'committee_admin', 'Committee level administration', 1),
('Manager', 'manager', 'Operational management', 1),
('Billing Officer', 'billing_officer', 'Billing operations', 1),
('Meter Reader', 'meter_reader', 'Meter reading tasks', 1),
('Technician', 'technician', 'Technical maintenance', 1),
('Accountant', 'accountant', 'Financial management', 1),
('Citizen', 'citizen', 'Public user portal access', 1);

-- Default Permissions
INSERT INTO permissions (name, slug, module, description) VALUES
('View Dashboard', 'dashboard.view', 'Dashboard', 'View main dashboard'),
('View Analytics', 'analytics.view', 'Dashboard', 'View analytics charts'),
('Export Reports', 'reports.export', 'Dashboard', 'Export dashboard reports'),

('View Users', 'users.view', 'Users', 'View user list'),
('Create Users', 'users.create', 'Users', 'Create new users'),
('Edit Users', 'users.edit', 'Users', 'Edit existing users'),
('Delete Users', 'users.delete', 'Users', 'Delete users'),
('View Roles', 'roles.view', 'Users', 'View roles'),
('Create Roles', 'roles.create', 'Users', 'Create roles'),
('Edit Roles', 'roles.edit', 'Users', 'Edit roles'),
('Delete Roles', 'roles.delete', 'Users', 'Delete roles'),
('Assign Permissions', 'permissions.assign', 'Users', 'Assign permissions'),

('View Consumers', 'consumers.view', 'Consumers', 'View consumer list'),
('Create Consumers', 'consumers.create', 'Consumers', 'Register new consumers'),
('Edit Consumers', 'consumers.edit', 'Consumers', 'Edit consumer details'),
('Delete Consumers', 'consumers.delete', 'Consumers', 'Delete consumers'),
('Transfer Ownership', 'consumers.transfer', 'Consumers', 'Transfer consumer ownership'),

('View Employees', 'employees.view', 'Employees', 'View employee list'),
('Create Employees', 'employees.create', 'Employees', 'Create employees'),
('Edit Employees', 'employees.edit', 'Employees', 'Edit employees'),
('Delete Employees', 'employees.delete', 'Employees', 'Delete employees'),
('Mark Attendance', 'attendance.mark', 'Employees', 'Mark attendance'),

('View Bills', 'bills.view', 'Billing', 'View billing list'),
('Generate Bills', 'bills.generate', 'Billing', 'Generate bills'),
('Edit Bills', 'bills.edit', 'Billing', 'Edit bills'),
('Cancel Bills', 'bills.cancel', 'Billing', 'Cancel bills'),
('Record Payments', 'payments.record', 'Billing', 'Record payments'),
('View Payments', 'payments.view', 'Billing', 'View payments'),
('Refund Payments', 'payments.refund', 'Billing', 'Process refunds'),
('Manage Tariffs', 'tariffs.manage', 'Billing', 'Manage tariff rates'),
('View Defaulters', 'defaulters.view', 'Billing', 'View defaulter list'),

('View Meter Readings', 'readings.view', 'Meter', 'View meter readings'),
('Enter Readings', 'readings.enter', 'Meter', 'Enter meter readings'),
('Verify Readings', 'readings.verify', 'Meter', 'Verify meter readings'),

('View Complaints', 'complaints.view', 'Complaints', 'View complaints'),
('Create Complaints', 'complaints.create', 'Complaints', 'Register complaints'),
('Assign Complaints', 'complaints.assign', 'Complaints', 'Assign complaints'),
('Resolve Complaints', 'complaints.resolve', 'Complaints', 'Resolve complaints'),
('View Work Orders', 'workorders.view', 'Complaints', 'View work orders'),
('Create Work Orders', 'workorders.create', 'Complaints', 'Create work orders'),

('View Inventory', 'inventory.view', 'Inventory', 'View inventory'),
('Manage Items', 'inventory.items', 'Inventory', 'Manage inventory items'),
('Manage Stock In', 'stock.in', 'Inventory', 'Manage stock in'),
('Manage Stock Out', 'stock.out', 'Inventory', 'Manage stock out'),
('Manage Suppliers', 'suppliers.manage', 'Inventory', 'Manage suppliers'),

('View Assets', 'assets.view', 'Assets', 'View assets'),
('Create Assets', 'assets.create', 'Assets', 'Create assets'),
('Edit Assets', 'assets.edit', 'Assets', 'Edit assets'),
('Schedule Maintenance', 'maintenance.schedule', 'Assets', 'Schedule maintenance'),

('View GIS', 'gis.view', 'GIS', 'View GIS maps'),
('Edit GIS', 'gis.edit', 'GIS', 'Edit GIS data'),

('View Reports', 'reports.view', 'Reports', 'View reports'),
('Generate Reports', 'reports.generate', 'Reports', 'Generate reports'),
('Export PDF', 'exports.pdf', 'Reports', 'Export to PDF'),
('Export Excel', 'exports.excel', 'Reports', 'Export to Excel'),
('Export CSV', 'exports.csv', 'Reports', 'Export to CSV'),

('View Settings', 'settings.view', 'Settings', 'View system settings'),
('Edit Settings', 'settings.edit', 'Settings', 'Edit system settings'),
('View Audit Logs', 'audit.view', 'Settings', 'View audit logs'),
('Manage Backup', 'backup.manage', 'Settings', 'Manage backups');

-- Assign all permissions to Super Admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Create default organization
INSERT INTO organizations (name, short_name) VALUES 
('Drinking Water & Sanitation Consumer Committee', 'DWSCC');

-- Create default admin user (password: admin123)
INSERT INTO users (role_id, name, email, username, password, status) VALUES
(1, 'Super Admin', 'admin@swms.gov.np', 'admin', '$2y$12$LJ3m4ys3Gz5Fq6x7y8z9A.abcdefghijklmnopqrstuvwxyzABCDEFG', 'active');

-- Default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_group, description) VALUES
('site_name', 'Smart Water Management System', 'general', 'Site title'),
('site_description', 'Drinking Water & Sanitation Consumer Committee Management System', 'general', 'Site description'),
('default_currency', 'NRs.', 'billing', 'Default currency symbol'),
('billing_cycle_days', '30', 'billing', 'Billing cycle in days'),
('due_date_days', '15', 'billing', 'Due date days after billing'),
('penalty_percent', '5.00', 'billing', 'Late payment penalty percentage'),
('vat_percent', '0.00', 'billing', 'VAT percentage'),
('meter_rent', '50.00', 'billing', 'Default meter rent'),
('sewerage_fee', '0.00', 'billing', 'Default sewerage fee'),
('smtp_host', '', 'email', 'SMTP host'),
('smtp_port', '587', 'email', 'SMTP port'),
('smtp_username', '', 'email', 'SMTP username'),
('smtp_password', '', 'email', 'SMTP password'),
('sms_api_key', '', 'sms', 'SMS API key'),
('sms_sender_id', 'SWMS', 'sms', 'SMS sender ID'),
('map_center_lat', '27.7172', 'gis', 'Map center latitude'),
('map_center_lng', '85.3240', 'gis', 'Map center longitude'),
('map_zoom', '13', 'gis', 'Default map zoom level'),
('default_page_size', '25', 'general', 'Default pagination size'),
('date_format', 'Y-m-d', 'general', 'Date format'),
('timezone', 'Asia/Kathmandu', 'general', 'System timezone'),
('maintenance_mode', '0', 'general', 'Maintenance mode');

-- Default fiscal year
INSERT INTO fiscal_years (year_code, label, start_date, end_date, is_current) VALUES
('2082-83', 'Fiscal Year 2082/83', '2025-07-17', '2026-07-16', 1);

-- Default consumer categories
INSERT INTO consumer_categories (name, slug, description) VALUES
('General Household', 'general-household', 'General household consumers'),
('Low Income', 'low-income', 'Low income household consumers'),
('Commercial', 'commercial', 'Commercial establishments'),
('Institutional', 'institutional', 'Government and institutional'),
('Industrial', 'industrial', 'Industrial consumers'),
('Bulk', 'bulk', 'Bulk water consumers');

-- Default GIS layers
INSERT INTO gis_layers (name, layer_type, color) VALUES
('Consumer Locations', 'consumer', '#181CB8'),
('Pipeline Network', 'pipeline', '#2196F3'),
('Water Tanks', 'tank', '#4CAF50'),
('Pump Stations', 'pump', '#FF9800'),
('Valves', 'valve', '#F44336'),
('Service Area', 'service_area', '#9C27B0');
