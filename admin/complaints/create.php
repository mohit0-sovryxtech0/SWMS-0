<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Register Complaint';
$breadcrumbs = [
    ['label' => 'Complaint Management', 'url' => ADMIN_URL . 'complaints/index.php'],
    ['label' => 'Register Complaint']
];
RBAC::requirePermission('complaints.create');

require_once __DIR__ . '/../includes/header.php';

$categories = db()->fetchAll("SELECT id, name, sla_hours FROM complaint_categories WHERE deleted_at IS NULL ORDER BY name");
$priorities = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];
$wards = get_ward_options();

$ticketNo = generate_ticket_no();
$errors = [];

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token. Please try again.');
    } else {
        $consumerId = (int) post('consumer_id', 0);
        $data = [
            'consumer_id' => $consumerId ?: null,
            'citizen_name' => trim(post('citizen_name', '')),
            'citizen_phone' => trim(post('citizen_phone', '')),
            'citizen_email' => trim(post('citizen_email', '')),
            'category_id' => (int) post('category_id', 0),
            'priority' => post('priority', 'medium'),
            'subject' => trim(post('subject', '')),
            'description' => trim(post('description', '')),
            'location' => trim(post('location', '')),
            'ward_no' => (int) post('ward_no', 0),
            'latitude' => preg_match('/^-?\d{1,3}\.\d+$/', post('latitude', '')) ? post('latitude', '') : null,
            'longitude' => preg_match('/^-?\d{1,3}\.\d+$/', post('longitude', '')) ? post('longitude', '') : null,
            'status' => 'open',
        ];

        $v = validator($data, [
            'subject' => 'required|min:5|max:300',
            'description' => 'required|min:10',
            'category_id' => 'required|numeric',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        if (empty($data['consumer_id']) && empty($data['citizen_name'])) {
            $v->setFieldName('citizen_name', 'Citizen name');

            class_exists('Validator');
            $r = new ReflectionMethod('Validator', 'addError');
            $r->setAccessible(true);
            $r->invoke($v, 'citizen_name', 'Citizen name is required when no consumer is selected');
        }
        if ($data['ward_no'] > 0) {
            // valid
        }

        if ($v->fails()) {
            $errors = $v->errors();
            $_SESSION['old'] = $data;
            alert_error(implode('<br>', $v->allErrors()));
        } else {
            $attachment = '';
            if (!empty($_FILES['attachment']['name'])) {
                $uploadDir = UPLOADS_PATH . 'complaints/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileName = uniqid() . '_' . time() . '.' . strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                    $attachment = $fileName;
                } else {
                    alert_error('File upload failed.');
                }
            }

            $insertData = [
                'ticket_no' => $ticketNo,
                'consumer_id' => $data['consumer_id'],
                'citizen_name' => $data['consumer_id'] ? null : ($data['citizen_name'] ?: null),
                'citizen_phone' => $data['consumer_id'] ? null : ($data['citizen_phone'] ?: null),
                'citizen_email' => $data['citizen_email'] ?: null,
                'category_id' => $data['category_id'],
                'priority' => $data['priority'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'location' => $data['location'] ?: null,
                'ward_no' => $data['ward_no'] ?: null,
                'latitude' => $data['latitude'] ?: null,
                'longitude' => $data['longitude'] ?: null,
                'attachment' => $attachment ?: null,
                'status' => 'open',
                'created_by' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $complaintId = db()->insert('complaints', $insertData);

            if ($complaintId) {
                db()->insert('complaint_updates', [
                    'complaint_id' => $complaintId,
                    'user_id' => Auth::id(),
                    'status' => 'open',
                    'message' => 'Complaint registered successfully.',
                    'is_public' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                log_activity(Auth::id(), 'create', 'complaints', "Registered complaint: {$ticketNo} - {$data['subject']}", ['complaint_id' => $complaintId]);
                alert_success('Complaint registered successfully. Ticket No: ' . $ticketNo);
                redirect(ADMIN_URL . 'complaints/view.php?id=' . $complaintId);
            } else {
                alert_error('Failed to register complaint. Please try again.');
            }
        }
    }
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Register Complaint</h4>
            <p>Register a new water supply complaint</p>
        </div>
        <a href="<?= ADMIN_URL ?>complaints/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Complaints
        </a>
    </div>
</div>

<?= display_alert() ?>

<form method="POST" action="" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Consumer Search -->
            <div class="card">
                <div class="card-header">
                    <h5>Consumer / Citizen Information</h5>
                </div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label class="form-label">Search Consumer (optional)</label>
                        <div class="input-group">
                            <input type="text" id="consumerSearch" class="form-control" placeholder="Search by consumer no, name or mobile..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="clearConsumerBtn"><i class="fas fa-times"></i></button>
                        </div>
                        <div id="consumerResults" class="list-group mt-1" style="display:none;position:absolute;z-index:1000;max-height:200px;overflow-y:auto;"></div>
                        <input type="hidden" name="consumer_id" id="consumerId" value="<?= (int)old('consumer_id') ?>">
                    </div>
                    <div id="consumerInfo" class="alert alert-info py-2 px-3 mb-3" style="display:<?= old('consumer_id') ? 'block' : 'none' ?>">
                        <strong id="consumerDisplayName"></strong> - <span id="consumerDisplayNo"></span>
                    </div>

                    <div id="citizenFields" style="display:<?= old('consumer_id') ? 'none' : 'block' ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Citizen Name</label>
                                    <input type="text" name="citizen_name" class="form-control" value="<?= escape(old('citizen_name')) ?>" maxlength="200">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="citizen_phone" class="form-control" value="<?= escape(old('citizen_phone')) ?>" maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="citizen_email" class="form-control" value="<?= escape(old('citizen_email')) ?>" maxlength="200">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Complaint Details -->
            <div class="card">
                <div class="card-header">
                    <h5>Complaint Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Ticket No</label>
                                <input type="text" class="form-control" value="<?= $ticketNo ?>" readonly>
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
                                <input type="text" name="subject" class="form-control" value="<?= escape(old('subject')) ?>" required maxlength="300" placeholder="Brief subject of the complaint">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Description <span class="required">*</span></label>
                                <textarea name="description" class="form-control" rows="5" required placeholder="Detailed description of the complaint..."><?= escape(old('description')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="card">
                <div class="card-header">
                    <h5>Location Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Location / Address</label>
                                <textarea name="location" class="form-control" rows="2" placeholder="Describe the location..."><?= escape(old('location')) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Ward No</label>
                                <select name="ward_no" class="form-select">
                                    <option value="">Select Ward</option>
                                    <?php foreach ($wards as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('ward_no') == $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">File Attachment</label>
                                <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" id="latitude" class="form-control" value="<?= escape(old('latitude')) ?>" readonly placeholder="Click on map">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" id="longitude" class="form-control" value="<?= escape(old('longitude')) ?>" readonly placeholder="Click on map">
                            </div>
                        </div>
                    </div>
                    <div id="complaintMap" style="height:300px;border-radius:8px;border:1.5px solid var(--border-color);margin-top:10px;"></div>
                    <p class="form-text mt-2">Click on the map to pin the complaint location.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Guidelines -->
            <div class="card">
                <div class="card-header"><h5>Guidelines</h5></div>
                <div class="card-body">
                    <ul style="font-size:13px;color:var(--text-muted);padding-left:16px;margin:0;">
                        <li class="mb-2">Fields marked with <span class="required">*</span> are required.</li>
                        <li class="mb-2">Search for an existing consumer first, or fill citizen details.</li>
                        <li class="mb-2">Ticket number is auto-generated.</li>
                        <li class="mb-2">Click on the map to record GPS coordinates.</li>
                        <li class="mb-2">Supported attachments: JPG, PNG, PDF, DOC (max 5MB).</li>
                    </ul>
                </div>
            </div>

            <!-- Submit -->
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-save"></i> Register Complaint
                    </button>
                    <a href="<?= ADMIN_URL ?>complaints/index.php" class="btn btn-outline-secondary w-100">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$extraJs = <<<'JS'
<script>
var map, marker;

function initMap() {
    var defaultLat = parseFloat(document.getElementById('latitude').value) || 27.7172;
    var defaultLng = parseFloat(document.getElementById('longitude').value) || 85.3240;

    map = L.map('complaintMap').setView([defaultLat, defaultLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
    }).addTo(map);

    if (document.getElementById('latitude').value && document.getElementById('longitude').value) {
        marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
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
}

document.addEventListener('DOMContentLoaded', function() {
    initMap();

    var searchTimer;
    var searchInput = document.getElementById('consumerSearch');
    var resultsDiv = document.getElementById('consumerResults');

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        var q = this.value.trim();
        if (q.length < 2) { resultsDiv.style.display = 'none'; return; }
        searchTimer = setTimeout(function() {
            fetch('<?= API_URL ?>get-consumers-json.php?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    resultsDiv.innerHTML = '';
                    if (data.data && data.data.length) {
                        data.data.forEach(function(c) {
                            var a = document.createElement('a');
                            a.className = 'list-group-item list-group-item-action';
                            a.href = '#';
                            a.innerHTML = '<strong>' + escapeHtml(c.consumer_no) + '</strong> - ' + escapeHtml(c.full_name) + ' <small>(' + escapeHtml(c.mobile) + ')</small>';
                            a.addEventListener('click', function(e) {
                                e.preventDefault();
                                selectConsumer(c.id, c.consumer_no, c.full_name);
                            });
                            resultsDiv.appendChild(a);
                        });
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.innerHTML = '<div class="list-group-item text-muted">No consumers found</div>';
                        resultsDiv.style.display = 'block';
                    }
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!resultsDiv.contains(e.target) && e.target !== searchInput) {
            resultsDiv.style.display = 'none';
        }
    });

    document.getElementById('clearConsumerBtn').addEventListener('click', function() {
        clearConsumer();
    });

    function selectConsumer(id, no, name) {
        document.getElementById('consumerId').value = id;
        document.getElementById('consumerSearch').value = no + ' - ' + name;
        document.getElementById('consumerInfo').style.display = 'block';
        document.getElementById('consumerDisplayName').textContent = name;
        document.getElementById('consumerDisplayNo').textContent = no;
        document.getElementById('citizenFields').style.display = 'none';
        resultsDiv.style.display = 'none';
    }

    function clearConsumer() {
        document.getElementById('consumerId').value = 0;
        document.getElementById('consumerSearch').value = '';
        document.getElementById('consumerInfo').style.display = 'none';
        document.getElementById('citizenFields').style.display = 'block';
        resultsDiv.style.display = 'none';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
</script>
JS;
unset($_SESSION['old']);
require_once __DIR__ . '/../includes/footer.php'; ?>
