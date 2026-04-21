<?php

require_once 'config.php';
require_once 'ai-content-enhancer.php';

$title = "Lucknow Airport Recruitment 2026";
$rawHtml = "<div>Sample content about Lucknow airport hiring technicians</div>";

$enhancer = new AIContentEnhancer();
$scrapedData = [
    'title' => $title,
    'content' => $rawHtml,
    'url' => 'https://www.freejobalert.com/articles/lucknow-airport-recruitment'
];

$enhanced = $enhancer->enhanceContent($scrapedData);
echo "==== ENHANCED HTML CONTENT ====\n";
echo $enhanced['content'];
echo "\n==== JSON DATA ====\n";
print_r($enhanced);
