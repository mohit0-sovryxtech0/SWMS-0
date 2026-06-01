<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Meter Reading History';
$breadcrumbs = [
    ['label' => 'Home', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Meter Reading', 'url' => ADMIN_URL . 'meter-reading/index.php'],
    ['label' => 'History'],
];
RBAC::requirePermission('readings.view');
$apiUrl = API_URL;
require_once __DIR__ . '/../includes/header.php';

$readings = db()->fetchAll(
    "SELECT mr.*, c.consumer_no, c.full_name, c.mobile, m.meter_no,
            u.name as reader_name, vu.name as verifier_name
     FROM meter_readings mr
     JOIN consumers c ON mr.consumer_id = c.id
     JOIN meters m ON mr.meter_id = m.id
     LEFT JOIN users u ON mr.read_by = u.id
     LEFT JOIN users vu ON mr.verified_by = vu.id
     WHERE c.deleted_at IS NULL AND m.deleted_at IS NULL
     ORDER BY mr.created_at DESC LIMIT 0"
);
?>
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-history me-2 text-primary"></i>Meter Reading History</h4>
        <p class="text-muted mb-0">View all meter readings with search and filter options</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Reading</a>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Search</label>
                <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Consumer, meter, reader...">
            </div>
            <div class="col-md-2">
                <label class="form-label small">From Date</label>
                <input type="date" class="form-control form-control-sm" id="filterFrom">
            </div>
            <div class="col-md-2">
                <label class="form-label small">To Date</label>
                <input type="date" class="form-control form-control-sm" id="filterTo">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select class="form-select form-select-sm" id="filterStatus">
                    <option value="">All</option>
                    <option value="1">Verified</option>
                    <option value="0">Unverified</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1" id="applyFilter"><i class="fas fa-filter me-1"></i>Filter</button>
                <button class="btn btn-outline-secondary btn-sm" id="resetFilter"><i class="fas fa-undo me-1"></i>Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="readingsTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Consumer</th>
                        <th>Meter No</th>
                        <th>Previous</th>
                        <th>Current</th>
                        <th>Consumption</th>
                        <th>Reader</th>
                        <th>Status</th>
                        <th>Verified By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $allReadings = db()->fetchAll(
                        "SELECT mr.*, c.consumer_no, c.full_name, c.mobile, m.meter_no,
                                u.name as reader_name, vu.name as verifier_name
                         FROM meter_readings mr
                         JOIN consumers c ON mr.consumer_id = c.id
                         JOIN meters m ON mr.meter_id = m.id
                         LEFT JOIN users u ON mr.read_by = u.id
                         LEFT JOIN users vu ON mr.verified_by = vu.id
                         WHERE c.deleted_at IS NULL AND m.deleted_at IS NULL
                         ORDER BY mr.reading_date DESC, mr.created_at DESC LIMIT 500"
                    );
                    foreach ($allReadings as $r):
                    ?>
                    <tr>
                        <td><?= format_date($r['reading_date']) ?></td>
                        <td>
                            <strong><?= escape($r['full_name']) ?></strong><br>
                            <small class="text-muted"><?= escape($r['consumer_no']) ?></small>
                        </td>
                        <td><?= escape($r['meter_no']) ?></td>
                        <td><?= number_format($r['previous_reading'], 2) ?></td>
                        <td><?= number_format($r['current_reading'], 2) ?></td>
                        <td><strong><?= number_format($r['consumption'], 2) ?></strong></td>
                        <td><?= escape($r['reader_name'] ?? '-') ?></td>
                        <td>
                            <?php if ($r['is_verified']): ?>
                                <span class="badge bg-success">Verified</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?= escape($r['verifier_name'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-info view-reading" data-id="<?= $r['id'] ?>" title="View Details"><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5><i class="fas fa-info-circle me-2 text-primary"></i>Reading Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="detailBody"></div>
        </div>
    </div>
</div>

<div id="spinnerOverlay" class="d-none" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,.7);z-index:9999;align-items:center;justify-content:center;">
    <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;"><span class="visually-hidden">Loading...</span></div>
</div>
<?php
ob_start(); ?>
<script>
$(function() {
    const table = $('#readingsTable').DataTable({
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 25,
        language: { search: '', searchPlaceholder: 'Type to filter...' },
        columnDefs: [
            { orderable: false, targets: 9 }
        ]
    });

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        const from = $('#filterFrom').val();
        const to = $('#filterTo').val();
        const status = $('#filterStatus').val();
        const date = data[0];

        if (from && date < from) return false;
        if (to && date > to) return false;

        const statusCol = data[7].toLowerCase();
        if (status !== '') {
            if (status === '1' && !statusCol.includes('verified')) return false;
            if (status === '0' && !statusCol.includes('pending')) return false;
        }
        return true;
    });

    $('#applyFilter').on('click', function() { table.draw(); });
    $('#resetFilter').on('click', function() {
        $('#filterFrom, #filterTo').val('');
        $('#filterStatus').val('');
        $('#searchInput').val('');
        table.search('').draw();
    });

    $('#searchInput').on('keyup', function() {
        table.search(this.value).draw();
    });

    $(document).on('click', '.view-reading', function() {
        const id = $(this).data('id');
        $('#spinnerOverlay').show();
        $.get('<?= $apiUrl ?>get-consumer-for-reading.php', { reading_id: id })
            .done(function(res) {
                if (res.success) {
                    const d = res.data;
                    let html = '<table class="table table-sm table-bordered mb-0">';
                    if (d.readings && d.readings.length) {
                        const r = d.readings[0];
                        html += '<tr><td class="text-muted">Reading Date</td><td>' + r.reading_date + '</td></tr>';
                        html += '<tr><td class="text-muted">Current Reading</td><td class="fw-600">' + parseFloat(r.current_reading).toFixed(2) + '</td></tr>';
                        html += '<tr><td class="text-muted">Consumption</td><td class="fw-600">' + parseFloat(r.consumption || 0).toFixed(2) + '</td></tr>';
                        html += '<tr><td class="text-muted">Remarks</td><td>' + (r.remarks || '-') + '</td></tr>';
                        html += '<tr><td class="text-muted">Status</td><td>' + (r.is_verified ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning">Pending</span>') + '</td></tr>';
                        html += '<tr><td class="text-muted">Created</td><td>' + r.created_at + '</td></tr>';
                    } else {
                        html += '<tr><td colspan="2" class="text-center text-muted">No details available</td></tr>';
                    }
                    html += '</table>';
                    $('#detailBody').html(html);
                    $('#detailModal').modal('show');
                } else {
                    alert(res.message);
                }
            })
            .fail(function() { alert('Failed to load details'); })
            .always(function() { $('#spinnerOverlay').hide(); });
    });
});
</script>
<?php
$extraJs = ob_get_clean();
require_once __DIR__ . '/../includes/footer.php';
?>
