<?php
// ============================================
// change-password.php — User Change Password
// ============================================
require_once __DIR__ . '/config.php';

// Require user to be logged in
requireUser();

$user = getCurrentUser();
if (!$user) {
    redirect(BASE_URL . '/logout.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword     = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all password fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($newPassword) < 4) {
        $error = 'New password must be at least 4 characters long.';
    } elseif ($newPassword === $currentPassword) {
        $error = 'New password must be different from the current password.';
    } else {
        // Verify current password
        $db = getDB();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $storedPassword = $stmt->fetchColumn();

        if ($storedPassword !== $currentPassword) {
            $error = 'Current password is incorrect.';
        } else {
            // Update password
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newPassword, $user['id']]);
            $success = 'Password changed successfully! Please use your new password for future logins.';
        }
    }
}

$tripName = getSetting('trip_name') ?: APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — <?= htmlspecialchars($tripName) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
</head>
<body>

<nav class="navbar">
    <a class="navbar-brand" href="index.php"><span class="icon"><i class="fa-solid fa-paw"></i></span> Safari Portal</a>
    <div class="navbar-links">
        <span style="color:var(--text-muted);margin-right:1rem;"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($user['full_name']) ?></span>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</nav>

<div class="page-header">
    <div class="subtitle">My Account</div>
    <h1>Change Password</h1>
    <p>Update your login password for security</p>
</div>

<div class="container-sm" style="padding-top:2rem;padding-bottom:2rem;">

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3><i class="fa-solid fa-key"></i> Update Password</h3></div>
        <div class="card-body">
            <form method="POST" action="">

                <div class="form-group">
                    <label for="current_password">Current Password <span style="color:var(--red)">*</span></label>
                    <input type="password" id="current_password" name="current_password" class="form-control"
                           placeholder="Enter your current password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password <span style="color:var(--red)">*</span></label>
                    <input type="password" id="new_password" name="new_password" class="form-control"
                           placeholder="Enter new password (min 4 chars)" required>
                    <small style="color:var(--text-muted);">Minimum 4 characters recommended.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span style="color:var(--red)">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                           placeholder="Re-enter new password" required>
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="submit" name="change_password" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Change Password</button>
                    <a href="index.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Password Tips Card -->
    <div class="card mt-3" style="border-left:4px solid var(--gold);">
        <div class="card-body" style="padding:1rem 1.2rem;">
            <strong style="color:var(--brown);"><i class="fa-solid fa-lightbulb"></i> Password Tips</strong>
            <ul style="font-size:0.85rem;color:var(--text-muted);margin:0.5rem 0 0 1.2rem;line-height:1.6;">
                <li>Use at least 4 characters (more is better)</li>
                <li>Mix letters and numbers for stronger security</li>
                <li>Avoid using obvious passwords like "1234" or your name</li>
                <li>Keep your password private — don't share it with anyone</li>
            </ul>
        </div>
    </div>

</div>

</body>
</html>
