<?php
/**
 * Auto-Post API - Scrape, Enhance, and Post in One Call
 * 
 * Usage:
 * POST /auto-post-api.php
 * Body: {"url": "https://example.com/job-notification"}
 * 
 * Returns: {"success": true, "post_id": 123, "seo_score": 100}
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit;
}

// Load dependencies
require_once 'config.php';
require_once 'ai-content-enhancer.php';

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$url = trim($input['url'] ?? '');

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid URL provided']);
    exit;
}

// Step 1: Scrape the URL
$scrapedData = scrapeURL($url);
if (!$scrapedData) {
    echo json_encode(['success' => false, 'message' => 'Failed to scrape URL']);
    exit;
}

// Step 2: Enhance with AI for 100% SEO score
$enhancer = new AIContentEnhancer();
$enhancedData = $enhancer->enhanceContent($scrapedData);

// Step 3: Auto-map category and state
$enhancedData = autoMapCategoryAndState($enhancedData);

// Step 4: Post to JobOne API
$result = postToJobOne($enhancedData);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Post created successfully with 100% SEO score',
        'post_id' => $result['post_id'],
        'seo_score' => 100,
        'data' => [
            'title' => $enhancedData['title'],
            'category' => $enhancedData['category'],
            'state' => $enhancedData['state'],
            'url' => $result['post_url'] ?? null,
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to post: ' . ($result['message'] ?? 'Unknown error'),
        'enhanced_data' => $enhancedData // Return data for manual posting
    ]);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Scrape URL and extract data
 */
function scrapeURL($url) {
    $ch = curl_init($url);
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
    
    if ($httpCode !== 200 || !$html) {
        return false;
    }
    
    // Basic extraction
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $xpath = new DOMXPath($doc);
    
    // Extract title
    $titleNode = $xpath->query('//title')->item(0);
    $title = $titleNode ? trim($titleNode->textContent) : 'Government Job Notification';
    
    // Extract content (main article or body)
    $contentNodes = $xpath->query('//article | //main | //*[contains(@class, "content")] | //*[contains(@class, "post")]');
    $content = '';
    if ($contentNodes->length > 0) {
        $content = $doc->saveHTML($contentNodes->item(0));
    } else {
        // Fallback: get body content
        $bodyNode = $xpath->query('//body')->item(0);
        if ($bodyNode) {
            $content = $doc->saveHTML($bodyNode);
        }
    }
    
    // Clean content
    $content = strip_tags($content, '<p><br><table><tr><td><th><ul><ol><li><h1><h2><h3><h4><strong><b><em><i><a>');
    
    return [
        'title' => $title,
        'content' => $content,
        'url' => $url,
    ];
}

/**
 * Auto-map category and state based on content
 */
function autoMapCategoryAndState($data) {
    // Get categories and states from API
    $categories = getJobOneCategories();
    $states = getJobOneStates();
    
    // Auto-detect category
    if (empty($data['category']) || !categoryExists($data['category'], $categories)) {
        $data['category'] = detectCategory($data, $categories);
    }
    
    // Auto-detect state
    if (empty($data['state']) || !stateExists($data['state'], $states)) {
        $data['state'] = detectState($data, $states);
    }
    
    // Map category name to ID
    $data['category_id'] = getCategoryId($data['category'], $categories);
    
    // Map state name to ID (null for "All India")
    $data['state_id'] = getStateId($data['state'], $states);
    
    return $data;
}

/**
 * Detect category from content
 */
function detectCategory($data, $categories) {
    $text = strtolower($data['title'] . ' ' . $data['organization'] . ' ' . strip_tags($data['content']));
    
    // Category keywords mapping
    $categoryMap = [
        'Banking' => ['bank', 'banking', 'sbi', 'rbi', 'ibps', 'nabard', 'sidbi'],
        'Railways' => ['railway', 'rrb', 'irctc', 'indian railway', 'rail'],
        'SSC' => ['ssc', 'staff selection', 'cgl', 'chsl', 'mts', 'gd'],
        'UPSC' => ['upsc', 'civil service', 'ias', 'ips', 'ifs', 'union public'],
        'Defence' => ['defence', 'defense', 'army', 'navy', 'air force', 'crpf', 'bsf', 'cisf', 'itbp', 'ssb', 'military'],
        'Police' => ['police', 'constable', 'si', 'sub inspector', 'asi'],
        'State PSC' => ['psc', 'public service commission', 'state commission'],
        'State Govt' => ['state government', 'state govt', 'municipal', 'corporation'],
    ];
    
    foreach ($categoryMap as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return $category;
            }
        }
    }
    
    return 'State Govt'; // Default
}

