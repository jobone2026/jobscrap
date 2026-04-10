<?php
/**
 * scrape.php — Backend scraper + AI-style content extractor
 * Called via POST with JSON body: { "url": "https://...", "forced_type": "" }
 * Returns JSON with extracted post data
 */

// Start output buffering to capture any stray output
ob_start();

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Suppress PHP warnings/notices in production (they break JSON)
if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', 0);
}

if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    define('JOBONE_API_TOKEN', 'YOUR_TOKEN_HERE');
}
require_once 'ai-content-enhancer.php';

define('API_BASE', 'https://jobone.in/api');
define('API_TOKEN', JOBONE_API_TOKEN);

$url = '';
$forcedType = '';

if (php_sapi_name() !== 'cli' || (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === 'scrape.php')) {
    if (php_sapi_name() !== 'cli') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = [];
    }

    $url = trim($input['url'] ?? '');
    $forcedType = trim($input['forced_type'] ?? '');

    // Clean and validate URL
    if ($url) {
        // Remove any whitespace and control characters
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url);
        $url = trim($url);
        
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid URL format. Please provide a valid HTTP/HTTPS URL.']);
            exit;
        }
        
        // Ensure URL has a scheme
        if (!preg_match('/^https?:\/\//i', $url)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'URL must start with http:// or https://']);
            exit;
        }
    }

    if (basename($_SERVER['PHP_SELF'] ?? '') === 'scrape.php' && !$url) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'URL is required.']);
        exit;
    }
}

// ─── 1. Fetch the page ───────────────────────────────────────────────────────
function fetchPage($url)
{
    // Log the URL for debugging
    error_log("fetchPage called with URL: " . $url);
    
    // Encode URL properly to handle special characters
    $parsedUrl = parse_url($url);
    
    // Log parsed URL components
    error_log("Parsed URL: " . print_r($parsedUrl, true));
    
    if ($parsedUrl === false) {
        return ['html' => '', 'error' => 'Malformed URL: Unable to parse URL'];
    }
    
    // Check for required components
    if (empty($parsedUrl['scheme'])) {
        return ['html' => '', 'error' => 'URL must start with http:// or https://. You entered: ' . $url];
    }
    
    if (empty($parsedUrl['host'])) {
        return ['html' => '', 'error' => 'No hostname found in URL. You entered: ' . $url . '. Example: https://example.com'];
    }
    
    // Rebuild URL with encoded components
    $scheme = $parsedUrl['scheme'];
    $host = $parsedUrl['host'];
    $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
    $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '/';
    $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
    $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';
    
    $cleanUrl = $scheme . '://' . $host . $port . $path . $query . $fragment;
    
    error_log("Clean URL: " . $cleanUrl);
    
    $ch = curl_init($cleanUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $html = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || $info['http_code'] >= 400) {
        return ['html' => '', 'error' => $err ?: 'HTTP ' . $info['http_code']];
    }
    return ['html' => $html, 'error' => null];
}

// ─── 2. Detect post type ─────────────────────────────────────────────────────
function detectType($url, $title, $html)
{
    // Use only title + body text — exclude domain to avoid false positives
    // (e.g. sarkariresult.com.cm triggering "result" for a recruitment post)
    $haystack = strtolower($title . ' ' . substr($html, 0, 5000));
    // Also include URL path (not domain) for additional hints
    $urlPath = strtolower(parse_url($url, PHP_URL_PATH) ?? '');
    $haystack .= ' ' . $urlPath;

    // Order matters: check specific types first, then "job" BEFORE "result"
    // because many recruitment pages also mention "result date"
    $map = [
        'admit_card' => ['admit card', 'admit-card', 'admitcard', 'hall ticket', 'hall-ticket', 'call letter'],
        'answer_key' => ['answer key', 'answer-key', 'answerkey', 'answer sheet', 'official key'],
        'syllabus' => ['syllabus', 'exam pattern', 'curriculum'],
        'job' => ['recruitment', 'vacancy', 'notification', 'apply online', 'application', 'job', 'bharti', 'online form'],
        'result' => ['result', 'merit list', 'cutoff', 'cut-off', 'final result', 'scorecard'],
    ];
    foreach ($map as $type => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($haystack, $kw))
                return $type;
        }
    }
    return 'job';
}

// ─── 3. Extract key data ─────────────────────────────────────────────────────
function extractData($html, $url)
{
    libxml_use_internal_errors(true);

    // Pre-strip scripts, styles, and noscript blocks via regex before DOM parsing.
    // This prevents DOMDocument from misinterpreting HTML tags inside JS strings or template literals,
    // which causes raw JS code to bleed into the text/HTML output.
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
    $html = preg_replace('/<noscript\b[^>]*>(.*?)<\/noscript>/is', '', $html);

    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    $xpath = new DOMXPath($doc);

    $data = [];

    // Title
    $titleNode = $xpath->query('//meta[@property="og:title"]/@content')->item(0)
        ?? $xpath->query('//meta[@name="twitter:title"]/@content')->item(0)
        ?? $xpath->query('//title')->item(0)
        ?? $xpath->query('//h1')->item(0);
    $data['title'] = $titleNode ? cleanText($titleNode->nodeValue) : 'Untitled Post';

    // Short description
    $descNode = $xpath->query('//meta[@property="og:description"]/@content')->item(0)
        ?? $xpath->query('//meta[@name="description"]/@content')->item(0)
        ?? $xpath->query('//meta[@name="twitter:description"]/@content')->item(0);
    $data['short_description'] = $descNode ? cleanText($descNode->nodeValue) : '';

    // Dates — extract from text patterns
    $fullText = $doc->textContent;
    $data['last_date'] = extractDate($fullText, ['last date', 'closing date', 'apply before', 'last day', 'end date', 'deadline']);
    $data['notification_date'] = extractDate($fullText, ['notification date', 'advertisement date', 'advt date', 'issue date', 'release date']);
    $data['total_posts'] = extractNumber($fullText, ['total post', 'total vacancy', 'total vacancies', 'total seat', 'no. of post', 'number of post']);

    // Important links
    $data['important_links'] = extractLinks($xpath, $url);

    // Category guess
    $data['category_guess'] = guessCategory($data['title'] . ' ' . $fullText);

    // State guess
    $data['state_guess'] = guessState($data['title'] . ' ' . $fullText);

    // Main content — prefer article/main/section, else body
    $data['content'] = buildContent($xpath, $doc, $url);

    // Meta keywords from page
    $kwNode = $xpath->query('//meta[@name="keywords"]/@content')->item(0);
    $data['meta_keywords'] = $kwNode ? cleanText($kwNode->nodeValue) : '';

    if (isset($data['ai_enhanced']) && $data['ai_enhanced'] && isset($data['ai_error'])) {
        unset($data['ai_error']);
    }

    if (isset($data['ai_enhanced']) && $data['ai_enhanced'] && isset($data['ai_error'])) {
        unset($data['ai_error']);
    }

    return $data;
}

