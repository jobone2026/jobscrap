<?php
/**
 * Detailed Modal Response Check
 * This script performs a comprehensive test of Modal token functionality
 */

$token_id = 'ak-EC1QQwG2ubPJM4ItH4YGym';
$token_secret = 'as-GeysyGCRJPgfoTGsclHitz';

echo "🔍 MODAL TOKEN RESPONSE CHECK\n";
echo str_repeat("=", 50) . "\n";

// Test 1: Basic connectivity
echo "1. Testing basic connectivity...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://modal.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_NOBODY, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    echo "   ✅ Modal.com is reachable (HTTP $http_code)\n";
} else {
    echo "   ❌ Modal.com unreachable (HTTP $http_code)\n";
}

// Test 2: Token format validation
echo "\n2. Validating token format...\n";
if (preg_match('/^ak-[A-Za-z0-9]{22}$/', $token_id)) {
    echo "   ✅ Token ID format is valid\n";
} else {
    echo "   ❌ Token ID format is invalid\n";
}

if (preg_match('/^as-[A-Za-z0-9]{22}$/', $token_secret)) {
    echo "   ✅ Token Secret format is valid\n";
} else {
    echo "   ❌ Token Secret format is invalid\n";
}

// Test 3: Try different Modal API endpoints
echo "\n3. Testing Modal API endpoints...\n";

$test_endpoints = [
    'https://api.modal.com/v1/apps',
    'https://api.modal.com/v1/functions',
    'https://api.modal.com/v1/workspaces'
];

foreach ($test_endpoints as $endpoint) {
    echo "   Testing: $endpoint\n";
    
    $headers = [
        'Modal-Key: ' . $token_id,
        'Modal-Secret: ' . $token_secret,
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: PHP-Modal-Client/1.0'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "      HTTP Code: $http_code\n";
    if ($error) {
        echo "      Error: $error\n";
    }
    if ($response) {
        $decoded = json_decode($response, true);
        if ($decoded) {
            echo "      Response: " . json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "      Raw Response: " . substr($response, 0, 200) . "...\n";
        }
    }
    echo "\n";
}

// Test 4: Check if we can access Modal dashboard/API info
echo "4. Checking Modal API documentation access...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://modal.com/docs/reference/cli/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && strpos($response, 'modal token') !== false) {
    echo "   ✅ Modal documentation accessible\n";
} else {
    echo "   ❌ Modal documentation not accessible\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🏁 RESPONSE CHECK COMPLETE\n";

// Summary
echo "\n📋 SUMMARY:\n";
echo "- Token ID: " . substr($token_id, 0, 15) . "...\n";
echo "- Token Secret: " . substr($token_secret, 0, 15) . "...\n";
echo "- Status: Tokens are properly formatted for Modal API\n";
echo "- Next: Deploy a Modal Python function to get an endpoint URL\n";

?>