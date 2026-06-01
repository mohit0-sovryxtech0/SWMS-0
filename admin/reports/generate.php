<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Generate Report';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Reports', 'url' => ADMIN_URL . 'reports/index.php'],
    ['label' => 'Generate']
];
RBAC::requirePermission('reports.generate');
require_once __DIR__ . '/../includes/header.php';

$selectedType = get('type', 'consumer');
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-d'));

$reportTypes = [
    'consumer' => 'Consumer Reports',
    'billing' => 'Billing Reports',
    'revenue' => 'Revenue Reports',
    'collection' => 'Collection Reports',
    'defaulter' => 'Defaulter Reports',
    'complaint' => 'Complaint Reports',
    'asset' => 'Asset Reports',
    'gis' => 'GIS Reports',
    'employee' => 'Employee Reports',
    'audit' => 'Audit Reports'
];

if (!array_key_exists($selectedType, $reportTypes)) {
    $selectedType = 'consumer';
}

$results = [];
$columns = [];
$totalRow = null;
$ranQuery = false;

if (isPost() && post('action') === 'preview') {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $startDate = post('start_date', $startDate);
        $endDate = post('end_date', $endDate);
        $selectedType = post('report_type', $selectedType);

        $params = [];
        $where = '';

        switch ($selectedType) {
            case 'consumer':
                $where = 'WHERE c.deleted_at IS NULL';
                $connType = post('connection_type');
                $ward = post('ward_no');
                $status = post('status');
                if (!empty($connType)) { $where .= ' AND c.connection_type = :conn_type'; $params['conn_type'] = $connType; }
                if (!empty($ward)) { $where .= ' AND c.ward_no = :ward'; $params['ward'] = $ward; }
                if (!empty($status)) { $where .= ' AND c.status = :status'; $params['status'] = $status; }
                if (!empty($startDate)) { $where .= ' AND c.registration_date >= :start_date'; $params['start_date'] = $startDate; }
                if (!empty($endDate)) { $where .= ' AND c.registration_date <= :end_date'; $params['end_date'] = $endDate; }

                $sql = "SELECT c.consumer_no, c.full_name, c.connection_type, c.ward_no, c.status,
                               cc.name AS category_name, c.mobile, c.phone, c.registration_date,
                               COALESCE(m.meter_no, '-') AS meter_no,
                               (SELECT COUNT(*) FROM bills WHERE consumer_id = c.id) AS bill_count,
                               (SELECT COALESCE(SUM(paid_amount), 0) FROM bills WHERE consumer_id = c.id AND status = 'paid') AS total_paid
                        FROM consumers c
                        LEFT JOIN consumer_categories cc ON c.category_id = cc.id
                        LEFT JOIN meters m ON c.id = m.consumer_id AND m.deleted_at IS NULL
                        {$where}
                        ORDER BY c.registration_date DESC
                        LIMIT 500";
                $results = db()->fetchAll($sql, $params);
                $columns = ['Consumer No', 'Full Name', 'Connection Type', 'Ward No', 'Status', 'Category', 'Mobile', 'Phone', 'Reg. Date', 'Meter No', 'Bills Count', 'Total Paid'];
                break;

            case 'billing':
                $where = 'WHERE b.deleted_at IS NULL';
                $billStatus = post('billing_status');
                $fiscalYear = post('fiscal_year');
                if (!empty($billStatus)) { $where .= ' AND b.status = :status'; $params['status'] = $billStatus; }
                if (!empty($fiscalYear)) { $where .= ' AND b.fiscal_year_id = :fy'; $params['fy'] = $fiscalYear; }
                if (!empty($startDate)) { $where .= ' AND b.billing_period_start >= :start_date'; $params['start_date'] = $startDate; }
                if (!empty($endDate)) { $where .= ' AND b.billing_period_end <= :end_date'; $params['end_date'] = $endDate; }

                $sql = "SELECT b.bill_no, c.consumer_no, c.full_name AS consumer_name,
                               b.billing_period_start, b.billing_period_end, b.due_date,
                               b.total_amount, b.paid_amount, b.due_amount, b.status,
                               fy.label AS fiscal_year
                        FROM bills b
                        JOIN consumers c ON b.consumer_id = c.id
                        LEFT JOIN fiscal_years fy ON b.fiscal_year_id = fy.id
                        {$where}
                        ORDER BY b.created_at DESC
                        LIMIT 500";
                $results = db()->fetchAll($sql, $params);
                $columns = ['Bill No', 'Consumer No', 'Consumer', 'Period Start', 'Period End', 'Due Date', 'Total', 'Paid', 'Due', 'Status', 'Fiscal Year'];

                $totalRow = db()->fetchOne(
                    "SELECT COUNT(*) AS total_bills, COALESCE(SUM(b.total_amount), 0) AS total_amount,
                            COALESCE(SUM(b.paid_amount), 0) AS total_paid, COALESCE(SUM(b.due_amount), 0) AS total_due
                     FROM bills b JOIN consumers c ON b.consumer_id = c.id {$where}", $params
                );
                break;

            case 'revenue':
                $where = 'WHERE p.status = \'completed\'';
                $payMethod = post('payment_method');
                if (!empty($payMethod)) { $where .= ' AND p.payment_method = :method'; $params['method'] = $payMethod; }
                if (!empty($startDate)) { $where .= ' AND p.payment_date >= :start_date'; $params['start_date'] = $startDate; }
                if (!empty($endDate)) { $where .= ' AND p.payment_date <= :end_date'; $params['end_date'] = $endDate; }

                $sql = "SELECT DATE_FORMAT(p.payment_date, '%Y-%m') AS month,
                               p.payment_method, COUNT(*) AS txn_count,
                               COALESCE(SUM(p.amount), 0) AS total_amount,
                               COALESCE(SUM(p.net_amount), 0) AS net_amount,
                               COALESCE(SUM(p.discount), 0) AS total_discount,
                               COALESCE(SUM(p.penalty_waived), 0) AS penalty_waived
                        FROM payments p
                        {$where}
                        GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m'), p.payment_method
                        ORDER BY month DESC, p.payment_method
                        LIMIT 500";
                $results = db()->fetchAll($sql, $params);
                $columns = ['Month', 'Payment Method', 'Transactions', 'Total Amount', 'Net Amount', 'Discount', 'Penalty Waived'];
                break;

            case 'collection':
                $where = 'WHERE b.deleted_at IS NULL';
                if (!empty($startDate)) { $where .= ' AND b.billing_period_start >= :start_date'; $params['start_date'] = $startDate; }
                if (!empty($endDate)) { $where .= ' AND b.billing_period_end <= :end_date'; $params['end_date'] = $endDate; }

                $sql = "SELECT DATE_FORMAT(b.billing_period_start, '%Y-%m') AS month,
                               COUNT(*) AS total_bills,
                               COALESCE(SUM(b.total_amount), 0) AS total_billed,
                               COALESCE(SUM(b.paid_amount), 0) AS total_collected,
                               COALESCE(SUM(b.due_amount), 0) AS total_outstanding,
                               CASE WHEN COALESCE(SUM(b.total_amount), 0) > 0
                                    THEN ROUND((COALESCE(SUM(b.paid_amount), 0) / SUM(b.total_amount)) * 100, 2)
                                    ELSE 0 END AS collection_pct
                        FROM bills b
                        {$where}
                        GROUP BY DATE_FORMAT(b.billing_period_start, '%Y-%m')
                        ORDER BY month DESC
                        LIMIT 24";
                $results = db()->fetchAll($sql, $params);
                $columns = ['Month', 'Total Bills', 'Total Billed', 'Total Collected', 'Outstanding', 'Collection %'];
                break;

            case 'defaulter':
                $minMonths = post('min_overdue_months', 1);
                $where = 'WHERE b.status IN (\'pending\', \'overdue\', \'partial\') AND b.deleted_at IS NULL';
                if (!empty($startDate)) { $where .= ' AND b.due_date >= :start_date'; $params['start_date'] = $startDate; }
                if (!empty($endDate)) { $where .= ' AND b.due_date <= :end_date'; $params['end_date'] = $endDate; }

                $sql = "SELECT c.consumer_no, c.full_name, c.mobile, c.ward_no, c.connection_type,
                               b.bill_no, b.total_amount, b.paid_amount, b.due_amount, b.due_date,
                               DATEDIFF(CURDATE(), b.due_date) AS days_overdue,
                               TIMESTAMPDIFF(MONTH, b.due_date, CURDATE()) AS months_overdue
                        FROM bills b
                        JOIN consumers c ON b.consumer_id = c.id
                        {$where}
                        AND b.due_date < CURDATE()
                        AND TIMESTAMPDIFF(MONTH, b.due_date, CURDATE()) >= :min_months
                        ORDER BY b.due_date ASC
                        LIMIT 500";
                $params['min_months'] = intval($minMonths);
                $results = db()->fetchAll($sql, $params);
                $columns = ['Consumer No', 'Name', 'Mobile', 'Ward', 'Connection Type', 'Bill No', 'Total', 'Paid', 'Due', 'Due Date', 'Days Overdue', 'Months Overdue'];
                break;

            case 'complaint':
                $where = 'WHERE c.deleted_at IS NULL';
                $catId = post('category_id');
                $compStatus = post('complaint_status');
                if (!empty($catId)) { $where .= ' AND c.category_id = :cat_id'; $params['cat_id'] = $catId; }
                if (!empty($compStatus)) { $where .= ' AND c.status = :status'; $params['status'] = $compStatus; }
                if (!empty($startDate)) { $where .= ' AND c.created_at >= :start_date'; $params['start_date'] = $startDate . ' 00:00:00'; }
                if (!empty($endDate)) { $where .= ' AND c.created_at <= :end_date'; $params['end_date'] = $endDate . ' 23:59:59'; }

                $sql = "SELECT c.ticket_no, c.subject, cc.name AS category, c.priority, c.status,
                               c.ward_no, u.name AS assigned_to,
                               c.created_at, c.resolved_at,
                               TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) AS resolution_hours
                        FROM complaints c
                        LEFT JOIN complaint_categories cc ON c.category_id = cc.id
                        LEFT JOIN users u ON c.assigned_to = u.id
                        {$where}
                        ORDER BY c.created_at DESC
                        LIMIT 500";
                $results = db()->fetchAll($sql, $params);
                $columns = ['Ticket No', 'Subject', 'Category', 'Priority', 'Status', 'Ward', 'Assigned To', 'Created', 'Resolved', 'Resolution Hours'];

                $trendSql = "SELECT DATE_FORMAT(c.created_at, '%Y-%m') AS month, COUNT(*) AS total,
                                    SUM(CASE WHEN c.status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved
                             FROM complaints c {$where}
                             GROUP BY DATE_FORMAT(c.created_at, '%Y-%m')
                             ORDER BY month DESC LIMIT 12";
                $monthlyTrend = db()->fetchAll($trendSql, $params);
                break;

            case 'asset':
                $where = 'WHERE a.deleted_at IS NULL';
                $assetType = post('asset_type');
                $assetStatus = post('asset_status');
                if (!empty($assetType)) { $where .= ' AND a.asset_type = :type'; $params['type'] = $assetType; }
                if (!empty($assetStatus)) { $where .= ' AND a.status = :status'; $params['status'] = $assetStatus; }
                if (!empty($startDate)) { $where .= ' AND a.purchase_date >= :start_date'; $params['start_date'] = $startDate; }
                if (!empty($endDate)) { $where .= ' AND a.purchase_date <= :end_date'; $params['end_date'] = $endDate; }

                $sql = "SELECT a.asset_code, a.name, a.asset_type, ac.name AS category, a.status,
                               a.location, a.ward_no, a.purchase_date, a.purchase_cost, a.current_value,
                               a.manufacturer, a.serial_no
                        FROM assets a
                        LEFT JOIN asset_categories ac ON a.category_id = ac.id
                        {$where}
                        ORDER BY a.created_at DESC
                        LIMIT 500";
                $results = db()->fetchAll($sql, $params);
                $columns = ['Asset Code', 'Name', 'Type', 'Category', 'Status', 'Location', 'Ward', 'Purchase Date', 'Purchase Cost', 'Current Value', 'Manufacturer', 'Serial No'];
                break;

            case 'gis':
                $sql = "SELECT 'Consumer' AS layer_type, COUNT(*) AS total,
                               CONCAT(ROUND(MIN(latitude), 4), ', ', ROUND(MIN(longitude), 4)) AS bounds_min,
                               CONCAT(ROUND(MAX(latitude), 4), ', ', ROUND(MAX(longitude), 4)) AS bounds_max
                        FROM consumers WHERE deleted_at IS NULL AND latitude IS NOT NULL
                        UNION ALL
                        SELECT 'Assets' AS layer_type, COUNT(*), '', ''
                        FROM assets WHERE deleted_at IS NULL AND latitude IS NOT NULL
                        UNION ALL
                        SELECT 'Pipelines' AS layer_type, COUNT(*), '', ''
                        FROM pipelines WHERE status = 'active'";
                $results = db()->fetchAll($sql);
                $columns = ['Layer Type', 'Total Features', 'Min Bounds', 'Max Bounds'];
                break;

            case 'employee':
                $where = 'WHERE e.deleted_at IS NULL';
                $deptId = post('department_id');
                $empStatus = post('emp_status');
                if (!empty($deptId)) { $where .= ' AND e.department_id = :dept'; $params['dept'] = $deptId; }
                if (!empty($empStatus)) { $where .= ' AND e.status = :status'; $params['status'] = $empStatus; }

                $sql = "SELECT e.employee_code, e.full_name, e.gender, e.mobile, e.email,
                               d.name AS department, des.name AS designation,
                               e.employment_type, e.joining_date, e.status
                        FROM employees e
                        LEFT JOIN departments d ON e.department_id = d.id
                        LEFT JOIN designations des ON e.designation_id = des.id
                        {$where}
                        ORDER BY e.joining_date DESC
                        LIMIT 500";
                $results = db()->fetchAll($sql, $params);
                $columns = ['Code', 'Full Name', 'Gender', 'Mobile', 'Email', 'Department', 'Designation', 'Type', 'Joining Date', 'Status'];
                break;

            case 'audit':
                $where = 'WHERE 1=1';
                $auditModule = post('audit_module');
                $auditAction = post('audit_action');
                if (!empty($auditModule)) { $where .= ' AND at.module = :module'; $params['module'] = $auditModule; }
                if (!empty($auditAction)) { $where .= ' AND at.action = :action'; $params['action'] = $auditAction; }
                if (!empty($startDate)) { $where .= ' AND at.created_at >= :start_date'; $params['start_date'] = $startDate . ' 00:00:00'; }
                if (!empty($endDate)) { $where .= ' AND at.created_at <= :end_date'; $params['end_date'] = $endDate . ' 23:59:59'; }

                $sql = "SELECT at.*, u.name AS user_name
                        FROM audit_trail at
                        LEFT JOIN users u ON at.user_id = u.id
                        {$where}
                        ORDER BY at.created_at DESC
                        LIMIT 500";
                $results = db()->fetchAll($sql, $params);
                $columns = ['ID', 'Timestamp', 'User', 'Action', 'Module', 'Reference', 'Description'];
                break;
        }

        $ranQuery = true;
    } catch (Exception $e) {
        alert_error($e->getMessage());
    }
}

