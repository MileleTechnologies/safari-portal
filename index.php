<?php
// index.php — User Portal (v2: Approval Workflow)
require_once __DIR__ . '/config.php';

$error = '';
$user  = null;
$payments = [];
$totalApproved = 0;
$totalPending  = 0;

$workIdInput = trim(strtoupper($_POST['work_id'] ?? $_GET['work_id'] ?? ''));

if ($workIdInput) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE work_id = ?");
    $stmt->execute([$workIdInput]);
    $user = $stmt->fetch();
    if (!$user) {
        $error = "Work ID not found. Please check and try again.";
    } else {
        $stmt2 = $db->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC");
        $stmt2->execute([$user['id']]);
        $payments = $stmt2->fetchAll();
        foreach ($payments as $p) {
            if ($p['status'] === 'approved') $totalApproved += (float)$p['amount'];
            if ($p['status'] === 'pending')  $totalPending  += (float)$p['amount'];
        }
    }
}

$flash    = getFlash();
$whatsapp = getSetting('whatsapp_group_link');
$tripName = getSetting('trip_name') ?: APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tripName) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <a class="navbar-brand" href="index.php"><span class="icon">&#x1F981;</span> Safari Portal</a>
    <div class="navbar-links">
        <a href="index.php" class="active">My Dashboard</a>
        <a href="admin/admin-login.php">Admin</a>
    </div>
</nav>
<div class="page-header">
    <div class="subtitle">Employee Portal</div>
    <h1><?= htmlspecialchars($tripName) ?></h1>
    <p>Enter your Work ID to view your contribution status</p>
</div>
<div class="container" style="padding-top:2rem;padding-bottom:2rem;">
<div class="card mb-3">
    <div class="card-header"><h3>&#x1F50D; Fetch My Dashboard</h3></div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="form-group">
                <label for="work_id">Your Work ID</label>
                <div class="fetch-bar">
                    <input type="text" id="work_id" name="work_id" class="form-control"
                           placeholder="e.g. EMP001"
                           value="<?= htmlspecialchars($workIdInput) ?>"
                           autocomplete="off" autofocus required>
                    <button type="submit" class="btn btn-primary">Fetch &#x279C;</button>
                </div>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error">&#x26A0;&#xFE0F; <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>">
    <?= $flash['type'] === 'success' ? '&#x2705;' : '&#x26A0;&#xFE0F;' ?>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<?php if ($user):
    $target       = (float)$user['target_amount'];
    $remaining    = max(0, $target - $totalApproved);
    $percent      = $target > 0 ? min(100, round(($totalApproved / $target) * 100)) : 0;
    $isComplete   = $totalApproved >= $target;
    $hasApproved  = $totalApproved > 0;
    $pendingCount = count(array_filter($payments, fn($p) => $p['status'] === 'pending'));
?>

<div class="user-info-card">
    <div class="user-avatar">&#x1F464;</div>
    <div>
        <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
        <span class="user-id">ID: <?= htmlspecialchars($user['work_id']) ?></span>
    </div>
    <?php if ($isComplete): ?>
        <span class="badge badge-complete" style="margin-left:auto;">&#x2705; Complete</span>
    <?php endif; ?>
</div>

<?php if ($pendingCount > 0): ?>
<div class="pending-banner">
    <div class="pb-icon">&#x23F3;</div>
    <div>
        <div class="pb-count"><?= $pendingCount ?></div>
        <div class="pb-label">payment request<?= $pendingCount > 1 ? 's' : '' ?> awaiting admin approval</div>
    </div>
</div>
<?php endif; ?>

<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon">&#x1F3AF;</div>
        <div class="stat-value"><?= money($target) ?></div>
        <div class="stat-label">Target</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#x2705;</div>
        <div class="stat-value"><?= money($totalApproved) ?></div>
        <div class="stat-label">Approved</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#x23F3;</div>
        <div class="stat-value"><?= money($totalPending) ?></div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#x1F4B8;</div>
        <div class="stat-value"><?= money($remaining) ?></div>
        <div class="stat-label">Remaining</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="flex-between mb-2">
            <strong>Contribution Progress</strong>
            <span style="color:var(--gold);font-weight:700;"><?= $percent ?>%</span>
        </div>
        <div class="progress-wrap">
            <div class="progress-bar <?= $isComplete ? 'full' : '' ?>" style="width:<?= $percent ?>%"></div>
        </div>
        <div class="progress-label">
            <span>Approved: <?= money($totalApproved) ?></span>
            <span>Goal: <?= money($target) ?></span>
        </div>
        <?php if ($totalPending > 0): ?>
            <div style="font-size:0.8rem;color:#856404;margin-top:0.5rem;">
                &#x23F3; <?= money($totalPending) ?> pending — not counted until approved
            </div>
        <?php endif; ?>
        <?php if ($isComplete): ?>
            <div class="alert alert-success mt-2">&#x1F389; Congratulations! You have completed your contribution!</div>
        <?php endif; ?>
    </div>
</div>

<div class="flex gap-2 wrap mb-3">
    <button onclick="document.getElementById('payModal').style.display='flex'" class="btn btn-primary">
        &#x1F4B3; Submit Payment Request
    </button>
    <?php if ($hasApproved && $whatsapp): ?>
        <a href="<?= htmlspecialchars($whatsapp) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp">
            &#x1F4F1; Join WhatsApp Group
        </a>
    <?php else: ?>
        <button class="btn btn-whatsapp locked" disabled title="Unlocked after your first approved payment">
            &#x1F512; Join WhatsApp Group
        </button>
    <?php endif; ?>
</div>

