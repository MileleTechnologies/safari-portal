<?php
// ============================================
// notifications/sms.php
// Modular SMS Notification System
//
// Supports:
//   - Africa's Talking  (set SMS_PROVIDER = 'africastalking')
//   - Twilio            (set SMS_PROVIDER = 'twilio')
//   - Log only / Mock   (set SMS_PROVIDER = 'log'  — default for dev/testing)
//
// To switch provider: change SMS_PROVIDER in config.php
// Each provider implements the same sendSms($to, $message) interface.
// ============================================

// -----------------------------------------------
// BASE INTERFACE — all providers must follow this
// -----------------------------------------------
interface SmsProviderInterface {
    /**
     * Send an SMS message.
     *
     * @param string $to      Phone number in international format, e.g. +254712345678
     * @param string $message The text message to send
     * @return array ['success' => bool, 'message' => string, 'raw' => mixed]
     */
    public function send(string $to, string $message): array;
}


// -----------------------------------------------
// LOG PROVIDER — default for dev/testing
// Does NOT send a real SMS. Writes to a log file.
// -----------------------------------------------
class LogSmsProvider implements SmsProviderInterface {
    public function send(string $to, string $message): array {
        $logLine = '[' . date('Y-m-d H:i:s') . '] TO:' . $to . ' MSG:' . $message . PHP_EOL;
        $logFile = __DIR__ . '/../sms_log.txt';
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        return ['success' => true, 'message' => 'Logged (no real SMS sent)', 'raw' => $logLine];
    }
}


// -----------------------------------------------
// AFRICA'S TALKING PROVIDER
// Docs: https://developers.africastalking.com/docs/sms/sending
//
// Required config constants (add to config.php):
//   define('AT_USERNAME', 'your_username');
//   define('AT_API_KEY',  'your_api_key');
//   define('AT_SENDER',   'SAFARI');   // optional shortcode/alphanumeric
// -----------------------------------------------
class AfricasTalkingSmsProvider implements SmsProviderInterface {
    private string $username;
    private string $apiKey;
    private string $sender;
    private string $endpoint = 'https://api.africastalking.com/version1/messaging';

    public function __construct() {
        $this->username = defined('AT_USERNAME') ? AT_USERNAME : '';
        $this->apiKey   = defined('AT_API_KEY')  ? AT_API_KEY  : '';
        $this->sender   = defined('AT_SENDER')   ? AT_SENDER   : '';
    }

    public function send(string $to, string $message): array {
        if (empty($this->username) || empty($this->apiKey)) {
            return ['success' => false, 'message' => 'Africa\'s Talking credentials not configured.', 'raw' => null];
        }

        $params = [
            'username' => $this->username,
            'to'       => $to,
            'message'  => $message,
        ];
        if (!empty($this->sender)) {
            $params['from'] = $this->sender;
        }

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'apiKey: ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'cURL error: ' . $error, 'raw' => null];
        }

        $data = json_decode($response, true);
        $ok   = $httpCode === 201 &&
                isset($data['SMSMessageData']['Recipients'][0]['status']) &&
                $data['SMSMessageData']['Recipients'][0]['status'] === 'Success';

        return [
            'success' => $ok,
            'message' => $ok ? 'SMS sent via Africa\'s Talking' : ('AT error: ' . $response),
            'raw'     => $data,
        ];
    }
}


// -----------------------------------------------
// TWILIO PROVIDER
// Docs: https://www.twilio.com/docs/sms/api
//
// Required config constants (add to config.php):
//   define('TWILIO_ACCOUNT_SID', 'ACxxxxx');
//   define('TWILIO_AUTH_TOKEN',  'your_token');
//   define('TWILIO_FROM_NUMBER', '+1234567890'); // your Twilio number
// -----------------------------------------------
class TwilioSmsProvider implements SmsProviderInterface {
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;

    public function __construct() {
        $this->accountSid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
        $this->authToken  = defined('TWILIO_AUTH_TOKEN')  ? TWILIO_AUTH_TOKEN  : '';
        $this->fromNumber = defined('TWILIO_FROM_NUMBER') ? TWILIO_FROM_NUMBER : '';
    }

    public function send(string $to, string $message): array {
        if (empty($this->accountSid) || empty($this->authToken) || empty($this->fromNumber)) {
            return ['success' => false, 'message' => 'Twilio credentials not configured.', 'raw' => null];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'From' => $this->fromNumber,
                'To'   => $to,
                'Body' => $message,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->accountSid . ':' . $this->authToken,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'cURL error: ' . $error, 'raw' => null];
        }

