<?php
// Send Notification Handler - POST only
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('settings.view');

if (!isPost()) {
    redirect(ADMIN_URL . 'notifications/index.php');
}

$csrf = post('csrf_token');
if (!verify_csrf($csrf)) {
    alert_error('Invalid security token.');
    redirect(ADMIN_URL . 'notifications/index.php');
}

$type = post('type', '');
$channel = post('channel', 'system');
$title = trim(post('title', ''));
$message = trim(post('message', ''));
$recipientType = post('recipient_type', 'all_consumers');
$consumerId = (int) post('consumer_id', 0);
$roleId = (int) post('role_id', 0);

if (empty($type) || empty($title) || empty($message)) {
    alert_error('Type, title, and message are required.');
    redirect(ADMIN_URL . 'notifications/index.php');
}

$sentCount = 0;
$failCount = 0;
$recipients = [];

// Determine recipients
switch ($recipientType) {
    case 'all_consumers':
        $consumers = db()->fetchAll(
            "SELECT id, full_name, mobile, email FROM consumers WHERE deleted_at IS NULL AND status = 'active'"
        );
        foreach ($consumers as $c) {
            $recipients[] = [
                'consumer_id' => $c['id'],
                'user_id' => null,
                'name' => $c['full_name'],
                'phone' => $c['mobile'],
                'email' => $c['email'],
            ];
        }
        break;

    case 'specific_consumer':
        if (!$consumerId) {
            alert_error('Please select a consumer.');
            redirect(ADMIN_URL . 'notifications/index.php');
        }
        $consumer = db()->fetchOne(
            "SELECT id, full_name, mobile, email FROM consumers WHERE id = ? AND deleted_at IS NULL",
            [$consumerId]
        );
        if ($consumer) {
            $recipients[] = [
                'consumer_id' => $consumer['id'],
                'user_id' => null,
                'name' => $consumer['full_name'],
                'phone' => $consumer['mobile'],
                'email' => $consumer['email'],
            ];
        }
        break;

    case 'user_group':
        if (!$roleId) {
            alert_error('Please select a user group.');
            redirect(ADMIN_URL . 'notifications/index.php');
        }
        $users = db()->fetchAll(
            "SELECT id, name, email FROM users WHERE role_id = ? AND deleted_at IS NULL AND status = 'active'",
            [$roleId]
        );
        foreach ($users as $u) {
            $recipients[] = [
                'consumer_id' => null,
                'user_id' => $u['id'],
                'name' => $u['name'],
                'phone' => null,
                'email' => $u['email'],
            ];
        }
        break;

    default:
        alert_error('Invalid recipient type.');
        redirect(ADMIN_URL . 'notifications/index.php');
}

if (empty($recipients)) {
    alert_error('No recipients found.');
    redirect(ADMIN_URL . 'notifications/index.php');
}

// Load SMS/Email config
$smtpHost = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_host'") ?: SMTP_HOST;
$smtpPort = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_port'") ?: SMTP_PORT;
$smtpUser = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_username'") ?: SMTP_USER;
$smtpPass = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_password'") ?: SMTP_PASS;
$smtpFrom = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_from_email'") ?: SMTP_FROM;
$smtpFromName = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_from_name'") ?: SMTP_FROM_NAME;
$smsApiKey = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_api_key'") ?: SMS_API_KEY;
$smsApiUrl = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_api_url'") ?: SMS_API_URL;
$smsSenderId = db()->fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_sender_id'") ?: SMS_SENDER_ID;

$sendSms = in_array($channel, ['sms', 'both']);
$sendEmail = in_array($channel, ['email', 'both']);
$sendSystem = in_array($channel, ['system', 'both']);

foreach ($recipients as $r) {
    $notificationId = db()->insert('notifications', [
        'user_id' => $r['user_id'],
        'consumer_id' => $r['consumer_id'],
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'channel' => $channel,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $success = true;

    // Send SMS
    if ($sendSms && !empty($r['phone'])) {
        $smsSent = send_sms($r['phone'], $message, $smsApiKey, $smsApiUrl, $smsSenderId);
        db()->insert('sms_logs', [
            'phone' => $r['phone'],
            'message' => $message,
            'sender_id' => $smsSenderId,
            'gateway_response' => is_string($smsSent) ? $smsSent : json_encode($smsSent),
            'status' => $smsSent ? 'sent' : 'failed',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$smsSent) $success = false;
    }

    // Send Email
    if ($sendEmail && !empty($r['email'])) {
        $emailSent = send_email($r['email'], $title, nl2br(escape($message)), $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpFromName);
        db()->insert('email_logs', [
            'recipient' => $r['email'],
            'subject' => $title,
            'message' => $message,
            'status' => $emailSent ? 'sent' : 'failed',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$emailSent) $success = false;
    }

    // System notifications are always "sent" (stored in DB)
    if ($sendSystem) {
        $success = true;
    }

    $newStatus = $success ? 'sent' : 'failed';
    $sentAt = $success ? date('Y-m-d H:i:s') : null;

    db()->update('notifications', [
        'status' => $newStatus,
        'sent_at' => $sentAt,
    ], 'id = :id', ['id' => $notificationId]);

    if ($success) $sentCount++;
    else $failCount++;
}

log_activity(Auth::id(), 'send', 'notifications', "Sent {$sentCount} notifications (type: {$type}, channel: {$channel})");

if ($failCount > 0) {
    alert_warning("Sent {$sentCount} notification(s), {$failCount} failed.");
} else {
    alert_success("Successfully sent {$sentCount} notification(s).");
}

redirect(ADMIN_URL . 'notifications/index.php');

// Helper: send SMS via API
function send_sms($phone, $message, $apiKey, $apiUrl, $senderId) {
    if (empty($apiKey) || empty($apiUrl)) return false;
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
    if ($error) {
        error_log("SMS send error: {$error}");
        return false;
    }
    return $response;
}

// Helper: send email via SMTP
function send_email($to, $subject, $htmlMessage, $host, $port, $user, $pass, $from, $fromName) {
    if (empty($host) || empty($user)) {
        // Fallback to mail()
        $headers = "From: {$fromName} <{$from}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return mail($to, $subject, $htmlMessage, $headers);
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
        $mail->SMTPSecure = $port == 587 ? 'tls' : 'ssl';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlMessage;
        $mail->AltBody = strip_tags($htmlMessage);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send error: " . $e->getMessage());
        return false;
    }
}
?>
