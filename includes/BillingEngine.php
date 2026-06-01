<?php
class BillingEngine {

    public static function getSettings() {
        $cache = [];
        try {
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('billing_cycle_days','due_date_days','penalty_percent','vat_percent','meter_rent','sewerage_fee','min_units','min_charge','rate_per_unit','default_currency')");
            foreach ($rows as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {
            error_log("getSettings: " . $e->getMessage());
        }
        return array_merge([
            'billing_cycle_days' => 30,
            'due_date_days' => 15,
            'penalty_percent' => 5.00,
            'vat_percent' => 0.00,
            'meter_rent' => 50.00,
            'sewerage_fee' => 0.00,
            'min_units' => 10,
            'min_charge' => 150,
            'rate_per_unit' => 10,
            'default_currency' => 'NRs.'
        ], $cache);
    }

    public static function calculateBillAmount($consumption, $tariff = null) {
        $settings = self::getSettings();
        $consumption = max(0, floatval($consumption));

        if ($tariff) {
            $baseFee = floatval($tariff['base_fee'] ?? 0);
            $ratePerUnit = floatval($tariff['rate_per_unit'] ?? 0);
            $minCharge = floatval($tariff['min_charge'] ?? 0);
            $meterRent = floatval($tariff['meter_rent'] ?? 0);
            $sewerageFee = floatval($tariff['sewerage_fee'] ?? 0);
            $vatPercent = floatval($tariff['vat_percent'] ?? 0);

            $consumptionCharge = $consumption * $ratePerUnit;

            if ($tariff['min_consumption'] ?? false && $consumption <= floatval($tariff['min_consumption'])) {
                $subTotal = $minCharge > 0 ? $minCharge : ($baseFee + $meterRent + $sewerageFee);
            } else {
                $subTotal = $baseFee + $consumptionCharge + $meterRent + $sewerageFee;
                if ($minCharge > 0 && $subTotal < $minCharge) {
                    $subTotal = $minCharge;
                }
            }

            $vatAmount = ($subTotal * $vatPercent) / 100;
            $totalAmount = $subTotal + $vatAmount;

            return [
                'base_fee' => $baseFee,
                'consumption_charge' => $consumptionCharge,
                'meter_rent' => $meterRent,
                'sewerage_fee' => $sewerageFee,
                'vat_amount' => $vatAmount,
                'vat_percent' => $vatPercent,
                'sub_total' => $subTotal,
                'total_amount' => round($totalAmount, 2),
                'min_charge' => $minCharge,
                'calculation_method' => 'tariff'
            ];
        }

        $minUnits = floatval($settings['min_units']);
        $minCharge = floatval($settings['min_charge']);
        $ratePerUnit = floatval($settings['rate_per_unit']);

        if ($consumption <= $minUnits) {
            $totalAmount = $minCharge;
        } else {
            $totalAmount = $minCharge + (($consumption - $minUnits) * $ratePerUnit);
        }

        return [
            'base_fee' => $minCharge,
            'consumption_charge' => $consumption > $minUnits ? ($consumption - $minUnits) * $ratePerUnit : 0,
            'meter_rent' => 0,
            'sewerage_fee' => 0,
            'vat_amount' => 0,
            'vat_percent' => 0,
            'sub_total' => $totalAmount,
            'total_amount' => round($totalAmount, 2),
            'min_charge' => $minCharge,
            'calculation_method' => 'default_slab'
        ];
    }

    public static function calculatePenalty($dueAmount, $dueDate, $penaltyPercent = null) {
        if (empty($dueDate) || floatval($dueAmount) <= 0) return 0;

        $dueTimestamp = strtotime($dueDate);
        if ($dueTimestamp === false) return 0;

        $now = time();
        if ($now <= $dueTimestamp) return 0;

        $settings = self::getSettings();
        $penaltyPercent = $penaltyPercent ?? floatval($settings['penalty_percent']);
        $penaltyDays = intval($settings['due_date_days']);

        $daysOverdue = floor(($now - $dueTimestamp) / 86400);
        if ($daysOverdue < $penaltyDays) return 0;

        $penalty = floatval($dueAmount) * ($penaltyPercent / 100);
        return round($penalty, 2);
    }

    public static function calculateDueDate($billingEndDate, $dueDateDays = null) {
        $settings = self::getSettings();
        $days = $dueDateDays ?? intval($settings['due_date_days']);
        return date('Y-m-d', strtotime($billingEndDate . " +{$days} days"));
    }

    public static function generateBillNo() {
        $prefix = 'SWMS-' . date('Y-m');
        $last = db()->fetchColumn(
            "SELECT MAX(bill_no) FROM bills WHERE bill_no LIKE ?",
            [$prefix . '-%']
        );
        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq = intval(end($parts)) + 1;
        }
        return $prefix . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    public static function generateReceiptNo() {
        return 'RCT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    public static function findTariff($consumer, $billingDate = null) {
        $billingDate = $billingDate ?? date('Y-m-d');
        $connectionType = $consumer['connection_type'] ?? 'household';
        $categoryId = $consumer['category_id'] ?? 0;

        $tariff = db()->fetchOne(
            "SELECT * FROM tariffs WHERE status = 'active' AND is_current = 1 AND deleted_at IS NULL
             AND (connection_type = ? OR connection_type = 'all')
             AND (category_id IS NULL OR category_id = ? OR category_id = 0)
             AND (effective_from IS NULL OR effective_from <= ?)
             ORDER BY FIELD(connection_type, ?, 'all') ASC, is_current DESC, effective_from DESC LIMIT 1",
            [$connectionType, $categoryId, $billingDate, $connectionType]
        );

        if (!$tariff) {
            $tariff = db()->fetchOne(
                "SELECT * FROM tariffs WHERE status = 'active' AND is_current = 1 AND deleted_at IS NULL
                 ORDER BY is_current DESC, effective_from DESC LIMIT 1"
            );
        }

        return $tariff;
    }

    public static function findMeter($consumerId) {
        return db()->fetchOne(
            "SELECT * FROM meters WHERE consumer_id = ? AND status = 'active' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1",
            [$consumerId]
        );
    }

    public static function getLastReading($meterId) {
        return db()->fetchOne(
            "SELECT * FROM meter_readings WHERE meter_id = ? AND is_verified = 1 ORDER BY reading_date DESC LIMIT 1",
            [$meterId]
        );
    }

    public static function getPreviousReading($meterId, $beforeDate) {
        $prevReading = db()->fetchColumn(
            "SELECT current_reading FROM meter_readings WHERE meter_id = ? AND is_verified = 1 AND reading_date < ? ORDER BY reading_date DESC LIMIT 1",
            [$meterId, $beforeDate]
        );
        return $prevReading ?: 0;
    }

    public static function getLastVerifiedReading($meterId) {
        return db()->fetchOne(
            "SELECT * FROM meter_readings WHERE meter_id = ? AND is_verified = 1 AND deleted_at IS NULL ORDER BY reading_date DESC LIMIT 1",
            [$meterId]
        );
    }

    public static function previewBills($params) {
        $fiscalYearId = intval($params['fiscal_year_id']);
        $billingStart = $params['billing_start'];
        $billingEnd = $params['billing_end'];
        $dueDate = $params['due_date'];
        $generateMode = $params['generate_mode'];
        $consumerId = intval($params['consumer_id'] ?? 0);
        $existingBills = [];

        $fy = db()->fetchOne("SELECT * FROM fiscal_years WHERE id = ?", [$fiscalYearId]);
        if (!$fy) throw new Exception('Invalid fiscal year');

        if ($generateMode === 'all') {
            $consumers = db()->fetchAll(
                "SELECT c.id, c.consumer_no, c.full_name, c.connection_type, c.ward_no, c.category_id
                 FROM consumers c WHERE c.status = 'active' AND c.deleted_at IS NULL ORDER BY c.full_name"
            );
        } else {
            $consumer = db()->fetchOne(
                "SELECT c.id, c.consumer_no, c.full_name, c.connection_type, c.ward_no, c.category_id
                 FROM consumers c WHERE c.id = ? AND c.status = 'active' AND c.deleted_at IS NULL",
                [$consumerId]
            );
            if (!$consumer) throw new Exception('Consumer not found or inactive');
            $consumers = [$consumer];
        }

        if (empty($consumers)) throw new Exception('No active consumers found for billing');

        $existingRows = db()->fetchAll(
            "SELECT b.consumer_id, b.bill_no FROM bills b
             WHERE b.billing_period_start = ? AND b.billing_period_end = ? AND b.deleted_at IS NULL AND b.status != 'cancelled'",
            [$billingStart, $billingEnd]
        );
        foreach ($existingRows as $r) {
            $existingBills[$r['consumer_id']] = $r['bill_no'];
        }

        $previewData = [];
        $skippedCount = 0;

        foreach ($consumers as $cons) {
            if (isset($existingBills[$cons['id']])) {
                $skippedCount++;
                continue;
            }

            $meter = self::findMeter($cons['id']);
            $prevReading = 0;
            $currReading = 0;
            $consumption = 0;
            $readingId = null;

            if ($meter) {
                $lastReading = self::getLastVerifiedReading($meter['id']);
                if ($lastReading) {
                    $currReading = floatval($lastReading['current_reading']);
                    $prevReading = floatval(self::getPreviousReading($meter['id'], $lastReading['reading_date']));
                    if (!$prevReading) $prevReading = floatval($meter['initial_reading'] ?? 0);
                    $consumption = max(0, $currReading - $prevReading);
                    $readingId = $lastReading['id'];
                } else {
                    continue;
                }
            } else {
                continue;
            }

            $tariff = self::findTariff($cons, $billingEnd);
            if (!$tariff) continue;

            $calc = self::calculateBillAmount($consumption, $tariff);

            $totalAmount = $calc['total_amount'];

            $previewData[] = [
                'consumer_id' => $cons['id'],
                'consumer_no' => $cons['consumer_no'],
                'consumer_name' => $cons['full_name'],
                'meter_id' => $meter ? $meter['id'] : null,
                'meter_no' => $meter ? $meter['meter_no'] : '-',
                'tariff_id' => $tariff['id'],
                'reading_id' => $readingId,
                'previous_reading' => $prevReading,
                'current_reading' => $currReading,
                'consumption' => $consumption,
                'base_fee' => $calc['base_fee'],
                'consumption_charge' => $calc['consumption_charge'],
                'meter_rent' => $calc['meter_rent'],
                'sewerage_fee' => $calc['sewerage_fee'],
                'vat_amount' => $calc['vat_amount'],
                'vat_percent' => $calc['vat_percent'],
                'total_amount' => $totalAmount,
                'due_amount' => $totalAmount,
            ];
        }

        return ['data' => $previewData, 'skipped' => $skippedCount, 'total' => count($previewData)];
    }

    public static function generateBills($preview, $params) {
        $fiscalYearId = intval($params['fiscal_year_id']);
        $billingStart = $params['billing_start'];
        $billingEnd = $params['billing_end'];
        $dueDate = $params['due_date'];
        $generatedBy = Auth::id();

        db()->beginTransaction();
        try {
            $generated = 0;
            $errors = [];

            foreach ($preview['data'] as $item) {
                try {
                    $billNo = self::generateBillNo();
                    $billId = db()->insert('bills', [
                        'bill_no' => $billNo,
                        'consumer_id' => $item['consumer_id'],
                        'meter_id' => $item['meter_id'],
                        'reading_id' => $item['reading_id'] ?? null,
                        'tariff_id' => $item['tariff_id'],
                        'fiscal_year_id' => $fiscalYearId,
                        'bill_date' => $billingEnd,
                        'billing_period_start' => $billingStart,
                        'billing_period_end' => $billingEnd,
                        'due_date' => $dueDate,
                        'previous_reading' => $item['previous_reading'],
                        'current_reading' => $item['current_reading'],
                        'consumption' => $item['consumption'],
                        'base_fee' => $item['base_fee'],
                        'consumption_charge' => $item['consumption_charge'],
                        'meter_rent' => $item['meter_rent'],
                        'sewerage_fee' => $item['sewerage_fee'],
                        'vat_amount' => $item['vat_amount'],
                        'vat_percent' => $item['vat_percent'],
                        'penalty_amount' => 0,
                        'discount_amount' => 0,
                        'total_amount' => $item['total_amount'],
                        'paid_amount' => 0,
                        'due_amount' => $item['due_amount'],
                        'bill_type' => 'metered',
                        'status' => 'pending',
                        'generated_by' => $generatedBy,
                        'generated_at' => date('Y-m-d H:i:s')
                    ]);
                    log_activity($generatedBy, 'generate_bill', 'billing', "Generated bill {$billNo} for consumer #{$item['consumer_id']}");
                    $generated++;
                } catch (Exception $e) {
                    $errors[] = "Consumer #{$item['consumer_id']}: {$e->getMessage()}";
                }
            }

            db()->commit();
            return ['generated' => $generated, 'errors' => $errors];
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }
    }

    public static function markOverdueBills() {
        $updated = db()->update(
            'bills',
            ['status' => 'overdue'],
            "status IN ('pending','partial') AND due_date < CURDATE() AND deleted_at IS NULL AND total_amount > paid_amount"
        );
        return $updated;
    }

    public static function calculateLatePenalty($bill) {
        return self::calculatePenalty(
            floatval($bill['due_amount'] ?? $bill['total_amount'] ?? 0),
            $bill['due_date'] ?? null
        );
    }

    public static function getMonthlyReport($year = null, $month = null) {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');
        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $bills = db()->fetchAll(
            "SELECT b.*, c.consumer_no, c.full_name AS consumer_name, c.ward_no, c.connection_type,
                    fy.label AS fiscal_year_label
             FROM bills b
             JOIN consumers c ON b.consumer_id = c.id
             LEFT JOIN fiscal_years fy ON b.fiscal_year_id = fy.id
             WHERE b.billing_period_start >= ? AND b.billing_period_end <= ? AND b.deleted_at IS NULL
             ORDER BY b.created_at DESC",
            [$startDate, $endDate]
        );

        $summary = db()->fetchOne(
            "SELECT COUNT(*) AS total_bills,
                    COALESCE(SUM(total_amount), 0) AS total_billed,
                    COALESCE(SUM(paid_amount), 0) AS total_collected,
                    COALESCE(SUM(due_amount), 0) AS total_outstanding,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) AS overdue_count,
                    SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) AS partial_count,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
             FROM bills WHERE billing_period_start >= ? AND billing_period_end <= ? AND deleted_at IS NULL",
            [$startDate, $endDate]
        );

        $wardWise = db()->fetchAll(
            "SELECT c.ward_no, COUNT(*) AS total, COALESCE(SUM(b.total_amount), 0) AS amount,
                    COALESCE(SUM(b.paid_amount), 0) AS collected
             FROM bills b JOIN consumers c ON b.consumer_id = c.id
             WHERE b.billing_period_start >= ? AND b.billing_period_end <= ? AND b.deleted_at IS NULL AND c.deleted_at IS NULL
             GROUP BY c.ward_no ORDER BY c.ward_no",
            [$startDate, $endDate]
        );

        return ['bills' => $bills, 'summary' => $summary, 'ward_wise' => $wardWise, 'start_date' => $startDate, 'end_date' => $endDate];
    }

