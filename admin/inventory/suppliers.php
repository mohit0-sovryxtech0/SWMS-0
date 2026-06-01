<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Suppliers';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Inventory', 'url' => ADMIN_URL . 'inventory/index.php'],
    ['label' => 'Suppliers']
];
RBAC::requirePermission('suppliers.manage');

require_once __DIR__ . '/../includes/header.php';

$extraJs = <<<'JS'
<script>
$(document).ready(function() {
    var table = $('#suppliersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '${ADMIN_URL}inventory/suppliers.php',
            type: 'POST',
            data: function(d) {
                d.action = 'getData';
                d.csrf_token = '${csrf_token()}';
            }
        },
        columns: [
            { data: 'supplier_code' },
            { data: 'name' },
            { data: 'contact_person' },
            { data: 'phone' },
            { data: 'mobile' },
            { data: 'email' },
            { data: 'status' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        pageLength: 25,
        language: { searchPlaceholder: 'Search suppliers...' }
    });

    $('#supplierForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var id = $('#supplierId').val();
        var url = id ? '${ADMIN_URL}inventory/suppliers.php?action=update&id=' + id : '${ADMIN_URL}inventory/suppliers.php?action=create';
        $.post(url, form.serialize(), function(res) {
            if (res.success) {
                $('#supplierModal').modal('hide');
                table.ajax.reload();
                showToast('success', res.message);
            } else {
                showToast('error', res.message);
            }
        });
    });

    $('#suppliersTable').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('${ADMIN_URL}inventory/suppliers.php?action=edit&id=' + id, function(data) {
            if (data.success) {
                var s = data.data;
                $('#supplierId').val(s.id);
                $('#supplierCode').val(s.supplier_code);
                $('#supplierName').val(s.name);
                $('#contactPerson').val(s.contact_person);
                $('#supplierPhone').val(s.phone);
                $('#supplierMobile').val(s.mobile);
                $('#supplierEmail').val(s.email);
                $('#supplierAddress').val(s.address);
                $('#supplierPan').val(s.pan_no);
                $('#supplierWebsite').val(s.website);
                $('#contractStart').val(s.contract_start_date);
                $('#contractEnd').val(s.contract_end_date);
                $('#paymentTerms').val(s.payment_terms);
                $('#supplierStatus').val(s.status);
                $('#supplierModalTitle').text('Edit Supplier');
                $('#supplierModal').modal('show');
            }
        });
    });

    $('.btn-new-supplier').click(function() {
        $('#supplierForm')[0].reset();
        $('#supplierId').val('');
        $('#supplierStatus').val('active');
        $('#supplierModalTitle').text('Add New Supplier');
        $('#supplierModal').modal('show');
    });

    $('#suppliersTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        if (confirm('Are you sure you want to delete this supplier?')) {
            $.post('${ADMIN_URL}inventory/suppliers.php?action=delete&id=' + id, {csrf_token: '${csrf_token()}'}, function(res) {
                if (res.success) {
                    table.ajax.reload();
                    showToast('success', res.message);
                } else {
                    showToast('error', res.message);
                }
            });
        }
    });
});
</script>
JS;

