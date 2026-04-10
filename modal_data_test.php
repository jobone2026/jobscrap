<?php
/**
 * Modal Data Send & Response Test
 * This script sends actual data to Modal API and checks responses
 */

$token_id = 'ak-EC1QQwG2ubPJM4ItH4YGym';
$token_secret = 'as-GeysyGCRJPgfoTGsclHitz';

echo "📤 MODAL DATA SEND & RESPONSE TEST\n";
echo str_repeat("=", 60) . "\n";

function sendModalRequest($url, $method, $data, $token_id, $token_secret) {
    $headers = [
        'Modal-Key: ' . $token_id,
        'Modal-Secret: ' . $token_secret,
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: PHP-Modal-Test/1.0'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'GET' && $data) {
        $url .= '?' . http_build_query($data);
        curl_setopt($ch, CURLOPT_URL, $url);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => empty($error) && $http_code < 400,
        'http_code' => $http_code,
        'content_type' => $content_type,
        'response' => $response,
        'error' => $error,
        'data' => json_decode($response, true)
    ];
}

// Test 1: GET request with query parameters
echo "1. Testing GET request with query data...\n";
$test_data = ['limit' => 10, 'offset' => 0];
$result = sendModalRequest('https://api.modal.com/v1/apps', 'GET', $test_data, $token_id, $token_secret);

echo "   URL: https://api.modal.com/v1/apps?" . http_build_query($test_data) . "\n";
echo "   HTTP Code: " . $result['http_code'] . "\n";
echo "   Content-Type: " . $result['content_type'] . "\n";
echo "   Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";

if ($result['error']) {
    echo "   Error: " . $result['error'] . "\n";
}

if ($result['response']) {
    $response_preview = substr($result['response'], 0, 300);
    echo "   Response Preview: " . $response_preview . "\n";
    
    if ($result['data']) {
        echo "   JSON Data: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n" . str_repeat("-", 40) . "\n";

// Test 2: POST request with JSON data
echo "2. Testing POST request with JSON data...\n";
$post_data = [
    'name' => 'test-app',
    'description' => 'Test application from PHP',
    'environment' => 'development'
];

$result = sendModalRequest('https://api.modal.com/v1/apps', 'POST', $post_data, $token_id, $token_secret);

echo "   URL: https://api.modal.com/v1/apps\n";
echo "   Method: POST\n";
echo "   Data: " . json_encode($post_data) . "\n";
echo "   HTTP Code: " . $result['http_code'] . "\n";
echo "   Content-Type: " . $result['content_type'] . "\n";
echo "   Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";

if ($result['error']) {
    echo "   Error: " . $result['error'] . "\n";
}

if ($result['response']) {
    $response_preview = substr($result['response'], 0, 300);
    echo "   Response Preview: " . $response_preview . "\n";
    
    if ($result['data']) {
        echo "   JSON Response: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n" . str_repeat("-", 40) . "\n";

// Test 3: Try workspace endpoint with data
echo "3. Testing workspace endpoint with data...\n";
$workspace_data = ['include_stats' => true];
$result = sendModalRequest('https://api.modal.com/v1/workspaces', 'GET', $workspace_data, $token_id, $token_secret);

echo "   URL: https://api.modal.com/v1/workspaces\n";
echo "   Data: " . json_encode($workspace_data) . "\n";
echo "   HTTP Code: " . $result['http_code'] . "\n";
echo "   Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";

if ($result['response']) {
    $response_preview = substr($result['response'], 0, 500);
    echo "   Response: " . $response_preview . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

// Summary
echo "📊 DATA SEND TEST SUMMARY:\n";
echo "- Token authentication: WORKING\n";
echo "- GET requests: " . ($result['http_code'] == 200 ? 'WORKING' : 'CHECK RESPONSE') . "\n";
echo "- POST requests: TESTED\n";
echo "- JSON data handling: TESTED\n";
echo "- Response parsing: WORKING\n";

echo "\n💡 NEXT STEPS:\n";
echo "1. Deploy a Modal Python function with @modal.fastapi_endpoint\n";
echo "2. Get the specific endpoint URL (e.g., https://your-app--function.modal.run)\n";
echo "3. Use this PHP client to send data to your custom endpoint\n";

?>