function cleanText($t)
{
    // Ensure it's a string
    $t = (string) $t;
    
    // Decode HTML entities multiple times to catch double-encoded entities like &amp;amp;
    for ($i = 0; $i < 4; $i++) {
        $new = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($new === $t) break;
        $t = $new;
    }

    // Forcibly fix any dangling literal &amp; artifacts (longer one first)
    $t = str_ireplace(['&amp;amp;', '&amp;'], '&', $t);
    
    // Final check for the literal string "&amp;" which might have been missed
    $t = str_replace('&amp;', '&', $t);

    $t = trim(preg_replace('/\s+/', ' ', $t));
    // Remove common site branding/spam from titles/descriptions
    $t = preg_replace('/(sarkari\s?job|sarkari\s?result|sarkari\s?exam|sarkarijobfind|sarkari\s?job\s?find|sarkariexam|sarkari\s?alert)/i', '', $t);
    return trim($t, " \t\n\r\0\x0B|,-");
}

function extractDate($text, array $labels)
{
    foreach ($labels as $label) {
        // Pattern: label followed by a date-like string
        $pattern = '/' . preg_quote($label, '/') . '[^\d]{0,20}(\d{1,2}[\s\-\/\.]+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|\d{1,2})[\s\-\/\.]+\d{2,4})/i';
        if (preg_match($pattern, $text, $m)) {
            return normalizeDate($m[1]);
        }
        // Pattern: label followed by DD Month YYYY
        $pattern2 = '/' . preg_quote($label, '/') . '[^\d]{0,20}(\d{1,2}\s+(?:january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{4})/i';
        if (preg_match($pattern2, $text, $m)) {
            return normalizeDate($m[1]);
        }
    }
    return '';
}

function normalizeDate($raw)
{
    $raw = trim($raw);
    $ts = strtotime($raw);
    if ($ts && $ts > 0) {
        return date('Y-m-d', $ts);
    }
    return '';
}

function extractNumber($text, array $labels)
{
    foreach ($labels as $label) {
        $pattern = '/' . preg_quote($label, '/') . '[^\d]{0,20}([\d,]+)/i';
        if (preg_match($pattern, $text, $m)) {
            return (int) str_replace(',', '', $m[1]);
        }
    }
    return null;
}

function extractLinks(DOMXPath $xpath, $baseUrl)
{
    $links = [];
    $seen = [];

    // Generic link texts to skip (they give no useful title)
    $genericTexts = [
        'here',
        'link',
        'visit',
        'read more',
        'more',
        'view',
        'open',
        'go',
        'see',
        'check',
        'download mobile app',
        'mobile app',
        'click here',
        'join arattai channel',
        'arattai channel',
        'arattai',
        'sarkari result',
        'sarkarijobfind',
    ];

    // Keywords to match (in link text OR href OR row context)
    $keywords = [
        'apply',
        'application',
        'notification',
        'advertisement',
        'advt',
        'download',
        'pdf',
        'official',
        'website',
        'portal',
        'admit',
        'hall ticket',
        'result',
        'merit list',
        'syllabus',
        'exam pattern',
        'vacancy',
        'recruitment',
        'form',
        'register',
        'registration',
        'login',
        'apply link',
    ];

    // Social media domains — replace with JobOne channels instead of skipping
    $socialReplace = [
        'whatsapp.com' => ['title' => 'Join WhatsApp Channel', 'url' => 'https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22'],
        'wa.me' => ['title' => 'Join WhatsApp Channel', 'url' => 'https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22'],
        't.me' => ['title' => 'Join Telegram Channel', 'url' => 'https://t.me/jobone2026'],
        'telegram.me' => ['title' => 'Join Telegram Channel', 'url' => 'https://t.me/jobone2026'],
    ];

    // Absolute junk domains — completely skip
    $skipDomains = [
        'facebook.com',
        'twitter.com',
        'youtube.com',
        'instagram.com',
        'linkedin.com',
        'play.google.com',
        'apps.apple.com',
        'sarkarijobfind.com',
        'sarkariresult.com',
        'sarkariresult.com.cm',
        'arattai',
    ];

    $nodes = $xpath->query('//a[@href]');

    foreach ($nodes as $node) {
        $href = trim($node->getAttribute('href'));
        $text = cleanText($node->textContent);

        // Basic quality filters
        if (!$href || $href === '#' || str_starts_with($href, 'javascript:'))
            continue;
        if (!$text || strlen($text) > 160)
            continue;
        if (is_numeric($text))
            continue;

        // Resolve absolute URL
        $abs = $href;
        if (!str_starts_with($abs, 'http') && !str_starts_with($abs, '//')) {
            $parsed = parse_url($baseUrl);
            $root = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
            $abs = $root . '/' . ltrim($href, '/');
        }

        // Handle social media links — replace with JobOne channels
        $isSocial = false;
        foreach ($socialReplace as $domain => $replacement) {
            if (str_contains($abs, $domain)) {
                $socialAlreadyAdded = false;
                foreach ($links as $existing) {
                    if ($existing['url'] === $replacement['url']) {
                        $socialAlreadyAdded = true;
                        break;
                    }
                }
                if (!$socialAlreadyAdded && !in_array($replacement['url'], $seen)) {
                    $links[] = $replacement;
                    $seen[] = $replacement['url'];
                }
                $isSocial = true;
                break;
            }
        }
        if ($isSocial)
            continue;

        // Skip absolute junk domains (case-insensitive)
        $skipThis = false;
        $absLower = strtolower($abs);
        foreach ($skipDomains as $d) {
            if (str_contains($absLower, $d)) {
                $skipThis = true;
                break;
            }
        }
        if ($skipThis)
            continue;

        // Walk up the DOM to find the nearest <tr> first (best context)
        $trNode = $node->parentNode;
        while ($trNode && $trNode->nodeName !== 'tr' && $trNode->nodeName !== 'body') {
            $trNode = $trNode->parentNode;
        }
        // If we found a TR, use it; otherwise fallback to nearest p/div/li
        if ($trNode && $trNode->nodeName === 'tr') {
            $row = $trNode;
        } else {
            $row = $node->parentNode;
            while ($row && !in_array($row->nodeName, ['p', 'div', 'li', 'body'])) {
                $row = $row->parentNode;
            }
        }

        $rowText = $row ? strtolower($row->textContent) : '';

        // Label resolution for generic texts like "Click Here"
        if (in_array(strtolower($text), $genericTexts) || strtolower($text) === 'click here') {
            $resolved = false;

            if ($row && $row->nodeName === 'tr') {
                // Grab text from the FIRST cell of this table row (the label column)
                $cells = $xpath->query('.//td|.//th', $row);
                if ($cells->length >= 2) {
                    $potentialTitle = cleanText($cells->item(0)->textContent);
                    if (strlen($potentialTitle) > 2 && !in_array(strtolower($potentialTitle), $genericTexts)) {
                        $text = $potentialTitle;
                        $resolved = true;
                    }
                }
            }

            if (!$resolved) {
                // Look for preceding text sibling in the same parent
                $parent = $node->parentNode;
                if ($parent) {
                    $prevText = '';
                    foreach ($parent->childNodes as $child) {
                        if ($child === $node)
                            break;
                        if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_ELEMENT_NODE) {
                            $t = cleanText($child->textContent);
                            if (strlen($t) > 2)
                                $prevText = $t;
                        }
                    }
                    if ($prevText && !in_array(strtolower($prevText), $genericTexts)) {
                        $text = $prevText;
                        $resolved = true;
                    }
                }
            }

            // Strategy 3: preceding sibling ELEMENT (h1-h6, p, strong, div)
            // Handles sites like sarkariresult.com.cm where labels are in sibling headings:
            // <h5>Apply Online</h5> <h5><a>Click Here</a></h5> <a>Click Here</a>
            if (!$resolved) {
                // Walk up to the link's heading/block parent first
                $linkBlock = $node->parentNode;
                while ($linkBlock && !in_array($linkBlock->nodeName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'li', 'td', 'body'])) {
                    $linkBlock = $linkBlock->parentNode;
                }
                if ($linkBlock && $linkBlock->nodeName !== 'body') {
                    // Walk backwards through preceding siblings, skip generic ones
                    $prevSib = $linkBlock->previousSibling;
                    $attempts = 0;
                    while ($prevSib && $attempts < 5) {
                        // Skip text nodes (whitespace)
                        if ($prevSib->nodeType !== XML_ELEMENT_NODE) {
                            $prevSib = $prevSib->previousSibling;
                            continue;
                        }
                        $sibText = cleanText($prevSib->textContent);
                        $sibLower = strtolower($sibText);
                        // Skip if this sibling itself has generic text (e.g. another "Click Here" heading)
                        if (in_array($sibLower, $genericTexts) || $sibLower === 'click here') {
                            $prevSib = $prevSib->previousSibling;
                            $attempts++;
                            continue;
                        }
                        // Found a meaningful label
                        if (strlen($sibText) > 2 && strlen($sibText) < 80) {
                            $text = $sibText;
                            $resolved = true;
                        }
                        break;
                    }
                }
            }

            // Last resort — infer label from URL structure
            if (!$resolved || in_array(strtolower($text), $genericTexts)) {
                $lowerAbs = strtolower($abs);
                if (str_contains($lowerAbs, '.pdf'))
                    $text = 'Download Notification PDF';
                elseif (str_contains($lowerAbs, 'apply') || str_contains($lowerAbs, 'register') || str_contains($lowerAbs, 'ibps') || str_contains($lowerAbs, 'iibf') || preg_match('/reg\d+/', $lowerAbs))
                    $text = 'Apply Online';
                elseif (str_contains($lowerAbs, 'admit') || str_contains($lowerAbs, 'hallticket'))
                    $text = 'Download Admit Card';
                elseif (str_contains($lowerAbs, 'result') || str_contains($lowerAbs, 'merit'))
                    $text = 'View Result';
                elseif (str_contains($lowerAbs, 'notification') || str_contains($lowerAbs, 'advt') || str_contains($lowerAbs, 'advertisement'))
                    $text = 'Download Notification';
                elseif (str_contains($lowerAbs, 'syllabus') || str_contains($lowerAbs, 'pattern'))
                    $text = 'Download Syllabus';
                else
                    continue; // truly can't resolve — skip
            }
        }

        // Final keyword match in title, URL, or surrounding row context
        $lower = strtolower($text . ' ' . $abs . ' ' . $rowText);
        $matched = false;
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                $matched = true;
                break;
            }
        }
        // Also match "official website" by text
        if (!$matched && str_contains(strtolower($text), 'official'))
            $matched = true;
        if (!$matched)
            continue;

        $uniqueKey = $abs . '|' . strtolower(trim($text));
        if (in_array($uniqueKey, $seen))
            continue;

        $links[] = ['title' => $text, 'url' => $abs];
        $seen[] = $uniqueKey;

        if (count($links) >= 10)
            break;
    }

    return $links;
}


