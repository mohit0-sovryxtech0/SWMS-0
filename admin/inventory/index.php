<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Inventory Dashboard';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Inventory', 'url' => ADMIN_URL . 'inventory/index.php'],
    ['label' => 'Dashboard']
];
RBAC::requirePermission('inventory.view');
require_once __DIR__ . '/../includes/header.php';

// Low stock items
$lowStockItems = db()->fetchAll(
    "SELECT id, item_code, name, category, unit, current_stock, reorder_level
     FROM inventory_items
     WHERE deleted_at IS NULL AND status = 'active' AND current_stock <= reorder_level
     ORDER BY (current_stock / reorder_level) ASC LIMIT 20"
);

// Recent stock movements (last 10)
$recentStockIn = db()->fetchAll(
    "SELECT si.id, si.receipt_no, si.received_date, si.total_amount, 
            s.name AS supplier_name, u.name AS received_by_name
     FROM stock_in si
     LEFT JOIN suppliers s ON si.supplier_id = s.id
     LEFT JOIN users u ON si.received_by = u.id
     ORDER BY si.created_at DESC LIMIT 5"
);

$recentStockOut = db()->fetchAll(
    "SELECT so.id, so.issue_no, so.issue_date, so.issued_to,
            u.name AS issued_by_name
     FROM stock_out so
     LEFT JOIN users u ON so.issued_by = u.id
     ORDER BY so.created_at DESC LIMIT 5"
);

// Category summary
$categorySummary = db()->fetchAll(
    "SELECT category,
            COUNT(*) AS total_items,
            COALESCE(SUM(current_stock), 0) AS total_stock,
            COALESCE(SUM(current_stock * unit_price), 0) AS total_value
     FROM inventory_items
     WHERE deleted_at IS NULL AND status = 'active'
     GROUP BY category ORDER BY category"
);

// Summary stats
$totalItems = db()->fetchColumn("SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL AND status = 'active'");
$totalSuppliers = db()->fetchColumn("SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL AND status = 'active'");
$stockInToday = db()->fetchColumn("SELECT COUNT(*) FROM stock_in WHERE DATE(received_date) = CURDATE()");
$stockOutToday = db()->fetchColumn("SELECT COUNT(*) FROM stock_out WHERE DATE(issue_date) = CURDATE()");
$lowStockCount = count($lowStockItems);

