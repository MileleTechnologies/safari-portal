<?php
// ============================================
// admin/whatsapp-settings.php
// WhatsApp Group & SMS Notification Settings
// ============================================
require_once __DIR__ . '/../config.php';
requireAdmin();

$db    = getDB();
$flash = getFlash();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $fields = [
        'whatsapp_group_link'      => trim($_POST['whatsapp_group_link'] ?? ''),
        'trip_name'                => trim($_POST['trip_name']           ?? ''),
        'sms_notifications_enabled'=> isset($_POST['sms_notifications_enabled']) ? '1' : '0',
    ];
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value)
                          VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($fields as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    setFlash('success', 'Settings saved successfully!');
    redirect('whatsapp-settings.php');
}

// Resend WhatsApp invite to a specific user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_invite'])) {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid > 0) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        $whatsappLink = getSetting('whatsapp_group_link');

        if ($u && !empty($u['phone_number']) && !empty($whatsappLink)) {
            $result = Sms::sendWhatsAppInvite(
                $u['full_name'],
                $u['phone_number'],
                0,  // Amount 0 = resend, not a new payment
                $whatsappLink
            );
            // Mark as invited
            $db->prepare("UPDATE users SET whatsapp_invited=1 WHERE id=?")->execute([$uid]);
            setFlash($result['success'] ? 'success' : 'error',
                $result['success']
                    ? '<i class="fa-solid fa-mobile-screen"></i> WhatsApp invite resent to ' . $u['full_name'] . ' (' . $u['phone_number'] . ')'
                    : '<i class="fa-solid fa-triangle-exclamation"></i> SMS failed for ' . $u['full_name'] . ': ' . $result['message']
            );
        } else {
            setFlash('error', 'User not found, no phone number, or WhatsApp link not set.');
        }
    }
    redirect('whatsapp-settings.php');
}

// Current settings
$whatsappLink  = getSetting('whatsapp_group_link');
$tripName      = getSetting('trip_name') ?: APP_NAME;
$smsEnabled    = getSetting('sms_notifications_enabled') === '1';
$smsProvider   = defined('SMS_PROVIDER') ? SMS_PROVIDER : 'log';

