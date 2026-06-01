<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('employees.delete');

if (!isPost()) {
    redirect(ADMIN_URL . 'employees/index.php');
}

$csrf = post('csrf_token');
if (!verify_csrf($csrf)) {
    alert_error('Invalid security token.');
    redirect(ADMIN_URL . 'employees/index.php');
}

$employeeId = (int) post('employee_id', 0);

if ($employeeId <= 0) {
    alert_error('Invalid employee ID.');
    redirect(ADMIN_URL . 'employees/index.php');
}

$employee = db()->fetchOne("SELECT id, employee_code, full_name, photo FROM employees WHERE id = ? AND deleted_at IS NULL", [$employeeId]);
if (!$employee) {
    alert_error('Employee not found.');
    redirect(ADMIN_URL . 'employees/index.php');
}

try {
    db()->beginTransaction();

    db()->softDelete('employees', $employeeId);
    db()->update('employees', [
        'status' => 'inactive',
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $employeeId]);

    db()->commit();

    log_activity(Auth::id(), 'delete', 'employees', "Deleted employee: {$employee['employee_code']} - {$employee['full_name']}", ['employee_id' => $employeeId]);
    Security::logSecurityEvent('employee_deleted', ['employee_id' => $employeeId, 'employee_code' => $employee['employee_code']]);

    alert_success('Employee deleted successfully.');
} catch (Exception $e) {
    db()->rollback();
    error_log("Employee delete error: " . $e->getMessage());
    alert_error('Failed to delete employee. Please try again.');
}

if (isAjax()) {
    json_success([], 'Employee deleted successfully.');
}

redirect(ADMIN_URL . 'employees/index.php');