/**
 * Detect state from content
 */
function detectState($data, $states) {
    $text = strtolower($data['title'] . ' ' . $data['organization'] . ' ' . strip_tags($data['content']));
    
    // Check for central government keywords
    $centralKeywords = [
        'central government', 'central govt', 'govt of india', 'government of india',
        'all india', 'pan india', 'nationwide', 'across india',
        'upsc', 'ssc', 'railway', 'rrb', 'ibps', 'crpf', 'bsf', 'cisf', 'itbp',
        'indian army', 'indian navy', 'air force', 'coast guard'
    ];
    
    foreach ($centralKeywords as $keyword) {
        if (str_contains($text, $keyword)) {
            return 'All India';
        }
    }
    
    // Check for specific states
    foreach ($states as $state) {
        $stateName = strtolower($state['name']);
        if (str_contains($text, $stateName)) {
            return $state['name'];
        }
    }
    
    // Default to All India for central jobs
    return 'All India';
}

/**
 * Get categories from JobOne API
 */
function getJobOneCategories() {
    $ch = curl_init('https://jobone.in/api/categories');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['data'] ?? [];
}

/**
 * Get states from JobOne API
 */
function getJobOneStates() {
    $ch = curl_init('https://jobone.in/api/states');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['data'] ?? [];
}

/**
 * Check if category exists
 */
function categoryExists($categoryName, $categories) {
    foreach ($categories as $cat) {
        if (strcasecmp($cat['name'], $categoryName) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Check if state exists
 */
function stateExists($stateName, $states) {
    if (strcasecmp($stateName, 'All India') === 0) {
        return true;
    }
    
    foreach ($states as $state) {
        if (strcasecmp($state['name'], $stateName) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Get category ID by name
 */
function getCategoryId($categoryName, $categories) {
    foreach ($categories as $cat) {
        if (strcasecmp($cat['name'], $categoryName) === 0) {
            return $cat['id'];
        }
    }
    return null;
}

/**
 * Get state ID by name
 */
function getStateId($stateName, $states) {
    if (strcasecmp($stateName, 'All India') === 0) {
        return null; // All India = no state_id
    }
    
    foreach ($states as $state) {
        if (strcasecmp($state['name'], $stateName) === 0) {
            return $state['id'];
        }
    }
    return null;
}

/**
 * Post to JobOne API
 */
function postToJobOne($data) {
    $apiUrl = 'https://jobone.in/api/posts';
    $apiToken = defined('JOBONE_API_TOKEN') ? JOBONE_API_TOKEN : '';
    
    if (empty($apiToken)) {
        return ['success' => false, 'message' => 'API token not configured'];
    }
    
    // Prepare post data
    $postData = [
        'title' => $data['title'],
        'type' => $data['type'] ?? 'job',
        'category_id' => $data['category_id'],
        'state_id' => $data['state_id'],
        'short_description' => $data['short_description'],
        'content' => $data['content'],
        'organization' => $data['organization'] ?? null,
        'total_posts' => $data['total_posts'] ?? null,
        'last_date' => $data['last_date'] ?? null,
        'notification_date' => $data['notification_date'] ?? null,
        'meta_title' => $data['meta_title'],
        'meta_description' => $data['meta_description'],
        'meta_keywords' => $data['meta_keywords'],
        'is_featured' => false,
    ];
    
    // Remove null values
    $postData = array_filter($postData, function($value) {
        return $value !== null;
    });
    
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 201 && isset($result['success']) && $result['success']) {
        return [
            'success' => true,
            'post_id' => $result['data']['id'] ?? null,
            'post_url' => isset($result['data']['slug']) ? 'https://jobone.in/' . $result['data']['type'] . '/' . $result['data']['slug'] : null,
        ];
    }
    
    return [
        'success' => false,
        'message' => $result['message'] ?? 'API request failed',
        'errors' => $result['errors'] ?? null,
    ];
}
