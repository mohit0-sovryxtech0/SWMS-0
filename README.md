# Smart Water Management System (SWMS)

A comprehensive web-based **Smart Water Management System** built for Drinking Water & Sanitation Consumer Committees in Nepal. This system digitizes and automates water utility operations including consumer management, billing, meter reading, complaint handling, inventory management, employee management, GIS mapping, and citizen self-service portal.

## Features

### 🔧 Admin Panel
- **Dashboard** – Real-time analytics and operational KPIs
- **Consumer Management** – Register, manage, and track water consumers
- **Billing & Payments** – Automated bill generation, payment processing, and receipts
- **Meter Reading** – Record and verify meter readings with photo uploads
- **Complaint Management** – Track and manage citizen complaints with workflow engine
- **Inventory Management** – Manage stock levels, low-stock alerts, and purchase orders
- **Employee Management** – Departments, designations, attendance, and payroll
- **Document Management** – Upload and manage consumer documents
- **GIS Mapping** – Visualize consumer locations on an interactive map
- **User & Role Management** – RBAC-based access control with granular permissions
- **Notifications** – SMS and email alerts
- **Reports** – Generate various operational and financial reports
- **Workflow Engine** – Configurable approval workflows
- **Settings** – System configuration and fiscal year management

### 🏠 Citizen Portal
- **Login / Register** – Self-registration for consumers
- **Dashboard** – View account summary and due bills
- **View & Pay Bills** – Check outstanding bills and pay online via eSewa, Khalti, or Fonepay
- **Complaint Tracking** – Submit and track complaints
- **Payment History** – View past payments and download receipts
- **Profile Management** – Update personal information

### 🔌 REST API
- Search and fetch consumer data
- Retrieve billing, outstanding, and bill details
- Record and verify meter readings
- Get complaint statistics
- Fetch GIS map data and low-stock items

## Technology Stack

| Component   | Technology                        |
|-------------|-----------------------------------|
| Backend     | PHP 8+ (Native, no framework)     |
| Database    | MySQL 8+                          |
| Frontend    | HTML5, CSS3, JavaScript, Bootstrap |
| Maps        | Leaflet.js (OpenStreetMap)        |
| Payments    | eSewa, Khalti, Fonepay integrations |
| Auth        | Session-based with RBAC           |
| CSS Framework | AdminLTE 3 (Bootstrap 4)        |

## Project Structure

```
CMS_0/
├── admin/               # Admin panel (92 PHP files)
│   ├── assets/          # CSS, JS, images
│   ├── assets-mgmt/     # Asset management
│   ├── billing/         # Billing module
│   ├── citizen-portal/  # Citizen portal management
│   ├── complaints/      # Complaint handling
│   ├── consumers/       # Consumer management
│   ├── dashboard/       # Admin dashboard
│   ├── documents/       # Document management
│   ├── employees/       # Employee management
│   ├── gis/             # GIS mapping
│   ├── includes/        # Admin-specific includes
│   ├── inventory/       # Inventory management
│   ├── meter-reading/   # Meter reading module
│   ├── notifications/   # Notifications
│   ├── reports/         # Reports
│   ├── settings/        # System settings
│   ├── users/           # User management
│   └── workflow/        # Workflow engine
├── api/                 # REST API endpoints
├── citizen/             # Citizen self-service portal
│   ├── assets/          # Citizen portal assets
│   └── includes/        # Citizen-specific includes
├── database/            # SQL schema and migrations
│   └── migrations/      # Database migration scripts
├── includes/            # Core framework files
│   ├── auth.php         # Authentication logic
│   ├── BillingEngine.php # Billing computation engine
│   ├── config.php       # Application configuration
│   ├── database.php     # Database connection layer
│   ├── functions.php    # Utility functions
│   ├── rbac.php         # Role-based access control
│   ├── security.php     # Security helpers
│   ├── validation.php   # Input validation
│   └── WorkflowEngine.php # Workflow processing engine
├── logs/                # Application logs
├── pages/               # Public pages
│   └── auth/            # Login page
├── uploads/             # User-uploaded files
│   ├── complaints/
│   ├── consumers/
│   ├── documents/
│   ├── employees/
│   ├── logo/
│   └── meter-photos/
└── index.php            # Application entry point
```

## Installation

### Prerequisites

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Composer (optional, for additional dependencies)

### Setup Instructions

1. **Clone or extract the project**
   ```bash
   git clone https://github.com/mohit0-sovryxtech0/SWMS_0.git
   ```
   Or extract the `CMS_0.zip` archive into your web server root (e.g., `htdocs/`).

