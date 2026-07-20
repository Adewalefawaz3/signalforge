<?php
/**
 * Debug_tg.php
 * Quick test script to verify Telegram connectivity.
 * Access via browser: https://your-app.onrender.com/Debug_tg.php
 */

$BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$CHAT_IDS = [
    getenv('TELEGRAM_CHAT_ID') ?: '6964954278',
    '8955126022',
    '8895304810'
];

function tgHtmlEscape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

echo "<pre>\n";
echo "=== Telegram Debug ===\n";
echo "Bot: " . substr($BOT_TOKEN, 0, 15) . "...\n";
echo "Chats: " . implode(', ', $CHAT_IDS) . "\n\n";

foreach ($CHAT_IDS as $chatId) {
    $chatId = trim($chatId);
    if (empty($chatId)) continue;

    $message = "<b>✅ Valtix — Debug Test</b>\n\n";
    $message .= "This is a test message via <b>HTML</b> parse_mode.\n";
    $message .= "It contains special chars: underscores_here, *asterisks*, (parens), [brackets]\n";
    $message .= "All should display correctly.\n\n";
    $message .= "⚡ <b>Valtix Intelligence</b>";

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
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "Chat {$chatId}: HTTP {$http}\n";
    if ($http === 200) {
        echo "  ✅ SUCCESS! Check Telegram.\n";
    } else {
        echo "  ❌ FAILED: {$error}\n";
        echo "  Response: " . substr($resp, 0, 300) . "\n";
    }
    echo "\n";
}

echo "</pre>\n";