<?php if (!$hasApproved && $pendingCount === 0): ?>
<div class="alert alert-info mb-3">
    &#x1F4A1; Submit your first payment request. WhatsApp access unlocks after admin approval.
</div>
<?php elseif (!$hasApproved && $pendingCount > 0): ?>
<div class="alert alert-warning mb-3">
    &#x23F3; Your request is under review. WhatsApp access unlocks once approved.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>&#x1F9FE; Payment History</h3>
        <span class="badge badge-partial"><?= count($payments) ?> record(s)</span>
    </div>
    <?php if (empty($payments)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#x1F4B3;</div>
            <p>No payment requests yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($payments as $p):
            $sMap = [
                'pending'  => ['label' => '&#x23F3; Pending Approval', 'cls' => 'pending'],
                'approved' => ['label' => '&#x2705; Approved',         'cls' => 'approved'],
                'rejected' => ['label' => '&#x274C; Rejected',         'cls' => 'rejected'],
            ];
            $si = $sMap[$p['status']] ?? $sMap['pending'];
            $amtColor = $p['status']==='approved' ? 'var(--green)' : ($p['status']==='rejected' ? 'var(--red)' : '#856404');
        ?>
        <div class="payment-item">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.25rem;">
                    <span class="pay-status <?= $si['cls'] ?>"><?= $si['label'] ?></span>
                    <strong style="color:<?= $amtColor ?>"><?= money($p['amount']) ?></strong>
                </div>
                <div class="pay-date">&#x1F4C5; <?= date('D, d M Y', strtotime($p['payment_date'])) ?></div>
                <?php if (!empty($p['reference_number'])): ?>
                    <div class="pay-ref">Ref: <?= htmlspecialchars($p['reference_number']) ?></div>
                <?php endif; ?>
                <?php if ($p['status'] === 'rejected' && !empty($p['rejection_reason'])): ?>
                    <div style="font-size:0.82rem;color:var(--red);margin-top:0.2rem;font-style:italic;">
                        Admin note: <?= htmlspecialchars($p['rejection_reason']) ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($p['proof_file'])): ?>
            <div style="flex-shrink:0;">
                <?php
                    $ext = strtolower(pathinfo($p['proof_file'], PATHINFO_EXTENSION));
                    $fUrl = 'uploads/' . htmlspecialchars(basename($p['proof_file']));
                ?>
                <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                    <img src="<?= $fUrl ?>" class="proof-thumb"
                         onclick="openLightbox('<?= $fUrl ?>')" alt="Proof">
                <?php else: ?>
                    <a href="<?= $fUrl ?>" target="_blank" class="proof-pdf-icon" title="View PDF">&#x1F4C4;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php endif; ?>
</div>

<!-- PAYMENT MODAL -->
<div id="payModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width:460px;">
        <div class="flex-between mb-2">
            <h3>&#x1F4B3; Submit Payment Request</h3>
            <button onclick="document.getElementById('payModal').style.display='none'"
                    style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);">&#x2715;</button>
        </div>
        <?php if ($user): ?>
        <div style="background:var(--gold-pale);border-radius:8px;padding:0.7rem 1rem;margin-bottom:1.2rem;font-size:0.88rem;color:var(--brown-mid);">
            &#x1F4CB; Your request will be reviewed by admin before counting toward your target.
        </div>
        <form method="POST" action="submit-payment.php" enctype="multipart/form-data">
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <input type="hidden" name="work_id" value="<?= htmlspecialchars($user['work_id']) ?>">
            <div class="form-group">
                <label>Amount (<?= CURRENCY ?>) <span style="color:var(--red)">*</span></label>
                <input type="number" name="amount" class="form-control" placeholder="e.g. 1000" min="1" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Payment Reference Number <span style="color:var(--red)">*</span></label>
                <input type="text" name="reference_number" class="form-control"
                       placeholder="e.g. MPESA-ABC123XY" required autocomplete="off">
                <small style="color:var(--text-muted);">M-Pesa code, bank ref, receipt number, etc.</small>
            </div>
            <div class="form-group">
                <label>Payment Date <span style="color:var(--red)">*</span></label>
                <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Payment Proof <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                <div class="file-upload-zone" id="uploadZone">
                    <input type="file" name="proof_file" id="proofFile"
                           accept="image/*,.pdf" onchange="updateFileName(this)">
                    <div class="upload-icon">&#x1F4CE;</div>
                    <div class="upload-text">Click or drag file here</div>
                    <div class="upload-hint">JPG, PNG, PDF — max 5MB</div>
                    <div class="file-preview-name" id="filePreviewName" style="display:none;"></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.25rem;">
                &#x1F4E4; Submit for Approval
            </button>
        </form>
        <?php else: ?>
            <p>Please fetch your dashboard first by entering your Work ID above.</p>
        <?php endif; ?>
    </div>
</div>

<!-- LIGHTBOX -->
<div id="lightbox" class="lightbox-overlay" style="display:none;" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">&#x2715;</button>
    <img id="lightboxImg" src="" alt="Payment Proof">
</div>

<div class="footer">&#x1F30D; <?= htmlspecialchars($tripName) ?> &nbsp;|&nbsp; Safari Contribution Portal</div>

<script>
const widInput = document.getElementById('work_id');
if (widInput) widInput.addEventListener('input', () => widInput.value = widInput.value.toUpperCase());

document.getElementById('payModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

function updateFileName(input) {
    const el = document.getElementById('filePreviewName');
    el.textContent = input.files[0] ? '\uD83D\uDCCE ' + input.files[0].name : '';
    el.style.display = input.files[0] ? 'block' : 'none';
}

const zone = document.getElementById('uploadZone');
if (zone) {
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop',      () => zone.classList.remove('dragover'));
}

function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>
