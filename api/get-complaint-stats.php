<?php
require_once __DIR__ . '/../includes/config.php';

Auth::requireAuth();

header('Content-Type: application/json');

try {
    $stats = db()->fetchAll(
        "SELECT status, COUNT(*) as count
         FROM complaints
         WHERE deleted_at IS NULL
         GROUP BY status"
    );

    $result = [
        'total' => 0,
        'open' => 0,
        'in_progress' => 0,
        'resolved' => 0,
        'closed' => 0,
        'reopened' => 0,
    ];

    foreach ($stats as $row) {
        $result[$row['status']] = (int) $row['count'];
        $result['total'] += (int) $row['count'];
    }

    // Priority breakdown
    $priorities = db()->fetchAll(
        "SELECT priority, COUNT(*) as count
         FROM complaints
         WHERE deleted_at IS NULL AND status NOT IN ('resolved', 'closed')
         GROUP BY priority"
    );

    $priorityBreakdown = [];
    foreach ($priorities as $p) {
        $priorityBreakdown[$p['priority']] = (int) $p['count'];
    }

    $result['priority_breakdown'] = $priorityBreakdown;

    // Category breakdown
    $categories = db()->fetchAll(
        "SELECT cat.name, COUNT(c.id) as count
         FROM complaints c
         JOIN complaint_categories cat ON c.category_id = cat.id
         WHERE c.deleted_at IS NULL
         GROUP BY cat.name
         ORDER BY count DESC"
    );

    $result['category_breakdown'] = $categories;

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch complaint statistics.',
        'error' => $e->getMessage()
    ]);
}