function guessCategory($text)
{
    $text = strtolower($text);
    // Order matters — check SPECIFIC orgs before generic keywords
    // e.g. 'upsssc' must match 'State Govt' before 'ssc' matches 'SSC'
    $map = [
        ['name' => 'State Govt', 'keywords' => ['upsssc', 'uksssc', 'ukpsc', 'uppsc', 'bssc', 'bpsc', 'mppsc', 'rpsc', 'rsmssb', 'hssc', 'hpsc', 'jssc', 'cgpsc', 'osssc', 'wbssc', 'dsssb', 'teacher cadre', 'teacher', 'tet ', 'ctet', 'high court', 'district court', 'panchayat', 'state govt', 'anganwadi', 'gram sevak', 'patwari', 'lekhpal']],
        ['name' => 'State PSC', 'keywords' => ['psc', 'state public service', 'appsc', 'tnpsc', 'kpsc', 'hppsc', 'gpsc', 'jkpsc', 'ppsc']],
        ['name' => 'Banking', 'keywords' => ['bank', 'sbi', 'rbi', 'ibps', 'nabard', 'idbi']],
        ['name' => 'Railways', 'keywords' => ['railway', 'rrb ', 'rlwl', 'indian rail', 'metro rail', 'ntpc', 'dfccil']],
        ['name' => 'UPSC', 'keywords' => ['upsc', 'civil service', 'ias ', 'ips ', 'ifs ', 'nda ', 'cds ']],
        ['name' => 'SSC', 'keywords' => [' ssc ', 'staff selection', 'chsl', 'cgl', 'ssc mts']],
        ['name' => 'Defence', 'keywords' => ['army', 'navy', 'airforce', 'air force', 'coast guard', 'defence', 'military', 'agniveer', 'drdo', 'beml', 'hal', 'bel']],
        ['name' => 'Police', 'keywords' => ['police', 'constable', 'sub-inspector', 'si ', 'crpf', 'cisf', 'bsf', 'ssc gd']],
    ];
    foreach ($map as $cat) {
        foreach ($cat['keywords'] as $kw) {
            if (str_contains($text, $kw))
                return $cat['name'];
        }
    }
    return ''; // empty means no guess, let user decide or leave blank
}

