<?php
class WorkflowEngine {

    // ============================================================
    // METER READING WORKFLOW
    // ============================================================

    public static function createRoute($data) {
        $id = db()->insert('meter_reading_routes', [
            'route_code' => $data['route_code'],
            'route_name' => $data['route_name'],
            'ward_no' => intval($data['ward_no'] ?? 0),
            'area_description' => $data['area_description'] ?? '',
            'estimated_consumers' => intval($data['estimated_consumers'] ?? 0),
            'assigned_reader_id' => intval($data['assigned_reader_id'] ?? 0) ?: null,
            'status' => $data['status'] ?? 'active',
            'created_by' => Auth::id(),
        ]);
        self::audit('meter_reading', 'create_route', 'meter_reading_routes', $id, null, json_encode($data));
        return $id;
    }

    public static function updateRoute($id, $data) {
        $old = db()->fetchOne("SELECT * FROM meter_reading_routes WHERE id = ?", [$id]);
        db()->update('meter_reading_routes', [
            'route_code' => $data['route_code'],
            'route_name' => $data['route_name'],
            'ward_no' => intval($data['ward_no'] ?? 0),
            'area_description' => $data['area_description'] ?? '',
            'estimated_consumers' => intval($data['estimated_consumers'] ?? 0),
            'assigned_reader_id' => intval($data['assigned_reader_id'] ?? 0) ?: null,
            'status' => $data['status'] ?? 'active',
        ], 'id = :id', ['id' => $id]);
        self::audit('meter_reading', 'update_route', 'meter_reading_routes', $id, json_encode($old), json_encode($data));
    }

    public static function deleteRoute($id) {
        db()->softDelete('meter_reading_routes', $id);
        self::audit('meter_reading', 'delete_route', 'meter_reading_routes', $id, null, $id);
    }

    public static function getRoutes($activeOnly = false) {
        $sql = "SELECT r.*, u.name AS reader_name,
                (SELECT COUNT(*) FROM route_consumers rc WHERE rc.route_id = r.id) AS consumer_count
                FROM meter_reading_routes r
                LEFT JOIN users u ON r.assigned_reader_id = u.id
                WHERE r.deleted_at IS NULL";
        if ($activeOnly) $sql .= " AND r.status = 'active'";
        $sql .= " ORDER BY r.route_name";
        return db()->fetchAll($sql);
    }

    public static function getRoute($id) {
        return db()->fetchOne(
            "SELECT r.*, u.name AS reader_name FROM meter_reading_routes r
             LEFT JOIN users u ON r.assigned_reader_id = u.id WHERE r.id = ?",
            [$id]
        );
    }

    public static function assignConsumersToRoute($routeId, $consumerIds) {
        db()->beginTransaction();
        try {
            db()->delete('route_consumers', 'route_id = :rid', ['rid' => $routeId]);
            $seq = 1;
            foreach ($consumerIds as $cid) {
                db()->insert('route_consumers', [
                    'route_id' => $routeId,
                    'consumer_id' => intval($cid),
                    'sequence_no' => $seq++,
                ]);
            }
            db()->update('meter_reading_routes', [
                'estimated_consumers' => count($consumerIds)
            ], 'id = :id', ['id' => $routeId]);
            db()->commit();
            self::audit('meter_reading', 'assign_consumers', 'meter_reading_routes', $routeId, null, json_encode($consumerIds));
            return true;
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }
    }

    public static function getRouteConsumers($routeId) {
        return db()->fetchAll(
            "SELECT rc.*, c.consumer_no, c.full_name, c.mobile, c.ward_no, c.tole, c.connection_type,
                    m.id AS meter_id, m.meter_no
             FROM route_consumers rc
             JOIN consumers c ON rc.consumer_id = c.id
             LEFT JOIN meters m ON m.consumer_id = c.id AND m.status = 'active' AND m.deleted_at IS NULL
             WHERE rc.route_id = ?
             ORDER BY rc.sequence_no, c.full_name",
            [$routeId]
        );
    }

    // Schedule management
    public static function createSchedule($data) {
        $id = db()->insert('meter_reading_schedules', [
            'route_id' => intval($data['route_id']),
            'fiscal_year_id' => intval($data['fiscal_year_id']),
            'billing_month' => intval($data['billing_month']),
            'schedule_start' => $data['schedule_start'],
            'schedule_end' => $data['schedule_end'],
            'target_consumers' => intval($data['target_consumers'] ?? 0),
            'assigned_to' => intval($data['assigned_to'] ?? 0) ?: null,
            'notes' => $data['notes'] ?? '',
            'created_by' => Auth::id(),
        ]);
        self::audit('meter_reading', 'create_schedule', 'meter_reading_schedules', $id, null, json_encode($data));
        return $id;
    }

