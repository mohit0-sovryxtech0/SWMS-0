<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Edit Complaint';
$breadcrumbs = [
    ['label' => 'Complaint Management', 'url' => ADMIN_URL . 'complaints/index.php'],
    ['label' => 'Edit Complaint']
];
RBAC::requirePermission('complaints.create');

require_once __DIR__ . '/../includes/header.php';

$id = (int) get('id', 0);
if ($id <= 0) {
    alert_error('Invalid complaint ID.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$complaint = db()->fetchOne("SELECT * FROM complaints WHERE id = ? AND deleted_at IS NULL", [$id]);
if (!$complaint) {
    alert_error('Complaint not found.');
    redirect(ADMIN_URL . 'complaints/index.php');
}

$categories = db()->fetchAll("SELECT id, name FROM complaint_categories WHERE deleted_at IS NULL ORDER BY name");
$priorities = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];
$wards = get_ward_options();
$errors = [];

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token. Please try again.');
    } else {
        $data = [
            'category_id' => (int) post('category_id', 0),
            'priority' => post('priority', 'medium'),
            'subject' => trim(post('subject', '')),
            'description' => trim(post('description', '')),
            'location' => trim(post('location', '')),
            'ward_no' => (int) post('ward_no', 0),
            'latitude' => post('latitude', ''),
            'longitude' => post('longitude', ''),
            'citizen_name' => trim(post('citizen_name', '')),
            'citizen_phone' => trim(post('citizen_phone', '')),
            'citizen_email' => trim(post('citizen_email', '')),
        ];

        $v = validator($data, [
            'subject' => 'required|min:5|max:300',
            'description' => 'required|min:10',
            'category_id' => 'required|numeric',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        if ($v->fails()) {
            $errors = $v->errors();
            $_SESSION['old'] = $data;
            alert_error(implode('<br>', $v->allErrors()));
        } else {
            $updateData = [
                'category_id' => $data['category_id'],
                'priority' => $data['priority'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'location' => $data['location'] ?: null,
                'ward_no' => $data['ward_no'] ?: null,
                'latitude' => $data['latitude'] ?: null,
                'longitude' => $data['longitude'] ?: null,
                'citizen_name' => $data['citizen_name'] ?: null,
                'citizen_phone' => $data['citizen_phone'] ?: null,
                'citizen_email' => $data['citizen_email'] ?: null,
            ];

            if (!empty($_FILES['attachment']['name'])) {
                $uploadDir = UPLOADS_PATH . 'complaints/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileName = uniqid() . '_' . time() . '.' . strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                    if ($complaint['attachment'] && file_exists($uploadDir . $complaint['attachment'])) {
                        unlink($uploadDir . $complaint['attachment']);
                    }
                    $updateData['attachment'] = $fileName;
                }
            }

            db()->update('complaints', $updateData, 'id = :id', ['id' => $id]);
            log_activity(Auth::id(), 'edit', 'complaints', "Edited complaint: {$complaint['ticket_no']}", ['complaint_id' => $id]);
            alert_success('Complaint updated successfully.');
            redirect(ADMIN_URL . 'complaints/view.php?id=' . $id);
        }
    }
}

if (empty($_SESSION['old'])) {
    $_SESSION['old'] = $complaint;
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Edit Complaint - <?= escape($complaint['ticket_no']) ?></h4>
        </div>
        <a href="<?= ADMIN_URL ?>complaints/view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?= display_alert() ?>

<form method="POST" action="" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5>Complaint Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Ticket No</label>
                                <input type="text" class="form-control" value="<?= escape($complaint['ticket_no']) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Category <span class="required">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Priority <span class="required">*</span></label>
                                <select name="priority" class="form-select" required>
                                    <?php foreach ($priorities as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('priority') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Subject <span class="required">*</span></label>
                                <input type="text" name="subject" class="form-control" value="<?= escape(old('subject')) ?>" required maxlength="300">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Description <span class="required">*</span></label>
                                <textarea name="description" class="form-control" rows="5" required><?= escape(old('description')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>Citizen Information</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Citizen Name</label>
                                <input type="text" name="citizen_name" class="form-control" value="<?= escape(old('citizen_name')) ?>" maxlength="200">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="citizen_phone" class="form-control" value="<?= escape(old('citizen_phone')) ?>" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="citizen_email" class="form-control" value="<?= escape(old('citizen_email')) ?>" maxlength="200">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>Location</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Location / Address</label>
                                <textarea name="location" class="form-control" rows="2"><?= escape(old('location')) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Ward No</label>
                                <select name="ward_no" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($wards as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('ward_no') == $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Attachment</label>
                                <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                                <?php if ($complaint['attachment']): ?>
                                <small class="text-muted">Current: <?= $complaint['attachment'] ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" id="latitude" class="form-control" value="<?= escape(old('latitude')) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" id="longitude" class="form-control" value="<?= escape(old('longitude')) ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div id="editMap" style="height:300px;border-radius:8px;border:1.5px solid var(--border-color);margin-top:10px;"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-save"></i> Update Complaint
                    </button>
                    <a href="<?= ADMIN_URL ?>complaints/view.php?id=<?= $id ?>" class="btn btn-outline-secondary w-100">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$extraJs = <<<'JS'
<script>
var map, marker;
document.addEventListener('DOMContentLoaded', function() {
    var lat = parseFloat(document.getElementById('latitude').value) || 27.7172;
    var lng = parseFloat(document.getElementById('longitude').value) || 85.3240;
    map = L.map('editMap').setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
    if (document.getElementById('latitude').value && document.getElementById('longitude').value) {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', function(e) {
            var ll = marker.getLatLng();
            document.getElementById('latitude').value = ll.lat.toFixed(6);
            document.getElementById('longitude').value = ll.lng.toFixed(6);
        });
    }
    map.on('click', function(e) {
        if (marker) { marker.setLatLng(e.latlng); }
        else {
            marker = L.marker(e.latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function(ev) {
                var ll = marker.getLatLng();
                document.getElementById('latitude').value = ll.lat.toFixed(6);
                document.getElementById('longitude').value = ll.lng.toFixed(6);
            });
        }
        document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
    });
});
</script>
JS;
unset($_SESSION['old']);
require_once __DIR__ . '/../includes/footer.php'; ?>
