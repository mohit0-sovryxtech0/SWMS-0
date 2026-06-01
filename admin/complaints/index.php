<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Complaint Management';
$breadcrumbs = [['label' => 'Complaint Management']];
RBAC::requirePermission('complaints.view');

require_once __DIR__ . '/../includes/header.php';

$categories = db()->fetchAll("SELECT id, name FROM complaint_categories WHERE deleted_at IS NULL ORDER BY name");
$priorities = ['low', 'medium', 'high', 'urgent'];
$statuses = ['open', 'in_progress', 'resolved', 'closed', 'reopened'];

$statusFilter = get('status', '');
$priorityFilter = get('priority', '');
$categoryFilter = get('category', '');
$dateFrom = get('date_from', '');
$dateTo = get('date_to', '');
$search = trim(get('search', ''));

$where = "WHERE c.deleted_at IS NULL";
$params = [];

if ($statusFilter !== '') {
    $where .= " AND c.status = :status";
    $params['status'] = $statusFilter;
}
if ($priorityFilter !== '') {
    $where .= " AND c.priority = :priority";
    $params['priority'] = $priorityFilter;
}
if ($categoryFilter !== '') {
    $where .= " AND c.category_id = :category";
    $params['category'] = (int)$categoryFilter;
}
if ($dateFrom !== '') {
    $where .= " AND DATE(c.created_at) >= :date_from";
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= " AND DATE(c.created_at) <= :date_to";
    $params['date_to'] = $dateTo;
}
if ($search !== '') {
    $where .= " AND (c.ticket_no LIKE :search OR c.subject LIKE :search2 OR c.citizen_name LIKE :search3 OR c.citizen_phone LIKE :search4)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
    $params['search3'] = "%{$search}%";
    $params['search4'] = "%{$search}%";
}

$total = db()->fetchColumn("SELECT COUNT(*) FROM complaints c {$where}", $params);

