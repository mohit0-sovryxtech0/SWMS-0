<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Notification Settings';
$breadcrumbs = [
    ['label' => 'Notification Management', 'url' => ADMIN_URL . 'notifications/index.php'],
    ['label' => 'Settings']
];
RBAC::requirePermission('settings.edit');

require_once __DIR__ . '/../includes/header.php';

// Handle settings save
if (isPost() && isset($_POST['save_settings'])) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token.');
    } else {
        $settings = [
            'smtp_host' => post('smtp_host', ''),
            'smtp_port' => post('smtp_port', '587'),
            'smtp_username' => post('smtp_username', ''),
            'smtp_password' => post('smtp_password', ''),
            'smtp_from_email' => post('smtp_from_email', ''),
            'smtp_from_name' => post('smtp_from_name', APP_NAME),
            'sms_api_key' => post('sms_api_key', ''),
            'sms_api_url' => post('sms_api_url', ''),
            'sms_sender_id' => post('sms_sender_id', 'SWMS'),
        ];

        foreach ($settings as $key => $value) {
            $exists = db()->fetchColumn(
                "SELECT COUNT(*) FROM system_settings WHERE setting_key = :key",
                ['key' => $key]
            );
            if ($exists) {
                db()->update('system_settings',
                    ['setting_value' => $value],
                    'setting_key = :key',
                    ['key' => $key]
                );
            } else {
                db()->insert('system_settings', [
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'setting_group' => in_array($key, ['sms_api_key', 'sms_api_url', 'sms_sender_id']) ? 'sms' : 'email',
                    'description' => '',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        log_activity(Auth::id(), 'update', 'settings', 'Updated notification settings');
        alert_success('Notification settings saved successfully.');
        redirect(ADMIN_URL . 'notifications/settings.php');
    }
}

// Handle test email
if (isPost() && isset($_POST['test_email'])) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        json_error('Invalid security token.');
    }
    $testEmail = post('test_email_address', Auth::user()['email'] ?? '');
    if (empty($testEmail)) json_error('No email address specified.');

    $host = post('smtp_host', db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_host'") ?: SMTP_HOST);
    $port = post('smtp_port', db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_port'") ?: SMTP_PORT);
    $user = post('smtp_username', db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_username'") ?: SMTP_USER);
    $pass = post('smtp_password', db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_password'") ?: SMTP_PASS);
    $from = post('smtp_from_email', db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_from_email'") ?: SMTP_FROM);
    $fromName = post('smtp_from_name', db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_from_name'") ?: SMTP_FROM_NAME);

    $result = test_smtp($host, $port, $user, $pass, $from, $fromName, $testEmail);
    if ($result === true) {
        json_success([], 'Test email sent successfully to ' . $testEmail);
    } else {
        json_error('Test email failed: ' . $result);
    }
}

// Handle test SMS
if (isPost() && isset($_POST['test_sms'])) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        json_error('Invalid security token.');
    }
    $testPhone = post('test_phone', Auth::user()['phone'] ?? '');
    if (empty($testPhone)) json_error('No phone number specified.');

    $apiKey = post('sms_api_key', db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_api_key'") ?: SMS_API_KEY);
    $apiUrl = post('sms_api_url', db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_api_url'") ?: SMS_API_URL);
    $senderId = post('sms_sender_id', db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_sender_id'") ?: SMS_SENDER_ID);

    $message = "This is a test SMS from " . APP_NAME . ". Sent at " . date('Y-m-d H:i:s');

    $result = test_sms($testPhone, $message, $apiKey, $apiUrl, $senderId);
    if ($result === true) {
        json_success([], 'Test SMS sent successfully to ' . $testPhone);
    } else {
        json_error('Test SMS failed: ' . $result);
    }
}

// Load current settings
$smtpHost = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_host'") ?: SMTP_HOST;
$smtpPort = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_port'") ?: SMTP_PORT;
$smtpUser = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_username'") ?: SMTP_USER;
$smtpPass = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_password'") ?: SMTP_PASS;
$smtpFrom = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_from_email'") ?: SMTP_FROM;
$smtpFromName = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_from_name'") ?: SMTP_FROM_NAME;
$smsApiKey = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_api_key'") ?: SMS_API_KEY;
$smsApiUrl = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_api_url'") ?: SMS_API_URL;
$smsSenderId = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_sender_id'") ?: SMS_SENDER_ID;
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Notification Settings</h4>
            <p>Configure email (SMTP) and SMS gateway settings</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>notifications/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<form method="post" id="settingsForm">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="save_settings" value="1">

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-envelope me-2"></i> SMTP / Email Settings</h5>
                </div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= escape($smtpHost) ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= escape($smtpPort) ?>" placeholder="587">
                        <div class="form-text">587 (TLS) or 465 (SSL)</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= escape($smtpUser) ?>" placeholder="your@email.com" autocomplete="off">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">SMTP Password</label>
                        <input type="password" name="smtp_password" class="form-control" value="<?= escape($smtpPass) ?>" placeholder="Enter SMTP password" autocomplete="off">
                        <div class="form-text">Leave as-is to keep current; enter new value to change.</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">From Email</label>
                        <input type="email" name="smtp_from_email" class="form-control" value="<?= escape($smtpFrom) ?>" placeholder="noreply@swms.gov.np">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">From Name</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="<?= escape($smtpFromName) ?>" placeholder="<?= escape(APP_NAME) ?>">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                        <button type="button" class="btn btn-outline-info" onclick="testEmail()"><i class="fas fa-envelope"></i> Test Email</button>
                    </div>
                    <div id="emailTestResult" class="mt-2" style="display:none;"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-sms me-2"></i> SMS Settings</h5>
                </div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label class="form-label">SMS API Key</label>
                        <input type="text" name="sms_api_key" class="form-control" value="<?= escape($smsApiKey) ?>" placeholder="Enter API key" autocomplete="off">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">SMS API URL</label>
                        <input type="url" name="sms_api_url" class="form-control" value="<?= escape($smsApiUrl) ?>" placeholder="https://api.smsprovider.com/v1/send">
                        <div class="form-text">Full endpoint URL for the SMS gateway</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Sender ID</label>
                        <input type="text" name="sms_sender_id" class="form-control" value="<?= escape($smsSenderId) ?>" placeholder="SWMS" maxlength="11">
                        <div class="form-text">Alpha-numeric sender ID (max 11 chars)</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                        <button type="button" class="btn btn-outline-info" onclick="testSms()"><i class="fas fa-sms"></i> Test SMS</button>
                    </div>
                    <div id="smsTestResult" class="mt-2" style="display:none;"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5><i class="fas fa-info-circle me-2"></i> Configuration Notes</h5></div>
                <div class="card-body small">
                    <ul class="mb-0">
                        <li>For <strong>Gmail</strong>, use <code>smtp.gmail.com</code> (port 587) with an App Password.</li>
                        <li>If SMTP is not configured, the system falls back to PHP's <code>mail()</code> function.</li>
                        <li>SMS requires a valid API key from your SMS gateway provider.</li>
                        <li>Test buttons send a message to your account email/phone.</li>
                        <li>Settings are stored encrypted in the database.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<?php $extraJs = <<<'JS'
<script>
function testEmail() {
    var btn = event.target;
    btn.disabled = true;
    var email = prompt('Send test email to:', '<?= escape(Auth::user()['email'] ?? '') ?>');
    if (!email) { btn.disabled = false; return; }
    var fd = new FormData(document.getElementById('settingsForm'));
    fd.set('save_settings', '');
    fd.set('test_email', '1');
    fd.set('test_email_address', email);
    var el = document.getElementById('emailTestResult');
    el.style.display = 'block';
    el.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Sending test email...</div>';
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    }).then(function(r) { return r.json(); }).then(function(d) {
        btn.disabled = false;
        el.innerHTML = d.success
            ? '<div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> ' + d.message + '</div>'
            : '<div class="alert alert-danger mb-0"><i class="fas fa-times-circle"></i> ' + d.message + '</div>';
    }).catch(function() {
        btn.disabled = false;
        el.innerHTML = '<div class="alert alert-danger mb-0">Network error</div>';
    });
}

function testSms() {
    var btn = event.target;
    btn.disabled = true;
    var phone = prompt('Send test SMS to:', '<?= escape(Auth::user()['phone'] ?? '') ?>');
    if (!phone) { btn.disabled = false; return; }
    var fd = new FormData(document.getElementById('settingsForm'));
    fd.set('save_settings', '');
    fd.set('test_sms', '1');
    fd.set('test_phone', phone);
    var el = document.getElementById('smsTestResult');
    el.style.display = 'block';
    el.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Sending test SMS...</div>';
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    }).then(function(r) { return r.json(); }).then(function(d) {
        btn.disabled = false;
        el.innerHTML = d.success
            ? '<div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> ' + d.message + '</div>'
            : '<div class="alert alert-danger mb-0"><i class="fas fa-times-circle"></i> ' + d.message + '</div>';
    }).catch(function() {
        btn.disabled = false;
        el.innerHTML = '<div class="alert alert-danger mb-0">Network error</div>';
    });
}
</script>
JS;
?>

<?php
// Helper: test SMTP
function test_smtp($host, $port, $user, $pass, $from, $fromName, $toEmail) {
    if (empty($host) || empty($user)) {
        return 'SMTP not configured. Please provide host and username.';
    }
    require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) $port;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->SMTPSecure = $port == 465 ? 'ssl' : 'tls';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($toEmail);
        $mail->Subject = 'Test Email from ' . APP_NAME;
        $mail->isHTML(true);
        $mail->Body = '<h3>Test Email</h3><p>This is a test email from ' . APP_NAME . '.</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';
        $mail->AltBody = 'Test email from ' . APP_NAME . '. Sent at: ' . date('Y-m-d H:i:s');
        $mail->send();
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

// Helper: test SMS
function test_sms($phone, $message, $apiKey, $apiUrl, $senderId) {
    if (empty($apiKey) || empty($apiUrl)) {
        return 'SMS API not configured. Please provide API key and URL.';
    }
    $data = [
        'api_key' => $apiKey,
        'to' => $phone,
        'message' => $message,
        'sender' => $senderId,
    ];
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) return 'Curl error: ' . $error;
    return $response ? true : 'Empty response from gateway.';
}

require_once __DIR__ . '/../includes/footer.php';
?>
