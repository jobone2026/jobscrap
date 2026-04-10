<?php
/**
 * Check Meta Description - Detailed Analysis
 */

require_once 'scrape.php';

echo "📝 META DESCRIPTION DETAILED CHECK\n";
echo str_repeat("=", 60) . "\n";

$hmtData = [
    'title' => 'HMT Officer Deputy Manager Recruitment',
    'content' => 'The Hindustan Machine Tools (HMT) has released an official notification for the recruitment of 05 Officer, Deputy Manager posts. Interested and eligible candidates can apply online.',
    'type' => 'job'
];

echo "Processing...\n\n";

$result = enrichWithAI($hmtData);

echo "📊 META DESCRIPTION ANALYSIS:\n";
echo str_repeat("-", 60) . "\n";

$metaDesc = $result['meta_description'] ?? 'NOT GENERATED';
$length = mb_strlen($metaDesc);

echo "Meta Description:\n";
echo "\"$metaDesc\"\n\n";

echo "Length: $length characters\n";
echo "Target: 130-150 characters\n\n";

if ($length < 130) {
    echo "❌ TOO SHORT (need at least 130 chars)\n";
    echo "   Missing: " . (130 - $length) . " characters\n";
} elseif ($length > 150) {
    echo "❌ TOO LONG (need maximum 150 chars)\n";
    echo "   Excess: " . ($length - 150) . " characters\n";
} else {
    echo "✅ PERFECT LENGTH (130-150 chars)\n";
}

echo "\n📋 CHARACTER BREAKDOWN:\n";
echo "Position 130: '" . (mb_strlen($metaDesc) >= 130 ? mb_substr($metaDesc, 129, 1) : 'N/A') . "'\n";
echo "Position 150: '" . (mb_strlen($metaDesc) >= 150 ? mb_substr($metaDesc, 149, 1) : 'N/A') . "'\n";

// Check if it ends properly
if (str_ends_with($metaDesc, '...')) {
    echo "\n⚠️  Ends with '...' (truncated)\n";
} elseif (preg_match('/\w+$/', $metaDesc, $matches)) {
    echo "\n✅ Ends with complete word: '" . $matches[0] . "'\n";
}

// Check for incomplete words
if (preg_match('/\b\w{1,3}$/', $metaDesc, $matches)) {
    $lastWord = $matches[0];
    if (!in_array(strtolower($lastWord), ['now', 'job', 'for', 'the', 'and', 'can', 'apply'])) {
        echo "⚠️  Possible incomplete word at end: '$lastWord'\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";

// Test multiple examples
echo "\n🔄 TESTING MULTIPLE EXAMPLES:\n\n";

$testCases = [
    [
        'title' => 'Teacher Recruitment 2026',
        'content' => 'Education department is hiring teachers for primary schools.',
    ],
    [
        'title' => 'Bank Manager Jobs',
        'content' => 'State bank is recruiting managers for various branches.',
    ],
    [
        'title' => 'Police Constable Vacancy',
        'content' => 'Police department has announced vacancies for constable posts.',
    ]
];

foreach ($testCases as $i => $test) {
    $result = enrichWithAI($test);
    $metaDesc = $result['meta_description'] ?? '';
    $len = mb_strlen($metaDesc);
    
    echo ($i + 1) . ". " . $test['title'] . "\n";
    echo "   Length: $len chars ";
    
    if ($len >= 130 && $len <= 150) {
        echo "✅\n";
    } else {
        echo "❌ (need 130-150)\n";
    }
    
    echo "   \"$metaDesc\"\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "🏁 META DESCRIPTION CHECK COMPLETE\n";

?>