<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['count' => 0]);
    exit;
}

$count = db()->fetchColumn(
    "SELECT COUNT(DISTINCT c.id) FROM bills b
     JOIN consumers c ON b.consumer_id = c.id
     WHERE b.status IN ('pending','partial','overdue') AND b.deleted_at IS NULL
     AND b.due_date < CURDATE() AND b.total_amount > b.paid_amount"
);

echo json_encode(['count' => intval($count)]);
