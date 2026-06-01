<?php
$pageTitle = 'Dashboard';
$breadcrumbs = [['label' => 'Dashboard']];
require_once __DIR__ . '/../includes/header.php';

// Fetch KPIs
$totalConsumers = db()->fetchColumn("SELECT COUNT(*) FROM consumers WHERE deleted_at IS NULL");
$activeConnections = db()->fetchColumn("SELECT COUNT(*) FROM consumers WHERE status = 'active' AND deleted_at IS NULL");
$totalRevenue = db()->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE) AND YEAR(created_at) = YEAR(CURRENT_DATE)");
$outstandingDues = db()->fetchColumn("SELECT COALESCE(SUM(due_amount), 0) FROM bills WHERE status IN ('pending', 'overdue', 'partial')");
$openComplaints = db()->fetchColumn("SELECT COUNT(*) FROM complaints WHERE status IN ('open', 'in_progress') AND deleted_at IS NULL");
$totalAssets = db()->fetchColumn("SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL");
$collectionEfficiency = 0;

$totalBilled = db()->fetchColumn("SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE MONTH(generated_at) = MONTH(CURRENT_DATE) AND YEAR(generated_at) = YEAR(CURRENT_DATE)");
if ($totalBilled > 0) {
    $collectionEfficiency = round(($totalRevenue / $totalBilled) * 100, 1);
}

// Recent activities
$recentActivities = db()->fetchAll(
    "SELECT al.*, u.name as user_name FROM activity_logs al 
     LEFT JOIN users u ON al.user_id = u.id 
     ORDER BY al.created_at DESC LIMIT 10"
);

// Monthly revenue chart data
$monthlyRevenue = db()->fetchAll(
    "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total 
     FROM payments WHERE status = 'completed' 
     AND payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(payment_date, '%Y-%m') ORDER BY month"
);

// Connection types
$connectionTypes = db()->fetchAll(
    "SELECT connection_type, COUNT(*) as total FROM consumers WHERE deleted_at IS NULL GROUP BY connection_type"
);

// Recent bills
$recentBills = db()->fetchAll(
    "SELECT b.*, c.consumer_no, c.full_name 
     FROM bills b JOIN consumers c ON b.consumer_id = c.id 
     ORDER BY b.created_at DESC LIMIT 5"
);
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Executive Dashboard</h4>
            <p>Welcome back, <?= escape($currentUser['name'] ?? 'User') ?>! Here's your system overview.</p>
        </div>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="far fa-calendar-alt me-1"></i> This Month
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#">Today</a></li>
                    <li><a class="dropdown-item" href="#">This Week</a></li>
                    <li><a class="dropdown-item active" href="#">This Month</a></li>
                    <li><a class="dropdown-item" href="#">This Year</a></li>
                </ul>
            </div>
            <button class="btn btn-primary btn-sm" onclick="window.location.reload()">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>
    </div>
</div>

<!-- KPI Cards Row 1 -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Total Consumers</div>
                <div class="stat-value"><?= number_format($totalConsumers) ?></div>
                <div class="stat-change up"><i class="fas fa-arrow-up"></i> Registered consumers</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-water"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Active Connections</div>
                <div class="stat-value"><?= number_format($activeConnections) ?></div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> Active</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon secondary">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Monthly Revenue</div>
                <div class="stat-value"><?= format_currency($totalRevenue) ?></div>
                <div class="stat-change up">Collection this month</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Outstanding Dues</div>
                <div class="stat-value"><?= format_currency($outstandingDues) ?></div>
                <div class="stat-change down"><i class="fas fa-arrow-down"></i> Pending collection</div>
            </div>
        </div>
    </div>
</div>

