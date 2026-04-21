<?php
require_once 'auto-post-api.php';
$url = 'https://www.freejobalert.com/articles/lucknow-airport-recruitment-2026-apply-online-for-08-technician-computer-programmer-and-more-posts-3046290';

$scrapedData = scrapeURL($url);
if (!$scrapedData) {
    die("Scrape failed");
}
$enhancer = new AIContentEnhancer();
$enhancedData = $enhancer->enhanceContent($scrapedData);

echo "IMAGE URL INCLUDED?\n";
if (strpos($enhancedData['content'], 'pollinations') !== false) {
    echo "YES\n";
    echo substr($enhancedData['content'], 0, 1000);
} else {
    echo "NO\n";
    echo substr($enhancedData['content'], 0, 500);
}
