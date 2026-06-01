<?php
// Global Helper Functions

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function post($key = null, $default = null) {
    if ($key === null) {
        return $_POST;
    }
    return $_POST[$key] ?? $default;
}

function get($key = null, $default = null) {
    if ($key === null) {
        return $_GET;
    }
    return $_GET[$key] ?? $default;
}

function request($key = null, $default = null) {
    if ($key === null) {
        return $_REQUEST;
    }
    return $_REQUEST[$key] ?? $default;
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function old($key, $default = '') {
    return $_SESSION['old'][$key] ?? $default;
}

function flash($key = null, $value = null) {
    if ($key === null) {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
    if ($value === null) {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    $_SESSION['flash'][$key] = $value;
}

function set_flash($key, $value) {
    $_SESSION['flash'][$key] = $value;
}

function has_flash($key) {
    return isset($_SESSION['flash'][$key]);
}

function alert_success($message) {
    set_flash('success', $message);
}

function alert_error($message) {
    set_flash('error', $message);
}

function alert_warning($message) {
    set_flash('warning', $message);
}

function alert_info($message) {
    set_flash('info', $message);
}

function display_alert() {
    $types = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $icons = ['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'];
    $output = '';
    foreach ($types as $key => $class) {
        $msg = flash($key);
        if ($msg) {
            $icon = $icons[$key];
            $output .= '<div class="alert alert-' . $class . ' alert-dismissible fade show" role="alert">';
            $output .= '<i class="fas fa-' . $icon . ' me-2"></i>' . escape($msg);
            $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $output .= '</div>';
        }
    }
    return $output;
}

function pagination($total, $page, $perPage, $url) {
    $totalPages = ceil($total / $perPage);
    if ($totalPages <= 1) return '';

    $html = '<nav><ul class="pagination justify-content-center">';
    
    // Previous
    $prevDisabled = $page <= 1 ? ' disabled' : '';
    $prevUrl = $page > 1 ? str_replace('{page}', $page - 1, $url) : '#';
    $html .= '<li class="page-item' . $prevDisabled . '">';
    $html .= '<a class="page-link" href="' . $prevUrl . '"><i class="fas fa-chevron-left"></i></a></li>';

    // Pages
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $page ? ' active' : '';
        $pageUrl = str_replace('{page}', $i, $url);
        $html .= '<li class="page-item' . $active . '">';
        $html .= '<a class="page-link" href="' . $pageUrl . '">' . $i . '</a></li>';
    }

    // Next
    $nextDisabled = $page >= $totalPages ? ' disabled' : '';
    $nextUrl = $page < $totalPages ? str_replace('{page}', $page + 1, $url) : '#';
    $html .= '<li class="page-item' . $nextDisabled . '">';
    $html .= '<a class="page-link" href="' . $nextUrl . '"><i class="fas fa-chevron-right"></i></a></li>';

    $html .= '</ul></nav>';
    return $html;
}

function format_date($date, $format = 'Y-m-d') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

function format_datetime($datetime, $format = 'Y-m-d h:i A') {
    if (empty($datetime)) return '-';
    return date($format, strtotime($datetime));
}

function format_currency($amount, $symbol = 'NRs. ') {
    return $symbol . number_format((float)$amount, 2);
}

function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    $periods = ['second', 'minute', 'hour', 'day', 'week', 'month', 'year'];
    $lengths = [60, 60, 24, 7, 4.35, 12];
    
    for ($i = 0; $diff >= $lengths[$i] && $i < count($lengths) - 1; $i++) {
        $diff /= $lengths[$i];
    }
    $diff = round($diff);
    $period = $periods[$i];
    $plural = $diff > 1 ? 's' : '';
    return $diff . ' ' . $period . $plural . ' ago';
}

function generate_code($prefix = '', $length = 8) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $prefix . $code;
}

function generate_ticket_no() {
    return 'TKT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function generate_bill_no() {
    return 'BILL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function generate_receipt_no() {
    return 'RCT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function get_status_badge($status) {
    $badges = [
        'active' => 'success',
        'inactive' => 'secondary',
        'pending' => 'warning',
        'suspended' => 'danger',
        'completed' => 'info',
        'cancelled' => 'danger',
        'open' => 'warning',
        'in_progress' => 'info',
        'resolved' => 'success',
        'closed' => 'secondary',
        'paid' => 'success',
        'unpaid' => 'danger',
        'partial' => 'warning',
        'overdue' => 'danger',
        'approved' => 'success',
        'rejected' => 'danger',
    ];
    $class = $badges[strtolower($status)] ?? 'primary';
    return '<span class="badge bg-' . $class . '">' . escape(ucfirst($status)) . '</span>';
}