    public static function getRevenueReport($startDate = null, $endDate = null) {
        $startDate = $startDate ?? date('Y-m-01', strtotime('-12 months'));
        $endDate = $endDate ?? date('Y-m-d');

        $monthly = db()->fetchAll(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month,
                    COUNT(*) AS txn_count,
                    COALESCE(SUM(amount), 0) AS total_amount,
                    COALESCE(SUM(net_amount), 0) AS net_amount,
                    COALESCE(SUM(discount), 0) AS total_discount,
                    COALESCE(SUM(penalty_waived), 0) AS penalty_waived
             FROM payments WHERE status = 'completed' AND payment_date BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(payment_date, '%Y-%m') ORDER BY month ASC",
            [$startDate, $endDate]
        );

        $methodWise = db()->fetchAll(
            "SELECT payment_method, COUNT(*) AS txn_count, COALESCE(SUM(net_amount), 0) AS total
             FROM payments WHERE status = 'completed' AND payment_date BETWEEN ? AND ?
             GROUP BY payment_method ORDER BY total DESC",
            [$startDate, $endDate]
        );

        $daily = db()->fetchAll(
            "SELECT payment_date, COUNT(*) AS txn_count, COALESCE(SUM(net_amount), 0) AS total
             FROM payments WHERE status = 'completed' AND payment_date BETWEEN ? AND ?
             GROUP BY payment_date ORDER BY payment_date DESC LIMIT 31",
            [$startDate, $endDate]
        );

        $summary = db()->fetchOne(
            "SELECT COUNT(*) AS total_txn, COALESCE(SUM(amount), 0) AS gross_revenue,
                    COALESCE(SUM(discount), 0) AS total_discount,
                    COALESCE(SUM(penalty_waived), 0) AS penalty_waived,
                    COALESCE(SUM(net_amount), 0) AS net_revenue
             FROM payments WHERE status = 'completed' AND payment_date BETWEEN ? AND ?",
            [$startDate, $endDate]
        );

        return ['monthly' => $monthly, 'method_wise' => $methodWise, 'daily' => $daily, 'summary' => $summary];
    }

