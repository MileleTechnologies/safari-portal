<?php
// ============================================
// admin/payment-requests.php — Review Payments (v3)
// All approvals go through approvePayment() in config.php
// which handles SMS + WhatsApp invite automatically.
// ============================================
require_once __DIR__ . '/../config.php';
requireAdmin();

$db    = getDB();
$flash = getFlash();

// ---- HANDLE APPROVE / REJECT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['payment_id'])) {
    $payId  = (int)$_POST['payment_id'];
    $action = $_POST['action'];
    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($action === 'approve') {
        // Central approval function handles SMS + WhatsApp invite
        $result = approvePayment($payId);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);

    } elseif ($action === 'reject') {
        if (empty($reason)) {
            setFlash('error', 'Please provide a rejection reason.');
            redirect('payment-requests.php');
        }
        // Load user name for flash message
        $stmt = $db->prepare("SELECT p.*, u.full_name FROM payments p JOIN users u ON u.id=p.user_id WHERE p.id=? AND p.status='pending'");
        $stmt->execute([$payId]);
        $pay = $stmt->fetch();
        if (!$pay) {
            setFlash('error', 'Payment not found or already reviewed.');
            redirect('payment-requests.php');
        }
        $db->prepare("UPDATE payments SET status='rejected', reviewed_at=NOW(), rejection_reason=? WHERE id=?")
           ->execute([$reason, $payId]);
        setFlash('success', '❌ Payment from ' . $pay['full_name'] . ' has been rejected.');
    }

    redirect('payment-requests.php');
}

// ---- FILTER ----
$filterStatus = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filterStatus, $allowed)) $filterStatus = 'pending';

// Counts for tabs
$counts = [];
foreach ($db->query("SELECT status, COUNT(*) AS cnt FROM payments GROUP BY status") as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
}
$countPending  = $counts['pending']  ?? 0;
$countApproved = $counts['approved'] ?? 0;
$countRejected = $counts['rejected'] ?? 0;
$countAll      = array_sum($counts);

