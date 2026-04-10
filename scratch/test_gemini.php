<?php
require_once 'config.php';
require_once 'ai-content-enhancer.php';

$enhancer = new AIContentEnhancer();
$testData = [
    'title' => 'Test Job Title',
    'content' => '<p>This is a test job description in Hindi: यह एक परीक्षण है।</p>',
    'type' => 'job'
];

echo "Testing Gemini API...\n";
try {
    $result = $enhancer->enhanceContent($testData);
    echo "SUCCESS!\n";
    echo "Generated Title: " . ($result['title'] ?? 'N/A') . "\n";
    echo "AI Provider: " . ($result['ai_provider'] ?? 'N/A') . "\n";
    echo "Has Translation: " . (isset($result['translated_content']) ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