    public static function getCollectionReport($fiscalYearId = null) {
        $where = 'WHERE b.deleted_at IS NULL';
        $params = [];
        if ($fiscalYearId) {
            $where .= ' AND b.fiscal_year_id = :fy';
            $params['fy'] = $fiscalYearId;
        }

        $monthly = db()->fetchAll(
            "SELECT DATE_FORMAT(b.billing_period_start, '%Y-%m') AS month,
                    COUNT(*) AS total_bills,
                    COALESCE(SUM(b.total_amount), 0) AS total_billed,
                    COALESCE(SUM(b.paid_amount), 0) AS total_collected,
                    COALESCE(SUM(b.due_amount), 0) AS total_outstanding,
                    ROUND(COALESCE(SUM(b.paid_amount), 0) / NULLIF(SUM(b.total_amount), 0) * 100, 2) AS collection_pct
             FROM bills b {$where}
             GROUP BY DATE_FORMAT(b.billing_period_start, '%Y-%m')
             ORDER BY month DESC LIMIT 24",
            $params
        );

        $summary = db()->fetchOne(
            "SELECT COUNT(*) AS total_bills, COALESCE(SUM(total_amount), 0) AS total_billed,
                    COALESCE(SUM(paid_amount), 0) AS total_collected, COALESCE(SUM(due_amount), 0) AS total_outstanding,
                    ROUND(COALESCE(SUM(paid_amount), 0) / NULLIF(SUM(total_amount), 0) * 100, 2) AS collection_pct
             FROM bills b {$where}",
            $params
        );

        return ['monthly' => $monthly, 'summary' => $summary];
    }

