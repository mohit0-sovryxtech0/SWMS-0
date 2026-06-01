<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

Auth::requireAuth();

$id = (int)get('id');
if (!$id) {
    json_error('Invalid fiscal year ID');
}

$fy = db()->fetchOne("SELECT id, year_code, label, start_date, end_date, is_current FROM fiscal_years WHERE id = ?", [$id]);

if (!$fy) {
    json_error('Fiscal year not found');
}

json_success($fy, 'Fiscal year found');
