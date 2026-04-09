<?php
/**
 * Test HTML entity decoding - Including double/triple encoding
 */

// Test data with various levels of HTML entity encoding
$testCases = [
    // Single encoding
    'single_amp' => 'SECL Recruitment 2026: Mining Sirdar &amp; More',
    'single_quote' => "Don&#39;t miss this opportunity",
    'single_rsquo' => "Government&rsquo;s new scheme",
    
    // Double encoding (the problem case)
    'double_amp' => 'SECL Recruitment 2026: Mining Sirdar &amp;amp; More',
    'double_quote' => "Don&amp;#39;t miss",
    
    // Triple encoding (extreme case)
    'triple_amp' => 'Mining &amp;amp;amp; More',
    
    // Mixed
    'mixed' => 'SECL &amp;amp; CRPF: Don&#39;t miss &rsquo;Apply Now&rsquo;',
];

echo "HTML ENTITY DECODING TEST\n";
echo "=========================\n\n";

// Decode function with multiple iterations
function decodeMultiple($text) {
    $decoded = $text;
    $maxIterations = 5;
    
    for ($i = 0; $i < $maxIterations; $i++) {
        $newDecoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // If no change, we're done
        if ($newDecoded === $decoded) {
            break;
        }
        
        $decoded = $newDecoded;
    }
    
    return $decoded;
}

foreach ($testCases as $name => $text) {
    echo "Test: $name\n";
    echo "Before: $text\n";
    echo "After:  " . decodeMultiple($text) . "\n";
    echo "---\n\n";
}

echo "\nEXPECTED RESULTS:\n";
echo "=================\n";
echo "✅ &amp; should become &\n";
echo "✅ &amp;amp; should become &\n";
echo "✅ &amp;amp;amp; should become &\n";
echo "✅ &#39; should become '\n";
echo "✅ &rsquo; should become '\n";
echo "✅ All HTML entities fully decoded\n";
echo "\n";

// Test the actual problem case
echo "PROBLEM CASE TEST:\n";
echo "==================\n";
$problemTitle = 'SECL Recruitment 2026: 1055 Mining Sirdar &amp;amp; More Vacancies';
echo "Input:  $problemTitle\n";
echo "Output: " . decodeMultiple($problemTitle) . "\n";
echo "\n";

if (decodeMultiple($problemTitle) === 'SECL Recruitment 2026: 1055 Mining Sirdar & More Vacancies') {
    echo "✅ TEST PASSED - Double encoding fixed!\n";
} else {
    echo "❌ TEST FAILED - Still has encoding issues\n";
}