if (isPost()) {
    $action = request('action');
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        if ($action === 'getData') {
            $draw = intval(post('draw'));
            $start = intval(post('start'));
            $length = intval(post('length')) ?: 25;
            $search = post('search')['value'] ?? '';

            $orderColumn = post('order')[0]['column'] ?? 1;
            $orderDir = post('order')[0]['dir'] ?? 'asc';
            $columns = ['supplier_code', 'name', 'contact_person', 'phone', 'mobile', 'email', 'status'];
            $orderBy = $columns[$orderColumn] ?? 'name';

            $where = "WHERE deleted_at IS NULL";
            $params = [];

            if (!empty($search)) {
                $where .= " AND (name LIKE :search OR supplier_code LIKE :search2 OR contact_person LIKE :search3 OR email LIKE :search4)";
                $params['search'] = "%{$search}%";
                $params['search2'] = "%{$search}%";
                $params['search3'] = "%{$search}%";
                $params['search4'] = "%{$search}%";
            }

            $total = db()->fetchColumn("SELECT COUNT(*) FROM suppliers {$where}", $params);
            $sql = "SELECT * FROM suppliers {$where} ORDER BY {$orderBy} {$orderDir} LIMIT {$start}, {$length}";
            $rows = db()->fetchAll($sql, $params);

            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    'supplier_code' => '<span class="fw-semibold">' . escape($row['supplier_code'] ?? 'N/A') . '</span>',
                    'name' => escape($row['name']),
                    'contact_person' => escape($row['contact_person'] ?? '-'),
                    'phone' => escape($row['phone'] ?? '-'),
                    'mobile' => escape($row['mobile'] ?? '-'),
                    'email' => $row['email'] ? '<a href="mailto:' . escape($row['email']) . '">' . escape($row['email']) . '</a>' : '-',
                    'status' => get_status_badge($row['status']),
                    'action' => '<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-info btn-edit" data-id="' . $row['id'] . '" title="Edit"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-danger btn-delete" data-id="' . $row['id'] . '" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>'
                ];
            }

            json_response([
                'draw' => $draw,
                'recordsTotal' => intval($total),
                'recordsFiltered' => intval($total),
                'data' => $data
            ]);
        }

        if ($action === 'create') {
            $v = validator(post(), [
                'name' => 'required|min:2|max:200',
                'supplier_code' => 'unique:suppliers,supplier_code',
                'email' => 'email',
                'phone' => 'phone',
                'mobile' => 'phone',
                'website' => 'url',
            ]);

            if ($v->fails()) {
                json_error('Validation failed', $v->errors());
            }

            $code = post('supplier_code');
            if (empty($code)) {
                $lastId = db()->fetchColumn("SELECT MAX(id) FROM suppliers");
                $code = 'SUP-' . str_pad(($lastId ?: 0) + 1, 4, '0', STR_PAD_LEFT);
            }

            $id = db()->insert('suppliers', [
                'supplier_code' => $code,
                'name' => post('name'),
                'contact_person' => post('contact_person'),
                'phone' => post('phone'),
                'mobile' => post('mobile'),
                'email' => post('email'),
                'address' => post('address'),
                'pan_no' => post('pan_no'),
                'website' => post('website'),
                'contract_start_date' => post('contract_start_date') ?: null,
                'contract_end_date' => post('contract_end_date') ?: null,
                'payment_terms' => post('payment_terms'),
                'status' => post('status', 'active'),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            log_activity(Auth::id(), 'create', 'Inventory', 'Created supplier: ' . post('name'));
            json_success(['id' => $id], 'Supplier created successfully');
        }

        if ($action === 'update') {
            $id = (int)get('id');
            if (!$id) json_error('Invalid supplier ID');

            $v = validator(post(), [
                'name' => 'required|min:2|max:200',
                'supplier_code' => 'unique:suppliers,supplier_code,' . $id,
                'email' => 'email',
                'phone' => 'phone',
            ]);

            if ($v->fails()) {
                json_error('Validation failed', $v->errors());
            }

            db()->update('suppliers', [
                'supplier_code' => post('supplier_code'),
                'name' => post('name'),
                'contact_person' => post('contact_person'),
                'phone' => post('phone'),
                'mobile' => post('mobile'),
                'email' => post('email'),
                'address' => post('address'),
                'pan_no' => post('pan_no'),
                'website' => post('website'),
                'contract_start_date' => post('contract_start_date') ?: null,
                'contract_end_date' => post('contract_end_date') ?: null,
                'payment_terms' => post('payment_terms'),
                'status' => post('status', 'active'),
            ], 'id = :id', ['id' => $id]);

            log_activity(Auth::id(), 'update', 'Inventory', 'Updated supplier: ' . post('name'));
            json_success([], 'Supplier updated successfully');
        }

        if ($action === 'delete') {
            $id = (int)get('id');
            if (!$id) json_error('Invalid supplier ID');
            db()->softDelete('suppliers', $id);
            log_activity(Auth::id(), 'delete', 'Inventory', 'Deleted supplier ID: ' . $id);
            json_success([], 'Supplier deleted successfully');
        }

        if ($action === 'edit') {
            $id = (int)get('id');
            $s = db()->fetchOne("SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL", [$id]);
            if (!$s) json_error('Supplier not found');
            json_success($s);
        }
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-truck me-2 text-primary"></i>Suppliers</h4>
        <button type="button" class="btn btn-primary btn-sm btn-new-supplier">
            <i class="fas fa-plus me-1"></i>New Supplier
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="suppliersTable" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th class="no-sort" style="width:80px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="supplierForm" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="supplierId">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierModalTitle"><i class="fas fa-truck me-2 text-primary"></i>Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Supplier Code</label>
                            <input type="text" name="supplier_code" id="supplierCode" class="form-control" placeholder="Auto-generated if empty">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="supplierName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" id="contactPerson" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="supplierPhone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile" id="supplierMobile" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="supplierEmail" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PAN / VAT No</label>
                            <input type="text" name="pan_no" id="supplierPan" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" id="supplierWebsite" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contract Start</label>
                            <input type="date" name="contract_start_date" id="contractStart" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contract End</label>
                            <input type="date" name="contract_end_date" id="contractEnd" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Terms</label>
                            <input type="text" name="payment_terms" id="paymentTerms" class="form-control" placeholder="e.g. Net 30">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="supplierAddress" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="supplierStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