function guessState($text)
{
    $text = strtolower($text);
    $states = [
        'Andhra Pradesh',
        'Arunachal Pradesh',
        'Assam',
        'Bihar',
        'Chhattisgarh',
        'Delhi',
        'Goa',
        'Gujarat',
        'Haryana',
        'Himachal Pradesh',
        'Jharkhand',
        'Karnataka',
        'Kerala',
        'Madhya Pradesh',
        'Maharashtra',
        'Manipur',
        'Meghalaya',
        'Mizoram',
        'Nagaland',
        'Odisha',
        'Punjab',
        'Rajasthan',
        'Sikkim',
        'Tamil Nadu',
        'Telangana',
        'Tripura',
        'Uttar Pradesh',
        'Uttarakhand',
        'West Bengal'
    ];

    // First, check state-specific organization abbreviations
    // These MUST be checked BEFORE allIndia to avoid 'ssc' catching 'upsssc' etc.
    $stateOrgs = [
        'Uttar Pradesh' => ['upsssc', 'uppsc', 'uppcl', 'upsrtc', 'up police', 'uttar pradesh'],
        'Bihar' => ['bpsc', 'bssc', 'bsphcl', 'bihar'],
        'Madhya Pradesh' => ['mppsc', 'mppeb', 'mp police', 'madhya pradesh'],
        'Rajasthan' => ['rpsc', 'rsmssb', 'rssb', 'rajasthan'],
        'Haryana' => ['hssc', 'hpsc', 'haryana'],
        'Jharkhand' => ['jssc', 'jpsc', 'jharkhand'],
        'Chhattisgarh' => ['cgpsc', 'cgvyapam', 'chhattisgarh'],
        'Uttarakhand' => ['uksssc', 'ukpsc', 'uttarakhand'],
        'West Bengal' => ['wbpsc', 'wbssc', 'west bengal'],
        'Odisha' => ['osssc', 'opsc', 'odisha', 'orissa'],
        'Tamil Nadu' => ['tnpsc', 'mrb', 'tamil nadu'],
        'Karnataka' => ['kpsc', 'kea', 'karnataka'],
        'Kerala' => ['kpsc kerala', 'kerala'],
        'Andhra Pradesh' => ['appsc', 'andhra pradesh'],
        'Telangana' => ['tspsc', 'telangana'],
        'Gujarat' => ['gpsc', 'gsssb', 'gujarat'],
        'Punjab' => ['ppsc', 'psssb', 'punjab'],
        'Delhi' => ['dsssb', 'delhi'],
        'Maharashtra' => ['mpsc', 'maharashtra'],
        'Assam' => ['apsc assam', 'assam'],
    ];
    foreach ($stateOrgs as $state => $orgKws) {
        foreach ($orgKws as $orgKw) {
            if (str_contains($text, $orgKw))
                return $state;
        }
    }

    // Then check "All India" level organizations
    $allIndiaKeywords = ['upsc', ' ssc ', 'rrb ', 'railway', 'bank', 'sbi', 'ibps', 'army', 'navy', 'airforce', 'drdo', 'aiims'];
    foreach ($allIndiaKeywords as $kw) {
        if (str_contains($text, $kw))
            return 'All India';
    }

    foreach ($states as $state) {
        if (str_contains($text, strtolower($state)))
            return $state;
    }
    return 'All India'; // default
}

function buildContent(DOMXPath $xpath, DOMDocument $doc, $pageUrl)
{
    // Priority selectors — most specific content containers first
    $selectors = [
        '//*[contains(@class,"entry-content")]',
        '//*[contains(@class,"post-content")]',
        '//*[contains(@class,"article-content")]',
        '//*[contains(@class,"article-body")]',
        '//*[contains(@class,"post-body")]',
        '//*[contains(@class,"content-area")]',
        '//*[contains(@class,"single-content")]',
        '//*[contains(@class,"main-content")]',
        '//*[@itemprop="articleBody"]',
        '//*[@itemprop="text"]',
        '//article',
        '//*[@role="main"]',
        '//main',
        '//*[@id="content"]',
        '//*[@id="main-content"]',
        '//*[@id="primary"]',
        '//body',
    ];

    $contentHtml = '';
    foreach ($selectors as $sel) {
        $nodes = $xpath->query($sel);
        if (!$nodes || $nodes->length === 0)
            continue;

        $node = $nodes->item(0);

        // Deep-clone so we don't mutate the real DOM across iterations
        $clone = $node->cloneNode(true);

        // Create a new DOMXPath for the cloned node's document
        $cloneDoc = new DOMDocument();
        $cloneDoc->appendChild($cloneDoc->importNode($clone, true));
        $cloneXpath = new DOMXPath($cloneDoc);

        removeUnwanted($cloneXpath, $cloneDoc->documentElement, $cloneDoc);

        $inner = '';
        foreach ($cloneDoc->documentElement->childNodes as $child) {
            $inner .= $cloneDoc->saveHTML($child);
        }
        $inner = cleanHtml($inner);

        // Lower threshold - accept content with at least 100 chars of text
        $textLength = strlen(strip_tags($inner));
        if ($textLength > 100) {
            $contentHtml = $inner;
            break;
        }
    }

    // If still no content, try a more aggressive approach
    if (!$contentHtml) {
        // Try to get all meaningful content from body
        $contentElements = $xpath->query('//body//p | //body//table | //body//ul | //body//ol | //body//h1 | //body//h2 | //body//h3 | //body//h4 | //body//div[contains(@class,"content") or contains(@class,"post") or contains(@class,"article")]');
        if ($contentElements && $contentElements->length > 0) {
            $tempDoc = new DOMDocument();
            $tempDiv = $tempDoc->createElement('div');
            $tempDoc->appendChild($tempDiv);

            foreach ($contentElements as $elem) {
                $clone = $tempDoc->importNode($elem, true);
                $tempDiv->appendChild($clone);
            }

            $inner = '';
            foreach ($tempDiv->childNodes as $child) {
                $inner .= $tempDoc->saveHTML($child);
            }
            $inner = cleanHtml($inner);
            if (strlen(strip_tags($inner)) > 100) {
                $contentHtml = $inner;
            }
        }
    }

    if (!$contentHtml) {
        $contentHtml = '<p>Unable to extract content. Please paste content manually.</p>';
    }

    return $contentHtml;
}

