<?php
// ============================================
// admin/record-payment.php — Admin Direct Payment (v3)
// Uses central approvePayment() — triggers SMS too
// ============================================
require_once __DIR__ . '/../config.php';
requireAdmin();

$db    = getDB();
$flash = getFlash();

$users = $db->query("
    SELECT u.*,
           COALESCE(SUM(CASE WHEN p.status='approved' THEN p.amount ELSE 0 END), 0) AS total_paid
    FROM users u
    LEFT JOIN payments p ON p.user_id = u.id
    GROUP BY u.id
    ORDER BY u.full_name
")->fetchAll();

$errors = [];
$form   = ['user_id'=>0,'amount'=>'','reference_number'=>'','payment_date'=>date('Y-m-d')];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    $form['user_id']          = (int)($_POST['user_id']          ?? 0);
    $form['amount']           = trim($_POST['amount']            ?? '');
    $form['reference_number'] = trim($_POST['reference_number']  ?? '');
    $form['payment_date']     = trim($_POST['payment_date']      ?? date('Y-m-d'));

    if ($form['user_id'] <= 0)                                       $errors[] = 'Please select a user.';
    if (!is_numeric($form['amount']) || (float)$form['amount'] <= 0) $errors[] = 'Enter a valid amount.';
    if (empty($form['reference_number']))                             $errors[] = 'Reference number is required.';

    if (empty($errors)) {
        // Insert as pending first, then approve through central function (so SMS fires)
        $stmt = $db->prepare("
            INSERT INTO payments (user_id, amount, reference_number, proof_file, status, payment_date)
            VALUES (?, ?, ?, NULL, 'pending', ?)
        ");
        $stmt->execute([$form['user_id'], (float)$form['amount'], $form['reference_number'], $form['payment_date']]);
        $newPayId = (int)$db->lastInsertId();

        // Central approval — sends SMS + WA invite if first payment
        $result = approvePayment($newPayId);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('admin-dashboard.php');
    }
}

$tripName = getSetting('trip_name') ?: APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment — Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<nav class="navbar">
    <a class="navbar-brand" href="admin-dashboard.php"><span class="icon"><i class="fa-solid fa-paw"></i></span> Admin Panel</a>
    <div class="navbar-links">
        <a href="admin-dashboard.php">Dashboard</a>
        <a href="payment-requests.php">Requests</a>
        <a href="record-payment.php" class="active">Direct Pay</a>
        <a href="whatsapp-settings.php">WhatsApp</a>
        <a href="admin-logout.php">Logout</a>
    </div>
</nav>

<div class="page-header">
    <div class="subtitle">Admin — Direct Payment</div>
    <h1>Record Payment</h1>
    <p>Instantly approved — SMS notification sent automatically.</p>
</div>

<div class="container-sm" style="padding-top:2rem;padding-bottom:2rem;">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <?= $flash['type']==='success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?> <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info mb-3">
        <i class="fa-solid fa-circle-info"></i> Payments recorded here are <strong>approved immediately</strong> and trigger SMS notifications
        (including the WhatsApp invite on first payment). For employee self-service, use the User Portal.
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode(' ', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3><i class="fa-solid fa-credit-card"></i> Payment Details</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Select Employee <span style="color:var(--red)">*</span></label>
                    <select name="user_id" class="form-control" required onchange="updateInfo(this)">
                        <option value="">— Choose employee —</option>
                        <?php foreach ($users as $u):
                            $rem = max(0,(float)$u['target_amount']-(float)$u['total_paid']);
                        ?>
                            <option value="<?= $u['id'] ?>"
                                data-target="<?= $u['target_amount'] ?>"
                                data-paid="<?= $u['total_paid'] ?>"
                                data-remaining="<?= $rem ?>"
                                data-name="<?= htmlspecialchars($u['full_name']) ?>"
                                data-phone="<?= htmlspecialchars($u['phone_number'] ?? '') ?>"
                                <?= $form['user_id']==$u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['work_id']) ?>) — Remaining: <?= money($rem) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="userInfo" style="display:none;background:var(--gold-pale);border-radius:8px;
                     padding:.75rem 1rem;margin-bottom:1rem;font-size:.88rem;">
                    <strong id="uiName">—</strong>
                    <span id="uiPhone" style="color:var(--text-muted);font-size:.82rem;margin-left:.5rem;"></span>
                    <div style="color:var(--text-muted);margin-top:3px;">
                        Approved: <span id="uiPaid">—</span> &nbsp;|&nbsp;
                        Remaining: <span id="uiRemaining">—</span>
                    </div>
                    <div class="progress-wrap" style="margin-top:.5rem;height:6px;">
                        <div class="progress-bar" id="uiBar" style="width:0%"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Amount (<?= CURRENCY ?>) <span style="color:var(--red)">*</span></label>
                    <input type="number" name="amount" class="form-control"
                           placeholder="e.g. 1000"
                           value="<?= htmlspecialchars($form['amount']) ?>"
                           min="1" step="0.01" required>
                </div>

                <div class="form-group">
                    <label>Reference Number <span style="color:var(--red)">*</span></label>
                    <input type="text" name="reference_number" class="form-control"
                           placeholder="e.g. CASH-001"
                           value="<?= htmlspecialchars($form['reference_number']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" class="form-control"
                           value="<?= htmlspecialchars($form['payment_date']) ?>" required>
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="submit" name="save_payment" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save &amp; Approve</button>
                    <a href="admin-dashboard.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="footer"><i class="fa-solid fa-globe"></i> Safari Portal — Admin</div>

<script>
const currency = '<?= CURRENCY ?>';
function fmt(v){return currency+' '+parseFloat(v).toLocaleString('en-US',{minimumFractionDigits:2});}
function updateInfo(sel){
    const opt=sel.options[sel.selectedIndex],box=document.getElementById('userInfo');
    if(!opt.value){box.style.display='none';return;}
    const paid=parseFloat(opt.dataset.paid),target=parseFloat(opt.dataset.target);
    const pct=target>0?Math.min(100,Math.round((paid/target)*100)):0;
    document.getElementById('uiName').textContent=opt.dataset.name;
    document.getElementById('uiPhone').textContent=opt.dataset.phone ? 'Phone: '+opt.dataset.phone : '(no phone)';
    document.getElementById('uiPaid').textContent=fmt(paid);
    document.getElementById('uiRemaining').textContent=fmt(parseFloat(opt.dataset.remaining));
    document.getElementById('uiBar').style.width=pct+'%';
    box.style.display='block';
}
window.addEventListener('DOMContentLoaded',function(){
    const sel=document.querySelector('select[name=user_id]');
    if(sel&&sel.value) updateInfo(sel);
});
</script>
</body>
</html>