function get_status_text($status) {
    $labels = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
        'suspended' => 'Suspended',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'paid' => 'Paid',
        'unpaid' => 'Unpaid',
        'partial' => 'Partial',
        'overdue' => 'Overdue',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
    return $labels[strtolower($status)] ?? ucfirst($status);
}

function get_connection_type_badge($type) {
    $badges = [
        'household' => 'primary',
        'commercial' => 'info',
        'institutional' => 'secondary',
    ];
    $class = $badges[strtolower($type)] ?? 'primary';
    return '<span class="badge bg-' . $class . '">' . escape(ucfirst($type)) . '</span>';
}

function get_gender_options() {
    return ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
}

function get_yes_no($value) {
    return $value == 1 ? 'Yes' : 'No';
}

function truncate($text, $length = 100) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_success($data = [], $message = 'Success') {
    json_response(['success' => true, 'message' => $message, 'data' => $data]);
}

function json_error($message = 'Error', $errors = []) {
    json_response(['success' => false, 'message' => $message, 'errors' => $errors], 400);
}

function upload_file($file, $targetDir, $allowedExts = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    
    $allowedExts = $allowedExts ?? explode(',', ALLOWED_EXTENSIONS);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowedExts)) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;
    
    $newName = uniqid() . '_' . time() . '.' . $ext;
    $targetPath = rtrim($targetDir, '/') . '/' . $newName;
    
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    
    return move_uploaded_file($file['tmp_name'], $targetPath) ? $newName : false;
}

function delete_file($filePath) {
    if (file_exists($filePath) && is_file($filePath)) {
        return unlink($filePath);
    }
    return false;
}

function log_activity($userId, $action, $module, $description = '', $data = null) {
    try {
        db()->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'data' => $data ? json_encode($data) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

function get_user_avatar($userId = null) {
    if ($userId) {
        $user = db()->fetchOne("SELECT avatar FROM users WHERE id = ?", [$userId]);
        if ($user && $user['avatar']) return UPLOAD_URL . 'users/' . $user['avatar'];
    }
    return ADMIN_URL . 'assets/images/default-avatar.png';
}

function get_ward_options() {
    $wards = [];
    for ($i = 1; $i <= 20; $i++) {
        $wards[$i] = 'Ward No. ' . $i;
    }
    return $wards;
}

function get_province_options() {
    return [
        '1' => 'Province No. 1',
        '2' => 'Province No. 2',
        '3' => 'Bagmati Province',
        '4' => 'Gandaki Province',
        '5' => 'Lumbini Province',
        '6' => 'Karnali Province',
        '7' => 'Sudurpashchim Province',
    ];
}

function get_district_options() {
    return [
        'kathmandu' => 'Kathmandu',
        'lalitpur' => 'Lalitpur',
        'bhaktapur' => 'Bhaktapur',
        'pokhara' => 'Kaski',
        'chitwan' => 'Chitwan',
    ];
}

function get_municipality_options() {
    return [
        'kathmandu_metro' => 'Kathmandu Metropolitan City',
        'lalitpur_metro' => 'Lalitpur Metropolitan City',
        'bhaktapur_muni' => 'Bhaktapur Municipality',
        'pokhara_metro' => 'Pokhara Metropolitan City',
        'bharatpur_metro' => 'Bharatpur Metropolitan City',
    ];
}

function generateReport($data, $filename, $format = 'pdf') {
    // Placeholder for report generation
    return true;
}

function sendSMS($phone, $message) {
    if (empty(SMS_API_KEY) || empty(SMS_API_URL)) return false;
    $data = ['api_key' => SMS_API_KEY, 'to' => $phone, 'message' => $message, 'sender' => SMS_SENDER_ID];
    $ch = curl_init(SMS_API_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function sendEmail($to, $subject, $message, $headers = '') {
    if (empty(SMTP_HOST)) return mail($to, $subject, $message, $headers);
    // SMTP implementation placeholder
    return true;
}
