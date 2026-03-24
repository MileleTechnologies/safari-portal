<?php
// ============================================
// config.php — Database & App Configuration
// ============================================

// ----- DATABASE SETTINGS -----
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your DB username
define('DB_PASS', '');           // Change to your DB password
define('DB_NAME', 'safari_portal');

// ----- ADMIN CREDENTIALS -----
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'safari2025');  // Change this!

// ----- APP SETTINGS -----
define('APP_NAME', 'Safari Contribution Portal');
define('CURRENCY', 'KSh');   // Change to your currency symbol

// ----- BASE URL (auto-detect) -----
$baseUrl = '';
if (!empty($_SERVER['SCRIPT_NAME']) && preg_match('#/safari-portal(/|$)#', $_SERVER['SCRIPT_NAME'])) {
    $baseUrl = '/safari-portal';
}
define('BASE_URL', $baseUrl);

// =====================================================
// SMS NOTIFICATION SETTINGS
// =====================================================
// Provider options: 'log' | 'africastalking' | 'twilio'
// Use 'log' during development (writes to sms_log.txt)
define('SMS_PROVIDER', 'log');

// Default country code for local numbers (Kenya = 254)
define('SMS_COUNTRY_CODE', '254');

// ----- Africa's Talking credentials -----
// Sign up at https://africastalking.com
// define('AT_USERNAME', 'your_sandbox_or_live_username');
// define('AT_API_KEY',  'your_api_key_here');
// define('AT_SENDER',   'SAFARI');  // Optional sender name/shortcode

// ----- Twilio credentials -----
// Sign up at https://twilio.com
// define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
// define('TWILIO_AUTH_TOKEN',  'your_auth_token_here');
// define('TWILIO_FROM_NUMBER', '+1234567890');  // Your Twilio phone number
// =====================================================

// ----- SESSION START -----
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----- DATABASE CONNECTION -----
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die("<div style='font-family:sans-serif;padding:2rem;color:#c0392b;'>
                <h2>Database Connection Error</h2>
                <p>Could not connect to database. Please check your config.php settings.</p>
                <pre>" . htmlspecialchars($e->getMessage()) . "</pre>
            </div>");
        }
    }
    return $pdo;
}

// ----- AUTO MIGRATION: Upgrade to v3 schema -----
function runMigrations() {
    $db = getDB();

    // ---- Payments table: add v2 columns if missing ----
    $hasStatus = false;
    try { $db->query("SELECT status FROM payments LIMIT 1"); $hasStatus = true; }
    catch (PDOException $e) { $hasStatus = false; }

    if (!$hasStatus) {
        $cols = [
            "ADD COLUMN reference_number VARCHAR(100) NOT NULL DEFAULT '' AFTER amount",
            "ADD COLUMN proof_file       VARCHAR(255) DEFAULT NULL AFTER reference_number",
            "ADD COLUMN status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER proof_file",
            "ADD COLUMN rejection_reason VARCHAR(255) DEFAULT NULL AFTER status",
            "ADD COLUMN submitted_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER payment_date",
            "ADD COLUMN reviewed_at      TIMESTAMP NULL DEFAULT NULL AFTER submitted_at",
        ];
        foreach ($cols as $col) {
            try { $db->exec("ALTER TABLE payments $col"); } catch (PDOException $e) { /* already exists */ }
        }
        try { $db->exec("UPDATE payments SET status='approved', reference_number=CONCAT('LEGACY-',id) WHERE reference_number=''"); }
        catch (PDOException $e) { /* ignore */ }
    }

    // Drop old 'note' column if present
    try {
        $db->query("SELECT note FROM payments LIMIT 1");
        try { $db->exec("ALTER TABLE payments DROP COLUMN note"); } catch (PDOException $e) { /* ignore */ }
    } catch (PDOException $e) { /* already gone */ }

    // ---- Users table: add phone_number and whatsapp_invited if missing ----
    $hasPhone = false;
    try { $db->query("SELECT phone_number FROM users LIMIT 1"); $hasPhone = true; }
    catch (PDOException $e) { $hasPhone = false; }

    if (!$hasPhone) {
        try { $db->exec("ALTER TABLE users ADD COLUMN phone_number VARCHAR(20) NOT NULL DEFAULT '' AFTER work_id"); }
        catch (PDOException $e) { /* ignore */ }
    }

    $hasWaInvited = false;
    try { $db->query("SELECT whatsapp_invited FROM users LIMIT 1"); $hasWaInvited = true; }
    catch (PDOException $e) { $hasWaInvited = false; }

    if (!$hasWaInvited) {
        try { $db->exec("ALTER TABLE users ADD COLUMN whatsapp_invited TINYINT(1) NOT NULL DEFAULT 0 AFTER phone_number"); }
        catch (PDOException $e) { /* ignore */ }
    }

    // ---- Settings: add sms_notifications setting ----
    try {
        $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value)
                   VALUES ('sms_notifications_enabled', '1')");
    } catch (PDOException $e) { /* ignore */ }
}

