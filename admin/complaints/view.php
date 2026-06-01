<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Complaint Details';
$breadcrumbs = [
    ['label' => 'Complaint Management', 'url' => ADMIN_URL . 'complaints/index.php'],
    ['label' => 'Complaint Details']
];
RBAC::requirePermission('complaints.view');

require_once __DIR__ . '/../includes/header.php';

$id = (int) get('id', 0);
if ($id <= 0) {
    alert_error('Invalid complaint ID.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$complaint = db()->fetchOne(
    "SELECT c.*, cat.name as category_name, cat.sla_hours,
            u.name as assigned_name,
            cons.consumer_no, cons.full_name as consumer_name, cons.mobile as consumer_mobile,
            closer.name as closed_by_name
     FROM complaints c
     LEFT JOIN complaint_categories cat ON c.category_id = cat.id
     LEFT JOIN users u ON c.assigned_to = u.id
     LEFT JOIN consumers cons ON c.consumer_id = cons.id
     LEFT JOIN users closer ON c.closed_by = closer.id
     WHERE c.id = ? AND c.deleted_at IS NULL",
    [$id]
);

if (!$complaint) {
    alert_error('Complaint not found.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$updates = db()->fetchAll(
    "SELECT cu.*, u.name as user_name
     FROM complaint_updates cu
     LEFT JOIN users u ON cu.user_id = u.id
     WHERE cu.complaint_id = ?
     ORDER BY cu.created_at ASC",
    [$id]
);

$feedback = db()->fetchOne(
    "SELECT * FROM complaint_feedback WHERE complaint_id = ?",
    [$id]
);

$workOrders = db()->fetchAll(
    "SELECT wo.*, u.name as assigned_name
     FROM work_orders wo
     LEFT JOIN users u ON wo.assigned_to = u.id
     WHERE wo.complaint_id = ?
     ORDER BY wo.created_at DESC",
    [$id]
);

$validTransitions = [];
$cur = $complaint['status'];
if ($cur === 'open' || $cur === 'reopened') {
    $validTransitions = ['in_progress'];
} elseif ($cur === 'in_progress') {
    $validTransitions = ['resolved'];
} elseif ($cur === 'resolved') {
    $validTransitions = ['closed'];
} elseif ($cur === 'closed') {
    $validTransitions = ['reopened'];
}

$usersList = db()->fetchAll("SELECT id, name FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY name");
$categories = db()->fetchAll("SELECT id, name FROM complaint_categories WHERE deleted_at IS NULL ORDER BY name");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= ADMIN_URL ?>complaints/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i></a>
            <h4 class="mb-0"><?= escape($complaint['ticket_no']) ?></h4>
            <?= get_status_badge($complaint['status']) ?>
            <?php
                $pBadge = match($complaint['priority']) {
                    'urgent' => 'bg-danger',
                    'high' => 'bg-orange',
                    'medium' => 'bg-warning text-dark',
                    'low' => 'bg-success',
                    default => 'bg-secondary'
                };
            ?>
            <span class="badge <?= $pBadge ?>"><?= ucfirst($complaint['priority']) ?></span>
        </div>
        <div class="d-flex gap-2">
            <?php if (RBAC::can('complaints.resolve') && in_array($complaint['status'], ['open', 'reopened'])): ?>
            <button type="button" class="btn btn-info btn-sm" onclick="openAssign()"><i class="fas fa-user-tag"></i> Assign</button>
            <?php endif; ?>
            <?php if (RBAC::can('complaints.resolve') && !empty($validTransitions)): ?>
                <?php if ($complaint['status'] === 'in_progress'): ?>
                <button type="button" class="btn btn-success btn-sm" onclick="openStatusModal('resolved')"><i class="fas fa-check-circle"></i> Resolve</button>
                <?php elseif ($complaint['status'] === 'resolved'): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openStatusModal('closed')"><i class="fas fa-door-closed"></i> Close</button>
                <?php elseif ($complaint['status'] === 'closed'): ?>
                <button type="button" class="btn btn-warning btn-sm" onclick="openStatusModal('reopened')"><i class="fas fa-undo"></i> Reopen</button>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (RBAC::can('complaints.create')): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="openWorkOrder()"><i class="fas fa-tools"></i> Work Order</button>
            <?php endif; ?>
            <?php if (RBAC::can('complaints.create')): ?>
            <a href="<?= ADMIN_URL ?>complaints/edit.php?id=<?= $complaint['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Citizen / Consumer Info -->
        <div class="card">
            <div class="card-header"><h5>Complainant Information</h5></div>
            <div class="card-body">
                <div class="row">
                    <?php if ($complaint['consumer_id']): ?>
                    <div class="col-md-6">
                        <div class="info-row"><label>Consumer No</label><span><?= escape($complaint['consumer_no']) ?></span></div>
                        <div class="info-row"><label>Name</label><span><?= escape($complaint['consumer_name']) ?></span></div>
                        <div class="info-row"><label>Mobile</label><span><?= escape($complaint['consumer_mobile'] ?: '-') ?></span></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($complaint['citizen_name']): ?>
                    <div class="col-md-6">
                        <div class="info-row"><label>Citizen Name</label><span><?= escape($complaint['citizen_name']) ?></span></div>
                        <div class="info-row"><label>Phone</label><span><?= escape($complaint['citizen_phone'] ?: '-') ?></span></div>
                        <div class="info-row"><label>Email</label><span><?= escape($complaint['citizen_email'] ?: '-') ?></span></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Complaint Details -->
        <div class="card">
            <div class="card-header"><h5>Complaint Details</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row"><label>Category</label><span><?= escape($complaint['category_name'] ?? '-') ?></span></div>
                        <div class="info-row"><label>Subject</label><span><?= escape($complaint['subject']) ?></span></div>
                        <div class="info-row"><label>Ward No</label><span><?= $complaint['ward_no'] ? 'Ward ' . (int)$complaint['ward_no'] : '-' ?></span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row"><label>Assigned To</label><span><?= escape($complaint['assigned_name'] ?: 'Unassigned') ?></span></div>
                        <div class="info-row"><label>Assigned At</label><span><?= $complaint['assigned_at'] ? format_datetime($complaint['assigned_at']) : '-' ?></span></div>
                        <div class="info-row"><label>Resolved At</label><span><?= $complaint['resolved_at'] ? format_datetime($complaint['resolved_at']) : '-' ?></span></div>
                    </div>
                </div>
                <div class="info-row"><label>Location</label><span><?= escape($complaint['location'] ?: '-') ?></span></div>
                <div class="info-row"><label>Description</label></div>
                <div class="p-3 bg-light rounded mt-1"><?= nl2br(escape($complaint['description'])) ?></div>
                <?php if ($complaint['resolution_notes']): ?>
                <div class="info-row mt-3"><label>Resolution Notes</label></div>
                <div class="p-3 bg-success bg-opacity-10 rounded mt-1"><?= nl2br(escape($complaint['resolution_notes'])) ?></div>
                <?php endif; ?>
                <?php if ($complaint['attachment']): ?>
                <div class="mt-3">
                    <a href="<?= UPLOAD_URL ?>complaints/<?= $complaint['attachment'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-download"></i> Download Attachment</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Location Map -->
        <?php if ($complaint['latitude'] && $complaint['longitude']): ?>
        <div class="card">
            <div class="card-header"><h5>Location Map</h5></div>
            <div class="card-body p-0">
                <div id="complaintDetailMap" style="height:300px;border-radius:0 0 8px 8px;"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timeline -->
        <div class="card">
            <div class="card-header"><h5>Activity Timeline</h5></div>
            <div class="card-body">
                <?php if (empty($updates)): ?>
                <p class="text-muted text-center py-3">No activity recorded yet.</p>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($updates as $up): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon bg-<?= match($up['status']) {
                            'open' => 'warning', 'in_progress' => 'info', 'resolved' => 'success', 'closed' => 'secondary', 'reopened' => 'warning', default => 'primary'
                        } ?>">
                            <i class="fas fa-<?= match($up['status']) {
                                'open' => 'exclamation', 'in_progress' => 'spinner', 'resolved' => 'check', 'closed' => 'door-closed', 'reopened' => 'undo', default => 'circle'
                            } ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong><?= get_status_text($up['status']) ?></strong>
                                <small class="text-muted"><?= format_datetime($up['created_at']) ?></small>
                            </div>
                            <p class="mb-1"><?= nl2br(escape($up['message'])) ?></p>
                            <small class="text-muted">by <?= escape($up['user_name'] ?? 'System') ?></small>
                            <?php if ($up['attachment']): ?>
                            <br><a href="<?= UPLOAD_URL ?>complaints/<?= $up['attachment'] ?>" target="_blank" class="small"><i class="fas fa-paperclip"></i> Attachment</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Work Orders -->
        <?php if (!empty($workOrders)): ?>
        <div class="card">
            <div class="card-header"><h5>Work Orders</h5></div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>WO No</th>
                                <th>Title</th>
                                <th>Assigned To</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workOrders as $wo): ?>
                            <tr>
                                <td><strong><?= escape($wo['work_order_no']) ?></strong></td>
                                <td><?= escape($wo['title']) ?></td>
                                <td><?= escape($wo['assigned_name'] ?: '-') ?></td>
                                <td><?= get_status_badge($wo['priority']) ?></td>
                                <td><?= get_status_badge($wo['status']) ?></td>
                                <td><?= format_date($wo['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Feedback -->
        <?php if ($feedback): ?>
        <div class="card">
            <div class="card-header"><h5>Feedback</h5></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <strong>Rating:</strong>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star <?= $i <= $feedback['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                    <?php endfor; ?>
                </div>
                <?php if ($feedback['feedback']): ?>
                <p class="mb-0"><?= nl2br(escape($feedback['feedback'])) ?></p>
                <?php endif; ?>
                <small class="text-muted">Submitted on <?= format_datetime($feedback['created_at']) ?></small>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- Action Panel -->
        <div class="card">
            <div class="card-header"><h5>Actions</h5></div>
            <div class="card-body">
                <!-- Add Update -->
                <form method="POST" action="<?= ADMIN_URL ?>complaints/update-status.php" enctype="multipart/form-data" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                    <input type="hidden" name="status" value="<?= $complaint['status'] ?>">
                    <div class="form-group mb-2">
                        <label class="form-label">Add Update</label>
                        <textarea name="message" class="form-control" rows="3" required placeholder="Write an update..."></textarea>
                    </div>
                    <div class="form-group mb-2">
                        <input type="file" name="attachment" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.gif,.pdf">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-sm"><i class="fas fa-comment"></i> Add Update</button>
                </form>

                <hr>

                <!-- Change Status -->
                <?php if (!empty($validTransitions) && RBAC::can('complaints.resolve')): ?>
                <div class="mb-3">
                    <label class="form-label">Change Status</label>
                    <div class="d-flex gap-2">
                        <?php foreach ($validTransitions as $vt): ?>
                        <button type="button" class="btn btn-sm btn-<?= match($vt) {
                            'in_progress' => 'info', 'resolved' => 'success', 'closed' => 'secondary', 'reopened' => 'warning', default => 'primary'
                        } ?> w-100" onclick="openStatusModal('<?= $vt ?>')">
                            <?= get_status_text($vt) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <hr>
                <?php endif; ?>

                <!-- Assign -->
                <?php if (RBAC::can('complaints.assign') && in_array($complaint['status'], ['open', 'reopened'])): ?>
                <div class="mb-3">
                    <label class="form-label">Assign To</label>
                    <form method="POST" action="<?= ADMIN_URL ?>complaints/assign.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                        <div class="d-flex gap-2">
                            <select name="assigned_to" class="form-select form-select-sm" required>
                                <option value="">Select...</option>
                                <?php foreach ($usersList as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= escape($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-user-tag"></i></button>
                        </div>
                    </form>
                </div>
                <hr>
                <?php endif; ?>

                <!-- Create Work Order -->
                <?php if (RBAC::can('workorders.create')): ?>
                <div>
                    <label class="form-label">Create Work Order</label>
                    <form method="POST" action="<?= ADMIN_URL ?>complaints/work-order-create.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                        <div class="form-group mb-2">
                            <input type="text" name="title" class="form-control form-control-sm" required placeholder="Work order title" value="WO: <?= escape($complaint['subject']) ?>">
                        </div>
                        <div class="form-group mb-2">
                            <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Description"><?= escape($complaint['description']) ?></textarea>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <select name="assigned_to" class="form-select form-select-sm" required>
                                    <option value="">Assign to...</option>
                                    <?php foreach ($usersList as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $complaint['assigned_to'] == $u['id'] ? 'selected' : '' ?>><?= escape($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <select name="priority" class="form-select form-select-sm">
                                    <option value="medium">Priority</option>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-tools"></i> Create Work Order</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Meta -->
        <div class="card">
            <div class="card-header"><h5>Metadata</h5></div>
            <div class="card-body">
                <div class="info-row"><label>Created By</label><span><?= $complaint['created_by'] ? 'User #' . $complaint['created_by'] : 'System' ?></span></div>
                <div class="info-row"><label>Created At</label><span><?= format_datetime($complaint['created_at']) ?></span></div>
                <div class="info-row"><label>Last Updated</label><span><?= format_datetime($complaint['updated_at']) ?></span></div>
                <?php if ($complaint['closed_by_name']): ?>
                <div class="info-row"><label>Closed By</label><span><?= escape($complaint['closed_by_name']) ?></span></div>
                <?php endif; ?>
                <?php if ($complaint['sla_hours']): ?>
                <div class="info-row"><label>SLA (hours)</label><span><?= (int)$complaint['sla_hours'] ?> hrs</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= ADMIN_URL ?>complaints/update-status.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                <input type="hidden" name="status" id="statusModalStatus">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalTitle">Change Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" id="statusModalLabel">Notes</label>
                        <textarea name="message" class="form-control" rows="4" required placeholder="Enter notes..."></textarea>
                    </div>
                    <div class="form-group mt-2">
                        <label class="form-label">Attachment (optional)</label>
                        <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="statusModalSubmit"><i class="fas fa-check"></i> Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= ADMIN_URL ?>complaints/assign.php">
                <?= csrf_field() ?>
                <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Complaint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Assign To <span class="required">*</span></label>
                        <select name="assigned_to" class="form-select" required>
                            <option value="">Select User</option>
                            <?php foreach ($usersList as $u): ?>
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

<style>
.bg-orange { background-color: #f97316 !important; color: #fff !important; }
.info-row { margin-bottom: 8px; font-size: 13px; }
.info-row label { font-weight: 600; display: block; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
.info-row span { color: var(--text-color); }
.timeline { position: relative; padding-left: 40px; }
.timeline::before { content: ''; position: absolute; left: 18px; top: 0; bottom: 0; width: 2px; background: #e5e7eb; }
.timeline-item { position: relative; margin-bottom: 24px; }
.timeline-icon { position: absolute; left: -40px; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; z-index: 1; }
.timeline-content { background: #f9fafb; padding: 12px 16px; border-radius: 8px; border: 1px solid #e5e7eb; }
</style>

<script>
function openStatusModal(status) {
    document.getElementById('statusModalStatus').value = status;
    var labels = { in_progress: 'In Progress', resolved: 'Resolve', closed: 'Close', reopened: 'Reopen' };
    document.getElementById('statusModalTitle').textContent = labels[status] || 'Change Status';
    document.getElementById('statusModalLabel').textContent = (status === 'resolved' ? 'Resolution Notes' : 'Notes') + ' *';
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

function openAssign() {
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}

function openWorkOrder() {
    document.querySelector('form[action*="work-order-create.php"] button[type="submit"]').click();
}

<?php if ($complaint['latitude'] && $complaint['longitude']): ?>
document.addEventListener('DOMContentLoaded', function() {
    var lat = <?= $complaint['latitude'] ?>;
    var lng = <?= $complaint['longitude'] ?>;
    var map = L.map('complaintDetailMap').setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
    }).addTo(map);
    L.marker([lat, lng]).addTo(map)
        .bindPopup('<b><?= escape($complaint['ticket_no']) ?></b><br><?= escape($complaint['subject']) ?>')
        .openPopup();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
