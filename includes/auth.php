<?php
// Authentication System
class Auth {
    private static $user = null;

    public static function login($username, $password, $remember = false) {
        $db = db();
        $user = $db->fetchOne(
            "SELECT u.*, r.name as role_name, r.slug as role_slug 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE (u.username = :username1 OR u.email = :username2) 
             AND u.status = 'active' 
             AND u.deleted_at IS NULL",
            ['username1' => $username, 'username2' => $username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            self::logAttempt($username, false);
            return false;
        }

        if ($user['is_locked'] == 1) {
            return false;
        }

        // Update login info
        $db->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'login_attempts' => 0,
            'locked_until' => null
        ], 'id = :id', ['id' => $user['id']]);

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['role_slug'] = $user['role_slug'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // Remember me
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + 2592000; // 30 days
            setcookie('remember_token', $token, $expiry, '/', '', false, true);
            $db->update('users', [
                'remember_token' => password_hash($token, PASSWORD_DEFAULT),
                'remember_expires' => date('Y-m-d H:i:s', $expiry)
            ], 'id = :id', ['id' => $user['id']]);
        }

        self::logAttempt($username, true);
        log_activity($user['id'], 'login', 'auth', 'User logged in');
        
        return $user;
    }

    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            log_activity($_SESSION['user_id'], 'logout', 'auth', 'User logged out');
        }
        setcookie('remember_token', '', time() - 3600, '/');
        session_destroy();
        return true;
    }

    public static function check() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function user() {
        if (self::$user === null && self::check()) {
            self::$user = db()->fetchOne(
                "SELECT u.*, r.name as role_name, r.slug as role_slug 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.id = :id",
                ['id' => $_SESSION['user_id']]
            );
        }
        return self::$user;
    }

    public static function id() {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role() {
        return $_SESSION['role_slug'] ?? null;
    }

    public static function roleName() {
        return $_SESSION['role_name'] ?? null;
    }

    public static function requireAuth() {
        if (!self::check()) {
            redirect(BASE_URL . 'pages/auth/login.php');
        }
    }

    public static function requireRole($roles) {
        if (!self::check()) {
            redirect(BASE_URL . 'pages/auth/login.php');
        }
        $roles = is_array($roles) ? $roles : [$roles];
        if (!in_array(self::role(), $roles)) {
            redirect(BASE_URL . 'pages/auth/unauthorized.php');
        }
    }

    public static function checkRole($roles) {
        $roles = is_array($roles) ? $roles : [$roles];
        return in_array(self::role(), $roles);
    }

    public static function isSuperAdmin() {
        return self::role() === 'super_admin';
    }

    public static function isCommitteeAdmin() {
        return self::role() === 'committee_admin';
    }

    public static function passwordReset($email) {
        $user = db()->fetchOne("SELECT id, name, email FROM users WHERE email = :email AND deleted_at IS NULL", ['email' => $email]);
        if (!$user) return false;

        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + 3600);

        db()->update('users', [
            'reset_token' => password_hash($token, PASSWORD_DEFAULT),
            'reset_expires' => $expiry
        ], 'id = :id', ['id' => $user['id']]);

        // Send email with reset link
        $resetLink = BASE_URL . "pages/auth/reset-password.php?token=" . $token . "&email=" . urlencode($user['email']);
        $subject = "Password Reset - " . APP_NAME;
        $message = "Dear {$user['name']},\n\nClick the following link to reset your password:\n{$resetLink}\n\nThis link expires in 1 hour.\n\nRegards,\n" . APP_NAME;
        
        return sendEmail($user['email'], $subject, $message);
    }

    public static function validateResetToken($token, $email) {
        $user = db()->fetchOne(
            "SELECT id, reset_token, reset_expires FROM users WHERE email = :email AND deleted_at IS NULL",
            ['email' => $email]
        );
        if (!$user || !$user['reset_token']) return false;
        if (strtotime($user['reset_expires']) < time()) return false;
        return password_verify($token, $user['reset_token']);
    }

    public static function updatePassword($email, $password) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        return db()->update('users', [
            'password' => $hash,
            'reset_token' => null,
            'reset_expires' => null
        ], 'email = :email', ['email' => $email]);
    }

    public static function changePassword($userId, $oldPassword, $newPassword) {
        $user = db()->fetchOne("SELECT password FROM users WHERE id = :id", ['id' => $userId]);
        if (!$user || !password_verify($oldPassword, $user['password'])) return false;
        
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        return db()->update('users', ['password' => $hash], 'id = :id', ['id' => $userId]);
    }

    private static function logAttempt($username, $success) {
        db()->insert('login_attempts', [
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'success' => $success ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s')
        ]);

        if (!$success) {
            // Lock account after MAX_LOGIN_ATTEMPTS failures
            $recent = db()->fetchColumn(
                "SELECT COUNT(*) FROM login_attempts 
                 WHERE username = :username 
                 AND success = 0 
                 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
                ['username' => $username]
            );
            if ($recent >= MAX_LOGIN_ATTEMPTS) {
                db()->update('users', [
                    'is_locked' => 1,
                    'locked_until' => date('Y-m-d H:i:s', time() + LOGIN_TIMEOUT)
                ], 'username = :username OR email = :email', [
                    'username' => $username,
                    'email' => $username
                ]);
            }
        }
    }
}
