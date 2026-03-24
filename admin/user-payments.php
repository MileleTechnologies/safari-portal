<?php
// ============================================
// admin/user-payments.php — User Payment History (v3)
// All approvals routed through central approvePayment()
// ============================================
require_once __DIR__ . '/../config.php';
requireAdmin();

$db     = getDB();
$flash  = getFlash();
$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) { redirect('admin-dashboard.php'); }

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) { setFlash('error', 'User not found.'); redirect('admin-dashboard.php'); }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['payment_id'])) {
    $payId  = (int)$_POST['payment_id'];
    $action = $_POST['action'];
    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($action === 'approve') {
        // Central approval: handles SMS + WhatsApp invite automatically
        $result = approvePayment($payId);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);

    } elseif ($action === 'reject' && $reason) {
        $db->prepare("UPDATE payments SET status='rejected', reviewed_at=NOW(), rejection_reason=? WHERE id=? AND user_id=? AND status='pending'")
           ->execute([$reason, $payId, $userId]);
        setFlash('success', 'Payment rejected.');

    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM payments WHERE id=? AND user_id=?")->execute([$payId, $userId]);
        setFlash('success', 'Payment deleted.');
    }
    redirect('user-payments.php?user_id=' . $userId);
}

// Load payments
$stmt = $db->prepare("SELECT * FROM payments WHERE user_id=? ORDER BY submitted_at DESC");
$stmt->execute([$userId]);
$payments = $stmt->fetchAll();

$approved  = array_sum(array_map(fn($p) => $p['status']==='approved' ? (float)$p['amount'] : 0, $payments));
$pending   = array_sum(array_map(fn($p) => $p['status']==='pending'  ? (float)$p['amount'] : 0, $payments));
$target    = (float)$user['target_amount'];
$remaining = max(0, $target - $approved);
$percent   = $target > 0 ? min(100, round(($approved / $target) * 100)) : 0;
$tripName  = getSetting('trip_name') ?: APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['full_name']) ?> — Payments</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<nav class="navbar">
    <a class="navbar-brand" href="admin-dashboard.php"><span class="icon">🦁</span> Admin Panel</a>
    <div class="navbar-links">
        <a href="admin-dashboard.php">Dashboard</a>
        <a href="payment-requests.php">Requests</a>
        <a href="whatsapp-settings.php">WhatsApp</a>
        <a href="admin-logout.php">Logout</a>
    </div>
</nav>

<div class="page-header">
    <div class="subtitle">Payment History</div>
    <h1><?= htmlspecialchars($user['full_name']) ?></h1>
    <p>
        Work ID: <?= htmlspecialchars($user['work_id']) ?>
        <?php if (!empty($user['phone_number'])): ?>
            &nbsp;|&nbsp; 📱 <?= htmlspecialchars($user['phone_number']) ?>
        <?php endif; ?>
        <?php if ($user['whatsapp_invited']): ?>
            &nbsp;|&nbsp; <span style="color:#25D366;font-weight:600;">📱 WA Invited</span>
        <?php endif; ?>
    </p>
</div>

<div class="container" style="padding-top:1.5rem;padding-bottom:2rem;">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <?= $flash['type']==='success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-value"><?= money($target) ?></div>
            <div class="stat-label">Target</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= money($approved) ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?= money($pending) ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💳</div>
            <div class="stat-value"><?= money($remaining) ?></div>
            <div class="stat-label">Remaining</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="progress-wrap">
                <div class="progress-bar <?= $percent>=100?'full':'' ?>" style="width:<?= $percent ?>%"></div>
            </div>
            <div class="progress-label">
                <span><?= money($approved) ?> approved</span>
                <span><?= $percent ?>% of <?= money($target) ?></span>
            </div>
        </div>
    </div>

    <div class="flex gap-2 wrap mb-3">
        <a href="payment-requests.php" class="btn btn-primary">🔍 All Requests</a>
        <a href="admin-dashboard.php" class="btn btn-outline">← Dashboard</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🧾 Payment Records</h3>
            <span class="badge badge-partial"><?= count($payments) ?> total</span>
        </div>
        <?php if (empty($payments)): ?>
            <div class="empty-state"><div class="empty-icon">💳</div><p>No payments yet.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Date</th><th>Amount</th>
                            <th>Reference</th><th>Proof</th>
                            <th>Status</th><th>Reviewed</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i=1; foreach ($payments as $p):
                        $bc  = $p['status']==='approved' ? 'badge-complete' : ($p['status']==='rejected' ? 'badge-none' : 'badge-pending');
                        $ext = !empty($p['proof_file']) ? strtolower(pathinfo($p['proof_file'],PATHINFO_EXTENSION)) : '';
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td style="white-space:nowrap;"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                            <td><strong style="color:var(--green);"><?= money($p['amount']) ?></strong></td>
                            <td>
                                <span style="font-family:monospace;font-size:.82rem;
                                             background:#f4f4f4;padding:2px 6px;border-radius:4px;">
                                    <?= htmlspecialchars($p['reference_number']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($p['proof_file'])): ?>
                                    <?php if (in_array($ext,['jpg','jpeg','png','gif'])): ?>
                                        <a href="../uploads/<?= htmlspecialchars($p['proof_file']) ?>" target="_blank">
                                            <img src="../uploads/<?= htmlspecialchars($p['proof_file']) ?>"
                                                 style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid var(--border);">
                                        </a>
                                    <?php else: ?>
                                        <a href="../uploads/<?= htmlspecialchars($p['proof_file']) ?>"
                                           target="_blank" class="btn btn-sm btn-outline">📄 PDF</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $bc ?>"><?= ucfirst($p['status']) ?></span>
                                <?php if ($p['status']==='rejected' && !empty($p['rejection_reason'])): ?>
                                    <div style="font-size:.72rem;color:var(--red);margin-top:2px;">
                                        <?= htmlspecialchars($p['rejection_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
                                <?= $p['reviewed_at'] ? date('d M, H:i', strtotime($p['reviewed_at'])) : '—' ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                                    <?php if ($p['status']==='pending'): ?>
                                        <form method="POST" onsubmit="return confirm('Approve this payment? SMS will be sent.')">
                                            <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-sm btn-success">✅</button>
                                        </form>
                                        <button onclick="openReject(<?= $p['id'] ?>)" class="btn btn-sm btn-danger">❌</button>
                                    <?php endif; ?>
                                    <form method="POST" onsubmit="return confirm('Delete this record permanently?')">
                                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button class="btn btn-sm btn-outline">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">🌍 Safari Portal — Admin</div>

<div id="rejectModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="flex-between mb-2">
            <h3>❌ Reject Payment</h3>
            <button onclick="document.getElementById('rejectModal').style.display='none'"
                    style="background:none;border:none;font-size:1.4rem;cursor:pointer;">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="payment_id" id="rPayId">
            <input type="hidden" name="action" value="reject">
            <div class="form-group">
                <label>Rejection Reason <span style="color:var(--red)">*</span></label>
                <textarea name="rejection_reason" class="form-control"
                          style="min-height:70px;" placeholder="Reason..." required></textarea>
                <small style="color:var(--text-muted);">Shown to the employee on their dashboard.</small>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-danger">Confirm Reject</button>
                <button type="button" onclick="document.getElementById('rejectModal').style.display='none'"
                        class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
function openReject(id){
    document.getElementById('rPayId').value=id;
    document.getElementById('rejectModal').style.display='flex';
}
document.getElementById('rejectModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});
</script>
</body>
</html>