<!-- KPI Cards Row 2 -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-headset"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Open Complaints</div>
                <div class="stat-value"><?= number_format($openComplaints) ?></div>
                <div class="stat-change down">Requires attention</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Total Assets</div>
                <div class="stat-value"><?= number_format($totalAssets) ?></div>
                <div class="stat-change up">Infrastructure</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Collection Efficiency</div>
                <div class="stat-value"><?= $collectionEfficiency ?>%</div>
                <div class="stat-change <?= $collectionEfficiency >= 70 ? 'up' : 'down' ?>">
                    <?= $collectionEfficiency >= 70 ? 'Good' : 'Needs improvement' ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Collection Efficiency</div>
                <div class="stat-value"><?= $collectionEfficiency ?>%</div>
                <div class="stat-change up">This month</div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-area me-2 text-primary"></i>Revenue Trend (12 Months)</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2 text-primary"></i>Connection Types</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:250px;">
                    <canvas id="connectionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity & Quick Actions -->
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history me-2 text-primary"></i>Recent Activities</h5>
                <a href="<?= ADMIN_URL ?>users/audit-logs.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivities)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No recent activities</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td>
                                        <span class="fw-600"><?= escape($activity['user_name'] ?? 'System') ?></span>
                                    </td>
                                    <td><?= escape($activity['action']) ?></td>
                                    <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= escape($activity['module']) ?></span></td>
                                    <td class="text-muted"><small><?= time_ago($activity['created_at']) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bolt me-2 text-primary"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="<?= ADMIN_URL ?>consumers/create.php" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>New Consumer</span>
                    </a>
                    <a href="<?= ADMIN_URL ?>billing/generate.php" class="quick-action-btn">
                        <i class="fas fa-file-invoice"></i>
                        <span>Generate Bills</span>
                    </a>
                    <a href="<?= ADMIN_URL ?>billing/payments.php?action=record" class="quick-action-btn">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Record Payment</span>
                    </a>
                    <a href="<?= ADMIN_URL ?>meter-reading/index.php" class="quick-action-btn">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Meter Reading</span>
                    </a>
                    <a href="<?= ADMIN_URL ?>complaints/create.php" class="quick-action-btn">
                        <i class="fas fa-headset"></i>
                        <span>New Complaint</span>
                    </a>
                    <a href="<?= ADMIN_URL ?>reports/index.php" class="quick-action-btn">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Bills -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-receipt me-2 text-primary"></i>Recent Bills</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Consumer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentBills)): ?>
                                <tr><td colspan="3" class="text-center py-3 text-muted">No recent bills</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentBills as $bill): ?>
                                <tr>
                                    <td><small><?= escape($bill['consumer_no']) ?></small></td>
                                    <td><small><?= format_currency($bill['total_amount']) ?></small></td>
                                    <td><?= get_status_badge($bill['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('revenueChart').getContext('2d');
    var months = [];
    var revenues = [];
    <?php foreach (($monthlyRevenue ?? []) as $row): ?>
    months.push('<?= $row['month'] ?>');
    revenues.push(parseFloat('<?= $row['total'] ?>'));
    <?php endforeach; ?>
    
    if (months.length === 0) {
        months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        revenues = [0, 0, 0, 0, 0, 0];
    }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Revenue',
                data: revenues,
                borderColor: '#181CB8',
                backgroundColor: 'rgba(24,28,184,0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#181CB8',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'NRs. ' + value.toLocaleString('en-IN'); }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    var ctx2 = document.getElementById('connectionChart').getContext('2d');
    var types = [];
    var counts = [];
    var colors = ['#181CB8', '#FF5700', '#28a745', '#ffc107'];
    <?php foreach (($connectionTypes ?? []) as $row): ?>
    types.push('<?= $row['connection_type'] ?>');
    counts.push(parseInt('<?= $row['total'] ?>'));
    <?php endforeach; ?>
    
    if (types.length === 0) {
        types = ['Household', 'Commercial', 'Institutional'];
        counts = [0, 0, 0];
    }

    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: types,
            datasets: [{
                data: counts,
                backgroundColor: colors.slice(0, types.length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 16, usePointStyle: true, font: { size: 12 } }
                }
            },
            cutout: '65%'
        }
    });
});
</script>
<?php
$extraJs = ob_get_clean();
require_once __DIR__ . '/../includes/footer.php';
?>
