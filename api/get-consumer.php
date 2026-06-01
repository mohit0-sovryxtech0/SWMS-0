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

// Authenticate via session or API key
if (!Auth::check()) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey) || $apiKey !== ENCRYPTION_KEY) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

$id = get('id');
$consumerNo = get('consumer_no');

if (!$id && !$consumerNo) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Provide id or consumer_no parameter']);
    exit;
}

try {
    if ($id) {
        $consumer = db()->fetchOne(
            "SELECT c.*, cat.name as category_name
             FROM consumers c
             LEFT JOIN consumer_categories cat ON c.category_id = cat.id
             WHERE c.id = ? AND c.deleted_at IS NULL",
            [(int)$id]
        );
    } else {
        $consumer = db()->fetchOne(
            "SELECT c.*, cat.name as category_name
             FROM consumers c
             LEFT JOIN consumer_categories cat ON c.category_id = cat.id
             WHERE c.consumer_no = ? AND c.deleted_at IS NULL",
            [$consumerNo]
        );
    }

    if (!$consumer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Consumer not found']);
        exit;
    }

    $consumer['meter'] = db()->fetchOne(
        "SELECT * FROM meters WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1",
        [$consumer['id']]
    );

    $consumer['documents'] = db()->fetchAll(
        "SELECT id, title, document_type, file_path, created_at FROM consumer_documents WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC",
        [$consumer['id']]
    );

    $consumer['recent_bills'] = db()->fetchAll(
        "SELECT id, bill_no, bill_date, total_amount, status FROM bills WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY bill_date DESC LIMIT 5",
        [$consumer['id']]
    );

    echo json_encode([
        'success' => true,
        'data' => $consumer
    ]);
} catch (Exception $e) {
    error_log("API get-consumer error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
