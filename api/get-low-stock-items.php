<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;

    $items = db()->fetchAll(
        "SELECT id, item_code, name, category, unit, current_stock, reorder_level, unit_price,
                (current_stock * unit_price) AS stock_value
         FROM inventory_items
         WHERE deleted_at IS NULL
           AND status = 'active'
           AND current_stock <= reorder_level
         ORDER BY (current_stock / reorder_level) ASC
         LIMIT ?",
        [$limit]
    );

    $totalLowStock = db()->fetchColumn(
        "SELECT COUNT(*) FROM inventory_items
         WHERE deleted_at IS NULL AND status = 'active' AND current_stock <= reorder_level"
    );

    $result = [];
    foreach ($items as $item) {
        $ratio = $item['reorder_level'] > 0 ? round(($item['current_stock'] / $item['reorder_level']) * 100, 1) : 0;
        $severity = 'low';
        if ($ratio <= 25) $severity = 'critical';
        elseif ($ratio <= 50) $severity = 'warning';

        $result[] = [
            'id' => (int)$item['id'],
            'item_code' => $item['item_code'],
            'name' => $item['name'],
            'category' => $item['category'],
            'unit' => $item['unit'],
            'current_stock' => (float)$item['current_stock'],
            'reorder_level' => (int)$item['reorder_level'],
            'unit_price' => (float)$item['unit_price'],
            'stock_value' => (float)$item['stock_value'],
            'stock_ratio_percent' => $ratio,
            'severity' => $severity,
            'needs_reorder' => $item['current_stock'] <= 0
        ];
    }

    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'total_low_stock' => (int)$totalLowStock,
        'count' => count($result),
        'data' => $result
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch low stock items',
        'error' => $e->getMessage()
    ]);
}
