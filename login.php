<?php
// ============================================
// login.php — User Login Page
// ============================================
require_once __DIR__ . '/config.php';

// Already logged in → redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    redirect(BASE_URL . '/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workId   = trim(strtoupper($_POST['work_id'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (empty($workId) || empty($password)) {
        $error = 'Please enter both Work ID and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE work_id = ? AND password = ?");
        $stmt->execute([$workId, $password]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_work_id'] = $user['work_id'];
            $_SESSION['user_name'] = $user['full_name'];
            redirect(BASE_URL . '/index.php');
        } else {
            $error = 'Invalid Work ID or password. Please try again.';
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
    <title>Employee Login — <?= htmlspecialchars($tripName) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
</head>
<body>

<div class="login-page">
    <div class="login-logo"><i class="fa-solid fa-paw"></i></div>
    <h1 class="login-title"><?= htmlspecialchars($tripName) ?></h1>
    <p class="login-sub">Employee Portal — Secure Login</p>

    <div class="login-box">
        <h2 style="margin-bottom:1.2rem;text-align:center;">Sign In</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="work_id">Work ID</label>
                <input type="text" id="work_id" name="work_id" class="form-control"
                       placeholder="e.g. EMP001" autocomplete="username"
                       value="<?= isset($_POST['work_id']) ? htmlspecialchars($_POST['work_id']) : '' ?>"
                       required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Enter your password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <p style="text-align:center;margin-top:1.2rem;font-size:0.85rem;">
            <a href="admin/admin-login.php"><i class="fa-solid fa-shield-halved"></i> Admin Login</a>
        </p>
    </div>
</div>

</body>
</html>
