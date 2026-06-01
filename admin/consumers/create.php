<?php
@include_once __DIR__ . '/../../includes/config.php';
if (!defined('ADMIN_URL')) {
    define('BASE_URL', 'http://localhost/CMS%20000/');
    define('ADMIN_URL', BASE_URL . 'admin/');
}
$pageTitle = 'Register Consumer';
$breadcrumbs = [
    ['label' => 'Consumer Management', 'url' => ADMIN_URL . 'consumers/index.php'],
    ['label' => 'Register Consumer']
];
RBAC::requirePermission('consumers.create');
require_once __DIR__ . '/../includes/header.php';

$categories = db()->fetchAll("SELECT id, name FROM consumer_categories WHERE deleted_at IS NULL ORDER BY name");
$genders = get_gender_options();
$connectionTypes = ['household' => 'Household', 'commercial' => 'Commercial', 'institutional' => 'Institutional'];
$provinces = get_province_options();
$districts = get_district_options();
$municipalities = get_municipality_options();
$wards = get_ward_options();
$propertyTypes = ['own' => 'Own', 'rental' => 'Rental', 'government' => 'Government', 'other' => 'Other'];
$pipeSizes = ['15mm', '20mm', '25mm', '32mm', '40mm', '50mm', '65mm', '80mm', '100mm'];
$connectionSizes = ['1/2"', '3/4"', '1"', '1.5"', '2"'];

