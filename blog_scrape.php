<?php
/**
 * blog_scrape.php — Blog Content Scraper with Images
 * Separate from scrape.php — designed for blog/article posts
 * Called via POST with JSON body: { "url": "https://..." }
 * Returns JSON with extracted blog data including images
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (file_exists('config.php')) {
    require_once 'config.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$url = trim($input['url'] ?? '');

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid URL provided.']);
    exit;
}

// ─── 1. Fetch the page ───────────────────────────────────────────────────────
function blogFetchPage($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: en-US,en;q=0.9,kn;q=0.8,hi;q=0.7',
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

// ─── 2. Resolve URL to absolute ──────────────────────────────────────────────
function resolveUrl($href, $baseUrl) {
    if (!$href || $href === '#' || str_starts_with($href, 'javascript:') || str_starts_with($href, 'data:')) {
        return '';
    }
    if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
        return $href;
    }
    if (str_starts_with($href, '//')) {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?? 'https';
        return $scheme . ':' . $href;
    }
    $parsed = parse_url($baseUrl);
    $root = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    if (str_starts_with($href, '/')) {
        return $root . $href;
    }
    // Relative path
    $basePath = $parsed['path'] ?? '/';
    $baseDir = substr($basePath, 0, strrpos($basePath, '/') + 1);
    return $root . $baseDir . $href;
}

// ─── 3. Extract all blog data ────────────────────────────────────────────────
function extractBlogData($html, $url) {
    libxml_use_internal_errors(true);

    // Pre-strip scripts, styles, noscript
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
    $html = preg_replace('/<noscript\b[^>]*>(.*?)<\/noscript>/is', '', $html);

    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    $xpath = new DOMXPath($doc);

    $data = [];

    // ── Title ──
    $titleNode = $xpath->query('//meta[@property="og:title"]/@content')->item(0)
        ?? $xpath->query('//title')->item(0)
        ?? $xpath->query('//h1')->item(0);
    $data['title'] = $titleNode ? blogCleanText($titleNode->nodeValue) : 'Untitled Post';

    // ── Description ──
    $descNode = $xpath->query('//meta[@property="og:description"]/@content')->item(0)
        ?? $xpath->query('//meta[@name="description"]/@content')->item(0);
    $data['short_description'] = $descNode ? blogCleanText($descNode->nodeValue) : '';

    // ── Featured Image (og:image) ──
    $ogImageNode = $xpath->query('//meta[@property="og:image"]/@content')->item(0);
    $data['featured_image'] = $ogImageNode ? resolveUrl(trim($ogImageNode->nodeValue), $url) : '';

    // ── Author ──
    $authorNode = $xpath->query('//meta[@name="author"]/@content')->item(0)
        ?? $xpath->query('//*[contains(@class,"author")]/a')->item(0)
        ?? $xpath->query('//*[contains(@class,"author")]')->item(0);
    $data['author'] = $authorNode ? blogCleanText($authorNode->nodeValue) : '';

    // ── Published Date ──
    $dateNode = $xpath->query('//meta[@property="article:published_time"]/@content')->item(0)
        ?? $xpath->query('//time[@datetime]/@datetime')->item(0)
        ?? $xpath->query('//*[contains(@class,"posted-on")]//time/@datetime')->item(0);
    $data['published_date'] = $dateNode ? trim($dateNode->nodeValue) : '';

    // ── Updated Date ──
    $updNode = $xpath->query('//meta[@property="article:modified_time"]/@content')->item(0)
        ?? $xpath->query('//*[contains(@class,"updated-on")]//time/@datetime')->item(0);
    $data['updated_date'] = $updNode ? trim($updNode->nodeValue) : '';

    // ── Meta Keywords ──
    $kwNode = $xpath->query('//meta[@name="keywords"]/@content')->item(0);
    $data['meta_keywords'] = $kwNode ? blogCleanText($kwNode->nodeValue) : '';

    // ── Extract ALL images from article content ──
    $data['images'] = extractBlogImages($xpath, $doc, $url);

    // ── Main Content with images preserved ──
    $data['content'] = buildBlogContent($xpath, $doc, $url);

    // ── Extract important links ──
    $data['important_links'] = extractBlogLinks($xpath, $url);

    // ── Extract FAQs ──
    $data['faqs'] = extractFAQs($xpath);

    // ── Type is always blog ──
    $data['type'] = 'blog';

    // ── Category guess ──
    $data['category_guess'] = blogGuessCategory($data['title'] . ' ' . $data['short_description']);

    // ── State guess ──
    $data['state_guess'] = blogGuessState($data['title'] . ' ' . $data['short_description']);

    return $data;
}

function blogCleanText($t) {
    $t = html_entity_decode((string)$t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_ireplace(['&amp;', '&amp;amp;'], '&', $t);
    $t = trim(preg_replace('/\s+/', ' ', $t));
    return trim($t, " \t\n\r\0\x0B|,-");
}

// ─── Extract images from article area ────────────────────────────────────────
function extractBlogImages(DOMXPath $xpath, DOMDocument $doc, $baseUrl) {
    $images = [];
    $seen = [];

    // Content area selectors (order of priority)
    $contentSelectors = [
        '//*[contains(@class,"entry-content")]',
        '//*[contains(@class,"post-content")]',
        '//*[contains(@class,"article-content")]',
        '//*[contains(@class,"article-body")]',
        '//article',
        '//*[@role="main"]',
        '//main',
        '//body',
    ];

    $scope = null;
    foreach ($contentSelectors as $sel) {
        $nodes = $xpath->query($sel);
        if ($nodes && $nodes->length > 0) {
            $scope = $nodes->item(0);
            break;
        }
    }
    if (!$scope) $scope = $doc->documentElement;

    // Find all img tags
    $imgNodes = $xpath->query('.//img', $scope);
    foreach ($imgNodes as $img) {
        $src = $img->getAttribute('src');
        // Try data-src (lazy loading) if src is placeholder
        if (!$src || str_contains($src, 'data:image') || str_contains($src, 'base64')) {
            $src = $img->getAttribute('data-src')
                ?: $img->getAttribute('data-lazy-src')
                ?: $img->getAttribute('data-original');
        }
        if (!$src) continue;

        $src = resolveUrl(trim($src), $baseUrl);
        if (!$src) continue;

        // Skip tiny icons, tracking pixels, avatars
        $width = (int)($img->getAttribute('width') ?: 0);
        $height = (int)($img->getAttribute('height') ?: 0);
        if (($width > 0 && $width < 50) || ($height > 0 && $height < 50)) continue;

        // Skip common junk patterns
        $srcLower = strtolower($src);
        if (str_contains($srcLower, 'gravatar.com')
            || str_contains($srcLower, 'pixel')
            || str_contains($srcLower, 'emoji')
            || str_contains($srcLower, 'smilies')
            || str_contains($srcLower, 'spinner')
            || str_contains($srcLower, 'loading')
            || str_contains($srcLower, 'avatar')
            || str_contains($srcLower, 'icon')
            || str_contains($srcLower, 'logo')
            || str_contains($srcLower, 'ad-')
            || str_contains($srcLower, 'ads/')
            || str_contains($srcLower, 'banner')
            || str_contains($srcLower, 'facebook.com')
            || str_contains($srcLower, 'twitter.com')
            || str_contains($srcLower, 'googleads')
        ) continue;

        // De-duplicate
        if (in_array($src, $seen)) continue;
        $seen[] = $src;

        $alt = blogCleanText($img->getAttribute('alt'));
        $title = blogCleanText($img->getAttribute('title') ?: '');

        // Determine srcset for best quality
        $srcset = $img->getAttribute('srcset') ?: $img->getAttribute('data-srcset');
        $bestSrc = $src;
        if ($srcset) {
            $bestSrc = getBestSrcsetUrl($srcset, $baseUrl);
            if (!$bestSrc) $bestSrc = $src;
        }

        $images[] = [
            'src' => $bestSrc,
            'alt' => $alt,
            'title' => $title,
            'width' => $width ?: null,
            'height' => $height ?: null,
        ];
    }

    return $images;
}

function getBestSrcsetUrl($srcset, $baseUrl) {
    $candidates = [];
    $parts = explode(',', $srcset);
    foreach ($parts as $part) {
        $tokens = preg_split('/\s+/', trim($part));
        if (count($tokens) >= 1) {
            $url = resolveUrl(trim($tokens[0]), $baseUrl);
            $descriptor = isset($tokens[1]) ? trim($tokens[1]) : '1x';
            // Parse width descriptor (e.g. 800w)
            $weight = 0;
            if (str_ends_with($descriptor, 'w')) {
                $weight = (int) $descriptor;
            } elseif (str_ends_with($descriptor, 'x')) {
                $weight = (float) $descriptor * 1000;
            }
            if ($url) {
                $candidates[] = ['url' => $url, 'weight' => $weight];
            }
        }
    }
    if (empty($candidates)) return '';
    // Sort by weight descending — pick the largest
    usort($candidates, fn($a, $b) => $b['weight'] <=> $a['weight']);
    return $candidates[0]['url'];
}

// ─── Build blog content preserving images ────────────────────────────────────
function buildBlogContent(DOMXPath $xpath, DOMDocument $doc, $pageUrl) {
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
        '//body',
    ];

    $contentHtml = '';
    foreach ($selectors as $sel) {
        $nodes = $xpath->query($sel);
        if (!$nodes || $nodes->length === 0) continue;

        $node = $nodes->item(0);
        $clone = $node->cloneNode(true);

        $cloneDoc = new DOMDocument();
        $cloneDoc->appendChild($cloneDoc->importNode($clone, true));
        $cloneXpath = new DOMXPath($cloneDoc);

        blogRemoveUnwanted($cloneXpath, $cloneDoc->documentElement, $cloneDoc);

        // Now fix image URLs to absolute
        fixImageUrls($cloneXpath, $cloneDoc, $pageUrl);

        $inner = '';
        foreach ($cloneDoc->documentElement->childNodes as $child) {
            $inner .= $cloneDoc->saveHTML($child);
        }
        // Decode HTML entities back to UTF-8 (DOMDocument encodes non-ASCII chars)
        $inner = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $inner = blogCleanHtml($inner, $pageUrl);

        $textLength = strlen(strip_tags($inner));
        if ($textLength > 100) {
            $contentHtml = $inner;
            break;
        }
    }

    if (!$contentHtml) {
        $contentHtml = '<p>Unable to extract content. Please paste content manually.</p>';
    }

    return $contentHtml;
}

function fixImageUrls(DOMXPath $xpath, DOMDocument $doc, $baseUrl) {
    $imgs = $xpath->query('.//img');
    foreach ($imgs as $img) {
        $src = $img->getAttribute('src');
        if (!$src || str_contains($src, 'data:image')) {
            $src = $img->getAttribute('data-src')
                ?: $img->getAttribute('data-lazy-src')
                ?: $img->getAttribute('data-original');
        }
        if ($src) {
            $abs = resolveUrl(trim($src), $baseUrl);
            if ($abs) {
                $img->setAttribute('src', $abs);
            }
        }
        // Also get best srcset
        $srcset = $img->getAttribute('srcset') ?: $img->getAttribute('data-srcset');
        if ($srcset) {
            $best = getBestSrcsetUrl($srcset, $baseUrl);
            if ($best) {
                $img->setAttribute('src', $best);
            }
        }
        // Remove lazy-load attributes
        $img->removeAttribute('data-src');
        $img->removeAttribute('data-lazy-src');
        $img->removeAttribute('data-original');
        $img->removeAttribute('data-srcset');
        $img->removeAttribute('srcset');
        $img->removeAttribute('sizes');
        $img->removeAttribute('loading');
    }
}

function blogRemoveUnwanted(DOMXPath $xpath, DOMNode $node, DOMDocument $doc) {
    // HTML tags to remove — but KEEP img, figure, picture, figcaption
    $removeTags = [
        'script', 'style', 'nav', 'footer', 'header', 'noscript',
        'iframe', 'form', 'aside', 'button', 'svg',
        'video', 'audio', 'canvas', 'select', 'input', 'textarea',
    ];
    foreach ($removeTags as $tag) {
        $els = $xpath->query('.//' . $tag, $node);
        foreach (iterator_to_array($els) as $el) {
            if ($el->parentNode) {
                $el->parentNode->removeChild($el);
            }
        }
    }

    // Junk class/id patterns
    $junkPatterns = [
        'facebook', 'twitter', 'share-btn', 'social-', 'share-',
        'ad-', '-ad-', 'ad_', 'banner', 'promo', 'sponsor',
        'google-preferred', 'dfp-', 'adsense',
        'widget', 'sidebar', 'yarpp', 'cj-widget',
        'breadcrumb', 'pagination', 'nav-', 'menu-',
        'author-bio', 'author-box', 'byline', 'post-author',
        'newsletter', 'subscribe', 'signup', 'opt-in',
        'comment', 'disqus', 'respond',
        'games-button', 'play-games', 'button-container',
        'jp-relatedposts', 'related-posts', 'sharedaddy',
        'wp-block-embed',
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

    // Remove spam text nodes
    $textNodes = $xpath->query('.//text()', $node);
    $nodesToDelete = [];
    foreach (iterator_to_array($textNodes) as $textNode) {
        $val = strtolower($textNode->nodeValue);
        if (str_contains($val, 'android app')
            || str_contains($val, 'download mobile')
            || str_contains($val, 'join our channel')
            || str_contains($val, 'join telegram')
            || str_contains($val, 'join whatsapp')
        ) {
            $nodesToDelete[] = $textNode;
        }
    }
    foreach ($nodesToDelete as $textNode) {
        if (!$textNode->parentNode) continue;
        $target = $textNode->parentNode;
        if ($target && in_array(strtolower($target->nodeName), ['p', 'span', 'a', 'li', 'div']) && $target->parentNode) {
            try { $target->parentNode->removeChild($target); } catch (\DOMException $e) {}
        }
    }

    // Remove "Related Posts" headings and their containers
    $headings = $xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::strong]', $node);
    foreach (iterator_to_array($headings) as $heading) {
        try {
            if (!$heading->parentNode) continue;
            $val = strtolower(trim($heading->textContent));
            if ($val === 'related posts' || $val === 'latest posts' || $val === 'recent posts' || $val === 'you may also like') {
                $parent = $heading->parentNode;
                while ($parent && $parent->nodeName !== 'body') {
                    if (in_array($parent->nodeName, ['div', 'section', 'aside'])) {
                        if ($parent->parentNode) { $parent->parentNode->removeChild($parent); }
                        break;
                    }
                    $parent = $parent->parentNode;
                }
            }
        } catch (\Throwable $e) {}
    }
}

function blogCleanHtml($html, $pageUrl = '') {
    // Remove class, id, style, data-* attributes but keep img attributes
    $html = preg_replace('/\s+class="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+id="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+style="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+data-[a-z\-]+="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+aria-[a-z\-]+="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+itemprop="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+itemscope\b/i', '', $html);
    $html = preg_replace('/\s+itemtype="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+role="[^"]*"/i', '', $html);

    // Remove empty block tags (but NOT img which is self-closing)
    $html = preg_replace('/<(p|div|span|li|td|th)[^>]*>\s*<\/\1>/i', '', $html);
    $html = preg_replace('/<div>\s*<\/div>/i', '', $html);
    $html = preg_replace('/<span>\s*<\/span>/i', '', $html);

    // Remove HTML comments
    $html = preg_replace('/<!--.*?-->/s', '', $html);

    // Remove ad placeholders
    $html = preg_replace('/<ins[^>]*><\/ins>/is', '', $html);

    // Ensure tables have border for styling consistency
    $html = preg_replace('/<table(?![^>]*border)[^>]*>/i', '<table border="1" cellpadding="8" cellspacing="0">', $html);

    // Fix image tags — make sure they are properly self-closing and styled
    $html = preg_replace('/<img([^>]*)>/i', '<img$1 style="max-width:100%;height:auto;border-radius:8px;margin:16px 0;display:block;" />', $html);

    // Remove promotional spam texts
    $sourceHost = parse_url($pageUrl, PHP_URL_HOST) ?? '';
    $siteName = preg_replace('/^www\./', '', $sourceHost);
    if ($siteName) {
        // Remove source site name mentions
        $html = str_ireplace($siteName, 'JobOne', $html);
    }

    // Brand replacements
    $brandKeywords = [
        'karnatakahelp.in', 'karnatakahelp',
        'sarkari result', 'sarkariresult',
        'sarkari exam', 'sarkariexam',
        'freejobalert', 'free job alert',
    ];
    $html = str_ireplace($brandKeywords, 'JobOne', $html);

    // Collapse whitespace
    $html = preg_replace('/\s+/m', ' ', $html);
    $html = preg_replace('/>\s+</m', '><', $html);

    // Apply premium blog styling
    $html = blogStyleContent($html);

    return trim($html);
}

// ─── Extract important links ─────────────────────────────────────────────────
function extractBlogLinks(DOMXPath $xpath, $baseUrl) {
    $links = [];
    $seen = [];

    $keywords = [
        'apply', 'application', 'official', 'download', 'pdf',
        'notification', 'form', 'register', 'portal', 'website',
        'click', 'important', 'direct link', 'seva sindhu',
    ];

    $skipDomains = [
        'facebook.com', 'twitter.com', 'youtube.com', 'instagram.com',
        'linkedin.com', 'play.google.com', 'apps.apple.com',
        'gravatar.com', 'wp.com',
    ];

    $nodes = $xpath->query('//a[@href]');
    foreach ($nodes as $node) {
        $href = trim($node->getAttribute('href'));
        $text = blogCleanText($node->textContent);

        if (!$href || $href === '#' || str_starts_with($href, 'javascript:')) continue;
        if (!$text || strlen($text) > 160 || strlen($text) < 2) continue;
        if (is_numeric($text)) continue;

        $abs = resolveUrl($href, $baseUrl);
        if (!$abs) continue;

        // Skip junk domains
        $skipThis = false;
        $absLower = strtolower($abs);
        foreach ($skipDomains as $d) {
            if (str_contains($absLower, $d)) { $skipThis = true; break; }
        }
        if ($skipThis) continue;

        // Handle social media — replace with JobOne channels
        if (str_contains($absLower, 't.me') || str_contains($absLower, 'telegram')) {
            $replacement = ['title' => 'Join Telegram Channel', 'url' => 'https://t.me/jobone2026'];
            if (!in_array($replacement['url'], $seen)) {
                $links[] = $replacement;
                $seen[] = $replacement['url'];
            }
            continue;
        }
        if (str_contains($absLower, 'whatsapp.com') || str_contains($absLower, 'wa.me')) {
            $replacement = ['title' => 'Join WhatsApp Channel', 'url' => 'https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22'];
            if (!in_array($replacement['url'], $seen)) {
                $links[] = $replacement;
                $seen[] = $replacement['url'];
            }
            continue;
        }

        // Match only relevant links
        $lower = strtolower($text . ' ' . $abs);
        $matched = false;
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) { $matched = true; break; }
        }
        // Also match PDF files and .nic.in domains (government sites)
        if (!$matched && (str_contains($absLower, '.pdf') || str_contains($absLower, '.nic.in') || str_contains($absLower, '.gov.in'))) {
            $matched = true;
        }
        if (!$matched) continue;

        $uniqueKey = $abs;
        if (in_array($uniqueKey, $seen)) continue;

        // Improve generic link text
        if (in_array(strtolower($text), ['click here', 'here', 'link', 'ಇಲ್ಲಿ ಕ್ಲಿಕ್ ಮಾಡಿ'])) {
            if (str_contains($absLower, '.pdf')) {
                $text = 'Download PDF';
            } elseif (str_contains($absLower, '.nic.in')) {
                $host = parse_url($abs, PHP_URL_HOST) ?: 'Official Website';
                $text = 'Visit ' . $host;
            } else {
                $text = 'Visit Link';
            }
        }

        $links[] = ['title' => $text, 'url' => $abs];
        $seen[] = $uniqueKey;

        if (count($links) >= 15) break;
    }

    return $links;
}

// ─── Extract FAQs ────────────────────────────────────────────────────────────
function extractFAQs(DOMXPath $xpath) {
    $faqs = [];

    // Method 1: Schema.org FAQPage
    $faqNodes = $xpath->query('//*[@itemtype="https://schema.org/FAQPage"]//*[@itemprop="mainEntity"]');
    if ($faqNodes && $faqNodes->length > 0) {
        foreach ($faqNodes as $faqNode) {
            $q = $xpath->query('.//*[@itemprop="name"]', $faqNode)->item(0);
            $a = $xpath->query('.//*[@itemprop="acceptedAnswer"]//*[@itemprop="text"]', $faqNode)->item(0);
            if ($q && $a) {
                $faqs[] = [
                    'question' => blogCleanText($q->textContent),
                    'answer' => blogCleanText($a->textContent),
                ];
            }
        }
        if (!empty($faqs)) return $faqs;
    }

    // Method 2: Look for heading "FAQ" or "FAQs" followed by Q&A patterns
    $headings = $xpath->query('//h2|//h3|//h4');
    $inFaq = false;
    foreach ($headings as $heading) {
        $text = strtolower(trim($heading->textContent));
        if (str_contains($text, 'faq') || str_contains($text, 'frequently asked')) {
            $inFaq = true;
            continue;
        }
        if ($inFaq) {
            $question = blogCleanText($heading->textContent);
            if (!$question || strlen($question) < 5) continue;

            // Get next sibling paragraph as answer
            $next = $heading->nextSibling;
            $answer = '';
            while ($next) {
                if ($next->nodeType === XML_ELEMENT_NODE) {
                    $nodeName = strtolower($next->nodeName);
                    if (in_array($nodeName, ['h1', 'h2', 'h3', 'h4'])) break;
                    if (in_array($nodeName, ['p', 'div', 'ul', 'ol'])) {
                        $answer .= blogCleanText($next->textContent) . ' ';
                    }
                }
                $next = $next->nextSibling;
            }
            $answer = trim($answer);
            if ($answer) {
                $faqs[] = ['question' => $question, 'answer' => $answer];
            }
        }
    }

    return $faqs;
}

// ─── Category guesser ────────────────────────────────────────────────────────
function blogGuessCategory($text) {
    $text = strtolower($text);
    $map = [
        ['name' => 'Government Schemes', 'keywords' => ['scheme', 'yojana', 'ಯೋಜನೆ', 'subsidy', 'free distribution', 'uchita', 'ಉಚಿತ', 'sewing machine', 'ration', 'pension']],
        ['name' => 'Education', 'keywords' => ['scholarship', 'education', 'exam', 'university', 'vidyarthi', 'school', 'college']],
        ['name' => 'Agriculture', 'keywords' => ['farm', 'agriculture', 'kisan', 'crop', 'ಕೃಷಿ']],
        ['name' => 'Health', 'keywords' => ['health', 'hospital', 'medical', 'ayushman', 'ārogya']],
        ['name' => 'Employment', 'keywords' => ['job', 'recruitment', 'vacancy', 'employment', 'ಉದ್ಯೋಗ']],
        ['name' => 'Finance', 'keywords' => ['loan', 'bank', 'finance', 'mudra', 'credit']],
    ];
    foreach ($map as $cat) {
        foreach ($cat['keywords'] as $kw) {
            if (str_contains($text, $kw)) return $cat['name'];
        }
    }
    return 'General';
}

function blogGuessState($text) {
    $text = strtolower($text);
    $states = [
        'Karnataka' => ['karnataka', 'ಕರ್ನಾಟಕ', 'bengaluru', 'bangalore', 'mysore', 'mysuru', 'hubli'],
        'Tamil Nadu' => ['tamil nadu', 'chennai'],
        'Kerala' => ['kerala', 'thiruvananthapuram'],
        'Andhra Pradesh' => ['andhra pradesh', 'hyderabad'],
        'Telangana' => ['telangana'],
        'Maharashtra' => ['maharashtra', 'mumbai', 'pune'],
        'Uttar Pradesh' => ['uttar pradesh', 'lucknow'],
        'Bihar' => ['bihar', 'patna'],
        'Rajasthan' => ['rajasthan', 'jaipur'],
    ];
    foreach ($states as $state => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) return $state;
        }
    }
    return 'All India';
}

// ─── Premium blog styling ────────────────────────────────────────────────────
function blogStyleContent($html) {
    $css = '<style>
    .jobone-blog-ui { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1e293b; font-size: 17px; line-height: 1.8; }
    .jobone-blog-ui p { margin: 14px 0; word-break: break-word; }
    .jobone-blog-ui a { color: #2563eb; text-decoration: none; font-weight: 600; border-bottom: 1px dashed #93c5fd; transition: color 0.2s; }
    .jobone-blog-ui a:hover { color: #1d4ed8; }
    .jobone-blog-ui strong, .jobone-blog-ui b { color: #0f172a; font-weight: 700; }
    .jobone-blog-ui img { max-width: 100%; height: auto; border-radius: 10px; margin: 20px auto; display: block; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }

    .jobone-blog-ui ul { padding: 0 0 0 24px; margin: 16px 0; list-style: none; }
    .jobone-blog-ui li { position: relative; padding: 10px 14px 10px 28px; margin: 8px 0; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 8px; border: 1px solid #e2e8f0; }
    .jobone-blog-ui li::before { content: "✔"; color: #10b981; font-weight: bold; position: absolute; left: 10px; top: 10px; }
    .jobone-blog-ui ol { padding-left: 24px; margin: 16px 0; }
    .jobone-blog-ui ol li { padding: 8px 0; background: transparent; border: none; list-style-type: decimal; }
    .jobone-blog-ui ol li::before { display: none; }

    .jobone-blog-ui h2 { margin: 36px 0 18px; padding: 16px 20px; background: linear-gradient(135deg, #eff6ff, #e0e7ff); border-left: 5px solid #2563eb; border-radius: 0 10px 10px 0; color: #1e3a8a; font-size: 24px; font-weight: 800; }
    .jobone-blog-ui h3 { margin: 30px 0 15px; padding: 14px 18px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-left: 4px solid #475569; border-radius: 0 8px 8px 0; color: #0f172a; font-size: 21px; font-weight: 700; }
    .jobone-blog-ui h4 { margin: 26px 0 12px; padding: 12px 16px; background: #f8fafc; border-left: 4px solid #64748b; border-radius: 0 8px 8px 0; color: #1e293b; font-size: 19px; font-weight: 700; }

    .jobone-blog-table-wrap { width: 100%; overflow-x: auto; margin: 24px 0; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .jobone-blog-ui table { width: 100%; min-width: 480px; border-collapse: collapse; font-size: 15px; }
    .jobone-blog-ui th { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #ffffff !important; font-weight: 700; padding: 14px 16px; text-align: center; border: none; }
    .jobone-blog-ui th * { color: inherit !important; }
    .jobone-blog-ui td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; line-height: 1.6; vertical-align: top; }
    .jobone-blog-ui tr:nth-child(even) td { background: #f8fafc; }
    .jobone-blog-ui tr:nth-child(odd) td { background: #ffffff; }
    .jobone-blog-ui tr:hover td { background: #eff6ff; }

    .jobone-blog-ui figure { margin: 24px 0; text-align: center; }
    .jobone-blog-ui figcaption { font-size: 14px; color: #64748b; margin-top: 8px; font-style: italic; }

    .jobone-blog-ui blockquote { margin: 20px 0; padding: 16px 20px; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; font-style: italic; color: #78350f; }

    @media (max-width: 640px) {
        .jobone-blog-ui { font-size: 15px; }
        .jobone-blog-ui h2 { font-size: 20px; padding: 12px 14px; }
        .jobone-blog-ui h3 { font-size: 18px; padding: 10px 12px; }
        .jobone-blog-ui h4 { font-size: 17px; padding: 10px 12px; }
        .jobone-blog-ui table { font-size: 13px; min-width: 340px; }
        .jobone-blog-ui th { padding: 10px 8px; }
        .jobone-blog-ui td { padding: 8px 10px; }
    }
    </style>';

    // Wrap tables in responsive wrapper
    $html = preg_replace('/(<table[^>]*>)/i', '<div class="jobone-blog-table-wrap">$1', $html);
    $html = preg_replace('/(<\/table>)/i', '$1</div>', $html);

    return $css . '<div class="jobone-blog-ui">' . $html . '</div>';
}

// ─── AI Enhancement for blog content ─────────────────────────────────────────
function blogEnrichWithAI($data) {
    if (!defined('AI_ENHANCEMENT_ENABLED') || !AI_ENHANCEMENT_ENABLED) {
        return $data;
    }
    if (!defined('AGENTROUTER_API_KEY') || !AGENTROUTER_API_KEY) {
        return $data;
    }

    $apiKey = AGENTROUTER_API_KEY;
    $url = 'https://agentrouter.org/v1/chat/completions';

    // For blog, preserve img tags
    $rawContent = strip_tags($data['content'], '<table><tr><td><th><thead><tbody><h2><h3><h4><h5><ul><li><ol><p><br><img><figure><figcaption><a><strong><em><blockquote>');
    $rawContent = substr($rawContent, 0, 25000);

    $systemPrompt = "You are an expert blog content formatter for JobOne.in portal. You rewrite blog articles into beautifully structured, SEO-optimized HTML while PRESERVING all existing <img> tags exactly as they are.

STRICT RULES:
1. Keep ALL <img> tags with their src, alt attributes - DO NOT REMOVE any images
2. Rewrite text for better readability and SEO
3. Use proper headings (h2, h3, h4), paragraphs, lists, tables
4. REMOVE ALL promotional content, source website names, external channel links
5. REMOVE comments section content
6. Add proper section breaks and formatting
7. Keep the content factual and informative
8. If content is in regional language (Kannada/Hindi etc), keep it in the same language but improve formatting

Return strictly valid JSON with:
1. \"title\": Clean SEO title (max 60 chars)
2. \"short_description\": Summary (max 160 chars)
3. \"content\": Beautifully formatted HTML with all images preserved
4. \"meta_keywords\": Comma-separated SEO keywords (max 500 chars)";

    $userPrompt = "Blog Article:\nTitle: {$data['title']}\nContent:\n{$rawContent}";

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
        CURLOPT_TIMEOUT => 60
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
                if (!empty($aiData['content'])) {
                    // Re-apply blog styling to AI content
                    $data['content'] = blogStyleContent($aiData['content']);
                }
                if (!empty($aiData['meta_keywords'])) $data['meta_keywords'] = $aiData['meta_keywords'];
                $data['ai_enhanced'] = true;
            }
        }
    } else {
        $data['ai_error'] = "HTTP $code";
    }

    return $data;
}

// ─── MAIN EXECUTION ──────────────────────────────────────────────────────────
$result = blogFetchPage($url);
if ($result['error']) {
    echo json_encode(['success' => false, 'message' => 'Could not fetch page: ' . $result['error']]);
    exit;
}

$html = $result['html'];
if (!$html) {
    echo json_encode(['success' => false, 'message' => 'Empty response from URL.']);
    exit;
}

$extracted = extractBlogData($html, $url);

// AI enhancement
$extracted = blogEnrichWithAI($extracted);

// Auto meta title
$rawMetaTitle = $extracted['title'];
if (!str_contains(strtolower($rawMetaTitle), 'jobone')) {
    $rawMetaTitle .= ' | JobOne.in';
}
$extracted['meta_title'] = substr($rawMetaTitle, 0, 60);

// Auto meta description
if (!empty($extracted['short_description'])) {
    $extracted['short_description_seo'] = substr($extracted['short_description'], 0, 160);
}

echo json_encode(['success' => true, 'data' => $extracted], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
