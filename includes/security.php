<?php
// Security Helper Class
class Security {

    // Hash password with bcrypt
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // Verify password
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    // Generate CSRF token
    public static function generateCsrf() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    // Verify CSRF token
    public static function verifyCsrf($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) return false;
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        // Regenerate token after verification
        if ($valid) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $valid;
    }

    // XSS Clean
    public static function xssClean($input) {
        if (is_array($input)) {
            return array_map([self::class, 'xssClean'], $input);
        }
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }

    // Sanitize filename
    public static function sanitizeFilename($filename) {
        $filename = preg_replace('/[^\w\-\.]/', '_', $filename);
        return trim($filename, '._');
    }

    // Validate file upload
    public static function validateFileUpload($file, $allowedTypes = null) {
        $allowedTypes = $allowedTypes ?? ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if ($file['error'] !== UPLOAD_ERR_OK) return 'Upload error';
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes)) return 'Invalid file type';
        if ($file['size'] > MAX_FILE_SIZE) return 'File too large';
        return true;
    }

    // Encrypt sensitive data
    public static function encrypt($data) {
        $method = 'AES-256-CBC';
        $key = hash('sha256', ENCRYPTION_KEY, true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    // Decrypt sensitive data
    public static function decrypt($data) {
        $method = 'AES-256-CBC';
        $key = hash('sha256', ENCRYPTION_KEY, true);
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);
    }

    // Generate secure random token
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    // Validate session
    public static function validateSession() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) return false;
        if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
            session_destroy();
            return false;
        }
        // Regenerate session ID periodically
        if (time() - $_SESSION['login_time'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['login_time'] = time();
        }
        return true;
    }

    // Rate limiting
    public static function checkRateLimit($key, $maxAttempts = 10, $window = 60) {
        $rateKey = "rate_limit_{$key}";
        $attempts = $_SESSION[$rateKey]['attempts'] ?? 0;
        $windowStart = $_SESSION[$rateKey]['window_start'] ?? time();
        
        if (time() - $windowStart > $window) {
            $attempts = 0;
            $windowStart = time();
        }
        
        $attempts++;
        $_SESSION[$rateKey] = ['attempts' => $attempts, 'window_start' => $windowStart];
        
        return $attempts <= $maxAttempts;
    }

    // Log security event
    public static function logSecurityEvent($event, $details = null) {
        try {
            db()->insert('security_logs', [
                'event' => $event,
                'details' => $details ? json_encode($details) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'user_id' => $_SESSION['user_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Security log error: " . $e->getMessage());
        }
    }

    // Validate strong password
    public static function validatePasswordStrength($password) {
        $errors = [];
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password must contain uppercase letter';
        if (!preg_match('/[a-z]/', $password)) $errors[] = 'Password must contain lowercase letter';
        if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain a number';
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $errors[] = 'Password must contain a special character';
        return empty($errors) ? true : $errors;
    }

    // Get client IP
    public static function getClientIP() {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ips = explode(',', $_SERVER[$h]);
                return trim($ips[0]);
            }
        }
        return '0.0.0.0';
    }
}
