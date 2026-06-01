<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Stock In';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Inventory', 'url' => ADMIN_URL . 'inventory/index.php'],
    ['label' => 'Stock In']
];
RBAC::requirePermission('stock.in');

$ajaxUrl = ADMIN_URL . 'inventory/stock-in.php';
$csrfToken = csrf_token();

// Handle AJAX before HTML output
if (isPost() && !post('action')) {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $v = validator(post(), [
            'received_date' => 'required|date',
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

                $invItem = db()->fetchOne("SELECT id, name, unit FROM inventory_items WHERE id = ? AND deleted_at IS NULL", [$itemId]);
                if (!$invItem) {
                    throw new Exception("Item not found at row " . ($idx + 1));
                }

                $lineTotal = $quantity * $unitPrice;
                $totalAmount += $lineTotal;

                $validItems[] = [
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'batch_no' => $item['batch_no'] ?? null,
                    'expiry_date' => !empty($item['expiry_date']) ? $item['expiry_date'] : null,
                ];
            }

            $lastReceipt = db()->fetchColumn("SELECT MAX(id) FROM stock_in");
            $receiptNo = 'RCT-' . date('Ymd') . '-' . str_pad(($lastReceipt ?: 0) + 1, 4, '0', STR_PAD_LEFT);

            $stockInId = db()->insert('stock_in', [
                'receipt_no' => post('receipt_no', $receiptNo),
                'supplier_id' => (int)post('supplier_id') ?: null,
                'bill_no' => post('bill_no'),
                'bill_date' => post('bill_date') ?: null,
                'received_date' => post('received_date'),
                'received_by' => Auth::id(),
                'notes' => post('notes'),
                'total_amount' => $totalAmount,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            foreach ($validItems as $vi) {
                db()->insert('stock_in_items', [
                    'stock_in_id' => $stockInId,
                    'item_id' => $vi['item_id'],
                    'quantity' => $vi['quantity'],
                    'unit_price' => $vi['unit_price'],
                    'total_price' => $vi['total_price'],
                    'batch_no' => $vi['batch_no'],
                    'expiry_date' => $vi['expiry_date'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                db()->query(
                    "UPDATE inventory_items SET current_stock = current_stock + :qty WHERE id = :id",
                    ['qty' => $vi['quantity'], 'id' => $vi['item_id']]
                );
            }

            db()->commit();
            log_activity(Auth::id(), 'create', 'Inventory', 'Stock In: ' . post('receipt_no', $receiptNo) . ' total: ' . $totalAmount);
            json_success(['id' => $stockInId], 'Stock in recorded successfully');
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';

$suppliers = db()->fetchAll("SELECT id, name, supplier_code FROM suppliers WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");
$items = db()->fetchAll("SELECT id, item_code, name, unit, current_stock, unit_price FROM inventory_items WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");

// Generate receipt no
$lastReceipt = db()->fetchColumn("SELECT MAX(id) FROM stock_in");
$receiptNo = 'RCT-' . date('Ymd') . '-' . str_pad(($lastReceipt ?: 0) + 1, 4, '0', STR_PAD_LEFT);

$extraCss = '<style>
.item-row { background-color: #f8f9fa; }
.item-row .delete-item { cursor: pointer; color: #dc3545; }
.stock-in-items-table th { font-size: 0.85rem; }
</style>';

$extraJs = <<<JS
<script>
$(document).ready(function() {
    var itemIndex = 0;

    function addItemRow(data) {
        data = data || {};
        var index = itemIndex++;
        var row = '<tr class="item-row" data-index="' + index + '">';
        row += '<td><select name="items[' + index + '][item_id]" class="form-select form-select-sm item-select" required>' +
               '<option value="">-- Select Item --</option>' +
               <?php 
               $opts = '';
               foreach ($items as $it) {
                   $opts .= "'<option value=\"{$it['id']}\" data-unit=\"" . escape($it['unit']) . "\" data-price=\"{$it['unit_price']}\">" . escape($it['item_code'] . ' - ' . $it['name']) . "</option>' + ";
               }
               echo $opts ?: "''";
               ?>
               '</select></td>';
        row += '<td><input type="number" step="0.01" min="0.01" name="items[' + index + '][quantity]" class="form-control form-control-sm item-qty" required placeholder="Qty"></td>';
        row += '<td><input type="text" name="items[' + index + '][unit]" class="form-control form-control-sm item-unit" readonly style="width:60px"></td>';
        row += '<td><input type="number" step="0.01" min="0" name="items[' + index + '][unit_price]" class="form-control form-control-sm item-price" required placeholder="0.00"></td>';
        row += '<td class="item-total text-end fw-semibold">0.00</td>';
        row += '<td><input type="text" name="items[' + index + '][batch_no]" class="form-control form-control-sm" placeholder="Batch No"></td>';
        row += '<td><input type="date" name="items[' + index + '][expiry_date]" class="form-control form-control-sm"></td>';
        row += '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger delete-item"><i class="fas fa-times"></i></button></td>';
        row += '</tr>';
        $('#itemsContainer').append(row);

        if (data.item_id) {
            var select = $('#itemsContainer tr:last .item-select');
            select.val(data.item_id).trigger('change');
            $('#itemsContainer tr:last .item-qty').val(data.quantity);
            $('#itemsContainer tr:last .item-price').val(data.unit_price);
            $('#itemsContainer tr:last input[name$="[batch_no]"]').val(data.batch_no || '');
            $('#itemsContainer tr:last input[name$="[expiry_date]"]').val(data.expiry_date || '');
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
        $('#itemsContainer .item-total').each(function() {
            grandTotal += parseFloat($(this).text()) || 0;
        });
        $('#grandTotal').text(grandTotal.toFixed(2));
        $('#totalAmount').val(grandTotal.toFixed(2));
    }

    $(document).on('click', '.delete-item', function() {
        $(this).closest('tr').remove();
        updateGrandTotal();
    });

    $('#addItemBtn').click(function() { addItemRow(); });

    $('#stockInForm').on('submit', function(e) {
        e.preventDefault();
        if ($('#itemsContainer tr.item-row').length === 0) {
            showToast('error', 'Please add at least one item');
            return;
        }
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');
        $.post('{$ajaxUrl}', form.serialize(), function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Stock In');
            if (res.success) {
                showToast('success', 'Stock in recorded successfully');
                setTimeout(function() { window.location.href = '{$ajaxUrl}'; }, 1000);
            } else {
                showToast('error', res.message);
            }
        }).fail(function() {
            btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Stock In');
            showToast('error', 'Request failed. Please try again.');
        });
    });

    // Add first row
    addItemRow();
});
</script>
JS;

// List recent stock in records
$page = max(1, (int)get('page', 1));
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$totalRecords = db()->fetchColumn("SELECT COUNT(*) FROM stock_in");
$records = db()->fetchAll(
    "SELECT si.*, s.name AS supplier_name, u.name AS received_by_name
     FROM stock_in si
     LEFT JOIN suppliers s ON si.supplier_id = s.id
     LEFT JOIN users u ON si.received_by = u.id
     ORDER BY si.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
);
$totalPages = ceil($totalRecords / $perPage);
$paginationUrl = ADMIN_URL . "inventory/stock-in.php?page={page}";
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-arrow-down text-success me-2"></i>Stock In Management</h4>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="collapse" data-bs-target="#newStockInForm">
            <i class="fas fa-plus me-1"></i>New Stock In
        </button>
    </div>
</div>

<div class="collapse <?= empty($records) || !empty($errors) ? 'show' : '' ?>" id="newStockInForm">
    <div class="card mb-3 border-success">
        <div class="card-header bg-success text-white">
            <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Stock In Receipt</h6>
        </div>
        <div class="card-body">
            <form id="stockInForm" method="post">
                <?= csrf_field() ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Receipt No <span class="text-danger">*</span></label>
                        <input type="text" name="receipt_no" class="form-control" value="<?= escape(post('receipt_no', $receiptNo)) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= post('supplier_id') == $s['id'] ? 'selected' : '' ?>><?= escape($s['supplier_code'] . ' - ' . $s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Received Date <span class="text-danger">*</span></label>
                        <input type="date" name="received_date" class="form-control" value="<?= escape(post('received_date', date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Supplier Bill No</label>
                        <input type="text" name="bill_no" class="form-control" value="<?= escape(post('bill_no')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bill Date</label>
                        <input type="date" name="bill_date" class="form-control" value="<?= escape(post('bill_date')) ?>">
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" value="<?= escape(post('notes')) ?>" placeholder="Optional notes">
                    </div>
                </div>

                <h6 class="fw-bold mb-2">Items <span class="text-danger">*</span></h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm stock-in-items-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30%">Item</th>
                                <th style="width:12%">Quantity</th>
                                <th style="width:8%">Unit</th>
                                <th style="width:12%">Unit Price</th>
                                <th style="width:10%">Total</th>
                                <th style="width:12%">Batch No</th>
                                <th style="width:10%">Expiry</th>
                                <th style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsContainer"></tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
                    <button type="button" id="addItemBtn" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i>Add Item
                    </button>
                    <div class="text-end">
                        <strong>Grand Total: NRs. <span id="grandTotal">0.00</span></strong>
                        <input type="hidden" name="total_amount" id="totalAmount" value="0">
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Stock In</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Stock In History</h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($records)): ?>
        <div class="text-center text-muted py-4">
            <i class="fas fa-arrow-down fa-2x mb-2"></i>
            <p class="mb-0">No stock in records yet</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Receipt No</th>
                        <th>Supplier</th>
                        <th>Received Date</th>
                        <th>Total Amount</th>
                        <th>Received By</th>
                        <th>Notes</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= escape($r['receipt_no']) ?></td>
                        <td><?= escape($r['supplier_name'] ?? '-') ?></td>
                        <td><?= format_date($r['received_date']) ?></td>
                        <td><?= format_currency($r['total_amount']) ?></td>
                        <td><?= escape($r['received_by_name'] ?? 'N/A') ?></td>
                        <td><?= escape(truncate($r['notes'] ?? '-', 40)) ?></td>
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
