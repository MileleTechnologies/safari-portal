<?php
// ============================================
// admin/add-user.php — Add New User (v3)
// ============================================
require_once __DIR__ . '/../config.php';
requireAdmin();

$flash  = getFlash();
$errors = [];
$form   = ['full_name' => '', 'work_id' => '', 'phone_number' => '', 'target_amount' => '', 'password' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $form['full_name']     = trim($_POST['full_name']     ?? '');
    $form['work_id']       = strtoupper(trim($_POST['work_id'] ?? ''));
    $form['phone_number']  = trim($_POST['phone_number']  ?? '');
    $form['password']      = trim($_POST['password']      ?? '');
    $form['target_amount'] = trim($_POST['target_amount'] ?? '');

    // Validation
    if (empty($form['full_name']))    $errors[] = 'Full name is required.';
    if (empty($form['work_id']))      $errors[] = 'Work ID is required.';
    if (empty($form['phone_number'])) $errors[] = 'Phone number is required.';
    if (empty($form['password']) || strlen($form['password']) < 4)
        $errors[] = 'Password is required (minimum 4 characters).';
    if (!is_numeric($form['target_amount']) || (float)$form['target_amount'] <= 0)
        $errors[] = 'Target amount must be a positive number.';

    // Phone format: must start with 0, +, or country code digits
    if (!empty($form['phone_number']) && !preg_match('/^[\+0-9][0-9\s\-]{6,18}$/', $form['phone_number'])) {
        $errors[] = 'Enter a valid phone number (e.g. 0712345678 or +254712345678).';
    }

    if (empty($errors)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE work_id = ?");
        $stmt->execute([$form['work_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'Work ID "' . htmlspecialchars($form['work_id']) . '" already exists.';
        } else {
            $stmt = $db->prepare("INSERT INTO users (full_name, work_id, phone_number, target_amount, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $form['full_name'],
                $form['work_id'],
                $form['phone_number'],
                (float)$form['target_amount'],
                $form['password']
            ]);
            setFlash('success', 'User "' . $form['full_name'] . '" added successfully!');
            redirect('admin-dashboard.php');
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
    <title>Add User — <?= htmlspecialchars($tripName) ?></title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
</head>
<body>

<nav class="navbar">
    <a class="navbar-brand" href="admin-dashboard.php">
        <span class="icon"><i class="fa-solid fa-paw"></i></span> Admin Panel
    </a>
    <div class="navbar-links">
        <a href="admin-dashboard.php">Dashboard</a>
        <a href="add-user.php" class="active">Add User</a>
        <a href="payment-requests.php">Requests</a>
        <a href="admin-logout.php">Logout</a>
    </div>
</nav>

<div class="page-header">
    <div class="subtitle">Admin</div>
    <h1>Add New User</h1>
    <p>Register an employee for the Safari fund</p>
</div>

<div class="container-sm" style="padding-top:2rem;padding-bottom:2rem;">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <?= $flash['type'] === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?> <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i> Please fix the following:
            <ul style="margin:0.4rem 0 0 1.2rem;">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3><i class="fa-solid fa-user"></i> User Details</h3></div>
        <div class="card-body">
            <form method="POST" action="">

                <div class="form-group">
                    <label for="full_name">Full Name <span style="color:var(--red)">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control"
                           placeholder="e.g. Alice Mwangi"
                           value="<?= htmlspecialchars($form['full_name']) ?>"
                           required autofocus>
                </div>

                <div class="form-group">
                    <label for="work_id">Work ID <span style="color:var(--red)">*</span></label>
                    <input type="text" id="work_id" name="work_id" class="form-control"
                           placeholder="e.g. EMP001"
                           value="<?= htmlspecialchars($form['work_id']) ?>" required>
                    <small style="color:var(--text-muted);">Unique login ID — auto-uppercased.</small>
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number <span style="color:var(--red)">*</span></label>
                    <input type="tel" id="phone_number" name="phone_number" class="form-control"
                           placeholder="e.g. 0712345678 or +254712345678"
                           value="<?= htmlspecialchars($form['phone_number']) ?>" required>
                    <small style="color:var(--text-muted);">
                        Used to send SMS notifications &amp; WhatsApp group invite after first approved payment.
                    </small>
                </div>

                <div class="form-group">
                    <label for="password">Initial Password <span style="color:var(--red)">*</span></label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter initial password (min 4 chars)"
                           required>
                    <small style="color:var(--text-muted);">
                        User will use this password to login. They can change it later.
                    </small>
                </div>

                <div class="form-group">
                    <label for="target_amount">Target Contribution (<?= CURRENCY ?>) <span style="color:var(--red)">*</span></label>
                    <input type="number" id="target_amount" name="target_amount" class="form-control"
                           placeholder="e.g. 5000"
                           value="<?= htmlspecialchars($form['target_amount']) ?>"
                           min="1" step="0.01" required>
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="submit" name="add_user" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add User</button>
                    <a href="admin-dashboard.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- SMS info box -->
    <div class="card mt-3" style="border-left:4px solid var(--gold);">
        <div class="card-body" style="padding:1rem 1.2rem;">
            <strong style="color:var(--brown);"><i class="fa-solid fa-mobile-screen"></i> How SMS notifications work</strong>
            <p style="font-size:0.85rem;margin-top:0.4rem;">
                After the admin approves this user's <strong>first payment</strong>, the system will automatically
                send an SMS to their phone number containing the WhatsApp group invite link.
                Subsequent approvals send a balance update SMS.
            </p>
            <p style="font-size:0.82rem;color:var(--text-muted);margin-top:0.3rem;">
                Configure your SMS provider in <code>config.php</code>
                (Africa's Talking, Twilio, or log-only for testing).
            </p>
        </div>
    </div>

</div>

<div class="footer"><i class="fa-solid fa-globe"></i> Safari Portal — Admin</div>

<script>
document.getElementById('work_id').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/\s/g, '');
});
</script>
</body>
</html>
