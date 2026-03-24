<?php
// ============================================
// admin/edit-user.php — Edit User & Reset Password
// ============================================
require_once __DIR__ . '/../config.php';
requireAdmin();

$db     = getDB();
$flash  = getFlash();
$errors = [];

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    setFlash('error', 'Invalid user ID.');
    redirect('admin-dashboard.php');
}

// Load user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    setFlash('error', 'User not found.');
    redirect('admin-dashboard.php');
}

$form = [
    'full_name'     => $user['full_name'],
    'work_id'       => $user['work_id'],
    'phone_number'  => $user['phone_number'],
    'target_amount' => $user['target_amount'],
    'password'      => ''  // Empty by default - only update if provided
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $form['full_name']     = trim($_POST['full_name']     ?? '');
    $form['work_id']       = strtoupper(trim($_POST['work_id'] ?? ''));
    $form['phone_number']  = trim($_POST['phone_number']  ?? '');
    $form['target_amount'] = trim($_POST['target_amount'] ?? '');
    $form['password']      = trim($_POST['password']      ?? '');

    // Validation
    if (empty($form['full_name']))    $errors[] = 'Full name is required.';
    if (empty($form['work_id']))      $errors[] = 'Work ID is required.';
    if (empty($form['phone_number'])) $errors[] = 'Phone number is required.';
    if (!is_numeric($form['target_amount']) || (float)$form['target_amount'] <= 0)
        $errors[] = 'Target amount must be a positive number.';

    // Phone format validation
    if (!empty($form['phone_number']) && !preg_match('/^[\+0-9][0-9\s\-]{6,18}$/', $form['phone_number'])) {
        $errors[] = 'Enter a valid phone number (e.g. 0712345678 or +254712345678).';
    }

    // Password validation (only if provided)
    if (!empty($form['password']) && strlen($form['password']) < 4) {
        $errors[] = 'Password must be at least 4 characters.';
    }

    if (empty($errors)) {
        // Check if work_id is being changed and already exists
        if ($form['work_id'] !== $user['work_id']) {
            $stmt = $db->prepare("SELECT id FROM users WHERE work_id = ? AND id != ?");
            $stmt->execute([$form['work_id'], $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'Work ID "' . htmlspecialchars($form['work_id']) . '" already exists.';
            }
        }

        if (empty($errors)) {
            // Build update query
            if (!empty($form['password'])) {
                // Update including password
                $stmt = $db->prepare("UPDATE users SET full_name=?, work_id=?, phone_number=?, target_amount=?, password=? WHERE id=?");
                $stmt->execute([
                    $form['full_name'],
                    $form['work_id'],
                    $form['phone_number'],
                    (float)$form['target_amount'],
                    $form['password'],
                    $userId
                ]);
                setFlash('success', 'User "' . $form['full_name'] . '" updated successfully with new password!');
            } else {
                // Update without changing password
                $stmt = $db->prepare("UPDATE users SET full_name=?, work_id=?, phone_number=?, target_amount=? WHERE id=?");
                $stmt->execute([
                    $form['full_name'],
                    $form['work_id'],
                    $form['phone_number'],
                    (float)$form['target_amount'],
                    $userId
                ]);
                setFlash('success', 'User "' . $form['full_name'] . '" updated successfully!');
            }
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
    <title>Edit User — <?= htmlspecialchars($tripName) ?></title>
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
        <a href="add-user.php">Add User</a>
        <a href="payment-requests.php">Requests</a>
        <a href="admin-logout.php">Logout</a>
    </div>
</nav>

<div class="page-header">
    <div class="subtitle">Admin</div>
    <h1>Edit User</h1>
    <p>Update employee details or reset password</p>
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
        <div class="card-header"><h3><i class="fa-solid fa-user-pen"></i> User Details</h3></div>
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
                    <label for="target_amount">Target Contribution (<?= CURRENCY ?>) <span style="color:var(--red)">*</span></label>
                    <input type="number" id="target_amount" name="target_amount" class="form-control"
                           placeholder="e.g. 5000"
                           value="<?= htmlspecialchars($form['target_amount']) ?>"
                           min="1" step="0.01" required>
                </div>

                <div style="background:var(--gold-pale);border-radius:8px;padding:1rem;margin:1.5rem 0;">
                    <h4 style="margin:0 0 0.5rem 0;color:var(--brown);"><i class="fa-solid fa-key"></i> Password Reset (Optional)</h4>
                    <p style="font-size:0.85rem;color:var(--text-muted);margin:0 0 1rem 0;">
                        Leave blank to keep the current password. Enter a new password to reset it.
                    </p>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Enter new password (min 4 chars) or leave blank">
                        <small style="color:var(--text-muted);">
                            Minimum 4 characters. User will need to login with this new password.
                        </small>
                    </div>
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="submit" name="update_user" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    <a href="admin-dashboard.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- User Stats Card -->
    <div class="card mt-3">
        <div class="card-header"><h3><i class="fa-solid fa-chart-simple"></i> User Statistics</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;">
                <div style="text-align:center;padding:1rem;background:var(--bg-warm);border-radius:8px;">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--brown);"><?= htmlspecialchars($user['work_id']) ?></div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Work ID</div>
                </div>
                <div style="text-align:center;padding:1rem;background:var(--bg-warm);border-radius:8px;">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--green);">
                        <?= $user['whatsapp_invited'] ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-regular fa-clock"></i>' ?>
                    </div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">WhatsApp Invite</div>
                </div>
                <div style="text-align:center;padding:1rem;background:var(--bg-warm);border-radius:8px;">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--brown);">
                        <a href="user-payments.php?user_id=<?= $userId ?>" class="btn btn-sm btn-outline">View</a>
                    </div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Payment History</div>
                </div>
            </div>
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