// Fetch payments for current filter
$where = $filterStatus !== 'all' ? "WHERE p.status = " . $db->quote($filterStatus) : '';
$payments = $db->query("
    SELECT p.*,
           u.full_name, u.work_id, u.target_amount, u.phone_number, u.whatsapp_invited,
           COALESCE((
               SELECT SUM(p2.amount) FROM payments p2
               WHERE p2.user_id = p.user_id AND p2.status = 'approved'
           ), 0) AS user_approved_total
    FROM payments p
    JOIN users u ON u.id = p.user_id
    $where
    ORDER BY p.submitted_at DESC
")->fetchAll();

$tripName   = getSetting('trip_name') ?: APP_NAME;
$smsEnabled = getSetting('sms_notifications_enabled') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Requests — <?= htmlspecialchars($tripName) ?></title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .tab-bar { display:flex; gap:0.4rem; flex-wrap:wrap; margin-bottom:1.2rem; }
        .tab {
            padding:.45rem 1rem; border-radius:999px; font-size:.85rem; font-weight:600;
            border:1.5px solid var(--border); background:var(--white); color:var(--text-muted);
            text-decoration:none; transition:all .18s; display:inline-flex; align-items:center; gap:.35rem;
        }
        .tab:hover { border-color:var(--gold); color:var(--gold); }
        .tab.active-pending  { background:#fef9e7; border-color:#f1c40f; color:#7d6608; }
        .tab.active-approved { background:#d5f5e3; border-color:#27ae60; color:#1e8449; }
        .tab.active-rejected { background:#fdecea; border-color:#e74c3c; color:#c0392b; }
        .tab.active-all      { background:var(--gold-pale); border-color:var(--gold); color:var(--brown); }
        .cnt { border-radius:999px; padding:0 6px; font-size:.75rem; font-weight:700; }
        .proof-thumb {
            width:44px; height:44px; border-radius:6px; object-fit:cover;
            border:1px solid var(--border); cursor:pointer; transition:transform .2s;
        }
        .proof-thumb:hover { transform:scale(1.1); }
        .action-btns { display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; }
        tr.row-pending { background:#fffdf0; }
        tr.row-rejected { opacity:.75; }
        .sms-chip {
            display:inline-flex; align-items:center; gap:3px;
            font-size:.72rem; padding:2px 7px; border-radius:999px;
            background:#e8f8f5; color:#1a7a5e; font-weight:600; white-space:nowrap;
        }
        .wa-chip {
            display:inline-flex; align-items:center; gap:3px;
            font-size:.72rem; padding:2px 7px; border-radius:999px;
            background:#d5f5e3; color:#1e8449; font-weight:600; white-space:nowrap;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a class="navbar-brand" href="admin-dashboard.php"><span class="icon">🦁</span> Admin Panel</a>
    <div class="navbar-links">
        <a href="admin-dashboard.php">Dashboard</a>
        <a href="add-user.php">Add User</a>
        <a href="payment-requests.php" class="active" style="position:relative;">
            Requests
            <?php if ($countPending > 0): ?>
                <span style="position:absolute;top:-6px;right:-8px;background:#e74c3c;color:#fff;
                             font-size:.65rem;font-weight:700;border-radius:50%;width:17px;height:17px;
                             display:flex;align-items:center;justify-content:center;">
                    <?= $countPending ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="whatsapp-settings.php">WhatsApp</a>
        <a href="admin-logout.php">Logout</a>
    </div>
</nav>

<div class="page-header">
    <div class="subtitle">Admin</div>
    <h1>Payment Requests</h1>
    <p>Approve or reject employee payment submissions</p>
</div>

<div class="container" style="padding-top:1.5rem;padding-bottom:2rem;">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <?= $flash['type'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- SMS status banner -->
    <?php if (!$smsEnabled): ?>
        <div class="alert alert-warning mb-3" style="font-size:.88rem;">
            📵 SMS notifications are <strong>disabled</strong>.
            <a href="whatsapp-settings.php" style="color:var(--brown);text-decoration:underline;">Enable in WhatsApp &amp; SMS Settings →</a>
        </div>
    <?php else: ?>
        <div class="alert alert-info mb-3" style="font-size:.88rem;">
            📱 SMS notifications <strong>active</strong> (provider: <code><?= SMS_PROVIDER ?></code>).
            On first approval the WhatsApp invite is automatically sent by SMS.
        </div>
    <?php endif; ?>

    <!-- TAB BAR -->
    <div class="tab-bar">
        <a href="?status=pending"  class="tab <?= $filterStatus==='pending'  ? 'active-pending'  : '' ?>">
            ⏳ Pending  <span class="cnt" style="background:#f1c40f;color:#7d6608;"><?= $countPending ?></span>
        </a>
        <a href="?status=approved" class="tab <?= $filterStatus==='approved' ? 'active-approved' : '' ?>">
            ✅ Approved <span class="cnt" style="background:#27ae60;color:#fff;"><?= $countApproved ?></span>
        </a>
        <a href="?status=rejected" class="tab <?= $filterStatus==='rejected' ? 'active-rejected' : '' ?>">
            ❌ Rejected <span class="cnt" style="background:#e74c3c;color:#fff;"><?= $countRejected ?></span>
        </a>
        <a href="?status=all"      class="tab <?= $filterStatus==='all'      ? 'active-all'      : '' ?>">
            📋 All      <span class="cnt" style="background:var(--gold);color:#fff;"><?= $countAll ?></span>
        </a>
    </div>

    <!-- TABLE -->
    <div class="card">
        <div class="card-header">
            <h3><?php $labels=['pending'=>'⏳ Pending Review','approved'=>'✅ Approved','rejected'=>'❌ Rejected','all'=>'📋 All Requests']; echo $labels[$filterStatus]; ?></h3>
            <span class="badge badge-partial"><?= count($payments) ?> record(s)</span>
        </div>

        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= $filterStatus==='pending' ? '🎉' : '📋' ?></div>
                <p><?= $filterStatus==='pending' ? 'No pending requests — you\'re all caught up!' : 'No records found.' ?></p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Phone</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Proof</th>
                            <th>Date</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Notifications</th>
                            <?php if (in_array($filterStatus,['pending','all'])): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p):
                            $rowCls  = $p['status']==='pending' ? 'row-pending' : ($p['status']==='rejected' ? 'row-rejected' : '');
                            $badgeCls= $p['status']==='approved' ? 'badge-complete' : ($p['status']==='rejected' ? 'badge-none' : 'badge-pending');
                            $icon    = $p['status']==='approved' ? '✅' : ($p['status']==='rejected' ? '❌' : '⏳');
                            $proofExt= !empty($p['proof_file']) ? strtolower(pathinfo($p['proof_file'],PATHINFO_EXTENSION)) : '';
                            $isImg   = in_array($proofExt,['jpg','jpeg','png','gif']);

                            // Determine if this is (or was) first approval
                            $approvedBefore = (float)$p['user_approved_total'] - ($p['status']==='approved' ? (float)$p['amount'] : 0);
                            $wasFirst = $p['status']==='approved' && $approvedBefore <= 0;
                        ?>
                            <tr class="<?= $rowCls ?>">
                                <td>
                                    <strong><?= htmlspecialchars($p['full_name']) ?></strong>
                                    <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($p['work_id']) ?></div>
                                    <?php if (!empty($p['rejection_reason'])): ?>
                                        <div style="font-size:.73rem;color:var(--red);margin-top:2px;">
                                            ↳ <?= htmlspecialchars($p['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.83rem;">
                                    <?= !empty($p['phone_number']) ? htmlspecialchars($p['phone_number']) : '<span style="color:var(--text-muted);">—</span>' ?>
                                </td>
                                <td><strong style="color:var(--green);"><?= money($p['amount']) ?></strong></td>
                                <td>
                                    <span style="font-family:monospace;font-size:.83rem;
                                                 background:#f4f4f4;padding:2px 6px;border-radius:4px;">
                                        <?= htmlspecialchars($p['reference_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($p['proof_file'])): ?>
                                        <?php if ($isImg): ?>
                                            <img src="../uploads/<?= htmlspecialchars($p['proof_file']) ?>"
                                                 class="proof-thumb"
                                                 onclick="openProof('../uploads/<?= htmlspecialchars($p['proof_file']) ?>')"
                                                 alt="Proof">
                                        <?php else: ?>
                                            <a href="../uploads/<?= htmlspecialchars($p['proof_file']) ?>"
                                               target="_blank" class="btn btn-sm btn-outline">📄 PDF</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap;font-size:.85rem;">
                                    <?= date('d M Y', strtotime($p['payment_date'])) ?>
                                </td>
                                <td style="white-space:nowrap;font-size:.8rem;color:var(--text-muted);">
                                    <?= date('d M, H:i', strtotime($p['submitted_at'])) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeCls ?>">
                                        <?= $icon ?> <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'approved'): ?>
                                        <?php if ($wasFirst && $p['whatsapp_invited']): ?>
                                            <span class="wa-chip">📱 WA Invited</span>
                                        <?php elseif ($wasFirst): ?>
                                            <span class="wa-chip" style="background:#fef9e7;color:#7d6608;">📱 First ✓</span>
                                        <?php else: ?>
                                            <span class="sms-chip">📩 SMS Sent</span>
                                        <?php endif; ?>
                                    <?php elseif ($p['status'] === 'pending'): ?>
                                        <?php if (!empty($p['phone_number'])): ?>
                                            <span style="font-size:.75rem;color:var(--text-muted);">
                                                📱 On approval
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size:.75rem;color:var(--red);">⚠️ No phone</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="font-size:.75rem;color:var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (in_array($filterStatus,['pending','all'])): ?>
                                    <td>
                                        <?php if ($p['status'] === 'pending'): ?>
                                            <div class="action-btns">
                                                <form method="POST"
                                                      onsubmit="return confirm('Approve <?= money($p['amount']) ?> from <?= addslashes($p['full_name']) ?>?\n\n<?= $p['whatsapp_invited'] ? '' : 'This is their FIRST approval — WhatsApp invite will be sent by SMS.' ?>') ">
                                                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-sm btn-success">✅ Approve</button>
                                                </form>
                                                <button onclick="openRejectForm(<?= $p['id'] ?>,'<?= addslashes($p['full_name']) ?>','<?= money($p['amount']) ?>')"
                                                        class="btn btn-sm btn-danger">❌ Reject</button>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-size:.8rem;">Reviewed</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<div class="footer">🌍 <?= htmlspecialchars($tripName) ?> — Admin Panel</div>

<!-- REJECT MODAL -->
<div id="rejectModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="flex-between mb-2">
            <h3>❌ Reject Payment</h3>
            <button onclick="closeRejectModal()"
                    style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);">✕</button>
        </div>
        <div id="rejectInfo" style="background:#fdecea;padding:.75rem 1rem;border-radius:8px;
                                     margin-bottom:1.2rem;font-size:.9rem;color:#c0392b;"></div>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="payment_id" id="rejectPaymentId">
            <input type="hidden" name="action" value="reject">
            <div class="form-group">
                <label>Reason for Rejection <span style="color:var(--red)">*</span></label>
                <textarea name="rejection_reason" id="rejectReason" class="form-control"
                          style="min-height:80px;"
                          placeholder="e.g. Reference number not found, unclear screenshot, wrong amount..."
                          required></textarea>
                <small style="color:var(--text-muted);">This message is shown to the employee.</small>
            </div>
            <div class="flex gap-2 mt-2">
                <button type="submit" class="btn btn-danger">❌ Confirm Rejection</button>
                <button type="button" onclick="closeRejectModal()" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- PROOF IMAGE LIGHTBOX -->
<div id="proofModal" class="modal-overlay" style="display:none;" onclick="this.style.display='none'">
    <div style="position:relative;max-width:90vw;max-height:90vh;" onclick="event.stopPropagation()">
        <img id="proofImg" src="" alt="Payment Proof"
             style="max-width:90vw;max-height:85vh;border-radius:10px;box-shadow:0 8px 40px rgba(0,0,0,.5);">
        <button onclick="document.getElementById('proofModal').style.display='none'"
                style="position:absolute;top:-12px;right:-12px;background:var(--red);color:#fff;
                       border:none;border-radius:50%;width:30px;height:30px;font-size:1rem;
                       cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
    </div>
</div>

<script>
function openRejectForm(payId, name, amount) {
    document.getElementById('rejectPaymentId').value = payId;
    document.getElementById('rejectInfo').innerHTML =
        'Rejecting <strong>' + amount + '</strong> from <strong>' + name + '</strong>';
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').style.display = 'flex';
    setTimeout(() => document.getElementById('rejectReason').focus(), 80);
}
function closeRejectModal() { document.getElementById('rejectModal').style.display = 'none'; }
document.getElementById('rejectModal').addEventListener('click', function(e) { if(e.target===this) closeRejectModal(); });
function openProof(src) {
    document.getElementById('proofImg').src = src;
    document.getElementById('proofModal').style.display = 'flex';
}
</script>
</body>
</html>
