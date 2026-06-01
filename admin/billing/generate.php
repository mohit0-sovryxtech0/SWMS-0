<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/BillingEngine.php';

Auth::requireAuth();
RBAC::requirePermission('bills.generate');

$pageTitle = 'Generate Bills';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Generate Bills']
];

$fiscalYears = db()->fetchAll("SELECT id, year_code, label, start_date, end_date FROM fiscal_years ORDER BY start_date DESC LIMIT 5");
$currentFy = db()->fetchOne("SELECT id, year_code, label, start_date, end_date FROM fiscal_years WHERE is_current = 1 LIMIT 1");
$apiUrl = API_URL;
$adminUrl = ADMIN_URL;

$error = '';

try {
    if (isPost()) {
        $action = post('action');
        if (!verify_csrf(post('csrf_token'))) throw new Exception('Security validation failed');

        $params = [
            'fiscal_year_id' => intval(post('fiscal_year_id')),
            'billing_start' => post('billing_start'),
            'billing_end' => post('billing_end'),
            'due_date' => post('due_date'),
            'generate_mode' => post('generate_mode'),
            'consumer_id' => intval(post('consumer_id')),
        ];

        $validator = validator($_POST, [
            'fiscal_year_id' => 'required|numeric',
            'billing_start' => 'required|date',
            'billing_end' => 'required|date',
            'due_date' => 'required|date',
            'generate_mode' => 'required|in:all,single'
        ]);
        if ($validator->fails()) throw new Exception($validator->firstError());

        if ($params['generate_mode'] === 'single' && !$params['consumer_id']) {
            throw new Exception('Please select a consumer');
        }

        if ($action === 'preview') {
            $result = BillingEngine::previewBills($params);
            if (empty($result['data'])) {
                $msg = 'No bills to generate';
                if ($result['skipped'] > 0) $msg .= ". {$result['skipped']} bills already exist.";
                json_error($msg);
            }

            $_SESSION['bill_preview'] = [
                'fiscal_year_id' => $params['fiscal_year_id'],
                'billing_start' => $params['billing_start'],
                'billing_end' => $params['billing_end'],
                'due_date' => $params['due_date'],
                'data' => $result['data']
            ];

            $rows = '';
            foreach ($result['data'] as $item) {
                $rows .= '<tr>
                    <td>' . escape($item['consumer_no']) . '</td>
                    <td>' . escape($item['consumer_name']) . '</td>
                    <td>' . escape($item['meter_no']) . '</td>
                    <td>' . number_format($item['previous_reading'], 2) . '</td>
                    <td>' . number_format($item['current_reading'], 2) . '</td>
                    <td>' . number_format($item['consumption'], 2) . '</td>
                    <td>' . format_currency($item['base_fee']) . '</td>
                    <td>' . format_currency($item['total_amount']) . '</td>
                </tr>';
            }

            $summary = '<div class="alert alert-info mb-3">
                <strong>Summary:</strong> ' . count($result['data']) . ' bill(s) to generate.' .
                ($result['skipped'] > 0 ? '<br><span class="text-warning">' . $result['skipped'] . ' consumer(s) skipped (bills already exist).</span>' : '') .
            '</div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Consumer No</th>
                            <th>Name</th>
                            <th>Meter No</th>
                            <th>Prev Reading</th>
                            <th>Curr Reading</th>
                            <th>Consumption</th>
                            <th>Base Fee</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>';

            json_success(['html' => $summary, 'count' => count($result['data'])]);
        }

        if ($action === 'generate') {
            $preview = $_SESSION['bill_preview'] ?? null;
            if (!$preview || $preview['fiscal_year_id'] != $params['fiscal_year_id']) {
                throw new Exception('Please preview bills first');
            }

            $genResult = BillingEngine::generateBills($preview, $params);
            unset($_SESSION['bill_preview']);

            $msg = "Successfully generated {$genResult['generated']} bill(s)!";
            if (!empty($genResult['errors'])) {
                $msg .= ' Errors: ' . implode('; ', $genResult['errors']);
            }
            json_success([], $msg);
        }
    }
} catch (Exception $e) {
    if (isAjax()) {
        json_error($e->getMessage());
    }
    $error = $e->getMessage();
}