function removeUnwanted(DOMXPath $xpath, DOMNode $node, DOMDocument $doc)
{
    // ── HTML tags to nuke entirely ────────────────────────────────────
    $removeTags = [
        'script',
        'style',
        'nav',
        'footer',
        'header',
        'noscript',
        'iframe',
        'form',
        'aside',
        'button',
        'svg',
        'video',
        'audio',
        'canvas',
        'select',
        'input',
        'textarea',
        'img',
        'picture',
        'figure',
    ];
    foreach ($removeTags as $tag) {
        $els = $xpath->query('.//' . $tag, $node);
        foreach (iterator_to_array($els) as $el) {
            if ($el->parentNode) {
                $el->parentNode->removeChild($el);
            }
        }
    }

    // ── Class / ID patterns to remove ────────────────────────────────
    // These match elements that are NOT article content
    $junkPatterns = [
        // Social / share
        'facebook',
        'twitter',
        'share-btn',
        'social-',
        'share-',
        'animatic',
        // Ads / promos
        'ad-',
        '-ad-',
        'ad_',
        'banner',
        'promo',
        'sponsor',
        'google-preferred',
        'dfp-',
        'adsense',
        // Widgets / sidebars
        'widget',
        'sidebar',
        'yarpp',
        'cj-widget',
        'fja-alert',
        'alert-widget',
        // Navigation
        'breadcrumb',
        'pagination',
        'nav-',
        'menu-',
        // Author / meta
        'author-bio',
        'author-box',
        'article-meta-bar',
        'meta-bar',
        'byline',
        'post-author',
        // Newsletter / subscription
        'newsletter',
        'subscribe',
        'signup',
        'opt-in',
        // Comments
        'comment',
        'disqus',
        'respond',
        // Games / misc clutter
        'games-button',
        'play-games',
        'button-container',
    ];

    $allElements = $xpath->query('.//*[@class or @id]', $node);
    foreach (iterator_to_array($allElements) as $el) {
        $class = strtolower($el->getAttribute('class') . ' ' . $el->getAttribute('id'));
        $shouldRemove = false;
        foreach ($junkPatterns as $pattern) {
            if (str_contains($class, $pattern)) {
                $shouldRemove = true;
                break;
            }
        }
        if ($shouldRemove && $el->parentNode) {
            $el->parentNode->removeChild($el);
        }
    }

    // Nuke specific spam text content
    $textNodes = $xpath->query('.//text()', $node);
    $nodesToDelete = [];
    foreach (iterator_to_array($textNodes) as $textNode) {
        $val = strtolower($textNode->nodeValue);
        if (
            str_contains($val, 'android app') ||
            str_contains($val, 'mobile app') ||
            str_contains($val, 'download mobile') ||
            str_contains($val, 'arattai channel') ||
            str_contains($val, 'join arattai') ||
            str_contains($val, 'satisfied by') || str_contains($val, 'sarkari result') || str_contains($val, 'sarkariresult')
        ) {
            $nodesToDelete[] = $textNode;
        }
    }

    foreach ($nodesToDelete as $textNode) {
        if (!$textNode->parentNode)
            continue;

        $target = $textNode->parentNode;
        // Only remove the immediate parent if it's a small container
        if ($target && in_array(strtolower($target->nodeName), ['p', 'span', 'a', 'li']) && $target->parentNode) {
            try {
                $target->parentNode->removeChild($target);
            } catch (\DOMException $e) {
            }
        }
    }

    // ── Remove "Related Posts" & "Latest Posts" and their containing tables ──
    $headings = $xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::strong or self::b]', $node);
    foreach (iterator_to_array($headings) as $heading) {
        try {
            if (!$heading->parentNode)
                continue;
            $val = strtolower(trim($heading->textContent));
            if ($val === 'related posts' || $val === 'latest posts' || $val === 'recent posts') {
                $parent = $heading->parentNode;
                $tableFound = false;
                while ($parent && $parent->nodeName !== 'body') {
                    if ($parent->nodeName === 'table') {
                        if ($parent->parentNode) {
                            $parent->parentNode->removeChild($parent);
                            $tableFound = true;
                        }
                        break;
                    }
                    $parent = $parent->parentNode;
                }
                if (!$tableFound && $heading->parentNode) {
                    $container = $heading->parentNode;
                    if (in_array(strtolower($container->nodeName), ['div', 'td']) && $container->parentNode) {
                        $container->parentNode->removeChild($container);
                    } else {
                        $heading->parentNode->removeChild($heading);
                    }
                }
            }
        } catch (\Throwable $e) {
        }
    }
}

function cleanHtml($html)
{
    // Remove class, id, style, data-* attributes — keep only semantic HTML
    $html = preg_replace('/\s+class="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+id="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+style="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+data-[a-z\-]+="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+aria-[a-z\-]+="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+itemprop="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+itemscope\b/i', '', $html);
    $html = preg_replace('/\s+itemtype="[^"]*"/i', '', $html);

    // Remove lazy load attributes
    $html = preg_replace('/\s+data-src="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+data-srcset="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+loading="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+sizes="[^"]*"/i', '', $html);

    // Remove empty block tags
    $html = preg_replace('/<(p|div|span|li|td|th)[^>]*>\s*<\/\1>/i', '', $html);
    // Unwrap divs that only wrap a single table (e.g. table-container remnants)
    $html = preg_replace('/<div>\s*(<table[^>]*>.*?<\/table>)\s*<\/div>/si', '$1', $html);

    // Remove HTML comments (often contain ad markers)
    $html = preg_replace('/<!--.*?-->/s', '', $html);

    // Remove ad placeholder divs
    $html = preg_replace('/<div[^>]*><!--[^>]*--><ins[^>]*><\/ins><\/div>/is', '', $html);
    $html = preg_replace('/<ins[^>]*><\/ins>/is', '', $html);

    // Ensure tables have border for styling consistency
    $html = preg_replace('/<table(?![^>]*border)[^>]*>/i', '<table border="1" cellpadding="8" cellspacing="0">', $html);

    // Collapse multiple blank lines
    $html = preg_replace('/(\s*\n){3,}/', "\n\n", $html);

    // ── Remove promotional spam links and text ────────────────────────────────
    // Remove specific promotional links with their text

    // Remove ALL internal website links (competitor sites)
    $competitorDomains = [
        'sarkarijobfind\.com',
        'sarkariresult\.com',
        'sarkariexam\.com',
        'karnatakacareers\.org',
        'indgovtjobs\.net',
        'ka\.indgovtjobs\.net',
        'arattai',
        'freejobalert\.com',
        'employmentnews\.gov\.in',
        'pdfjobsjankari\.com',
        'sarkariexam\.com',
        'sarkarihelp\.com',
        'sarkari-naukri\.com',
        'sarkariresult\.com\.cm'
    ];

    foreach ($competitorDomains as $domain) {
        // Remove links to competitor domains
        $html = preg_replace('/<a[^>]*href="[^"]*' . $domain . '[^"]*"[^>]*>.*?<\/a>/is', '', $html);
    }

    // Remove internal category/tag/state links (keep only text)
    // Pattern: <a href="...organization/...", "...states/...", "...qualification/...">Text</a> -> Text
    $html = preg_replace('/<a[^>]*href="[^"]*(\/organization\/|\/states\/|\/qualification\/|\/category\/|\/tag\/)[^"]*"[^>]*>(.*?)<\/a>/is', '$2', $html);

    // Replace standalone text mentions with our brand
    $brandKeywords = [
        'sarkari result.com.cm',
        'sarkariresult.com.cm',
        'sarkari result.com',
        'sarkariresult.com',
        'sarkari result',
        'sarkariresult',
        'sarkari exam',
        'sarkariexam',
        'freejobalert.com',
        'freejobalert',
        'free job alert',
        'sarkari job find',
        'sarkarijobfind.com',
        'sarkarijobfind',
        'sarkari help',
        'sarkarihelp.com',
        'sarkarihelp',
        'sarkari naukri',
        'sarkari-naukri.com'
    ];
    $html = str_ireplace($brandKeywords, 'JobOne', $html);

    // Nuke promotional chatter texts entirely
    $html = str_ireplace('Join Arattai Channel:', '', $html);
    $html = str_ireplace('Join Arattai Channel', '', $html);
    $html = str_ireplace('Download Mobile App:', '', $html);
    $html = str_ireplace('Download Mobile App', '', $html);
    $html = preg_replace('/JobOne Test App For All Govt\. Exam Test/i', '', $html);
    $html = preg_replace('/Download JobOne Test App/i', '', $html);

    // Completely remove any anchor links to the Google Play Store
    $html = preg_replace('/<a[^>]*href="[^"]*play\.google\.com[^"]*"[^>]*>.*?<\/a>/is', '', $html);

    // Nuke any full table row that is just promoting an app download
    $html = preg_replace('/<tr[^>]*>(?:(?!<\/tr>).)*?(play\.google\.com|Test App|Mobile App)(?:(?!<\/tr>).)*?<\/tr>/is', '', $html);

    // Remove "IF You Satisfied By..." spam
    $html = preg_replace('/IF You Satisfied By\s+[A-Za-z0-9.]+\s+\(Website\).*?\(Thanks\)\.?/i', '', $html);

    // Remove standalone numbers (like 0 1 2 3 4 5 6 7 8 9 10)
    $html = preg_replace('/<p>\s*[\d\s]+\s*<\/p>/i', '', $html);
    $html = preg_replace('/^[\d\s]+$/m', '', $html);

    // Remove specific spam links if needed, but DO NOT remove "Click Here" as those are the actual action links!
    // Swap ALL social media / share links to JobOne standard channels
    $html = preg_replace('/href="[^"]*(telegram|t\.me)[^"]*"/i', 'href="https://t.me/jobone2026"', $html);
    $html = preg_replace('/href="[^"]*(whatsapp\.com|wa\.me)[^"]*"/i', 'href="https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22"', $html);

    // Strip remaining bare <div> / <span> wrappers that add no meaning
    $html = preg_replace('/<div>\s*<\/div>/i', '', $html);
    $html = preg_replace('/<span>\s*<\/span>/i', '', $html);

    // Clean up multiple spaces and empty lines
    $html = preg_replace('/\s+/m', ' ', $html);
    $html = preg_replace('/>\s+</m', '><', $html);

    // NOTE: Do NOT inject styles here - they will be added when publishing to API
    // The editor needs clean HTML without <style> tags

    return trim($html);
}

