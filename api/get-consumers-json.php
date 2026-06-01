<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!Auth::check()) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey) || $apiKey !== ENCRYPTION_KEY) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

$search = trim(get('search', ''));
$ward = get('ward', '');
$status = get('status', '');
$connectionType = get('connection_type', '');
$page = max(1, (int) get('page', 1));
$limit = min(100, max(1, (int) get('limit', 25)));
$offset = ($page - 1) * $limit;

$where = "WHERE c.deleted_at IS NULL";
$params = [];

if ($search !== '') {
    $where .= " AND (c.full_name LIKE :search OR c.consumer_no LIKE :search2 OR c.mobile LIKE :search3)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
    $params['search3'] = "%{$search}%";
}
if ($ward !== '') {
    $where .= " AND c.ward_no = :ward";
    $params['ward'] = (int)$ward;
}
if ($status !== '') {
    $where .= " AND c.status = :status";
    $params['status'] = $status;
}
if ($connectionType !== '') {
    $where .= " AND c.connection_type = :connection_type";
    $params['connection_type'] = $connectionType;
}

try {
    $total = db()->fetchColumn("SELECT COUNT(*) FROM consumers c {$where}", $params);

    $consumers = db()->fetchAll(
        "SELECT c.id, c.consumer_no, c.full_name, c.mobile, c.ward_no, c.connection_type, c.status,
                cat.name as category_name
         FROM consumers c
         LEFT JOIN consumer_categories cat ON c.category_id = cat.id
         {$where}
         ORDER BY c.full_name ASC
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );

    $results = array_map(function($c) {
        return [
            'id' => (int)$c['id'],
            'text' => $c['consumer_no'] . ' - ' . $c['full_name'],
            'consumer_no' => $c['consumer_no'],
            'full_name' => $c['full_name'],
            'mobile' => $c['mobile'],
            'ward_no' => $c['ward_no'],
            'connection_type' => $c['connection_type'],
            'status' => $c['status'],
            'category_name' => $c['category_name'],
        ];
    }, $consumers);

    echo json_encode([
        'success' => true,
        'results' => $results,
        'total' => (int)$total,
        'page' => $page,
        'more' => ($offset + $limit) < $total,
    ]);
} catch (Exception $e) {
    error_log("API get-consumers-json error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