runMigrations();

// Load SMS system
require_once __DIR__ . '/notifications/sms.php';

// ----- HELPER: Get a setting value -----
function getSetting($key) {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : '';
}

// ----- HELPER: Format currency -----
function money($amount) {
    return CURRENCY . ' ' . number_format((float)$amount, 2);
}

// ----- HELPER: Redirect -----
function redirect($url) {
    header("Location: $url");
    exit;
}

// ----- HELPER: Admin auth check -----
function requireAdmin() {
    if (empty($_SESSION['admin_logged_in'])) {
        redirect(BASE_URL . '/admin/admin-login.php');
    }
}

// ----- HELPER: Flash messages -----
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ----- HELPER: Process payment approval with notifications -----
// Call this ONCE whenever a payment is approved (from any admin page).
function approvePayment(int $paymentId): array {
    $db = getDB();

    // Load full payment + user data
    $stmt = $db->prepare("
        SELECT p.*, u.full_name, u.phone_number, u.target_amount,
               u.whatsapp_invited, u.id AS uid
        FROM payments p
        JOIN users u ON u.id = p.user_id
        WHERE p.id = ? AND p.status = 'pending'
    ");
    $stmt->execute([$paymentId]);
    $pay = $stmt->fetch();

    if (!$pay) {
        return ['success' => false, 'message' => 'Payment not found or already reviewed.'];
    }

    // 1. Mark as approved
    $db->prepare("UPDATE payments SET status='approved', reviewed_at=NOW(), rejection_reason=NULL WHERE id=?")
       ->execute([$paymentId]);

    // 2. Count previously approved payments for this user (BEFORE this one)
    $stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE user_id=? AND status='approved' AND id != ?");
    $stmt->execute([$pay['user_id'], $paymentId]);
    $prevApprovedCount = (int)$stmt->fetchColumn();

    // 3. Get new total approved
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE user_id=? AND status='approved'");
    $stmt->execute([$pay['user_id']]);
    $newTotal = (float)$stmt->fetchColumn();

    $isFirstApproval = ($prevApprovedCount === 0);
    $smsResult       = null;
    $smsEnabled      = getSetting('sms_notifications_enabled') === '1';
    $whatsappLink    = getSetting('whatsapp_group_link');

    // 4. Send SMS notification
    if ($smsEnabled && !empty($pay['phone_number'])) {
        if ($isFirstApproval && !empty($whatsappLink)) {
            // First approval: send WhatsApp invite link
            $smsResult = Sms::sendWhatsAppInvite(
                $pay['full_name'],
                $pay['phone_number'],
                (float)$pay['amount'],
                $whatsappLink
            );
            // Mark user as WhatsApp invited
            $db->prepare("UPDATE users SET whatsapp_invited=1 WHERE id=?")
               ->execute([$pay['uid']]);
        } else {
            // Subsequent approval: send plain approval notice
            $smsResult = Sms::sendApprovalNotice(
                $pay['full_name'],
                $pay['phone_number'],
                (float)$pay['amount'],
                $newTotal,
                (float)$pay['target_amount']
            );
        }
    }

    // Build result message
    $msg = '✅ Payment of ' . money($pay['amount']) . ' for ' . $pay['full_name'] . ' approved.';
    if ($isFirstApproval && !empty($whatsappLink)) {
        $msg .= ' WhatsApp invite';
        if ($smsResult && $smsResult['success']) {
            $msg .= ' sent via SMS.';
        } elseif (!empty($pay['phone_number'])) {
            $msg .= ' SMS failed — check sms_log.txt.';
        } else {
            $msg .= ' (no phone on file).';
        }
    }

    return [
        'success'        => true,
        'message'        => $msg,
        'isFirstApproval'=> $isFirstApproval,
        'smsResult'      => $smsResult,
        'newTotal'       => $newTotal,
    ];
}
