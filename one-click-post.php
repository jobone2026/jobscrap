<?php
/**
 * One-Click Post - Scrape, Enhance, and Post automatically
 */

require_once 'config.php';
require_once 'ai-content-enhancer.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Get URL from request
$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? $_GET['url'] ?? $GLOBALS['url'] ?? '';

error_log("one-click-post.php received URL: " . $url);

if (!$url) {
    echo json_encode(['success' => false, 'message' => 'URL is required']);
    exit;
}

// 1. Fetch and Scrape
require_once 'scrape.php'; // Reuse existing scraping functions

$fetchResult = fetchPage($url);
if ($fetchResult['error']) {
    echo json_encode(['success' => false, 'message' => 'Fetch error: ' . $fetchResult['error']]);
    exit;
}

$html = $fetchResult['html'];
$scrapedData = extractData($html, $url);
$scrapedData['url'] = $url;
$scrapedData['type'] = detectType($url, $scrapedData['title'], $html);

// 2. Enhance with AI
$enhancer = new AIContentEnhancer();
$enhancedData = $enhancer->enhanceContent($scrapedData);

// 3. Auto-map Category and State
require_once 'auto-post-api.php'; // Reuse mapping and posting functions

// We need to make sure the functions are available. 
// Since auto-post-api.php also has some logic that runs on load, 
// we might need to wrap them or just use them if they are defined.

$categories = getJobOneCategories();
$states = getJobOneStates();

// Auto-detect if not guessed or if guessed incorrectly
if (empty($enhancedData['category_guess'])) {
    $enhancedData['category'] = detectCategory($enhancedData, $categories);
} else {
    $enhancedData['category'] = $enhancedData['category_guess'];
}

if (empty($enhancedData['state_guess']) || $enhancedData['state_guess'] === 'All India') {
    $enhancedData['state'] = detectState($enhancedData, $states);
} else {
    $enhancedData['state'] = $enhancedData['state_guess'];
}

$enhancedData['category_id'] = getCategoryId($enhancedData['category'], $categories);
$enhancedData['state_id'] = getStateId($enhancedData['state'], $states);

// 4. Post to API
$result = postToJobOne($enhancedData);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Successfully scraped and posted!',
        'post_id' => $result['post_id'],
        'url' => $result['post_url'],
        'data' => [
            'title' => $enhancedData['title'],
            'category' => $enhancedData['category'],
            'state' => $enhancedData['state'],
            'type' => $enhancedData['type']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Post failed: ' . ($result['message'] ?? 'Unknown error'),
        'errors' => $result['errors'] ?? null,
        'debug_data' => $enhancedData
    ]);
}