2. **Configure the database**
   - Open `includes/config.php` and verify the database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_PORT', '3306');
     define('DB_NAME', 'swms_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

3. **Import the database schema**
   - Run `database/schema.sql` against your MySQL server:
     ```bash
     mysql -u root -p < database/schema.sql
     ```

4. **Run migrations**
   - Execute any pending migration scripts from the `database/migrations/` directory:
     ```bash
     php _run_migration_006.php
     php _run_migration_007.php
     php _run_migration_008.php
     ```

5. **Set file permissions**
   - Ensure the `uploads/` and `logs/` directories are writable by the web server.

6. **Configure base URL**
   - Update `BASE_URL` in `includes/config.php` to match your local or production domain:
     ```php
     define('BASE_URL', 'http://localhost/CMS_0/');
     ```

7. **Access the application**
   - Open your browser and navigate to the base URL.
   - You will be redirected to the login page.
   - Default login credentials can be set up via the setup script:
     ```bash
     php setup.php
     ```

## Default Accounts

After running `setup.php`, the following default accounts may be created:

| Role     | Username/Email | Password     |
|----------|----------------|--------------|
| Admin    | admin          | admin12      |
| Employee | (varies)       | (varies)     |

> **⚠️ Security:** Change default passwords immediately in production.

## Configuration

### Payment Gateway Integration

Configure the following in `includes/config.php`:

```php
define('ESEWA_MERCHANT_ID', 'your_esewa_merchant_id');
define('ESEWA_SECRET_KEY',  'your_esewa_secret_key');
define('KHALTI_MERCHANT_ID', 'your_khalti_merchant_id');
define('KHALTI_SECRET_KEY',   'your_khalti_secret_key');
define('FONEPAY_MERCHANT_ID', 'your_fonepay_merchant_id');
define('FONEPAY_SECRET_KEY',  'your_fonepay_secret_key');
```

### Email / SMS

```php
define('SMTP_HOST', 'smtp.yourprovider.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@example.com');
define('SMTP_PASS', 'your_password');
define('SMS_API_KEY', 'your_sms_api_key');
define('SMS_API_URL', 'https://api.smsprovider.com/send');
```

## API Endpoints

| Method | Endpoint                                | Description                     |
|--------|------------------------------------------|---------------------------------|
| GET    | `api/get-consumer.php?id={id}`           | Fetch consumer details          |
| GET    | `api/search-consumers.php?q={query}`     | Search consumers                |
| GET    | `api/get-consumer-bills.php?id={id}`     | Get bills for a consumer        |
| GET    | `api/get-consumer-outstanding.php?id={id}` | Get outstanding amount        |
| POST   | `api/save-reading.php`                   | Save meter reading              |
| POST   | `api/verify-reading.php`                 | Verify meter reading            |
| GET    | `api/get-map-data.php`                   | Fetch GIS map data              |
| GET    | `api/get-low-stock-items.php`            | Get low-stock inventory items   |
| GET    | `api/get-defaulter-count.php`            | Get defaulter statistics        |
| GET    | `api/get-fiscal-year.php`                | Get current fiscal year         |
| GET    | `api/get-complaint-stats.php`            | Get complaint statistics        |
| GET    | `api/get-consumer-for-reading.php`       | Get consumer for meter reading  |
| GET    | `api/get-consumers-json.php`             | Get all consumers as JSON       |

## Database

The database schema (`database/schema.sql`) contains the following module groups:

| Module Group                        | Tables |
|-------------------------------------|--------|
| User Management                     | 7      |
| Consumer Management                 | 6      |
| Billing & Payments                  | 5      |
| Meter Reading                       | 3      |
| Water Quality                       | 1      |
| Employee Management                 | 4      |
| Complaint Management                | 4      |
| Inventory Management                | 5      |
| Document Management                 | 1      |
| GIS / Mapping                       | 1      |
| Notifications                       | 2      |
| System Configuration                | 4      |

## Security

- **Authentication** – Session-based login with brute-force protection (max 5 attempts, 15-min timeout)
- **Authorization** – Role-Based Access Control (RBAC) with granular permissions
- **Encryption** – Sensitive data encrypted via configurable encryption key
- **Input Validation** – Server-side validation for all user inputs
- **File Uploads** – Restricted to allowed extensions (jpg, png, pdf, doc, xls, csv, etc.)
- **CSRF Protection** – SameSite cookie attribute enforced
- **HTTPS Ready** – Session cookie secure flag configurable

## License

This project is proprietary software developed for Drinking Water & Sanitation Consumer Committees. All rights reserved.

## Support

For issues, feature requests, or contributions, please contact the development team.

---
*Built with ❤️ for water utility management in Nepal.*
