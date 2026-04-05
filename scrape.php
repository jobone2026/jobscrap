<?php
/**
 * scrape.php — Backend scraper + AI-style content extractor
 * Called via POST with JSON body: { "url": "https://...", "forced_type": "" }
 * Returns JSON with extracted post data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    define('JOBONE_API_TOKEN', 'YOUR_TOKEN_HERE');
    define('AGENTROUTER_API_KEY', 'YOUR_KEY_HERE');
}

define('API_BASE', 'https://jobone.in/api');
define('API_TOKEN', JOBONE_API_TOKEN);

$input = json_decode(file_get_contents('php://input'), true);
$url = trim($input['url'] ?? '');
$forcedType = trim($input['forced_type'] ?? '');

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid URL provided.']);
    exit;
}

// ─── 1. Fetch the page ───────────────────────────────────────────────────────
function fetchPage($url) {
    $ch = curl_init($url);
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
function detectType($url, $title, $html) {
    $haystack = strtolower($url . ' ' . $title . ' ' . substr($html, 0, 5000));
    $map = [
        'admit_card' => ['admit card', 'admit-card', 'admitcard', 'hall ticket', 'hall-ticket', 'call letter'],
        'answer_key' => ['answer key', 'answer-key', 'answerkey', 'answer sheet', 'official key'],
        'result'     => ['result', 'merit list', 'cutoff', 'cut-off', 'final result', 'scorecard'],
        'syllabus'   => ['syllabus', 'exam pattern', 'curriculum'],
        'job'        => ['recruitment', 'vacancy', 'notification', 'apply online', 'application', 'job'],
    ];
    foreach ($map as $type => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($haystack, $kw)) return $type;
        }
    }
    return 'job';
}

// ─── 3. Extract key data ─────────────────────────────────────────────────────
function extractData($html, $url) {
    libxml_use_internal_errors(true);
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

    return $data;
}

function cleanText($t) {
    $t = trim(preg_replace('/\s+/', ' ', $t));
    // Remove common site branding/spam from titles/descriptions
    $t = preg_replace('/(sarkari\s?job|sarkari\s?result|sarkari\s?exam|sarkarijobfind|sarkari\s?job\s?find|sarkariexam|sarkari\s?alert)/i', '', $t);
    return trim($t, " \t\n\r\0\x0B|,-");
}

function extractDate($text, array $labels) {
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

function normalizeDate($raw) {
    $raw = trim($raw);
    $ts = strtotime($raw);
    if ($ts && $ts > 0) {
        return date('Y-m-d', $ts);
    }
    return '';
}

function extractNumber($text, array $labels) {
    foreach ($labels as $label) {
        $pattern = '/' . preg_quote($label, '/') . '[^\d]{0,20}([\d,]+)/i';
        if (preg_match($pattern, $text, $m)) {
            return (int) str_replace(',', '', $m[1]);
        }
    }
    return null;
}

function extractLinks(DOMXPath $xpath, $baseUrl) {
    $links = [];
    $seen  = [];

    // Classes on ANY ancestor that disqualify a link as junk
    $badAncestors = [
        'wp-block-navigation', 'nav-', 'navigation', 'menu-',
        'sidebar', 'widget',
        'yarpp', 'related',
        'cj-widget', 'cj-posts',
        'gb-container', 'gb-inside',
        'author-bio', 'author-box',
        'button-container', 'games-button',
        'fja-alert', 'alert-widget',
        'comment', 'pagination', 'breadcrumb',
        'footer', 'header',
    ];

    // Generic link texts to skip (they give no useful title)
    $genericTexts = [
        'here', 'link', 'visit', 'read more', 'more', 'view', 'open', 'go', 'see', 'check',
        'download mobile app', 'mobile app', 'click here',
        'join arattai channel', 'arattai channel', 'arattai',
        'sarkari result', 'sarkarijobfind',
    ];

    // Keywords to match (in link text OR href OR row context)
    $keywords = [
        'apply', 'application', 'notification', 'advertisement', 'advt',
        'download', 'pdf', 'official', 'website', 'portal',
        'admit', 'hall ticket', 'result', 'merit list',
        'syllabus', 'exam pattern', 'vacancy', 'recruitment',
        'form', 'register', 'registration', 'login', 'apply link',
    ];

    $nodes = $xpath->query('//a[@href]');

    foreach ($nodes as $node) {
        $href = trim($node->getAttribute('href'));
        $text = cleanText($node->textContent);

        // Basic quality filters
        if (!$href || $href === '#' || str_starts_with($href, 'javascript:')) continue;
        if (!$text || strlen($text) > 160) continue;
        if (is_numeric($text)) continue;

        // Skip absolute social/junk domains
        $skipDomains = ['facebook.com', 'twitter.com', 'youtube.com', 'instagram.com', 'linkedin.com', 'play.google.com', 'apps.apple.com', 'sarkarijobfind.com', 'sarkariresult.com', 'arattai'];
        $skipThis = false;
        foreach ($skipDomains as $d) {
            if (str_contains($href, $d)) { $skipThis = true; break; }
        }
        if ($skipThis) continue;

        // Resolve absolute URL immediately safely
        $abs = $href;
        if (!str_starts_with($abs, 'http') && !str_starts_with($abs, '//')) {
            $parsed = parse_url($baseUrl);
            $root = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
            $abs = $root . '/' . ltrim($href, '/');
        }

        // Context search: find previous sibling text if this link is a placeholder like "Click Here"
        $row = $node->parentNode;
        while ($row && !in_array($row->nodeName, ['tr', 'p', 'div'])) {
            $row = $row->parentNode;
        }
        $rowText = $row ? strtolower($row->textContent) : '';

        if (in_array(strtolower($text), $genericTexts) || strtolower($text) === 'click here') {
            // Try to find a label in the row
            if ($row) {
                // If it's a table row, look at the first TD if we are in another TD
                $cells = $xpath->query('.//td|.//th', $row);
                if ($cells->length > 1) {
                    $potentialTitle = cleanText($cells->item(0)->textContent);
                    if (strlen($potentialTitle) > 3) $text = $potentialTitle;
                }
            }
        }

        // Final keyword match in title, URL, or surrounding row context
        $lower = strtolower($text . ' ' . $href . ' ' . $rowText);
        $matched = false;
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) { $matched = true; break; }
        }
        if (!$matched) continue;

        if (in_array($abs, $seen)) continue;

        $links[] = ['title' => $text, 'url' => $abs];
        $seen[]  = $abs;

        if (count($links) >= 10) break;
    }

    return $links;
}


function guessCategory($text) {
    $text = strtolower($text);
    $map = [
        ['name' => 'Banking',   'keywords' => ['bank', 'sbi', 'rbi', 'ibps', 'nabard', 'rrb bank', 'idbi']],
        ['name' => 'Railways',  'keywords' => ['railway', 'rrb', 'rlwl', 'indian rail', 'metro rail', 'ntpc', 'dfccil']],
        ['name' => 'UPSC',      'keywords' => ['upsc', 'civil service', 'ias ', 'ips ', 'ifs ', 'nda ', 'cds ']],
        ['name' => 'SSC',       'keywords' => ['ssc', 'staff selection', 'chsl', 'cgl', 'mts']],
        ['name' => 'Defence',   'keywords' => ['army', 'navy', 'airforce', 'air force', 'coast guard', 'defence', 'military', 'agniveer', 'drdo', 'beml', 'hal', 'bel']],
        ['name' => 'Police',    'keywords' => ['police', 'constable', 'sub-inspector', 'si ', 'crpf', 'cisf', 'bsf', 'ssc gd']],
        ['name' => 'State PSC', 'keywords' => ['psc', 'state public service', 'bpsc', 'mpsc', 'uppsc', 'rpsc', 'appsc', 'tnpsc', 'kpsc', 'hppsc']],
        ['name' => 'State Govt','keywords' => ['state govt', 'teacher', 'tet', 'ctet', 'high court', 'district court', 'panchayat']],
    ];
    foreach ($map as $cat) {
        foreach ($cat['keywords'] as $kw) {
            if (str_contains($text, $kw)) return $cat['name'];
        }
    }
    return ''; // empty means no guess, let user decide or leave blank
}

function guessState($text) {
    $text = strtolower($text);
    $states = [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 
        'Delhi', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 
        'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 
        'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 
        'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 
        'Uttarakhand', 'West Bengal'
    ];
    
    // First, look for "All India" level organizations
    $allIndiaKeywords = ['upsc', 'ssc', 'rrb', 'railway', 'bank', 'sbi', 'ibps', 'army', 'navy', 'airforce', 'drdo', 'aiims delhi'];
    foreach ($allIndiaKeywords as $kw) {
        if (str_contains($text, $kw)) return 'All India';
    }

    foreach ($states as $state) {
        if (str_contains($text, strtolower($state))) return $state;
    }
    return 'All India'; // default
}

function buildContent(DOMXPath $xpath, DOMDocument $doc, $pageUrl) {
    // Priority selectors — most specific content containers first
    $selectors = [
        '//*[contains(@class,"entry-content")]',
        '//*[contains(@class,"post-content")]',
        '//*[contains(@class,"article-content")]',
        '//*[contains(@class,"article-body")]',
        '//*[contains(@class,"post-body")]',
        '//*[contains(@class,"content-area")]',
        '//*[contains(@class,"single-content")]',
        '//*[@itemprop="articleBody"]',
        '//*[@itemprop="text"]',
        '//article',
        '//*[@role="main"]',
        '//main',
        '//*[@id="content"]',
        '//*[@id="main-content"]',
        '//body',
    ];

    $contentHtml = '';
    foreach ($selectors as $sel) {
        $nodes = $xpath->query($sel);
        if (!$nodes || $nodes->length === 0) continue;

        $node = $nodes->item(0);

        // Deep-clone so we don't mutate the real DOM across iterations
        $clone = $node->cloneNode(true);
        $cloneXpath = new DOMXPath($doc);

        removeUnwanted($cloneXpath, $clone, $doc);

        $inner = '';
        foreach ($clone->childNodes as $child) {
            $inner .= $doc->saveHTML($child);
        }
        $inner = cleanHtml($inner);

        if (strlen(strip_tags($inner)) > 300) {
            $contentHtml = $inner;
            break;
        }
    }

    if (!$contentHtml) {
        $contentHtml = '<p>Unable to extract content. Please paste content manually.</p>';
    }

    return $contentHtml;
}

function removeUnwanted(DOMXPath $xpath, DOMNode $node, DOMDocument $doc) {
    // ── HTML tags to nuke entirely ────────────────────────────────────
    $removeTags = [
        'script','style','nav','footer','header','noscript',
        'iframe','form','aside','button','svg','picture','figure',
        'video','audio','canvas','select','input','textarea',
    ];
    foreach ($removeTags as $tag) {
        $els = $xpath->query('.//' . $tag, $node);
        foreach (iterator_to_array($els) as $el) {
            $el->parentNode?->removeChild($el);
        }
    }

    // ── Class / ID patterns to remove ────────────────────────────────
    // These match elements that are NOT article content
    $junkPatterns = [
        // Social / share
        'facebook', 'twitter', 'share-btn',
        'social-', 'share-', 'animatic',
        // Ads / promos
        'ad-', '-ad-', 'ad_', 'banner', 'promo', 'sponsor',
        'google-preferred', 'dfp-', 'adsense',
        // Widgets / sidebars
        'widget', 'sidebar', 'related', 'yarpp', 'cj-widget',
        'fja-alert', 'alert-widget',
        // Navigation
        'breadcrumb', 'pagination', 'nav-', 'menu-',
        // Author / meta
        'author-bio', 'author-box', 'article-meta-bar', 'meta-bar',
        'byline', 'post-author',
        // Newsletter / subscription
        'newsletter', 'subscribe', 'signup', 'opt-in',
        // Comments
        'comment', 'disqus', 'respond',
        // Tags / categories
        'tag-cloud', 'post-tag', 'category-label',
        // Games / misc clutter
        'games-button', 'play-games', 'button-container',
        'gb-container', 'gb-inside',
        // FAQ sections added by CMS (not the actual content FAQ)
        'faq-section', 'faq-container',
        // Images / media wrappers (keep tables & text, not pictures)
        'picture_container', 'fl_defer', 'lazy',
    ];

    $allElements = $xpath->query('.//*[@class or @id]', $node);
    foreach (iterator_to_array($allElements) as $el) {
        $class = strtolower($el->getAttribute('class') . ' ' . $el->getAttribute('id'));
        foreach ($junkPatterns as $pattern) {
            if (str_contains($class, $pattern)) {
                $el->parentNode?->removeChild($el);
                break;
            }
        }
    }

    // Nuke specific rows / containers by text content safely
    $textNodes = $xpath->query('.//text()', $node);
    $nodesToDelete = [];
    foreach (iterator_to_array($textNodes) as $textNode) {
        $val = strtolower($textNode->nodeValue);
        if (str_contains($val, 'android app') || 
            str_contains($val, 'mobile app') ||
            str_contains($val, 'download mobile') ||
            str_contains($val, 'arattai channel') ||
            str_contains($val, 'join arattai') ||
            str_contains($val, 'sarkari result') ||
            str_contains($val, 'sarkarijobfind') || 
            str_contains($val, 'satisfied by')) {
            $nodesToDelete[] = $textNode;
        }
    }

    $deletedNodes = new SplObjectStorage();

    foreach ($nodesToDelete as $textNode) {
        // Skip if already deleted (no parent)
        if (!$textNode->parentNode || $deletedNodes->contains($textNode)) continue;

        $target = $textNode->parentNode;
        while ($target && !in_array($target->nodeName, ['tr', 'p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
            if ($target->nodeName === 'div') break; 
            if ($target->nodeName === 'body' || $target->nodeName === 'html') break;
            $target = $target->parentNode;
        }
        
        if ($target && $target->parentNode && !$deletedNodes->contains($target)) {
            if ($target->nodeName === 'div' && strlen($target->nodeValue) > 300) {
                $immediate = $textNode->parentNode;
                if ($immediate && $immediate->parentNode && !$deletedNodes->contains($immediate)) {
                    $immediate->parentNode->removeChild($immediate);
                    $deletedNodes->attach($immediate);
                }
            } else {
                $target->parentNode->removeChild($target);
                $deletedNodes->attach($target);
            }
        } elseif (!$deletedNodes->contains($textNode)) {
            $textNode->parentNode->removeChild($textNode);
            $deletedNodes->attach($textNode);
        }
    }
}

function cleanHtml($html) {
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
    $html = preg_replace('/<a[^>]*>.*?(Join Arattai Channel|Arattai Channel).*?<\/a>/is', '', $html);
    $html = preg_replace('/<a[^>]*>.*?(Sarkari Result).*?<\/a>/is', '', $html);
    $html = preg_replace('/<a[^>]*>.*?(Download Mobile App|Mobile App).*?<\/a>/is', '', $html);
    
    // Remove ALL internal website links (competitor sites)
    $competitorDomains = [
        'sarkarijobfind\.com',
        'sarkariresult\.com',
        'sarkariexam\.com',
        'karnatakacareers\.org',
        'arattai',
        'freejobalert\.com',
        'employmentnews\.gov\.in',
        'pdfjobsjankari\.com'
    ];
    
    foreach ($competitorDomains as $domain) {
        // Remove links to competitor domains
        $html = preg_replace('/<a[^>]*href="[^"]*' . $domain . '[^"]*"[^>]*>.*?<\/a>/is', '', $html);
    }
    
    // Remove internal category/tag/state links (keep only text)
    // Pattern: <a href="...organization/...", "...states/...", "...qualification/...">Text</a> -> Text
    $html = preg_replace('/<a[^>]*href="[^"]*(\/organization\/|\/states\/|\/qualification\/|\/category\/|\/tag\/)[^"]*"[^>]*>(.*?)<\/a>/is', '$2', $html);
    
    // Remove standalone text mentions
    $html = str_ireplace('SARKARIJOBFIND.COM', '', $html);
    $html = str_ireplace('SARKARIJOBFIND', '', $html);
    $html = str_ireplace('Join Arattai Channel:', '', $html);
    $html = str_ireplace('Sarkari Result:', '', $html);
    $html = str_ireplace('Download Mobile App:', '', $html);
    
    // Remove "IF You Satisfied By..." spam
    $html = preg_replace('/IF You Satisfied By\s+[A-Za-z0-9.]+\s+\(Website\).*?\(Thanks\)\.?/i', '', $html);
    
    // Remove standalone numbers (like 0 1 2 3 4 5 6 7 8 9 10)
    $html = preg_replace('/<p>\s*[\d\s]+\s*<\/p>/i', '', $html);
    $html = preg_replace('/^[\d\s]+$/m', '', $html);
    
    // Remove "Click Here" links that don't have meaningful context
    $html = preg_replace('/<a[^>]*>\s*Click Here\s*<\/a>/i', '', $html);

    // Swap ALL social media / share links to JobOne standard channels
    $html = preg_replace('/href="[^"]*(telegram|t\.me)[^"]*"/i', 'href="https://t.me/jobone2026"', $html);
    $html = preg_replace('/href="[^"]*(whatsapp\.com|wa\.me)[^"]*"/i', 'href="https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22"', $html);

    // Strip remaining bare <div> / <span> wrappers that add no meaning
    $html = preg_replace('/<div>\s*<\/div>/i', '', $html);
    $html = preg_replace('/<span>\s*<\/span>/i', '', $html);
    
    // Clean up multiple spaces and empty lines
    $html = preg_replace('/\s+/m', ' ', $html);
    $html = preg_replace('/>\s+</m', '><', $html);

    return trim($html);
}

// ─── Main execution ──────────────────────────────────────────────────────────
$result = fetchPage($url);
if ($result['error']) {
    echo json_encode(['success' => false, 'message' => 'Could not fetch page: ' . $result['error']]);
    exit;
}

$html = $result['html'];
if (!$html) {
    echo json_encode(['success' => false, 'message' => 'Empty response from URL.']);
    exit;
}

// ─── AI Auto-Generation ────────────────────────────────────────────────────────
function enrichWithAI($data) {
    // Check if AI enhancement is enabled
    if (!defined('AI_ENHANCEMENT_ENABLED') || !AI_ENHANCEMENT_ENABLED) {
        return $data; // Skip AI enhancement
    }
    
    $apiKey = AGENTROUTER_API_KEY;
    // Deepseek endpoint via AgentRouter
    $url = 'https://agentrouter.org/v1/chat/completions';

    // Strip too much raw text to avoid maxing out tokens
    $rawContent = strip_tags($data['content']);
    $rawContent = substr($rawContent, 0, 4000); 

    // Use custom system prompt from config or default
    $systemPrompt = defined('AI_SYSTEM_PROMPT') ? AI_SYSTEM_PROMPT : "You are an expert SEO copywriter and HTML formatter for a government job portal. Review the provided job details and return a strictly valid JSON object with:
1. \"title\": A concise SEO title (max 60 chars).
2. \"short_description\": A crisp summary of the job (max 150 chars).
3. \"content\": A rewritten, beautifully structured HTML version of the job details using <h3>, <p>, <ul>, <li>, and <table border=\"1\" cellpadding=\"8\" cellspacing=\"0\">. Make it professional, easy to read, and highlight important dates and vacancies.";

    // Add additional instructions if defined
    if (defined('AI_ADDITIONAL_INSTRUCTIONS') && AI_ADDITIONAL_INSTRUCTIONS) {
        $systemPrompt .= "\n\nAdditional Requirements:" . AI_ADDITIONAL_INSTRUCTIONS;
    }

    $userPrompt = "Job Info: \nTitle: {$data['title']}\nContent: {$rawContent}";

    // Get model and temperature from config or use defaults
    $model = defined('AI_MODEL') ? AI_MODEL : 'deepseek-v3.2';
    $temperature = defined('AI_TEMPERATURE') ? AI_TEMPERATURE : 0.3;

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => $temperature
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'Origin: https://agentrouter.org',
            'Referer: https://agentrouter.org/'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 45
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code == 200 && $response) {
        $json = json_decode($response, true);
        if (isset($json['choices'][0]['message']['content'])) {
            $aiData = json_decode($json['choices'][0]['message']['content'], true);
            if ($aiData) {
                if (!empty($aiData['title'])) $data['title'] = $aiData['title'];
                if (!empty($aiData['short_description'])) $data['short_description'] = $aiData['short_description'];
                if (!empty($aiData['content'])) $data['content'] = $aiData['content'];
                $data['ai_enhanced'] = true;
            }
        }
    } else {
        $data['ai_error'] = "HTTP $code - " . $response;
    }

    return $data;
}

$extracted = extractData($html, $url);

// Pass through AI enrichment
$extracted = enrichWithAI($extracted);

// ─── Add JobOne social media links at the end of content ──────────────────────
if (!defined('AUTO_ADD_SOCIAL_LINKS') || AUTO_ADD_SOCIAL_LINKS) {
    $telegramUrl = defined('TELEGRAM_CHANNEL_URL') ? TELEGRAM_CHANNEL_URL : 'https://t.me/jobone2026';
    $whatsappUrl = defined('WHATSAPP_CHANNEL_URL') ? WHATSAPP_CHANNEL_URL : 'https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22';
    
    $socialLinksHtml = '
<div style="margin-top: 30px; padding: 20px; background: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 8px;">
    <h3 style="color: #0369a1; margin-top: 0;">📢 Stay Updated with JobOne</h3>
    <p style="margin: 10px 0;">Join our channels for instant job notifications, admit cards, results & exam updates!</p>
    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;">
        <a href="' . htmlspecialchars($telegramUrl) . '" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; background: #0088cc; color: white; text-decoration: none; border-radius: 6px; font-weight: 500;">
            <span style="font-size: 20px;">📱</span>
            <span>Join Telegram Channel</span>
        </a>
        <a href="' . htmlspecialchars($whatsappUrl) . '" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; background: #25D366; color: white; text-decoration: none; border-radius: 6px; font-weight: 500;">
            <span style="font-size: 20px;">💬</span>
            <span>Join WhatsApp Channel</span>
        </a>
    </div>
</div>';

    // Append social links to content
    $extracted['content'] = $extracted['content'] . $socialLinksHtml;
}

$type = $forcedType ?: detectType($url, $extracted['title'], $html);
$extracted['type'] = $type;

// Auto-generate short_description if empty or AI failed
if (empty($extracted['short_description'])) {
    $extracted['short_description'] = $extracted['title'] . ' — Notification, Eligibility, Application & Important Dates.';
}

// Auto meta title — max 60 chars
$rawMetaTitle = $extracted['title'];
if (!str_contains(strtolower($rawMetaTitle), 'jobone')) {
    $rawMetaTitle .= ' | JobOne.in';
}
$extracted['meta_title'] = strlen($rawMetaTitle) > 60
    ? substr($rawMetaTitle, 0, 57 - 3) . '… | J'  
    : $rawMetaTitle;
$extracted['meta_title'] = substr($extracted['meta_title'], 0, 60);

// Auto meta description — max 160 chars
if (!empty($extracted['short_description'])) {
    $extracted['short_description_seo'] = substr($extracted['short_description'], 0, 160);
}

echo json_encode(['success' => true, 'data' => $extracted]);