    public static function getDefaulters($minMonths = 2, $fiscalYearId = null) {
        $where = "WHERE b.status IN ('pending','partial','overdue') AND b.deleted_at IS NULL AND b.due_date < CURDATE()";
        $params = ['min_months' => $minMonths];

        if ($fiscalYearId) {
            $where .= ' AND b.fiscal_year_id = :fy';
            $params['fy'] = $fiscalYearId;
        }

        $bills = db()->fetchAll(
            "SELECT c.id AS consumer_id, c.consumer_no, c.full_name, c.mobile, c.ward_no, c.tole, c.connection_type,
                    b.id AS bill_id, b.bill_no, b.total_amount, b.paid_amount, b.due_amount, b.due_date, b.status,
                    DATEDIFF(CURDATE(), b.due_date) AS days_overdue,
                    TIMESTAMPDIFF(MONTH, b.due_date, CURDATE()) AS months_overdue
             FROM bills b
             JOIN consumers c ON b.consumer_id = c.id
             {$where} AND TIMESTAMPDIFF(MONTH, b.due_date, CURDATE()) >= :min_months
             ORDER BY b.due_date ASC",
            $params
        );

        $consumerAgg = [];
        foreach ($bills as $b) {
            $cid = $b['consumer_id'];
            if (!isset($consumerAgg[$cid])) {
                $consumerAgg[$cid] = [
                    'consumer_id' => $cid,
                    'consumer_no' => $b['consumer_no'],
                    'full_name' => $b['full_name'],
                    'mobile' => $b['mobile'],
                    'ward_no' => $b['ward_no'],
                    'tole' => $b['tole'],
                    'connection_type' => $b['connection_type'],
                    'total_due' => 0,
                    'total_overdue' => 0,
                    'bill_count' => 0,
                    'max_months_overdue' => 0,
                    'oldest_due_date' => $b['due_date'],
                    'bills' => []
                ];
            }
            $consumerAgg[$cid]['total_due'] += floatval($b['due_amount']);
            $consumerAgg[$cid]['total_overdue'] += floatval($b['due_amount']);
            $consumerAgg[$cid]['bill_count']++;
            $consumerAgg[$cid]['max_months_overdue'] = max($consumerAgg[$cid]['max_months_overdue'], intval($b['months_overdue']));
            if ($b['due_date'] < $consumerAgg[$cid]['oldest_due_date']) {
                $consumerAgg[$cid]['oldest_due_date'] = $b['due_date'];
            }
            $consumerAgg[$cid]['bills'][] = $b;
        }

        $summary = db()->fetchOne(
            "SELECT COUNT(DISTINCT c.id) AS total_defaulters,
                    COALESCE(SUM(b.due_amount), 0) AS total_overdue_amount,
                    COUNT(*) AS total_overdue_bills
             FROM bills b JOIN consumers c ON b.consumer_id = c.id
             WHERE b.status IN ('pending','partial','overdue') AND b.deleted_at IS NULL
             AND b.due_date < CURDATE() AND TIMESTAMPDIFF(MONTH, b.due_date, CURDATE()) >= :min_months2",
            ['min_months2' => $minMonths]
        );

        return ['consumers' => $consumerAgg, 'bills' => $bills, 'summary' => $summary];
    }

