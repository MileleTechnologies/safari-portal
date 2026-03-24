<?php
// ============================================
// admin/admin-login.php — Admin Login Page
// ============================================
require_once __DIR__ . '/../config.php';

// Already logged in → redirect
if (!empty($_SESSION['admin_logged_in'])) {
    redirect(BASE_URL . '/admin/admin-dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $username;
        redirect(BASE_URL . '/admin/admin-dashboard.php');
    } else {
        $error = 'Incorrect username or password. Please try again.';
    }
}

$tripName = getSetting('trip_name') ?: APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= htmlspecialchars($tripName) ?></title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<div class="login-page">
    <div class="login-logo">🦁</div>
    <h1 class="login-title"><?= htmlspecialchars($tripName) ?></h1>
    <p class="login-sub">Admin Panel — Secure Login</p>

    <div class="login-box">
        <h2 style="margin-bottom:1.2rem;text-align:center;">Sign In</h2>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       placeholder="Enter username" autocomplete="username"
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                       required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Enter password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">
                🔑 Sign In
            </button>
        </form>

        <p style="text-align:center;margin-top:1.2rem;font-size:0.85rem;">
            <a href="../index.php">← Back to User Portal</a>
        </p>
    </div>
</div>

</body>
</html>