ob_start(); ?>
<script>
$(function() {
    let selectedConsumerId = null;
    let consumerSearchTimeout;

    $('#fiscal_year_id').change(function() {
        var fyId = $(this).val();
        if (fyId) {
            $.get('<?= $apiUrl ?>get-fiscal-year.php', { id: fyId }, function(res) {
                if (res.success && res.data) {
                    $('#billing_start').val(res.data.start_date);
                    $('#billing_end').val(res.data.end_date);
                }
            });
        }
    });

    $('#generateMode input').change(function() {
        if ($(this).val() === 'single') {
            $('#consumerSearchGroup').show();
            $('#consumerSearch').prop('required', true);
        } else {
            $('#consumerSearchGroup').hide();
            $('#consumerSearch').prop('required', false);
        }
    });

    $('#consumerSearch').on('keyup', function() {
        clearTimeout(consumerSearchTimeout);
        var q = $(this).val();
        if (q.length < 2) return;
        consumerSearchTimeout = setTimeout(function() {
            $.get('<?= $apiUrl ?>search-consumers.php', { q: q }, function(data) {
                var list = $('#consumerResults');
                list.empty().show();
                if (data.length === 0) {
                    list.append('<div class="dropdown-item text-muted">No results</div>');
                    return;
                }
                $.each(data, function(i, item) {
                    var a = $('<a class="dropdown-item" href="#"></a>')
                        .text(item.label + ' (' + item.consumer_no + ')')
                        .data('id', item.id)
                        .data('label', item.label)
                        .on('click', function(e) {
                            e.preventDefault();
                            selectedConsumerId = $(this).data('id');
                            $('#consumerSearch').val($(this).data('label'));
                            list.hide();
                        });
                    list.append(a);
                });
            });
        }, 300);
    });
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#consumerSearchGroup').length) {
            $('#consumerResults').hide();
        }
    });

    $('#previewBtn').click(function() {
        var valid = validateForm();
        if (!valid) return;

        var data = {
            action: 'preview',
            fiscal_year_id: $('#fiscal_year_id').val(),
            billing_start: $('#billing_start').val(),
            billing_end: $('#billing_end').val(),
            due_date: $('#due_date').val(),
            generate_mode: $('input[name="generate_mode"]:checked').val(),
            consumer_id: selectedConsumerId,
            csrf_token: '<?= csrf_token() ?>'
        };

        $('#previewBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Previewing...');

        $.post('', data, function(res) {
            if (res.success) {
                $('#previewTable').html(res.html);
                $('#previewSection').show();
                $('#generateBtn').prop('disabled', false);
            } else {
                alert(res.message || 'No bills to preview');
            }
            $('#previewBtn').prop('disabled', false).html('<i class="fas fa-eye me-1"></i>Preview Bills');
        }, 'json').fail(function(xhr) {
            var msg = 'Error generating preview';
            try {
                var r = JSON.parse(xhr.responseText);
                msg = r.message || msg;
            } catch(e) {}
            alert(msg);
            $('#previewBtn').prop('disabled', false).html('<i class="fas fa-eye me-1"></i>Preview Bills');
        });
    });

    $('#generateBtn').click(function() {
        if (!confirm('Are you sure you want to generate these bills? This cannot be undone.')) return;

        var data = {
            action: 'generate',
            fiscal_year_id: $('#fiscal_year_id').val(),
            billing_start: $('#billing_start').val(),
            billing_end: $('#billing_end').val(),
            due_date: $('#due_date').val(),
            generate_mode: $('input[name="generate_mode"]:checked').val(),
            consumer_id: selectedConsumerId,
            csrf_token: '<?= csrf_token() ?>'
        };

        $('#generateBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Generating...');

        $.post('', data, function(res) {
            if (res.success) {
                alert(res.message);
                window.location.href = '<?= $adminUrl ?>billing/index.php';
            } else {
                alert(res.message || 'Generation failed');
                $('#generateBtn').prop('disabled', false).html('<i class="fas fa-play me-1"></i>Generate Bills');
            }
        }, 'json').fail(function(xhr) {
            var msg = 'Error';
            try {
                var r = JSON.parse(xhr.responseText);
                msg = r.message || msg;
            } catch(e) {}
            alert(msg);
            $('#generateBtn').prop('disabled', false).html('<i class="fas fa-play me-1"></i>Generate Bills');
        });
    });

    function validateForm() {
        var fy = $('#fiscal_year_id').val();
        var start = $('#billing_start').val();
        var end = $('#billing_end').val();
        var due = $('#due_date').val();

        if (!fy) { alert('Please select fiscal year'); return false; }
        if (!start) { alert('Please select billing start date'); return false; }
        if (!end) { alert('Please select billing end date'); return false; }
        if (!due) { alert('Please select due date'); return false; }
        if (start >= end) { alert('Billing end date must be after start date'); return false; }

        return true;
    }
});
</script>
<?php
$extraJs = ob_get_clean();