$page = max(1, (int) get('page', 1));
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$complaints = db()->fetchAll(
    "SELECT c.*, cat.name as category_name,
            u.name as assigned_name,
            cons.consumer_no, cons.full_name as consumer_name
     FROM complaints c
     LEFT JOIN complaint_categories cat ON c.category_id = cat.id
     LEFT JOIN users u ON c.assigned_to = u.id
     LEFT JOIN consumers cons ON c.consumer_id = cons.id
     {$where}
     ORDER BY c.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "complaints/index.php?page={page}" .
    ($statusFilter ? "&status={$statusFilter}" : "") .
    ($priorityFilter ? "&priority={$priorityFilter}" : "") .
    ($categoryFilter ? "&category={$categoryFilter}" : "") .
    ($dateFrom ? "&date_from={$dateFrom}" : "") .
    ($dateTo ? "&date_to={$dateTo}" : "") .
    ($search ? "&search=" . urlencode($search) : "");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Complaint Management</h4>
            <p>Manage and track water supply complaints</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (RBAC::can('complaints.create')): ?>
            <a href="<?= ADMIN_URL ?>complaints/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Register Complaint
            </a>
            <?php endif; ?>
            <a href="<?= ADMIN_URL ?>complaints/categories.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-tags"></i> Categories
            </a>
            <a href="<?= ADMIN_URL ?>complaints/work-orders.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-tools"></i> Work Orders
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" class="form-control" placeholder="Search ticket, subject, citizen..." value="<?= escape($search) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($search || $statusFilter || $priorityFilter || $categoryFilter || $dateFrom || $dateTo): ?>
                <a href="<?= ADMIN_URL ?>complaints/index.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
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
            <select name="category" class="form-select" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" class="form-select" value="<?= escape($dateFrom) ?>" onchange="this.form.submit()" placeholder="From">
            <input type="date" name="date_to" class="form-select" value="<?= escape($dateTo) ?>" onchange="this.form.submit()" placeholder="To">
        </form>

        <div class="table-container">
            <table class="table" id="complaintsTable">
                <thead>
                    <tr>
                        <th>Ticket No</th>
                        <th>Citizen / Consumer</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="width:140px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($complaints)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No complaints found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($complaints as $c): ?>
                    <?php
                        $priorityBadge = match($c['priority']) {
                            'urgent' => 'bg-danger',
                            'high' => 'bg-orange',
                            'medium' => 'bg-warning text-dark',
                            'low' => 'bg-success',
                            default => 'bg-secondary'
                        };
                    ?>
                    <tr>
                        <td><strong><?= escape($c['ticket_no']) ?></strong></td>
                        <td>
                            <?php if ($c['consumer_id']): ?>
                            <div class="d-flex align-items-center gap-1">
                                <i class="fas fa-user-check text-primary" style="font-size:12px"></i>
                                <span><?= escape($c['consumer_name'] ?? '') ?></span>
                                <small class="text-muted">(<?= escape($c['consumer_no'] ?? '') ?>)</small>
                            </div>
                            <?php else: ?>
                            <div>
                                <div><?= escape($c['citizen_name'] ?: '-') ?></div>
                                <small class="text-muted"><?= escape($c['citizen_phone'] ?: '') ?></small>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= escape($c['category_name'] ?? '-') ?></span></td>
                        <td><span class="badge <?= $priorityBadge ?>"><?= ucfirst($c['priority']) ?></span></td>
                        <td><?= escape($c['assigned_name'] ?: '-') ?></td>
                        <td><?= get_status_badge($c['status']) ?></td>
                        <td><small><?= format_datetime($c['created_at'], 'Y-m-d') ?></small></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= ADMIN_URL ?>complaints/view.php?id=<?= $c['id'] ?>" class="btn-action view" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (RBAC::can('complaints.assign') && in_array($c['status'], ['open', 'reopened'])): ?>
                                <button type="button" class="btn-action assign" title="Assign" onclick="openAssign(<?= $c['id'] ?>, '<?= escape($c['ticket_no']) ?>')"><i class="fas fa-user-tag"></i></button>
                                <?php endif; ?>
                                <?php if (RBAC::can('complaints.resolve') && $c['status'] === 'in_progress'): ?>
                                <button type="button" class="btn-action resolve" title="Resolve" onclick="openResolve(<?= $c['id'] ?>, '<?= escape($c['ticket_no']) ?>')"><i class="fas fa-check"></i></button>
                                <?php endif; ?>
                                <?php if (RBAC::can('complaints.view') && $c['status'] === 'resolved'): ?>
                                <button type="button" class="btn-action close" title="Close" onclick="openClose(<?= $c['id'] ?>, '<?= escape($c['ticket_no']) ?>')"><i class="fas fa-door-closed"></i></button>
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

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= ADMIN_URL ?>complaints/assign.php">
                <?= csrf_field() ?>
                <input type="hidden" name="complaint_id" id="assignComplaintId">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Complaint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Assign complaint <strong id="assignTicketNo"></strong> to:</p>
                    <div class="form-group">
                        <label class="form-label">Assign To <span class="required">*</span></label>
                        <select name="assigned_to" class="form-select" required>
                            <option value="">Select User</option>
                            <?php
                            $users = db()->fetchAll("SELECT id, name FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY name");
                            foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= escape($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= ADMIN_URL ?>complaints/update-status.php">
                <?= csrf_field() ?>
                <input type="hidden" name="complaint_id" id="resolveComplaintId">
                <input type="hidden" name="status" value="resolved">
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Complaint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Resolve complaint <strong id="resolveTicketNo"></strong></p>
                    <div class="form-group">
                        <label class="form-label">Resolution Notes <span class="required">*</span></label>
                        <textarea name="message" class="form-control" rows="4" required placeholder="Describe the resolution..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-circle"></i> Resolve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Close Modal -->
<div class="modal fade" id="closeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= ADMIN_URL ?>complaints/update-status.php">
                <?= csrf_field() ?>
                <input type="hidden" name="complaint_id" id="closeComplaintId">
                <input type="hidden" name="status" value="closed">
                <div class="modal-header">
                    <h5 class="modal-title">Close Complaint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Close complaint <strong id="closeTicketNo"></strong></p>
                    <div class="form-group">
                        <label class="form-label">Closing Notes</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Optional closing notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary"><i class="fas fa-door-closed"></i> Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-orange { background-color: #f97316 !important; color: #fff !important; }
</style>

<script>
function openAssign(id, ticket) {
    document.getElementById('assignComplaintId').value = id;
    document.getElementById('assignTicketNo').textContent = ticket;
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}
function openResolve(id, ticket) {
    document.getElementById('resolveComplaintId').value = id;
    document.getElementById('resolveTicketNo').textContent = ticket;
    new bootstrap.Modal(document.getElementById('resolveModal')).show();
}
function openClose(id, ticket) {
    document.getElementById('closeComplaintId').value = id;
    document.getElementById('closeTicketNo').textContent = ticket;
    new bootstrap.Modal(document.getElementById('closeModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
