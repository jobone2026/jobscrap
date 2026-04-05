<?php
/**
 * Check AgentRouter API Key Validity
 */

if (file_exists('config.php')) {
    require_once 'config.php';
}

echo "🔑 Checking AgentRouter API Key...\n\n";

$apiKey = AGENTROUTER_API_KEY;
echo "API Key: " . substr($apiKey, 0, 15) . "...\n";
echo "Key Length: " . strlen($apiKey) . " characters\n\n";

// Try a simple models list endpoint first
$url = 'https://agentrouter.org/v1/models';

echo "📤 Testing connection to: $url\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n\n";

if ($error) {
    echo "❌ cURL Error: $error\n";
    exit(1);
}

if ($httpCode === 401) {
    echo "❌ API Key is INVALID or EXPIRED\n";
    echo "Please check your AGENTROUTER_API_KEY in config.php\n";
    echo "Get a new key from: https://agentrouter.org\n";
    exit(1);
}

if ($httpCode === 200) {
    echo "✅ API Key is VALID!\n\n";
    $json = json_decode($response, true);
    if ($json && isset($json['data'])) {
        echo "Available models:\n";
        foreach ($json['data'] as $model) {
            echo "  - " . ($model['id'] ?? 'unknown') . "\n";
        }
    }
} else {
    echo "⚠️ Unexpected response (HTTP $httpCode)\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}

echo "\n" . str_repeat("─", 50) . "\n";
echo "Note: If you're getting WAF/firewall errors, it might be:\n";
echo "1. Your server IP is blocked by AgentRouter's firewall\n";
echo "2. The API endpoint has changed\n";
echo "3. AgentRouter is having issues\n\n";
echo "Alternative: You can disable AI and use auto-scraping only:\n";
echo "Set AI_ENHANCEMENT_ENABLED = false in config.php\n";
