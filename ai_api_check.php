<?php
/**
 * Comprehensive AI API Check
 * Tests all AI functionality for JobOne Auto-Poster
 */

require_once 'config.php';
require_once 'sambanova_client.php';

echo "🤖 COMPREHENSIVE AI API CHECK\n";
echo str_repeat("=", 60) . "\n";

// Initialize AI client
$ai = new SambaNovaAI();

// Test 1: Basic Connection
echo "1. BASIC CONNECTION TEST\n";
echo str_repeat("-", 30) . "\n";
$connection_test = $ai->testConnection();

if ($connection_test['success']) {
    echo "✅ AI Connection: WORKING\n";
    echo "   Response: " . $connection_test['content'] . "\n";
    if (isset($connection_test['usage'])) {
        echo "   Tokens Used: " . $connection_test['usage']['total_tokens'] . "\n";
    }
} else {
    echo "❌ AI Connection: FAILED\n";
    echo "   Error: " . $connection_test['error'] . "\n";
}

echo "\n";

// Test 2: Job Content Enhancement
echo "2. JOB CONTENT ENHANCEMENT TEST\n";
echo str_repeat("-", 30) . "\n";

$test_job = [
    'title' => 'PHP Developer',
    'company' => 'WebTech Solutions',
    'location' => 'Mumbai, India',
    'description' => 'We are looking for an experienced PHP developer to join our dynamic team. Must have knowledge of Laravel framework.'
];

echo "Input Job Data:\n";
echo "  Title: " . $test_job['title'] . "\n";
echo "  Company: " . $test_job['company'] . "\n";
echo "  Location: " . $test_job['location'] . "\n";
echo "  Description: " . $test_job['description'] . "\n\n";

$enhanced = $ai->enhanceJobContent(
    $test_job['title'],
    $test_job['description'],
    $test_job['company'],
    $test_job['location']
);

if ($enhanced['success']) {
    echo "✅ Job Enhancement: WORKING\n";
    echo "Enhanced Content:\n";
    echo $enhanced['content'] . "\n";
    
    if (isset($enhanced['usage'])) {
        echo "\nAPI Usage Stats:\n";
        echo "  Prompt Tokens: " . $enhanced['usage']['prompt_tokens'] . "\n";
        echo "  Completion Tokens: " . $enhanced['usage']['completion_tokens'] . "\n";
        echo "  Total Tokens: " . $enhanced['usage']['total_tokens'] . "\n";
    }
} else {
    echo "❌ Job Enhancement: FAILED\n";
    echo "   Error: " . $enhanced['error'] . "\n";
}

echo "\n";

// Test 3: SEO Metadata Generation
echo "3. SEO METADATA GENERATION TEST\n";
echo str_repeat("-", 30) . "\n";

$seo_test = $ai->generateSEOMetadata(
    'Senior React Developer',
    'Join our team as a Senior React Developer. Work with modern technologies and build amazing user interfaces.'
);

if ($seo_test['success']) {
    echo "✅ SEO Generation: WORKING\n";
    echo "SEO Metadata:\n";
    echo $seo_test['content'] . "\n";
} else {
    echo "❌ SEO Generation: FAILED\n";
    echo "   Error: " . $seo_test['error'] . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

// Final Summary
echo "🏁 AI API CHECK SUMMARY\n\n";

$all_working = $connection_test['success'] && $enhanced['success'] && $seo_test['success'];

if ($all_working) {
    echo "🎉 ALL AI FEATURES ARE WORKING PERFECTLY!\n\n";
    echo "✅ Connection: OK\n";
    echo "✅ Job Enhancement: OK\n";
    echo "✅ SEO Generation: OK\n";
    echo "✅ API Key: Valid\n";
    echo "✅ Model: " . SAMBANOVA_MODEL . "\n\n";
    echo "🚀 Your JobOne Auto-Poster is ready to use AI enhancement!\n";
} else {
    echo "⚠️  SOME AI FEATURES HAVE ISSUES\n\n";
    echo ($connection_test['success'] ? "✅" : "❌") . " Connection\n";
    echo ($enhanced['success'] ? "✅" : "❌") . " Job Enhancement\n";
    echo ($seo_test['success'] ? "✅" : "❌") . " SEO Generation\n";
    echo "\n🔧 Please check the errors above and fix any issues.\n";
}

?>