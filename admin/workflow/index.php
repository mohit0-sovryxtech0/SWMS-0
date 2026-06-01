<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/WorkflowEngine.php';
$pageTitle = 'Workflow Dashboard';
$breadcrumbs = [
    ['label' => 'Home', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Workflow Dashboard'],
];
RBAC::requirePermission('dashboard.view');

try {
    $stats = WorkflowEngine::getDashboardStats();
} catch (Exception $e) {
    $stats = ['reading_today' => 0, 'reading_pending_verification' => 0, 'active_cycles' => 0, 'collection_rate' => 0, 'defaulter_count' => 0, 'total_billed' => 0, 'total_collected' => 0, 'total_overdue' => 0];
}

try {
    $recentReadings = db()->fetchAll(
        "SELECT mr.*, c.consumer_no, c.full_name, m.meter_no, u.name AS reader_name
         FROM meter_readings mr
         JOIN consumers c ON mr.consumer_id = c.id
         JOIN meters m ON mr.meter_id = m.id
         LEFT JOIN users u ON mr.read_by = u.id
         ORDER BY mr.created_at DESC LIMIT 10"
    );
} catch (Exception $e) {
    $recentReadings = [];
}

try {
    $activeCycles = WorkflowEngine::getBillingCycles(['status' => 'bills_generated']);
} catch (Exception $e) {
    $activeCycles = [];
}

try {
    $recentPayments = db()->fetchAll(
        "SELECT p.*, c.consumer_no, c.full_name
         FROM payments p
         JOIN consumers c ON p.consumer_id = c.id
         ORDER BY p.created_at DESC LIMIT 10"
    );
} catch (Exception $e) {
    $recentPayments = [];
}

try {
    $upcomingReadings = db()->fetchAll(
        "SELECT s.*, r.route_name
         FROM meter_reading_schedules s
         JOIN meter_reading_routes r ON s.route_id = r.id
         WHERE s.status IN ('pending', 'in_progress')
         ORDER BY s.schedule_start ASC LIMIT 10"
    );
} catch (Exception $e) {
    $upcomingReadings = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.stat-card { border-radius: 12px; transition: all .2s; border: none; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.1); }
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
</style>

<div class="page-header mb-4">
    <h4 class="mb-0"><i class="fas fa-project-diagram me-2 text-primary"></i>Workflow Dashboard</h4>
    <p class="text-muted mb-0">Overview of meter reading, billing, and collection workflows</p>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-tachometer-alt"></i></div>
                    <div>
                        <div class="text-muted small">Readings Today</div>
                        <div class="fs-3 fw-bold"><?= $stats['reading_today'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="text-muted small">Pending Verification</div>
                        <div class="fs-3 fw-bold"><?= $stats['reading_pending_verification'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-sync-alt"></i></div>
                    <div>
                        <div class="text-muted small">Active Cycles</div>
                        <div class="fs-3 fw-bold"><?= $stats['active_cycles'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-percentage"></i></div>
                    <div>
                        <div class="text-muted small">Collection Rate</div>
                        <div class="fs-3 fw-bold"><?= $stats['collection_rate'] ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card h-100 border-danger border-opacity-25">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <div class="text-muted small">Defaulters</div>
                        <div class="fs-3 fw-bold"><?= $stats['defaulter_count'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div>
                        <div class="text-muted small">Total Billed (FY)</div>
                        <div class="fs-3 fw-bold"><?= format_currency($stats['total_billed']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-hand-holding-usd"></i></div>
                    <div>
                        <div class="text-muted small">Collected (FY)</div>
                        <div class="fs-3 fw-bold"><?= format_currency($stats['total_collected']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-dark bg-opacity-10 text-dark"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="text-muted small">Overdue Amount</div>
                        <div class="fs-3 fw-bold text-danger"><?= format_currency($stats['total_overdue']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Readings -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Recent Readings</h5>
                <a href="<?= ADMIN_URL ?>meter-reading/history.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Consumer</th><th>Meter</th><th>Reading</th><th>Consumption</th><th>Reader</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentReadings as $r): ?>
                            <tr>
                                <td><small><?= escape($r['consumer_no']) ?><br><?= escape($r['full_name']) ?></small></td>
                                <td><?= escape($r['meter_no']) ?></td>
                                <td><?= number_format($r['current_reading'], 2) ?></td>
                                <td><?= number_format($r['consumption'], 2) ?></td>
                                <td><small><?= escape($r['reader_name'] ?? '-') ?></small></td>
                                <td><?= $r['is_verified'] ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning text-dark">Pending</span>' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentReadings)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No readings recorded yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Cycles -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-sync-alt me-2 text-primary"></i>Billing Cycles in Progress</h5>
                <a href="<?= ADMIN_URL ?>billing/cycles.php" class="btn btn-sm btn-outline-primary">Manage Cycles</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Cycle</th><th>Period</th><th>Due Date</th><th>Bills</th><th>Collected</th><th>Progress</th></tr></thead>
                        <tbody>
                            <?php foreach ($activeCycles as $c): ?>
                            <?php $progress = $c['total_billed'] > 0 ? round(($c['total_collected'] / $c['total_billed']) * 100, 1) : 0; ?>
                            <tr>
                                <td><strong><?= escape($c['cycle_code']) ?></strong></td>
                                <td><small><?= $c['billing_period_start'] ?> to <?= $c['billing_period_end'] ?></small></td>
                                <td><?= $c['due_date'] ?></td>
                                <td><?= intval($c['bills_generated']) ?></td>
                                <td><?= format_currency($c['total_collected']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <div class="progress flex-grow-1" style="height:6px;max-width:60px">
                                            <div class="progress-bar bg-success" style="width:<?= $progress ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= $progress ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($activeCycles)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No active billing cycles</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Reading Schedules -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>Upcoming Reading Schedules</h5>
                <a href="<?= ADMIN_URL ?>meter-reading/routes.php" class="btn btn-sm btn-outline-primary">Manage Routes</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Route</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($upcomingReadings as $s): ?>
                            <tr>
                                <td><?= escape($s['route_name']) ?></td>
                                <td><small><?= $s['schedule_start'] ?></small></td>
                                <td><small><?= $s['schedule_end'] ?></small></td>
                                <td>
                                    <?php
                                    $badge = ['pending' => 'secondary', 'in_progress' => 'info', 'completed' => 'success'];
                                    $b = $badge[$s['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $b ?>"><?= str_replace('_', ' ', ucfirst($s['status'])) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($upcomingReadings)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No upcoming reading schedules</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2 text-success"></i>Recent Payments</h5>
                <a href="<?= ADMIN_URL ?>billing/payments.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Receipt</th><th>Consumer</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentPayments as $p): ?>
                            <tr>
                                <td><small><?= escape($p['receipt_no']) ?></small></td>
                                <td><small><?= escape($p['consumer_no']) ?><br><?= escape($p['full_name']) ?></small></td>
                                <td><strong><?= format_currency($p['net_amount']) ?></strong></td>
                                <td><span class="badge bg-info"><?= escape($p['payment_method']) ?></span></td>
                                <td><small><?= $p['payment_date'] ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentPayments)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No payments recorded</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tasks me-2 text-primary"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <a href="<?= ADMIN_URL ?>meter-reading/index.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-tachometer-alt fa-2x d-block mb-1"></i>
                            <small>Enter Reading</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="<?= ADMIN_URL ?>meter-reading/verify.php" class="btn btn-outline-warning w-100 py-3">
                            <i class="fas fa-check-double fa-2x d-block mb-1"></i>
                            <small>Verify Readings</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="<?= ADMIN_URL ?>billing/generate.php" class="btn btn-outline-info w-100 py-3">
                            <i class="fas fa-file-invoice fa-2x d-block mb-1"></i>
                            <small>Generate Bills</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="<?= ADMIN_URL ?>billing/cycles.php" class="btn btn-outline-success w-100 py-3">
                            <i class="fas fa-sync-alt fa-2x d-block mb-1"></i>
                            <small>Manage Cycles</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="<?= ADMIN_URL ?>billing/record-payment.php" class="btn btn-outline-success w-100 py-3">
                            <i class="fas fa-hand-holding-usd fa-2x d-block mb-1"></i>
                            <small>Record Payment</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="<?= ADMIN_URL ?>billing/defaulters.php" class="btn btn-outline-danger w-100 py-3">
                            <i class="fas fa-exclamation-triangle fa-2x d-block mb-1"></i>
                            <small>View Defaulters</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="<?= ADMIN_URL ?>reports/index.php" class="btn btn-outline-secondary w-100 py-3">
                            <i class="fas fa-chart-bar fa-2x d-block mb-1"></i>
                            <small>Reports</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="<?= ADMIN_URL ?>billing/gateways.php" class="btn btn-outline-dark w-100 py-3">
                            <i class="fas fa-credit-card fa-2x d-block mb-1"></i>
                            <small>Payment Gateways</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