    public static function getConsumerHistory($consumerId) {
        $consumer = db()->fetchOne(
            "SELECT c.*, cc.name AS category_name FROM consumers c
             LEFT JOIN consumer_categories cc ON c.category_id = cc.id WHERE c.id = ?",
            [$consumerId]
        );
        if (!$consumer) throw new Exception('Consumer not found');

        $bills = db()->fetchAll(
            "SELECT b.*, fy.label AS fiscal_year_label
             FROM bills b LEFT JOIN fiscal_years fy ON b.fiscal_year_id = fy.id
             WHERE b.consumer_id = ? AND b.deleted_at IS NULL ORDER BY b.billing_period_start DESC LIMIT 60",
            [$consumerId]
        );

        $payments = db()->fetchAll(
            "SELECT p.*, GROUP_CONCAT(bp.bill_id) AS linked_bill_ids
             FROM payments p
             LEFT JOIN bill_payments bp ON p.id = bp.payment_id
             WHERE p.consumer_id = ? AND p.status = 'completed'
             GROUP BY p.id ORDER BY p.payment_date DESC LIMIT 60",
            [$consumerId]
        );

        $meters = db()->fetchAll(
            "SELECT * FROM meters WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC",
            [$consumerId]
        );

        $readings = db()->fetchAll(
            "SELECT mr.*, m.meter_no FROM meter_readings mr
             JOIN meters m ON mr.meter_id = m.id
             WHERE m.consumer_id = ? ORDER BY mr.reading_date DESC LIMIT 24",
            [$consumerId]
        );

        $summary = db()->fetchOne(
            "SELECT COUNT(*) AS total_bills,
                    COALESCE(SUM(total_amount), 0) AS total_billed,
                    COALESCE(SUM(paid_amount), 0) AS total_paid,
                    COALESCE(SUM(due_amount), 0) AS total_due,
                    COUNT(CASE WHEN status = 'paid' THEN 1 END) AS paid_count,
                    COUNT(CASE WHEN status IN ('pending','partial','overdue') THEN 1 END) AS unpaid_count
             FROM bills WHERE consumer_id = ? AND deleted_at IS NULL",
            [$consumerId]
        );

        return ['consumer' => $consumer, 'bills' => $bills, 'payments' => $payments, 'meters' => $meters, 'readings' => $readings, 'summary' => $summary];
    }

