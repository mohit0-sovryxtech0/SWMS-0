<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Consumer Management';
$breadcrumbs = [['label' => 'Consumer Management']];
RBAC::requirePermission('consumers.view');

require_once __DIR__ . '/../includes/header.php';

$categories = db()->fetchAll("SELECT id, name FROM consumer_categories WHERE deleted_at IS NULL ORDER BY name");
$connectionTypes = ['household', 'commercial', 'institutional'];
$statuses = ['active', 'inactive', 'suspended', 'pending'];

$page = max(1, (int) get('page', 1));
$perPage = RECORDS_PER_PAGE;
$search = trim(get('search', ''));
$categoryFilter = get('category', '');
$connectionFilter = get('connection_type', '');
$statusFilter = get('status', '');
$wardFilter = get('ward', '');
$offset = ($page - 1) * $perPage;

$where = "WHERE c.deleted_at IS NULL";
$params = [];

if ($search !== '') {
    $where .= " AND (c.full_name LIKE :search OR c.consumer_no LIKE :search2 OR c.mobile LIKE :search3 OR c.ward_no LIKE :search4)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
    $params['search3'] = "%{$search}%";
    $params['search4'] = "%{$search}%";
}
if ($categoryFilter !== '') {
    $where .= " AND c.category_id = :category";
    $params['category'] = (int)$categoryFilter;
}
if ($connectionFilter !== '') {
    $where .= " AND c.connection_type = :connection_type";
    $params['connection_type'] = $connectionFilter;
}
if ($statusFilter !== '') {
    $where .= " AND c.status = :status";
    $params['status'] = $statusFilter;
}
if ($wardFilter !== '') {
    $where .= " AND c.ward_no = :ward";
    $params['ward'] = $wardFilter;
}

$total = db()->fetchColumn(
    "SELECT COUNT(*) FROM consumers c {$where}", $params
);

$consumers = db()->fetchAll(
    "SELECT c.*, cat.name as category_name,
            m.meter_no
     FROM consumers c
     LEFT JOIN consumer_categories cat ON c.category_id = cat.id
     LEFT JOIN meters m ON c.id = m.consumer_id AND m.deleted_at IS NULL
     {$where}
     ORDER BY c.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "consumers/index.php?page={page}" .
    ($search ? "&search=" . urlencode($search) : "") .
    ($categoryFilter ? "&category={$categoryFilter}" : "") .
    ($connectionFilter ? "&connection_type={$connectionFilter}" : "") .
    ($statusFilter ? "&status={$statusFilter}" : "") .
    ($wardFilter ? "&ward={$wardFilter}" : "");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Consumer Management</h4>
            <p>Manage water consumers, connections, and billing information</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (RBAC::can('consumers.create')): ?>
            <a href="<?= ADMIN_URL ?>consumers/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Consumer
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" class="form-control" placeholder="Search by name, consumer no, mobile or ward..." value="<?= escape($search) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($search || $categoryFilter || $connectionFilter || $statusFilter || $wardFilter): ?>
                <a href="<?= ADMIN_URL ?>consumers/index.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            <select name="category" class="form-select" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="connection_type" class="form-select" onchange="this.form.submit()">
                <option value="">All Connections</option>
                <?php foreach ($connectionTypes as $ct): ?>
                <option value="<?= $ct ?>" <?= $connectionFilter === $ct ? 'selected' : '' ?>><?= ucfirst($ct) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="ward" class="form-select" onchange="this.form.submit()">
                <option value="">All Wards</option>
                <?php foreach (range(1, 20) as $w): ?>
                <option value="<?= $w ?>" <?= $wardFilter == $w ? 'selected' : '' ?>>Ward <?= $w ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach ($statuses as $st): ?>
                <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="table-container">
            <table class="table" id="consumersTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Consumer No</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Ward</th>
                        <th>Connection Type</th>
                        <th>Category</th>
                        <th>Meter No</th>
                        <th>Status</th>
                        <th style="width:130px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($consumers)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">No consumers found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($consumers as $i => $c): ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td><strong><?= escape($c['consumer_no']) ?></strong></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;flex-shrink:0;">
                                    <?= strtoupper(substr($c['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:500;font-size:13px;"><?= escape($c['full_name']) ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);"><?= escape($c['father_name'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= escape($c['mobile'] ?: '-') ?></td>
                        <td><?= $c['ward_no'] ? 'Ward ' . (int)$c['ward_no'] : '-' ?></td>
                        <td><?= get_connection_type_badge($c['connection_type']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= escape($c['category_name'] ?? '-') ?></span></td>
                        <td><?= escape($c['meter_no'] ?: '-') ?></td>
                        <td><?= get_status_badge($c['status']) ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= ADMIN_URL ?>consumers/view.php?id=<?= $c['id'] ?>" class="btn-action view" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (RBAC::can('consumers.edit')): ?>
                                <a href="<?= ADMIN_URL ?>consumers/edit.php?id=<?= $c['id'] ?>" class="btn-action edit" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <?php if (RBAC::can('consumers.delete')): ?>
                                <button type="button" class="btn-action delete" title="Delete" onclick="confirmDelete(<?= $c['id'] ?>, '<?= escape(addslashes($c['full_name'])) ?>')"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">Showing <?= min($total, $offset + 1) ?> to <?= min($total, $offset + $perPage) ?> of <?= $total ?> entries</small>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $page - 1, $paginationUrl) ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $i, $paginationUrl) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $page + 1, $paginationUrl) ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= ADMIN_URL ?>consumers/delete.php">
                <?= csrf_field() ?>
                <input type="hidden" name="consumer_id" id="deleteConsumerId">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete consumer <strong id="deleteConsumerName"></strong>?</p>
                    <p class="text-muted small mb-0">This action will soft-delete the consumer record.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteConsumerId').value = id;
    document.getElementById('deleteConsumerName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
