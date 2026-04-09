<?php
/**
 * Test SEO Score Calculation
 * Simulates the exact scoring logic from admin panel
 */

// Sample enhanced data
$testData = [
    'title' => 'CRPF Constable Recruitment 2026: Apply for 9195 Technical Posts',
    'meta_title' => 'CRPF Constable 2026: 9195 Posts | JobOne.in',
    'meta_description' => 'CRPF Constable Recruitment 2026: Apply online for 9195 Technical & Tradesmen posts. Last date: 15 March 2026. Check eligibility & apply now!',
    'meta_keywords' => 'CRPF Constable 2026, CRPF Recruitment, Government Jobs 2026, Police Jobs, Central Govt Jobs, CRPF Bharti, Constable Vacancy, Technical Posts',
    'content' => str_repeat('<p>This is sample content for word count testing. ', 60) . '<a href="https://jobone.in/">Link 1</a> <a href="https://jobone.in/category/defence">Link 2</a>',
];

echo "SEO SCORE CALCULATOR TEST\n";
echo "==========================\n\n";

// Calculate score using exact admin panel logic
function calculateSEOScore($data) {
    $score = 0;
    $breakdown = [];
    
    // 1. Title Length (20 points)
    $titleLength = mb_strlen($data['meta_title'] ?? $data['title']);
    if ($titleLength >= 50 && $titleLength <= 60) {
        $score += 20;
        $breakdown['title_length'] = ['score' => 20, 'status' => '✅', 'value' => $titleLength];
    } elseif ($titleLength >= 40 && $titleLength < 50) {
        $score += 15;
        $breakdown['title_length'] = ['score' => 15, 'status' => '⚠️', 'value' => $titleLength];
    } elseif ($titleLength > 0) {
        $score += 5;
        $breakdown['title_length'] = ['score' => 5, 'status' => '❌', 'value' => $titleLength];
    }
    
    // 2. Description Length (20 points)
    $descLength = mb_strlen($data['meta_description'] ?? '');
    if ($descLength >= 120 && $descLength <= 160) {
        $score += 20;
        $breakdown['desc_length'] = ['score' => 20, 'status' => '✅', 'value' => $descLength];
    } elseif ($descLength >= 80 && $descLength < 120) {
        $score += 15;
        $breakdown['desc_length'] = ['score' => 15, 'status' => '⚠️', 'value' => $descLength];
    } elseif ($descLength > 0) {
        $score += 5;
        $breakdown['desc_length'] = ['score' => 5, 'status' => '❌', 'value' => $descLength];
    }
    
    // 3. Keyword in Title (20 points)
    $keywords = array_map('trim', explode(',', $data['meta_keywords'] ?? ''));
    $titleLower = strtolower($data['meta_title'] ?? $data['title']);
    $keywordInTitle = false;
    foreach ($keywords as $kw) {
        if (!empty($kw) && str_contains($titleLower, strtolower($kw))) {
            $keywordInTitle = true;
            break;
        }
    }
    if ($keywordInTitle) {
        $score += 20;
        $breakdown['keyword_in_title'] = ['score' => 20, 'status' => '✅'];
    } else {
        $breakdown['keyword_in_title'] = ['score' => 0, 'status' => '❌'];
    }
    
    // 4. Keyword in Description (15 points)
    $descLower = strtolower($data['meta_description'] ?? '');
    $keywordInDesc = false;
    foreach ($keywords as $kw) {
        if (!empty($kw) && str_contains($descLower, strtolower($kw))) {
            $keywordInDesc = true;
            break;
        }
    }
    if ($keywordInDesc) {
        $score += 15;
        $breakdown['keyword_in_desc'] = ['score' => 15, 'status' => '✅'];
    } else {
        $breakdown['keyword_in_desc'] = ['score' => 0, 'status' => '❌'];
    }
    
    // 5. Word Count (15 points)
    $text = strip_tags($data['content'] ?? '');
    $words = array_filter(explode(' ', $text), function($w) { return strlen(trim($w)) > 0; });
    $wordCount = count($words);
    if ($wordCount >= 300) {
        $score += 15;
        $breakdown['word_count'] = ['score' => 15, 'status' => '✅', 'value' => $wordCount];
    } elseif ($wordCount >= 150) {
        $score += 10;
        $breakdown['word_count'] = ['score' => 10, 'status' => '⚠️', 'value' => $wordCount];
    } elseif ($wordCount > 0) {
        $score += 5;
        $breakdown['word_count'] = ['score' => 5, 'status' => '❌', 'value' => $wordCount];
    }
    
    // 6. Internal Links (10 points)
    $linkCount = preg_match_all('/<a\s+[^>]*href=["\'][^"\']*["\'][^>]*>/i', $data['content'] ?? '', $matches);
    if ($linkCount >= 2) {
        $score += 10;
        $breakdown['links'] = ['score' => 10, 'status' => '✅', 'value' => $linkCount];
    } elseif ($linkCount === 1) {
        $score += 5;
        $breakdown['links'] = ['score' => 5, 'status' => '⚠️', 'value' => $linkCount];
    } else {
        $breakdown['links'] = ['score' => 0, 'status' => '❌', 'value' => $linkCount];
    }
    
    return ['total' => $score, 'breakdown' => $breakdown];
}

$result = calculateSEOScore($testData);

echo "SCORE BREAKDOWN:\n";
echo "================\n";
foreach ($result['breakdown'] as $metric => $data) {
    $label = ucwords(str_replace('_', ' ', $metric));
    $value = isset($data['value']) ? " ({$data['value']})" : "";
    echo sprintf("%-25s %s %2d/20 points%s\n", $label . ':', $data['status'], $data['score'], $value);
}

echo "\n";
echo "TOTAL SCORE: {$result['total']}/100\n";
echo "\n";

if ($result['total'] === 100) {
    echo "✅ PERFECT SCORE! All SEO requirements met!\n";
} elseif ($result['total'] >= 80) {
    echo "⚠️  GOOD SCORE - Minor improvements needed\n";
} else {
    echo "❌ LOW SCORE - Significant improvements needed\n";
}

echo "\n";
echo "REQUIREMENTS FOR 100/100:\n";
echo "=========================\n";
echo "✅ Meta title: 50-60 characters\n";
echo "✅ Meta description: 120-160 characters\n";
echo "✅ At least one keyword in title\n";
echo "✅ At least one keyword in description\n";
echo "✅ Content: 300+ words\n";
echo "✅ Content: 2+ internal links\n";
