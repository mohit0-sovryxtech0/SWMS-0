<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Work Orders';
$breadcrumbs = [
    ['label' => 'Complaint Management', 'url' => ADMIN_URL . 'complaints/index.php'],
    ['label' => 'Work Orders']
];
RBAC::requirePermission('workorders.view');

require_once __DIR__ . '/../includes/header.php';

$statusFilter = get('status', '');
$priorityFilter = get('priority', '');
$search = trim(get('search', ''));
$statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
$priorities = ['low', 'medium', 'high', 'urgent'];

$where = "WHERE wo.deleted_at IS NULL";
$params = [];

if ($statusFilter !== '') {
    $where .= " AND wo.status = :status";
    $params['status'] = $statusFilter;
}
if ($priorityFilter !== '') {
    $where .= " AND wo.priority = :priority";
    $params['priority'] = $priorityFilter;
}
if ($search !== '') {
    $where .= " AND (wo.work_order_no LIKE :search OR wo.title LIKE :search2)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
}

$page = max(1, (int) get('page', 1));
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$total = db()->fetchColumn("SELECT COUNT(*) FROM work_orders wo {$where}", $params);

$orders = db()->fetchAll(
    "SELECT wo.*, u.name as assigned_name, c.ticket_no, c.subject as complaint_subject
     FROM work_orders wo
     LEFT JOIN users u ON wo.assigned_to = u.id
     LEFT JOIN complaints c ON wo.complaint_id = c.id
     {$where}
     ORDER BY wo.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "complaints/work-orders.php?page={page}" .
    ($statusFilter ? "&status={$statusFilter}" : "") .
    ($priorityFilter ? "&priority={$priorityFilter}" : "") .
    ($search ? "&search=" . urlencode($search) : "");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Work Orders</h4>
            <p>Manage work orders created from complaints</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>complaints/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Complaints
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" class="form-control" placeholder="Search work orders..." value="<?= escape($search) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($search || $statusFilter || $priorityFilter): ?>
                <a href="<?= ADMIN_URL ?>complaints/work-orders.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach ($statuses as $st): ?>
                <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= get_status_text($st) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="priority" class="form-select" onchange="this.form.submit()">
                <option value="">All Priority</option>
                <?php foreach ($priorities as $p): ?>
                <option value="<?= $p ?>" <?= $priorityFilter === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>WO No</th>
                        <th>Title</th>
                        <th>Complaint</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No work orders found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $wo): ?>
                    <tr>
                        <td><strong><?= escape($wo['work_order_no']) ?></strong></td>
                        <td><?= escape($wo['title']) ?></td>
                        <td>
                            <?php if ($wo['ticket_no']): ?>
                            <a href="<?= ADMIN_URL ?>complaints/view.php?id=<?= $wo['complaint_id'] ?>"><?= escape($wo['ticket_no']) ?></a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td><?= escape($wo['assigned_name'] ?: '-') ?></td>
                        <td><?= get_status_badge($wo['priority']) ?></td>
                        <td><?= get_status_badge($wo['status']) ?></td>
                        <td><small><?= format_date($wo['created_at']) ?></small></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
