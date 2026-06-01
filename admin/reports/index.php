<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Reports Center';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Reports']
];
RBAC::requirePermission('reports.view');
require_once __DIR__ . '/../includes/header.php';

$reportTypes = [
    'consumer' => [
        'icon' => 'fas fa-users',
        'color' => '#4e73df',
        'title' => 'Consumer Reports',
        'desc' => 'Consumer registration, demographics, connection types, ward-wise distribution'
    ],
    'billing' => [
        'icon' => 'fas fa-file-invoice-dollar',
        'color' => '#1cc88a',
        'title' => 'Billing Reports',
        'desc' => 'Bills generated, paid, pending, overdue by fiscal year and period'
    ],
    'revenue' => [
        'icon' => 'fas fa-chart-line',
        'color' => '#36b9cc',
        'title' => 'Revenue Reports',
        'desc' => 'Revenue collected, monthly trends, payment method breakdown'
    ],
    'collection' => [
        'icon' => 'fas fa-hand-holding-usd',
        'color' => '#f6c23e',
        'title' => 'Collection Reports',
        'desc' => 'Collection efficiency, recovery rates, outstanding analysis'
    ],
    'defaulter' => [
        'icon' => 'fas fa-exclamation-triangle',
        'color' => '#e74a3b',
        'title' => 'Defaulter Reports',
        'desc' => 'Overdue accounts, aging analysis, notice tracking'
    ],
    'complaint' => [
        'icon' => 'fas fa-headset',
        'color' => '#fd7e14',
        'title' => 'Complaint Reports',
        'desc' => 'Complaints by category, status trends, resolution times'
    ],
    'asset' => [
        'icon' => 'fas fa-building',
        'color' => '#6f42c1',
        'title' => 'Asset Reports',
        'desc' => 'Assets inventory, status, maintenance history, location-wise'
    ],
    'gis' => [
        'icon' => 'fas fa-map-marked-alt',
        'color' => '#20c997',
        'title' => 'GIS Reports',
        'desc' => 'Spatial distribution, layer statistics, coverage analysis'
    ],
    'employee' => [
        'icon' => 'fas fa-user-tie',
        'color' => '#e83e8c',
        'title' => 'Employee Reports',
        'desc' => 'Employee directory, attendance, department-wise stats'
    ],
    'audit' => [
        'icon' => 'fas fa-history',
        'color' => '#858796',
        'title' => 'Audit Reports',
        'desc' => 'System activity logs, user actions, security events'
    ]
];
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Reports Center</h4>
            <p>Select a report type to generate detailed analytics and exports</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print
            </button>
        </div>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($reportTypes as $slug => $report): ?>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a href="<?= ADMIN_URL ?>reports/generate.php?type=<?= $slug ?>" class="text-decoration-none">
            <div class="card report-card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="report-icon mb-3" style="color: <?= $report['color'] ?>;">
                        <i class="<?= $report['icon'] ?> fa-3x"></i>
                    </div>
                    <h5 class="card-title mb-2"><?= escape($report['title']) ?></h5>
                    <p class="card-text text-muted small mb-0"><?= escape($report['desc']) ?></p>
                </div>
                <div class="card-footer bg-transparent border-top-0 text-center pb-3">
                    <span class="btn btn-sm" style="background: <?= $report['color'] ?>; color: #fff;">
                        <i class="fas fa-chart-bar me-1"></i> Generate Report
                    </span>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<style>
.report-card { transition: transform 0.2s, box-shadow 0.2s; border-radius: 12px; cursor: pointer; }
.report-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important; }
.report-icon { transition: transform 0.3s; }
.report-card:hover .report-icon { transform: scale(1.1); }
</style>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