    public static function updateScheduleStatus($id, $status) {
        $old = db()->fetchOne("SELECT status FROM meter_reading_schedules WHERE id = ?", [$id]);
        $updates = ['status' => $status];
        if ($status === 'in_progress') {
            $updates['readings_taken'] = db()->fetchColumn(
                "SELECT COUNT(*) FROM meter_readings WHERE route_id = (SELECT route_id FROM meter_reading_schedules WHERE id = ?)",
                [$id]
            );
        }
        if ($status === 'completed') {
            $updates['readings_taken'] = db()->fetchColumn(
                "SELECT COUNT(*) FROM meter_readings WHERE route_id = (SELECT route_id FROM meter_reading_schedules WHERE id = ?)",
                [$id]
            );
        }
        db()->update('meter_reading_schedules', $updates, 'id = :id', ['id' => $id]);
        self::audit('meter_reading', 'update_schedule_status', 'meter_reading_schedules', $id, json_encode($old), $status);
    }

    public static function getSchedules($filters = []) {
        $sql = "SELECT s.*, r.route_name, r.route_code, fy.label AS fiscal_year,
                u.name AS assigned_to_name, cr.name AS created_by_name
                FROM meter_reading_schedules s
                JOIN meter_reading_routes r ON s.route_id = r.id
                LEFT JOIN fiscal_years fy ON s.fiscal_year_id = fy.id
                LEFT JOIN users u ON s.assigned_to = u.id
                LEFT JOIN users cr ON s.created_by = cr.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['route_id'])) { $sql .= " AND s.route_id = :route"; $params['route'] = $filters['route_id']; }
        if (!empty($filters['status'])) { $sql .= " AND s.status = :status"; $params['status'] = $filters['status']; }
        if (!empty($filters['fiscal_year_id'])) { $sql .= " AND s.fiscal_year_id = :fy"; $params['fy'] = $filters['fiscal_year_id']; }
        $sql .= " ORDER BY s.schedule_start DESC";
        return db()->fetchAll($sql, $params);
    }

    // Offline batch sync
    public static function createReadingBatch($deviceId = null) {
        $code = 'BATCH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        return db()->insert('meter_reading_batches', [
            'batch_code' => $code,
            'user_id' => Auth::id(),
            'device_id' => $deviceId,
            'status' => 'pending',
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function syncBatchReadings($batchId, $readings) {
        $batch = db()->fetchOne("SELECT * FROM meter_reading_batches WHERE id = ?", [$batchId]);
        if (!$batch) throw new Exception('Invalid batch');

        db()->beginTransaction();
        try {
            $synced = 0;
            foreach ($readings as $r) {
                db()->insert('meter_readings', [
                    'consumer_id' => intval($r['consumer_id']),
                    'meter_id' => intval($r['meter_id']),
                    'route_id' => intval($r['route_id'] ?? 0) ?: null,
                    'reading_batch_id' => $batchId,
                    'reading_date' => $r['reading_date'],
                    'previous_reading' => floatval($r['previous_reading'] ?? 0),
                    'current_reading' => floatval($r['current_reading']),
                    'consumption' => floatval($r['consumption'] ?? 0),
                    'consumption_flag' => $r['consumption_flag'] ?? 'normal',
                    'reading_source' => 'pos',
                    'sync_status' => 'synced',
                    'sync_device_id' => $batch['device_id'],
                    'meter_photo' => $r['meter_photo'] ?? null,
                    'gps_latitude' => floatval($r['latitude'] ?? 0) ?: null,
                    'gps_longitude' => floatval($r['longitude'] ?? 0) ?: null,
                    'meter_condition' => $r['meter_condition'] ?? 'good',
                    'is_estimated' => intval($r['is_estimated'] ?? 0),
                    'remarks' => $r['remarks'] ?? '',
                    'read_by' => Auth::id(),
                ]);
                $synced++;
            }
            db()->update('meter_reading_batches', [
                'total_readings' => count($readings),
                'synced_readings' => $synced,
                'status' => $synced === count($readings) ? 'completed' : 'partial',
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $batchId]);
            db()->commit();
            self::audit('meter_reading', 'sync_batch', 'meter_reading_batches', $batchId, null, json_encode(['total' => count($readings), 'synced' => $synced]));
            return ['synced' => $synced, 'total' => count($readings)];
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }
    }

    // ============================================================
    // BILLING WORKFLOW
    // ============================================================

    public static function createBillingCycle($data) {
        $month = intval($data['billing_month']);
        $fy = db()->fetchOne("SELECT * FROM fiscal_years WHERE id = ?", [intval($data['fiscal_year_id'])]);
        if (!$fy) throw new Exception('Invalid fiscal year');

        $code = 'CYCLE-' . $fy['year_code'] . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
        $existing = db()->fetchOne("SELECT id FROM billing_cycles WHERE cycle_code = ?", [$code]);
        if ($existing) throw new Exception("Billing cycle {$code} already exists");

        $id = db()->insert('billing_cycles', [
            'cycle_code' => $code,
            'fiscal_year_id' => intval($data['fiscal_year_id']),
            'billing_month' => $month,
            'billing_period_start' => $data['billing_period_start'],
            'billing_period_end' => $data['billing_period_end'],
            'due_date' => $data['due_date'],
            'reading_cutoff_date' => $data['reading_cutoff_date'] ?? null,
            'target_consumers' => intval($data['target_consumers'] ?? 0),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);
        self::audit('billing', 'create_cycle', 'billing_cycles', $id, null, json_encode($data));
        return $id;
    }

    public static function runBillingCycle($cycleId) {
        $cycle = db()->fetchOne("SELECT * FROM billing_cycles WHERE id = ?", [$cycleId]);
        if (!$cycle) throw new Exception('Billing cycle not found');
        if ($cycle['status'] !== 'draft' && $cycle['status'] !== 'reading_in_progress') {
            throw new Exception('Cycle must be in draft or reading_in_progress status');
        }

        db()->update('billing_cycles', ['status' => 'billing_in_progress'], 'id = :id', ['id' => $cycleId]);

        try {
            $params = [
                'fiscal_year_id' => $cycle['fiscal_year_id'],
                'billing_start' => $cycle['billing_period_start'],
                'billing_end' => $cycle['billing_period_end'],
                'due_date' => $cycle['due_date'],
                'generate_mode' => 'all',
            ];

            $preview = BillingEngine::previewBills($params);
            if (empty($preview['data'])) {
                db()->update('billing_cycles', ['status' => 'draft', 'bills_generated' => 0], 'id = :id', ['id' => $cycleId]);
                return ['generated' => 0, 'message' => 'No bills to generate', 'skipped' => $preview['skipped']];
            }

            $result = BillingEngine::generateBills($preview, $params);

            $totalBilled = db()->fetchColumn(
                "SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE billing_period_start = ? AND billing_period_end = ? AND deleted_at IS NULL",
                [$cycle['billing_period_start'], $cycle['billing_period_end']]
            );

            db()->update('billing_cycles', [
                'status' => 'bills_generated',
                'bills_generated' => $result['generated'],
                'total_billed' => $totalBilled,
                'generated_by' => Auth::id(),
                'generated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $cycleId]);

            db()->query(
                "UPDATE bills SET billing_cycle_id = ? WHERE billing_period_start = ? AND billing_period_end = ? AND deleted_at IS NULL",
                [$cycleId, $cycle['billing_period_start'], $cycle['billing_period_end']]
            );

            self::sendBulkBillNotifications($cycleId);

            self::audit('billing', 'run_cycle', 'billing_cycles', $cycleId, null, json_encode($result));
            return $result;
        } catch (Exception $e) {
            db()->update('billing_cycles', ['status' => 'draft'], 'id = :id', ['id' => $cycleId]);
            throw $e;
        }
    }

    public static function closeBillingCycle($cycleId) {
        $cycle = db()->fetchOne("SELECT * FROM billing_cycles WHERE id = ?", [$cycleId]);
        if (!$cycle) throw new Exception('Billing cycle not found');
        if ($cycle['status'] !== 'bills_generated' && $cycle['status'] !== 'collection_in_progress') {
            throw new Exception('Cycle must be in bills_generated or collection_in_progress status');
        }

        $collected = db()->fetchColumn(
            "SELECT COALESCE(SUM(paid_amount), 0) FROM bills WHERE billing_cycle_id = ? AND deleted_at IS NULL",
            [$cycleId]
        );

        db()->update('billing_cycles', [
            'status' => 'closed',
            'total_collected' => $collected,
            'closed_by' => Auth::id(),
            'closed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $cycleId]);

        self::audit('billing', 'close_cycle', 'billing_cycles', $cycleId, json_encode($cycle), 'closed');
        return true;
    }

    public static function getBillingCycles($filters = []) {
        $sql = "SELECT c.*, fy.label AS fiscal_year_label, fy.year_code,
                u1.name AS generated_by_name, u2.name AS closed_by_name, u3.name AS created_by_name
                FROM billing_cycles c
                LEFT JOIN fiscal_years fy ON c.fiscal_year_id = fy.id
                LEFT JOIN users u1 ON c.generated_by = u1.id
                LEFT JOIN users u2 ON c.closed_by = u2.id
                LEFT JOIN users u3 ON c.created_by = u3.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND c.status = :status"; $params['status'] = $filters['status']; }
        if (!empty($filters['fiscal_year_id'])) { $sql .= " AND c.fiscal_year_id = :fy"; $params['fy'] = $filters['fiscal_year_id']; }
        $sql .= " ORDER BY c.created_at DESC";
        return db()->fetchAll($sql, $params);
    }

    public static function sendBulkBillNotifications($cycleId) {
        $bills = db()->fetchAll(
            "SELECT b.id, b.bill_no, b.total_amount, b.due_date, b.consumer_id, c.mobile, c.email, c.full_name
             FROM bills b JOIN consumers c ON b.consumer_id = c.id
             WHERE b.billing_cycle_id = ? AND b.deleted_at IS NULL AND c.deleted_at IS NULL",
            [$cycleId]
        );

        $sent = 0;
        foreach ($bills as $bill) {
            if (!empty($bill['mobile'])) {
                $msg = "Dear {$bill['full_name']}, your water bill {$bill['bill_no']} of NRs. {$bill['total_amount']} is generated. Due date: {$bill['due_date']}. - " . APP_SHORT;
                try {
                    $sent += sendSMS($bill['mobile'], $msg) ? 1 : 0;
                } catch (Exception $e) {
                    error_log("SMS failed for bill {$bill['bill_no']}: " . $e->getMessage());
                }
            }
        }
        return $sent;
    }

    // ============================================================
    // PAYMENT WORKFLOW
    // ============================================================

    public static function updateGatewayConfig($id, $data) {
        db()->update('payment_gateways', [
            'gateway_name' => $data['gateway_name'],
            'merchant_id' => $data['merchant_id'] ?? '',
            'secret_key' => $data['secret_key'] ?? '',
            'api_key' => $data['api_key'] ?? '',
            'api_url' => $data['api_url'] ?? '',
            'is_active' => intval($data['is_active'] ?? 0),
            'is_test_mode' => intval($data['is_test_mode'] ?? 1),
        ], 'id = :id', ['id' => $id]);
        self::audit('payment', 'update_gateway', 'payment_gateways', $id, null, json_encode($data));
    }

    public static function getActiveGateways() {
        return db()->fetchAll("SELECT * FROM payment_gateways WHERE is_active = 1");
    }

    public static function reconcilePayment($paymentId, $actualAmount, $notes = '') {
        $payment = db()->fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
        if (!$payment) throw new Exception('Payment not found');

        $diff = floatval($payment['net_amount']) - floatval($actualAmount);
        $status = abs($diff) < 0.01 ? 'matched' : 'mismatch';

        $id = db()->insert('payment_reconciliation', [
            'payment_id' => $paymentId,
            'reconciled_date' => date('Y-m-d'),
            'expected_amount' => $payment['net_amount'],
            'actual_amount' => floatval($actualAmount),
            'difference' => $diff,
            'reconciled_by' => Auth::id(),
            'status' => $status,
            'notes' => $notes,
        ]);
        self::audit('payment', 'reconcile', 'payment_reconciliation', $id, null, json_encode([
            'payment_id' => $paymentId, 'expected' => $payment['net_amount'], 'actual' => $actualAmount, 'diff' => $diff, 'status' => $status
        ]));
        return ['id' => $id, 'status' => $status, 'difference' => $diff];
    }

    public static function getUnreconciledPayments() {
        return db()->fetchAll(
            "SELECT p.*, c.consumer_no, c.full_name AS consumer_name,
                    COALESCE(pr.status, 'unreconciled') AS recon_status
             FROM payments p
             JOIN consumers c ON p.consumer_id = c.id
             LEFT JOIN payment_reconciliation pr ON pr.payment_id = p.id
             WHERE pr.id IS NULL AND p.status = 'completed'
             ORDER BY p.payment_date DESC LIMIT 50"
        );
    }

    // ============================================================
    // REPORT EXPORT HELPERS
    // ============================================================

    public static function exportCSV($headers, $rows, $filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public static function generateBillReceiptHTML($billId) {
        $bill = db()->fetchOne(
            "SELECT b.*, c.consumer_no, c.full_name, c.mobile, c.address, c.ward_no, c.tole,
                    fy.label AS fiscal_year, fy.year_code,
                    u.name AS generated_by_name
             FROM bills b
             JOIN consumers c ON b.consumer_id = c.id
             LEFT JOIN fiscal_years fy ON b.fiscal_year_id = fy.id
             LEFT JOIN users u ON b.generated_by = u.id
             WHERE b.id = ?",
            [$billId]
        );
        if (!$bill) throw new Exception('Bill not found');

        $org = db()->fetchOne("SELECT * FROM organizations LIMIT 1");
        $orgName = $org['organization_name'] ?? APP_ORG;
        $orgAddress = $org['address'] ?? '';
        $orgPhone = $org['phone'] ?? '';
        $orgLogo = $org['logo'] ?? '';

        $html = '<html><head>
        <style>
            body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 12px; line-height: 1.6; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .header h2 { margin: 0; font-size: 18px; }
            .header p { margin: 2px 0; font-size: 12px; }
            .bill-title { text-align: center; font-size: 16px; font-weight: bold; margin: 15px 0; padding: 5px; background: #f0f0f0; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { padding: 5px 8px; border: 1px solid #666; text-align: left; font-size: 11px; }
            th { background: #e0e0e0; font-weight: bold; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .total-row td { font-weight: bold; background: #f5f5f5; }
            .footer { margin-top: 30px; border-top: 1px solid #999; padding-top: 10px; font-size: 10px; text-align: center; color: #666; }
            .amount-words { font-style: italic; margin: 10px 0; padding: 8px; background: #f9f9f9; border-left: 3px solid #333; }
        </style></head><body>
        <div class="header">
            ' . ($orgLogo ? '<img src="' . $orgLogo . '" height="60"><br>' : '') . '
            <h2>' . escape($orgName) . '</h2>
            <p>' . escape($orgAddress) . '</p>
            <p>Phone: ' . escape($orgPhone) . '</p>
        </div>
        <div class="bill-title">TAX BILL / INVOICE</div>
        <table>
            <tr><th colspan="2">Bill Details</th><th colspan="2">Consumer Details</th></tr>
            <tr><td>Bill No:</td><td><strong>' . escape($bill['bill_no']) . '</strong></td><td>Consumer No:</td><td>' . escape($bill['consumer_no']) . '</td></tr>
            <tr><td>Fiscal Year:</td><td>' . escape($bill['fiscal_year']) . '</td><td>Name:</td><td>' . escape($bill['full_name']) . '</td></tr>
            <tr><td>Bill Period:</td><td>' . $bill['billing_period_start'] . ' to ' . $bill['billing_period_end'] . '</td><td>Address:</td><td>' . escape($bill['tole'] ? $bill['tole'] . ', Ward ' . $bill['ward_no'] : ($bill['address'] ?? 'N/A')) . '</td></tr>
            <tr><td>Due Date:</td><td>' . $bill['due_date'] . '</td><td>Mobile:</td><td>' . escape($bill['mobile'] ?? '') . '</td></tr>
            <tr><td>Status:</td><td>' . ucfirst($bill['status']) . '</td><td></td><td></td></tr>
        </table>
        <table>
            <tr><th>#</th><th>Description</th><th class="text-right">Amount (NRs.)</th></tr>
            <tr><td>1</td><td>Previous Reading: ' . number_format($bill['previous_reading'], 2) . ' | Current Reading: ' . number_format($bill['current_reading'], 2) . ' | Consumption: ' . number_format($bill['consumption'], 2) . ' Units</td><td class="text-right">-</td></tr>
            <tr><td>2</td><td>Base Fee</td><td class="text-right">' . number_format($bill['base_fee'], 2) . '</td></tr>
            <tr><td>3</td><td>Consumption Charge</td><td class="text-right">' . number_format($bill['consumption_charge'], 2) . '</td></tr>
            <tr><td>4</td><td>Meter Rent</td><td class="text-right">' . number_format($bill['meter_rent'], 2) . '</td></tr>
            <tr><td>5</td><td>Sewerage Fee</td><td class="text-right">' . number_format($bill['sewerage_fee'], 2) . '</td></tr>
            ' . ($bill['vat_amount'] > 0 ? '<tr><td>6</td><td>VAT (' . $bill['vat_percent'] . '%)</td><td class="text-right">' . number_format($bill['vat_amount'], 2) . '</td></tr>' : '') . '
            ' . ($bill['penalty_amount'] > 0 ? '<tr><td>7</td><td>Penalty</td><td class="text-right">' . number_format($bill['penalty_amount'], 2) . '</td></tr>' : '') . '
            ' . ($bill['discount_amount'] > 0 ? '<tr><td>8</td><td>Discount' . ($bill['discount_reason'] ? ' (' . escape($bill['discount_reason']) . ')' : '') . '</td><td class="text-right">-' . number_format($bill['discount_amount'], 2) . '</td></tr>' : '') . '
            <tr class="total-row"><td colspan="2" class="text-right">Total Amount</td><td class="text-right">' . number_format($bill['total_amount'], 2) . '</td></tr>
            <tr><td colspan="2" class="text-right">Paid Amount</td><td class="text-right">' . number_format($bill['paid_amount'], 2) . '</td></tr>
            <tr class="total-row"><td colspan="2" class="text-right">Due Amount</td><td class="text-right">' . number_format($bill['due_amount'], 2) . '</td></tr>
        </table>
        <div class="amount-words">Amount in Words: ' . BillingEngine::numberToWords($bill['total_amount']) . '</div>
        <div class="footer">
            <p>Generated on: ' . date('Y-m-d H:i:s') . ' | ' . escape(APP_NAME) . '</p>
            <p>This is a computer-generated document.</p>
        </div>
        </body></html>';
        return $html;
    }

    // ============================================================
    // WORKFLOW DASHBOARD
    // ============================================================

    public static function getDashboardStats() {
        $currentFy = db()->fetchOne("SELECT id FROM fiscal_years WHERE is_current = 1 LIMIT 1");
        $fyId = $currentFy ? $currentFy['id'] : 0;

        $readingPending = db()->fetchColumn(
            "SELECT COUNT(*) FROM meter_readings WHERE is_verified = 0"
        );

        $readingToday = db()->fetchColumn(
            "SELECT COUNT(*) FROM meter_readings WHERE DATE(created_at) = CURDATE()"
        );

        $activeCycles = db()->fetchColumn(
            "SELECT COUNT(*) FROM billing_cycles WHERE status NOT IN ('closed', 'cancelled') AND fiscal_year_id = ?",
            [$fyId]
        );

        $totalOverdue = db()->fetchColumn(
            "SELECT COALESCE(SUM(due_amount), 0) FROM bills WHERE status IN ('pending', 'partial', 'overdue') AND deleted_at IS NULL AND due_date < CURDATE()"
        );

        $defaulterCount = db()->fetchColumn(
            "SELECT COUNT(DISTINCT consumer_id) FROM bills WHERE status IN ('pending', 'partial', 'overdue') AND deleted_at IS NULL AND due_date < CURDATE()"
        );

        $collectionRate = 0;
        $totalBilled = db()->fetchColumn(
            "SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE fiscal_year_id = ? AND deleted_at IS NULL",
            [$fyId]
        );
        $totalCollected = db()->fetchColumn(
            "SELECT COALESCE(SUM(paid_amount), 0) FROM bills WHERE fiscal_year_id = ? AND deleted_at IS NULL",
            [$fyId]
        );
        if ($totalBilled > 0) {
            $collectionRate = round(($totalCollected / $totalBilled) * 100, 2);
        }

        return [
            'reading_pending_verification' => $readingPending,
            'reading_today' => $readingToday,
            'active_cycles' => $activeCycles,
            'total_overdue' => $totalOverdue,
            'defaulter_count' => $defaulterCount,
            'collection_rate' => $collectionRate,
            'total_billed' => $totalBilled,
            'total_collected' => $totalCollected,
        ];
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private static function audit($workflowType, $action, $entityType, $entityId, $oldValue = null, $newValue = null) {
        try {
            db()->insert('workflow_audit', [
                'workflow_type' => $workflowType,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => intval($entityId),
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'performed_by' => Auth::id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (Exception $e) {
            error_log("Workflow audit failed: " . $e->getMessage());
        }
    }
}
