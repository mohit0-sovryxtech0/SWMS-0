<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Verify Meter Readings';
$breadcrumbs = [
    ['label' => 'Home', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Meter Reading', 'url' => ADMIN_URL . 'meter-reading/index.php'],
    ['label' => 'Verify'],
];
RBAC::requirePermission('readings.verify');
$apiUrl = API_URL;
$adminUrl = ADMIN_URL;

$filter = get('filter', 'pending');
$search = get('search', '');
$page = max(1, intval(get('page', 1)));
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];

if ($filter === 'pending') {
    $where .= " AND mr.is_verified = 0";
} elseif ($filter === 'verified') {
    $where .= " AND mr.is_verified = 1";
} elseif ($filter === 'all') {
    // no filter
}

if (!empty($search)) {
    $where .= " AND (c.consumer_no LIKE ? OR c.full_name LIKE ? OR m.meter_no LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$totalReadings = db()->fetchColumn(
    "SELECT COUNT(*) FROM meter_readings mr
     JOIN consumers c ON mr.consumer_id = c.id
     JOIN meters m ON mr.meter_id = m.id
     {$where}",
    $params
);

$readings = db()->fetchAll(
    "SELECT mr.*, m.meter_no, m.meter_type, c.consumer_no, c.full_name AS consumer_name,
            c.ward_no, c.tole, c.mobile,
            u1.name AS reader_name, u2.name AS verifier_name
     FROM meter_readings mr
     JOIN consumers c ON mr.consumer_id = c.id
     JOIN meters m ON mr.meter_id = m.id
     LEFT JOIN users u1 ON mr.read_by = u1.id
     LEFT JOIN users u2 ON mr.verified_by = u2.id
     {$where}
     ORDER BY mr.reading_date DESC, mr.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

$stats = db()->fetchOne(
    "SELECT
        SUM(mr.is_verified = 0) AS pending_count,
        SUM(mr.is_verified = 1) AS verified_count,
        SUM(DATE(mr.created_at) = CURDATE()) AS today_count
     FROM meter_readings mr"
);

if (empty($stats)) {
    $stats = ['pending_count' => 0, 'verified_count' => 0, 'today_count' => 0];
}
$stats['rejected_count'] = 0;

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.reading-card { border-radius: 12px; transition: all .2s; border: 1px solid #dee2e6; }
.reading-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.reading-card.pending { border-left: 4px solid #ffc107; }
.reading-card.verified { border-left: 4px solid #28a745; }
.reading-card.rejected { border-left: 4px solid #dc3545; }
.reading-card .card-header { background: transparent; border-bottom: 1px solid #eee; padding: 12px 16px; }
.reading-card .card-body { padding: 16px; }
.reading-detail-label { font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: .5px; }
.reading-detail-value { font-size: 15px; font-weight: 600; }
.consumption-normal { color: #28a745; }
.consumption-high { color: #dc3545; }
.consumption-low { color: #ffc107; }
.consumption-zero { color: #6c757d; }
</style>

<div class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="fas fa-check-double me-2 text-primary"></i>Verify Meter Readings</h4>
            <p class="text-muted mb-0">Review, approve, or reject submitted meter readings</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>meter-reading/index.php" class="btn btn-outline-primary">
                <i class="fas fa-tachometer-alt me-1"></i>New Reading
            </a>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card bg-warning bg-opacity-10 border-0">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-warning"><?= intval($stats['pending_count']) ?></div>
                <small class="text-muted">Pending Verification</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card bg-success bg-opacity-10 border-0">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-success"><?= intval($stats['verified_count']) ?></div>
                <small class="text-muted">Verified</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card bg-danger bg-opacity-10 border-0">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-danger"><?= intval($stats['rejected_count']) ?></div>
                <small class="text-muted">Rejected</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card bg-info bg-opacity-10 border-0">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-info"><?= intval($stats['today_count']) ?></div>
                <small class="text-muted">Today's Readings</small>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Status Filter</label>
                <select name="filter" class="form-select" onchange="this.form.submit()">
                    <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending Verification</option>
                    <option value="verified" <?= $filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Readings</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Search Consumer/Meter</label>
                <input type="text" name="search" class="form-control" placeholder="Consumer no, name, or meter no..."
                       value="<?= escape($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button>
            </div>
            <div class="col-md-2">
                <a href="?" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Readings Grid -->
<?php if (empty($readings)): ?>
<div class="text-center py-5">
    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
    <h5 class="text-muted">No readings found</h5>
    <p class="text-muted"><?= $filter === 'pending' ? 'All readings have been verified.' : 'No readings match your criteria.' ?></p>
    <?php if ($filter !== 'pending'): ?>
    <a href="?filter=pending" class="btn btn-primary">View Pending Readings</a>
    <?php endif; ?>
</div>
<?php else: ?>
<?php foreach ($readings as $r):
    $consumption = floatval($r['consumption']);
    $prevReading = floatval($r['previous_reading']);
    $currReading = floatval($r['current_reading']);
    $consumptionFlag = '';
    if ($consumption == 0) $consumptionFlag = 'zero';
    elseif ($consumption > 50) $consumptionFlag = 'high';
    elseif ($consumption < 5) $consumptionFlag = 'low';
    else $consumptionFlag = 'normal';

    $cardClass = $r['is_verified'] ? 'verified' : 'pending';
?>
<div class="reading-card <?= $cardClass ?> mb-3" id="reading-<?= $r['id'] ?>">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <strong>#<?= $r['id'] ?></strong>
            <span class="badge bg-secondary"><?= escape($r['meter_no']) ?></span>
            <span class="badge bg-primary"><?= escape($r['consumer_no']) ?></span>
            <span class="badge <?= $r['is_verified'] ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $r['is_verified'] ? 'Verified' : 'Pending' ?></span>
        </div>
        <small class="text-muted"><?= format_date($r['reading_date']) ?> | Reader: <?= escape($r['reader_name'] ?? 'N/A') ?></small>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Consumer Info -->
            <div class="col-md-3">
                <div class="reading-detail-label">Consumer</div>
                <div class="reading-detail-value"><?= escape($r['consumer_name']) ?></div>
                <small class="text-muted">Ward <?= escape($r['ward_no']) ?>, <?= escape($r['tole'] ?? '') ?></small>
            </div>
            <!-- Reading Values -->
            <div class="col-md-3">
                <div class="reading-detail-label">Reading Values</div>
                <div class="row g-1">
                    <div class="col-6"><small class="text-muted">Previous:</small></div>
                    <div class="col-6 text-end"><strong><?= number_format($prevReading, 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted">Current:</small></div>
                    <div class="col-6 text-end"><strong><?= number_format($currReading, 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted">Consumption:</small></div>
                    <div class="col-6 text-end">
                        <strong class="consumption-<?= $consumptionFlag ?>"><?= number_format($consumption, 2) ?></strong>
                    </div>
                </div>
            </div>
            <!-- GPS & Photo -->
            <div class="col-md-3">
                <div class="reading-detail-label">Location & Photo</div>
                <?php if ($r['gps_latitude'] && $r['gps_longitude']): ?>
                <div><small class="text-muted">GPS:</small>
                    <a href="https://www.google.com/maps?q=<?= $r['gps_latitude'] ?>,<?= $r['gps_longitude'] ?>" target="_blank" class="small">
                        <?= number_format($r['gps_latitude'], 6) ?>, <?= number_format($r['gps_longitude'], 6) ?>
                    </a>
                </div>
                <?php else: ?>
                <div><small class="text-muted">GPS: <span class="text-warning">Not captured</span></small></div>
                <?php endif; ?>
                <?php if ($r['meter_photo']): ?>
                <div><a href="#" class="small" onclick="return viewPhoto(<?= $r['id'] ?>, '<?= escape($r['meter_photo']) ?>')">
                    <i class="fas fa-camera me-1"></i>View Photo
                </a></div>
                <?php else: ?>
                <div><small class="text-muted">Photo: <span class="text-warning">Not uploaded</span></small></div>
                <?php endif; ?>
            </div>
            <!-- Actions -->
            <div class="col-md-3 d-flex flex-column justify-content-center align-items-end gap-2">
                <?php if (!$r['is_verified']): ?>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="approveReading(<?= $r['id'] ?>)">
                        <i class="fas fa-check me-1"></i>Approve
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="rejectReading(<?= $r['id'] ?>)">
                        <i class="fas fa-times me-1"></i>Reject
                    </button>
                </div>
                <?php else: ?>
                <div class="text-end">
                    <span class="badge bg-success">Verified</span>
                    <?php if ($r['verifier_name']): ?>
                    <br><small class="text-muted">by <?= escape($r['verifier_name']) ?></small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($r['remarks']): ?>
        <div class="mt-2 pt-2 border-top">
            <small class="text-muted">Remarks: <?= escape($r['remarks']) ?></small>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?= pagination($totalReadings, $page, $perPage, ADMIN_URL . "meter-reading/verify.php?filter={$filter}&search={$search}&page={page}") ?>
<?php endif; ?>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera me-2"></i>Meter Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="meterPhoto" src="" alt="Meter Photo" class="img-fluid" style="max-height:400px;">
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Reject Reading</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="reading_id" id="rejectReadingId">
                <input type="hidden" name="action" value="reject">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <select name="reject_reason" id="rejectReason" class="form-select" required>
                            <option value="">Select a reason...</option>
                            <option value="Meter reading seems inaccurate">Meter reading seems inaccurate</option>
                            <option value="Meter photo not clear or missing">Meter photo not clear or missing</option>
                            <option value="GPS location mismatch">GPS location mismatch</option>
                            <option value="Reading significantly higher than expected">Reading significantly higher than expected</option>
                            <option value="Reading significantly lower than expected">Reading significantly lower than expected</option>
                            <option value="Wrong meter or consumer">Wrong meter or consumer</option>
                            <option value="Duplicate reading">Duplicate reading</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional details..."></textarea>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-info-circle me-1"></i> Rejected readings will be returned to the meter reader for correction and resubmission.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i>Reject Reading</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
function viewPhoto(id, path) {
    $('#meterPhoto').attr('src', '<?= ADMIN_URL ?>../uploads/meter-photos/' + path);
    $('#photoModal').modal('show');
    return false;
}

function approveReading(id) {
    if (!confirm('Approve this reading? The meter\'s last reading will be updated.')) return;
    $.post('<?= $apiUrl ?>verify-reading.php', {
        action: 'approve',
        reading_id: id,
        csrf_token: '<?= csrf_token() ?>'
    }, function(res) {
        if (res.success) {
            alert(res.message);
            location.reload();
        } else {
            alert(res.message || 'Error approving reading');
        }
    }, 'json').fail(function(xhr) {
        try {
            var r = JSON.parse(xhr.responseText);
            alert(r.message || 'Request failed');
        } catch(e) {
            alert('Request failed');
        }
    });
}

function rejectReading(id) {
    $('#rejectReadingId').val(id);
    $('#rejectReason').val('');
    $('#rejectForm textarea[name="remarks"]').val('');
    $('#rejectModal').modal('show');
}

$('#rejectForm').submit(function(e) {
    e.preventDefault();
    var reason = $('#rejectReason').val();
    if (!reason) { alert('Please select a rejection reason'); return; }

    $.post('<?= $apiUrl ?>verify-reading.php', $(this).serialize(), function(res) {
        if (res.success) {
            $('#rejectModal').modal('hide');
            alert(res.message);
            location.reload();
        } else {
            alert(res.message || 'Error rejecting reading');
        }
    }, 'json').fail(function(xhr) {
        try {
            var r = JSON.parse(xhr.responseText);
            alert(r.message || 'Request failed');
        } catch(e) {
            alert('Request failed');
        }
    });
});
</script>
<?php
$extraJs = ob_get_clean();
require_once __DIR__ . '/../includes/footer.php';
?>
