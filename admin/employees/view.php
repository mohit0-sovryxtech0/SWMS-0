<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Employee Profile';
$breadcrumbs = [
    ['label' => 'Employees', 'url' => ADMIN_URL . 'employees/index.php'],
    ['label' => 'Employee Profile']
];
RBAC::requirePermission('employees.view');

require_once __DIR__ . '/../includes/header.php';

$employeeId = (int) get('id', 0);
$employee = db()->fetchOne(
    "SELECT e.*, d.name as department_name, dg.name as designation_name, u.name as created_by_name, u2.name as user_name
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     LEFT JOIN designations dg ON e.designation_id = dg.id
     LEFT JOIN users u ON e.created_by = u.id
     LEFT JOIN users u2 ON e.user_id = u2.id
     WHERE e.id = ? AND e.deleted_at IS NULL",
    [$employeeId]
);

if (!$employee) {
    alert_error('Employee not found.');
    redirect(ADMIN_URL . 'employees/index.php');
}

$attendance = db()->fetchAll(
    "SELECT date, status FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 30",
    [$employeeId]
);
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Employee Profile</h4>
            <p><?= escape($employee['employee_code']) ?> &mdash; <?= escape($employee['full_name']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <?php if (RBAC::can('employees.edit')): ?>
            <a href="<?= ADMIN_URL ?>employees/edit.php?id=<?= $employeeId ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
            <?php endif; ?>
            <a href="<?= ADMIN_URL ?>employees/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3">
            <div style="width:64px;height:64px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:26px;flex-shrink:0;overflow:hidden;">
                <?php if ($employee['photo']): ?>
                <img src="<?= UPLOAD_URL ?>employees/<?= escape($employee['photo']) ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                <?= strtoupper(substr($employee['full_name'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <h5 class="mb-1"><?= escape($employee['full_name']) ?></h5>
                <div class="d-flex gap-3 flex-wrap">
                    <span><i class="fas fa-fingerprint text-muted me-1"></i> <?= escape($employee['employee_code']) ?></span>
                    <span><i class="fas fa-building text-muted me-1"></i> <?= escape($employee['department_name'] ?? '-') ?></span>
                    <span><i class="fas fa-tag text-muted me-1"></i> <?= escape($employee['designation_name'] ?? '-') ?></span>
                    <span><i class="fas fa-phone text-muted me-1"></i> <?= escape($employee['mobile'] ?: '-') ?></span>
                    <span><?= get_status_badge($employee['status']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs" id="employeeTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button"><i class="fas fa-user me-1"></i> Profile</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button"><i class="fas fa-briefcase me-1"></i> Employment</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial" type="button"><i class="fas fa-money-bill me-1"></i> Financial</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button"><i class="fas fa-file-alt me-1"></i> Documents</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button"><i class="fas fa-clipboard-check me-1"></i> Attendance</button>
    </li>
</ul>

<div class="tab-content" id="employeeTabContent">
    <div class="tab-pane fade show active" id="profile" role="tabpanel">
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Personal Information</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Full Name</td><td><strong><?= escape($employee['full_name']) ?></strong></td></tr>
                            <tr><td style="color:var(--text-muted);">Father's Name</td><td><?= escape($employee['father_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Mother's Name</td><td><?= escape($employee['mother_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Gender</td><td><?= $employee['gender'] ? ucfirst($employee['gender']) : '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Date of Birth</td><td><?= $employee['date_of_birth'] ? format_date($employee['date_of_birth']) : '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Marital Status</td><td><?= $employee['marital_status'] ? ucfirst(str_replace('_', ' ', $employee['marital_status'])) : '-' ?></td></tr>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h5>Contact</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Phone</td><td><?= escape($employee['phone'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Mobile</td><td><?= escape($employee['mobile'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Email</td><td><?= escape($employee['email'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Permanent Address</td><td><?= nl2br(escape($employee['permanent_address'] ?: '-')) ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Temporary Address</td><td><?= nl2br(escape($employee['temporary_address'] ?: '-')) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Emergency Contact</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Contact Name</td><td><?= escape($employee['emergency_contact_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Contact Phone</td><td><?= escape($employee['emergency_contact_phone'] ?: '-') ?></td></tr>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h5>System Info</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Employee Code</td><td><strong><?= escape($employee['employee_code']) ?></strong></td></tr>
                            <tr><td style="color:var(--text-muted);">Linked User</td><td><?= $employee['user_name'] ? escape($employee['user_name']) : '<span class="text-muted">Not linked</span>' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Created By</td><td><?= escape($employee['created_by_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Created At</td><td><?= format_datetime($employee['created_at']) ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Updated At</td><td><?= $employee['updated_at'] ? format_datetime($employee['updated_at']) : '-' ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="employment" role="tabpanel">
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Employment Details</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:180px;color:var(--text-muted);">Department</td><td><strong><?= escape($employee['department_name'] ?? '-') ?></strong></td></tr>
                            <tr><td style="color:var(--text-muted);">Designation</td><td><?= escape($employee['designation_name'] ?? '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Joining Date</td><td><?= $employee['joining_date'] ? format_date($employee['joining_date']) : '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Employment Type</td><td><?= $employee['employment_type'] ? ucfirst(str_replace('_', ' ', $employee['employment_type'])) : '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Status</td><td><?= get_status_badge($employee['status']) ?></td></tr>
                            <?php if ($employee['resignation_date']): ?>
                            <tr><td style="color:var(--text-muted);">Resignation Date</td><td><?= format_date($employee['resignation_date']) ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Resignation Reason</td><td><?= nl2br(escape($employee['resignation_reason'] ?: '-')) ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Education</h5></div>
                    <div class="card-body">
                        <?= $employee['education'] ? nl2br(escape($employee['education'])) : '<p class="text-muted mb-0">No education details provided.</p>' ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h5>Experience</h5></div>
                    <div class="card-body">
                        <?= $employee['experience'] ? nl2br(escape($employee['experience'])) : '<p class="text-muted mb-0">No experience details provided.</p>' ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h5>Skills</h5></div>
                    <div class="card-body">
                        <?php if ($employee['skills']): ?>
                            <?php foreach (explode(',', $employee['skills']) as $skill): ?>
                            <span class="badge bg-light text-dark me-1 mb-1"><?= escape(trim($skill)) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <p class="text-muted mb-0">No skills listed.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="financial" role="tabpanel">
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Salary & Bank Details</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:180px;color:var(--text-muted);">Salary</td><td><strong><?= format_currency($employee['salary'] ?? 0) ?></strong></td></tr>
                            <tr><td style="color:var(--text-muted);">Bank Name</td><td><?= escape($employee['bank_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Bank Account No</td><td><?= escape($employee['bank_account_no'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">PAN No</td><td><?= escape($employee['pan_no'] ?: '-') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="documents" role="tabpanel">
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Citizenship</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Citizenship No</td><td><?= escape($employee['citizenship_no'] ?: '-') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Photo</h5></div>
                    <div class="card-body text-center">
                        <?php if ($employee['photo']): ?>
                        <img src="<?= UPLOAD_URL ?>employees/<?= escape($employee['photo']) ?>" alt="Photo" style="max-width:200px;max-height:200px;border-radius:8px;">
                        <?php else: ?>
                        <p class="text-muted mb-0">No photo uploaded.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="attendance" role="tabpanel">
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5>Recent Attendance (Last 30 Records)</h5></div>
                    <div class="card-body">
                        <?php if (empty($attendance)): ?>
                        <div class="text-center py-4 text-muted">No attendance records found.</div>
                        <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $i => $a): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= format_date($a['date']) ?></td>
                                        <td><?= get_status_badge($a['status']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
