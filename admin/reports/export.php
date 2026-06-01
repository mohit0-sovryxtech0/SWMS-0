<?php
require_once __DIR__ . '/../../includes/config.php';
Auth::requireAuth();

$type = get('type', 'consumer');
$format = get('format', 'csv');
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-d'));

$allowedTypes = ['consumer', 'billing', 'revenue', 'collection', 'defaulter', 'complaint', 'asset', 'gis', 'employee', 'audit'];
$allowedFormats = ['csv', 'pdf', 'xlsx', 'print'];

if (!in_array($type, $allowedTypes)) {
    die('Invalid report type');
}
if (!in_array($format, $allowedFormats)) {
    die('Invalid format');
}

if ($format === 'pdf' && !RBAC::can('exports.pdf')) {
    die('Permission denied: PDF export');
}
if ($format === 'xlsx' && !RBAC::can('exports.excel')) {
    die('Permission denied: Excel export');
}
if ($format === 'csv' && !RBAC::can('exports.csv')) {
    die('Permission denied: CSV export');
}

$params = [];
$where = '';
$columns = [];
$rows = [];

switch ($type) {
    case 'consumer':
        $where = 'WHERE c.deleted_at IS NULL';
        $connType = get('connection_type');
        $ward = get('ward_no');
        $status = get('status');
        if (!empty($connType)) { $where .= ' AND c.connection_type = :conn_type'; $params['conn_type'] = $connType; }
        if (!empty($ward)) { $where .= ' AND c.ward_no = :ward'; $params['ward'] = $ward; }
        if (!empty($status)) { $where .= ' AND c.status = :status'; $params['status'] = $status; }
        if (!empty($startDate)) { $where .= ' AND c.registration_date >= :start_date'; $params['start_date'] = $startDate; }
        if (!empty($endDate)) { $where .= ' AND c.registration_date <= :end_date'; $params['end_date'] = $endDate; }
        $sql = "SELECT c.consumer_no, c.full_name, c.connection_type, c.ward_no, c.status,
                       cc.name AS category, c.mobile, c.phone, c.registration_date,
                       COALESCE(m.meter_no, '-') AS meter_no,
                       (SELECT COUNT(*) FROM bills WHERE consumer_id = c.id) AS bill_count,
                       (SELECT COALESCE(SUM(paid_amount), 0) FROM bills WHERE consumer_id = c.id AND status = 'paid') AS total_paid
                FROM consumers c
                LEFT JOIN consumer_categories cc ON c.category_id = cc.id
                LEFT JOIN meters m ON c.id = m.consumer_id AND m.deleted_at IS NULL
                {$where} ORDER BY c.registration_date DESC LIMIT 5000";
        $columns = ['Consumer No', 'Full Name', 'Connection Type', 'Ward No', 'Status', 'Category', 'Mobile', 'Phone', 'Reg. Date', 'Meter No', 'Bills Count', 'Total Paid'];
        $rows = db()->fetchAll($sql, $params);
        break;

    case 'billing':
        $where = 'WHERE b.deleted_at IS NULL';
        $billStatus = get('billing_status');
        $fiscalYear = get('fiscal_year');
        if (!empty($billStatus)) { $where .= ' AND b.status = :status'; $params['status'] = $billStatus; }
        if (!empty($fiscalYear)) { $where .= ' AND b.fiscal_year_id = :fy'; $params['fy'] = $fiscalYear; }
        if (!empty($startDate)) { $where .= ' AND b.billing_period_start >= :start_date'; $params['start_date'] = $startDate; }
        if (!empty($endDate)) { $where .= ' AND b.billing_period_end <= :end_date'; $params['end_date'] = $endDate; }
        $sql = "SELECT b.bill_no, c.consumer_no, c.full_name, b.billing_period_start, b.billing_period_end,
                       b.due_date, b.total_amount, b.paid_amount, b.due_amount, b.status, fy.label AS fiscal_year
                FROM bills b JOIN consumers c ON b.consumer_id = c.id
                LEFT JOIN fiscal_years fy ON b.fiscal_year_id = fy.id
                {$where} ORDER BY b.created_at DESC LIMIT 5000";
        $columns = ['Bill No', 'Consumer No', 'Consumer', 'Period Start', 'Period End', 'Due Date', 'Total', 'Paid', 'Due', 'Status', 'Fiscal Year'];
        $rows = db()->fetchAll($sql, $params);
        break;

    case 'revenue':
        $where = 'WHERE p.status = \'completed\'';
        $payMethod = get('payment_method');
        if (!empty($payMethod)) { $where .= ' AND p.payment_method = :method'; $params['method'] = $payMethod; }
        if (!empty($startDate)) { $where .= ' AND p.payment_date >= :start_date'; $params['start_date'] = $startDate; }
        if (!empty($endDate)) { $where .= ' AND p.payment_date <= :end_date'; $params['end_date'] = $endDate; }
        $sql = "SELECT DATE_FORMAT(p.payment_date, '%Y-%m') AS month, p.payment_method,
                       COUNT(*) AS txn_count, SUM(p.amount) AS total_amount, SUM(p.net_amount) AS net_amount,
                       SUM(p.discount) AS total_discount, SUM(p.penalty_waived) AS penalty_waived
                FROM payments p
                {$where}
                GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m'), p.payment_method
                ORDER BY month DESC LIMIT 5000";
        $columns = ['Month', 'Payment Method', 'Transactions', 'Total Amount', 'Net Amount', 'Discount', 'Penalty Waived'];
        $rows = db()->fetchAll($sql, $params);
        break;

    case 'collection':
        $where = 'WHERE b.deleted_at IS NULL';
        if (!empty($startDate)) { $where .= ' AND b.billing_period_start >= :start_date'; $params['start_date'] = $startDate; }
        if (!empty($endDate)) { $where .= ' AND b.billing_period_end <= :end_date'; $params['end_date'] = $endDate; }
        $sql = "SELECT DATE_FORMAT(b.billing_period_start, '%Y-%m') AS month, COUNT(*) AS total_bills,
                       SUM(b.total_amount) AS total_billed, SUM(b.paid_amount) AS total_collected,
                       SUM(b.due_amount) AS total_outstanding,
                       CASE WHEN SUM(b.total_amount) > 0 THEN ROUND((SUM(b.paid_amount) / SUM(b.total_amount)) * 100, 2) ELSE 0 END AS collection_pct
                FROM bills b {$where}
                GROUP BY DATE_FORMAT(b.billing_period_start, '%Y-%m')
                ORDER BY month DESC LIMIT 24";
        $columns = ['Month', 'Total Bills', 'Total Billed', 'Total Collected', 'Outstanding', 'Collection %'];
        $rows = db()->fetchAll($sql, $params);
        break;

    case 'defaulter':
        $minMonths = get('min_overdue_months', 1);
        $where = 'WHERE b.status IN (\'pending\', \'overdue\', \'partial\') AND b.deleted_at IS NULL AND b.due_date < CURDATE()';
        $params['min_months'] = intval($minMonths);
        $where .= ' AND TIMESTAMPDIFF(MONTH, b.due_date, CURDATE()) >= :min_months';
        if (!empty($startDate)) { $where .= ' AND b.due_date >= :start_date'; $params['start_date'] = $startDate; }
        if (!empty($endDate)) { $where .= ' AND b.due_date <= :end_date'; $params['end_date'] = $endDate; }
        $sql = "SELECT c.consumer_no, c.full_name, c.mobile, c.ward_no, c.connection_type,
                       b.bill_no, b.total_amount, b.paid_amount, b.due_amount, b.due_date,
                       DATEDIFF(CURDATE(), b.due_date) AS days_overdue,
                       TIMESTAMPDIFF(MONTH, b.due_date, CURDATE()) AS months_overdue
                FROM bills b JOIN consumers c ON b.consumer_id = c.id
                {$where} ORDER BY b.due_date ASC LIMIT 5000";
        $columns = ['Consumer No', 'Name', 'Mobile', 'Ward', 'Connection Type', 'Bill No', 'Total', 'Paid', 'Due', 'Due Date', 'Days Overdue', 'Months Overdue'];
        $rows = db()->fetchAll($sql, $params);
        break;

    case 'complaint':
        $where = 'WHERE c.deleted_at IS NULL';
        $catId = get('category_id');
        $compStatus = get('complaint_status');
        if (!empty($catId)) { $where .= ' AND c.category_id = :cat_id'; $params['cat_id'] = $catId; }
        if (!empty($compStatus)) { $where .= ' AND c.status = :status'; $params['status'] = $compStatus; }
        if (!empty($startDate)) { $where .= ' AND c.created_at >= :start_date'; $params['start_date'] = $startDate . ' 00:00:00'; }
        if (!empty($endDate)) { $where .= ' AND c.created_at <= :end_date'; $params['end_date'] = $endDate . ' 23:59:59'; }
        $sql = "SELECT c.ticket_no, c.subject, cc.name AS category, c.priority, c.status,
                       c.ward_no, u.name AS assigned_to, c.created_at, c.resolved_at,
                       TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) AS resolution_hours
                FROM complaints c
                LEFT JOIN complaint_categories cc ON c.category_id = cc.id
                LEFT JOIN users u ON c.assigned_to = u.id
                {$where} ORDER BY c.created_at DESC LIMIT 5000";
        $columns = ['Ticket No', 'Subject', 'Category', 'Priority', 'Status', 'Ward', 'Assigned To', 'Created', 'Resolved', 'Resolution Hours'];
        $rows = db()->fetchAll($sql, $params);
        break;

    case 'asset':
        $where = 'WHERE a.deleted_at IS NULL';
        $assetType = get('asset_type');
        $assetStatus = get('asset_status');
        if (!empty($assetType)) { $where .= ' AND a.asset_type = :type'; $params['type'] = $assetType; }
        if (!empty($assetStatus)) { $where .= ' AND a.status = :status'; $params['status'] = $assetStatus; }
        if (!empty($startDate)) { $where .= ' AND a.purchase_date >= :start_date'; $params['start_date'] = $startDate; }
        if (!empty($endDate)) { $where .= ' AND a.purchase_date <= :end_date'; $params['end_date'] = $endDate; }
        $sql = "SELECT a.asset_code, a.name, a.asset_type, ac.name AS category, a.status,
                       a.location, a.ward_no, a.purchase_date, a.purchase_cost, a.current_value,
                       a.manufacturer, a.serial_no
                FROM assets a LEFT JOIN asset_categories ac ON a.category_id = ac.id
                {$where} ORDER BY a.created_at DESC LIMIT 5000";
        $columns = ['Asset Code', 'Name', 'Type', 'Category', 'Status', 'Location', 'Ward', 'Purchase Date', 'Purchase Cost', 'Current Value', 'Manufacturer', 'Serial No'];
        $rows = db()->fetchAll($sql, $params);
        break;

    case 'gis':
        $sql = "SELECT 'Consumer' AS layer_type, COUNT(*) AS total FROM consumers WHERE deleted_at IS NULL AND latitude IS NOT NULL
                UNION ALL SELECT 'Assets', COUNT(*) FROM assets WHERE deleted_at IS NULL AND latitude IS NOT NULL
                UNION ALL SELECT 'Pipelines', COUNT(*) FROM pipelines WHERE status = 'active'";
        $columns = ['Layer Type', 'Total Features'];
        $rows = db()->fetchAll($sql);
        break;

    case 'employee':
        $where = 'WHERE e.deleted_at IS NULL';
        $deptId = get('department_id');
        $empStatus = get('emp_status');
        if (!empty($deptId)) { $where .= ' AND e.department_id = :dept'; $params['dept'] = $deptId; }
        if (!empty($empStatus)) { $where .= ' AND e.status = :status'; $params['status'] = $empStatus; }
        $sql = "SELECT e.employee_code, e.full_name, e.gender, e.mobile, e.email,
                       d.name AS department, des.name AS designation,
                       e.employment_type, e.joining_date, e.status
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN designations des ON e.designation_id = des.id
                {$where} ORDER BY e.joining_date DESC LIMIT 5000";
        $columns = ['Code', 'Full Name', 'Gender', 'Mobile', 'Email', 'Department', 'Designation', 'Type', 'Joining Date', 'Status'];
        $rows = db()->fetchAll($sql, $params);
        break;

    case 'audit':
        $where = 'WHERE 1=1';
        $auditModule = get('audit_module');
        $auditAction = get('audit_action');
        if (!empty($auditModule)) { $where .= ' AND at.module = :module'; $params['module'] = $auditModule; }
        if (!empty($auditAction)) { $where .= ' AND at.action = :action'; $params['action'] = $auditAction; }
        if (!empty($startDate)) { $where .= ' AND at.created_at >= :start_date'; $params['start_date'] = $startDate . ' 00:00:00'; }
        if (!empty($endDate)) { $where .= ' AND at.created_at <= :end_date'; $params['end_date'] = $endDate . ' 23:59:59'; }
        $sql = "SELECT at.*, u.name AS user_name FROM audit_trail at LEFT JOIN users u ON at.user_id = u.id {$where} ORDER BY at.created_at DESC LIMIT 5000";
        $columns = ['ID', 'Timestamp', 'User', 'Action', 'Module', 'Reference Type', 'Reference ID', 'Description'];
        $rows = db()->fetchAll($sql, $params);
        break;
}

