<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/WorkflowEngine.php';
$pageTitle = 'Billing Cycles';
$breadcrumbs = [
    ['label' => 'Home', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Cycles'],
];
RBAC::requirePermission('bills.generate');
$error = '';
$success = '';

$action = post('action') ?: get('action');

try {
    if (isPost() && $action === 'create_cycle') {
        if (!verify_csrf(post('csrf_token'))) throw new Exception('Security validation failed');
        $id = WorkflowEngine::createBillingCycle([
            'fiscal_year_id' => intval(post('fiscal_year_id')),
            'billing_month' => intval(post('billing_month')),
            'billing_period_start' => post('billing_period_start'),
            'billing_period_end' => post('billing_period_end'),
            'due_date' => post('due_date'),
            'reading_cutoff_date' => post('reading_cutoff_date'),
            'target_consumers' => intval(post('target_consumers')),
        ]);
        $success = "Billing cycle created successfully";
        if (!isAjax()) redirect(ADMIN_URL . 'billing/cycles.php');
    }

    if (isPost() && $action === 'run_cycle') {
        if (!verify_csrf(post('csrf_token'))) throw new Exception('Security validation failed');
        $cycleId = intval(post('cycle_id'));
        $result = WorkflowEngine::runBillingCycle($cycleId);
        $success = "Billing cycle completed: {$result['generated']} bill(s) generated";
        if (!empty($result['errors'])) {
            $success .= '. Errors: ' . implode('; ', $result['errors']);
        }
        if (isAjax()) { json_success([], $success); }
    }

    if ($action === 'close_cycle' && get('id')) {
        if (!verify_csrf(get('csrf_token'))) throw new Exception('Security validation failed');
        WorkflowEngine::closeBillingCycle(intval(get('id')));
        $success = 'Billing cycle closed';
        redirect(ADMIN_URL . 'billing/cycles.php');
    }

    if ($action === 'delete_cycle' && get('id')) {
        if (!verify_csrf(get('csrf_token'))) throw new Exception('Security validation failed');
        db()->update('billing_cycles', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => intval(get('id'))]);
        $success = 'Cycle deleted';
        redirect(ADMIN_URL . 'billing/cycles.php');
    }
} catch (Exception $e) {
    if (isAjax()) { json_error($e->getMessage()); }
    $error = $e->getMessage();
}

try {
    $fiscalYears = db()->fetchAll("SELECT id, year_code, label FROM fiscal_years ORDER BY start_date DESC");
    $currentFy = db()->fetchOne("SELECT id, year_code, label FROM fiscal_years WHERE is_current = 1 LIMIT 1");
} catch (Exception $e) {
    $fiscalYears = [];
    $currentFy = null;
}
try {
    $cycles = WorkflowEngine::getBillingCycles();
} catch (Exception $e) {
    $cycles = [];
}