        $data = json_decode($response, true);
        $ok   = in_array($httpCode, [200, 201]) && !isset($data['code']); // Twilio errors include 'code'

        return [
            'success' => $ok,
            'message' => $ok ? 'SMS sent via Twilio' : ('Twilio error: ' . ($data['message'] ?? $response)),
            'raw'     => $data,
        ];
    }
}


// -----------------------------------------------
// SMS FACADE — main entry point used by app code
// Usage:
//   $result = Sms::send('+254712345678', 'Hello!');
// -----------------------------------------------
class Sms {
    private static ?SmsProviderInterface $provider = null;

    /** Get (or create) the configured provider instance */
    private static function provider(): SmsProviderInterface {
        if (self::$provider === null) {
            $name = defined('SMS_PROVIDER') ? strtolower(SMS_PROVIDER) : 'log';
            self::$provider = match($name) {
                'africastalking', 'at' => new AfricasTalkingSmsProvider(),
                'twilio'               => new TwilioSmsProvider(),
                default                => new LogSmsProvider(),   // 'log' or anything unknown
            };
        }
        return self::$provider;
    }

    /**
     * Send an SMS.
     *
     * @param string $to      Phone in international format: +254712345678
     * @param string $message Text body
     * @return array ['success' => bool, 'message' => string, 'raw' => mixed]
     */
    public static function send(string $to, string $message): array {
        // Normalise phone: ensure it starts with +
        $to = self::normalisePhone($to);
        $result = self::provider()->send($to, $message);

        // Always log result for audit trail
        self::logResult($to, $message, $result);
        return $result;
    }

    /**
     * Send the WhatsApp invite SMS after first payment approval.
     *
     * @param string $fullName     User's full name
     * @param string $phone        User's phone number
     * @param float  $amount       Approved payment amount
     * @param string $whatsappLink WhatsApp group invite URL
     * @return array SMS result
     */
    public static function sendWhatsAppInvite(
        string $fullName,
        string $phone,
        float  $amount,
        string $whatsappLink
    ): array {
        $message = "Hello {$fullName}. Your safari contribution payment of "
                 . CURRENCY . ' ' . number_format($amount, 2)
                 . " has been approved. You can now join the safari WhatsApp group using this link: {$whatsappLink}";

        return self::send($phone, $message);
    }

    /**
     * Send a payment approval notification (for subsequent approvals — no WA link).
     */
    public static function sendApprovalNotice(
        string $fullName,
        string $phone,
        float  $amount,
        float  $totalApproved,
        float  $target
    ): array {
        $remaining = max(0, $target - $totalApproved);
        $message   = "Hello {$fullName}. Your safari contribution of "
                   . CURRENCY . ' ' . number_format($amount, 2)
                   . " has been approved. Total approved: "
                   . CURRENCY . ' ' . number_format($totalApproved, 2)
                   . ". Remaining: "
                   . CURRENCY . ' ' . number_format($remaining, 2) . ".";
        return self::send($phone, $message);
    }

    /**
     * Normalise phone number to international format.
     * Handles common formats like 0712345678 → +254712345678 (Kenya default).
     */
    public static function normalisePhone(string $phone): string {
        $phone = preg_replace('/\s+/', '', $phone); // strip spaces
        if (substr($phone, 0, 1) === '+') return $phone;
        if (substr($phone, 0, 2) === '00') return '+' . substr($phone, 2);
        // Local number starting with 0 — use default country code from config
        if (substr($phone, 0, 1) === '0') {
            $cc = defined('SMS_COUNTRY_CODE') ? SMS_COUNTRY_CODE : '254'; // Kenya default
            return '+' . $cc . substr($phone, 1);
        }
        return '+' . $phone;
    }

    /** Write a result to sms_log.txt for auditing */
    private static function logResult(string $to, string $message, array $result): void {
        $status  = $result['success'] ? 'OK' : 'FAIL';
        $logLine = '[' . date('Y-m-d H:i:s') . '] [' . $status . '] TO:' . $to
                 . ' | ' . $result['message']
                 . ' | MSG:' . mb_substr($message, 0, 80) . '...'
                 . PHP_EOL;
        @file_put_contents(__DIR__ . '/../sms_log.txt', $logLine, FILE_APPEND | LOCK_EX);
    }
}
