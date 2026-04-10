<?php
/**
 * Verify AI Integration - Check if SambaNova AI is properly integrated
 */

echo "🔍 VERIFYING AI INTEGRATION\n";
echo str_repeat("=", 50) . "\n";

// Test 1: Check config.php
echo "1. Checking config.php...\n";
require_once 'config.php';

if (defined('SAMBANOVA_API_KEY') && SAMBANOVA_API_KEY) {
    echo "   ✅ SAMBANOVA_API_KEY: " . substr(SAMBANOVA_API_KEY, 0, 8) . "...\n";
} else {
    echo "   ❌ SAMBANOVA_API_KEY: NOT FOUND\n";
}

if (defined('SAMBANOVA_CHAT_ENDPOINT') && SAMBANOVA_CHAT_ENDPOINT) {
    echo "   ✅ SAMBANOVA_CHAT_ENDPOINT: " . SAMBANOVA_CHAT_ENDPOINT . "\n";
} else {
    echo "   ❌ SAMBANOVA_CHAT_ENDPOINT: NOT FOUND\n";
}

if (defined('SAMBANOVA_MODEL') && SAMBANOVA_MODEL) {
    echo "   ✅ SAMBANOVA_MODEL: " . SAMBANOVA_MODEL . "\n";
} else {
    echo "   ❌ SAMBANOVA_MODEL: NOT FOUND\n";
}

// Test 2: Check AI Content Enhancer
echo "\n2. Testing AI Content Enhancer...\n";
if (file_exists('ai-content-enhancer.php')) {
    require_once 'ai-content-enhancer.php';
    $enhancer = new AIContentEnhancer();
    
    $testData = [
        'title' => 'Test Job',
        'content' => 'This is a test job posting.',
        'type' => 'job'
    ];
    
    try {
        $result = $enhancer->enhanceContent($testData);
        if (isset($result['ai_provider']) && $result['ai_provider'] === 'sambanova') {
            echo "   ✅ AI Content Enhancer: WORKING with SambaNova\n";
        } else {
            echo "   ⚠️  AI Content Enhancer: Working but provider unclear\n";
        }
    } catch (Exception $e) {
        echo "   ❌ AI Content Enhancer: ERROR - " . $e->getMessage() . "\n";
    }
} else {
    echo "   ❌ ai-content-enhancer.php: FILE NOT FOUND\n";
}

// Test 3: Check blog scraper
echo "\n3. Testing blog scraper AI function...\n";
if (file_exists('blog_scrape.php')) {
    // Read the file content to check for SambaNova references
    $blogContent = file_get_contents('blog_scrape.php');
    if (strpos($blogContent, 'SAMBANOVA_API_KEY') !== false) {
        echo "   ✅ Blog scraper: Updated to use SambaNova\n";
    } else {
        echo "   ❌ Blog scraper: Still using old AI\n";
    }
    
    if (strpos($blogContent, 'agentrouter') === false) {
        echo "   ✅ Blog scraper: Old API references removed\n";
    } else {
        echo "   ⚠️  Blog scraper: Still has old API references\n";
    }
} else {
    echo "   ❌ blog_scrape.php: FILE NOT FOUND\n";
}

// Test 4: Direct API test
echo "\n4. Testing SambaNova API directly...\n";
$headers = [
    'Authorization: Bearer ' . SAMBANOVA_API_KEY,
    'Content-Type: application/json'
];

$data = [
    'model' => SAMBANOVA_MODEL,
    'messages' => [
        ['role' => 'user', 'content' => 'Say "AI integration test successful" if you can read this.']
    ],
    'max_tokens' => 20
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, SAMBANOVA_CHAT_ENDPOINT);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        echo "   ✅ SambaNova API: WORKING\n";
        echo "   Response: " . $result['choices'][0]['message']['content'] . "\n";
    } else {
        echo "   ❌ SambaNova API: Invalid response format\n";
    }
} else {
    echo "   ❌ SambaNova API: HTTP $httpCode\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🏁 VERIFICATION COMPLETE\n";

// Check for old references
echo "\n🔍 CHECKING FOR OLD AI REFERENCES...\n";
$oldRefs = 0;

if (defined('AO_TOKEN_ID')) {
    echo "⚠️  AO_TOKEN_ID still defined in config\n";
    $oldRefs++;
}

if (defined('AO_TOKEN_SECRET')) {
    echo "⚠️  AO_TOKEN_SECRET still defined in config\n";
    $oldRefs++;
}

if ($oldRefs == 0) {
    echo "✅ No old AI references found\n";
    echo "🎉 SambaNova AI integration is COMPLETE!\n";
} else {
    echo "⚠️  Found $oldRefs old AI references that need cleanup\n";
}

?>