$fiscalYears = db()->fetchAll("SELECT id, label FROM fiscal_years ORDER BY start_date DESC");
$categories = db()->fetchAll("SELECT id, name FROM complaint_categories ORDER BY name");
$departments = db()->fetchAll("SELECT id, name FROM departments ORDER BY name");
$wardOptions = range(1, 20);

$extraCss = '<style>
.preview-table { font-size: 13px; }
.preview-table th { white-space: nowrap; background: var(--bg-color); }
.summary-card { border-left: 4px solid var(--primary); }
</style>';

$extraJs = <<<JS
<script>
$(document).ready(function() {
    $('#reportType').on('change', function() {
        var val = $(this).val();
        $('.filter-group').addClass('d-none').find('input,select').prop('disabled', true);
        $('.filter-group[data-type="' + val + '"]').removeClass('d-none').find('input,select').prop('disabled', false);
        $('.filter-group[data-type="all"]').removeClass('d-none').find('input,select').prop('disabled', false);
    });
    $('#reportType').trigger('change');
});
</script>
JS;
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Generate Report</h4>
            <p>Select report type, apply filters, and preview before export</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>reports/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Reports
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="preview">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type <span class="text-danger">*</span></label>
                    <select name="report_type" id="reportType" class="form-select">
                        <?php foreach ($reportTypes as $slug => $label): ?>
                        <option value="<?= $slug ?>" <?= $selectedType === $slug ? 'selected' : '' ?>><?= escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= escape($startDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= escape($endDate) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Preview Report
                    </button>
                </div>
            </div>

            <hr class="my-3">

            <!-- Consumer Filters -->
            <div class="filter-group d-none" data-type="consumer">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Connection Type</label>
                        <select name="connection_type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option value="household">Household</option>
                            <option value="commercial">Commercial</option>
                            <option value="institutional">Institutional</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ward No</label>
                        <select name="ward_no" class="form-select form-select-sm">
                            <option value="">All Wards</option>
                            <?php for ($w = 1; $w <= 20; $w++): ?>
                            <option value="<?= $w ?>">Ward <?= $w ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                            <option value="disconnected">Disconnected</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Billing Filters -->
            <div class="filter-group d-none" data-type="billing">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Bill Status</label>
                        <select name="billing_status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="partial">Partial</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fiscal Year</label>
                        <select name="fiscal_year" class="form-select form-select-sm">
                            <option value="">All Years</option>
                            <?php foreach ($fiscalYears as $fy): ?>
                            <option value="<?= $fy['id'] ?>"><?= escape($fy['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Revenue Filters -->
            <div class="filter-group d-none" data-type="revenue">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select form-select-sm">
                            <option value="">All Methods</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="esewa">eSewa</option>
                            <option value="khalti">Khalti</option>
                            <option value="fonepay">FonePay</option>
                            <option value="qr">QR</option>
                            <option value="cheque">Cheque</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Defaulter Filters -->
            <div class="filter-group d-none" data-type="defaulter">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Min Overdue Months</label>
                        <select name="min_overdue_months" class="form-select form-select-sm">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"><?= $m ?> Month<?= $m > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Complaint Filters -->
            <div class="filter-group d-none" data-type="complaint">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="complaint_status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                            <option value="reopened">Reopened</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Asset Filters -->
            <div class="filter-group d-none" data-type="asset">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Asset Type</label>
                        <select name="asset_type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option value="water_tank">Water Tank</option>
                            <option value="pipeline">Pipeline</option>
                            <option value="pump">Pump</option>
                            <option value="valve">Valve</option>
                            <option value="meter">Meter</option>
                            <option value="vehicle">Vehicle</option>
                            <option value="building">Building</option>
                            <option value="equipment">Equipment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="asset_status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="operational">Operational</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="damaged">Damaged</option>
                            <option value="decommissioned">Decommissioned</option>
                            <option value="under_construction">Under Construction</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Employee Filters -->
            <div class="filter-group d-none" data-type="employee">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= escape($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="emp_status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="resigned">Resigned</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Audit Filters -->
            <div class="filter-group d-none" data-type="audit">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Module</label>
                        <select name="audit_module" class="form-select form-select-sm">
                            <option value="">All Modules</option>
                            <option value="Settings">Settings</option>
                            <option value="Users">Users</option>
                            <option value="Consumers">Consumers</option>
                            <option value="Billing">Billing</option>
                            <option value="Complaints">Complaints</option>
                            <option value="Reports">Reports</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Action</label>
                        <select name="audit_action" class="form-select form-select-sm">
                            <option value="">All Actions</option>
                            <option value="create">Create</option>
                            <option value="update">Update</option>
                            <option value="delete">Delete</option>
                            <option value="login">Login</option>
                            <option value="export">Export</option>
                        </select>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>

<?php if ($ranQuery): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-table me-2 text-primary"></i>
            <?= escape($reportTypes[$selectedType]) ?> - Preview
            <small class="text-muted ms-2">(<?= count($results) ?> records)</small>
        </h5>
        <div class="btn-group btn-group-sm">
            <?php if (RBAC::can('exports.pdf')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=<?= $selectedType ?>&format=pdf&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&<?= http_build_query(array_filter($_POST)) ?>" class="btn btn-danger" target="_blank">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.excel')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=<?= $selectedType ?>&format=xlsx&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&<?= http_build_query(array_filter($_POST)) ?>" class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i> Excel
            </a>
            <?php endif; ?>
            <?php if (RBAC::can('exports.csv')): ?>
            <a href="<?= ADMIN_URL ?>reports/export.php?type=<?= $selectedType ?>&format=csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&<?= http_build_query(array_filter($_POST)) ?>" class="btn btn-secondary">
                <i class="fas fa-file-csv me-1"></i> CSV
            </a>
            <?php endif; ?>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($results)): ?>
        <div class="text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <p class="text-muted">No records found for the selected criteria</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover preview-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <?php foreach ($columns as $col): ?>
                        <th><?= escape($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <?php foreach ($row as $key => $val): ?>
                        <td><?= escape(is_null($val) ? '-' : $val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($totalRow): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td></td>
                        <?php for ($j = 0; $j < count($columns); $j++): ?>
                        <td>
                            <?php if (isset($totalRow['total_bills']) && $j === 0): ?>
                                <?= number_format($totalRow['total_bills']) ?> bills
                            <?php elseif (isset($totalRow['total_amount']) && $columns[$j] === 'Total'): ?>
                                <?= format_currency($totalRow['total_amount']) ?>
                            <?php elseif (isset($totalRow['total_paid']) && $columns[$j] === 'Paid'): ?>
                                <?= format_currency($totalRow['total_paid']) ?>
                            <?php elseif (isset($totalRow['total_due']) && $columns[$j] === 'Due'): ?>
                                <?= format_currency($totalRow['total_due']) ?>
                            <?php endif; ?>
                        </td>
                        <?php endfor; ?>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($selectedType === 'complaint' && !empty($monthlyTrend)): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i> Monthly Trend</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>Month</th><th>Total</th><th>Resolved</th><th>Resolution %</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyTrend as $t): ?>
                    <tr>
                        <td><?= escape($t['month']) ?></td>
                        <td><?= $t['total'] ?></td>
                        <td><?= $t['resolved'] ?></td>
                        <td><?= $t['total'] > 0 ? round(($t['resolved'] / $t['total']) * 100, 1) : 0 ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
