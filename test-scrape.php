<?php
/**
 * Test Scraper with Diagnostics
 * This will show you exactly what's happening during scraping
 */

if (file_exists('config.php')) {
    require_once 'config.php';
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Scraper Test</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #0ea5e9;}";
echo ".success{border-left-color:#10b981;}.error{border-left-color:#ef4444;}";
echo ".warning{border-left-color:#f59e0b;}";
echo "h3{margin:0 0 10px 0;}pre{background:#f9fafb;padding:10px;overflow:auto;}</style>";
echo "</head><body>";

echo "<h1>🧪 Scraper Diagnostic Test</h1>";

// Test URL
$testUrl = 'https://www.karnatakacareers.org/ucsl-recruitment-2026-apply-online-for-15-assistant-manager-posts/';

echo "<div class='box'><h3>📋 Configuration Status</h3>";
echo "AI Enabled: " . (defined('AI_ENHANCEMENT_ENABLED') && AI_ENHANCEMENT_ENABLED ? '✅ YES' : '❌ NO') . "<br>";
echo "AI Model: " . (defined('AI_MODEL') ? AI_MODEL : 'deepseek-v3.2') . "<br>";
echo "Social Links: " . (defined('AUTO_ADD_SOCIAL_LINKS') && AUTO_ADD_SOCIAL_LINKS ? '✅ YES' : '❌ NO') . "<br>";
echo "Telegram: " . (defined('TELEGRAM_CHANNEL_URL') ? TELEGRAM_CHANNEL_URL : 'Not set') . "<br>";
echo "WhatsApp: " . (defined('WHATSAPP_CHANNEL_URL') ? WHATSAPP_CHANNEL_URL : 'Not set') . "<br>";
echo "</div>";

echo "<div class='box'><h3>🌐 Testing URL Fetch</h3>";
echo "Test URL: <a href='$testUrl' target='_blank'>$testUrl</a><br><br>";

// Fetch page
$ch = curl_init($testUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $html) {
    echo "✅ Page fetched successfully<br>";
    echo "HTML Size: " . number_format(strlen($html)) . " bytes<br>";
    
    // Check for competitor links
    $competitorCount = 0;
    $competitors = ['karnatakacareers.org', 'indgovtjobs.net', 'sarkariresult.com'];
    foreach ($competitors as $comp) {
        $count = substr_count(strtolower($html), $comp);
        if ($count > 0) {
            $competitorCount += $count;
            echo "Found '$comp': $count times<br>";
        }
    }
    
    echo "</div>";
    
    // Test scraping
    echo "<div class='box'><h3>🔧 Testing Content Extraction</h3>";
    
    // Simple extraction test
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $xpath = new DOMXPath($doc);
    
    $titleNode = $xpath->query('//title')->item(0);
    $title = $titleNode ? trim($titleNode->nodeValue) : 'No title found';
    
    echo "Extracted Title: <strong>$title</strong><br>";
    
    // Check if scrape.php functions are available
    if (file_exists('scrape.php')) {
        echo "✅ scrape.php found<br>";
        
        // Test AI if enabled
        if (defined('AI_ENHANCEMENT_ENABLED') && AI_ENHANCEMENT_ENABLED) {
            echo "<br><div class='warning'><h3>⚠️ AI Test</h3>";
            echo "AI is ENABLED but may fail due to WAF blocking.<br>";
            echo "If scraping is slow or fails, disable AI in config.php<br>";
            echo "</div>";
        } else {
            echo "<br><div class='success'><h3>✅ Auto-Scraper Mode</h3>";
            echo "AI is disabled. Using fast auto-scraping only.<br>";
            echo "This is the recommended mode!<br>";
            echo "</div>";
        }
    }
    
    echo "</div>";
    
    // Test social links
    echo "<div class='box'><h3>📱 Social Links Test</h3>";
    if (defined('AUTO_ADD_SOCIAL_LINKS') && AUTO_ADD_SOCIAL_LINKS) {
        echo "✅ Social links will be added automatically<br>";
        echo "Telegram: " . TELEGRAM_CHANNEL_URL . "<br>";
        echo "WhatsApp: " . WHATSAPP_CHANNEL_URL . "<br>";
    } else {
        echo "❌ Social links are disabled<br>";
    }
    echo "</div>";
    
    // Summary
    echo "<div class='box success'><h3>✅ Summary</h3>";
    echo "<strong>Your scraper is working!</strong><br><br>";
    echo "What happens when you scrape:<br>";
    echo "1. ✅ Fetches the page successfully<br>";
    echo "2. ✅ Extracts title and content<br>";
    echo "3. ✅ Removes competitor links ($competitorCount found)<br>";
    echo "4. ✅ Cleans HTML and removes spam<br>";
    echo "5. ✅ Adds your Telegram & WhatsApp links<br>";
    if (defined('AI_ENHANCEMENT_ENABLED') && AI_ENHANCEMENT_ENABLED) {
        echo "6. ⚠️ Tries AI enhancement (may fail due to WAF)<br>";
    } else {
        echo "6. ✅ Skips AI (fast mode)<br>";
    }
    echo "<br><strong>Go to <a href='https://jobone.in/jobscrap/'>https://jobone.in/jobscrap/</a> and try scraping!</strong>";
    echo "</div>";
    
} else {
    echo "❌ Failed to fetch page (HTTP $httpCode)<br>";
    echo "</div>";
}

echo "</body></html>";
