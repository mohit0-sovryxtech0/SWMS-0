<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Meter Management';
$breadcrumbs = [
    ['label' => 'Home', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Meter Reading', 'url' => ADMIN_URL . 'meter-reading/index.php'],
    ['label' => 'Meters'],
];
RBAC::requirePermission('readings.view');
require_once __DIR__ . '/../includes/header.php';

$meters = db()->fetchAll(
    "SELECT m.*, c.consumer_no, c.full_name, c.mobile, c.ward_no
     FROM meters m
     LEFT JOIN consumers c ON m.consumer_id = c.id AND c.deleted_at IS NULL
     WHERE m.deleted_at IS NULL
     ORDER BY m.created_at DESC"
);

$consumers = db()->fetchAll(
    "SELECT id, consumer_no, full_name, mobile, ward_no, tole
     FROM consumers WHERE status = 'active' AND deleted_at IS NULL
     ORDER BY consumer_no"
);

$unassignedMeters = db()->fetchAll(
    "SELECT id, meter_no, meter_type, meter_brand, meter_model, meter_size, status
     FROM meters WHERE consumer_id IS NULL AND status = 'active' AND deleted_at IS NULL
     ORDER BY meter_no"
);

if (isPost()) {
    $token = post('csrf_token');
    if (!verify_csrf($token)) {
        alert_error('Invalid security token');
        redirect($_SERVER['PHP_SELF']);
    }

    $formAction = post('form_action');

    try {
        if ($formAction === 'register') {
            $validator = validator($_POST, [
                'meter_no' => 'required|unique:meters,meter_no',
                'meter_type' => 'required',
                'initial_reading' => 'numeric',
                'installation_date' => 'date',
            ]);
            $validator->setFieldNames([
                'meter_no' => 'Meter number',
                'meter_type' => 'Meter type',
                'initial_reading' => 'Initial reading',
                'installation_date' => 'Installation date',
            ]);

            if ($validator->fails()) {
                throw new Exception($validator->firstError());
            }

            $photoPath = null;
            if (!empty($_FILES['meter_photo']) && $_FILES['meter_photo']['error'] === UPLOAD_ERR_OK) {
                $result = upload_file($_FILES['meter_photo'], UPLOADS_PATH . 'meters/', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                if ($result) $photoPath = 'meters/' . $result;
            }

            $meterId = db()->insert('meters', [
                'meter_no' => post('meter_no'),
                'meter_type' => post('meter_type'),
                'meter_brand' => post('meter_brand'),
                'meter_model' => post('meter_model'),
                'meter_size' => post('meter_size'),
                'initial_reading' => (float)post('initial_reading', 0),
                'installation_date' => post('installation_date') ?: null,
                'gps_latitude' => post('latitude') ?: null,
                'gps_longitude' => post('longitude') ?: null,
                'meter_photo' => $photoPath,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            log_activity(Auth::id(), 'Meter Registered', 'Meter', "Meter {$meterId} ({$_POST['meter_no']}) registered");
            alert_success('Meter registered successfully');
        }

        elseif ($formAction === 'assign') {
            $meterId = (int)post('meter_id');
            $consumerId = (int)post('consumer_id');

            $meter = db()->fetchOne("SELECT * FROM meters WHERE id = ? AND deleted_at IS NULL", [$meterId]);
            if (!$meter) throw new Exception('Meter not found');
            if ($meter['consumer_id']) throw new Exception('Meter is already assigned to a consumer');

            $consumer = db()->fetchOne("SELECT * FROM consumers WHERE id = ? AND deleted_at IS NULL", [$consumerId]);
            if (!$consumer) throw new Exception('Consumer not found');

            $existingMeter = db()->fetchOne(
                "SELECT id, meter_no FROM meters WHERE consumer_id = ? AND status = 'active' AND deleted_at IS NULL AND id != ?",
                [$consumerId, $meterId]
            );
            if ($existingMeter) {
                throw new Exception("Consumer already has an active meter ({$existingMeter['meter_no']}). Replace it first.");
            }

            db()->beginTransaction();
            db()->update('meters', [
                'consumer_id' => $consumerId,
                'status' => 'active',
                'installation_date' => post('assign_date') ?: date('Y-m-d'),
            ], 'id = :id', ['id' => $meterId]);

            log_activity(Auth::id(), 'Meter Assigned', 'Meter',
                "Meter {$meter['meter_no']} assigned to {$consumer['consumer_no']}"
            );
            db()->commit();
            alert_success("Meter {$meter['meter_no']} assigned to {$consumer['consumer_no']}");
        }

        elseif ($formAction === 'replace') {
            $oldMeterId = (int)post('old_meter_id');
            $newMeterId = (int)post('new_meter_id');
            $consumerId = (int)post('consumer_id');
            $replaceDate = post('replace_date', date('Y-m-d'));
            $reason = post('replace_reason');

            $oldMeter = db()->fetchOne("SELECT * FROM meters WHERE id = ? AND deleted_at IS NULL", [$oldMeterId]);
            if (!$oldMeter) throw new Exception('Old meter not found');
            $newMeter = db()->fetchOne("SELECT * FROM meters WHERE id = ? AND deleted_at IS NULL", [$newMeterId]);
            if (!$newMeter) throw new Exception('New meter not found');
            if ($newMeter['consumer_id']) throw new Exception('New meter is already assigned');

            db()->beginTransaction();

            $oldReading = (float)post('old_reading', $oldMeter['last_reading'] ?: $oldMeter['initial_reading']);
            $newReading = (float)post('new_reading', 0);

            db()->insert('meter_replacements', [
                'consumer_id' => $consumerId,
                'old_meter_id' => $oldMeterId,
                'new_meter_id' => $newMeterId,
                'old_reading' => $oldReading,
                'new_reading' => $newReading,
                'replacement_date' => $replaceDate,
                'reason' => $reason,
                'done_by' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            db()->update('meters', ['status' => 'replaced', 'consumer_id' => null], 'id = :id', ['id' => $oldMeterId]);
            db()->update('meters', [
                'consumer_id' => $consumerId,
                'status' => 'active',
                'initial_reading' => $newReading,
                'installation_date' => $replaceDate,
            ], 'id = :id', ['id' => $newMeterId]);

            log_activity(Auth::id(), 'Meter Replaced', 'Meter',
                "Meter {$oldMeter['meter_no']} replaced with {$newMeter['meter_no']} for consumer #{$consumerId}"
            );
            db()->commit();
            alert_success("Meter replaced successfully. {$oldMeter['meter_no']} -> {$newMeter['meter_no']}");
        }

        elseif ($formAction === 'change_status') {
            $meterId = (int)post('meter_id');
            $newStatus = post('new_status');
            $statusReason = post('status_reason');

            $validStatuses = ['active', 'inactive', 'defective', 'replaced', 'damaged'];
            if (!in_array($newStatus, $validStatuses)) throw new Exception('Invalid status');

            $meter = db()->fetchOne("SELECT * FROM meters WHERE id = ? AND deleted_at IS NULL", [$meterId]);
            if (!$meter) throw new Exception('Meter not found');

            db()->update('meters', ['status' => $newStatus], 'id = :id', ['id' => $meterId]);

            log_activity(Auth::id(), 'Meter Status Changed', 'Meter',
                "Meter {$meter['meter_no']} status changed to {$newStatus}: {$statusReason}"
            );
            alert_success("Meter {$meter['meter_no']} status updated to {$newStatus}");
        }

        redirect($_SERVER['PHP_SELF']);
    } catch (Exception $e) {
        db()->rollback();
        alert_error($e->getMessage());
        redirect($_SERVER['PHP_SELF']);
    }
}
?>
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-water-meter me-2 text-primary"></i>Meter Management</h4>
        <p class="text-muted mb-0">Register, assign, replace, and manage water meters</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal"><i class="fas fa-plus me-1"></i>Register Meter</button>
        <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-tachometer-alt me-1"></i>Meter Reading</a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="metersTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Meter No</th>
                        <th>Type</th>
                        <th>Brand / Model</th>
                        <th>Size</th>
                        <th>Consumer</th>
                        <th>Last Reading</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meters as $m): ?>
                    <tr>
                        <td class="fw-600"><?= escape($m['meter_no']) ?></td>
                        <td><?= escape(ucfirst($m['meter_type'])) ?></td>
                        <td><?= escape($m['meter_brand']) ?> <?= escape($m['meter_model']) ?></td>
                        <td><?= escape($m['meter_size']) ?: '-' ?></td>
                        <td>
                            <?php if ($m['consumer_id']): ?>
                                <strong><?= escape($m['full_name']) ?></strong><br>
                                <small class="text-muted"><?= escape($m['consumer_no']) ?></small>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= number_format($m['last_reading'] ?: $m['initial_reading'], 2) ?>
                            <?php if ($m['last_reading_date']): ?>
                                <br><small class="text-muted"><?= format_date($m['last_reading_date']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= get_status_badge($m['status']) ?></td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="alert('Meter No: <?= escape($m['meter_no']) ?>\nType: <?= escape($m['meter_type']) ?>\nBrand: <?= escape($m['meter_brand']) ?>\nModel: <?= escape($m['meter_model']) ?>\nSize: <?= escape($m['meter_size']) ?>\nInitial Reading: <?= $m['initial_reading'] ?>\nInstalled: <?= $m['installation_date'] ?: 'N/A' ?>\nGPS: <?= $m['gps_latitude'] ? $m['gps_latitude'] . ', ' . $m['gps_longitude'] : 'Not set' ?>')"><i class="fas fa-eye me-2"></i>View Details</a></li>
                                    <?php if (!$m['consumer_id'] && $m['status'] === 'active'): ?>
                                    <li><a class="dropdown-item assign-meter" data-meter-id="<?= $m['id'] ?>" data-meter-no="<?= escape($m['meter_no']) ?>" href="#"><i class="fas fa-link me-2"></i>Assign to Consumer</a></li>
                                    <?php endif; ?>
                                    <?php if ($m['consumer_id'] && $m['status'] === 'active'): ?>
                                    <li><a class="dropdown-item replace-meter" data-meter-id="<?= $m['id'] ?>" data-consumer-id="<?= $m['consumer_id'] ?>" data-consumer-name="<?= escape($m['full_name']) ?>" href="#"><i class="fas fa-exchange-alt me-2"></i>Replace Meter</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item change-status" data-meter-id="<?= $m['id'] ?>" data-meter-no="<?= escape($m['meter_no']) ?>" data-current-status="<?= $m['status'] ?>" href="#"><i class="fas fa-flag me-2"></i>Change Status</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="form_action" value="register">
                <div class="modal-header"><h5><i class="fas fa-plus-circle me-2 text-primary"></i>Register New Meter</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Meter No <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="meter_no" required placeholder="e.g. MT-2026-0001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Meter Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="meter_type" required>
                                <option value="">Select Type</option>
                                <option value="domestic">Domestic</option>
                                <option value="commercial">Commercial</option>
                                <option value="bulk">Bulk</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <input type="text" class="form-control" name="meter_brand" placeholder="e.g. Elster, Sensus">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control" name="meter_model" placeholder="Model number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Size</label>
                            <input type="text" class="form-control" name="meter_size" placeholder="e.g. 15mm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Initial Reading</label>
                            <input type="number" step="0.01" class="form-control" name="initial_reading" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Installation Date</label>
                            <input type="date" class="form-control" name="installation_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">GPS Latitude</label>
                            <input type="text" class="form-control" name="latitude" id="regLat" placeholder="27.7172000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">GPS Longitude</label>
                            <input type="text" class="form-control" name="longitude" id="regLng" placeholder="85.3240000">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Meter Photo</label>
                            <input type="file" class="form-control" name="meter_photo" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Register Meter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="form_action" value="assign">
                <input type="hidden" name="meter_id" id="assignMeterId">
                <div class="modal-header"><h5><i class="fas fa-link me-2 text-primary"></i>Assign Meter to Consumer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Meter: <strong id="assignMeterNo"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Consumer <span class="text-danger">*</span></label>
                        <select class="form-select" name="consumer_id" required>
                            <option value="">Select consumer...</option>
                            <?php foreach ($consumers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= escape($c['consumer_no']) ?> - <?= escape($c['full_name']) ?> (Ward <?= $c['ward_no'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assignment Date</label>
                        <input type="date" class="form-control" name="assign_date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-link me-1"></i>Assign Meter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="replaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="form_action" value="replace">
                <input type="hidden" name="old_meter_id" id="replaceOldMeterId">
                <input type="hidden" name="consumer_id" id="replaceConsumerId">
                <div class="modal-header"><h5><i class="fas fa-exchange-alt me-2 text-warning"></i>Replace Meter</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Replacing meter for: <strong id="replaceConsumerName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Meter <span class="text-danger">*</span></label>
                        <select class="form-select" name="new_meter_id" required>
                            <option value="">Select new meter...</option>
                            <?php foreach ($unassignedMeters as $um): ?>
                            <option value="<?= $um['id'] ?>"><?= escape($um['meter_no']) ?> (<?= escape($um['meter_type']) ?> - <?= escape($um['meter_brand']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Old Meter Reading</label>
                            <input type="number" step="0.01" class="form-control" name="old_reading" id="replaceOldReading" placeholder="Enter final reading">
                        </div>
                        <div class="col-6">
                            <label class="form-label">New Meter Initial</label>
                            <input type="number" step="0.01" class="form-control" name="new_reading" value="0">
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">Replacement Date</label>
                        <input type="date" class="form-control" name="replace_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mt-2">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="replace_reason" rows="2" placeholder="e.g. Meter damaged, faulty, expired calibration..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-exchange-alt me-1"></i>Replace Meter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="form_action" value="change_status">
                <input type="hidden" name="meter_id" id="statusMeterId">
                <div class="modal-header"><h5><i class="fas fa-flag me-2 text-primary"></i>Change Meter Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Meter: <strong id="statusMeterNo"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Status <span class="text-danger">*</span></label>
                        <select class="form-select" name="new_status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="defective">Defective</option>
                            <option value="damaged">Damaged</option>
                            <option value="replaced">Replaced</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="status_reason" rows="2" placeholder="Why is this status changing?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
<script>
$(function() {
    $('#metersTable').DataTable({
        responsive: true,
        order: [[0, 'asc']],
        pageLength: 25,
        language: { search: '', searchPlaceholder: 'Type to filter...' },
        columnDefs: [{ orderable: false, targets: 7 }]
    });

    $(document).on('click', '.assign-meter', function(e) {
        e.preventDefault();
        $('#assignMeterId').val($(this).data('meter-id'));
        $('#assignMeterNo').text($(this).data('meter-no'));
        $('#assignModal').modal('show');
    });

    $(document).on('click', '.replace-meter', function(e) {
        e.preventDefault();
        const meterId = $(this).data('meter-id');
        const consumerId = $(this).data('consumer-id');
        const consumerName = $(this).data('consumer-name');

        $('#replaceOldMeterId').val(meterId);
        $('#replaceConsumerId').val(consumerId);
        $('#replaceConsumerName').text(consumerName);

        const row = $(this).closest('tr');
        const lastReading = row.find('td:eq(5)').text().trim();
        $('#replaceOldReading').val(parseFloat(lastReading.replace(/,/g, '')) || 0);

        $('#replaceModal').modal('show');
    });

    $(document).on('click', '.change-status', function(e) {
        e.preventDefault();
        $('#statusMeterId').val($(this).data('meter-id'));
        $('#statusMeterNo').text($(this).data('meter-no'));
        const curStatus = $(this).data('current-status');
        $('#statusModal select[name="new_status"]').val(curStatus);
        $('#statusModal').modal('show');
    });
});
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
