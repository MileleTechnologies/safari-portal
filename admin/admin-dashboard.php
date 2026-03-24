<?php
// admin/admin-dashboard.php — Admin Dashboard (v2: Approval Workflow)
require_once __DIR__ . '/../config.php';
requireAdmin();

$db    = getDB();
$flash = getFlash();

// Handle approve / reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['approve_payment'])) {
        $pid = (int)($_POST['payment_id'] ?? 0);
        if ($pid > 0) {
            $db->prepare("UPDATE payments SET status='approved', reviewed_at=NOW() WHERE id=? AND status='pending'")
               ->execute([$pid]);
            setFlash('success', 'Payment approved successfully.');
        }
        redirect('admin-dashboard.php#requests');
    }

    if (isset($_POST['reject_payment'])) {
        $pid  = (int)($_POST['payment_id'] ?? 0);
        $note = trim($_POST['rejection_reason'] ?? '');
        if ($pid > 0) {
            $db->prepare("UPDATE payments SET status='rejected', rejection_reason=?, reviewed_at=NOW() WHERE id=? AND status='pending'")
               ->execute([$note ?: null, $pid]);
            setFlash('success', 'Payment rejected.');
        }
        redirect('admin-dashboard.php#requests');
    }

    if (isset($_POST['save_settings'])) {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value)
                              VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['whatsapp_group_link', trim($_POST['whatsapp_link'] ?? '')]);
        $stmt->execute(['trip_name',           trim($_POST['trip_name']    ?? '')]);
        setFlash('success', 'Settings saved.');
        redirect('admin-dashboard.php');
    }
}