include_once ADMIN_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title mb-0">
            <i class="fas fa-plus-circle me-2 text-primary"></i>Generate Bills
        </h4>
        <a href="<?= ADMIN_URL ?>billing/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Bills
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" id="billForm" class="needs-validation" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Fiscal Year <span class="text-danger">*</span></label>
                    <select name="fiscal_year_id" id="fiscal_year_id" class="form-select" required>
                        <option value="">Select Fiscal Year</option>
                        <?php foreach ($fiscalYears as $fy): ?>
                        <option value="<?= $fy['id'] ?>" <?= ($currentFy && $currentFy['id'] == $fy['id']) ? 'selected' : '' ?>>
                            <?= escape($fy['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Billing Period Start <span class="text-danger">*</span></label>
                    <input type="date" name="billing_start" id="billing_start" class="form-control" required
                           value="<?= $currentFy ? date('Y-m-d', strtotime($currentFy['start_date'])) : '' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Billing Period End <span class="text-danger">*</span></label>
                    <input type="date" name="billing_end" id="billing_end" class="form-control" required
                           value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Due Date <span class="text-danger">*</span></label>
                    <input type="date" name="due_date" id="due_date" class="form-control" required
                           value="<?= date('Y-m-d', strtotime('+15 days')) ?>">
                </div>
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <label class="form-label fw-semibold">Generate Mode</label>
                <div id="generateMode">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="generate_mode" id="modeAll" value="all" checked>
                        <label class="form-check-label" for="modeAll">
                            <i class="fas fa-users me-1"></i>Generate for All Active Consumers
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="generate_mode" id="modeSingle" value="single">
                        <label class="form-check-label" for="modeSingle">
                            <i class="fas fa-user me-1"></i>Generate for Single Consumer
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-3" id="consumerSearchGroup" style="display:none">
                <label class="form-label">Search Consumer <span class="text-danger">*</span></label>
                <input type="text" id="consumerSearch" class="form-control" placeholder="Type consumer name or consumer no...">
                <div class="dropdown-menu" id="consumerResults" style="width:100%;max-height:200px;overflow-y:auto;display:none"></div>
                <div class="form-text">Type at least 2 characters to search</div>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="button" id="previewBtn" class="btn btn-info btn-lg">
                    <i class="fas fa-eye me-1"></i>Preview Bills
                </button>
                <button type="button" id="generateBtn" class="btn btn-success btn-lg" disabled>
                    <i class="fas fa-play me-1"></i>Generate Bills
                </button>
            </div>
        </form>
    </div>
</div>

<div id="previewSection" class="mt-3" style="display:none">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-table me-2"></i>Bill Preview</h5>
        </div>
        <div class="card-body" id="previewTable"></div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