$extraCss = '<style>
.card-low-stock { border-left: 4px solid #dc3545; }
.card-stock-in { border-left: 4px solid #198754; }
.card-stock-out { border-left: 4px solid #ffc107; }
.category-badge { font-size: 0.75rem; padding: 0.25em 0.6em; }
.low-stock-row { background-color: #fff5f5 !important; }
.low-stock-row td:first-child { border-left: 3px solid #dc3545; }
</style>';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="fas fa-boxes me-2 text-primary"></i>Inventory Dashboard</h4>
            <p class="text-muted mb-0">Real-time inventory overview and control</p>
        </div>
        <div class="btn-group">
            <?php if (RBAC::can('stock.in')): ?>
            <a href="<?= ADMIN_URL ?>inventory/stock-in.php" class="btn btn-success btn-sm">
                <i class="fas fa-arrow-down me-1"></i>Stock In
            </a>
            <?php endif; ?>
            <?php if (RBAC::can('stock.out')): ?>
            <a href="<?= ADMIN_URL ?>inventory/stock-out.php" class="btn btn-warning btn-sm">
                <i class="fas fa-arrow-up me-1"></i>Stock Out
            </a>
            <?php endif; ?>
            <?php if (RBAC::can('inventory.items')): ?>
            <a href="<?= ADMIN_URL ?>inventory/items.php" class="btn btn-primary btn-sm">
                <i class="fas fa-list me-1"></i>All Items
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-stats">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Total Items</h6>
                        <h3 class="mb-0"><?= number_format($totalItems) ?></h3>
                    </div>
                    <div class="stat-icon bg-primary-subtle"><i class="fas fa-box text-primary"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Suppliers</h6>
                        <h3 class="mb-0"><?= number_format($totalSuppliers) ?></h3>
                    </div>
                    <div class="stat-icon bg-info-subtle"><i class="fas fa-truck text-info"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Stock In Today</h6>
                        <h3 class="mb-0"><?= number_format($stockInToday) ?></h3>
                    </div>
                    <div class="stat-icon bg-success-subtle"><i class="fas fa-arrow-down text-success"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats <?= $lowStockCount > 0 ? 'border-danger' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Low Stock Alerts</h6>
                        <h3 class="mb-0 <?= $lowStockCount > 0 ? 'text-danger' : '' ?>"><?= number_format($lowStockCount) ?></h3>
                    </div>
                    <div class="stat-icon bg-danger-subtle"><i class="fas fa-exclamation-triangle text-danger"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Low Stock Alerts</h6>
                <?php if ($lowStockCount > 0): ?>
                <a href="<?= ADMIN_URL ?>inventory/items.php?low_stock=1" class="btn btn-sm btn-outline-danger">View All</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockItems)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <p class="mb-0">All items are well stocked</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Min</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockItems as $item): 
                                $ratio = $item['current_stock'] > 0 ? ($item['current_stock'] / $item['reorder_level']) * 100 : 0;
                                $badgeClass = $ratio <= 25 ? 'danger' : ($ratio <= 50 ? 'warning' : 'info');
                            ?>
                            <tr class="low-stock-row">
                                <td class="fw-semibold"><?= escape($item['item_code']) ?></td>
                                <td><?= escape($item['name']) ?></td>
                                <td><span class="badge bg-secondary category-badge"><?= escape(ucfirst(str_replace('_', ' ', $item['category']))) ?></span></td>
                                <td><strong><?= number_format($item['current_stock']) ?></strong> <?= escape($item['unit']) ?></td>
                                <td><?= number_format($item['reorder_level']) ?></td>
                                <td><span class="badge bg-<?= $badgeClass ?>"><?= round($ratio) ?>%</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Categories Summary</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($categorySummary)): ?>
                <div class="text-center text-muted py-3">
                    <p class="mb-0">No items found</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Items</th>
                                <th>Total Stock</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorySummary as $cat): ?>
                            <tr>
                                <td><span class="badge bg-secondary category-badge"><?= escape(ucfirst(str_replace('_', ' ', $cat['category']))) ?></span></td>
                                <td><?= number_format($cat['total_items']) ?></td>
                                <td><?= number_format($cat['total_stock']) ?></td>
                                <td><?= format_currency($cat['total_value']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-2 text-success"></i>Stock Value by Category</h6>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="card card-stock-in">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-arrow-down text-success me-2"></i>Recent Stock In</h6>
                <a href="<?= ADMIN_URL ?>inventory/stock-in.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentStockIn)): ?>
                <div class="text-center text-muted py-3">
                    <p class="mb-0">No stock in records</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Receipt No</th>
                                <th>Supplier</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Received By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStockIn as $si): ?>
                            <tr>
                                <td class="fw-semibold"><?= escape($si['receipt_no']) ?></td>
                                <td><?= escape($si['supplier_name'] ?? 'N/A') ?></td>
                                <td><?= format_date($si['received_date']) ?></td>
                                <td><?= format_currency($si['total_amount']) ?></td>
                                <td><?= escape($si['received_by_name'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-stock-out">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-arrow-up text-warning me-2"></i>Recent Stock Out</h6>
                <a href="<?= ADMIN_URL ?>inventory/stock-out.php" class="btn btn-sm btn-outline-warning">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentStockOut)): ?>
                <div class="text-center text-muted py-3">
                    <p class="mb-0">No stock out records</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Issue No</th>
                                <th>Issued To</th>
                                <th>Date</th>
                                <th>Purpose</th>
                                <th>Issued By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStockOut as $so): ?>
                            <tr>
                                <td class="fw-semibold"><?= escape($so['issue_no']) ?></td>
                                <td><?= escape($so['issued_to'] ?? 'N/A') ?></td>
                                <td><?= format_date($so['issue_date']) ?></td>
                                <td><?= escape(truncate($so['purpose'] ?? 'N/A', 30)) ?></td>
                                <td><?= escape($so['issued_by_name'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
$catLabels = [];
$catValues = [];
foreach ($categorySummary as $cat) {
    $catLabels[] = ucfirst(str_replace('_', ' ', $cat['category']));
    $catValues[] = (float)$cat['total_value'];
}

ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [<?= "'" . implode("','", $catLabels) . "'" ?>],
            datasets: [{
                data: [<?= implode(',', $catValues) ?>],
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                    '#5a5c69', '#858796', '#b7b9cc', '#2e59d9', '#17a673'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } }
            }
        }
    });
});
</script>
<?php
$extraJs = ob_get_clean();
?>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
