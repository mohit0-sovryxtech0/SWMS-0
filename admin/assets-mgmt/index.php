<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Asset Management Dashboard';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Assets', 'url' => ADMIN_URL . 'assets-mgmt/index.php'],
    ['label' => 'Dashboard']
];
RBAC::requirePermission('assets.view');

require_once __DIR__ . '/../includes/header.php';

// Summary by type
$typeSummary = db()->fetchAll(
    "SELECT asset_type, COUNT(*) AS total,
            SUM(CASE WHEN status = 'operational' THEN 1 ELSE 0 END) AS operational,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS in_maintenance,
            SUM(CASE WHEN status = 'damaged' THEN 1 ELSE 0 END) AS damaged,
            SUM(CASE WHEN status = 'decommissioned' THEN 1 ELSE 0 END) AS decommissioned,
            COALESCE(SUM(purchase_cost), 0) AS total_value
     FROM assets WHERE deleted_at IS NULL
     GROUP BY asset_type ORDER BY asset_type"
);

// Status summary
$statusSummary = db()->fetchAll(
    "SELECT status, COUNT(*) AS total FROM assets WHERE deleted_at IS NULL GROUP BY status"
);

// Recent maintenance
$recentMaintenance = db()->fetchAll(
    "SELECT am.*, a.name AS asset_name, a.asset_code
     FROM asset_maintenance am
     JOIN assets a ON am.asset_id = a.id
     ORDER BY am.created_at DESC LIMIT 5"
);

// Assets with GPS coordinates
$mapAssets = db()->fetchAll(
    "SELECT id, name, asset_type, asset_code, latitude, longitude, status, ward_no
     FROM assets
     WHERE deleted_at IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL
     ORDER BY name"
);

// Stats
$totalAssets = db()->fetchColumn("SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL");
$operational = db()->fetchColumn("SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL AND status = 'operational'");
$underMaintenance = db()->fetchColumn("SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL AND status = 'maintenance'");
$damaged = db()->fetchColumn("SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL AND status = 'damaged'");
$totalAssetValue = db()->fetchColumn("SELECT COALESCE(SUM(purchase_cost), 0) FROM assets WHERE deleted_at IS NULL");

$typeLabels = [
    'water_tank' => 'Water Tank', 'pipeline' => 'Pipeline', 'pump' => 'Pump',
    'valve' => 'Valve', 'meter' => 'Meter', 'vehicle' => 'Vehicle',
    'building' => 'Building', 'equipment' => 'Equipment', 'other' => 'Other'
];

$extraCss = '<style>
#assetMap { height: 400px; border-radius: 0.5rem; }
.asset-type-icon { width: 32px; text-align: center; }
.leaflet-popup-content { margin: 8px 12px; font-size: 13px; }
.leaflet-popup-content strong { display: block; font-size: 14px; margin-bottom: 4px; }
</style>';