    public static function numberToWords($number) {
        $number = floatval($number);
        $decimal = round($number - floor($number), 2) * 100;
        $number = floor($number);

        $words = [
            0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
            20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
            60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
        ];

        if ($number < 21) {
            $result = $words[$number] ?? '';
        } elseif ($number < 100) {
            $tens = floor($number / 10) * 10;
            $units = $number % 10;
            $result = $words[$tens] . ($units ? ' ' . $words[$units] : '');
        } elseif ($number < 1000) {
            $hundreds = floor($number / 100);
            $remainder = $number % 100;
            $result = $words[$hundreds] . ' Hundred' . ($remainder ? ' ' . self::numberToWords($remainder) : '');
        } elseif ($number < 100000) {
            $thousands = floor($number / 1000);
            $remainder = $number % 1000;
            $result = self::numberToWords($thousands) . ' Thousand' . ($remainder ? ' ' . self::numberToWords($remainder) : '');
        } elseif ($number < 10000000) {
            $lakhs = floor($number / 100000);
            $remainder = $number % 100000;
            $result = self::numberToWords($lakhs) . ' Lakh' . ($remainder ? ' ' . self::numberToWords($remainder) : '');
        } else {
            $crores = floor($number / 10000000);
            $remainder = $number % 10000000;
            $result = self::numberToWords($crores) . ' Crore' . ($remainder ? ' ' . self::numberToWords($remainder) : '');
        }

        if ($decimal > 0) {
            $result .= ' and ' . self::numberToWords($decimal) . ' Paisa';
        }

        return $result . ' Only';
    }

