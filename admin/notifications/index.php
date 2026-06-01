<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Notification Management';
$breadcrumbs = [['label' => 'Notification Management']];
RBAC::requirePermission('settings.view');

require_once __DIR__ . '/../includes/header.php';

$types = ['sms', 'email', 'system', 'bill_reminder', 'payment', 'complaint', 'service', 'alert'];
$channels = ['sms', 'email', 'both', 'system'];
$statuses = ['pending', 'sent', 'failed', 'delivered'];

$typeFilter = get('type', '');
$statusFilter = get('status', '');
$dateFrom = get('date_from', '');
$dateTo = get('date_to', '');

$where = "WHERE 1=1";
$params = [];

if ($typeFilter !== '') {
    $where .= " AND n.type = :type";
    $params['type'] = $typeFilter;
}
if ($statusFilter !== '') {
    $where .= " AND n.status = :status";
    $params['status'] = $statusFilter;
}
if ($dateFrom !== '') {
    $where .= " AND DATE(n.created_at) >= :date_from";
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= " AND DATE(n.created_at) <= :date_to";
    $params['date_to'] = $dateTo;
}

$page = max(1, (int) get('page', 1));
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$total = db()->fetchColumn("SELECT COUNT(*) FROM notifications n {$where}", $params);

$notifications = db()->fetchAll(
    "SELECT n.*, u.name as user_name, c.full_name as consumer_name
     FROM notifications n
     LEFT JOIN users u ON n.user_id = u.id
     LEFT JOIN consumers c ON n.consumer_id = c.id
     {$where}
     ORDER BY n.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "notifications/index.php?page={page}" .
    ($typeFilter ? "&type={$typeFilter}" : "") .
    ($statusFilter ? "&status={$statusFilter}" : "") .
    ($dateFrom ? "&date_from={$dateFrom}" : "") .
    ($dateTo ? "&date_to={$dateTo}" : "");

$allConsumers = db()->fetchAll("SELECT id, consumer_no, full_name, mobile FROM consumers WHERE deleted_at IS NULL AND status = 'active' ORDER BY full_name");
$userGroups = db()->fetchAll("SELECT id, name, slug FROM roles WHERE deleted_at IS NULL ORDER BY name");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Notification Management</h4>
            <p>Send and manage system notifications, emails, and SMS</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>notifications/templates.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-file-alt"></i> Templates
            </a>
            <a href="<?= ADMIN_URL ?>notifications/settings.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-cog"></i> Settings
            </a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#sendModal">
                <i class="fas fa-paper-plane"></i> Send Notification
            </button>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="type" class="form-select form-select-sm" style="min-width:140px;">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm" style="min-width:130px;">
                    <option value="">All Status</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= escape($dateFrom) ?>" placeholder="From">
            </div>
            <div class="col-auto">
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= escape($dateTo) ?>" placeholder="To">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="<?= ADMIN_URL ?>notifications/index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5>Sent Notifications (<?= number_format($total) ?>)</h5></div>
    <div class="card-body">
        <?php if (empty($notifications)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-bell-slash fa-3x mb-3" style="opacity:0.3;"></i>
            <p>No notifications found.</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="table table-hover" id="notifTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Channel</th>
                        <th>Recipient</th>
                        <th>Status</th>
                        <th>Sent At</th>
                        <th style="width:80px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $i => $n): ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td>
                            <strong><?= escape($n['title']) ?></strong>
                            <div class="text-muted small" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= escape(strip_tags($n['message'])) ?></div>
                        </td>
                        <td><span class="badge bg-<?= $n['type'] === 'alert' ? 'danger' : ($n['type'] === 'payment' ? 'success' : ($n['type'] === 'complaint' ? 'warning' : ($n['type'] === 'bill_reminder' ? 'info' : 'primary'))) ?>"><?= ucfirst(str_replace('_', ' ', $n['type'])) ?></span></td>
                        <td><span class="badge bg-secondary"><?= ucfirst($n['channel']) ?></span></td>
                        <td class="small">
                            <?php if ($n['consumer_name']): ?>
                                <i class="fas fa-user"></i> <?= escape($n['consumer_name']) ?>
                            <?php elseif ($n['user_name']): ?>
                                <i class="fas fa-user-shield"></i> <?= escape($n['user_name']) ?>
                            <?php else: ?>
                                <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                        <td><?= get_status_badge($n['status']) ?></td>
                        <td class="small text-muted"><?= $n['sent_at'] ? format_datetime($n['sent_at']) : '-' ?></td>
                        <td>
                            <button type="button" class="btn-action view" title="View" onclick="viewNotif(<?= $n['id'] ?>)"><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($total, $page, $perPage, $paginationUrl) ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="sendModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="sendForm" method="post" action="<?= ADMIN_URL ?>notifications/send.php">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Send Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Notification Type <span class="required">*</span></label>
                                <select name="type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($types as $t): ?>
                                        <option value="<?= $t ?>"><?= ucfirst(str_replace('_', ' ', $t)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Channel <span class="required">*</span></label>
                                <select name="channel" class="form-select" required>
                                    <?php foreach ($channels as $c): ?>
                                        <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="300">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Message <span class="required">*</span></label>
                        <textarea name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Recipients <span class="required">*</span></label>
                        <div class="border rounded p-3 bg-light">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="recipient_type" value="all_consumers" id="rcptAll" checked>
                                <label class="form-check-label" for="rcptAll">All Active Consumers</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="recipient_type" value="specific_consumer" id="rcptConsumer">
                                <label class="form-check-label" for="rcptConsumer">Specific Consumer</label>
                            </div>
                            <div id="consumerSelectWrapper" style="display:none;margin-left:24px;">
                                <select name="consumer_id" class="form-select form-select-sm" style="max-width:300px;">
                                    <option value="">Select Consumer</option>
                                    <?php foreach ($allConsumers as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= escape($c['consumer_no']) ?> - <?= escape($c['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="recipient_type" value="user_group" id="rcptGroup">
                                <label class="form-check-label" for="rcptGroup">User Group (Role)</label>
                            </div>
                            <div id="groupSelectWrapper" style="display:none;margin-left:24px;">
                                <select name="role_id" class="form-select form-select-sm" style="max-width:300px;">
                                    <option value="">Select Role</option>
                                    <?php foreach ($userGroups as $g): ?>
                                        <option value="<?= $g['id'] ?>"><?= escape($g['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="sendBtn"><i class="fas fa-paper-plane"></i> Send</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Notification Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php $extraJs = <<<'JS'
<script>
$(document).ready(function() {
    $('input[name="recipient_type"]').on('change', function() {
        var val = $(this).val();
        $('#consumerSelectWrapper').toggle(val === 'specific_consumer');
        $('#groupSelectWrapper').toggle(val === 'user_group');
    });
});
function viewNotif(id) {
    $('#viewModal').modal('show');
    $('#viewModalBody').html('<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $.get('<?= ADMIN_URL ?>notifications/index.php', { view_id: id }, function(r) {
        if (r.success) {
            var d = r.data;
            var html = '<table class="table table-bordered table-sm">' +
                '<tr><th style="width:120px;">Title</th><td>' + esc(d.title) + '</td></tr>' +
                '<tr><th>Type</th><td>' + esc(d.type) + '</td></tr>' +
                '<tr><th>Channel</th><td>' + esc(d.channel) + '</td></tr>' +
                '<tr><th>Status</th><td>' + esc(d.status) + '</td></tr>' +
                '<tr><th>Recipient</th><td>' + esc(d.recipient) + '</td></tr>' +
                '<tr><th>Sent At</th><td>' + (d.sent_at || '-') + '</td></tr>' +
                '<tr><th>Message</th><td style="white-space:pre-wrap;">' + esc(d.message) + '</td></tr>' +
                '</table>';
            $('#viewModalBody').html(html);
        } else {
            $('#viewModalBody').html('<div class="alert alert-danger">' + (r.message || 'Not found') + '</div>');
        }
    }, 'json').fail(function() {
        $('#viewModalBody').html('<div class="alert alert-danger">Network error</div>');
    });
}
function esc(s) { return $('<div>').text(s || '').html(); }
</script>
JS;
?>

<?php
// AJAX view handler
if (isAjax() && isGet()) {
    $viewId = (int) get('view_id', 0);
    if ($viewId) {
        $n = db()->fetchOne(
            "SELECT n.*, u.name as user_name, c.full_name as consumer_name, c.mobile as consumer_mobile, c.email as consumer_email
             FROM notifications n
             LEFT JOIN users u ON n.user_id = u.id
             LEFT JOIN consumers c ON n.consumer_id = c.id
             WHERE n.id = :id",
            ['id' => $viewId]
        );
        if ($n) {
            $recipient = $n['consumer_name'] ?: $n['user_name'] ?: 'System';
            if ($n['consumer_mobile']) $recipient .= ' (' . $n['consumer_mobile'] . ')';
            json_success([
                'id' => $n['id'],
                'title' => $n['title'],
                'type' => ucfirst(str_replace('_', ' ', $n['type'])),
                'channel' => ucfirst($n['channel']),
                'status' => ucfirst($n['status']),
                'recipient' => $recipient,
                'sent_at' => $n['sent_at'] ? format_datetime($n['sent_at']) : '-',
                'message' => $n['message']
            ]);
        }
        json_error('Notification not found');
    }
    json_error('Invalid request');
}

require_once __DIR__ . '/../includes/footer.php';
?>