$extraJs = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('assetMap').setView([27.7172, 85.3240], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var markers = [
    <?php foreach ($mapAssets as $a): 
        $typeLabel = $typeLabels[$a['asset_type']] ?? ucfirst($a['asset_type']);
        $statusClass = $a['status'];
        $iconColor = $statusClass === 'operational' ? 'green' : ($statusClass === 'maintenance' ? 'orange' : ($statusClass === 'damaged' ? 'red' : 'gray'));
    ?>
        { lat: {$a['latitude']}, lng: {$a['longitude']}, name: '{$a['name']}', code: '{$a['asset_code']}', type: '{$typeLabel}', status: '{$a['status']}', ward: '{$a['ward_no']}', color: '{$iconColor}' },
    <?php endforeach; ?>
    ];

    var bounds = [];
    markers.forEach(function(m) {
        var icon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-' + m.color + '.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [20, 30], iconAnchor: [10, 30], popupAnchor: [0, -30]
        });
        var marker = L.marker([m.lat, m.lng], {icon: icon}).addTo(map);
        marker.bindPopup(
            '<strong>' + escapeHtml(m.name) + '</strong>' +
            '<b>Code:</b> ' + escapeHtml(m.code) + '<br>' +
            '<b>Type:</b> ' + escapeHtml(m.type) + '<br>' +
            '<b>Status:</b> ' + escapeHtml(m.status) + '<br>' +
            '<b>Ward:</b> ' + escapeHtml(m.ward)
        );
        bounds.push([m.lat, m.lng]);
    });

    if (bounds.length > 0) { map.fitBounds(bounds); }
    if (bounds.length === 1) { map.setZoom(15); }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function(m) {
            if (m === '&') return '&amp;'; if (m === '<') return '&lt;';
            if (m === '>') return '&gt;'; if (m === '"') return '&quot;';
            return '&#039;';
        });
    }

    // Chart
    var typeLabels = [
    <?php foreach ($typeSummary as $t): ?>
        '<?= $typeLabels[$t['asset_type']] ?? ucfirst($t['asset_type']) ?>',
    <?php endforeach; ?>
    ];
    var typeData = [
    <?php foreach ($typeSummary as $t): ?>
        { operational: <?= $t['operational'] ?>, maintenance: <?= $t['in_maintenance'] ?>, damaged: <?= $t['damaged'] ?>, decommissioned: <?= $t['decommissioned'] ?> },
    <?php endforeach; ?>
    ];

    var ctx = document.getElementById('assetTypeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: typeLabels,
            datasets: [
                { label: 'Operational', data: typeData.map(function(d) { return d.operational; }), backgroundColor: '#1cc88a' },
                { label: 'Maintenance', data: typeData.map(function(d) { return d.maintenance; }), backgroundColor: '#f6c23e' },
                { label: 'Damaged', data: typeData.map(function(d) { return d.damaged; }), backgroundColor: '#e74a3b' },
                { label: 'Decommissioned', data: typeData.map(function(d) { return d.decommissioned; }), backgroundColor: '#858796' }
            ]
        },
        options: {
            responsive: true, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });
});
</script>
JS;
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="fas fa-building me-2 text-primary"></i>Asset Management Dashboard</h4>
            <p class="text-muted mb-0">Infrastructure asset overview and monitoring</p>
        </div>
        <div class="btn-group">
            <?php if (RBAC::can('assets.create')): ?>
            <a href="<?= ADMIN_URL ?>assets-mgmt/assets.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>New Asset
            </a>
            <?php endif; ?>
            <a href="<?= ADMIN_URL ?>assets-mgmt/maintenance.php" class="btn btn-info btn-sm">
                <i class="fas fa-tools me-1"></i>Maintenance
            </a>
            <a href="<?= ADMIN_URL ?>assets-mgmt/categories.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-tags me-1"></i>Categories
            </a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-stats">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Total Assets</h6>
                        <h3 class="mb-0"><?= number_format($totalAssets) ?></h3>
                    </div>
                    <div class="stat-icon bg-primary-subtle"><i class="fas fa-boxes text-primary"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Operational</h6>
                        <h3 class="mb-0 text-success"><?= number_format($operational) ?></h3>
                    </div>
                    <div class="stat-icon bg-success-subtle"><i class="fas fa-check-circle text-success"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Under Maintenance</h6>
                        <h3 class="mb-0 text-warning"><?= number_format($underMaintenance) ?></h3>
                    </div>
                    <div class="stat-icon bg-warning-subtle"><i class="fas fa-tools text-warning"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats border-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Damaged</h6>
                        <h3 class="mb-0 text-danger"><?= number_format($damaged) ?></h3>
                    </div>
                    <div class="stat-icon bg-danger-subtle"><i class="fas fa-exclamation-circle text-danger"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-map-marked-alt me-2 text-danger"></i>Asset Locations</h6>
                <span class="badge bg-secondary"><?= count($mapAssets) ?> assets mapped</span>
            </div>
            <div class="card-body p-0">
                <div id="assetMap"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Assets by Type & Status</h6>
            </div>
            <div class="card-body">
                <canvas id="assetTypeChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-list me-2 text-success"></i>Status Summary</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Status</th><th class="text-end">Count</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        $statusTotals = ['operational' => 0, 'maintenance' => 0, 'damaged' => 0, 'decommissioned' => 0, 'under_construction' => 0];
                        foreach ($statusSummary as $s) { $statusTotals[$s['status']] = $s['total']; }
                        $statusLabels = ['operational' => 'Operational', 'maintenance' => 'Maintenance', 'damaged' => 'Damaged', 'decommissioned' => 'Decommissioned', 'under_construction' => 'Under Construction'];
                        $statusColors = ['operational' => 'success', 'maintenance' => 'warning', 'damaged' => 'danger', 'decommissioned' => 'secondary', 'under_construction' => 'info'];
                        foreach ($statusTotals as $key => $val): 
                        ?>
                        <tr>
                            <td><span class="badge bg-<?= $statusColors[$key] ?>"><?= $statusLabels[$key] ?></span></td>
                            <td class="text-end fw-bold"><?= number_format($val) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-tools me-2 text-info"></i>Recent Maintenance</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentMaintenance)): ?>
                <div class="text-center text-muted py-3">
                    <i class="fas fa-wrench fa-2x mb-2"></i>
                    <p class="mb-0 small">No maintenance records</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentMaintenance as $m): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong class="small"><?= escape($m['asset_name']) ?></strong>
                            <?= get_status_badge($m['status']) ?>
                        </div>
                        <p class="mb-0 small text-muted"><?= escape(truncate($m['title'], 50)) ?></p>
                        <small class="text-muted"><?= format_date($m['scheduled_date']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
