<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/WorkflowEngine.php';
Auth::requireAuth();
RBAC::requirePermission('bills.view');

$billId = intval(get('id'));
if (!$billId) { redirect(ADMIN_URL . 'billing/index.php'); }

try {
    $html = WorkflowEngine::generateBillReceiptHTML($billId);
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

if (get('format') === 'pdf') {
    require_once __DIR__ . '/../../includes/mpdf/vendor/autoload.php';
    $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/../../tmp']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('bill-' . $billId . '.pdf', 'D');
    exit;
}

if (get('format') === 'html') {
    echo $html;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Bill</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11px; }
        }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .print-area { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,.1); }
        .no-print { text-align: center; margin-bottom: 20px; }
        .no-print .btn { padding: 8px 20px; margin: 0 5px; cursor: pointer; border: none; border-radius: 4px; font-size: 14px; }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-success { background: #198754; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h2 { margin: 0; font-size: 18px; }
        .header p { margin: 2px 0; font-size: 12px; }
        .bill-title { text-align: center; font-size: 16px; font-weight: bold; margin: 15px 0; padding: 5px; background: #f0f0f0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 5px 8px; border: 1px solid #666; text-align: left; font-size: 11px; }
        th { background: #e0e0e0; font-weight: bold; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; background: #f5f5f5; }
        .footer { margin-top: 30px; border-top: 1px solid #999; padding-top: 10px; font-size: 10px; text-align: center; color: #666; }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <button class="btn btn-success" onclick="window.location.href='print-bill.php?id=<?= $billId ?>&format=pdf'"><i class="fas fa-file-pdf"></i> Download PDF</button>
        <button class="btn btn-secondary" onclick="window.close()">Close</button>
    </div>
    <div class="print-area">
        <?= $html ?>
    </div>
    <script>
        window.onload = function() {
            if (window.location.search.includes('autoprint')) { window.print(); }
        };
    </script>
</body>
</html>
