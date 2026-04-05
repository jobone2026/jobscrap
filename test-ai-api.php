<?php
/**
 * Test AI API Connection
 * Run this to check if your AgentRouter API key is working
 */

if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    die("❌ config.php not found!\n");
}

echo "🧪 Testing AI API Connection...\n\n";

// Check if API key is set
if (!defined('AGENTROUTER_API_KEY') || AGENTROUTER_API_KEY === 'YOUR_KEY_HERE') {
    die("❌ AGENTROUTER_API_KEY not configured in config.php\n");
}

echo "✅ API Key found: " . substr(AGENTROUTER_API_KEY, 0, 10) . "...\n\n";

// Test API call
$url = 'https://agentrouter.org/v1/chat/completions';

$testPrompt = "Format this job info as JSON with title, short_description, and content fields: UPSC Civil Services 2024 - 1000 posts, Apply before 15 March 2024";

$payload = [
    'model' => 'deepseek-v3.2',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a job content formatter. Return JSON only.'],
        ['role' => 'user', 'content' => $testPrompt]
    ],
    'response_format' => ['type' => 'json_object'],
    'temperature' => 0.3
];

echo "📤 Sending test request to AgentRouter...\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AGENTROUTER_API_KEY,
        'Origin: https://agentrouter.org',
        'Referer: https://agentrouter.org/'
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "📥 Response received\n";
echo "HTTP Code: $httpCode\n\n";

if ($curlError) {
    echo "❌ cURL Error: $curlError\n";
    exit(1);
}

if ($httpCode === 200) {
    echo "✅ API Connection Successful!\n\n";
    
    $json = json_decode($response, true);
    
    // Debug: Show full response structure
    echo "📋 Full Response Structure:\n";
    echo "─────────────────────────────────────\n";
    echo json_encode($json, JSON_PRETTY_PRINT);
    echo "\n─────────────────────────────────────\n\n";
    
    if (isset($json['choices'][0]['message']['content'])) {
        echo "✅ AI Response received:\n";
        echo "─────────────────────────────────────\n";
        echo $json['choices'][0]['message']['content'];
        echo "\n─────────────────────────────────────\n\n";
        
        // Check if it's valid JSON
        $aiContent = json_decode($json['choices'][0]['message']['content'], true);
        if ($aiContent) {
            echo "✅ Valid JSON response\n";
            if (isset($aiContent['title'])) echo "✅ Title field present\n";
            if (isset($aiContent['short_description'])) echo "✅ Short description field present\n";
            if (isset($aiContent['content'])) echo "✅ Content field present\n";
        } else {
            echo "⚠️ Response is not valid JSON\n";
        }
        
        echo "\n🎉 AI API is working correctly!\n";
        echo "Your jobscrap tool will use AI enhancement.\n";
        
    } else if (isset($json['content'])) {
        // Alternative response format
        echo "✅ AI Response received (alternative format):\n";
        echo "─────────────────────────────────────\n";
        echo $json['content'];
        echo "\n─────────────────────────────────────\n\n";
        echo "🎉 AI API is working!\n";
    } else {
        echo "⚠️ Unexpected response format\n";
        echo "Available keys: " . implode(', ', array_keys($json)) . "\n";
    }
    
} else {
    echo "❌ API Error (HTTP $httpCode)\n\n";
    echo "Response:\n";
    echo $response . "\n\n";
    
    $json = json_decode($response, true);
    if ($json && isset($json['error'])) {
        echo "Error details:\n";
        echo "Type: " . ($json['error']['type'] ?? 'unknown') . "\n";
        echo "Message: " . ($json['error']['message'] ?? 'unknown') . "\n";
    }
    
    echo "\n❌ AI API is NOT working!\n";
    echo "Possible issues:\n";
    echo "- Invalid API key\n";
    echo "- API quota exceeded\n";
    echo "- Network/firewall blocking the request\n";
    echo "- AgentRouter service down\n";
}

echo "\n" . str_repeat("─", 50) . "\n";
echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
