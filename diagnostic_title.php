<?php
/**
 * Diagnostic script to test AI Title Rewriting
 */
require_once 'config.php';
require_once 'scrape.php'; // We'll mock the inputs to test the function

$url = 'https://www.freejobalert.com/articles/central-bank-of-india-so-recruitment-2026-apply-online-for-26-specialist-officers-posts-3044731';
$originalTitle = "Central Bank of India SO Recruitment 2026 – Apply Online for 26 Specialist Officers Posts";

$data = [
    'title' => $originalTitle,
    'content' => '<h1>Job Details</h1><p>Central Bank of India has announced notification for Specialist Officers...</p>',
    'short_description' => ''
];

echo "--- START DIAGNOSTIC ---\n";
echo "Original Title: " . $data['title'] . "\n";
echo "AI Provider: " . AI_PROVIDER . "\n";
echo "AI Model: " . AI_MODEL . "\n";

$enriched = enrichWithAI($data);

echo "\nAI Output Title: " . ($enriched['title'] ?? 'FAILED TO RETURN TITLE') . "\n";

if ($enriched['title'] === $originalTitle) {
    echo "\n❌ RESULT: Title DID NOT change! The AI returned the same title.\n";
} else {
    echo "\n✅ RESULT: Title SUCCESSFULLY changed to: " . $enriched['title'] . "\n";
}

if (isset($enriched['ai_error'])) {
    echo "AI ERROR: " . $enriched['ai_error'] . "\n";
}

echo "--- END DIAGNOSTIC ---\n";