    public static function getPaymentGateway($gateway) {
        $config = db()->fetchOne(
            "SELECT * FROM payment_gateways WHERE gateway_type = ? AND is_active = 1 LIMIT 1",
            [$gateway]
        );
        return $config;
    }

    public static function initiateEsewaPayment($amount, $transactionId, $billNo, $consumerInfo) {
        $gateway = self::getPaymentGateway('esewa');
        if (!$gateway) throw new Exception('eSewa gateway not configured');

        $merchantId = $gateway['merchant_id'] ?: ESEWA_MERCHANT_ID;
        $secretKey = $gateway['secret_key'] ?: ESEWA_SECRET_KEY;
        $apiUrl = $gateway['api_url'] ?: ($gateway['is_test_mode'] ? 'https://rc-epay.esewa.com.np/api/epay/main/v2/form' : 'https://epay.esewa.com.np/api/epay/main/v2/form');

        return [
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'bill_no' => $billNo,
            'api_url' => $apiUrl,
            'success_url' => CITIZEN_URL . 'payment-callback.php?gateway=esewa',
            'failure_url' => CITIZEN_URL . 'payment-callback.php?gateway=esewa',
        ];
    }

    public static function initiateKhaltiPayment($amount, $transactionId, $consumerInfo) {
        $gateway = self::getPaymentGateway('khalti');
        if (!$gateway) throw new Exception('Khalti gateway not configured');

        $merchantId = $gateway['merchant_id'] ?: KHALTI_MERCHANT_ID;
        $secretKey = $gateway['secret_key'] ?: KHALTI_SECRET_KEY;
        $apiUrl = $gateway['api_url'] ?: 'https://khalti.com/api/v2/payment/verify/';

        return [
            'merchant_id' => $merchantId,
            'amount' => $amount * 100,
            'transaction_id' => $transactionId,
            'api_url' => $apiUrl,
            'return_url' => CITIZEN_URL . 'payment-callback.php?gateway=khalti',
        ];
    }

    public static function initiateFonepayPayment($amount, $transactionId, $billNo, $consumerInfo) {
        $gateway = self::getPaymentGateway('fonepay');
        if (!$gateway) throw new Exception('FonePay gateway not configured');

        return [
            'merchant_id' => $gateway['merchant_id'] ?: FONEPAY_MERCHANT_ID,
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'bill_no' => $billNo,
            'api_url' => $gateway['api_url'] ?: '',
            'return_url' => CITIZEN_URL . 'payment-callback.php?gateway=fonepay',
        ];
    }