$filename = 'report_' . $type . '_' . date('YmdHis');

// Log the export
log_activity(Auth::id(), 'export', 'Reports', "Exported {$type} report as {$format}", [
    'type' => $type,
    'format' => $format,
    'start_date' => $startDate,
    'end_date' => $endDate
]);

// === CSV Export ===
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, $columns);

    foreach ($rows as $row) {
        $flat = [];
        foreach ($row as $val) {
            $flat[] = is_null($val) ? '' : (is_numeric($val) ? $val : strip_tags($val));
        }
        fputcsv($output, $flat);
    }

    fclose($output);
    exit;
}

// === Print / HTML Export ===
if ($format === 'print') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?= escape(ucfirst($type)) ?> Report</title>
        <style>
            body { font-family: 'Inter', Arial, sans-serif; font-size: 12px; padding: 20px; color: #333; }
            h2 { margin-bottom: 5px; }
            .meta { color: #666; font-size: 11px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #f5f6fa; text-align: left; padding: 8px 10px; border: 1px solid #dee2e6; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
            td { padding: 6px 10px; border: 1px solid #dee2e6; }
            tr:nth-child(even) { background: #fafafa; }
            .footer { margin-top: 30px; text-align: center; color: #999; font-size: 11px; }
        </style>
    </head>
    <body>
        <h2><?= escape(ucfirst($type)) ?> Report</h2>
        <div class="meta">Generated: <?= date('Y-m-d H:i:s') ?> | Period: <?= escape($startDate) ?> to <?= escape($endDate) ?> | Records: <?= count($rows) ?></div>
        <table>
            <thead><tr><?php foreach ($columns as $c): ?><th><?= escape($c) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr><?php foreach ($row as $v): ?><td><?= escape(is_null($v) ? '-' : $v) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="footer"><?= APP_NAME ?> - Generated by <?= escape(Auth::user()['name'] ?? 'System') ?></div>
        <script>window.print();</script>
    </body>
    </html>
    <?php
    exit;
}

// === PDF Export (using TCPDF or simple HTML-to-PDF) ===
if ($format === 'pdf') {
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10px; }
        h2 { margin-bottom: 5px; }
        .meta { color: #666; font-size: 9px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f0f0f0; padding: 6px 8px; border: 1px solid #ccc; font-size: 9px; }
        td { padding: 4px 8px; border: 1px solid #ccc; }
        .footer { margin-top: 20px; text-align: center; color: #999; font-size: 9px; }
    </style></head><body>
        <h2>' . escape(ucfirst($type)) . ' Report</h2>
        <div class="meta">Generated: ' . date('Y-m-d H:i:s') . ' | Period: ' . escape($startDate) . ' to ' . escape($endDate) . ' | Records: ' . count($rows) . '</div>
        <table><thead><tr>';
    foreach ($columns as $c) {
        $html .= '<th>' . escape($c) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $v) {
            $html .= '<td>' . escape(is_null($v) ? '-' : $v) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>
        <div class="footer">' . APP_NAME . ' - Generated by ' . escape(Auth::user()['name'] ?? 'System') . '</div>
    </body></html>';

    require_once ROOT_PATH . 'vendor/autoload.php';

    if (class_exists('\Mpdf\Mpdf')) {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10
        ]);
        $mpdf->SetTitle(ucfirst($type) . ' Report - ' . APP_NAME);
        $mpdf->SetAuthor(Auth::user()['name'] ?? APP_NAME);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename . '.pdf', 'D');
    } elseif (class_exists('\TCPDF')) {
        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(APP_NAME);
        $pdf->SetAuthor(Auth::user()['name'] ?? APP_NAME);
        $pdf->SetTitle(ucfirst($type) . ' Report');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filename . '.pdf', 'D');
    } else {
        // Fallback: output HTML for browser PDF
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
    exit;
}

// === XLSX Export (using simple XML-based XLSX or library) ===
if ($format === 'xlsx') {
    require_once ROOT_PATH . 'vendor/autoload.php';

    if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(ucfirst($type));

        // Headers
        foreach (array_values($columns) as $colIdx => $colName) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue($colLetter . '1', $colName);
            $sheet->getStyle($colLetter . '1')->getFont()->setBold(true);
        }

        // Data
        foreach ($rows as $rowIdx => $row) {
            $vals = array_values($row);
            foreach ($vals as $colIdx => $val) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet->setCellValue($colLetter . ($rowIdx + 2), strip_tags(is_null($val) ? '' : $val));
            }
        }

        // Auto-size columns
        foreach (array_keys($columns) as $colIdx) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    } else {
        // Fallback: Simple XLSX using XML
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <?mso-application progid="Excel.Sheet"?>
        <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
        <Worksheet ss:Name="Report"><Table>';
        $xml .= '<Row>';
        foreach ($columns as $c) {
            $xml .= '<Cell><Data ss:Type="String">' . escape($c) . '</Data></Cell>';
        }
        $xml .= '</Row>';
        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $v) {
                $val = strip_tags(is_null($v) ? '' : $v);
                $type = is_numeric($val) ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="' . $type . '">' . escape($val) . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';
        echo $xml;
    }
    exit;
}

// Fallback
header('Location: ' . ADMIN_URL . 'reports/generate.php?type=' . $type);
exit;
