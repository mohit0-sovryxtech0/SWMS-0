<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Register Employee';
$breadcrumbs = [
    ['label' => 'Employees', 'url' => ADMIN_URL . 'employees/index.php'],
    ['label' => 'Register Employee']
];
RBAC::requirePermission('employees.create');

require_once __DIR__ . '/../includes/header.php';

$departments = db()->fetchAll("SELECT id, name FROM departments WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");
$designations = db()->fetchAll("SELECT id, name, department_id FROM designations WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");
$roles = db()->fetchAll("SELECT id, name FROM roles WHERE deleted_at IS NULL ORDER BY name");
$genders = get_gender_options();
$maritalStatuses = ['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed'];
$employmentTypes = ['permanent' => 'Permanent', 'temporary' => 'Temporary', 'contract' => 'Contract', 'part_time' => 'Part Time', 'volunteer' => 'Volunteer'];

$lastCode = db()->fetchColumn("SELECT employee_code FROM employees WHERE employee_code LIKE 'EMP-%' ORDER BY id DESC LIMIT 1");
$nextNum = $lastCode ? (int)substr($lastCode, 4) + 1 : 1;
$nextCode = 'EMP-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

$errors = [];

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token. Please try again.');
    } else {
        $data = [
            'employee_code' => trim(post('employee_code', $nextCode)),
            'full_name' => trim(post('full_name', '')),
            'father_name' => trim(post('father_name', '')),
            'mother_name' => trim(post('mother_name', '')),
            'gender' => post('gender', ''),
            'date_of_birth' => post('date_of_birth', ''),
            'marital_status' => post('marital_status', ''),
            'phone' => trim(post('phone', '')),
            'mobile' => trim(post('mobile', '')),
            'email' => trim(post('email', '')),
            'department_id' => (int) post('department_id', 0),
            'designation_id' => (int) post('designation_id', 0),
            'joining_date' => post('joining_date', ''),
            'employment_type' => post('employment_type', ''),
            'salary' => post('salary', 0),
            'bank_name' => trim(post('bank_name', '')),
            'bank_account_no' => trim(post('bank_account_no', '')),
            'pan_no' => trim(post('pan_no', '')),
            'citizenship_no' => trim(post('citizenship_no', '')),
            'permanent_address' => trim(post('permanent_address', '')),
            'temporary_address' => trim(post('temporary_address', '')),
            'emergency_contact_name' => trim(post('emergency_contact_name', '')),
            'emergency_contact_phone' => trim(post('emergency_contact_phone', '')),
            'education' => trim(post('education', '')),
            'experience' => trim(post('experience', '')),
            'skills' => trim(post('skills', '')),
            'status' => 'active',
            'create_user' => post('create_user', 0),
            'role_id' => (int) post('role_id', 0),
            'username' => trim(post('username', '')),
            'password' => post('password', ''),
        ];

        $v = validator($data, [
            'full_name' => 'required|min:2|max:150',
            'employee_code' => 'required|unique:employees,employee_code',
            'gender' => 'in:male,female,other',
            'marital_status' => 'in:single,married,divorced,widowed',
            'date_of_birth' => 'date',
            'email' => 'email',
            'mobile' => 'required|mobile',
            'phone' => 'phone',
            'department_id' => 'required|numeric',
            'designation_id' => 'required|numeric',
            'joining_date' => 'date',
            'employment_type' => 'in:permanent,temporary,contract,part_time,volunteer',
            'salary' => 'numeric',
            'emergency_contact_name' => 'max:200',
            'emergency_contact_phone' => 'phone',
        ]);

        if ($v->fails()) {
            $errors = $v->errors();
            $_SESSION['old'] = $data;
            alert_error(implode('<br>', $v->allErrors()));
        } else {
            try {
                db()->beginTransaction();

                $photo = '';
                if (!empty($_FILES['photo']['name'])) {
                    $uploadDir = UPLOADS_PATH . 'employees/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $photo = upload_file($_FILES['photo'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif']);
                    if ($photo === false) {
                        throw new Exception('Photo upload failed. Allowed: jpg, jpeg, png, gif (max 5MB).');
                    }
                }

                $employeeId = db()->insert('employees', [
                    'employee_code' => $data['employee_code'],
                    'full_name' => $data['full_name'],
                    'father_name' => $data['father_name'] ?: null,
                    'mother_name' => $data['mother_name'] ?: null,
                    'gender' => $data['gender'] ?: null,
                    'date_of_birth' => $data['date_of_birth'] ?: null,
                    'marital_status' => $data['marital_status'] ?: null,
                    'phone' => $data['phone'] ?: null,
                    'mobile' => $data['mobile'] ?: null,
                    'email' => $data['email'] ?: null,
                    'department_id' => $data['department_id'] ?: null,
                    'designation_id' => $data['designation_id'] ?: null,
                    'joining_date' => $data['joining_date'] ?: null,
                    'employment_type' => $data['employment_type'] ?: null,
                    'salary' => $data['salary'] ?: 0,
                    'bank_name' => $data['bank_name'] ?: null,
                    'bank_account_no' => $data['bank_account_no'] ?: null,
                    'pan_no' => $data['pan_no'] ?: null,
                    'citizenship_no' => $data['citizenship_no'] ?: null,
                    'permanent_address' => $data['permanent_address'] ?: null,
                    'temporary_address' => $data['temporary_address'] ?: null,
                    'photo' => $photo ?: null,
                    'emergency_contact_name' => $data['emergency_contact_name'] ?: null,
                    'emergency_contact_phone' => $data['emergency_contact_phone'] ?: null,
                    'education' => $data['education'] ?: null,
                    'experience' => $data['experience'] ?: null,
                    'skills' => $data['skills'] ?: null,
                    'status' => 'active',
                    'created_by' => Auth::id(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                if (!$employeeId) {
                    throw new Exception('Failed to create employee record.');
                }

                if ($data['create_user'] && $data['role_id'] && $data['username'] && $data['password']) {
                    $existing = db()->fetchColumn("SELECT COUNT(*) FROM users WHERE username = ? AND deleted_at IS NULL", [$data['username']]);
                    if ($existing > 0) {
                        throw new Exception('Username already exists.');
                    }
                    $userData = [
                        'role_id' => $data['role_id'],
                        'name' => $data['full_name'],
                        'email' => $data['email'] ?: null,
                        'username' => $data['username'],
                        'password' => Security::hashPassword($data['password']),
                        'phone' => $data['mobile'] ?: null,
                        'gender' => $data['gender'] ?: null,
                        'designation' => db()->fetchColumn("SELECT name FROM designations WHERE id = ?", [$data['designation_id']]) ?: null,
                        'department' => db()->fetchColumn("SELECT name FROM departments WHERE id = ?", [$data['department_id']]) ?: null,
                        'status' => 'active',
                        'created_by' => Auth::id(),
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                    $userId = db()->insert('users', $userData);
                    if (!$userId) {
                        throw new Exception('Failed to create user account.');
                    }
                    db()->update('employees', ['user_id' => $userId], 'id = :id', ['id' => $employeeId]);
                }

                db()->commit();

                log_activity(Auth::id(), 'create', 'employees', "Registered employee: {$data['employee_code']} - {$data['full_name']}", ['employee_id' => $employeeId]);
                alert_success('Employee registered successfully.');
                redirect(ADMIN_URL . 'employees/view.php?id=' . $employeeId);

            } catch (Exception $e) {
                db()->rollback();
                if (!empty($photo)) {
                    delete_file(UPLOADS_PATH . 'employees/' . $photo);
                }
                $_SESSION['old'] = $data;
                alert_error($e->getMessage());
            }
        }
    }
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Register Employee</h4>
            <p>Complete employee registration form</p>
        </div>
        <a href="<?= ADMIN_URL ?>employees/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Employees
        </a>
    </div>
</div>

<?= display_alert() ?>

<form method="POST" action="" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5>Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Employee Code <span class="required">*</span></label>
                                <input type="text" name="employee_code" class="form-control" value="<?= escape(old('employee_code', $nextCode)) ?>" required readonly>
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
                                <input type="text" name="father_name" class="form-control" value="<?= escape(old('father_name')) ?>" maxlength="200">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Mother's Name</label>
                                <input type="text" name="mother_name" class="form-control" value="<?= escape(old('mother_name')) ?>" maxlength="200">
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
                                <label class="form-label">Marital Status</label>
                                <select name="marital_status" class="form-select">
                                    <option value="">Select Status</option>
                                    <?php foreach ($maritalStatuses as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('marital_status') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                                <input type="email" name="email" class="form-control" value="<?= escape(old('email')) ?>" maxlength="200">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Employment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Department <span class="required">*</span></label>
                                <select name="department_id" id="department_id" class="form-select" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= old('department_id') == $d['id'] ? 'selected' : '' ?>><?= escape($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Designation <span class="required">*</span></label>
                                <select name="designation_id" id="designation_id" class="form-select" required>
                                    <option value="">Select Designation</option>
                                    <?php foreach ($designations as $d): ?>
                                    <option value="<?= $d['id'] ?>" data-dept="<?= $d['department_id'] ?>" <?= old('designation_id') == $d['id'] ? 'selected' : '' ?>><?= escape($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Joining Date</label>
                                <input type="date" name="joining_date" class="form-control" value="<?= escape(old('joining_date')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Employment Type</label>
                                <select name="employment_type" class="form-select">
                                    <option value="">Select Type</option>
                                    <?php foreach ($employmentTypes as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('employment_type') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Financial Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Salary (NRs.)</label>
                                <input type="number" step="0.01" name="salary" class="form-control" value="<?= escape(old('salary', 0)) ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" value="<?= escape(old('bank_name')) ?>" maxlength="200">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Bank Account No</label>
                                <input type="text" name="bank_account_no" class="form-control" value="<?= escape(old('bank_account_no')) ?>" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">PAN No</label>
                                <input type="text" name="pan_no" class="form-control" value="<?= escape(old('pan_no')) ?>" maxlength="50">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Documents & Emergency</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Citizenship No</label>
                                <input type="text" name="citizenship_no" class="form-control" value="<?= escape(old('citizenship_no')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" class="form-control" value="<?= escape(old('emergency_contact_name')) ?>" maxlength="200">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="text" name="emergency_contact_phone" class="form-control" value="<?= escape(old('emergency_contact_phone')) ?>" maxlength="20">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Address</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Permanent Address</label>
                                <textarea name="permanent_address" class="form-control" rows="2" maxlength="500"><?= escape(old('permanent_address')) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Temporary Address</label>
                                <textarea name="temporary_address" class="form-control" rows="2" maxlength="500"><?= escape(old('temporary_address')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Education, Experience & Skills</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Education</label>
                                <textarea name="education" class="form-control" rows="4" maxlength="1000" placeholder="Degree, Institution, Year"><?= escape(old('education')) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Experience</label>
                                <textarea name="experience" class="form-control" rows="4" maxlength="1000" placeholder="Organization, Role, Duration"><?= escape(old('experience')) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Skills</label>
                                <textarea name="skills" class="form-control" rows="4" maxlength="1000" placeholder="Comma separated skills"><?= escape(old('skills')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Create User Account</h5>
                </div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="create_user" id="create_user" class="form-check-input" value="1" <?= old('create_user') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="create_user">Create a user account for this employee</label>
                    </div>
                    <div id="userAccountFields" class="row g-3" style="<?= old('create_user') ? '' : 'display:none;' ?>">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Username <span class="required">*</span></label>
                                <input type="text" name="username" class="form-control" value="<?= escape(old('username')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Password <span class="required">*</span></label>
                                <input type="password" name="password" class="form-control" minlength="6">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Role <span class="required">*</span></label>
                                <select name="role_id" class="form-select">
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= old('role_id') == $r['id'] ? 'selected' : '' ?>><?= escape($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
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

            <div class="card">
                <div class="card-header">
                    <h5>Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul style="font-size:13px;color:var(--text-muted);padding-left:16px;margin:0;">
                        <li class="mb-2">Fields marked with <span class="required">*</span> are required.</li>
                        <li class="mb-2">Employee code is auto-generated.</li>
                        <li class="mb-2">Mobile must be a valid Nepali number (98, 97, or 96 prefix).</li>
                        <li class="mb-2">Select department first, then designation.</li>
                        <li class="mb-2">Check "Create User Account" to enable login credentials.</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-save"></i> Register Employee
                    </button>
                    <a href="<?= ADMIN_URL ?>employees/index.php" class="btn btn-outline-secondary w-100">Cancel</a>
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
document.addEventListener('DOMContentLoaded', function() {
    var createUserCheck = document.getElementById('create_user');
    var userFields = document.getElementById('userAccountFields');
    createUserCheck.addEventListener('change', function() {
        userFields.style.display = this.checked ? '' : 'none';
    });

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

    var deptSelect = document.getElementById('department_id');
    var desgSelect = document.getElementById('designation_id');
    function filterDesignations() {
        var deptId = deptSelect.value;
        var options = desgSelect.querySelectorAll('option');
        var hasVisible = false;
        options.forEach(function(opt) {
            if (opt.value === '') {
                opt.style.display = '';
                return;
            }
            var dept = opt.getAttribute('data-dept');
            if (!deptId || dept === deptId) {
                opt.style.display = '';
                hasVisible = true;
            } else {
                opt.style.display = 'none';
            }
        });
    }
    deptSelect.addEventListener('change', filterDesignations);
    filterDesignations();
});
</script>
JS;
unset($_SESSION['old']);
require_once __DIR__ . '/../includes/footer.php'; ?>
