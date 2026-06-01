<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Stock Out';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Inventory', 'url' => ADMIN_URL . 'inventory/index.php'],
    ['label' => 'Stock Out']
];
RBAC::requirePermission('stock.out');

$ajaxUrl = ADMIN_URL . 'inventory/stock-out.php';
$csrfToken = csrf_token();

// Handle AJAX before HTML output
if (isPost() && !post('action')) {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $v = validator(post(), [
            'issue_date' => 'required|date',
            'items' => 'required|array',
        ]);

        if ($v->fails()) {
            json_error('Validation failed', $v->allErrors());
        }

        $itemsData = post('items');
        if (empty($itemsData) || !is_array($itemsData)) {
            throw new Exception('Please add at least one item');
        }

        db()->beginTransaction();

        try {
            $totalAmount = 0;
            $validItems = [];

            foreach ($itemsData as $idx => $item) {
                $itemId = (int)($item['item_id'] ?? 0);
                $quantity = (float)($item['quantity'] ?? 0);
                $unitPrice = (float)($item['unit_price'] ?? 0);

                if ($itemId <= 0 || $quantity <= 0) {
                    throw new Exception("Invalid item data at row " . ($idx + 1));
                }

                $invItem = db()->fetchOne("SELECT id, name, current_stock, unit FROM inventory_items WHERE id = ? AND deleted_at IS NULL FOR UPDATE", [$itemId]);
                if (!$invItem) {
                    throw new Exception("Item not found at row " . ($idx + 1));
                }

                if ($quantity > $invItem['current_stock']) {
                    throw new Exception("Insufficient stock for {$invItem['name']}. Available: {$invItem['current_stock']}, Requested: {$quantity}");
                }

                $lineTotal = $quantity * $unitPrice;
                $totalAmount += $lineTotal;

                $validItems[] = [
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ];
            }

            $lastIssue = db()->fetchColumn("SELECT MAX(id) FROM stock_out");
            $issueNo = 'ISS-' . date('Ymd') . '-' . str_pad(($lastIssue ?: 0) + 1, 4, '0', STR_PAD_LEFT);

            $stockOutId = db()->insert('stock_out', [
                'issue_no' => post('issue_no', $issueNo),
                'issued_to' => post('issued_to'),
                'department_id' => (int)post('department_id') ?: null,
                'employee_id' => (int)post('employee_id') ?: null,
                'work_order_id' => (int)post('work_order_id') ?: null,
                'issue_date' => post('issue_date'),
                'issued_by' => Auth::id(),
                'purpose' => post('purpose'),
                'notes' => post('notes'),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            foreach ($validItems as $vi) {
                db()->insert('stock_out_items', [
                    'stock_out_id' => $stockOutId,
                    'item_id' => $vi['item_id'],
                    'quantity' => $vi['quantity'],
                    'unit_price' => $vi['unit_price'],
                    'total_price' => $vi['total_price'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                db()->query(
                    "UPDATE inventory_items SET current_stock = current_stock - :qty WHERE id = :id",
                    ['qty' => $vi['quantity'], 'id' => $vi['item_id']]
                );
            }

            db()->commit();
            log_activity(Auth::id(), 'create', 'Inventory', 'Stock Out: ' . post('issue_no', $issueNo) . ' total: ' . $totalAmount);
            json_success(['id' => $stockOutId], 'Stock out recorded successfully');
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';

$departments = db()->fetchAll("SELECT id, name FROM departments WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");
$employees = db()->fetchAll("SELECT id, employee_code, full_name FROM employees WHERE deleted_at IS NULL AND status = 'active' ORDER BY full_name");
$workOrders = db()->fetchAll("SELECT id, work_order_no, title FROM work_orders WHERE status IN ('pending', 'in_progress') ORDER BY created_at DESC");
$items = db()->fetchAll("SELECT id, item_code, name, unit, current_stock, unit_price FROM inventory_items WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");

// Generate issue no
$lastIssue = db()->fetchColumn("SELECT MAX(id) FROM stock_out");
$issueNo = 'ISS-' . date('Ymd') . '-' . str_pad(($lastIssue ?: 0) + 1, 4, '0', STR_PAD_LEFT);

$extraCss = '<style>
.item-row-out { background-color: #fffef5; }
.low-stock-warning { color: #dc3545; font-size: 0.8rem; }
</style>';

$extraJs = <<<JS
<script>
$(document).ready(function() {
    var itemIndex = 0;

    function addItemRow(data) {
        data = data || {};
        var index = itemIndex++;
        var row = '<tr class="item-row-out" data-index="' + index + '">';
        row += '<td><select name="items[' + index + '][item_id]" class="form-select form-select-sm item-select" required>' +
               '<option value="">-- Select Item --</option>' +
               <?php 
               $opts = '';
               foreach ($items as $it) {
                   $stockClass = $it['current_stock'] <= 0 ? 'style="color:#dc3545"' : '';
                   $label = $it['item_code'] . ' - ' . $it['name'] . ' [Stock: ' . $it['current_stock'] . ']';
                   $opts .= "'<option value=\"{$it['id']}\" data-unit=\"" . escape($it['unit']) . "\" data-price=\"{$it['unit_price']}\" data-stock=\"{$it['current_stock']}\" {$stockClass}>" . escape($label) . "</option>' + ";
               }
               echo $opts ?: "''";
               ?>
               '</select></td>';
        row += '<td><input type="number" step="0.01" min="0.01" name="items[' + index + '][quantity]" class="form-control form-control-sm item-qty" required placeholder="Qty"></td>';
        row += '<td><input type="text" name="items[' + index + '][unit]" class="form-control form-control-sm item-unit" readonly style="width:60px"></td>';
        row += '<td><input type="number" step="0.01" min="0" name="items[' + index + '][unit_price]" class="form-control form-control-sm item-price" placeholder="0.00"></td>';
        row += '<td class="item-total text-end fw-semibold">0.00</td>';
        row += '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger delete-item"><i class="fas fa-times"></i></button></td>';
        row += '</tr>';
        $('#itemsOutContainer').append(row);

        if (data.item_id) {
            var select = $('#itemsOutContainer tr:last .item-select');
            select.val(data.item_id).trigger('change');
            $('#itemsOutContainer tr:last .item-qty').val(data.quantity);
            $('#itemsOutContainer tr:last .item-price').val(data.unit_price);
        }
    }

    $(document).on('change', '.item-select', function() {
        var selected = $(this).find(':selected');
        var row = $(this).closest('tr');
        row.find('.item-unit').val(selected.data('unit') || '');
        var price = selected.data('price');
        if (price > 0 && !row.find('.item-price').val()) {
            row.find('.item-price').val(price);
        }
        updateRowTotal(row);
    });

    $(document).on('input', '.item-qty, .item-price', function() {
        updateRowTotal($(this).closest('tr'));
    });

    function updateRowTotal(row) {
        var qty = parseFloat(row.find('.item-qty').val()) || 0;
        var price = parseFloat(row.find('.item-price').val()) || 0;
        var total = qty * price;
        row.find('.item-total').text(total.toFixed(2));
        updateGrandTotal();
    }

    function updateGrandTotal() {
        var grandTotal = 0;
        $('#itemsOutContainer .item-total').each(function() {
            grandTotal += parseFloat($(this).text()) || 0;
        });
        $('#grandTotalOut').text(grandTotal.toFixed(2));
    }

    $(document).on('click', '.delete-item', function() {
        $(this).closest('tr').remove();
        updateGrandTotal();
    });

    $('#addItemOutBtn').click(function() { addItemRow(); });

    $('#stockOutForm').on('submit', function(e) {
        e.preventDefault();
        if ($('#itemsOutContainer tr.item-row-out').length === 0) {
            showToast('error', 'Please add at least one item');
            return;
        }

        // Check stock availability
        var hasError = false;
        $('#itemsOutContainer .item-select').each(function() {
            var selected = $(this).find(':selected');
            var stock = parseFloat(selected.data('stock')) || 0;
            var row = $(this).closest('tr');
            var qty = parseFloat(row.find('.item-qty').val()) || 0;
            if (qty > stock) {
                hasError = true;
                row.addClass('table-danger');
            } else {
                row.removeClass('table-danger');
            }
        });

        if (hasError) {
            showToast('error', 'Some items exceed available stock. Please adjust quantities.');
            return;
        }

        var form = $(this);
        var btn = form.find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');
        $.post('{$ajaxUrl}', form.serialize(), function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Stock Out');
            if (res.success) {
                showToast('success', 'Stock out recorded successfully');
                setTimeout(function() { window.location.href = '{$ajaxUrl}'; }, 1000);
            } else {
                showToast('error', res.message);
            }
        }).fail(function() {
            btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Stock Out');
            showToast('error', 'Request failed. Please try again.');
        });
    });

    addItemRow();
});
</script>
JS;

$page = max(1, (int)get('page', 1));
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$totalRecords = db()->fetchColumn("SELECT COUNT(*) FROM stock_out");
$records = db()->fetchAll(
    "SELECT so.*, d.name AS department_name, e.full_name AS employee_name,
            wo.work_order_no, u.name AS issued_by_name
     FROM stock_out so
     LEFT JOIN departments d ON so.department_id = d.id
     LEFT JOIN employees e ON so.employee_id = e.id
     LEFT JOIN work_orders wo ON so.work_order_id = wo.id
     LEFT JOIN users u ON so.issued_by = u.id
     ORDER BY so.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
);
$totalPages = ceil($totalRecords / $perPage);
$paginationUrl = ADMIN_URL . "inventory/stock-out.php?page={page}";
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-arrow-up text-warning me-2"></i>Stock Out Management</h4>
        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="collapse" data-bs-target="#newStockOutForm">
            <i class="fas fa-plus me-1"></i>New Stock Out
        </button>
    </div>
</div>

<div class="collapse <?= empty($records) ? 'show' : '' ?>" id="newStockOutForm">
    <div class="card mb-3 border-warning">
        <div class="card-header bg-warning text-dark">
            <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Stock Out Issue</h6>
        </div>
        <div class="card-body">
            <form id="stockOutForm" method="post">
                <?= csrf_field() ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Issue No <span class="text-danger">*</span></label>
                        <input type="text" name="issue_no" class="form-control" value="<?= escape(post('issue_no', $issueNo)) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Issue Date <span class="text-danger">*</span></label>
                        <input type="date" name="issue_date" class="form-control" value="<?= escape(post('issue_date', date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Issued To (Name)</label>
                        <input type="text" name="issued_to" class="form-control" value="<?= escape(post('issued_to')) ?>" placeholder="Person/entity name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= post('department_id') == $d['id'] ? 'selected' : '' ?>><?= escape($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Employee</label>
                        <select name="employee_id" class="form-select">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= post('employee_id') == $e['id'] ? 'selected' : '' ?>><?= escape($e['employee_code'] . ' - ' . $e['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Work Order</label>
                        <select name="work_order_id" class="form-select">
                            <option value="">Select Work Order</option>
                            <?php foreach ($workOrders as $wo): ?>
                            <option value="<?= $wo['id'] ?>" <?= post('work_order_id') == $wo['id'] ? 'selected' : '' ?>><?= escape($wo['work_order_no'] . ' - ' . truncate($wo['title'], 40)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Purpose</label>
                        <input type="text" name="purpose" class="form-control" value="<?= escape(post('purpose')) ?>" placeholder="Reason for stock issue">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" value="<?= escape(post('notes')) ?>" placeholder="Additional notes">
                    </div>
                </div>

                <h6 class="fw-bold mb-2">Items <span class="text-danger">*</span></h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width:35%">Item</th>
                                <th style="width:15%">Quantity</th>
                                <th style="width:10%">Unit</th>
                                <th style="width:15%">Unit Price</th>
                                <th style="width:15%">Total</th>
                                <th style="width:10%"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsOutContainer"></tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
                    <button type="button" id="addItemOutBtn" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i>Add Item
                    </button>
                    <div class="text-end">
                        <strong>Grand Total: NRs. <span id="grandTotalOut">0.00</span></strong>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>Save Stock Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Stock Out History</h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($records)): ?>
        <div class="text-center text-muted py-4">
            <i class="fas fa-arrow-up fa-2x mb-2"></i>
            <p class="mb-0">No stock out records yet</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Issue No</th>
                        <th>Issued To</th>
                        <th>Department</th>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Purpose</th>
                        <th>Issued By</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= escape($r['issue_no']) ?></td>
                        <td><?= escape($r['issued_to'] ?? '-') ?></td>
                        <td><?= escape($r['department_name'] ?? '-') ?></td>
                        <td><?= escape($r['employee_name'] ?? '-') ?></td>
                        <td><?= format_date($r['issue_date']) ?></td>
                        <td><?= escape(truncate($r['purpose'] ?? '-', 40)) ?></td>
                        <td><?= escape($r['issued_by_name'] ?? 'N/A') ?></td>
                        <td><?= format_datetime($r['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="p-2">
            <?= pagination($totalRecords, $page, $perPage, $paginationUrl) ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
