<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Attendance Management';
$breadcrumbs = [
    ['label' => 'Employees', 'url' => ADMIN_URL . 'employees/index.php'],
    ['label' => 'Attendance']
];
RBAC::requirePermission('attendance.mark');

require_once __DIR__ . '/../includes/header.php';

$date = get('date', date('Y-m-d'));
$month = get('month', date('Y-m'));
$reportMonth = $month;

$errors = [];

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token.');
    } else {
        $attendanceDate = post('attendance_date', date('Y-m-d'));
        $employeeIds = post('employee_ids', []);
        $statuses = post('statuses', []);

        if (empty($attendanceDate)) {
            alert_error('Please select a date.');
        } elseif (empty($employeeIds)) {
            alert_error('No employees found to mark attendance.');
        } else {
            try {
                db()->beginTransaction();
                $count = 0;

                foreach ($employeeIds as $empId) {
                    $empId = (int) $empId;
                    $status = $statuses[$empId] ?? 'absent';
                    $validStatuses = ['present', 'absent', 'late', 'half_day', 'leave'];
                    if (!in_array($status, $validStatuses)) {
                        $status = 'absent';
                    }

                    $existing = db()->fetchColumn(
                        "SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND date = ?",
                        [$empId, $attendanceDate]
                    );

                    if ($existing > 0) {
                        db()->update(
                            'attendance',
                            ['status' => $status, 'marked_by' => Auth::id()],
                            'employee_id = :eid AND date = :d',
                            ['eid' => $empId, 'd' => $attendanceDate]
                        );
                    } else {
                        db()->insert('attendance', [
                            'employee_id' => $empId,
                            'date' => $attendanceDate,
                            'status' => $status,
                            'marked_by' => Auth::id(),
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                    $count++;
                }

                db()->commit();
                log_activity(Auth::id(), 'mark_attendance', 'employees', "Marked attendance for {$count} employees on {$attendanceDate}");
                alert_success("Attendance marked for {$count} employees on {$attendanceDate}.");
            } catch (Exception $e) {
                db()->rollback();
                error_log("Attendance error: " . $e->getMessage());
                alert_error('Failed to mark attendance. Please try again.');
            }
        }
    }
}

$activeEmployees = db()->fetchAll(
    "SELECT e.id, e.employee_code, e.full_name, e.department_id, e.designation_id,
            d.name as department_name, dg.name as designation_name,
            (SELECT a.status FROM attendance a WHERE a.employee_id = e.id AND a.date = :date) as today_status
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     LEFT JOIN designations dg ON e.designation_id = dg.id
     WHERE e.deleted_at IS NULL AND e.status = 'active'
     ORDER BY e.full_name ASC",
    ['date' => $date]
);

$reportData = db()->fetchAll(
    "SELECT e.id, e.employee_code, e.full_name, d.name as department_name,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as total_absent,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as total_late,
            SUM(CASE WHEN a.status = 'half_day' THEN 1 ELSE 0 END) as total_half_day,
            SUM(CASE WHEN a.status = 'leave' THEN 1 ELSE 0 END) as total_leave,
            COUNT(a.id) as total_days
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     LEFT JOIN attendance a ON e.id = a.employee_id AND DATE_FORMAT(a.date, '%Y-%m') = :month
     WHERE e.deleted_at IS NULL AND e.status = 'active'
     GROUP BY e.id
     ORDER BY e.full_name ASC",
    ['month' => $reportMonth]
);

$attendanceStatuses = [
    'present' => 'Present',
    'absent' => 'Absent',
    'late' => 'Late',
    'half_day' => 'Half Day',
    'leave' => 'Leave',
];
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Attendance Management</h4>
            <p>Mark daily attendance and view monthly reports</p>
        </div>
        <a href="<?= ADMIN_URL ?>employees/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Employees
        </a>
    </div>
</div>

<?= display_alert() ?>

<div class="card mb-4">
    <div class="card-header">
        <h5>Mark Attendance</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="filter-bar mb-3">
            <div class="form-group" style="min-width:200px;">
                <label class="form-label fw500">Select Date</label>
                <input type="date" name="date" class="form-control" value="<?= escape($date) ?>" onchange="this.form.submit()">
            </div>
        </form>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="attendance_date" value="<?= escape($date) ?>">

            <div class="table-container">
                <table class="table" id="attendanceTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Employee Code</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th style="width:400px">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activeEmployees)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No active employees found</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($activeEmployees as $i => $e): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= escape($e['employee_code']) ?></strong></td>
                            <td><?= escape($e['full_name']) ?></td>
                            <td><?= escape($e['department_name'] ?? '-') ?></td>
                            <td><?= escape($e['designation_name'] ?? '-') ?></td>
                            <td>
                                <input type="hidden" name="employee_ids[]" value="<?= $e['id'] ?>">
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php foreach ($attendanceStatuses as $val => $label):
                                        $checked = ($e['today_status'] === $val) ? 'checked' : '';
                                        $color = $val === 'present' ? 'success' : ($val === 'absent' ? 'danger' : ($val === 'late' ? 'warning' : ($val === 'half_day' ? 'info' : 'secondary')));
                                    ?>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" class="form-check-input attendance-radio" name="statuses[<?= $e['id'] ?>]" id="status_<?= $e['id'] ?>_<?= $val ?>" value="<?= $val ?>" <?= $checked ?>>
                                        <label class="form-check-label" for="status_<?= $e['id'] ?>_<?= $val ?>">
                                            <span class="badge bg-<?= $color ?>" style="cursor:pointer;"><?= $label ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($activeEmployees)): ?>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAllStatus('present')">
                        <i class="fas fa-check-circle"></i> All Present
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="setAllStatus('absent')">
                        <i class="fas fa-times-circle"></i> All Absent
                    </button>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Mark Attendance
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Monthly Attendance Report</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="filter-bar mb-3">
            <div class="form-group" style="min-width:200px;">
                <label class="form-label fw500">Select Month</label>
                <input type="month" name="month" class="form-control" value="<?= escape($reportMonth) ?>" onchange="this.form.submit()">
            </div>
            <?php if (get('date')): ?>
            <input type="hidden" name="date" value="<?= escape($date) ?>">
            <?php endif; ?>
        </form>

        <div class="table-container">
            <table class="table" id="attendanceReportTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Half Day</th>
                        <th>Leave</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">No attendance data for this month</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($reportData as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= escape($r['employee_code']) ?></strong></td>
                        <td><?= escape($r['full_name']) ?></td>
                        <td><?= escape($r['department_name'] ?? '-') ?></td>
                        <td><span class="badge bg-success"><?= (int)$r['total_present'] ?></span></td>
                        <td><span class="badge bg-danger"><?= (int)$r['total_absent'] ?></span></td>
                        <td><span class="badge bg-warning"><?= (int)$r['total_late'] ?></span></td>
                        <td><span class="badge bg-info"><?= (int)$r['total_half_day'] ?></span></td>
                        <td><span class="badge bg-secondary"><?= (int)$r['total_leave'] ?></span></td>
                        <td><strong><?= (int)$r['total_days'] ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function setAllStatus(status) {
    var radios = document.querySelectorAll('.attendance-radio');
    radios.forEach(function(radio) {
        if (radio.value === status) {
            radio.checked = true;
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var labels = document.querySelectorAll('.form-check-label .badge');
    labels.forEach(function(badge) {
        badge.addEventListener('click', function() {
            var radio = this.closest('.form-check').querySelector('.form-check-input');
            if (radio) radio.checked = true;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
