<?php
require_once __DIR__ . '/../../includes/config.php';

RBAC::requirePermission('consumers.edit');

$consumerId = (int) get('id', 0);
$consumer = db()->fetchOne("SELECT * FROM consumers WHERE id = ? AND deleted_at IS NULL", [$consumerId]);

if (!$consumer) {
    alert_error('Consumer not found.');
    redirect(ADMIN_URL . 'consumers/index.php');
}

$pageTitle = 'Edit Consumer';
$breadcrumbs = [
    ['label' => 'Consumer Management', 'url' => ADMIN_URL . 'consumers/index.php'],
    ['label' => 'Edit Consumer']
];
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
$statuses = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'pending' => 'Pending'];

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token. Please try again.');
    } else {
        $data = [
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
            'status' => post('status', 'active'),
        ];

        $rules = [
            'full_name' => 'required|min:2|max:150',
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
            'status' => 'in:active,inactive,suspended,pending',
        ];
        $v = validator($data, $rules);

        if ($v->fails()) {
            alert_error(implode('<br>', $v->allErrors()));
        } else {
            $oldData = $consumer;
            $photo = $consumer['photo'];

            if (!empty($_FILES['photo']['name'])) {
                $uploadDir = UPLOADS_PATH . 'consumers/';
                $newPhoto = upload_file($_FILES['photo'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif']);
                if ($newPhoto === false) {
                    alert_error('Photo upload failed. Allowed: jpg, jpeg, png, gif (max 5MB).');
                    require_once __DIR__ . '/../includes/footer.php';
                    exit;
                }
                if ($consumer['photo']) {
                    delete_file($uploadDir . $consumer['photo']);
                }
                $photo = $newPhoto;
            }

            $updateData = [
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
                'photo' => $photo,
                'status' => $data['status'],
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => Auth::id(),
            ];

            db()->update('consumers', $updateData, 'id = :id', ['id' => $consumerId]);

            db()->insert('consumer_history', [
                'consumer_id' => $consumerId,
                'action' => 'updated',
                'old_value' => json_encode($oldData),
                'new_value' => json_encode($updateData),
                'changed_by' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            log_activity(Auth::id(), 'update', 'consumers', "Updated consumer: {$consumer['consumer_no']} - {$data['full_name']}", ['consumer_id' => $consumerId]);
            alert_success('Consumer updated successfully.');
            redirect(ADMIN_URL . 'consumers/edit.php?id=' . $consumerId);
        }
    }
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Edit Consumer</h4>
            <p><?= escape($consumer['consumer_no']) ?> &mdash; <?= escape($consumer['full_name']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>consumers/create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add New</a>
            <a href="<?= ADMIN_URL ?>consumers/view.php?id=<?= $consumerId ?>" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> View</a>
            <a href="<?= ADMIN_URL ?>consumers/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<form method="POST" action="" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5>Personal Information</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Consumer No</label>
                                <input type="text" class="form-control" value="<?= escape($consumer['consumer_no']) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" name="full_name" class="form-control" value="<?= escape($consumer['full_name']) ?>" required maxlength="150">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Father's Name</label>
                                <input type="text" name="father_name" class="form-control" value="<?= escape($consumer['father_name']) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Mother's Name</label>
                                <input type="text" name="mother_name" class="form-control" value="<?= escape($consumer['mother_name']) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Spouse Name</label>
                                <input type="text" name="spouse_name" class="form-control" value="<?= escape($consumer['spouse_name']) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Grandfather's Name</label>
                                <input type="text" name="grandfather_name" class="form-control" value="<?= escape($consumer['grandfather_name']) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($genders as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['gender'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" value="<?= escape($consumer['date_of_birth']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Citizenship No</label>
                                <input type="text" name="citizenship_no" class="form-control" value="<?= escape($consumer['citizenship_no']) ?>" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Citizenship Issued District</label>
                                <select name="citizenship_issued_district" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($districts as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['citizenship_issued_district'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>Contact Information</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= escape($consumer['phone']) ?>" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Mobile <span class="required">*</span></label>
                                <input type="text" name="mobile" class="form-control" value="<?= escape($consumer['mobile']) ?>" required maxlength="10">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= escape($consumer['email']) ?>" maxlength="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>Permanent Address</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Province</label>
                                <select name="permanent_province" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($provinces as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['permanent_province'] == $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">District</label>
                                <select name="permanent_district" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($districts as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['permanent_district'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Municipality</label>
                                <select name="permanent_municipality" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($municipalities as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['permanent_municipality'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Ward No</label>
                                <select name="permanent_ward" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($wards as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['permanent_ward'] == $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="form-label">Tole</label>
                                <input type="text" name="permanent_tole" class="form-control" value="<?= escape($consumer['permanent_tole']) ?>" maxlength="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>Current Address & Connection Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Temporary Address</label>
                                <textarea name="temporary_address" class="form-control" rows="2" maxlength="500"><?= escape($consumer['temporary_address']) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Ward No <span class="required">*</span></label>
                                <select name="ward_no" class="form-select" required>
                                    <option value="">Select</option>
                                    <?php foreach ($wards as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['ward_no'] == $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Tole</label>
                                <input type="text" name="tole" class="form-control" value="<?= escape($consumer['tole']) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">House No</label>
                                <input type="text" name="house_no" class="form-control" value="<?= escape($consumer['house_no']) ?>" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Street</label>
                                <input type="text" name="street" class="form-control" value="<?= escape($consumer['street']) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Landmark</label>
                                <input type="text" name="landmark" class="form-control" value="<?= escape($consumer['landmark']) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Category <span class="required">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $consumer['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Connection Type <span class="required">*</span></label>
                                <select name="connection_type" class="form-select" required>
                                    <option value="">Select</option>
                                    <?php foreach ($connectionTypes as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['connection_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Property Type</label>
                                <select name="property_type" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($propertyTypes as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['property_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Family Members</label>
                                <input type="number" name="family_members" class="form-control" value="<?= (int)$consumer['family_members'] ?: '' ?>" min="0" max="50">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Tap Connection Date</label>
                                <input type="date" name="tap_connection_date" class="form-control" value="<?= escape($consumer['tap_connection_date']) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Connection Size</label>
                                <select name="connection_size" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($connectionSizes as $s): ?>
                                    <option value="<?= $s ?>" <?= $consumer['connection_size'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Pipe Size</label>
                                <select name="pipe_size" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($pipeSizes as $s): ?>
                                    <option value="<?= $s ?>" <?= $consumer['pipe_size'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach ($statuses as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $consumer['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>GPS Location</h5></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" id="latitude" class="form-control" value="<?= escape($consumer['latitude']) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" id="longitude" class="form-control" value="<?= escape($consumer['longitude']) ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div id="locationMap" style="height:300px;border-radius:8px;border:1.5px solid var(--border-color);z-index:1;"></div>
                    <p class="form-text mt-2">Click on the map or drag the marker to update location.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Consumer</button>
                    <a href="<?= ADMIN_URL ?>consumers/index.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5>Photo</h5></div>
                <div class="card-body">
                    <div class="form-group">
                        <div class="photo-upload-preview mb-3" id="photoPreview" style="width:150px;height:150px;border-radius:50%;border:2px dashed var(--border-color);display:flex;align-items:center;justify-content:center;margin:0 auto;overflow:hidden;background:#f8f9fa;">
                            <?php if ($consumer['photo']): ?>
                            <img src="<?= UPLOAD_URL ?>consumers/<?= escape($consumer['photo']) ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                            <i class="fas fa-user fa-3x" style="color:#ccc;"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="photo" id="photoInput" class="form-control" accept="image/jpeg,image/png,image/gif">
                        <div class="form-text">JPG, PNG or GIF. Max 5MB. Leave empty to keep current.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>Account Info</h5></div>
                <div class="card-body">
                    <div style="font-size:13px;">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Consumer No</span>
                            <span><strong><?= escape($consumer['consumer_no']) ?></strong></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Created</span>
                            <span><?= format_datetime($consumer['created_at']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Updated</span>
                            <span><?= $consumer['updated_at'] ? format_datetime($consumer['updated_at']) : '-' ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Status</span>
                            <span><?= get_status_badge($consumer['status']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (RBAC::can('consumers.delete')): ?>
            <div class="card border-danger">
                <div class="card-header text-danger"><h5>Danger Zone</h5></div>
                <div class="card-body">
                    <p class="small text-muted">Deleting this consumer will archive all associated records.</p>
                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="confirmDelete(<?= $consumer['id'] ?>, '<?= escape(addslashes($consumer['full_name'])) ?>')">
                        <i class="fas fa-trash"></i> Delete Consumer
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<style>
.photo-upload-preview img { width:100%;height:100%;object-fit:cover; }
</style>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= ADMIN_URL ?>consumers/delete.php">
                <?= csrf_field() ?>
                <input type="hidden" name="consumer_id" id="deleteConsumerId">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete consumer <strong id="deleteConsumerName"></strong>?</p>
                    <p class="text-muted small mb-0">This action will soft-delete the consumer record.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
var map, marker;

function initMap() {
    var lat = parseFloat(document.getElementById('latitude').value) || 27.7172;
    var lng = parseFloat(document.getElementById('longitude').value) || 85.3240;

    map = L.map('locationMap').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    marker = L.marker([lat, lng], { draggable: true }).addTo(map);

    marker.on('dragend', function(e) {
        var ll = marker.getLatLng();
        document.getElementById('latitude').value = ll.lat.toFixed(6);
        document.getElementById('longitude').value = ll.lng.toFixed(6);
    });

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
    });
}

function confirmDelete(id, name) {
    document.getElementById('deleteConsumerId').value = id;
    document.getElementById('deleteConsumerName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    initMap();

    document.getElementById('photoInput').addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('photoPreview').innerHTML = '<img src="' + ev.target.result + '" alt="Photo">';
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
JS;
require_once __DIR__ . '/../includes/footer.php'; ?>
