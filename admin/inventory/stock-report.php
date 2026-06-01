<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Stock Report';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Inventory', 'url' => ADMIN_URL . 'inventory/index.php'],
    ['label' => 'Stock Report']
];
RBAC::requirePermission('inventory.view');

require_once __DIR__ . '/../includes/header.php';

$categories = ['', 'pipe', 'valve', 'fitting', 'meter', 'pump', 'chemical', 'tool', 'safety_equipment', 'office_supply', 'other'];
$filterCategory = get('category', '');
$filterStatus = get('status', '');
$search = trim(get('search', ''));

$where = "WHERE i.deleted_at IS NULL";
$params = [];

if (!empty($filterCategory)) {
    $where .= " AND i.category = :category";
    $params['category'] = $filterCategory;
}
if (!empty($filterStatus)) {
    $where .= " AND i.status = :status";
    $params['status'] = $filterStatus;
}
if (!empty($search)) {
    $where .= " AND (i.item_code LIKE :search OR i.name LIKE :search2)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
}

$items = db()->fetchAll(
    "SELECT i.*,
            COALESCE((SELECT SUM(sii.quantity) FROM stock_in_items sii WHERE sii.item_id = i.id), 0) AS total_received,
            COALESCE((SELECT SUM(soi.quantity) FROM stock_out_items soi WHERE soi.item_id = i.id), 0) AS total_issued
     FROM inventory_items i
     {$where}
     ORDER BY i.name ASC"
);

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stock_report_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Item Code', 'Name', 'Category', 'Unit', 'Current Stock', 'Unit Price', 'Stock Value', 'Min Stock', 'Max Stock', 'Reorder Level', 'Total Received', 'Total Issued', 'Status', 'Location']);
    foreach ($items as $item) {
        fputcsv($output, [
            $item['item_code'],
            $item['name'],
            $item['category'],
            $item['unit'],
            $item['current_stock'],
            $item['unit_price'],
            $item['current_stock'] * $item['unit_price'],
            $item['min_stock'],
            $item['max_stock'],
            $item['reorder_level'],
            $item['total_received'],
            $item['total_issued'],
            $item['status'],
            $item['location'],
        ]);
    }
    fclose($output);
    exit;
}

$totalStockValue = 0;
$lowStockCount = 0;
$outOfStockCount = 0;
foreach ($items as $item) {
    $totalStockValue += $item['current_stock'] * $item['unit_price'];
    if ($item['current_stock'] <= 0) $outOfStockCount++;
    elseif ($item['current_stock'] <= $item['reorder_level']) $lowStockCount++;
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-file-alt me-2 text-info"></i>Stock Report</h4>
        <div class="btn-group">
            <a href="?export=csv<?= !empty($filterCategory) ? '&category=' . urlencode($filterCategory) : '' ?><?= !empty($filterStatus) ? '&status=' . urlencode($filterStatus) : '' ?>" class="btn btn-success btn-sm">
                <i class="fas fa-file-csv me-1"></i>Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-info btn-sm">
                <i class="fas fa-print me-1"></i>Print
            </button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-stats">
            <div class="card-body">
                <h6 class="text-muted mb-1">Total Items</h6>
                <h3 class="mb-0"><?= number_format(count($items)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats">
            <div class="card-body">
                <h6 class="text-muted mb-1">Stock Value</h6>
                <h3 class="mb-0"><?= format_currency($totalStockValue) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats border-warning">
            <div class="card-body">
                <h6 class="text-muted mb-1">Low Stock Items</h6>
                <h3 class="mb-0 text-warning"><?= $lowStockCount ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats border-danger">
            <div class="card-body">
                <h6 class="text-muted mb-1">Out of Stock</h6>
                <h3 class="mb-0 text-danger"><?= $outOfStockCount ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-3">
                <label class="form-label small">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach (array_slice($categories, 1) as $cat): ?>
                    <option value="<?= $cat ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $cat)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by code or name..." value="<?= escape($search) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary me-2"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="?" class="btn btn-sm btn-secondary"><i class="fas fa-undo me-1"></i>Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered" id="reportTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Item Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Current Stock</th>
                        <th>Unit Price</th>
                        <th>Stock Value</th>
                        <th>Reorder Level</th>
                        <th>Total Received</th>
                        <th>Total Issued</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($items as $item): 
                        $stockVal = $item['current_stock'] * $item['unit_price'];
                        $rowClass = '';
                        if ($item['current_stock'] <= 0) $rowClass = 'table-danger';
                        elseif ($item['current_stock'] <= $item['reorder_level']) $rowClass = 'table-warning';
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><?= $i++ ?></td>
                        <td class="fw-semibold"><?= escape($item['item_code']) ?></td>
                        <td><?= escape($item['name']) ?></td>
                        <td><span class="badge bg-secondary"><?= escape(ucfirst(str_replace('_', ' ', $item['category']))) ?></span></td>
                        <td><?= escape($item['unit']) ?></td>
                        <td><strong><?= number_format($item['current_stock']) ?></strong></td>
                        <td><?= format_currency($item['unit_price']) ?></td>
                        <td><?= format_currency($stockVal) ?></td>
                        <td><?= number_format($item['reorder_level']) ?></td>
                        <td><?= number_format($item['total_received']) ?></td>
                        <td><?= number_format($item['total_issued']) ?></td>
                        <td><?= get_status_badge($item['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-4">No items found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $extraJs = <<<'JS'
<script>$(document).ready(function() { $('#reportTable').DataTable({ paging: true, pageLength: 50, order: [], language: { searchPlaceholder: 'Search table...' } }); });</script>
JS; ?>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
