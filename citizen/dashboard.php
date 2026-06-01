<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Dashboard';
if (!isset($_SESSION['citizen_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(CITIZEN_URL . 'login.php');
}
require_once __DIR__ . '/includes/header.php';

$consumerId = citizenId();
$db = db();

// Unpaid bills
$unpaidCount = $db->fetchColumn(
    "SELECT COUNT(*) FROM bills WHERE consumer_id = ? AND status IN ('pending', 'overdue') AND deleted_at IS NULL",
    [$consumerId]
);

$totalDue = $db->fetchColumn(
    "SELECT COALESCE(SUM(due_amount), 0) FROM bills WHERE consumer_id = ? AND status IN ('pending', 'overdue') AND deleted_at IS NULL",
    [$consumerId]
);

$lastBill = $db->fetchOne(
    "SELECT id, bill_no, total_amount, due_amount, status, due_date, billing_period_end FROM bills WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1",
    [$consumerId]
);

$openComplaints = $db->fetchColumn(
    "SELECT COUNT(*) FROM complaints WHERE consumer_id = ? AND status IN ('open', 'in_progress') AND deleted_at IS NULL",
    [$consumerId]
);

$recentPayments = $db->fetchAll(
    "SELECT p.receipt_no, p.amount, p.payment_date, p.payment_method, p.status
     FROM payments p WHERE p.consumer_id = ? ORDER BY p.created_at DESC LIMIT 5",
    [$consumerId]
);

$billsCount = $db->fetchColumn(
    "SELECT COUNT(*) FROM bills WHERE consumer_id = ? AND deleted_at IS NULL",
    [$consumerId]
);

$paymentsCount = $db->fetchColumn(
    "SELECT COUNT(*) FROM payments WHERE consumer_id = ? AND status = 'completed'",
    [$consumerId]
);
?>
<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col">
                <h2><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h2>
                <p>Welcome back, <?= escape($citizen['full_name']) ?></p>
            </div>
            <div class="col-auto">
                <span class="badge bg-light text-dark fs-6 px-3 py-2">
                    <i class="fas fa-id-card me-1"></i> <?= escape($citizen['consumer_no']) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <!-- Status Overview -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card bg-primary">
                <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-value"><?= $billsCount ?></div>
                <div class="stat-label">Total Bills</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card <?= $unpaidCount > 0 ? 'bg-warning' : 'bg-success' ?>">
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-value"><?= $unpaidCount ?></div>
                <div class="stat-label">Unpaid Bills</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card bg-info">
                <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                <div class="stat-value"><?= format_currency($totalDue) ?></div>
                <div class="stat-label">Total Due</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card bg-danger">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-value"><?= $openComplaints ?></div>
                <div class="stat-label">Open Complaints</div>
            </div>
        </div>
    </div>

    <!-- Last Bill Alert -->
    <?php if ($lastBill && $lastBill['status'] !== 'paid'): ?>
    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="fas fa-clock fa-2x me-3"></i>
        <div>
            <strong>Bill Due:</strong> Bill #<?= escape($lastBill['bill_no']) ?> of <?= format_date($lastBill['billing_period_end'], 'M Y') ?> — 
            <strong><?= format_currency($lastBill['due_amount']) ?></strong> due on <?= format_date($lastBill['due_date']) ?>
            <?= strtotime($lastBill['due_date']) < time() ? ' <span class="badge bg-danger">Overdue</span>' : '' ?>
        </div>
        <a href="<?= CITIZEN_URL ?>bills.php?id=<?= $lastBill['id'] ?>" class="btn btn-sm btn-outline-dark ms-auto">View Bill</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="<?= CITIZEN_URL ?>bills.php" class="quick-action-card">
                <div class="qa-icon" style="background:rgba(0,56,147,0.1);color:var(--primary);"><i class="fas fa-file-invoice"></i></div>
                <h6>View Bills</h6>
                <small>Check all bills</small>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= CITIZEN_URL ?>bills.php" class="quick-action-card">
                <div class="qa-icon" style="background:rgba(40,167,69,0.1);color:#28a745;"><i class="fas fa-credit-card"></i></div>
                <h6>Pay Now</h6>
                <small>Pay outstanding</small>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= CITIZEN_URL ?>complaints.php" class="quick-action-card">
                <div class="qa-icon" style="background:rgba(255,193,7,0.1);color:#e0a800;"><i class="fas fa-exclamation-triangle"></i></div>
                <h6>Submit Complaint</h6>
                <small>Register issue</small>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= CITIZEN_URL ?>complaints.php" class="quick-action-card">
                <div class="qa-icon" style="background:rgba(23,162,184,0.1);color:#17a2b8;"><i class="fas fa-tasks"></i></div>
                <h6>Track Status</h6>
                <small>View complaint status</small>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Payments -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-history me-2 text-primary"></i> Recent Payments</span>
                    <a href="<?= CITIZEN_URL ?>payment-history.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentPayments)): ?>
                        <p class="text-muted text-center py-4 mb-0"><i class="fas fa-credit-card me-2"></i> No payments yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-citizen mb-0">
                            <thead>
                                <tr>
                                    <th>Receipt</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $p): ?>
                                <tr>
                                    <td class="fw-semibold"><?= escape($p['receipt_no']) ?></td>
                                    <td><?= format_date($p['payment_date']) ?></td>
                                    <td><?= format_currency($p['amount']) ?></td>
                                    <td><?= escape(ucfirst($p['payment_method'])) ?></td>
                                    <td><?= get_status_badge($p['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Consumer Info -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-user me-2 text-primary"></i> Account Information</div>
                <div class="card-body">
                    <table class="table table-citizen mb-0">
                        <tbody>
                            <tr><td class="text-muted" style="width:140px;">Consumer No</td><td class="fw-semibold"><?= escape($citizen['consumer_no']) ?></td></tr>
                            <tr><td class="text-muted">Full Name</td><td><?= escape($citizen['full_name']) ?></td></tr>
                            <tr><td class="text-muted">Mobile</td><td><?= escape($citizen['mobile']) ?></td></tr>
                            <tr><td class="text-muted">Email</td><td><?= escape($citizen['email'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">Connection</td><td><?= get_connection_type_badge($citizen['connection_type']) ?></td></tr>
                            <tr><td class="text-muted">Ward No.</td><td><?= escape($citizen['ward_no'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">Status</td><td><?= get_status_badge($citizen['status']) ?></td></tr>
                            <tr><td class="text-muted">Tole</td><td><?= escape($citizen['tole'] ?? '-') ?></td></tr>
                        </tbody>
                    </table>
                    <a href="<?= CITIZEN_URL ?>profile.php" class="btn btn-sm btn-outline-primary mt-3"><i class="fas fa-edit me-1"></i> Edit Profile</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/includes/footer.php'; ?>