    public static function recordPayment($data) {
        $billIds = $data['bill_ids'] ?? [];
        $consumerId = intval($data['consumer_id']);
        $amount = floatval($data['amount']);
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $paymentMode = $data['payment_mode'] ?? 'office';
        $receivedBy = $data['received_by'] ?? Auth::id();
        $discount = floatval($data['discount'] ?? 0);
        $penaltyWaived = floatval($data['penalty_waived'] ?? 0);
        $remarks = $data['remarks'] ?? '';
        $transactionId = $data['transaction_id'] ?? '';
        $bankName = $data['bank_name'] ?? '';
        $chequeNo = $data['cheque_no'] ?? '';

        if (empty($billIds) || !$consumerId || $amount <= 0) {
            throw new Exception('Invalid payment data');
        }

        $netAmount = $amount - $discount - $penaltyWaived;
        if ($netAmount <= 0) throw new Exception('Net amount must be positive');

        $receiptNo = self::generateReceiptNo();

        db()->beginTransaction();
        try {
            $paymentId = db()->insert('payments', [
                'receipt_no' => $receiptNo,
                'consumer_id' => $consumerId,
                'payment_date' => date('Y-m-d'),
                'amount' => $amount,
                'discount' => $discount,
                'penalty_waived' => $penaltyWaived,
                'net_amount' => $netAmount,
                'payment_method' => $paymentMethod,
                'payment_mode' => $paymentMode,
                'bank_name' => $bankName,
                'cheque_no' => $chequeNo,
                'transaction_id' => $transactionId,
                'reference_no' => $receiptNo,
                'received_by' => $receivedBy,
                'remarks' => $remarks,
                'status' => 'completed',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $remaining = $netAmount;
            $allocatedBills = [];
            $billsToUpdate = db()->fetchAll(
                "SELECT id, bill_no, total_amount, paid_amount, due_amount, status
                 FROM bills WHERE id IN (" . implode(',', array_map('intval', $billIds)) . ")
                 AND consumer_id = ? AND deleted_at IS NULL AND status != 'cancelled'
                 ORDER BY due_date ASC",
                [$consumerId]
            );

            foreach ($billsToUpdate as $bill) {
                if ($remaining <= 0) break;
                $billDue = floatval($bill['due_amount']);
                $allocAmount = min($remaining, $billDue);
                $newPaid = floatval($bill['paid_amount']) + $allocAmount;
                $newDue = floatval($bill['total_amount']) - $newPaid;
                $newStatus = $newDue <= 0 ? 'paid' : (floatval($bill['paid_amount']) > 0 || $allocAmount > 0 ? 'partial' : $bill['status']);

                db()->update('bills', [
                    'paid_amount' => $newPaid,
                    'due_amount' => max(0, $newDue),
                    'status' => $newStatus,
                    'paid_at' => $newDue <= 0 ? date('Y-m-d H:i:s') : null
                ], 'id = :id', ['id' => $bill['id']]);

                db()->insert('bill_payments', [
                    'bill_id' => $bill['id'],
                    'payment_id' => $paymentId,
                    'amount' => $allocAmount,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $allocatedBills[] = $bill['bill_no'];
                $remaining -= $allocAmount;
            }

            if ($remaining > 0) {
                db()->update('payments', ['remarks' => $remarks . ' (Excess: ' . $remaining . ')'], 'id = :id', ['id' => $paymentId]);
            }

            log_activity($receivedBy, 'record_payment', 'billing', "Payment {$receiptNo} of NRs. {$netAmount} recorded for consumer #{$consumerId}");

            db()->commit();
            return [
                'payment_id' => $paymentId,
                'receipt_no' => $receiptNo,
                'net_amount' => $netAmount,
                'allocated_bills' => $allocatedBills,
                'excess' => $remaining
            ];
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }
    }

    public static function autoGenerateBills() {
        $settings = self::getSettings();
        $cycleDays = intval($settings['billing_cycle_days']);
        $today = date('Y-m-d');

        $activeFy = db()->fetchOne("SELECT * FROM fiscal_years WHERE is_current = 1 AND status = 'active' LIMIT 1");
        if (!$activeFy) throw new Exception('No active fiscal year set');

        $lastBillDate = db()->fetchColumn(
            "SELECT MAX(billing_period_end) FROM bills WHERE fiscal_year_id = ? AND deleted_at IS NULL",
            [$activeFy['id']]
        );

        if (!$lastBillDate) {
            $billingStart = $activeFy['start_date'];
        } else {
            $billingStart = date('Y-m-d', strtotime($lastBillDate . ' +1 day'));
        }

        $billingEnd = date('Y-m-d', strtotime($billingStart . " +{$cycleDays} days"));
        if ($billingEnd > $today) return ['generated' => 0, 'message' => 'Not yet due for billing'];

        $dueDate = self::calculateDueDate($billingEnd);

        return self::generateBills(
            self::previewBills([
                'fiscal_year_id' => $activeFy['id'],
                'billing_start' => $billingStart,
                'billing_end' => $billingEnd,
                'due_date' => $dueDate,
                'generate_mode' => 'all'
            ]),
            [
                'fiscal_year_id' => $activeFy['id'],
                'billing_start' => $billingStart,
                'billing_end' => $billingEnd,
                'due_date' => $dueDate
            ]
        );
    }
}
