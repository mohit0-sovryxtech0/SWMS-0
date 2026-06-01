<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Payment History';
if (!isset($_SESSION['citizen_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(CITIZEN_URL . 'login.php');
}
require_once __DIR__ . '/includes/header.php';

$consumerId = citizenId();
$db = db();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$totalPayments = $db->fetchColumn(
    "SELECT COUNT(*) FROM payments WHERE consumer_id = ?",
    [$consumerId]
);

$totalPaid = $db->fetchColumn(
    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE consumer_id = ? AND status = 'completed'",
    [$consumerId]
);

$payments = $db->fetchAll(
    "SELECT p.id, p.receipt_no, p.payment_date, p.amount, p.net_amount, p.payment_method,
            p.transaction_id, p.reference_no, p.status, p.remarks,
            GROUP_CONCAT(DISTINCT b.bill_no SEPARATOR ', ') as bill_nos
     FROM payments p
     LEFT JOIN bill_payments bp ON p.id = bp.payment_id
     LEFT JOIN bills b ON bp.bill_id = b.id
     WHERE p.consumer_id = ?
     GROUP BY p.id
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?",
    [$consumerId, $perPage, $offset]
);
?>
<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col">
                <h2><i class="fas fa-history me-2"></i> Payment History</h2>
                <p>Your complete payment records</p>
            </div>
            <div class="col-auto">
                <span class="badge bg-light text-dark px-3 py-2">Total Paid: <?= format_currency($totalPaid) ?></span>
            </div>
        </div>
    </div>
</div>
<div class="container pb-5">
    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card bg-success">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?= $totalPayments ?></div>
                <div class="stat-label">Total Transactions</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-primary">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-value"><?= format_currency($totalPaid) ?></div>
                <div class="stat-label">Total Amount Paid</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-info">
                <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                <div class="stat-value"><?= $db->fetchColumn("SELECT COUNT(DISTINCT payment_method) FROM payments WHERE consumer_id = ? AND status = 'completed'", [$consumerId]) ?: 0 ?></div>
                <div class="stat-label">Payment Methods Used</div>
            </div>
        </div>
    </div>

    <?php if (empty($payments)): ?>
        <div class="text-center py-5">
            <i class="fas fa-credit-card fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">No payment history yet</h5>
            <p>Payments you make will appear here.</p>
            <a href="<?= CITIZEN_URL ?>bills.php" class="btn btn-primary"><i class="fas fa-file-invoice me-1"></i> View Bills to Pay</a>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-citizen mb-0">
                        <thead>
                            <tr>
                                <th>Receipt No</th>
                                <th>Date</th>
                                <th>Bills Paid</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Transaction ID</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                            <tr>
                                    <td class="fw-semibold"><?= escape($p['receipt_no']) ?></td>
                                <td><?= format_date($p['payment_date']) ?></td>
                                <td><small><?= escape($p['bill_nos'] ?? '-') ?></small></td>
                                <td class="fw-semibold"><?= format_currency($p['amount']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= escape(ucfirst($p['payment_method'])) ?></span>
                                </td>
                                <td><small class="text-muted"><?= escape($p['transaction_id'] ?? '-') ?></small></td>
                                <td><?= get_status_badge($p['status']) ?></td>
                                <td>
                                    <a href="<?= CITIZEN_URL ?>receipt.php?payment_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Receipt"><i class="fas fa-receipt"></i></a>
                                    <a href="<?= CITIZEN_URL ?>receipt.php?payment_id=<?= $p['id'] ?>&print=1" class="btn btn-sm btn-outline-secondary" title="Print/PDF" target="_blank"><i class="fas fa-print"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?= pagination($totalPayments, $page, $perPage, CITIZEN_URL . 'payment-history.php?page={page}') ?>
    <?php endif; ?>
</div>
<?php include_once __DIR__ . '/includes/footer.php'; ?>
