<?php
header('Content-Type: text/plain');

$BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '8771510966:AAGsaZJhzefxDFmK5CHBLsIFnKt9nT4itgQ';
$CHAT_IDS  = [
    getenv('TELEGRAM_CHAT_ID') ?: '6964954278',
    '8955126022',
    '8895304810'
];

echo "Testing Telegram connection...\n\n";
echo "Bot token: " . substr($BOT_TOKEN, 0, 20) . "...\n";
echo "Chat IDs: " . implode(', ', $CHAT_IDS) . "\n\n";

foreach ($CHAT_IDS as $chatId) {
    $chatId = trim($chatId);
    if (empty($chatId)) { echo "SKIP: empty chat ID\n"; continue; }
    
    $message = "🔧 *Debug Test from Valtix*\n━━━━━━━━━━━━━━━━━━\n📅 Time: " . date('Y-m-d H:i:s') . "\n💻 Server: " . php_uname() . "\n🌐 IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n━━━━━━━━━━━━━━━━━━\n✅ If you see this, Telegram sending from collect.php will work!";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown']),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
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
