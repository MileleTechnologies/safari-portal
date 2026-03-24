<?php
// admin/clear-sms-log.php — Clear the SMS activity log
require_once __DIR__ . '/../config.php';
requireAdmin();

$logFile = __DIR__ . '/../sms_log.txt';
if (file_exists($logFile)) {
    file_put_contents($logFile, '');
}
setFlash('success', 'SMS log cleared.');
redirect('whatsapp-settings.php');
