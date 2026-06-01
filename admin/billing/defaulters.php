<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('defaulters.view');

$pageTitle = 'Defaulters';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Defaulters']
];

$error = '';
$success = '';

// Handle actions
if (isPost()) {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $action = post('action_type');
        $defaulterId = intval(post('defaulter_id'));

        if ($action === 'send_notice') {
            db()->update('defaulters', [
                'notice_sent' => 1,
                'notice_sent_date' => date('Y-m-d'),
                'status' => 'noticed'
            ], 'id = :id', ['id' => $defaulterId]);
            log_activity(Auth::id(), 'send_defaulter_notice', 'billing', "Sent notice to defaulter #{$defaulterId}");
            set_flash('success', 'Notice sent successfully');
        } elseif ($action === 'disconnect_notice') {
            db()->update('defaulters', [
                'disconnection_notice' => 1,
                'disconnection_date' => date('Y-m-d', strtotime('+7 days')),
                'status' => 'noticed'
            ], 'id = :id', ['id' => $defaulterId]);
            log_activity(Auth::id(), 'send_disconnect_notice', 'billing', "Sent disconnection notice to defaulter #{$defaulterId}");
            set_flash('success', 'Disconnection notice sent');
        } elseif ($action === 'settle') {
            db()->update('defaulters', [
                'action_taken' => 'Settled manually',
                'status' => 'settled'
            ], 'id = :id', ['id' => $defaulterId]);
            log_activity(Auth::id(), 'settle_defaulter', 'billing', "Settled defaulter #{$defaulterId}");
            set_flash('success', 'Defaulter marked as settled');
        }

        redirect(ADMIN_URL . 'billing/defaulters.php');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Query defaulters based on overdue bills
$defaulters = db()->fetchAll(
    "SELECT c.id as consumer_id, c.consumer_no, c.full_name, c.mobile, c.ward_no, c.tole,
            SUM(b.due_amount) as total_due,
            COUNT(b.id) as bills_count,
            MIN(b.due_date) as oldest_due,
            MAX(b.due_date) as latest_due,
            DATEDIFF(NOW(), MIN(b.due_date)) as days_overdue,
            (SELECT MAX(p.payment_date) FROM payments p WHERE p.consumer_id = c.id) as last_payment_date,
            d.id as defaulter_id, d.notice_sent, d.disconnection_notice, d.status as defaulter_status
     FROM consumers c
     JOIN bills b ON c.id = b.consumer_id
     LEFT JOIN defaulters d ON c.id = d.consumer_id AND d.status != 'settled'
     WHERE b.status IN ('pending', 'overdue', 'partial')
     AND b.due_amount > 0
     AND b.due_date < DATE_ADD(NOW(), INTERVAL 1 DAY)
     AND c.status = 'active'
     AND c.deleted_at IS NULL
     GROUP BY c.id
     HAVING total_due > 0
     ORDER BY days_overdue DESC, total_due DESC"
);

// Sync defaulters table
foreach ($defaulters as $d) {
    $defaulterCheck = db()->fetchOne(
        "SELECT id FROM defaulters WHERE consumer_id = ? AND status != 'settled'",
        [$d['consumer_id']]
    );

    if (!$defaulterCheck) {
        $latestBill = db()->fetchOne(
            "SELECT id FROM bills WHERE consumer_id = ? AND status IN ('pending','overdue','partial') AND due_amount > 0 ORDER BY due_date DESC LIMIT 1",
            [$d['consumer_id']]
        );
        if ($latestBill) {
            db()->insert('defaulters', [
                'consumer_id' => $d['consumer_id'],
                'bill_id' => $latestBill['id'],
                'total_due' => $d['total_due'],
                'months_overdue' => ceil($d['days_overdue'] / 30),
                'status' => 'pending'
            ]);
        }
    } else {
        db()->update('defaulters', [
            'total_due' => $d['total_due'],
            'months_overdue' => ceil($d['days_overdue'] / 30)
        ], 'id = :id', ['id' => $defaulterCheck['id']]);
    }
}

$defaulters = db()->fetchAll(
    "SELECT d.*, c.consumer_no, c.full_name, c.mobile, c.ward_no, c.tole,
            (SELECT MAX(p.payment_date) FROM payments p WHERE p.consumer_id = c.id) as last_payment_date
     FROM defaulters d
     JOIN consumers c ON d.consumer_id = c.id
     WHERE d.status != 'settled'
     ORDER BY d.months_overdue DESC, d.total_due DESC"
);

include_once ADMIN_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title mb-0">
            <i class="fas fa-exclamation-triangle me-2 text-danger"></i>Defaulters List
        </h4>
        <div>
            <span class="badge bg-danger fs-6 me-2"><?= count($defaulters) ?> Defaulters</span>
            <a href="<?= ADMIN_URL ?>billing/index.php?status=overdue" class="btn btn-outline-primary">
                <i class="fas fa-file-invoice me-1"></i>View Overdue Bills
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (empty($defaulters)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
        <h5>No Defaulters</h5>
        <p class="text-muted">All consumers are up to date with their payments</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-bordered" id="defaultersTable">
                <thead class="table-light">
                    <tr>
                        <th>Consumer No</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Ward</th>
                        <th class="text-end">Total Due</th>
                        <th>Months Overdue</th>
                        <th>Last Payment</th>
                        <th>Status</th>
                        <th class="no-sort" style="width:200px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($defaulters as $d):
                        $severity = '';
                        $rowClass = '';
                        if ($d['months_overdue'] >= 6) {
                            $severity = 'bg-danger text-white';
                            $rowClass = 'table-danger';
                        } elseif ($d['months_overdue'] >= 3) {
                            $severity = 'bg-warning';
                            $rowClass = 'table-warning';
                        } elseif ($d['months_overdue'] >= 1) {
                            $severity = 'bg-info';
                        }
                        $statusBadge = get_status_badge($d['status']);
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="fw-semibold"><?= escape($d['consumer_no']) ?></td>
                        <td>
                            <?= escape($d['full_name']) ?>
                            <?php if ($d['months_overdue'] >= 6): ?>
                            <i class="fas fa-exclamation-circle text-danger ms-1" title="Severely overdue"></i>
                            <?php endif; ?>
                        </td>
                        <td><?= escape($d['mobile']) ?></td>
                        <td>Ward <?= escape($d['ward_no']) ?></td>
                        <td class="text-end fw-bold text-danger"><?= format_currency($d['total_due']) ?></td>
                        <td>
                            <span class="badge <?= $severity ?: 'bg-secondary' ?>">
                                <?= $d['months_overdue'] ?> month<?= $d['months_overdue'] > 1 ? 's' : '' ?>
                            </span>
                        </td>
                        <td><?= $d['last_payment_date'] ? format_date($d['last_payment_date']) : '<span class="text-muted">Never</span>' ?></td>
                        <td><?= $statusBadge ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-info" onclick="showActionModal(<?= $d['id'] ?>, 'send_notice')" title="Send Notice"
                                    <?= $d['notice_sent'] ? 'disabled' : '' ?>>
                                    <i class="fas fa-envelope"></i> Notice
                                </button>
                                <button type="button" class="btn btn-warning" onclick="showActionModal(<?= $d['id'] ?>, 'disconnect_notice')" title="Disconnect Notice"
                                    <?= $d['disconnection_notice'] ? 'disabled' : '' ?>>
                                    <i class="fas fa-plug"></i> Disconnect
                                </button>
                                <button type="button" class="btn btn-success" onclick="showActionModal(<?= $d['id'] ?>, 'settle')" title="Settle">
                                    <i class="fas fa-check"></i> Settle
                                </button>
                                <a href="<?= ADMIN_URL ?>billing/record-payment.php?consumer_id=<?= $d['consumer_id'] ?>" class="btn btn-primary" title="Record Payment">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Action Confirmation Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="defaulter_id" id="defaulterId">
                <input type="hidden" name="action_type" id="actionType">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="actionModalBody">
                    Are you sure you want to proceed?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="actionModalBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showActionModal(id, action) {
    document.getElementById('defaulterId').value = id;
    document.getElementById('actionType').value = action;

    var titles = {
        'send_notice': 'Send Payment Notice',
        'disconnect_notice': 'Send Disconnection Notice',
        'settle': 'Mark as Settled'
    };
    var bodies = {
        'send_notice': 'Send a payment reminder notice to this defaulter?',
        'disconnect_notice': 'Send a disconnection warning notice to this defaulter? The disconnection will be scheduled in 7 days.',
        'settle': 'Mark this defaulter as settled? This indicates the matter has been resolved.'
    };
    var btnClasses = {
        'send_notice': 'btn-info',
        'disconnect_notice': 'btn-warning',
        'settle': 'btn-success'
    };

    document.getElementById('actionModalTitle').textContent = titles[action] || 'Confirm Action';
    document.getElementById('actionModalBody').textContent = bodies[action] || 'Are you sure?';
    document.getElementById('actionModalBtn').className = 'btn ' + (btnClasses[action] || 'btn-primary');

    new bootstrap.Modal(document.getElementById('actionModal')).show();
}
</script>
<?php
$extraJs = '<script>
$(document).ready(function() {
    $("#defaultersTable").DataTable({
        pageLength: 25,
        order: [[5, "desc"]],
        language: { searchPlaceholder: "Search defaulters..." }
    });
});
</script>';
endif; ?>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
