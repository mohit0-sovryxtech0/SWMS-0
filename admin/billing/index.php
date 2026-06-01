<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('bills.view');

$pageTitle = 'Bills Management';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Bills']
];

$statuses = ['', 'pending', 'paid', 'partial', 'overdue', 'cancelled'];

$fiscalYears = db()->fetchAll("SELECT id, label FROM fiscal_years ORDER BY start_date DESC");

$extraCss = '<style>
.dataTable tbody tr.status-overdue { background-color: #fff5f5 !important; }
.dataTable tbody tr.status-cancelled { background-color: #f8f9fa !important; opacity: 0.7; }
.dataTable tbody tr.status-paid { background-color: #f0fff4 !important; }
</style>';

$ajaxUrl = ADMIN_URL . 'billing/index.php';
$csrfToken = csrf_token();
// Handle DataTables AJAX request before any HTML output
if (isPost() && post('action') === 'getData') {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $draw = intval(post('draw'));
        $start = intval(post('start'));
        $length = intval(post('length')) ?: 25;
        $search = post('search')['value'] ?? '';

        $orderColumn = post('order')[0]['column'] ?? 3;
        $orderDir = post('order')[0]['dir'] ?? 'desc';
        $orderColumns = ['b.bill_no', 'c.full_name', 'c.consumer_no', 'b.billing_period_start', 'b.total_amount', 'b.due_amount', 'b.due_date', 'b.status'];
        $orderBy = $orderColumns[$orderColumn] ?? 'b.billing_period_start';

        $where = "WHERE 1=1";
        $params = [];

        $status = post('status');
        if (!empty($status) && in_array($status, ['pending', 'paid', 'partial', 'overdue', 'cancelled'])) {
            $where .= " AND b.status = :status";
            $params['status'] = $status;
        }

        $fy = post('fiscal_year_id');
        if (!empty($fy)) {
            $where .= " AND b.fiscal_year_id = :fy";
            $params['fy'] = intval($fy);
        }

        $dateFrom = post('date_from');
        if (!empty($dateFrom)) {
            $where .= " AND b.billing_period_start >= :date_from";
            $params['date_from'] = $dateFrom;
        }

        $dateTo = post('date_to');
        if (!empty($dateTo)) {
            $where .= " AND b.billing_period_end <= :date_to";
            $params['date_to'] = $dateTo;
        }

        if (!empty($search)) {
            $where .= " AND (b.bill_no LIKE :search OR c.full_name LIKE :search2 OR c.consumer_no LIKE :search3)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
        }

        $total = db()->fetchColumn(
            "SELECT COUNT(*) FROM bills b JOIN consumers c ON b.consumer_id = c.id {$where}",
            $params
        );

        $sql = "SELECT b.id, b.bill_no, b.total_amount, b.paid_amount, b.due_amount, 
                       b.billing_period_start, b.billing_period_end, b.due_date, b.status,
                       c.full_name AS consumer, c.consumer_no
                FROM bills b
                JOIN consumers c ON b.consumer_id = c.id
                {$where}
                ORDER BY {$orderBy} {$orderDir}
                LIMIT {$start}, {$length}";

        $rows = db()->fetchAll($sql, $params);

        $data = [];
        foreach ($rows as $row) {
            $statusBadge = get_status_badge($row['status']);
            $data[] = [
                'bill_no' => '<a href="' . ADMIN_URL . 'billing/view.php?id=' . $row['id'] . '" class="fw-semibold text-decoration-none">' . escape($row['bill_no']) . '</a>',
                'consumer' => escape($row['consumer']),
                'consumer_no' => escape($row['consumer_no']),
                'period' => format_date($row['billing_period_start']) . ' - ' . format_date($row['billing_period_end']),
                'total_amount' => format_currency($row['total_amount']),
                'due_amount' => format_currency($row['due_amount']),
                'due_date' => format_date($row['due_date']),
                'status' => $statusBadge,
                'action' => '<div class="btn-group btn-group-sm">
                    <a href="' . ADMIN_URL . 'billing/view.php?id=' . $row['id'] . '" class="btn btn-info" title="View"><i class="fas fa-eye"></i></a>
                    ' . (RBAC::can('bills.edit') ? '<a href="' . ADMIN_URL . 'billing/edit.php?id=' . $row['id'] . '" class="btn btn-warning" title="Edit"><i class="fas fa-edit"></i></a>' : '') . '
                    ' . (RBAC::can('bills.cancel') && $row['status'] !== 'cancelled' && $row['status'] !== 'paid' ? '<button type="button" class="btn btn-danger btn-cancel-bill" data-id="' . $row['id'] . '" title="Cancel"><i class="fas fa-ban"></i></button>' : '') . '
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

$ajaxUrl = ADMIN_URL . 'billing/index.php';
$csrfToken = csrf_token();
$extraJs = <<<JS
<script>
$(document).ready(function() {
    var table = $('#billsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{$ajaxUrl}',
            type: 'POST',
            data: function(d) {
                d.action = 'getData';
                d.status = $('#filterStatus').val();
                d.fiscal_year_id = $('#filterFy').val();
                d.date_from = $('#filterDateFrom').val();
                d.date_to = $('#filterDateTo').val();
                d.csrf_token = '{$csrfToken}';
            }
        },
        columns: [
            { data: 'bill_no', name: 'b.bill_no' },
            { data: 'consumer', name: 'c.full_name', orderable: false },
            { data: 'consumer_no', name: 'c.consumer_no', orderable: false },
            { data: 'period', name: 'b.billing_period_start', orderable: true },
            { data: 'total_amount', name: 'b.total_amount', searchable: false },
            { data: 'due_amount', name: 'b.due_amount', searchable: false },
            { data: 'due_date', name: 'b.due_date', searchable: false },
            { data: 'status', name: 'b.status', searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[3, 'desc']],
        pageLength: 25,
        language: { searchPlaceholder: 'Search bills...' }
    });

    $('#filterStatus, #filterFy, #filterDateFrom, #filterDateTo').on('change', function() {
        table.ajax.reload();
    });

    $('#billsTable').on('click', '.btn-cancel-bill', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#cancelBillId').val(id);
        $('#cancelModal').modal('show');
    });
});
</script>
JS;

include_once ADMIN_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title mb-0">
            <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Bills Management
        </h4>
        <div class="btn-group">
            <?php if (RBAC::can('bills.generate')): ?>
            <a href="<?= ADMIN_URL ?>billing/generate.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i>Generate Bills
            </a>
            <?php endif; ?>
            <a href="<?= ADMIN_URL ?>billing/payments.php" class="btn btn-success">
                <i class="fas fa-hand-holding-usd me-1"></i>Payments
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <label class="form-label small">Status</label>
                <select id="filterStatus" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="partial">Partial</option>
                    <option value="overdue">Overdue</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Fiscal Year</label>
                <select id="filterFy" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    <?php foreach ($fiscalYears as $fy): ?>
                    <option value="<?= $fy['id'] ?>"><?= escape($fy['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Date From</label>
                <input type="date" id="filterDateFrom" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Date To</label>
                <input type="date" id="filterDateTo" class="form-control form-control-sm">
            </div>
        </div>

        <div class="table-responsive">
            <table id="billsTable" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Bill No</th>
                        <th>Consumer</th>
                        <th>Consumer No</th>
                        <th>Period</th>
                        <th>Total</th>
                        <th>Due Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="no-sort" style="width:100px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= ADMIN_URL ?>billing/cancel.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="cancelBillId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban text-danger me-2"></i>Cancel Bill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this bill? This action cannot be undone.</p>
                    <div class="mb-3">
                        <label class="form-label">Reason for Cancellation <span class="text-danger">*</span></label>
                        <textarea name="cancel_reason" class="form-control" rows="3" required placeholder="Enter reason for cancellation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-ban me-1"></i>Cancel Bill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
