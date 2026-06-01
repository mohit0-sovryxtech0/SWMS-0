<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

Auth::requireAuth();

$q = trim(get('q', ''));
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$consumers = db()->fetchAll(
    "SELECT id, consumer_no, full_name, mobile, ward_no
     FROM consumers
     WHERE (consumer_no LIKE ? OR full_name LIKE ? OR mobile LIKE ?)
     AND deleted_at IS NULL
     ORDER BY full_name ASC
     LIMIT 20",
    ["%{$q}%", "%{$q}%", "%{$q}%"]
);

$results = array_map(function($c) {
    return [
        'id' => $c['id'],
        'label' => $c['full_name'] . ' (' . $c['consumer_no'] . ')',
        'value' => $c['full_name'],
        'consumer_no' => $c['consumer_no']
    ];
}, $consumers);

echo json_encode($results);