$consumerNo = db()->fetchColumn("SELECT MAX(CAST(SUBSTRING(consumer_no, 5) AS UNSIGNED)) FROM consumers WHERE consumer_no LIKE 'SWM-%'");
$nextNo = 'SWM-' . str_pad(($consumerNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

$errors = [];

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token. Please try again.');
    } else {
        $data = [
            'consumer_no' => trim(post('consumer_no', $nextNo)),
            'full_name' => trim(post('full_name', '')),
            'father_name' => trim(post('father_name', '')),
            'mother_name' => trim(post('mother_name', '')),
            'spouse_name' => trim(post('spouse_name', '')),
            'grandfather_name' => trim(post('grandfather_name', '')),
            'gender' => post('gender', ''),
            'date_of_birth' => post('date_of_birth', ''),
            'citizenship_no' => trim(post('citizenship_no', '')),
            'citizenship_issued_district' => trim(post('citizenship_issued_district', '')),
            'phone' => trim(post('phone', '')),
            'mobile' => trim(post('mobile', '')),
            'email' => trim(post('email', '')),
            'permanent_province' => post('permanent_province', ''),
            'permanent_district' => trim(post('permanent_district', '')),
            'permanent_municipality' => trim(post('permanent_municipality', '')),
            'permanent_ward' => post('permanent_ward', ''),
            'permanent_tole' => trim(post('permanent_tole', '')),
            'temporary_address' => trim(post('temporary_address', '')),
            'ward_no' => post('ward_no', ''),
            'tole' => trim(post('tole', '')),
            'house_no' => trim(post('house_no', '')),
            'street' => trim(post('street', '')),
            'landmark' => trim(post('landmark', '')),
            'category_id' => (int) post('category_id', 0),
            'connection_type' => post('connection_type', ''),
            'property_type' => post('property_type', ''),
            'family_members' => (int) post('family_members', 0),
            'tap_connection_date' => post('tap_connection_date', ''),
            'connection_size' => post('connection_size', ''),
            'pipe_size' => post('pipe_size', ''),
            'latitude' => preg_match('/^-?\d{1,3}\.\d+$/', post('latitude', '')) ? post('latitude', '') : null,
            'longitude' => preg_match('/^-?\d{1,3}\.\d+$/', post('longitude', '')) ? post('longitude', '') : null,
            'status' => 'active',
        ];

        $rules = [
            'full_name' => 'required|min:2|max:150',
            'consumer_no' => 'required|unique:consumers,consumer_no',
            'gender' => 'in:male,female,other',
            'date_of_birth' => 'date',
            'email' => 'email',
            'mobile' => 'required|mobile',
            'phone' => 'phone',
            'ward_no' => 'required|numeric',
            'category_id' => 'required|numeric',
            'connection_type' => 'required|in:household,commercial,institutional',
            'property_type' => 'in:own,rental,government,other',
            'family_members' => 'numeric',
            'tap_connection_date' => 'date',
        ];
        $v = validator($data, $rules);

        if ($v->fails()) {
            $errors = $v->errors();
            $_SESSION['old'] = $data;
            alert_error(implode('<br>', $v->allErrors()));
        } else {
            $photo = '';
            if (!empty($_FILES['photo']['name'])) {
                $uploadDir = UPLOADS_PATH . 'consumers/';
                $photo = upload_file($_FILES['photo'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif']);
                if ($photo === false) {
                    $_SESSION['old'] = $data;
                    alert_error('Photo upload failed. Allowed: jpg, jpeg, png, gif (max 5MB).');
                    require_once __DIR__ . '/../includes/footer.php';
                    exit;
                }
            }

            $consumerId = db()->insert('consumers', [
                'consumer_no' => $data['consumer_no'],
                'full_name' => $data['full_name'],
                'father_name' => $data['father_name'] ?: null,
                'mother_name' => $data['mother_name'] ?: null,
                'spouse_name' => $data['spouse_name'] ?: null,
                'grandfather_name' => $data['grandfather_name'] ?: null,
                'gender' => $data['gender'] ?: null,
                'date_of_birth' => $data['date_of_birth'] ?: null,
                'citizenship_no' => $data['citizenship_no'] ?: null,
                'citizenship_issued_district' => $data['citizenship_issued_district'] ?: null,
                'phone' => $data['phone'] ?: null,
                'mobile' => $data['mobile'] ?: null,
                'email' => $data['email'] ?: null,
                'permanent_province' => $data['permanent_province'] ?: null,
                'permanent_district' => $data['permanent_district'] ?: null,
                'permanent_municipality' => $data['permanent_municipality'] ?: null,
                'permanent_ward' => $data['permanent_ward'] ?: null,
                'permanent_tole' => $data['permanent_tole'] ?: null,
                'temporary_address' => $data['temporary_address'] ?: null,
                'ward_no' => (int)$data['ward_no'],
                'tole' => $data['tole'] ?: null,
                'house_no' => $data['house_no'] ?: null,
                'street' => $data['street'] ?: null,
                'landmark' => $data['landmark'] ?: null,
                'category_id' => $data['category_id'] ?: null,
                'connection_type' => $data['connection_type'],
                'property_type' => $data['property_type'] ?: null,
                'family_members' => $data['family_members'] ?: 0,
                'tap_connection_date' => $data['tap_connection_date'] ?: null,
                'connection_size' => $data['connection_size'] ?: null,
                'pipe_size' => $data['pipe_size'] ?: null,
                'latitude' => $data['latitude'] ?: null,
                'longitude' => $data['longitude'] ?: null,
                'photo' => $photo ?: null,
                'registration_date' => date('Y-m-d'),
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);

            if ($consumerId) {
                db()->insert('consumer_history', [
                    'consumer_id' => $consumerId,
                    'action' => 'created',
                    'old_value' => null,
                    'new_value' => json_encode($data),
                    'changed_by' => Auth::id(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                log_activity(Auth::id(), 'create', 'consumers', "Registered consumer: {$data['consumer_no']} - {$data['full_name']}", ['consumer_id' => $consumerId]);
                alert_success('Consumer registered successfully.');
                redirect(ADMIN_URL . 'consumers/view.php?id=' . $consumerId);
            } else {
                alert_error('Failed to register consumer. Please try again.');
            }
        }
    }
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Register Consumer</h4>
            <p>Complete consumer registration form</p>
        </div>
        <a href="<?= ADMIN_URL ?>consumers/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Consumers
        </a>
    </div>
</div>

<?= display_alert() ?>

<form method="POST" action="" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Personal Information -->
            <div class="card">
                <div class="card-header">
                    <h5>Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Consumer No <span class="required">*</span></label>
                                <input type="text" name="consumer_no" class="form-control" value="<?= escape(old('consumer_no', $nextNo)) ?>" required readonly>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" name="full_name" class="form-control" value="<?= escape(old('full_name')) ?>" required maxlength="150">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Father's Name</label>
                                <input type="text" name="father_name" class="form-control" value="<?= escape(old('father_name')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Mother's Name</label>
                                <input type="text" name="mother_name" class="form-control" value="<?= escape(old('mother_name')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Spouse Name</label>
                                <input type="text" name="spouse_name" class="form-control" value="<?= escape(old('spouse_name')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Grandfather's Name</label>
                                <input type="text" name="grandfather_name" class="form-control" value="<?= escape(old('grandfather_name')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <?php foreach ($genders as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('gender') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" value="<?= escape(old('date_of_birth')) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Citizenship No</label>
                                <input type="text" name="citizenship_no" class="form-control" value="<?= escape(old('citizenship_no')) ?>" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Citizenship Issued District</label>
                                <select name="citizenship_issued_district" class="form-select">
                                    <option value="">Select District</option>
                                    <?php foreach ($districts as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('citizenship_issued_district') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card">
                <div class="card-header">
                    <h5>Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= escape(old('phone')) ?>" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Mobile <span class="required">*</span></label>
                                <input type="text" name="mobile" class="form-control" value="<?= escape(old('mobile')) ?>" required maxlength="10" placeholder="98XXXXXXXX">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= escape(old('email')) ?>" maxlength="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permanent Address -->
            <div class="card">
                <div class="card-header">
                    <h5>Permanent Address</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Province</label>
                                <select name="permanent_province" class="form-select">
                                    <option value="">Select Province</option>
                                    <?php foreach ($provinces as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('permanent_province') == $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">District</label>
                                <select name="permanent_district" class="form-select">
                                    <option value="">Select District</option>
                                    <?php foreach ($districts as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('permanent_district') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Municipality</label>
                                <select name="permanent_municipality" class="form-select">
                                    <option value="">Select Municipality</option>
                                    <?php foreach ($municipalities as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('permanent_municipality') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Ward No</label>
                                <select name="permanent_ward" class="form-select">
                                    <option value="">Select Ward</option>
                                    <?php foreach ($wards as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('permanent_ward') == $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="form-label">Tole</label>
                                <input type="text" name="permanent_tole" class="form-control" value="<?= escape(old('permanent_tole')) ?>" maxlength="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Address & Connection -->
            <div class="card">
                <div class="card-header">
                    <h5>Current Address & Connection Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Temporary Address</label>
                                <textarea name="temporary_address" class="form-control" rows="2" maxlength="500"><?= escape(old('temporary_address')) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Ward No <span class="required">*</span></label>
                                <select name="ward_no" class="form-select" required>
                                    <option value="">Select Ward</option>
                                    <?php foreach ($wards as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('ward_no') == $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Tole</label>
                                <input type="text" name="tole" class="form-control" value="<?= escape(old('tole')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">House No</label>
                                <input type="text" name="house_no" class="form-control" value="<?= escape(old('house_no')) ?>" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Street</label>
                                <input type="text" name="street" class="form-control" value="<?= escape(old('street')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Landmark</label>
                                <input type="text" name="landmark" class="form-control" value="<?= escape(old('landmark')) ?>" maxlength="100">
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
                                <label class="form-label">Connection Type <span class="required">*</span></label>
                                <select name="connection_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($connectionTypes as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('connection_type') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Property Type</label>
                                <select name="property_type" class="form-select">
                                    <option value="">Select Property Type</option>
                                    <?php foreach ($propertyTypes as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('property_type') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Family Members</label>
                                <input type="number" name="family_members" class="form-control" value="<?= escape(old('family_members')) ?>" min="0" max="50">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Tap Connection Date</label>
                                <input type="date" name="tap_connection_date" class="form-control" value="<?= escape(old('tap_connection_date')) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Connection Size</label>
                                <select name="connection_size" class="form-select">
                                    <option value="">Select Size</option>
                                    <?php foreach ($connectionSizes as $s): ?>
                                    <option value="<?= $s ?>" <?= old('connection_size') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Pipe Size</label>
                                <select name="pipe_size" class="form-select">
                                    <option value="">Select Pipe Size</option>
                                    <?php foreach ($pipeSizes as $s): ?>
                                    <option value="<?= $s ?>" <?= old('pipe_size') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GPS Coordinates -->
            <div class="card">
                <div class="card-header">
                    <h5>GPS Location</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" id="latitude" class="form-control" value="<?= escape(old('latitude')) ?>" readonly placeholder="Click on map to select">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" id="longitude" class="form-control" value="<?= escape(old('longitude')) ?>" readonly placeholder="Click on map to select">
                            </div>
                        </div>
                    </div>
                    <div id="locationMap" style="height:350px;border-radius:8px;border:1.5px solid var(--border-color);z-index:1;"></div>
                    <p class="form-text mt-2">Click on the map to set the consumer's location. Drag the marker to refine.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Photo Upload -->
            <div class="card">
                <div class="card-header">
                    <h5>Photo</h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <div class="photo-upload-preview mb-3" id="photoPreview" style="width:150px;height:150px;border-radius:50%;border:2px dashed var(--border-color);display:flex;align-items:center;justify-content:center;margin:0 auto;overflow:hidden;background:#f8f9fa;">
                            <i class="fas fa-user fa-3x" style="color:#ccc;"></i>
                        </div>
                        <input type="file" name="photo" id="photoInput" class="form-control" accept="image/jpeg,image/png,image/gif">
                        <div class="form-text">JPG, PNG or GIF. Max 5MB.</div>
                    </div>
                </div>
            </div>

            <!-- Guidelines -->
            <div class="card">
                <div class="card-header">
                    <h5>Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul style="font-size:13px;color:var(--text-muted);padding-left:16px;margin:0;">
                        <li class="mb-2">Fields marked with <span class="required">*</span> are required.</li>
                        <li class="mb-2">Mobile must be a valid Nepali number (98, 97, or 96 prefix).</li>
                        <li class="mb-2">Consumer number is auto-generated.</li>
                        <li class="mb-2">Click on the map to record GPS coordinates.</li>
                        <li class="mb-2">Upload a recent passport-size photo.</li>
                    </ul>
                </div>
            </div>

            <!-- Submit -->
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-save"></i> Register Consumer
                    </button>
                    <a href="<?= ADMIN_URL ?>consumers/index.php" class="btn btn-outline-secondary w-100">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.photo-upload-preview img { width:100%;height:100%;object-fit:cover; }
</style>

<?php
$extraJs = <<<'JS'
<script>
var map, marker;

function initMap() {
    var defaultLat = parseFloat(document.getElementById('latitude').value) || 27.7172;
    var defaultLng = parseFloat(document.getElementById('longitude').value) || 85.3240;

    map = L.map('locationMap').setView([defaultLat, defaultLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    if (document.getElementById('latitude').value && document.getElementById('longitude').value) {
        marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
        marker.on('dragend', function(e) {
            var latlng = marker.getLatLng();
            document.getElementById('latitude').value = latlng.lat.toFixed(6);
            document.getElementById('longitude').value = latlng.lng.toFixed(6);
        });
    }

    map.on('click', function(e) {
        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function(ev) {
                var latlng = marker.getLatLng();
                document.getElementById('latitude').value = latlng.lat.toFixed(6);
                document.getElementById('longitude').value = latlng.lng.toFixed(6);
            });
        }
        document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initMap();

    document.getElementById('photoInput').addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(ev) {
                var preview = document.getElementById('photoPreview');
                preview.innerHTML = '<img src="' + ev.target.result + '" alt="Photo">';
            };
            reader.readAsDataURL(file);
        }
    });

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
});
</script>
JS;
unset($_SESSION['old']);
require_once __DIR__ . '/../includes/footer.php'; ?>
