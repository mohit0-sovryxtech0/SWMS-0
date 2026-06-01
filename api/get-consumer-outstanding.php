<?php
require_once __DIR__ . '/../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

try {
    $consumerId = intval(get('consumer_id'));
    $consumerNo = trim(get('consumer_no', ''));

    if (!$consumerId && empty($consumerNo)) {
        json_error('Consumer ID or Consumer No is required');
    }

    if ($consumerNo) {
        $consumer = db()->fetchOne(
            "SELECT id, consumer_no, full_name, mobile, ward_no
             FROM consumers
             WHERE consumer_no = ? AND deleted_at IS NULL",
            [$consumerNo]
        );
        if (!$consumer) {
            json_error('Consumer not found');
        }
        $consumerId = $consumer['id'];
    } else {
        $consumer = db()->fetchOne(
            "SELECT id, consumer_no, full_name, mobile, ward_no
             FROM consumers WHERE id = ? AND deleted_at IS NULL",
            [$consumerId]
        );
        if (!$consumer) {
            json_error('Consumer not found');
        }
    }

    $bills = db()->fetchAll(
        "SELECT b.id, b.bill_no, b.total_amount, b.paid_amount, b.due_amount,
                b.billing_period_start, b.billing_period_end, b.due_date, b.status
         FROM bills b
         WHERE b.consumer_id = ?
         AND b.deleted_at IS NULL
         AND b.status IN ('pending', 'partial', 'overdue')
         AND b.due_amount > 0
         ORDER BY b.due_date ASC",
        [$consumerId]
    );

    $totalOutstanding = 0;
    $overdueCount = 0;
    $totalOverdue = 0;
    $billDetails = [];

    foreach ($bills as $b) {
        $due = floatval($b['due_amount']);
        $totalOutstanding += $due;

        if ($b['status'] === 'overdue' || strtotime($b['due_date']) < time()) {
            $overdueCount++;
            $totalOverdue += $due;
        }

        $billDetails[] = [
            'id' => intval($b['id']),
            'bill_no' => $b['bill_no'],
            'total_amount' => floatval($b['total_amount']),
            'paid_amount' => floatval($b['paid_amount']),
            'due_amount' => $due,
            'period' => format_date($b['billing_period_start']) . ' - ' . format_date($b['billing_period_end']),
            'due_date' => $b['due_date'],
            'status' => $b['status']
        ];
    }

    json_success([
        'consumer' => $consumer,
        'total_outstanding' => $totalOutstanding,
        'total_outstanding_formatted' => format_currency($totalOutstanding),
        'overdue_count' => $overdueCount,
        'total_overdue' => $totalOverdue,
        'total_overdue_formatted' => format_currency($totalOverdue),
        'bill_count' => count($bills),
        'bills' => $billDetails
    ]);
} catch (Exception $e) {
    json_error($e->getMessage());
}
