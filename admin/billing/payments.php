<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('payments.view');

$pageTitle = 'Payments';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Payments']
];

$paymentMethods = ['', 'cash', 'bank', 'esewa', 'khalti', 'fonepay', 'qr', 'cheque', 'online'];

// Handle DataTables AJAX before HTML output
if (isPost() && post('action') === 'getData') {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $draw = intval(post('draw'));
        $start = intval(post('start'));
        $length = intval(post('length')) ?: 25;
        $search = post('search')['value'] ?? '';

        $orderColumn = post('order')[0]['column'] ?? 5;
        $orderDir = post('order')[0]['dir'] ?? 'desc';
        $columns = ['p.receipt_no', 'c.full_name', 'c.consumer_no', 'p.net_amount', 'p.payment_method', 'p.payment_date', 'p.transaction_id', 'p.status'];
        $orderBy = $columns[$orderColumn] ?? 'p.payment_date';

        $where = "";
        $params = [];

        $method = post('payment_method');
        if (!empty($method) && in_array($method, ['cash', 'bank', 'esewa', 'khalti', 'fonepay', 'qr', 'cheque', 'online'])) {
            $where .= " AND p.payment_method = :method";
            $params['method'] = $method;
        }

        $dateFrom = post('date_from');
        if (!empty($dateFrom)) {
            $where .= " AND p.payment_date >= :date_from";
            $params['date_from'] = $dateFrom;
        }

        $dateTo = post('date_to');
        if (!empty($dateTo)) {
            $where .= " AND p.payment_date <= :date_to";
            $params['date_to'] = $dateTo;
        }

        if (!empty($search)) {
            $where .= " AND (p.receipt_no LIKE :search OR c.full_name LIKE :search2 OR c.consumer_no LIKE :search3 OR p.transaction_id LIKE :search4)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
            $params['search4'] = "%{$search}%";
        }

        $total = db()->fetchColumn(
            "SELECT COUNT(*) FROM payments p
             JOIN consumers c ON p.consumer_id = c.id
             WHERE 1=1 {$where}",
            $params
        );

        $sql = "SELECT p.id, p.receipt_no, p.net_amount, p.payment_method, p.payment_date,
                       p.transaction_id, p.status, p.created_at,
                       c.full_name AS consumer, c.consumer_no
                FROM payments p
                JOIN consumers c ON p.consumer_id = c.id
                WHERE 1=1 {$where}
                ORDER BY {$orderBy} {$orderDir}
                LIMIT {$start}, {$length}";

        $rows = db()->fetchAll($sql, $params);

        $data = [];
        foreach ($rows as $row) {
            $statusBadge = get_status_badge($row['status']);
            $data[] = [
                'receipt_no' => '<a href="' . ADMIN_URL . 'billing/receipt.php?payment_id=' . $row['id'] . '" class="fw-semibold text-decoration-none" target="_blank">' . escape($row['receipt_no']) . '</a>',
                'consumer' => escape($row['consumer']),
                'consumer_no' => escape($row['consumer_no']),
                'amount' => format_currency($row['net_amount']),
                'method' => '<span class="badge bg-info">' . escape(ucfirst($row['payment_method'])) . '</span>',
                'date' => format_date($row['payment_date']),
                'transaction_id' => escape($row['transaction_id'] ?? '-'),
                'status' => $statusBadge,
                'action' => '<div class="btn-group btn-group-sm">
                    <a href="' . ADMIN_URL . 'billing/receipt.php?payment_id=' . $row['id'] . '" class="btn btn-info" target="_blank" title="Receipt"><i class="fas fa-receipt"></i></a>
                </div>'
            ];
        }

        json_response([
            'draw' => $draw,
            'recordsTotal' => intval($total),
            'recordsFiltered' => intval($total),
            'data' => $data
        ]);
    } catch (Exception $e) {
        json_response(['error' => $e->getMessage()], 500);
    }
}

$ajaxUrl = ADMIN_URL . 'billing/payments.php';
$csrfToken = csrf_token();
$extraJs = <<<JS
<script>
\$(document).ready(function() {
    var table = \$('#paymentsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{$ajaxUrl}',
            type: 'POST',
            data: function(d) {
                d.action = 'getData';
                d.payment_method = \$('#filterMethod').val();
                d.date_from = \$('#filterDateFrom').val();
                d.date_to = \$('#filterDateTo').val();
                d.csrf_token = '{$csrfToken}';
            }
        },
        columns: [
            { data: 'receipt_no', name: 'p.receipt_no' },
            { data: 'consumer', name: 'c.full_name', orderable: false },
            { data: 'consumer_no', name: 'c.consumer_no', orderable: false },
            { data: 'amount', name: 'p.net_amount', searchable: false },
            { data: 'method', name: 'p.payment_method', searchable: false },
            { data: 'date', name: 'p.payment_date', searchable: false },
            { data: 'transaction_id', name: 'p.transaction_id', orderable: false },
            { data: 'status', name: 'p.status', searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[5, 'desc']],
        pageLength: 25,
        language: { searchPlaceholder: 'Search receipts, consumers...' }
    });

    \$('#filterMethod, #filterDateFrom, #filterDateTo').on('change', function() {
        table.ajax.reload();
    });
});
</script>
JS;

include_once ADMIN_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title mb-0">
            <i class="fas fa-hand-holding-usd me-2 text-success"></i>Payments
        </h4>
        <div class="btn-group">
            <a href="<?= ADMIN_URL ?>billing/index.php" class="btn btn-outline-primary">
                <i class="fas fa-file-invoice me-1"></i>Bills
            </a>
            <?php if (RBAC::can('payments.record')): ?>
            <a href="<?= ADMIN_URL ?>billing/record-payment.php" class="btn btn-success">
                <i class="fas fa-plus-circle me-1"></i>Record Payment
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label small">Payment Method</label>
                <select id="filterMethod" class="form-select form-select-sm">
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
            <div class="col-md-4">
                <label class="form-label small">Date From</label>
                <input type="date" id="filterDateFrom" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Date To</label>
                <input type="date" id="filterDateTo" class="form-control form-control-sm">
            </div>
        </div>

        <div class="table-responsive">
            <table id="paymentsTable" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Receipt No</th>
                        <th>Consumer</th>
                        <th>Consumer No</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Date</th>
                        <th>Transaction ID</th>
                        <th>Status</th>
                        <th class="no-sort" style="width:60px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