$months = ['', 'Baisakh', 'Jestha', 'Ashad', 'Shrawan', 'Bhadra', 'Ashwin', 'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-sync-alt me-2 text-primary"></i>Billing Cycles</h4>
        <p class="text-muted mb-0">Manage monthly billing cycles from reading through collection</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cycleModal">
            <i class="fas fa-plus me-1"></i>New Cycle
        </button>
        <a href="generate.php" class="btn btn-outline-info btn-sm">
            <i class="fas fa-file-invoice me-1"></i>Generate Bills
        </a>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Cycle Code</th>
                        <th>Fiscal Year</th>
                        <th>Period</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Bills</th>
                        <th>Total Billed</th>
                        <th>Collected</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cycles as $c): ?>
                    <?php $progress = $c['total_billed'] > 0 ? round(($c['total_collected'] / $c['total_billed']) * 100, 1) : 0; ?>
                    <tr>
                        <td><strong><?= escape($c['cycle_code']) ?></strong></td>
                        <td><?= escape($c['fiscal_year_label']) ?></td>
                        <td><small><?= $c['billing_period_start'] ?> <br> to <?= $c['billing_period_end'] ?></small></td>
                        <td><?= $c['due_date'] ?></td>
                        <td>
                            <?php
                            $badges = [
                                'draft' => 'secondary',
                                'reading_in_progress' => 'info',
                                'billing_in_progress' => 'warning',
                                'bills_generated' => 'primary',
                                'collection_in_progress' => 'primary',
                                'closed' => 'success',
                            ];
                            $label = $badges[$c['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $label ?>"><?= str_replace('_', ' ', ucfirst($c['status'])) ?></span>
                        </td>
                        <td><?= intval($c['bills_generated']) ?></td>
                        <td class="text-end"><?= $c['total_billed'] ? number_format($c['total_billed'], 2) : '-' ?></td>
                        <td>
                            <?php if ($c['total_collected'] > 0): ?>
                            <div class="d-flex align-items-center gap-2">
                                <span><?= number_format($c['total_collected'], 2) ?></span>
                                <div class="progress flex-grow-1" style="height:6px;max-width:80px">
                                    <div class="progress-bar bg-success" style="width:<?= $progress ?>%"></div>
                                </div>
                                <small><?= $progress ?>%</small>
                            </div>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($c['status'] === 'draft'): ?>
                            <form method="post" class="d-inline run-cycle-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="run_cycle">
                                <input type="hidden" name="cycle_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success run-cycle-btn" title="Run Billing">
                                    <i class="fas fa-play me-1"></i>Run
                                </button>
                            </form>
                            <a href="?action=delete_cycle&id=<?= $c['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this cycle?')"><i class="fas fa-trash"></i></a>
                            <?php elseif ($c['status'] === 'bills_generated' || $c['status'] === 'collection_in_progress'): ?>
                            <a href="?action=close_cycle&id=<?= $c['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Close this billing cycle?')"><i class="fas fa-check me-1"></i>Close</a>
                            <?php elseif ($c['status'] === 'closed'): ?>
                            <span class="text-success"><i class="fas fa-check-circle"></i> Closed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cycles)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No billing cycles found. Create one to start the monthly billing process.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Cycle Modal -->
<div class="modal fade" id="cycleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_cycle">
                <div class="modal-header">
                    <h5><i class="fas fa-plus-circle me-2 text-primary"></i>New Billing Cycle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Fiscal Year <span class="text-danger">*</span></label>
                            <select name="fiscal_year_id" class="form-select" required>
                                <?php foreach ($fiscalYears as $fy): ?>
                                <option value="<?= $fy['id'] ?>" <?= ($currentFy && $currentFy['id'] == $fy['id']) ? 'selected' : '' ?>>
                                    <?= escape($fy['label']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Billing Month <span class="text-danger">*</span></label>
                            <select name="billing_month" class="form-select" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>><?= $months[$m] ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target Consumers</label>
                            <input type="number" name="target_consumers" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Period Start <span class="text-danger">*</span></label>
                            <input type="date" name="billing_period_start" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Period End <span class="text-danger">*</span></label>
                            <input type="date" name="billing_period_end" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reading Cutoff Date</label>
                            <input type="date" name="reading_cutoff_date" class="form-control">
                            <small class="text-muted">Last date for meter readings to be included in this cycle</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create Cycle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
ob_start(); ?>
<script>
$(function() {
    $('.run-cycle-form').on('submit', function(e) {
        e.preventDefault();
        if (!confirm('Run this billing cycle? This will generate bills for all active consumers and send SMS notifications.')) return;

        var form = $(this);
        var btn = form.find('.run-cycle-btn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Running...');

        $.post('', form.serialize(), function(res) {
            if (res.success) {
                alert(res.message);
                location.reload();
            } else {
                alert(res.message || 'Error running cycle');
                btn.prop('disabled', false).html('<i class="fas fa-play me-1"></i>Run');
            }
        }, 'json').fail(function(xhr) {
            var msg = 'Error';
            try { var r = JSON.parse(xhr.responseText); msg = r.message || msg; } catch(e) {}
            alert(msg);
            btn.prop('disabled', false).html('<i class="fas fa-play me-1"></i>Run');
        });
    });
});
</script>
<?php
$extraJs = ob_get_clean();
require_once __DIR__ . '/../includes/footer.php';
?>
