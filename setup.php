<?php
// SWMS Setup Script - Run once after importing schema.sql
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';

echo "<h2>Smart Water Management System - Setup</h2>";

// Test database connection
try {
    $db = db();
    echo "<p style='color:green'>✓ Database connected successfully</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Make sure you have imported database/schema.sql into MySQL database 'swms_db'</p>";
    exit;
}

// Check if admin user exists
$admin = $db->fetchOne("SELECT id, username, password FROM users WHERE username = 'admin'");
if ($admin) {
    // Generate real hash for admin123
    $realHash = password_hash('admin12', PASSWORD_BCRYPT, ['cost' => 12]);
    $db->update('users', ['password' => $realHash], 'id = :id', ['id' => $admin['id']]);
    echo "<p style='color:green'>✓ Admin password hash regenerated</p>";
} else {
    // Insert admin user
    $realHash = password_hash('admin12', PASSWORD_BCRYPT, ['cost' => 12]);
    $db->insert('users', [
        'role_id' => 1,
        'name' => 'Super Admin',
        'email' => 'admin@swms.gov.np',
        'username' => 'admin',
        'password' => $realHash,
        'status' => 'active'
    ]);
    echo "<p style='color:green'>✓ Admin user created</p>";
}

// Verify the password works
$test = $db->fetchOne("SELECT password FROM users WHERE username = 'admin'");
if ($test && password_verify('admin12', $test['password'])) {
    echo "<p style='color:green; font-weight:bold; font-size:18px;'>✓ Password verified! Login credentials:</p>";
} else {
    echo "<p style='color:red'>✗ Password verification failed</p>";
}

echo "<div style='background:#f0f0f0; padding:20px; border-radius:8px; margin-top:20px;'>";
echo "<h3>Login Credentials</h3>";
echo "<p><strong>URL:</strong> <a href='pages/auth/login.php'>http://localhost/CMS%20000/pages/auth/login.php</a></p>";
echo "<p><strong>Username:</strong> admin</p>";
echo "<p><strong>Email:</strong> admin@swms.gov.np</p>";
echo "<p><strong>Password:</strong> admin12</p>";
echo "</div>";

echo "<div style='margin-top:20px;'>";
echo "<h3>Other Test Accounts</h3>";
echo "<p>After logging in as admin, go to <strong>User Management</strong> to create additional users with different roles.</p>";
echo "</div>";

// Delete this file after use
echo "<p style='margin-top:30px; color:#999; font-size:12px;'>⚠ Delete this setup.php file after running for security.</p>";