// Users with APPROVED totals only
$users = $db->query("
    SELECT u.*,
           COALESCE(SUM(CASE WHEN p.status='approved' THEN p.amount ELSE 0 END), 0) AS total_paid,
           COALESCE(SUM(CASE WHEN p.status='pending'  THEN p.amount ELSE 0 END), 0) AS total_pending,
           COUNT(CASE WHEN p.status='pending'  THEN 1 END) AS pending_count,
           COUNT(CASE WHEN p.status='approved' THEN 1 END) AS approved_count
    FROM users u
    LEFT JOIN payments p ON p.user_id = u.id
    GROUP BY u.id
    ORDER BY u.full_name
")->fetchAll();

// Pending payment requests (for review table)
$pendingPayments = $db->query("
    SELECT p.*, u.full_name, u.work_id
    FROM payments p
    JOIN users u ON u.id = p.user_id
    WHERE p.status = 'pending'
    ORDER BY p.created_at ASC
")->fetchAll();

$totalUsers     = count($users);
$totalCollected = array_sum(array_column($users, 'total_paid'));
$totalTarget    = array_sum(array_column($users, 'target_amount'));
$completed      = count(array_filter($users, fn($u) => $u['total_paid'] >= $u['target_amount']));
$pendingCount   = count($pendingPayments);
$whatsapp       = getSetting('whatsapp_group_link');
$tripName       = getSetting('trip_name') ?: APP_NAME;
$overallPct     = $totalTarget > 0 ? min(100, round(($totalCollected / $totalTarget) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<nav class="navbar">
    <a class="navbar-brand" href="admin-dashboard.php"><span class="icon">&#x1F981;</span> Admin Panel</a>
    <div class="navbar-links">
        <a href="admin-dashboard.php" class="active">Dashboard</a>
        <a href="add-user.php">Add User</a>
        <a href="record-payment.php">Add Payment</a>
        <a href="admin-logout.php">Logout</a>
    </div>
</nav>

<div class="page-header">
    <div class="subtitle">Admin Panel</div>
    <h1><?= htmlspecialchars($tripName) ?></h1>
    <p>Manage contributions and approve payment requests</p>
</div>

<div class="container" style="padding-top:1.5rem;padding-bottom:2rem;">

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>">
    <?= $flash['type']==='success'?'&#x2705;':'&#x26A0;&#xFE0F;' ?> <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon">&#x1F465;</div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#x1F4B0;</div>
        <div class="stat-value"><?= money($totalCollected) ?></div>
        <div class="stat-label">Collected</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#x2705;</div>
        <div class="stat-value"><?= $completed ?></div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card" style="position:relative;">
        <div class="stat-icon">&#x23F3;</div>
        <div class="stat-value" style="color:<?= $pendingCount>0?'#856404':'var(--brown)' ?>"><?= $pendingCount ?></div>
        <div class="stat-label">Pending Review</div>
        <?php if ($pendingCount > 0): ?>
            <span style="position:absolute;top:8px;right:8px;background:#ffc107;color:#fff;font-size:0.7rem;font-weight:700;padding:0.1rem 0.45rem;border-radius:20px;">NEW</span>
        <?php endif; ?>
    </div>
</div>

<!-- OVERALL PROGRESS -->
<div class="card mb-3">
    <div class="card-body">
        <div class="flex-between mb-2">
            <strong>Overall Fund Progress (Approved Only)</strong>
            <span style="color:var(--gold);font-weight:700;"><?= $overallPct ?>%</span>
        </div>
        <div class="progress-wrap">
            <div class="progress-bar <?= $overallPct>=100?'full':'' ?>" style="width:<?= $overallPct ?>%"></div>
        </div>
        <div class="progress-label">
            <span>Collected: <?= money($totalCollected) ?></span>
            <span>Target: <?= money($totalTarget) ?></span>
        </div>
    </div>
</div>

<div class="flex gap-2 wrap mb-3">
    <a href="add-user.php" class="btn btn-primary">&#x2795; Add User</a>
    <a href="record-payment.php" class="btn btn-success">&#x1F4B3; Direct Payment</a>
</div>

<!-- ==============================
     PAYMENT REQUESTS SECTION
     ============================== -->
<div class="card mb-3" id="requests">
    <div class="card-header">
        <h3>&#x23F3; Payment Requests</h3>
        <?php if ($pendingCount > 0): ?>
            <span class="badge" style="background:#ffc107;color:#7d6608;"><?= $pendingCount ?> pending</span>
        <?php else: ?>
            <span class="badge badge-complete">All reviewed</span>
        <?php endif; ?>
    </div>

    <?php if (empty($pendingPayments)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#x2705;</div>
            <p>No pending payment requests. All caught up!</p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Work ID</th>
                    <th>Amount</th>
                    <th>Reference</th>
                    <th>Proof</th>
                    <th>Date</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingPayments as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($p['work_id']) ?></code></td>
                    <td><strong style="color:var(--brown)"><?= money($p['amount']) ?></strong></td>
                    <td>
                        <span style="font-family:monospace;font-size:0.85rem;background:var(--gold-pale);padding:0.1rem 0.5rem;border-radius:4px;">
                            <?= htmlspecialchars($p['reference_number'] ?? '—') ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($p['proof_file'])): ?>
                            <?php
                                $ext  = strtolower(pathinfo($p['proof_file'], PATHINFO_EXTENSION));
                                $fUrl = '../uploads/' . htmlspecialchars(basename($p['proof_file']));
                            ?>
                            <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                                <img src="<?= $fUrl ?>" class="proof-thumb"
                                     onclick="openLightbox('<?= $fUrl ?>')" alt="Proof"
                                     title="Click to enlarge">
                            <?php else: ?>
                                <a href="<?= $fUrl ?>" target="_blank" class="proof-pdf-icon" title="Open PDF">&#x1F4C4;</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:0.82rem;">None</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;font-size:0.85rem;">
                        <?= date('d M Y', strtotime($p['payment_date'])) ?>
                    </td>
                    <td style="white-space:nowrap;font-size:0.82rem;color:var(--text-muted);">
                        <?= date('d M, H:i', strtotime($p['created_at'])) ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <!-- APPROVE -->
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Approve this payment of <?= money($p['amount']) ?> for <?= htmlspecialchars($p['full_name']) ?>?')">
                            <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                            <button type="submit" name="approve_payment" class="btn btn-sm btn-approve">
                                &#x2705; Approve
                            </button>
                        </form>
                        <!-- REJECT -->
                        <button type="button" class="btn btn-sm btn-reject"
                                style="margin-left:0.3rem;"
                                onclick="openRejectModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['full_name']) ?>', '<?= money($p['amount']) ?>')">
                            &#x274C; Reject
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- USERS TABLE -->
<div class="card mb-3">
    <div class="card-header">
        <h3>&#x1F465; All Users</h3>
        <span class="badge badge-partial"><?= $totalUsers ?> users</span>
    </div>
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#x1F464;</div>
            <p>No users yet. <a href="add-user.php">Add the first user</a>.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Work ID</th>
                    <th>Target</th>
                    <th>Approved</th>
                    <th>Pending</th>
                    <th>Remaining</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $paid    = (float)$u['total_paid'];
                $pending = (float)$u['total_pending'];
                $tgt     = (float)$u['target_amount'];
                $rem     = max(0, $tgt - $paid);
                $pct     = $tgt > 0 ? min(100, round(($paid / $tgt) * 100)) : 0;
                $status  = $pct >= 100 ? 'complete' : ($pct > 0 ? 'partial' : 'none');
                $sLabel  = $pct >= 100 ? '&#x2705; Complete' : ($pct > 0 ? '&#x23F3; Partial' : '&#x274C; None');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                <td><code><?= htmlspecialchars($u['work_id']) ?></code></td>
                <td><?= money($tgt) ?></td>
                <td style="color:var(--green);font-weight:600;"><?= money($paid) ?></td>
                <td>
                    <?php if ($pending > 0): ?>
                        <span style="color:#856404;font-size:0.85rem;">&#x23F3; <?= money($pending) ?></span>
                    <?php else: ?>
                        <span style="color:var(--text-muted);font-size:0.82rem;">—</span>
                    <?php endif; ?>
                </td>
                <td><?= money($rem) ?></td>
                <td style="min-width:100px;">
                    <div class="progress-wrap" style="height:8px;">
                        <div class="progress-bar <?= $status==='complete'?'full':'' ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;"><?= $pct ?>%</div>
                </td>
                <td><span class="badge badge-<?= $status ?>"><?= $sLabel ?></span></td>
                <td>
                    <a href="record-payment.php?user_id=<?= $u['id'] ?>" class="btn btn-sm btn-primary">Pay</a>
                    <a href="user-payments.php?user_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline" style="margin-left:0.3rem;">History</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- SETTINGS -->
<div class="card">
    <div class="card-header"><h3>&#x2699;&#xFE0F; Settings</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>Trip / Fund Name</label>
                <input type="text" name="trip_name" class="form-control"
                       value="<?= htmlspecialchars($tripName) ?>" placeholder="e.g. Safari Adventure 2025">
            </div>
            <div class="form-group">
                <label>WhatsApp Group Invite Link</label>
                <input type="url" name="whatsapp_link" class="form-control"
                       value="<?= htmlspecialchars($whatsapp) ?>" placeholder="https://chat.whatsapp.com/xxxxx">
                <small style="color:var(--text-muted);">Unlocks for users after their first approved payment.</small>
            </div>
            <button type="submit" name="save_settings" class="btn btn-primary">&#x1F4BE; Save Settings</button>
        </form>
    </div>
</div>

</div>

<!-- REJECT MODAL -->
<div id="rejectModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width:420px;">
        <div class="flex-between mb-2">
            <h3>&#x274C; Reject Payment</h3>
            <button onclick="document.getElementById('rejectModal').style.display='none'"
                    style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);">&#x2715;</button>
        </div>
        <p id="rejectDesc" style="margin-bottom:1.2rem;"></p>
        <form method="POST">
            <input type="hidden" name="payment_id" id="rejectPaymentId">
            <div class="form-group">
                <label>Reason for Rejection <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                <input type="text" name="rejection_reason" id="rejectNote" class="form-control"
                       placeholder="e.g. Reference number not found, Please resubmit">
            </div>
            <div class="flex gap-2 mt-2">
                <button type="submit" name="reject_payment" class="btn btn-reject">
                    &#x274C; Confirm Rejection
                </button>
                <button type="button" onclick="document.getElementById('rejectModal').style.display='none'"
                        class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- LIGHTBOX -->
<div id="lightbox" class="lightbox-overlay" style="display:none;" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">&#x2715;</button>
    <img id="lightboxImg" src="" alt="Payment Proof">
</div>

<div class="footer">
    &#x1F30D; <?= htmlspecialchars($tripName) ?> &nbsp;|&nbsp; Admin Panel &nbsp;|&nbsp;
    <a href="admin-logout.php">Logout</a>
</div>

<script>
function openRejectModal(id, name, amount) {
    document.getElementById('rejectPaymentId').value = id;
    document.getElementById('rejectDesc').textContent = 'Rejecting payment of ' + amount + ' for ' + name + '.';
    document.getElementById('rejectNote').value = '';
    document.getElementById('rejectModal').style.display = 'flex';
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeLightbox(); document.getElementById('rejectModal').style.display='none'; } });
</script>
</body>
</html>
