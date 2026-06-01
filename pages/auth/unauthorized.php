<?php
require_once __DIR__ . '/../../includes/config.php';
$message = $_GET['msg'] ?? 'You do not have permission to access this page.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-card { text-align: center; padding: 60px; }
        .error-card .icon { font-size: 64px; color: #dc3545; margin-bottom: 20px; }
        .error-card h2 { font-weight: 700; color: #2c3e50; margin-bottom: 10px; }
        .error-card p { color: #6c757d; margin-bottom: 24px; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon"><i class="fas fa-shield-alt"></i></div>
        <h2>Access Denied</h2>
        <p><?= escape($message) ?></p>
        <a href="<?= ADMIN_URL ?>dashboard/index.php" class="btn btn-primary">
            <i class="fas fa-home me-2"></i>Go to Dashboard
        </a>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
</body>
</html>