// Users with at least one approved payment (eligible for WA invite)
$users = $db->query("
    SELECT u.*,
           COUNT(CASE WHEN p.status='approved' THEN 1 END) AS approved_count
    FROM users u
    LEFT JOIN payments p ON p.user_id = u.id
    GROUP BY u.id
    HAVING approved_count > 0
    ORDER BY u.full_name
")->fetchAll();

// SMS log (last 20 lines)
$smsLog     = [];
$smsLogFile = __DIR__ . '/../sms_log.txt';
if (file_exists($smsLogFile)) {
    $lines  = file($smsLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $smsLog = array_slice(array_reverse($lines), 0, 20);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp & SMS Settings — <?= htmlspecialchars($tripName) ?></title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .provider-badge {
            display:inline-flex; align-items:center; gap:6px;
            padding:.3rem .8rem; border-radius:999px; font-size:.82rem; font-weight:600;
        }
        .provider-log       { background:#fef9e7; color:#7d6608; }
        .provider-active    { background:#d5f5e3; color:#1e8449; }
        .provider-inactive  { background:#fdecea; color:#c0392b; }
        .log-line {
            font-family:monospace; font-size:.78rem; padding:.3rem .5rem;
            border-bottom:1px solid var(--border); line-height:1.5;
            word-break:break-all;
        }
        .log-ok   { color:#1e8449; }
        .log-fail { color:#c0392b; }
        .toggle-switch {
            position:relative; display:inline-flex; align-items:center;
            gap:.6rem; cursor:pointer;
        }
        .toggle-switch input { display:none; }
        .toggle-track {
            width:44px; height:24px; background:#ddd; border-radius:999px;
            transition:background .2s; flex-shrink:0; position:relative;
        }
        .toggle-track::after {
            content:''; position:absolute; top:3px; left:3px;
            width:18px; height:18px; background:#fff; border-radius:50%;
            transition:left .2s; box-shadow:0 1px 4px rgba(0,0,0,.2);
        }
        input:checked + .toggle-track { background:var(--green); }
        input:checked + .toggle-track::after { left:23px; }
    </style>
</head>
<body>

<nav class="navbar">
    <a class="navbar-brand" href="admin-dashboard.php"><span class="icon"><i class="fa-solid fa-paw"></i></span> Admin Panel</a>
    <div class="navbar-links">
        <a href="admin-dashboard.php">Dashboard</a>
        <a href="add-user.php">Add User</a>
        <a href="payment-requests.php">Requests</a>
        <a href="whatsapp-settings.php" class="active">WhatsApp</a>
        <a href="admin-logout.php">Logout</a>
    </div>
</nav>

<div class="page-header">
    <div class="subtitle">Admin</div>
    <h1><i class="fa-brands fa-whatsapp"></i> WhatsApp &amp; SMS Settings</h1>
    <p>Configure group invite link and notification preferences</p>
</div>

<div class="container" style="padding-top:1.5rem;padding-bottom:2rem;">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <?= $flash['type']==='success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?> <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- ===== MAIN SETTINGS FORM ===== -->
    <div class="card mb-3">
        <div class="card-header"><h3><i class="fa-solid fa-gear"></i> Group &amp; Notification Settings</h3></div>
        <div class="card-body">
            <form method="POST" action="">

                <div class="form-group">
                    <label>Trip / Fund Name</label>
                    <input type="text" name="trip_name" class="form-control"
                           placeholder="e.g. Safari Adventure 2025"
                           value="<?= htmlspecialchars($tripName) ?>">
                </div>

                <div class="form-group">
                    <label>WhatsApp Group Invite Link <span style="color:var(--red)">*</span></label>
                    <input type="url" name="whatsapp_group_link" class="form-control"
                           placeholder="https://chat.whatsapp.com/xxxxxxxxxxxxxxx"
                           value="<?= htmlspecialchars($whatsappLink) ?>">
                    <small style="color:var(--text-muted);">
                        Go to your WhatsApp group → Invite to group via link → Copy link.
                        This is sent by SMS after the first payment is approved.
                    </small>
                </div>

                <?php if (!empty($whatsappLink)): ?>
                    <div style="margin-bottom:1rem;">
                        <a href="<?= htmlspecialchars($whatsappLink) ?>" target="_blank" rel="noopener"
                           class="btn btn-whatsapp btn-sm">
                            <i class="fa-brands fa-whatsapp"></i> Test Link — Open Group
                        </a>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="sms_notifications_enabled"
                               <?= $smsEnabled ? 'checked' : '' ?>>
                        <span class="toggle-track"></span>
                        <span style="font-weight:600;font-size:.9rem;color:var(--brown);">
                            Enable SMS Notifications
                        </span>
                    </label>
                    <div style="font-size:.82rem;color:var(--text-muted);margin-top:.4rem;margin-left:52px;">
                        When enabled, SMS is sent on every payment approval.
                        First approval includes the WhatsApp group link.
                    </div>
                </div>

                <button type="submit" name="save_settings" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
            </form>
        </div>
    </div>

    <!-- ===== SMS PROVIDER STATUS ===== -->
    <div class="card mb-3">
        <div class="card-header"><h3><i class="fa-solid fa-tower-broadcast"></i> SMS Provider Status</h3></div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
                <div>
                    <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.2rem;">Active Provider</div>
                    <?php
                    $providerLabel = match($smsProvider) {
                        'africastalking','at' => "Africa's Talking",
                        'twilio'             => 'Twilio',
                        default              => 'Log Only (Dev Mode)',
                    };
                    $providerClass = match($smsProvider) {
                        'africastalking','at','twilio' => 'provider-active',
                        default                        => 'provider-log',
                    };
                    ?>
                    <span class="provider-badge <?= $providerClass ?>">
                        <?= $smsProvider === 'log' ? '<i class="fa-solid fa-pen-to-square"></i>' : '<i class="fa-solid fa-tower-broadcast"></i>' ?> <?= htmlspecialchars($providerLabel) ?>
                    </span>
                </div>
                <div>
                    <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.2rem;">Notifications</div>
                    <span class="provider-badge <?= $smsEnabled ? 'provider-active' : 'provider-inactive' ?>">
                        <?= $smsEnabled ? '<i class="fa-solid fa-circle-check"></i> Enabled' : '<i class="fa-solid fa-circle-xmark"></i> Disabled' ?>
                    </span>
                </div>
            </div>

            <div style="background:var(--gold-pale);border-radius:8px;padding:1rem 1.2rem;font-size:.85rem;">
                <strong style="color:var(--brown);">How to switch providers:</strong>
                <ol style="margin:.5rem 0 0 1.2rem;color:var(--text-muted);line-height:1.9;">
                    <li>Open <code>config.php</code></li>
                    <li>Set <code>define('SMS_PROVIDER', 'africastalking')</code>
                        or <code>'twilio'</code> or <code>'log'</code></li>
                    <li>Add your API credentials (commented examples are in config.php)</li>
                    <li>Save and reload — SMS will route through the new provider immediately</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- ===== RESEND INVITES ===== -->
    <?php if (!empty($users)): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h3><i class="fa-solid fa-envelope"></i> Resend WhatsApp Invite</h3>
            <span style="font-size:.8rem;color:var(--text-muted);">
                Only users with ≥1 approved payment shown
            </span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($whatsappLink)): ?>
                <div class="empty-state" style="padding:1.5rem;">
                    <p><i class="fa-solid fa-triangle-exclamation"></i> Set the WhatsApp group link above before sending invites.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Work ID</th>
                                <th>Phone</th>
                                <th>WA Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($u['work_id']) ?></code></td>
                                    <td style="font-size:.85rem;">
                                        <?= !empty($u['phone_number']) ? htmlspecialchars($u['phone_number']) : '<span style="color:var(--red);">No phone</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($u['whatsapp_invited']): ?>
                                            <span class="badge badge-complete"><i class="fa-solid fa-circle-check"></i> Invited</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending"><i class="fa-regular fa-clock"></i> Not yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['phone_number'])): ?>
                                            <form method="POST"
                                                  onsubmit="return confirm('Resend WhatsApp invite to <?= addslashes($u['full_name']) ?>?')">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" name="resend_invite" class="btn btn-sm btn-whatsapp">
                                                    <i class="fa-brands fa-whatsapp"></i> Resend
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size:.78rem;color:var(--text-muted);">
                                                No phone — edit user to add
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== SMS LOG ===== -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-list"></i> Recent SMS Log</h3>
            <span style="font-size:.78rem;color:var(--text-muted);">Last 20 entries from sms_log.txt</span>
        </div>
        <?php if (empty($smsLog)): ?>
            <div class="empty-state" style="padding:1.5rem;">
                <div class="empty-icon"><i class="fa-regular fa-envelope-open"></i></div>
                <p>No SMS activity logged yet.</p>
                <small style="color:var(--text-muted);">
                    Log file: <code><?= htmlspecialchars(realpath($smsLogFile) ?: $smsLogFile) ?></code>
                </small>
            </div>
        <?php else: ?>
            <div style="padding:.5rem 0;">
                <?php foreach ($smsLog as $line):
                    $cls = str_contains($line, '[OK]') ? 'log-ok' : (str_contains($line, '[FAIL]') ? 'log-fail' : '');
                ?>
                    <div class="log-line <?= $cls ?>"><?= htmlspecialchars($line) ?></div>
                <?php endforeach; ?>
            </div>
            <div style="padding:.7rem 1rem;border-top:1px solid var(--border);">
                <a href="clear-sms-log.php"
                   onclick="return confirm('Clear the SMS log file?')"
                   class="btn btn-sm btn-outline"
                   style="color:var(--red);border-color:var(--red);">
                    <i class="fa-solid fa-trash-can"></i> Clear Log
                </a>
            </div>
        <?php endif; ?>
    </div>

</div>

<div class="footer"><i class="fa-solid fa-globe"></i> <?= htmlspecialchars($tripName) ?> — Admin Panel</div>
</body>
</html>
