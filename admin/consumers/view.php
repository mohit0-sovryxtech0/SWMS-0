<?php
require_once __DIR__ . '/../../includes/config.php';

RBAC::requirePermission('consumers.view');

$consumerId = (int) get('id', 0);
$consumer = db()->fetchOne("SELECT c.*, cat.name as category_name, u.name as created_by_name FROM consumers c LEFT JOIN consumer_categories cat ON c.category_id = cat.id LEFT JOIN users u ON c.created_by = u.id WHERE c.id = ? AND c.deleted_at IS NULL", [$consumerId]);

if (!$consumer) {
    alert_error('Consumer not found.');
    redirect(ADMIN_URL . 'consumers/index.php');
}

$pageTitle = 'Consumer Profile';
$breadcrumbs = [
    ['label' => 'Consumer Management', 'url' => ADMIN_URL . 'consumers/index.php'],
    ['label' => 'Consumer Profile']
];
require_once __DIR__ . '/../includes/header.php';

$meter = db()->fetchOne("SELECT * FROM meters WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1", [$consumerId]);
$documents = db()->fetchAll("SELECT * FROM consumer_documents WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC", [$consumerId]);
$bills = db()->fetchAll("SELECT b.*, b.id as bill_id FROM bills b WHERE b.consumer_id = ? ORDER BY b.created_at DESC LIMIT 20", [$consumerId]);
$payments = db()->fetchAll("SELECT p.* FROM payments p WHERE p.consumer_id = ? ORDER BY p.payment_date DESC LIMIT 20", [$consumerId]);
$complaints = db()->fetchAll("SELECT * FROM complaints WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 20", [$consumerId]);
$transfers = db()->fetchAll("SELECT * FROM ownership_transfers WHERE consumer_id = ? ORDER BY created_at DESC", [$consumerId]);
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Consumer Profile</h4>
            <p><?= escape($consumer['consumer_no']) ?> &mdash; <?= escape($consumer['full_name']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <?php if (RBAC::can('consumers.edit')): ?>
            <a href="<?= ADMIN_URL ?>consumers/edit.php?id=<?= $consumerId ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
            <?php endif; ?>
            <a href="<?= ADMIN_URL ?>consumers/index.php" class="btn btn-outline-secondary btn-sm">
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
                <?php if ($consumer['photo']): ?>
                <img src="<?= UPLOAD_URL ?>consumers/<?= escape($consumer['photo']) ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                <?= strtoupper(substr($consumer['full_name'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <h5 class="mb-1"><?= escape($consumer['full_name']) ?></h5>
                <div class="d-flex gap-3 flex-wrap">
                    <span><i class="fas fa-fingerprint text-muted me-1"></i> <?= escape($consumer['consumer_no']) ?></span>
                    <span><i class="fas fa-phone text-muted me-1"></i> <?= escape($consumer['mobile'] ?: '-') ?></span>
                    <span><i class="fas fa-map-marker-alt text-muted me-1"></i> Ward <?= (int)$consumer['ward_no'] ?></span>
                    <span><?= get_status_badge($consumer['status']) ?></span>
                    <span><?= get_connection_type_badge($consumer['connection_type']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs" id="consumerTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button"><i class="fas fa-user me-1"></i> Profile</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button"><i class="fas fa-file-alt me-1"></i> Documents</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="meter-tab" data-bs-toggle="tab" data-bs-target="#meter" type="button"><i class="fas fa-tachometer-alt me-1"></i> Meter Information</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="billing-tab" data-bs-toggle="tab" data-bs-target="#billing" type="button"><i class="fas fa-file-invoice me-1"></i> Billing History</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button"><i class="fas fa-hand-holding-usd me-1"></i> Payment History</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="complaints-tab" data-bs-toggle="tab" data-bs-target="#complaints" type="button"><i class="fas fa-headset me-1"></i> Complaint History</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="transfer-tab" data-bs-toggle="tab" data-bs-target="#transfer" type="button"><i class="fas fa-exchange-alt me-1"></i> Ownership Transfer</button>
    </li>
</ul>

<div class="tab-content" id="consumerTabContent">
    <!-- Tab 1: Profile -->
    <div class="tab-pane fade show active" id="profile" role="tabpanel">
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Personal Information</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Full Name</td><td><strong><?= escape($consumer['full_name']) ?></strong></td></tr>
                            <tr><td style="color:var(--text-muted);">Father's Name</td><td><?= escape($consumer['father_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Mother's Name</td><td><?= escape($consumer['mother_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Spouse Name</td><td><?= escape($consumer['spouse_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Grandfather's Name</td><td><?= escape($consumer['grandfather_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Gender</td><td><?= escape($consumer['gender'] ? ucfirst($consumer['gender']) : '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Date of Birth</td><td><?= $consumer['date_of_birth'] ? format_date($consumer['date_of_birth']) : '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Citizenship No</td><td><?= escape($consumer['citizenship_no'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Issued District</td><td><?= escape($consumer['citizenship_issued_district'] ?: '-') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Contact & Address</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Phone</td><td><?= escape($consumer['phone'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Mobile</td><td><?= escape($consumer['mobile'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Email</td><td><?= escape($consumer['email'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Permanent Province</td><td><?= escape($consumer['permanent_province'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Permanent District</td><td><?= escape($consumer['permanent_district'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Permanent Municipality</td><td><?= escape($consumer['permanent_municipality'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Permanent Ward</td><td><?= $consumer['permanent_ward'] ? 'Ward ' . (int)$consumer['permanent_ward'] : '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Permanent Tole</td><td><?= escape($consumer['permanent_tole'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Temporary Address</td><td><?= escape($consumer['temporary_address'] ?: '-') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Current Location</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Ward No</td><td><?= $consumer['ward_no'] ? 'Ward ' . (int)$consumer['ward_no'] : '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Tole</td><td><?= escape($consumer['tole'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">House No</td><td><?= escape($consumer['house_no'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Street</td><td><?= escape($consumer['street'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Landmark</td><td><?= escape($consumer['landmark'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Latitude</td><td><?= $consumer['latitude'] ?: '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Longitude</td><td><?= $consumer['longitude'] ?: '-' ?></td></tr>
                        </table>
                        <?php if ($consumer['latitude'] && $consumer['longitude']): ?>
                        <div id="profileMap" style="height:200px;border-radius:8px;margin-top:12px;"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>Connection Details</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Category</td><td><?= escape($consumer['category_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Connection Type</td><td><?= get_connection_type_badge($consumer['connection_type']) ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Property Type</td><td><?= escape($consumer['property_type'] ? ucfirst($consumer['property_type']) : '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Family Members</td><td><?= (int)$consumer['family_members'] ?: '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Tap Connection Date</td><td><?= $consumer['tap_connection_date'] ? format_date($consumer['tap_connection_date']) : '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Connection Size</td><td><?= escape($consumer['connection_size'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Pipe Size</td><td><?= escape($consumer['pipe_size'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Status</td><td><?= get_status_badge($consumer['status']) ?></td></tr>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h5>System Info</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:160px;color:var(--text-muted);">Consumer No</td><td><strong><?= escape($consumer['consumer_no']) ?></strong></td></tr>
                            <tr><td style="color:var(--text-muted);">Created By</td><td><?= escape($consumer['created_by_name'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Created At</td><td><?= format_datetime($consumer['created_at']) ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Updated At</td><td><?= $consumer['updated_at'] ? format_datetime($consumer['updated_at']) : '-' ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Documents -->
    <div class="tab-pane fade" id="documents" role="tabpanel">
        <div class="row mt-3">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5>Upload Document</h5></div>
                    <div class="card-body">
                        <form id="documentUploadForm" enctype="multipart/form-data">
                            <input type="hidden" name="consumer_id" value="<?= $consumerId ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="form-label">Document Title <span class="required">*</span></label>
                                <input type="text" name="title" class="form-control" required maxlength="200">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Document Type</label>
                                <select name="document_type" class="form-select">
                                    <option value="citizenship">Citizenship</option>
                                    <option value="passport">Passport</option>
                                    <option value="tax_receipt">Tax Receipt</option>
                                    <option value="property_document">Property Document</option>
                                    <option value="agreement">Agreement</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">File <span class="required">*</span></label>
                                <input type="file" name="document_file" class="form-control" required accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                                <div class="form-text">JPG, PNG, PDF, DOC. Max 5MB.</div>
                            </div>
                            <div class="form-group mb-0">
                                <button type="submit" class="btn btn-primary w-100" id="uploadBtn">
                                    <i class="fas fa-upload"></i> Upload
                                </button>
                            </div>
                        </form>
                        <div id="uploadProgress" class="mt-3" style="display:none;">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%">Uploading...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5>Documents (<?= count($documents) ?>)</h5></div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                        <div class="text-center py-4 text-muted">No documents uploaded yet.</div>
                        <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>File</th>
                                        <th>Uploaded On</th>
                                        <th style="width:80px">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $i => $doc): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><strong><?= escape($doc['title']) ?></strong></td>
                                        <td><span class="badge bg-light text-dark"><?= escape(ucfirst(str_replace('_', ' ', $doc['document_type']))) ?></span></td>
                                        <td><a href="<?= UPLOAD_URL ?>documents/<?= escape($doc['file_path']) ?>" target="_blank"><i class="fas fa-download me-1"></i> Download</a></td>
                                        <td style="font-size:12px;color:var(--text-muted);"><?= format_datetime($doc['created_at']) ?></td>
                                        <td>
                                            <button type="button" class="btn-action delete" title="Delete" onclick="deleteDoc(<?= $doc['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </td>
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

    <!-- Tab 3: Meter Information -->
    <div class="tab-pane fade" id="meter" role="tabpanel">
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5>Assigned Meter</h5></div>
                    <div class="card-body">
                        <?php if ($meter): ?>
                        <table class="table table-borderless" style="font-size:13px;">
                            <tr><td style="width:180px;color:var(--text-muted);">Meter No</td><td><strong><?= escape($meter['meter_no']) ?></strong></td></tr>
                            <tr><td style="color:var(--text-muted);">Meter Type</td><td><?= escape($meter['meter_type'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Meter Size</td><td><?= escape($meter['meter_size'] ?: '-') ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Initial Reading</td><td><?= $meter['initial_reading'] ?? '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Installation Date</td><td><?= $meter['installation_date'] ? format_date($meter['installation_date']) : '-' ?></td></tr>
                            <tr><td style="color:var(--text-muted);">Status</td><td><?= get_status_badge($meter['status'] ?? 'active') ?></td></tr>
                        </table>
                        <?php else: ?>
                        <div class="text-center py-4 text-muted">No meter assigned to this consumer.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 4: Billing History -->
    <div class="tab-pane fade" id="billing" role="tabpanel">
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5>Billing History</h5></div>
                    <div class="card-body">
                        <?php if (empty($bills)): ?>
                        <div class="text-center py-4 text-muted">No billing records found.</div>
                        <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Bill No</th>
                                        <th>Bill Date</th>
                                        <th>Period</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bills as $b): ?>
                                    <tr>
                                        <td><strong><?= escape($b['bill_no'] ?? '-') ?></strong></td>
                                        <td><?= format_date($b['bill_date'] ?? '') ?></td>
                                        <td><?= escape(($b['billing_month'] ?? '') . ' ' . ($b['billing_year'] ?? '')) ?></td>
                                        <td><?= format_currency($b['total_amount'] ?? 0) ?></td>
                                        <td><?= get_status_badge($b['status'] ?? 'unpaid') ?></td>
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

    <!-- Tab 5: Payment History -->
    <div class="tab-pane fade" id="payments" role="tabpanel">
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5>Payment History</h5></div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                        <div class="text-center py-4 text-muted">No payment records found.</div>
                        <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Receipt No</th>
                                        <th>Payment Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><strong><?= escape($p['receipt_no'] ?? '-') ?></strong></td>
                                        <td><?= format_date($p['payment_date'] ?? '') ?></td>
                                        <td><?= format_currency($p['amount'] ?? 0) ?></td>
                                        <td><?= escape(ucfirst($p['payment_method'] ?? '-')) ?></td>
                                        <td><?= get_status_badge($p['status'] ?? 'paid') ?></td>
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

    <!-- Tab 6: Complaint History -->
    <div class="tab-pane fade" id="complaints" role="tabpanel">
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5>Complaint History</h5></div>
                    <div class="card-body">
                        <?php if (empty($complaints)): ?>
                        <div class="text-center py-4 text-muted">No complaints found.</div>
                        <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Ticket No</th>
                                        <th>Category</th>
                                        <th>Subject</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($complaints as $comp): ?>
                                    <tr>
                                        <td><strong><?= escape($comp['ticket_no'] ?? '-') ?></strong></td>
                                        <td><?= escape(ucfirst($comp['category'] ?? '-')) ?></td>
                                        <td><?= escape(truncate($comp['subject'] ?? '', 40)) ?></td>
                                        <td><span class="badge bg-<?= $comp['priority'] === 'high' ? 'danger' : ($comp['priority'] === 'medium' ? 'warning' : 'info') ?>"><?= escape(ucfirst($comp['priority'] ?? 'normal')) ?></span></td>
                                        <td><?= get_status_badge($comp['status'] ?? 'open') ?></td>
                                        <td style="font-size:12px;color:var(--text-muted);"><?= format_datetime($comp['created_at']) ?></td>
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

    <!-- Tab 7: Ownership Transfer -->
    <div class="tab-pane fade" id="transfer" role="tabpanel">
        <div class="row mt-3">
            <?php if (RBAC::can('consumers.edit')): ?>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h5>Transfer Ownership</h5></div>
                    <div class="card-body">
                        <form method="POST" action="<?= ADMIN_URL ?>consumers/transfer.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="consumer_id" value="<?= $consumerId ?>">
                            <div class="form-group">
                                <label class="form-label">New Owner Full Name <span class="required">*</span></label>
                                <input type="text" name="new_owner_name" class="form-control" required maxlength="150">
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Owner Mobile <span class="required">*</span></label>
                                <input type="text" name="new_owner_mobile" class="form-control" required maxlength="10">
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Owner Email</label>
                                <input type="email" name="new_owner_email" class="form-control" maxlength="100">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Reason for Transfer <span class="required">*</span></label>
                                <textarea name="reason" class="form-control" rows="3" required maxlength="500"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Transfer Date</label>
                                <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-exchange-alt"></i> Submit Transfer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?= RBAC::can('consumers.edit') ? 'col-lg-7' : 'col-12' ?>">
                <div class="card">
                    <div class="card-header"><h5>Transfer History</h5></div>
                    <div class="card-body">
                        <?php if (empty($transfers)): ?>
                        <div class="text-center py-4 text-muted">No ownership transfers recorded.</div>
                        <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Previous Owner</th>
                                        <th>New Owner</th>
                                        <th>Reason</th>
                                        <th>Transfer Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transfers as $i => $t): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= escape($t['previous_owner_name'] ?: '-') ?></td>
                                        <td><strong><?= escape($t['new_owner_name']) ?></strong></td>
                                        <td><?= escape(truncate($t['reason'], 30)) ?></td>
                                        <td><?= $t['transfer_date'] ? format_date($t['transfer_date']) : '-' ?></td>
                                        <td><?= get_status_badge($t['status'] ?? 'pending') ?></td>
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

<script>
function deleteDoc(docId) {
    if (!confirm('Are you sure you want to delete this document?')) return;
    var formData = new FormData();
    formData.append('action', 'delete');
    formData.append('document_id', docId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('<?= ADMIN_URL ?>consumers/documents.php?consumer_id=<?= $consumerId ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            location.reload();
        } else {
            alert(d.message || 'Delete failed');
        }
    })
    .catch(() => alert('Network error'));
}

document.addEventListener('DOMContentLoaded', function() {
    var uploadForm = document.getElementById('documentUploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('uploadBtn');
            var progress = document.getElementById('uploadProgress');
            btn.disabled = true;
            progress.style.display = 'block';

            var formData = new FormData(this);
            formData.append('action', 'upload');

            fetch('<?= ADMIN_URL ?>consumers/documents.php?consumer_id=<?= $consumerId ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                progress.style.display = 'none';
                btn.disabled = false;
                if (d.success) {
                    location.reload();
                } else {
                    alert(d.message || 'Upload failed');
                }
            })
            .catch(() => {
                progress.style.display = 'none';
                btn.disabled = false;
                alert('Network error');
            });
        });
    }

    <?php if ($consumer['latitude'] && $consumer['longitude']): ?>
    var map = L.map('profileMap').setView([<?= $consumer['latitude'] ?>, <?= $consumer['longitude'] ?>], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    L.marker([<?= $consumer['latitude'] ?>, <?= $consumer['longitude'] ?>]).addTo(map);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
