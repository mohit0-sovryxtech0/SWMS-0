<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('users.delete');

if (!isPost()) {
    redirect(ADMIN_URL . 'users/index.php');
}

$csrf = post('csrf_token');
if (!verify_csrf($csrf)) {
    alert_error('Invalid security token.');
    redirect(ADMIN_URL . 'users/index.php');
}

$userId = (int) post('user_id', 0);

if ($userId <= 0) {
    alert_error('Invalid user ID.');
    redirect(ADMIN_URL . 'users/index.php');
}

if ($userId === Auth::id()) {
    alert_error('You cannot delete your own account.');
    redirect(ADMIN_URL . 'users/index.php');
}

$user = db()->fetchOne("SELECT id, name, email FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
if (!$user) {
    alert_error('User not found.');
    redirect(ADMIN_URL . 'users/index.php');
}

db()->softDelete('users', $userId);
db()->update('users', [
    'status' => 'inactive',
    'deleted_by' => Auth::id(),
    'updated_at' => date('Y-m-d H:i:s'),
], 'id = :id', ['id' => $userId]);

log_activity(Auth::id(), 'delete', 'users', "Soft-deleted user: {$user['name']} ({$user['email']})", ['user_id' => $userId]);
Security::logSecurityEvent('user_deleted', ['deleted_user_id' => $userId, 'deleted_by' => Auth::id()]);

alert_success('User deleted successfully.');

if (isAjax()) {
    json_success([], 'User deleted successfully.');
}

redirect(ADMIN_URL . 'users/index.php');
