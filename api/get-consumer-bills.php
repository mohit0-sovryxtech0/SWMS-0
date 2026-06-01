<?php
require_once __DIR__ . '/../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

try {
    $action = get('action');
    $consumerId = intval(get('consumer_id'));
    $search = trim(get('q', ''));

    if ($action === 'search' && !empty($search)) {
        $consumers = db()->fetchAll(
            "SELECT c.id, c.consumer_no, CONCAT(c.full_name, ' (', c.consumer_no, ')') as label,
                    c.full_name, c.mobile, c.ward_no
             FROM consumers c
             WHERE c.deleted_at IS NULL
             AND (c.full_name LIKE :search OR c.consumer_no LIKE :search2 OR c.mobile LIKE :search3)
             AND c.status = 'active'
             LIMIT 20",
            [
                'search' => "%{$search}%",
                'search2' => "%{$search}%",
                'search3' => "%{$search}%"
            ]
        );

        $results = array_map(function($c) {
            return [
                'id' => $c['id'],
                'label' => $c['label'],
                'value' => $c['label'],
                'consumer_no' => $c['consumer_no'],
                'full_name' => $c['full_name'],
                'mobile' => $c['mobile'],
                'ward_no' => $c['ward_no']
            ];
        }, $consumers);

        json_response($results);
    }

    if ($action === 'info' && $consumerId) {
        $consumer = db()->fetchOne(
            "SELECT id, consumer_no, full_name, mobile, ward_no
             FROM consumers WHERE id = ? AND deleted_at IS NULL",
            [$consumerId]
        );
        if (!$consumer) {
            json_error('Consumer not found');
        }
        json_success($consumer);
    }

    if ($consumerId) {
        $bills = db()->fetchAll(
            "SELECT b.id, b.bill_no, b.billing_period_start, b.billing_period_end,
                    b.total_amount, b.paid_amount, b.due_amount, b.status, b.due_date
             FROM bills b
             WHERE b.consumer_id = ?
             AND b.deleted_at IS NULL
             AND b.status IN ('pending', 'partial', 'overdue')
             AND b.due_amount > 0
             ORDER BY b.due_date ASC",
            [$consumerId]
        );

        $data = array_map(function($b) {
            return [
                'id' => intval($b['id']),
                'bill_no' => $b['bill_no'],
                'billing_period_start' => format_date($b['billing_period_start']),
                'billing_period_end' => format_date($b['billing_period_end']),
                'total_amount' => format_currency($b['total_amount']),
                'paid_amount' => format_currency($b['paid_amount']),
                'due_amount' => format_currency($b['due_amount']),
                'status' => $b['status'],
                'due_date' => format_date($b['due_date'])
            ];
        }, $bills);

        $consumer = db()->fetchOne(
            "SELECT id, consumer_no, full_name FROM consumers WHERE id = ?",
            [$consumerId]
        );

        json_success([
            'consumer' => $consumer,
            'bills' => $bills,
            'data' => $bills
        ]);
    }

    json_error('Invalid request');
} catch (Exception $e) {
    json_error($e->getMessage());
}