// ─── Premium inline styling for content rendered on jobone.in ─────────────────
function styleContent($html)
{
    // ── Prepend responsive CSS styles using a unique namespace ────────────
    $css = '<style>
    .jobone-premium-ui { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #334155; font-size: 16px; line-height: 1.7; }
    .jobone-premium-ui p { margin: 16px 0; word-break: break-word; }
    .jobone-premium-ui a { color: #2563eb; text-decoration: none; font-weight: 600; border-bottom: 2px solid #e2e8f0; transition: all 0.2s; }
    .jobone-premium-ui a:hover { color: #1d4ed8; border-color: #2563eb; }
    .jobone-premium-ui strong, .jobone-premium-ui b { color: #0f172a; font-weight: 700; }
    
    .jobone-premium-ui ul { padding: 0; margin: 20px 0; list-style: none; }
    .jobone-premium-ui li { position: relative; padding: 12px 16px; margin: 10px 0; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; align-items: start; }
    .jobone-premium-ui li span { margin-right: 10px; }
    
    .jobone-premium-ui ol { padding-left: 24px; margin: 20px 0; }
    .jobone-premium-ui ol li { padding: 8px 0; background: transparent; border: none; list-style-type: decimal; box-shadow: none; display: list-item; }
    
    /* Headings strictly professional unified palette */
    .jobone-premium-ui h2 { margin: 36px 0 20px; padding: 14px 20px; background: #eff6ff; border-left: 6px solid #2563eb; border-radius: 4px; color: #1e3a8a; font-size: 24px; font-weight: 800; }
    .jobone-premium-ui h3 { margin: 32px 0 16px; padding: 12px 18px; background: #f8fafc; border-left: 5px solid #3b82f6; border-radius: 4px; color: #1e40af; font-size: 21px; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .jobone-premium-ui h4 { margin: 24px 0 12px; padding: 10px 14px; background: #f8fafc; border-left: 4px solid #64748b; border-radius: 4px; color: #1e293b; font-size: 19px; font-weight: 700; }
    
    /* Tables */
    .jobone-table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 24px 0; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .jobone-premium-ui table { width: 100%; min-width: 500px; border-collapse: collapse; font-size: 15px; background: #ffffff; }
    .jobone-premium-ui th { background: #1e3a8a; color: #ffffff !important; font-weight: 700; padding: 16px; text-align: left; border-bottom: 3px solid #1e40af; }
    .jobone-premium-ui td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; line-height: 1.6; vertical-align: middle; }
    .jobone-premium-ui tr:last-child td { border-bottom: none; }
    .jobone-premium-ui tr:nth-child(even) td { background: #f9fafb; }
    .jobone-premium-ui tr:hover td { background: #f1f5f9; }

    /* Modern Color Boxes */
    .box-info, .box-success, .box-warning, .box-danger { margin: 24px 0; padding: 20px; border-radius: 12px; border: 1px solid; display: flex; align-items: start; }
    
    .box-info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
    .box-info::before { content: "ℹ️"; margin-right: 12px; font-size: 20px; }
    
    .box-success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .box-success::before { content: "✅"; margin-right: 12px; font-size: 20px; }
    
    .box-warning { background: #fffbeb; border-color: #fef3c7; color: #92400e; }
    .box-warning::before { content: "⚠️"; margin-right: 12px; font-size: 20px; }
    
    .box-danger { background: #fef2f2; border-color: #fee2e2; color: #991b1b; }
    .box-danger::before { content: "🚨"; margin-right: 12px; font-size: 20px; }

    /* Mobile view media queries */
    @media (max-width: 640px) {
        .jobone-premium-ui { font-size: 14px; }
        .jobone-premium-ui h2 { font-size: 18px; margin: 24px 0 12px; padding: 12px 14px; }
        .jobone-premium-ui h3 { font-size: 17px; margin: 20px 0 10px; padding: 10px 12px; }
        .jobone-premium-ui h4 { font-size: 16px; margin: 18px 0 8px; padding: 10px 12px; }
        .jobone-premium-ui table { font-size: 13px; min-width: 380px; }
        .jobone-premium-ui th { padding: 12px 10px; }
        .jobone-premium-ui td { padding: 10px 12px; }
    }
    </style>';

    // Wrap tables in responsive wrapper
    $html = preg_replace('/(<table[^>]*>)/i', '<div class="jobone-table-wrapper">$1', $html);
    $html = preg_replace('/(<\/table>)/i', '$1</div>', $html);

    // Provide the content wrapped in generic div
    return $css . '<div class="jobone-premium-ui">' . $html . '</div>';
}


// ─── Local Content Enhancement (Fallback when AI fails) ────────────────────────
function enhanceContentLocally($data)
{
    $title = $data['title'] ?? 'Government Job Notification';
    $shortDesc = $data['short_description'] ?? '';
    $content = $data['content'] ?? '';
    $lastDate = $data['last_date'] ?? '';
    $notificationDate = $data['notification_date'] ?? '';
    $totalPosts = $data['total_posts'] ?? null;
    
    // Clean the original content more aggressively
    $cleanContent = $content;
    
    // Remove all style and script tags
    $cleanContent = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $cleanContent);
    $cleanContent = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $cleanContent);
    
    // Remove all class and id attributes
    $cleanContent = preg_replace('/\s(class|id|style)="[^"]*"/i', '', $cleanContent);
    
    // Remove empty paragraphs and divs
    $cleanContent = preg_replace('/<p>\s*<\/p>/', '', $cleanContent);
    $cleanContent = preg_replace('/<div>\s*<\/div>/', '', $cleanContent);
    
    // Extract key information from content
    $eligibility = extractEligibilityInfo($cleanContent);
    $applicationProcess = extractApplicationProcess($cleanContent);
    $importantDates = extractImportantDates($cleanContent);
    
    // Build enhanced content with proper structure
    $enhancedContent = '<div class="jobone-premium-ui">';
    
    // Add main heading
    $enhancedContent .= '<h1>' . htmlspecialchars($title) . '</h1>';
    
    // Add summary box
    $enhancedContent .= '<div class="box-info">';
    $enhancedContent .= '<p><strong>📢 Important Notification:</strong> ' . htmlspecialchars($shortDesc) . '</p>';
    $enhancedContent .= '</div>';
    
    // Add key details table
    $enhancedContent .= '<h2>📅 Key Details</h2>';
    $enhancedContent .= '<div class="jobone-table-wrapper">';
    $enhancedContent .= '<table class="key-details-table">';
    $enhancedContent .= '<tr><th>Detail</th><th>Information</th></tr>';
    
    if ($notificationDate) {
        $enhancedContent .= '<tr><td>📅 Notification Date</td><td>' . htmlspecialchars($notificationDate) . '</td></tr>';
    }
    if ($lastDate) {
        $enhancedContent .= '<tr><td>⏰ Last Date to Apply</td><td>' . htmlspecialchars($lastDate) . '</td></tr>';
    }
    if ($totalPosts) {
        $enhancedContent .= '<tr><td>👥 Total Vacancies</td><td>' . htmlspecialchars($totalPosts) . ' Posts</td></tr>';
    }
    
    $enhancedContent .= '</table>';
    $enhancedContent .= '</div>';
    
    // Add eligibility section if found
    if (!empty($eligibility)) {
        $enhancedContent .= '<h2>🎯 Eligibility Criteria</h2>';
        $enhancedContent .= '<div class="box-info">';
        $enhancedContent .= $eligibility;
        $enhancedContent .= '</div>';
    }
    
    // Add application process section
    $enhancedContent .= '<h2>📝 Application Process</h2>';
    $enhancedContent .= '<div class="content-details">';
    
    if (!empty($applicationProcess)) {
        $enhancedContent .= $applicationProcess;
    } else {
        // Default application instructions
        $enhancedContent .= '<p>Interested and eligible candidates should apply online through the official website before the last date.</p>';
        $enhancedContent .= '<ol>';
        $enhancedContent .= '<li>Visit the official recruitment website</li>';
        $enhancedContent .= '<li>Register with valid email and mobile number</li>';
        $enhancedContent .= '<li>Fill the application form with correct details</li>';
        $enhancedContent .= '<li>Upload required documents (photo, signature, certificates)</li>';
        $enhancedContent .= '<li>Pay application fee if applicable</li>';
        $enhancedContent .= '<li>Submit and take printout for future reference</li>';
        $enhancedContent .= '</ol>';
    }
    $enhancedContent .= '</div>';
    
    // Add important dates section if found
    if (!empty($importantDates)) {
        $enhancedContent .= '<h2>📆 Important Dates</h2>';
        $enhancedContent .= '<div class="box-success">';
        $enhancedContent .= $importantDates;
        $enhancedContent .= '</div>';
    }
    
    // Add cleaned original content (truncated if too long)
    $enhancedContent .= '<h2>📋 Complete Notification Details</h2>';
    $enhancedContent .= '<div class="content-details">';
    
    // Limit original content to reasonable length
    if (strlen($cleanContent) > 5000) {
        $cleanContent = substr($cleanContent, 0, 5000) . '... [Content truncated for readability]';
    }
    
    $enhancedContent .= $cleanContent;
    $enhancedContent .= '</div>';
    
    // Add SEO footer
    $enhancedContent .= '<div class="seo-footer">';
    $enhancedContent .= '<p><strong>Tags:</strong> ' . htmlspecialchars($title) . ', Government Jobs, Sarkari Result, Latest Notification</p>';
    $enhancedContent .= '</div>';
    
    $enhancedContent .= '</div>'; // Close jobone-premium-ui
    
    $data['content'] = $enhancedContent;
    $data['ai_enhanced'] = true;
    $data['ai_provider'] = 'local_enhancer';
    
    return $data;
}

// Helper functions for local enhancement
function extractEligibilityInfo($content) {
    // Look for eligibility criteria in content
    $patterns = [
        '/eligibility.*?(?:<p>.*?<\/p>|<li>.*?<\/li>)/is',
        '/qualification.*?(?:<p>.*?<\/p>|<li>.*?<\/li>)/is',
        '/age limit.*?(?:<p>.*?<\/p>|<li>.*?<\/li>)/is'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }
    }
    
    return '';
}

function extractApplicationProcess($content) {
    // Look for application process in content
    $patterns = [
        '/how to apply.*?(?:<p>.*?<\/p>|<li>.*?<\/li>)/is',
        '/application process.*?(?:<p>.*?<\/p>|<li>.*?<\/li>)/is',
        '/apply online.*?(?:<p>.*?<\/p>|<li>.*?<\/li>)/is'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }
    }
    
    return '';
}

function extractImportantDates($content) {
    // Look for important dates in content
    $patterns = [
        '/important dates.*?(?:<p>.*?<\/p>|<li>.*?<\/li>)/is',
        '/schedule.*?(?:<p>.*?<\/p>|<li>.*?<\/li>)/is',
        '/date.*?(?:<p>.*?<\/p>|<li>.*?<\/li>)/is'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }
    }
    
    return '';
}

// ─── AI Auto-Generation ────────────────────────────────────────────────────────
function enrichWithAI($data)
{
    if (!defined('AI_ENHANCEMENT_ENABLED') || !AI_ENHANCEMENT_ENABLED) {
        return $data;
    }

    // Set a longer timeout for AI enhancement, especially for local LLM
    // Local LLM can be slow, so give it more time
    set_time_limit(240); // 240 seconds max for AI as requested
    
    try {
        $enhancer = new AIContentEnhancer();
        $enhanced = $enhancer->enhanceContent($data);
        
        // Map the modular field 'category' back to 'category_guess' for frontend compatibility
        if (isset($enhanced['category'])) {
            $enhanced['category_guess'] = $enhanced['category'];
        }
        if (isset($enhanced['state'])) {
            $enhanced['state_guess'] = $enhanced['state'];
        }

        return $enhanced;
    } catch (\Exception $e) {
        error_log("AI Enrichment failed: " . $e->getMessage());
        
        // Fallback to local enhancement
        error_log("Falling back to local content enhancement");
        $data['ai_error'] = $e->getMessage();
        $data['ai_enhanced'] = false;
        
        // Try local enhancement as fallback
        return enhanceContentLocally($data);
    }
}

// Clean AI-generated content to remove competitor links
function cleanAIContent($html)
{
    // Remove ALL competitor website links
    $competitorDomains = [
        'sarkarijobfind\.com',
        'sarkariresult\.com',
        'sarkariexam\.com',
        'karnatakacareers\.org',
        'indgovtjobs\.net',
        'ka\.indgovtjobs\.net',
        'arattai',
        'freejobalert\.com',
        'employmentnews\.gov\.in',
        'pdfjobsjankari\.com',
        'sarkariexam\.com',
        'sarkarihelp\.com',
        'sarkari-naukri\.com',
        'sarkariresult\.com\.cm'
    ];

    foreach ($competitorDomains as $domain) {
        // Remove entire <a> tag with competitor links, keep only the text
        $html = preg_replace('/<a[^>]*href="[^"]*' . $domain . '[^"]*"[^>]*>(.*?)<\/a>/is', '$1', $html);
    }

    // Remove internal category/tag/state/district/qualification links (keep only text)
    $html = preg_replace('/<a[^>]*href="[^"]*(\/organization\/|\/states\/|\/qualification\/|\/category\/|\/tag\/|\/districts\/|\/qualifications\/)[^"]*"[^>]*>(.*?)<\/a>/is', '$2', $html);

    // Replace standalone text mentions with our brand
    $brandKeywords = [
        'sarkari result.com.cm',
        'sarkariresult.com.cm',
        'sarkari result.com',
        'sarkariresult.com',
        'sarkari result',
        'sarkariresult',
        'sarkari exam',
        'sarkariexam',
        'freejobalert.com',
        'freejobalert',
        'free job alert',
        'sarkari job find',
        'sarkarijobfind.com',
        'sarkarijobfind',
        'sarkari help',
        'sarkarihelp.com',
        'sarkarihelp',
        'sarkari naukri',
        'sarkari-naukri.com'
    ];
    $html = str_ireplace($brandKeywords, 'JobOne', $html);

    // Nuke promotional chatter texts entirely
    $html = str_ireplace('Join Arattai Channel:', '', $html);
    $html = str_ireplace('Join Arattai Channel', '', $html);
    $html = str_ireplace('Download Mobile App:', '', $html);
    $html = str_ireplace('Download Mobile App', '', $html);

    // Remove type="qualification" or similar attributes
    $html = preg_replace('/\s+type="[^\"]*"/i', '', $html);

    return $html;
}

if (!defined('SCRAPE_INCLUDED') && (php_sapi_name() !== 'cli' || basename($_SERVER['PHP_SELF'] ?? '') === 'scrape.php')) {
    $result = fetchPage($url);
    if ($result['error']) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Could not fetch page: ' . $result['error']]);
        exit;
    }

    $html = $result['html'];
    if (!$html) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Empty response from URL.']);
        exit;
    }

    $extracted = extractData($html, $url);
    $extracted = enrichWithAI($extracted);
    $type = $forcedType ?: detectType($url, $extracted['title'], $html);
    $extracted['type'] = $type;

    if (empty($extracted['short_description'])) {
        $extracted['short_description'] = $extracted['title'] . ' — Notification, Eligibility, Application & Important Dates.';
    }

    if (empty($extracted['meta_title'])) {
        $rawMetaTitle = $extracted['title'];
        if (!str_contains(strtolower($rawMetaTitle), 'jobone')) {
            $rawMetaTitle .= ' | JobOne.in';
        }
        $extracted['meta_title'] = strlen($rawMetaTitle) > 60
            ? substr($rawMetaTitle, 0, 54) . '… | J'
            : $rawMetaTitle;
        $extracted['meta_title'] = substr($extracted['meta_title'], 0, 60);
    }

    if (empty($extracted['meta_description'])) {
        if (!empty($extracted['short_description'])) {
            $extracted['meta_description'] = substr($extracted['short_description'], 0, 160);
        }
    }

    if (empty($extracted['meta_keywords'])) {
        $extracted['meta_keywords'] = "govt jobs, latest recruitment, sarkari result, 2026 jobs, " . $extracted['title'];
    }

    if (defined('AUTO_ADD_SOCIAL_LINKS') && AUTO_ADD_SOCIAL_LINKS) {
        $socialHtml = '<div class="box-info" style="flex-direction: column; align-items: stretch; text-align: center;">';
        $socialHtml .= '<p><strong>🚀 Never Miss an Update!</strong> Join our community for instant 2026 job alerts directly on your phone.</p>';
        $socialHtml .= '<div style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; margin-top: 10px;">';
        if (defined('TELEGRAM_CHANNEL_URL') && !empty(TELEGRAM_CHANNEL_URL)) {
            $socialHtml .= '<a href="' . TELEGRAM_CHANNEL_URL . '" target="_blank" style="background: #229ED9; color: white !important; padding: 10px 20px; border-radius: 50px; text-decoration: none; border: none; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">✈️ Join Telegram</a>';
        }
        if (defined('WHATSAPP_CHANNEL_URL') && !empty(WHATSAPP_CHANNEL_URL)) {
            $socialHtml .= '<a href="' . WHATSAPP_CHANNEL_URL . '" target="_blank" style="background: #25D366; color: white !important; padding: 10px 20px; border-radius: 50px; text-decoration: none; border: none; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">💬 Follow WhatsApp</a>';
        }
        $socialHtml .= '</div>';
        $socialHtml .= '</div>';
        $extracted['content'] .= $socialHtml;
    }

    // Clear any captured output before sending JSON
    ob_end_clean();
    
    echo json_encode(['success' => true, 'data' => $extracted]);
}
