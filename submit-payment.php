<?php
// submit-payment.php — User Payment Submission (saves as PENDING)
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$userId          = (int)($_POST['user_id'] ?? 0);
$amount          = (float)($_POST['amount'] ?? 0);
$referenceNumber = trim($_POST['reference_number'] ?? '');
$paymentDate     = trim($_POST['payment_date'] ?? date('Y-m-d'));
$workId          = trim($_POST['work_id'] ?? '');

$errors = [];
if ($userId <= 0)            $errors[] = 'Invalid user.';
if ($amount <= 0)            $errors[] = 'Invalid amount.';
if (empty($referenceNumber)) $errors[] = 'Reference number is required.';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) $paymentDate = date('Y-m-d');

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    redirect('index.php?work_id=' . urlencode($workId));
}

$db = getDB();
$stmt = $db->prepare("SELECT id, full_name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    setFlash('error', 'User not found.');
    redirect('index.php');
}

// Handle file upload
$proofFileName = null;
if (!empty($_FILES['proof_file']['name'])) {
    $file    = $_FILES['proof_file'];
    $maxSize = 5 * 1024 * 1024;
    $allowed = ['jpg','jpeg','png','gif','webp','pdf'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'File upload error. Please try again.');
        redirect('index.php?work_id=' . urlencode($workId));
    }
    if ($file['size'] > $maxSize) {
        setFlash('error', 'File too large. Maximum 5MB allowed.');
        redirect('index.php?work_id=' . urlencode($workId));
    }
    if (!in_array($ext, $allowed)) {
        setFlash('error', 'Invalid file type. Use JPG, PNG, or PDF.');
        redirect('index.php?work_id=' . urlencode($workId));
    }

    $proofFileName = $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $uploadDir     = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $proofFileName)) {
        setFlash('error', 'Could not save uploaded file. Check folder permissions on /uploads/.');
        redirect('index.php?work_id=' . urlencode($workId));
    }
}

// Insert as PENDING
$stmt = $db->prepare("
    INSERT INTO payments (user_id, amount, reference_number, proof_file, status, payment_date)
    VALUES (?, ?, ?, ?, 'pending', ?)
");
$stmt->execute([$userId, $amount, $referenceNumber, $proofFileName, $paymentDate]);

setFlash('info', 'Your payment request has been submitted and is awaiting admin approval.');
redirect('index.php?work_id=' . urlencode($workId));
