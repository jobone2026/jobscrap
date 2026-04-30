<?php
// â”€â”€ JobOne Publisher — PHP API Proxy â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// FIX #1: Output buffering — prevents ANY stray PHP warning/notice from
//          corrupting the JSON response ("Unexpected end of JSON input")
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(204);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(1200);
ini_set('memory_limit', '512M');

if (!function_exists('curl_init')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'PHP cURL extension is not installed or enabled.']);
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â”€â”€ CONSTANTS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// FIX #1 continued: exception/error handlers must flush buffer before outputting
set_exception_handler(function ($e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Uncaught Exception: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity))
        return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Load secrets from Laravel .env file if available (Live server: /var/www/jobone/.env)
$envPath = __DIR__ . '/../../.env';
$env = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $env[trim($name)] = trim(trim($value), '"\'');
        }
    }
}

// If running locally, check for a local config.php
if (file_exists(__DIR__ . '/config.php')) {
    $localConfig = include __DIR__ . '/config.php';
    if (is_array($localConfig)) {
        $env = array_merge($env, $localConfig);
    }
}

define('JOBONE_API', 'https://jobone.in/api');
define('JOBONE_TOKEN', $env['JOBONE_TOKEN'] ?? 'your_jobone_token_here');
define('JOBONE_SITE_URL', 'https://jobone.in');
define('JOBONE_SITE_NAME', 'JobOne.in');

define('AI_MODEL', 'gpt-4.1-mini');
define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('AI_API_KEY', $env['OPENAI_API_KEY'] ?? 'your_openai_key_here');

define('TG_CHANNEL', 'https://t.me/jobone2026');
define('WA_CHANNEL', 'https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22');

define('INDEXNOW_KEY', 'YOUR_32CHAR_GUID_KEY_HERE');
define('INDEXNOW_HOST', 'jobone.in');

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â”€â”€ DOMAIN CLASSIFIER â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

const AGGREGATOR_DOMAINS = [
    'sarkarijobfind.com',
    'freejobalert.com',
    'sarkariresult.com',
    'rojgarresult.com',
    'naukri.com',
    'indeed.com',
    'shine.com',
    'monsterindia.com',
    'timesjobs.com',
    'sarkariexam.com',
    'sarkariresults.in',
    'sarkarinaukri.com',
    'govtjobsalert.in',
    'sarkariwallahs.com',
    'govtjobguru.com',
    'sarkarialert.com',
    'govresult.com',
    'indgovtjobs.in',
    'govtjobs.com',
    'allindiajobs.in',
    'newgovtjobs.in',
    'freshersworld.com',
    'careerpower.in',
    'adda247.com',
    'testbook.com',
    'oliveboard.in',
    'jagranjosh.com',
    'aglasem.com',
    'successcds.net',
    'examresults.net',
    'resultnic.in',
    'careerwill.com',
    'gradeup.co',
    'byjus.com',
    'unacademy.com',
    'vidyakul.com',
    'jobone.in',
];

const OFFICIAL_PATTERNS = [
    '/\.gov\.in$/i',
    '/\.nic\.in$/i',
    '/\.ac\.in$/i',
    '/\.edu\.in$/i',
    '/\.org\.in$/i',
    '/\.res\.in$/i',
    '/ssc\.nic\.in/i',
    '/upsc\.gov\.in/i',
    '/ibps\.in/i',
    '/rbi\.org\.in/i',
    '/indianrailways\.gov\.in/i',
    '/indianarmy\.nic\.in/i',
    '/indiannavy\.nic\.in/i',
    '/indianairforce\.nic\.in/i',
    '/bsf\.gov\.in/i',
    '/crpf\.gov\.in/i',
    '/cisf\.gov\.in/i',
    '/itbp\.gov\.in/i',
    '/ssb\.nic\.in/i',
    '/niacl\.org\.in/i',
    '/licindia\.in/i',
    '/onlinesbi\.sbi/i',
    '/sbi\.co\.in/i',
    '/bankofbaroda\.in/i',
    '/pnbindia\.in/i',
    '/canarabank\.com/i',
    '/unionbankofindia\.co\.in/i',
    '/centralbankofindia\.co\.in/i',
    '/bankofindia\.co\.in/i',
    '/bankofmaharashtra\.in/i',
    '/ucobank\.com/i',
    '/npcil\.nic\.in/i',
    '/barc\.gov\.in/i',
    '/drdo\.gov\.in/i',
    '/isro\.gov\.in/i',
    '/sail\.co\.in/i',
    '/hal-india\.co\.in/i',
    '/bhel\.com/i',
    '/ongc\.co\.in/i',
    '/iocl\.com/i',
    '/gail\.com/i',
    '/coalindia\.in/i',
    '/ntpc\.co\.in/i',
    '/powergrid\.in/i',
    '/rvnl\.org/i',
    '/esic\.nic\.in/i',
    '/epfindia\.gov\.in/i',
    '/mea\.gov\.in/i',
    '/aai\.aero/i',
    '/ugc\.ac\.in/i',
    '/nta\.ac\.in/i',
    '/cbse\.gov\.in/i',
    '/icai\.org/i',
    '/icsi\.edu/i',
    '/icmai\.in/i',
    '/india\.gov\.in/i',
];

function get_registrable_domain(string $url): string
{
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $host = preg_replace('/^www\./', '', $host);
    $parts = explode('.', $host);
    $count = count($parts);
    if ($count >= 3) {
        $tld2 = $parts[$count - 2] . '.' . $parts[$count - 1];
        $known = ['gov.in', 'nic.in', 'ac.in', 'edu.in', 'org.in', 'co.in', 'res.in', 'net.in'];
        if (in_array($tld2, $known, true))
            return implode('.', array_slice($parts, -3));
    }
    return $count >= 2 ? implode('.', array_slice($parts, -2)) : $host;
}

function classify_domain(string $url, string $sourceDomain): string
{
    if (!filter_var($url, FILTER_VALIDATE_URL))
        return 'unknown';
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $host = preg_replace('/^www\./', '', $host);
    if ($host === $sourceDomain || str_ends_with($host, '.' . $sourceDomain))
        return 'aggregator';
    foreach (AGGREGATOR_DOMAINS as $agg) {
        if ($host === $agg || str_ends_with($host, '.' . $agg))
            return 'aggregator';
    }
    foreach (OFFICIAL_PATTERNS as $pattern) {
        if (preg_match($pattern, $host))
            return 'official';
    }
    $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');
    if (str_ends_with($path, '.pdf'))
        return 'official';
    return 'unknown';
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â”€â”€ LINK EXTRACTOR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

function extract_and_classify_links(string $html, string $pageUrl): array
{
    $sourceDomain = get_registrable_domain($pageUrl);
    $baseScheme = parse_url($pageUrl, PHP_URL_SCHEME) . '://';
    $baseHost = parse_url($pageUrl, PHP_URL_HOST);
    $basePath = dirname(parse_url($pageUrl, PHP_URL_PATH) ?? '/');

    preg_match_all('/<a[^>]+href=["\']([^"\'#\s][^"\']*)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

    $seen = [];
    $official = [];
    $agg = [];
    $unknown = [];
    foreach ($matches as $m) {
        $href = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $linkText = trim(strip_tags($m[2]));
        if (preg_match('/^(mailto:|tel:|javascript:|#)/i', $href))
            continue;
        if (!preg_match('/^https?:\/\//i', $href)) {
            if (str_starts_with($href, '//'))
                $href = 'https:' . $href;
            elseif (str_starts_with($href, '/'))
                $href = $baseScheme . $baseHost . $href;
            else
                $href = $baseScheme . $baseHost . rtrim($basePath, '/') . '/' . $href;
        }
        if (!filter_var($href, FILTER_VALIDATE_URL))
            continue;
        $key = strtolower($href);
        if (isset($seen[$key]))
            continue;
        $seen[$key] = true;
        $class = classify_domain($href, $sourceDomain);
        $entry = ['title' => $linkText ?: '', 'url' => $href];
        if ($class === 'official')
            $official[] = $entry;
        elseif ($class === 'aggregator')
            $agg[] = $entry;
        else
            $unknown[] = $entry;
    }
    return ['official' => $official, 'aggregator' => $agg, 'unknown' => $unknown];
}

function infer_link_title(string $rawTitle, string $url, int $idx): string
{
    $t = trim($rawTitle);
    $isGeneric = preg_match('/^[\d]+$/', $t)
        || preg_match('/^(link|click here|here|view|open|download|visit|check|more|read|notification|important|official)$/i', $t)
        || mb_strlen($t) < 3;
    if (!$isGeneric)
        return $t;
    $u = strtolower($url);
    $patterns = [
        ['/re.?register|re.?apply/i', 'Re-Apply / Re-Register'],
        ['/apply|register|application|online.form|fill.form/i', 'Apply Online'],
        ['/admit|hall.?ticket|call.?letter|e.?admit/i', 'Download Admit Card'],
        ['/final.result|merit.?list|selection.?list/i', 'Final Result / Merit List'],
        ['/provisional.result/i', 'Provisional Result'],
        ['/scorecard|score.?card/i', 'Download Scorecard'],
        ['/result/i', 'Check Result'],
        ['/answer.?key|ans.?key|solution|objection/i', 'Answer Key'],
        ['/syllabus|curriculum|exam.?pattern/i', 'Download Syllabus'],
        ['/interview|document.?verif|dv.?schedule/i', 'Interview / DV Schedule'],
        ['/walk.?in/i', 'Walk-In Interview Details'],
        ['/cut.?off|cutoff/i', 'Cut-Off Marks'],
        ['/extension|extend/i', 'Extended Last Date Notice'],
        ['/notification|advt|advertisement|circular|corrigendum/i', 'Official Notification PDF'],
        ['/login|candidate.?portal|applicant/i', 'Candidate Login Portal'],
        ['/fee|payment|challan/i', 'Fee / Payment Link'],
        ['/status/i', 'Check Application Status'],
        ['/\.pdf/i', 'Download PDF'],
        ['/\.gov\.|\.nic\.|official|home/i', 'Official Website'],
    ];
    foreach ($patterns as [$regex, $label]) {
        if (preg_match($regex, $u))
            return $label;
    }
    return 'Official Link ' . $idx;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â”€â”€ CURL HELPERS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

function curl_request(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null)
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error)
        return ['success' => false, 'message' => 'cURL error: ' . $error];
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        return [
            'success' => false,
            'message' => 'Invalid JSON from upstream (HTTP ' . $httpCode . ')',
            'http_code' => $httpCode,
            'url' => $url,
            'snippet' => substr($response, 0, 500),
        ];
    }
    return $decoded;
}

function curl_request_raw(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
            'Accept-Language: en-IN,en;q=0.9',
            'Accept-Encoding: identity',
            'Cache-Control: no-cache',
        ],
    ]);
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error)
        return ['success' => false, 'message' => 'cURL error: ' . $error];
    if ($httpCode >= 400)
        return ['success' => false, 'message' => 'HTTP ' . $httpCode . ' — page not accessible'];
    if (!$content)
        return ['success' => false, 'message' => 'Empty response from URL'];
    return ['success' => true, 'content' => $content, 'http_code' => $httpCode];
}

function html_to_text(string $html): string
{
    $html = preg_replace('/<script[^>]*>.*?<\/script>/si', ' ', $html);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/si', ' ', $html);
    $html = preg_replace('/<noscript[^>]*>.*?<\/noscript>/si', ' ', $html);
    $html = preg_replace('/<(nav|header|footer|aside|iframe)[^>]*>.*?<\/\1>/si', ' ', $html);
    $html = preg_replace('/<!--.*?-->/si', ' ', $html);
    $html = preg_replace('/<(br|p|div|h[1-6]|li|tr|td|th|section|article)[^>]*>/i', "\n", $html);
    $html = preg_replace('/<\/(p|div|h[1-6]|section|article|ul|ol|table)>/i', "\n", $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);
    if (mb_strlen($text) > 9000)
        $text = mb_substr($text, 0, 9000) . ' [truncated]';
    return $text;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â”€â”€ CONTENT TABLE BUILDERS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// ── Helper: safe date display — never show 01-01-1970 or empty ────────────────
function safe_date(string $val, string $fallback = 'Check Notification'): string
{
    if (empty(trim($val))) return $fallback;
    $ts = strtotime($val);
    if ($ts === false || $ts <= 0) return $fallback;
    if ($ts < mktime(0, 0, 0, 1, 1, 2000)) return $fallback;
    return date('d-m-Y', $ts);
}

function build_quick_info_table(array $p): string
{
    // Build age limit display with relaxations
    $ageGen  = (int)($p['age_max_gen'] ?? 0);
    $ageMin  = (int)($p['age_min'] ?? 0);
    $ageDisp = 'Check Notification';
    if ($ageGen > 0) {
        $ageDisp = ($ageMin > 0 ? $ageMin . ' – ' : '') . $ageGen . ' Yrs (Gen)';
        $parts = [];
        if ((int)($p['age_max_obc'] ?? 0) > 0)  $parts[] = 'OBC: ' . $p['age_max_obc'] . ' Yrs';
        if ((int)($p['age_max_sc']  ?? 0) > 0)  $parts[] = 'SC/ST: ' . $p['age_max_sc'] . ' Yrs';
        if ((int)($p['age_max_ph']  ?? 0) > 0)  $parts[] = 'PwD: ' . $p['age_max_ph'] . ' Yrs';
        if (!empty($p['age_as_on_date'])) $parts[] = 'As on: ' . date('d-m-Y', strtotime($p['age_as_on_date']));
        if ($parts) $ageDisp .= ' | ' . implode(' | ', $parts);
    }

    // Salary with pay level
    $salaryDisp = !empty($p['salary']) ? $p['salary'] : 'Check Notification';
    if (!empty($p['salary_min']) && (int)$p['salary_min'] > 0)
        $salaryDisp .= ' (₹' . number_format((int)$p['salary_min']) . '/month)';

    // Last date with time
    $lastDateDisp = safe_date($p['last_date'] ?? '', 'Apply Soon');
    if (!empty($p['deadline_time'])) $lastDateDisp .= ' by ' . $p['deadline_time'];

    $rows = [
        ["\u{1F3E2} Organization",   $p['organization'] ?? 'Government Body'],
        ["\u{1F4CB} Post Name",       $p['post_name'] ?? $p['title'] ?? 'Various'],
        ["\u{1F4E2} Total Vacancies", ($p['total_posts'] ?? 0) > 0 ? $p['total_posts'] : 'As per requirement'],
        ["\u{1F4C1} Category",        $p['category_name'] ?? 'Govt Jobs'],
        ["\u{1F4CD} Location",        $p['state_name'] ?? 'All India'],
        ["\u{1F393} Education",        !empty($p['education']) ? implode(', ', (array)$p['education']) : 'Check Notification'],
        ["\u{1F382} Age Limit",        $ageDisp],
        ["\u{1F4B0} Salary",           $salaryDisp],
        ["\u{1F4B3} App Fee",          ($p['fee_general'] ?? 0) > 0 ? "₹" . $p['fee_general'] . ' (Gen) | SC/ST/Women: Free' : 'No Fee'],
        ["\u{1F4DD} Apply Mode",       !empty($p['online_form']) ? 'Online' : 'Offline'],
        ["\u{1F4C5} Last Date",        $lastDateDisp],
    ];
    if (!empty($p['gate_note'])) {
        $rows[] = ["\u{1F3AF} GATE Note", htmlspecialchars($p['gate_note'])];
    }
    $htmlRows = '';
    foreach ($rows as $i => $r) {
        $bg = $i % 2 === 0 ? '#ffffff' : '#f9fbff';
        $htmlRows .= "<tr style=\"background:{$bg};\"><td style=\"padding:10px 14px;border:1px solid #eef2f7;font-weight:700;width:40%;\">{$r[0]}</td><td style=\"padding:10px 14px;border:1px solid #eef2f7;\">{$r[1]}</td></tr>";
    }
    return '<h3>' . "\u{1F4CA}" . ' Quick Info Overview</h3><table style="width:100%;border-collapse:collapse;margin:12px 0;border:1px solid #eef2f7;font-size:14px;">' . $htmlRows . '</table>';
}

function build_dates_table(array $p): string
{
    // Last date with exact time
    $lastDateVal = safe_date($p['last_date'] ?? '');
    if ($lastDateVal !== 'Check Notification' && !empty($p['deadline_time']))
        $lastDateVal .= ' (by ' . htmlspecialchars($p['deadline_time']) . ')';

    $dateDefs = [
        ['Notification Released',     $p['notification_date']    ?? ''],
        ['Online Apply Start',         $p['start_date']           ?? ''],
        ['Apply Last Date',            '__custom_last__'],
        ['Fee Payment Deadline',       $p['last_date']            ?? ''],
        ['Interview Date',             $p['interview_date_from']  ?? ''],
        ['Exam Date',                  $p['exam_date']            ?? ''],
        ['Result Date',                $p['result_date']          ?? ''],
        ['Waitlist Valid Until',        '__custom_waitlist__'],
    ];
    $htmlRows = '';
    $skipIfEmpty = ['Exam Date', 'Result Date', 'Interview Date', 'Waitlist Valid Until'];
    foreach ($dateDefs as [$label, $val]) {
        if ($val === '__custom_last__') {
            $display = $lastDateVal;
        } elseif ($val === '__custom_waitlist__') {
            $display = !empty($p['waitlist_date']) ? safe_date($p['waitlist_date']) : 'Check Notification';
        } else {
            $display = safe_date((string)$val);
        }
        if ($display === 'Check Notification' && in_array($label, $skipIfEmpty)) continue;
        $htmlRows .= "<tr><td style=\"padding:10px;border:1px solid #eef2f7;font-weight:600;\">{$label}</td><td style=\"padding:10px;border:1px solid #eef2f7;\">{$display}</td></tr>";
    }
    return '<h3>' . "\u{1F4C5}" . ' Important Dates</h3><table style="width:100%;border-collapse:collapse;margin:12px 0;border:1px solid #eef2f7;font-size:14px;background:#fff;">' . $htmlRows . '</table>';
}

function build_fee_table(array $p): string
{
    $fees = [
        'General / OBC'     => $p['fee_general'] ?? 0,
        'SC / ST'           => $p['fee_sc_st']   ?? 0,
        'PH / PWD'          => $p['fee_ph']      ?? 0,
        'Female Candidates' => $p['fee_women']   ?? 0,
    ];
    $htmlRows = '';
    foreach ($fees as $label => $val) {
        $display = $val > 0 ? "\u{20B9}" . $val : 'Nil / Exempted';
        $htmlRows .= "<tr><td style=\"padding:10px;border:1px solid #eef2f7;font-weight:600;\">{$label}</td><td style=\"padding:10px;border:1px solid #eef2f7;\">{$display}</td></tr>";
    }
    return '<h3>' . "\u{1F4B3}" . ' Application Fee</h3><table style="width:100%;border-collapse:collapse;margin:12px 0;border:1px solid #eef2f7;font-size:14px;background:#fff;">' . $htmlRows . '</table>';
}

function build_vacancy_table(array $p): string
{
    $cats = [
        'General' => $p['vacancy_gen'] ?? 0,
        'OBC'     => $p['vacancy_obc'] ?? 0,
        'SC'      => $p['vacancy_sc']  ?? 0,
        'ST'      => $p['vacancy_st']  ?? 0,
        'EWS'     => $p['vacancy_ews'] ?? 0,
        'PH'      => $p['vacancy_ph']  ?? 0,
    ];
    $htmlRows = '';
    $total = 0;
    foreach ($cats as $label => $val) {
        $total += (int) $val;
        $htmlRows .= "<tr><td style=\"padding:10px;border:1px solid #eef2f7;font-weight:600;\">{$label}</td><td style=\"padding:10px;border:1px solid #eef2f7;\">{$val}</td></tr>";
    }
    $htmlRows .= "<tr style=\"background:#f8fafc;\"><td style=\"padding:10px;border:1px solid #eef2f7;font-weight:800;\">Total Posts</td><td style=\"padding:10px;border:1px solid #eef2f7;font-weight:800;\">" . ($p['total_posts'] ?? $total) . "</td></tr>";
    return '<h3>' . "\u{1F4E2}" . ' Vacancy Breakdown</h3><table style="width:100%;border-collapse:collapse;margin:12px 0;border:1px solid #eef2f7;font-size:14px;background:#fff;">' . $htmlRows . '</table>';
}

function build_selection_stages(array $p): string
{
    $stages = $p['selection_stages'] ?? [];
    if (empty($stages))
        return '';
    $list = '';
    foreach ($stages as $i => $s) {
        $list .= "<li style=\"margin-bottom:8px;\"><strong>Stage " . ($i + 1) . ":</strong> " . htmlspecialchars($s) . "</li>";
    }
    return '<h3>' . "\u{1F3AF}" . ' Selection Process</h3><ol style="padding-left:20px;margin:12px 0;">' . $list . '</ol>';
}

function build_links_table(array $links): string
{
    $iconMap = [
        'apply online'      => "\u{1F4DD}",
        'register'          => "\u{1F4DD}",
        'application form'  => "\u{1F4DD}",
        'admit card'        => "\u{1FAAA}",
        'hall ticket'       => "\u{1FAAA}",
        'call letter'       => "\u{1FAAA}",
        'final result'      => "\u{1F3C6}",
        'merit list'        => "\u{1F3C6}",
        'selection list'    => "\u{1F3C6}",
        'provisional result'=> "\u{1F4CA}",
        'scorecard'         => "\u{1F4CA}",
        'marks'             => "\u{1F4CA}",
        'result'            => "\u{1F4CA}",
        'syllabus'          => "\u{1F4DA}",
        'exam pattern'      => "\u{1F4DA}",
        'answer key'        => "\u{1F511}",
        'cut-off'           => "\u{2702}\u{FE0F}",
        'cut off'           => "\u{2702}\u{FE0F}",
        'cutoff'            => "\u{2702}\u{FE0F}",
        'interview'         => "\u{1F5E3}\u{FE0F}",
        'walk-in'           => "\u{1F5E3}\u{FE0F}",
        'walk in'           => "\u{1F5E3}\u{FE0F}",
        'official website'  => "\u{1F3DB}\u{FE0F}",
        'official'          => "\u{1F3DB}\u{FE0F}",
        'notification'      => "\u{1F4C4}",
        'pdf'               => "\u{1F4C4}",
        'telegram'          => "\u{1F4E2}",
        'whatsapp'          => "\u{1F4E2}",
        'channel'           => "\u{1F4E2}",
        'schedule'          => "\u{1F4C5}",
        'date'              => "\u{1F4C5}",
        'extension'         => "\u{1F4C5}",
        'fee'               => "\u{1F4B3}",
        'payment'           => "\u{1F4B3}",
        'login'             => "\u{1F50D}",
        'status'            => "\u{1F50D}",
        'download'          => "\u{2B07}\u{FE0F}",
    ];
    $getIcon = function (string $title) use ($iconMap): string {
        $lower = mb_strtolower($title);
        foreach ($iconMap as $kw => $icon) {
            if (str_contains($lower, $kw))
                return $icon;
        }
        return "\u{1F517}";
    };

    $validLinks = array_values(array_filter($links, fn($l) => !empty($l['url'])));
    if (empty($validLinks)) return '';
    $rows = '';
    foreach ($validLinks as $i => $link) {
        $title = htmlspecialchars($link['title'] ?? 'Important Link');
        $url = htmlspecialchars($link['url']);
        $icon = $getIcon($link['title'] ?? '');
        $rowBg = $i % 2 === 0 ? '#ffffff' : '#f4f7ff';
        $isSocial = str_contains(strtolower($link['url']), 't.me/') || str_contains(strtolower($link['url']), 'whatsapp.com/channel');
        $btnStyle = $isSocial ? 'background:linear-gradient(135deg,#229ED9,#0d7abf);' : 'background:linear-gradient(135deg,#1a6ef5,#5b4ceb);';
        $rows .= "<tr style=\"background:{$rowBg};\"><td style=\"padding:13px 18px;border-bottom:1px solid #e4e9f2;font-size:13px;font-weight:600;color:#0f1724;\">{$icon}&nbsp; {$title}</td><td style=\"padding:10px 18px;border-bottom:1px solid #e4e9f2;text-align:center;\"><a href=\"{$url}\" target=\"_blank\" rel=\"noreferrer\" style=\"display:inline-block;{$btnStyle}color:#fff;border-radius:6px;padding:7px 20px;font-size:12px;font-weight:700;text-decoration:none;\">Click Here &#8599;</a></td></tr>";
    }

    return '<h3>&#128279; Important Links Table</h3><table style="width:100%;border-collapse:collapse;border:1px solid #e4e9f2;border-radius:12px;overflow:hidden;margin:14px 0;font-family:system-ui,sans-serif;box-shadow:0 2px 12px rgba(15,23,36,.07);"><thead><tr style="background:linear-gradient(135deg,#1a6ef5 0%,#5b4ceb 100%);"><th style="padding:14px 18px;color:#fff;font-size:13px;font-weight:700;text-align:left;">&#128203; Description</th><th style="padding:14px 18px;color:#fff;font-size:13px;font-weight:700;text-align:center;width:150px;">&#128279; Direct Link</th></tr></thead><tbody>' . 
$rows
 . '</tbody></table>';
}
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â”€â”€ SCHEMA GENERATORS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * FIX #2: The original code did:
 *   $schema['jobLocationType'] = 'TELECOMMUTE';   // sets on undefined var
 *   $schema = [ '@context' => ... ];              // OVERWRITES it entirely!
 * Fix: collect location fields separately, then merge into $schema.
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
function generate_job_schema(array $p, array $importantLinks = []): string
{
    $now = date('c');
    $postSlug = $p['slug'] ?? '';
    $jobUrl = $postSlug ? JOBONE_SITE_URL . '/' . $postSlug : JOBONE_SITE_URL;

    // â”€â”€ Hiring organisation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $org = [
        '@type' => 'Organization',
        'name' => !empty($p['organization']) ? $p['organization'] : JOBONE_SITE_NAME,
        'sameAs' => JOBONE_SITE_URL,
        'url' => JOBONE_SITE_URL,
        'logo' => [
            '@type' => 'ImageObject',
            'url'   => JOBONE_SITE_URL . '/images/jobone-logo.png',
        ],
    ];
    foreach ($importantLinks as $l) {
        if (!empty($l['url']) && preg_match('/official.?website|official.?site/i', $l['title'] ?? '')) {
            $org['sameAs'] = $l['url'];
            $org['url'] = $l['url'];
            break;
        }
    }

    // â”€â”€ FIX #2: build location fields into a separate array first â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $region = !empty($p['state_name']) ? trim($p['state_name']) : 'All India';
    $locationFields = [];
    if ($region === '' || $region === 'All India') {
        $locationFields['jobLocationType'] = 'TELECOMMUTE';
        $locationFields['applicantLocationRequirements'] = [
            '@type' => 'Country',
            'name' => 'India',
        ];
    } else {
        $locationFields['jobLocation'] = [
            '@type' => 'Place',
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $region,
                'addressLocality' => $region,
                'addressRegion'   => $region,
                'postalCode'      => '000000',
                'addressCountry'  => 'IN',
            ],
        ];
    }

    // â”€â”€ Salary â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $minSal = (int) ($p['salary_min'] ?? 0);
    $maxSal = (int) ($p['salary_max'] ?? 0);
    if ($minSal === 0 && $maxSal === 0 && !empty($p['salary'])) {
        preg_match_all('/\d+/', str_replace(',', '', $p['salary']), $salMatches);
        if (!empty($salMatches[0])) {
            $salaries = array_map('intval', $salMatches[0]);
            sort($salaries);
            $minSal = $salaries[0];
            $maxSal = $salaries[count($salaries) - 1];
        }
    }
    if ($maxSal < $minSal)
        $maxSal = $minSal;
    $salaryBlock = [
        '@type' => 'MonetaryAmount',
        'currency' => 'INR',
        'value' => [
            '@type' => 'QuantitativeValue',
            'minValue' => $minSal,
            'maxValue' => $maxSal > 0 ? $maxSal : ($minSal > 0 ? $minSal : 0),
            'unitText' => 'MONTH',
        ],
    ];

    // â”€â”€ Education requirements (Google-accepted lowercase values) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $eduMap = [
        '10th_pass'       => 'high school',
        '12th_pass'       => 'high school',
        'graduate'        => 'bachelor degree',
        'post_graduate'   => 'master degree',
        'diploma'         => 'associate degree',
        'iti'             => 'associate degree',
        'btech'           => 'bachelor degree',
        'bsc'             => 'bachelor degree',
        'bcom'            => 'bachelor degree',
        'ba'              => 'bachelor degree',
        'bpharm'          => 'bachelor degree',
        'bed'             => 'bachelor degree',
        'nursing'         => 'bachelor degree',
        'llb'             => 'bachelor degree',
        'mbbs'            => 'bachelor degree',
        'bds'             => 'bachelor degree',
        'mtech'           => 'master degree',
        'msc'             => 'master degree',
        'mcom'            => 'master degree',
        'ma'              => 'master degree',
        'mpharm'          => 'master degree',
        'mba'             => 'master degree',
        'llm'             => 'master degree',
        'ca'              => 'professional certificate',
        'cs'              => 'professional certificate',
        'cma'             => 'professional certificate',
        'phd'             => 'doctoral degree',
        'any_qualification' => 'bachelor degree',
    ];
    $eduReqs = [];
    foreach (($p['education'] ?? []) as $e) {
        if (isset($eduMap[$e]))
            $eduReqs[] = $eduMap[$e];
    }
    $eduReqs = array_values(array_unique($eduReqs));

    // â”€â”€ Direct apply â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $applyUrlCandidate = $p['apply_url'] ?? $p['online_form'] ?? '';
    $isDirect = false;
    if (!empty($applyUrlCandidate)) {
        $parsedUrl = parse_url($applyUrlCandidate);
        $path = rtrim($parsedUrl['path'] ?? '/', '/');
        $query = $parsedUrl['query'] ?? '';
        if ($path !== '' && !empty($query))
            $isDirect = true;
    }
    $directApply = (bool) ($p['direct_apply'] ?? $isDirect);

    // â”€â”€ Employment type â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $empType = 'FULL_TIME';
    $tLower = strtolower($p['title'] ?? '');
    if (str_contains($tLower, 'apprentice') || str_contains($tLower, 'trainee') || ($p['salary_type'] ?? '') === 'stipend')
        $empType = 'INTERN';
    elseif (str_contains($tLower, 'contract'))
        $empType = 'CONTRACTOR';
    elseif (str_contains($tLower, 'part time') || ($p['type'] ?? '') === 'scholarship')
        $empType = 'PART_TIME';
    elseif (str_contains($tLower, 'volunteer'))
        $empType = 'VOLUNTEER';
    elseif (str_contains($tLower, 'temporary'))
        $empType = 'TEMPORARY';

    // â”€â”€ FIX #3: description — use short_description only (plain text, â‰¤5000c) â”€
    // The original code stuffed the entire content HTML (tables, schemas, etc.)
    // into description, which breaks Google's validator.
    $description = trim(strip_tags($p['short_description'] ?? ''));
    if (empty($description)) {
        $description = trim(substr(strip_tags($p['title'] ?? ''), 0, 300));
    }
    // Append a structured summary to meet Google's meaningful-content requirement
    $extras = [];
    if (!empty($p['total_posts']) && (int) $p['total_posts'] > 0)
        $extras[] = 'Total Vacancies: ' . $p['total_posts'];
    if (!empty($p['salary']))
        $extras[] = 'Salary: ' . strip_tags($p['salary']);
    if (!empty($p['last_date']))
        $extras[] = 'Last Date: ' . date('d-m-Y', strtotime($p['last_date']));
    if (!empty($extras))
        $description .= ' ' . implode('. ', $extras) . '.';
    $description = substr($description, 0, 5000);

    // â”€â”€ Build schema — location fields merged in, NOT set before â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $schema = array_merge(
        [
            '@context' => 'https://schema.org/',
            '@type' => 'JobPosting',
            'title' => $p['title'] ?? '',
            'description' => $description,
            'url' => $jobUrl,
            'datePosted' => !empty($p['notification_date'])
                ? date('c', strtotime($p['notification_date']))
                : $now,
            'dateModified' => $now,
            'employmentType' => $empType,
            'directApply' => $directApply,
            'hiringOrganization' => $org,
            'jobBenefits' => 'Government employment benefits: job security, pension, health insurance, HRA, TA, DA, medical allowance',
            'occupationalCategory' => '11-1000.00',
            'industry' => 'Government / Public Sector',
            'workHours' => '8 hours/day, 5 days/week',
        ],
        $locationFields          // â† FIX #2: merged here, never overwritten
    );

    // Optional fields
    $schema['validThrough'] = !empty($p['last_date'])
        ? $p['last_date'] . 'T23:59:59+05:30'
        : date('Y-m-d', strtotime('+90 days')) . 'T23:59:59+05:30';

    if (!empty($p['start_date']))
        $schema['jobStartDate'] = $p['start_date'];
    if ($salaryBlock)
        $schema['baseSalary'] = $salaryBlock;
    if (!empty($p['total_posts']))
        $schema['totalJobOpenings'] = (int) $p['total_posts'];

    $applyLink = $p['apply_url'] ?? $p['online_form'] ?? '';
    if (!empty($applyLink))
        $schema['applicationContact'] = ['@type' => 'ContactPoint', 'contactType' => 'Apply Online', 'url' => $applyLink];

    $qualParts = [];
    if (!empty($p['qualifications']))
        $qualParts[] = strip_tags($p['qualifications']);
    if (!empty($qualParts))
        $schema['qualifications'] = implode('. ', $qualParts);

    if (!empty($eduReqs)) {
        $fullQuals = strip_tags($p['qualifications'] ?? '');
        $eduReqsObj = [];
        foreach ($eduReqs as $level) {
            $eduReqsObj[] = [
                '@type' => 'EducationalOccupationalCredential',
                'credentialCategory' => $level,
                'description' => $fullQuals ?: $level,
            ];
        }
        $schema['educationRequirements'] = count($eduReqsObj) === 1 ? $eduReqsObj[0] : $eduReqsObj;
    }

    if (!empty($p['skills']))
        $schema['skills'] = $p['skills'];
    if (!empty($p['responsibilities']))
        $schema['responsibilities'] = $p['responsibilities'];
    if (!empty($p['id']))
        $schema['identifier'] = ['@type' => 'PropertyValue', 'name' => JOBONE_SITE_NAME, 'value' => (string) $p['id']];

    // Breadcrumb
    $breadcrumb = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => JOBONE_SITE_URL],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $p['category_name'] ?? 'Jobs', 'item' => JOBONE_SITE_URL . '/category/' . strtolower(str_replace(' ', '-', $p['category_name'] ?? 'jobs'))],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $p['title'] ?? 'Job Post', 'item' => $jobUrl],
        ],
    ];

    $out = '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
    $out .= '<script type="application/ld+json">' . json_encode($breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    return $out;
}

/**
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 * FIX #3 (continued): FAQ schema — strip HTML from answer text.
 * JSON-LD requires plain text in acceptedAnswer.text; HTML tags cause
 * "invalid items" in Google's Rich Results Test.
 * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
function generate_faq_schema(array $faq): string
{
    if (empty($faq))
        return '';
    $items = [];
    foreach ($faq as $item) {
        $q = trim($item['question'] ?? '');
        $a = trim($item['answer'] ?? '');
        if (!$q || !$a)
            continue;
        // Strip HTML tags and decode entities for clean JSON-LD plain text
        $a = html_entity_decode(strip_tags($a), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $q = html_entity_decode(strip_tags($q), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $items[] = [
            '@type' => 'Question',
            'name' => $q,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
        ];
    }
    if (empty($items))
        return '';
    $schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items];
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

function build_faq_html(array $faq): string
{
    if (empty($faq))
        return '';
    $items = '';
    foreach ($faq as $item) {
        if (empty($item['question']))
            continue;
        $q = htmlspecialchars($item['question']);
        $a = htmlspecialchars($item['answer'] ?? '');
        $items .= "
<div style=\"border:1px solid #e4e9f2;border-radius:10px;margin-bottom:10px;overflow:hidden;\">
  <details>
    <summary style=\"padding:14px 18px;cursor:pointer;font-size:13px;font-weight:700;color:#0f1724;list-style:none;display:flex;justify-content:space-between;align-items:center;\">
      {$q} <span style=\"font-size:18px;color:#1a6ef5;\">+</span>
    </summary>
    <div style=\"padding:0 18px 14px;font-size:13px;color:#4a5568;line-height:1.75;border-top:1px solid #e4e9f2;\">{$a}</div>
  </details>
</div>";
    }
    if (!$items)
        return '';
    return '<h3>❓ Frequently Asked Questions (FAQ)</h3><div style="margin:14px 0;">' . $items . '</div>';
}

function generate_og_tags(array $p, string $jobUrl, string $imageUrl = ''): string
{
    $title = htmlspecialchars($p['title'] ?? '', ENT_QUOTES);
    $description = htmlspecialchars($p['short_description'] ?? '', ENT_QUOTES);
    $siteName = htmlspecialchars(JOBONE_SITE_NAME, ENT_QUOTES);
    $url = htmlspecialchars($jobUrl, ENT_QUOTES);
    $image = $imageUrl ?: JOBONE_SITE_URL . '/images/og-default.png';

    return implode("\n", [
        "<!-- Open Graph -->",
        "<meta property=\"og:type\"        content=\"website\">",
        "<meta property=\"og:site_name\"   content=\"{$siteName}\">",
        "<meta property=\"og:title\"       content=\"{$title}\">",
        "<meta property=\"og:description\" content=\"{$description}\">",
        "<meta property=\"og:url\"         content=\"{$url}\">",
        "<meta property=\"og:image\"       content=\"" . htmlspecialchars($image, ENT_QUOTES) . "\">",
        "<meta property=\"og:locale\"      content=\"en_IN\">",
        "<!-- Twitter Card -->",
        "<meta name=\"twitter:card\"        content=\"summary_large_image\">",
        "<meta name=\"twitter:title\"       content=\"{$title}\">",
        "<meta name=\"twitter:description\" content=\"{$description}\">",
        "<meta name=\"twitter:image\"       content=\"" . htmlspecialchars($image, ENT_QUOTES) . "\">",
        "<!-- hreflang -->",
        "<link rel=\"alternate\" hreflang=\"en-IN\" href=\"{$url}\">",
        "<link rel=\"alternate\" hreflang=\"hi\"    href=\"{$url}\">",
        "<link rel=\"canonical\"            href=\"{$url}\">",
    ]);
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â”€â”€ INDEXNOW PING â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• 

function ping_indexnow(string $jobUrl): array
{
    if (INDEXNOW_KEY === 'YOUR_32CHAR_GUID_KEY_HERE')
        return ['skipped' => true, 'reason' => 'IndexNow key not configured'];

    $payload = json_encode([
        'host' => INDEXNOW_HOST,
        'key' => INDEXNOW_KEY,
        'keyLocation' => JOBONE_SITE_URL . '/' . INDEXNOW_KEY . '.txt',
        'urlList' => [$jobUrl],
    ]);
    $headers = ['Content-Type: application/json; charset=utf-8'];
    $engines = ['indexnow' => 'https://api.indexnow.org/indexnow', 'bing' => 'https://www.bing.com/indexnow'];

    $results = [];
    foreach ($engines as $name => $endpoint) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $results[$name] = ['http_code' => $code, 'error' => $err ?: null, 'success' => $code === 200 || $code === 202];
    }
    return ['pinged' => true, 'url' => $jobUrl, 'results' => $results];
}

function build_internal_links(string $postType): string
{
    $base = JOBONE_SITE_URL;

    // ── VERIFIED REAL URLs (tested 2026-04-28) ──────────────────────────
    // Type listing pages:
    //   /jobs ✅  /admit-cards ✅  /results ✅  /answer-keys ✅
    //   /scholarships ✅  /blogs ✅  /syllabus ✅
    // Category pages: /category/{slug} ✅  (NOT /jobs/category/!)
    // State pages: /state/{slug} ✅

    $links = [];

    switch ($postType) {
        case 'job':
            $links = [
                "{$base}/jobs"                       => "Latest Govt Jobs 2026",
                "{$base}/category/ssc"               => "SSC Jobs 2026",
                "{$base}/category/upsc"              => "UPSC Jobs 2026",
                "{$base}/category/banking"           => "Banking Jobs 2026",
                "{$base}/category/railways"          => "Railway Jobs 2026",
                "{$base}/admit-cards"                => "Download Admit Card",
                "{$base}/results"                    => "Check Latest Results",
                "{$base}/syllabus"                   => "Download Syllabus PDF",
                "{$base}/category/state-govt"        => "State Govt Jobs 2026",
                "{$base}/category/defence"           => "Defence Jobs 2026",
            ];
            break;

        case 'admit_card':
            $links = [
                "{$base}/admit-cards"                => "Latest Admit Cards 2026",
                "{$base}/results"                    => "Check Exam Results 2026",
                "{$base}/answer-keys"                => "Download Answer Key",
                "{$base}/syllabus"                   => "Exam Syllabus & Pattern",
                "{$base}/jobs"                       => "Latest Govt Jobs 2026",
                "{$base}/category/ssc"               => "SSC Admit Card",
                "{$base}/category/railways"          => "Railway Admit Card",
                "{$base}/category/banking"           => "Bank Exam Admit Card",
                "{$base}/category/upsc"              => "UPSC Admit Card",
            ];
            break;

        case 'result':
            $links = [
                "{$base}/results"                    => "Latest Exam Results 2026",
                "{$base}/admit-cards"                => "Download Admit Card",
                "{$base}/answer-keys"                => "Official Answer Key",
                "{$base}/syllabus"                   => "Exam Syllabus PDF",
                "{$base}/jobs"                       => "Latest Sarkari Naukri 2026",
                "{$base}/category/ssc"               => "SSC Exam Results",
                "{$base}/category/upsc"              => "UPSC Results",
                "{$base}/category/banking"           => "Bank Exam Results",
                "{$base}/category/railways"          => "Railway Exam Results",
            ];
            break;

        case 'answer_key':
            $links = [
                "{$base}/answer-keys"                => "Official Answer Keys 2026",
                "{$base}/results"                    => "Check Result 2026",
                "{$base}/admit-cards"                => "Download Admit Card",
                "{$base}/syllabus"                   => "Exam Syllabus & Pattern",
                "{$base}/jobs"                       => "Latest Govt Jobs 2026",
                "{$base}/category/ssc"               => "SSC Answer Key",
                "{$base}/category/railways"          => "Railway Answer Key",
            ];
            break;

        case 'syllabus':
            $links = [
                "{$base}/syllabus"                   => "All Exam Syllabus 2026",
                "{$base}/admit-cards"                => "Download Hall Ticket",
                "{$base}/results"                    => "Check Exam Results",
                "{$base}/answer-keys"                => "Official Answer Key",
                "{$base}/jobs"                       => "Latest Sarkari Naukri 2026",
                "{$base}/category/ssc"               => "SSC Syllabus",
                "{$base}/category/upsc"              => "UPSC Syllabus",
                "{$base}/category/railways"          => "Railway Exam Syllabus",
                "{$base}/category/banking"           => "Bank Exam Syllabus",
            ];
            break;

        case 'scholarship':
            $links = [
                "{$base}/scholarships"               => "Latest Scholarships 2026",
                "{$base}/jobs"                       => "Latest Govt Jobs 2026",
                "{$base}/category/central-university" => "University Admissions",
                "{$base}/results"                    => "Scholarship Results",
                "{$base}/blogs"                      => "Career Guides & Tips",
            ];
            break;

        case 'blog':
            $links = [
                "{$base}/jobs"                       => "Latest Sarkari Naukri 2026",
                "{$base}/category/ssc"               => "SSC Jobs 2026",
                "{$base}/category/upsc"              => "UPSC Jobs 2026",
                "{$base}/category/banking"           => "Bank Jobs 2026",
                "{$base}/admit-cards"                => "Download Admit Cards",
                "{$base}/results"                    => "Check Latest Results",
                "{$base}/syllabus"                   => "Exam Syllabus PDF",
                "{$base}/scholarships"               => "Scholarships 2026",
                "{$base}/blogs"                      => "Career Guides",
            ];
            break;

        default:
            $links = [
                "{$base}/jobs"           => "Latest Govt Jobs 2026",
                "{$base}/admit-cards"    => "Download Admit Card",
                "{$base}/results"        => "Check Results",
                "{$base}/syllabus"       => "Exam Syllabus",
                "{$base}/scholarships"   => "Scholarships 2026",
            ];
    }

    // Format as HTML <a> tags list for the prompt
    $html = [];
    foreach ($links as $url => $label) {
        $html[] = "      <a href=\"{$url}\">{$label}</a>";
    }
    return implode("\n", $html);
}

function sanitize_total_posts(mixed $val): int
{
    if (is_int($val) && $val >= 0)
        return $val;
    if (is_numeric($val))
        return max(0, (int) $val);
    if (preg_match('/(\d+)/', (string) $val, $m))
        return (int) $m[1];
    return 0;
}

function generate_slug(array $p): string
{
    $org = !empty($p['organization']) ? $p['organization'] : 'govt';
    $orgParts = explode(' ', $org);
    if (count($orgParts) > 3)
        $org = implode(' ', array_slice($orgParts, 0, 3));

    $postName = $p['title'] ?? 'job';
    $year = date('Y');
    if (preg_match('/\b(20\d{2})\b/', $postName . ' ' . ($p['short_description'] ?? ''), $m))
        $year = $m[1];

    $fillers = ['apply-for', 'apply-now', 'apply', 'notification', 'check-here', 'recruitment', 'jobs', 'post', 'posts', 'official', 'latest', 'new'];
    $slugBody = $org . ' ' . $postName;
    foreach ($fillers as $f) {
        $slugBody = preg_replace('/\b' . preg_quote($f, '/') . '\b/i', '', $slugBody);
    }

    $vacancy = (int) ($p['total_posts'] ?? 0);
    $finalBase = trim($slugBody) . ' recruitment ' . $year;
    if ($vacancy > 0)
        $finalBase .= ' ' . $vacancy . ' posts';

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $finalBase), '-'));
    if (strlen($slug) > 70) {
        $slug = substr($slug, 0, 70);
        $slug = rtrim($slug, '-');
    }
    return $slug;
}

function sanitize_apply_url(string $url): string
{
    if (!$url)
        return '';
    $p = parse_url($url);
    $path = rtrim($p['path'] ?? '/', '/');
    $query = $p['query'] ?? '';
    if (($path === '' || $path === '/') && !$query)
        return '';
    return $url;
}

function auto_detect_education(string $content, array $current): array
{
    if (!empty($current))
        return $current;
    $t = strtolower($content);
    $map = [
        'phd' => '/ph\.?d\b|doctorate|doctoral/',
        'mba' => '/\bmba\b|pgdm|post.?graduate.?diploma.?manage/',
        'llm' => '/\bllm\b|master.?of.?law/',
        'llb' => '/\bllb\b|bachelor.?of.?law|law.?graduate/',
        'mbbs' => '/\bmbbs\b|bachelor.?of.?medicine/',
        'mtech' => '/\bm\.?tech\b|\bm\.?e\.\b|master.?of.?technology/',
        'mpharm' => '/\bm\.?pharm\b/',
        'bpharm' => '/\bb\.?pharm\b/',
        'msc' => '/\bm\.?sc\b|master.?of.?science/',
        'mcom' => '/\bm\.?com\b|master.?of.?commerce/',
        'ma' => '/\bm\.?a\.\b|master.?of.?arts/',
        'ca' => '/chartered.?accountant|\bca\b/',
        'cs' => '/company.?secretary|\bcs\b/',
        'cma' => '/\bcma\b|cost.?accountant/',
        'post_graduate' => '/post.?graduate|master.?degree|\bm\.?sc\b|\bm\.?com\b|\bm\.?a\.\b|\bmca\b/',
        'btech' => '/\bb\.?tech\b|\bb\.?e\.\b|bachelor.?of.?engineering|bachelor.?of.?technology/',
        'bsc' => '/\bb\.?sc\b|bachelor.?of.?science/',
        'bcom' => '/\bb\.?com\b|bachelor.?of.?commerce/',
        'ba' => '/\bb\.?a\.\b|bachelor.?of.?arts/',
        'bed' => '/\bb\.?ed\b|bachelor.?of.?education/',
        'nursing' => '/\bnursing\b|\bgnm\b|\bbnsc\b/',
        'graduate' => '/\bgraduate\b|graduation|bachelor.?degree|degree.?holder/',
        'diploma' => '/\bdiploma\b/',
        'iti' => '/\biti\b|industrial.?training.?institute/',
        '12th_pass' => '/12th|hsc|higher.?secondary|intermediate|10\+2/',
        '10th_pass' => '/10th|ssc|matriculation|\bssle\b|secondary.?school/',
    ];
    $detected = [];
    foreach ($map as $chip => $rx) {
        if (preg_match($rx, $t))
            $detected[] = $chip;
    }

    $hasMasters = array_intersect(['mba', 'mtech', 'mpharm', 'llm', 'msc', 'mcom', 'ma', 'post_graduate'], $detected);
    $hasBachelors = array_intersect(['btech', 'bsc', 'bcom', 'ba', 'bpharm', 'bed', 'nursing'], $detected)
        || preg_match('/\b(bachelor|graduate|graduation|degree)\b/i', $content);

    if ($hasMasters && !in_array('post_graduate', $detected))
        $detected[] = 'post_graduate';
    if ($hasBachelors && !in_array('graduate', $detected))
        $detected[] = 'graduate';
    if ($hasMasters && !$hasBachelors && ($key = array_search('graduate', $detected)) !== false)
        unset($detected[$key]);

    $detected = array_unique($detected);
    return $detected ?: ['graduate'];
}

function auto_detect_tags(string $content, array $current): array
{
    if (!empty($current))
        return $current;
    $t = strtolower($content);
    $map = [
        'cutoff' => '/cut.?off|cutoff/',
        'merit_list' => '/merit.?list/',
        'selection_list' => '/selection.?list/',
        'final_result' => '/final.?result/',
        'provisional_result' => '/provisional.?result/',
        'revised_result' => '/revised.?result/',
        'scorecard' => '/scorecard|score.?card/',
        'marks' => '/marks.?(released|published|available)/',
    ];
    $out = [];
    foreach ($map as $tag => $rx) {
        if (preg_match($rx, $t))
            $out[] = $tag;
    }
    return $out;
}

function correct_category(array $p): array
{
    $combined = strtolower(($p['organization'] ?? '') . ' ' . ($p['title'] ?? '') . ' ' . ($p['short_description'] ?? ''));
    $psu = [
        'iffco',
        'bpcl',
        'hpcl',
        'iocl',
        'ongc',
        'gail',
        'ntpc',
        'bhel',
        'sail',
        'hal',
        'drdo',
        'isro',
        'barc',
        'npcil',
        'coalindia',
        'coal india',
        'power grid',
        'powergrid',
        'rvnl',
        'aai',
        'concor',
        'rites',
        'ircon',
        'irctc',
        'nhpc',
        'nlc',
        'mecl',
        'bsnl',
        'mtnl',
        'nhai',
        'hecl',
        'beml',
        'public sector undertaking',
        'central public sector',
        'psu'
    ];
    foreach ($psu as $kw) {
        if (str_contains($combined, $kw)) {
            $p['category_name'] = 'PSU Jobs';
            $p['state_name'] = 'All India';
            return $p;
        }
    }
    $central = [
        'ministry of',
        'department of',
        'government of india',
        'high court',
        'supreme court',
        'upsc',
        'staff selection',
        'rrb ',
        'rail',
        'ibps',
        'reserve bank',
        'rbi ',
        'esic',
        'epfo',
        'central government',
        'union government'
    ];
    if (($p['category_name'] ?? '') === 'State Govt Jobs') {
        foreach ($central as $kw) {
            if (str_contains($combined, $kw)) {
                $p['category_name'] = 'Central Govt Jobs';
                $p['state_name'] = 'All India';
                return $p;
            }
        }
    }
    return $p;
}

function correct_employment_type(array $p): array
{
    $salType = strtolower($p['salary_type'] ?? 'salary');
    $salary = strtolower($p['salary'] ?? '');
    $title = strtolower($p['title'] ?? '');
    if ($salType === 'stipend' || str_contains($salary, 'stipend') || preg_match('/trainee|intern|apprentice/', $title)) {
        $p['salary_type'] = 'stipend';
        if (!empty($p['salary']) && !str_contains(strtolower($p['salary']), 'stipend'))
            $p['salary'] = 'Stipend: ' . $p['salary'];
    }
    return $p;
}
function get_ai_prompt(array $preFilteredLinks = [], string $postType = 'job'): string
{
    $tg = TG_CHANNEL;
    $wa = WA_CHANNEL;
    $internalLinks = build_internal_links($postType);

    if (!empty($preFilteredLinks)) {
        $lines = [];
        foreach ($preFilteredLinks as $i => $l)
            $lines[] = ($i + 1) . '. Title: "' . addslashes($l['title']) . '" | URL: ' . $l['url'];
        $linkBlock = implode("\n", $lines);
        $linkInstruction = <<<LINKSEC

â”â”â”â” PRE-EXTRACTED OFFICIAL LINKS (USE ONLY THESE) â”â”â”â”
Use EXACTLY these URLs in the important_links array. Do NOT invent or modify URLs.

{$linkBlock}

LINKSEC;
    } else {
        $linkInstruction = <<<LINKSEC

â”â”â”â” LINKS NOTE â”â”â”â”
Extract only direct official government/recruitment URLs (.gov.in, .nic.in).
Do NOT include aggregator or third-party job portal links.

LINKSEC;
    }

    // â”€â”€ Type-specific config â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $typeCfg = [
        'admit_card'  => ['role'=>'admit card expert','title_fmt'=>'"[Org] [Exam] Admit Card [Year] – Download Hall Ticket"','struct'=>'Overview â†’ Exam Schedule â†’ How to Download â†’ Details on Hall Ticket â†’ Documents to Carry â†’ Important Links','cat'=>'Admit Card','faq'=>'download steps, login details, exam date, centre, documents to carry, objection, result date'],
        'result'      => ['role'=>'exam result analyst','title_fmt'=>'"[Org] [Exam] Result [Year] – Check Marks & Merit List"','struct'=>'Overview â†’ Result Highlights â†’ Cut-off Marks (category-wise) â†’ How to Check â†’ Merit List â†’ Next Steps','cat'=>'Result','faq'=>'how to check, cut-off, merit list, scorecard, re-evaluation, next steps'],
        'answer_key'  => ['role'=>'exam answer key analyst','title_fmt'=>'"[Org] [Exam] Answer Key [Year] – Download & Raise Objection"','struct'=>'Overview â†’ Highlights â†’ How to Download â†’ Raising Objection â†’ Fee â†’ Important Dates','cat'=>'Answer Key','faq'=>'download steps, objection process, fee, timeline, final key, result date'],
        'syllabus'    => ['role'=>'exam preparation expert','title_fmt'=>'"[Org] [Exam] Syllabus [Year] – Topic-wise Pattern & PDF"','struct'=>'Overview â†’ Exam Pattern (table) â†’ Subject-wise Syllabus â†’ Marking Scheme â†’ Recommended Books â†’ Preparation Tips','cat'=>'Syllabus','faq'=>'topics, exam pattern, marking scheme, negative marking, best books, strategy'],
        'scholarship' => ['role'=>'scholarship expert','title_fmt'=>'"[Org] [Scholarship] [Year] – Eligibility, Amount & Apply"','struct'=>'Overview â†’ Highlights â†’ Eligibility â†’ Award Amount â†’ How to Apply â†’ Required Documents â†’ Important Dates','cat'=>'Scholarship','faq'=>'eligibility, amount, how to apply, documents, last date, selection, disbursement'],
        'blog'        => ['role'=>'career guide writer','title_fmt'=>'"[Topic] – Complete Guide [Year]"','struct'=>'Introduction â†’ Key Points â†’ Detailed Explanation â†’ Tips & Advice â†’ Conclusion','cat'=>'Blog','faq'=>'main topic questions, eligibility, process, timeline, tips'],
        'job'         => ['role'=>'SEO content strategist','title_fmt'=>'"[Org Abbr] [Post Name] [Year] – Apply for [N] Posts"','struct'=>'Overview â†’ Key Highlights â†’ Vacancy Details â†’ Eligibility â†’ Important Dates â†’ Application Fee â†’ How to Apply â†’ Selection Process','cat'=>'Central Govt Jobs','faq'=>'eligibility, how to apply, last date, age limit, salary, selection, fee'],
    ];
    $cfg = $typeCfg[$postType] ?? $typeCfg['job'];

    // â”€â”€ Type-specific field guidance â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $extraNotes = '';
    if (in_array($postType, ['admit_card','result','answer_key','syllabus'])) {
        $extraNotes = "TYPE NOTE [{$postType}]: total_posts=0, salary/age/fee all 0, education=[], qualifications/skills/responsibilities=\"\".\n";
    } elseif ($postType === 'blog') {
        $extraNotes = "TYPE NOTE [blog]: total_posts=0, salary/age/fee all 0, education=[], organization can be empty.\n";
    } elseif ($postType === 'scholarship') {
        $extraNotes = "TYPE NOTE [scholarship]: total_posts=0, set salary=scholarship amount, fee usually 0, fill education from eligibility.\n";
    }

    return <<<PROMPT
You are a {$cfg['role']} for JobOne.in — India's top government job portal.
Analyze the provided content and return a FULLY SEO-OPTIMIZED JSON for a **{$postType}** post.
{$extraNotes}
â”â”â”â” FIELD INSTRUCTIONS â”â”â”â”

â‘  title (max 70 chars): {$cfg['title_fmt']}
â‘¡ type: MUST be exactly "{$postType}"
â‘¢ short_description (max 160 chars): concise summary for this post type.
â‘£ content (HTML: <h3><p><ul><li><a> only):
   Structure: {$cfg['struct']}
   — MANDATORY INTERNAL LINKS: You MUST embed AT LEAST 5 internal links from the list below naturally inside your content paragraphs.
     Insert them as inline anchor tags within sentences, e.g. "Candidates can also check <a href=\"https://jobone.in/results\">latest exam results</a> on our portal."
     DO NOT create a separate "Internal Links" or "Related Links" section. Weave them naturally into the text.
     If content has 0 internal links, the output is REJECTED.
     Available internal links (pick the most relevant for this {$postType}):
{$internalLinks}
   — DO NOT add "Important Links" section (auto-generated).
   — End with: <h3>📢 Stay Updated</h3><ul><li>🔵 <a href="{$tg}">Telegram @jobone2026</a></li><li>🟢 <a href="{$wa}">WhatsApp JobOne.in</a></li></ul>
â‘¤ organization: Full official name
â‘¥ state_name: Indian state OR "All India"
â‘¦ category_name: {$cfg['cat']}
â‘§ notification_date, start_date, end_date, last_date: YYYY-MM-DD or ""
deadline_time: Exact time of last date (e.g. "4:00 PM", "5:00 PM IST") or ""
interview_date_from: Interview/DV start date YYYY-MM-DD or ""
interview_date_to: Interview/DV end date YYYY-MM-DD or ""
waitlist_date: Date until which waitlist is valid YYYY-MM-DD or ""
age_max_obc: Max age for OBC (integer) or 0
age_max_sc: Max age for SC/ST (integer) or 0
age_max_ph: Max age for PwD/Divyang (integer) or 0
age_as_on_date: Date on which age is calculated YYYY-MM-DD or ""
age_relaxation_note: Any additional age relaxation note or ""
notification_pdf: Direct URL to official notification PDF or ""
gate_note: IMPORTANT — clarify if GATE score is only for shortlisting (not final merit). E.g. "GATE score used for shortlisting only; final selection via personal interview + medical". Leave "" if not applicable.
â‘¨ total_posts: integer or 0
â‘© salary: pay scale / scholarship amount or ""
â‘ª salary_min, salary_max: integers or 0
â‘« salary_type: salary|stipend|consolidated|pay_scale
â‘¬ online_form: https:// URL or ""
â‘­ age_min, age_max_gen: integer years or 0
â‘® fee_general, fee_obc, fee_sc_st, fee_women, fee_ph: integers or 0
â‘¯ selection_stages: array of strings or []
â‘° is_date_extended: boolean
â‘± important_links: array of {"title":"...","url":"https://..."}.
{$linkInstruction}
   âœ… GOOD: "Download Admit Card" | "Check Result" | "Official Notification PDF" | "Apply Online"
   â Œ BAD: "1" | "Link" | "Click Here"
â‘² tags: relevant subset of [cutoff,merit_list,final_result,admit_card,exam_date,answer_key,syllabus,new_vacancy,govt_job]
â‘³ education: relevant subset of [10th_pass,12th_pass,graduate,post_graduate,diploma,iti,btech,mtech,bsc,msc,bcom,mcom,ba,ma,mba,ca,llb,mbbs,phd,any_qualification] or []
ã‰‘ meta_title: SEO title with org, type, year and "| JobOne.in"
ã‰’ meta_description: 120–160 chars relevant to this post type
ã‰“ meta_keywords: MANDATORY 200+ comma-separated SEO keywords. This is CRITICAL for ranking. Generate keywords from ALL these categories:
   A) EXACT MATCH: org name + post name + year (e.g. "SSF Constable 2026", "SSF Tradesman Recruitment 2026")
   B) LONG-TAIL: "how to apply [org] [post] [year]", "[org] [post] eligibility", "[org] [post] salary", "[org] [post] syllabus", "[org] [post] age limit"
   C) HINDI TRANSLITERATION: write the org/post name in romanized Hindi (e.g. "sarkari naukri", "bharti", "sarkari result")
   D) YEAR COMBOS: combine every keyword with 2026, 2025-26, latest, new, upcoming
   E) RELATED SEARCHES: "govt jobs [state]", "[state] sarkari naukri [year]", "10th pass govt jobs", "[qualification] govt job"
   F) ACTION KEYWORDS: "apply online", "last date", "notification pdf", "admit card", "result", "selection process", "application form"
   G) ABBREVIATIONS: all common short forms of org name
   H) QUESTION KEYWORDS: "what is [post] salary", "is [org] [post] online or offline", "[org] [post] kab aayega"
   I) COMPETITOR KEYWORDS: "sarkari result [org]", "rojgar result [org]", "free job alert [post]"
   J) CATEGORY KEYWORDS: "police jobs [year]", "defence jobs", "central govt jobs [year]", "[state] police bharti"
   YOU MUST generate AT LEAST 200 keywords. If fewer than 200, the output is REJECTED.
ã‰” qualifications, skills, responsibilities: relevant text or ""
ã‰• faq: EXACTLY 7 objects {"question":"...","answer":"..."} — plain text only. Cover: {$cfg['faq']}

â” â” â” â”  OUTPUT RULES â” â” â” â” 
Return ONLY valid compact JSON. No markdown. The "type" field MUST be "{$postType}".
{"title":"","type":"{$postType}","short_description":"","content":"","organization":"","state_name":"","category_name":"","notification_date":"","start_date":"","end_date":"","last_date":"","deadline_time":"","interview_date_from":"","interview_date_to":"","waitlist_date":"","is_date_extended":false,"total_posts":0,"vacancy_gen":0,"vacancy_obc":0,"vacancy_sc":0,"vacancy_st":0,"vacancy_ews":0,"vacancy_ph":0,"salary":"","salary_min":0,"salary_max":0,"salary_type":"salary","age_min":0,"age_max_gen":0,"age_max_obc":0,"age_max_sc":0,"age_max_ph":0,"age_as_on_date":"","age_relaxation_note":"","fee_general":0,"fee_obc":0,"fee_sc_st":0,"fee_women":0,"fee_ph":0,"selection_stages":[],"online_form":"","notification_pdf":"","gate_note":"","important_links":[],"tags":[],"education":[],"meta_title":"","meta_description":"","meta_keywords":"","qualifications":"","skills":"","responsibilities":"","faq":[]}
PROMPT;
}

// Helper to send a clean JSON response (FIX #1: clears buffer first)
function send_json(mixed $data): never
{
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // â”€â”€ categories â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    case 'categories':
        send_json(curl_request(JOBONE_API . '/categories', 'GET', ['Accept: application/json']));

    // â”€â”€ states â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    case 'states':
        send_json(curl_request(JOBONE_API . '/states', 'GET', ['Accept: application/json']));

    // â”€â”€ scrape_url â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    case 'scrape_url':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $url = trim($input['url'] ?? '');
        if (!$url)
            send_json(['success' => false, 'message' => 'URL is required']);

        if (!preg_match('/^https?:\/\//i', $url))
            $url = 'https://' . ltrim($url, '/');
        if (!filter_var($url, FILTER_VALIDATE_URL))
            send_json(['success' => false, 'message' => 'Invalid URL format']);

        $raw = curl_request_raw($url);
        if (!$raw['success'])
            send_json($raw);

        $classified = extract_and_classify_links($raw['content'], $url);
        $officialLinks = $classified['official'];
        $skippedCount = count($classified['aggregator']) + count($classified['unknown']);

        foreach ($officialLinks as $idx => &$link)
            $link['title'] = infer_link_title($link['title'], $link['url'], $idx + 1);
        unset($link);

        $seen = [];
        $officialLinks = array_values(array_filter($officialLinks, function ($l) use (&$seen) {
            $key = strtolower($l['url']);
            if (isset($seen[$key]))
                return false;
            return $seen[$key] = true;
        }));

        $text = html_to_text($raw['content']);
        if (strlen($text) < 100)
            send_json(['success' => false, 'message' => 'Page content too short or blocked. Try pasting the text manually.']);

        send_json(['success' => true, 'text' => $text, 'chars' => strlen($text), 'official_links' => $officialLinks, 'skipped_count' => $skippedCount]);

    // â”€â”€ analyze â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    case 'analyze':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $rawText = trim($input['raw_text'] ?? '');
        $officialLinks = $input['official_links'] ?? [];
        $sourceUrl = $input['source_url'] ?? '';

        if (!$rawText)
            send_json(['success' => false, 'message' => 'raw_text is required']);

        $payload = json_encode([
            'model' => AI_MODEL,
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'system', 'content' => get_ai_prompt($officialLinks, $input['post_type'] ?? 'job')],
                ['role' => 'user', 'content' => "Analyze this " . ($input['post_type'] ?? 'job') . " notification and return the complete JSON with type=\"" . ($input['post_type'] ?? 'job') . "\":\n\n" . $rawText],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        $response = curl_request(AI_API_URL, 'POST', ['Content-Type: application/json', 'Authorization: Bearer ' . AI_API_KEY], $payload);
        if (isset($response['success']) && $response['success'] === false)
            send_json($response);
        if (isset($response['error']) && !isset($response['choices']))
            send_json(['success' => false, 'message' => $response['error']['message'] ?? 'API error', 'raw' => $response]);

        $aiText = $response['choices'][0]['message']['content'] ?? '';
        if (!$aiText)
            send_json(['success' => false, 'message' => 'AI returned empty response', 'raw' => $response]);

        $clean = trim(preg_replace('/```json|```/i', '', $aiText));
        $parsed = json_decode($clean, true);
        if ($parsed === null)
            $parsed = json_decode(repair_json($clean), true);
        if ($parsed === null)
            send_json(['success' => false, 'message' => 'Failed to parse AI response', 'raw' => $clean]);

        $parsed['total_posts'] = sanitize_total_posts($parsed['total_posts'] ?? 0);

        foreach (['online_form', 'apply_url'] as $urlField) {
            if (!empty($parsed[$urlField])) {
                $u = trim($parsed[$urlField]);
                if (!preg_match('/^https?:\/\//i', $u))
                    $u = 'https://' . ltrim($u, '/');
                $parsed[$urlField] = filter_var($u, FILTER_VALIDATE_URL) ? sanitize_apply_url($u) : '';
            }
        }
        $parsed['direct_apply'] = !empty($parsed['online_form']);
        if (empty($parsed['apply_url']) && !empty($parsed['online_form']))
            $parsed['apply_url'] = $parsed['online_form'];

        $parsed = correct_category($parsed);

        $detectSrc = ($parsed['content'] ?? '') . ' ' . ($parsed['qualifications'] ?? '') . ' ' . $rawText;
        $parsed['education'] = auto_detect_education($detectSrc, $parsed['education'] ?? []);
        $parsed['tags'] = auto_detect_tags($detectSrc, $parsed['tags'] ?? []);
        $parsed = correct_employment_type($parsed);

        // Merge + sanitize links
        $sourceDomain = $sourceUrl ? get_registrable_domain($sourceUrl) : '';
        $aiLinks = is_array($parsed['important_links'] ?? null) ? $parsed['important_links'] : [];
        $sanitizedAiLinks = [];
        foreach ($aiLinks as $l) {
            if (empty($l['url']))
                continue;
            $lu = trim($l['url']);
            if (!preg_match('/^https?:\/\//i', $lu))
                $lu = 'https://' . ltrim($lu, '/');
            if (!filter_var($lu, FILTER_VALIDATE_URL))
                continue;
            if (classify_domain($lu, $sourceDomain) === 'aggregator')
                continue;
            $sanitizedAiLinks[] = ['title' => $l['title'] ?? '', 'url' => $lu];
        }

        $merged = $officialLinks;
        $seenUrls = array_map(fn($l) => strtolower($l['url']), $merged);
        foreach ($sanitizedAiLinks as $l) {
            if (!in_array(strtolower($l['url']), $seenUrls, true)) {
                $merged[] = $l;
                $seenUrls[] = strtolower($l['url']);
            }
        }
        foreach ($merged as $idx => &$link)
            $link['title'] = infer_link_title($link['title'], $link['url'], $idx + 1);
        unset($link);

        // Override meta title & description
        $orgAbbr = !empty($parsed['organization']) ? explode(' ', $parsed['organization'])[0] : 'Govt';
        $postN = $parsed['post_name'] ?? $parsed['title'] ?? 'Job';
        $vCount = (int) ($parsed['total_posts'] ?? 0);
        $vStr = $vCount > 0 ? " – {$vCount} Posts" : "";
        $yearStr = date('Y');
        if (preg_match('/\b(20\d{2})\b/', $postN, $m))
            $yearStr = $m[1];
        $lastDateHint = !empty($parsed['last_date']) ? 'Apply by ' . date('d M', strtotime($parsed['last_date'])) : 'Apply Soon';
        $extendPrefix = !empty($parsed['is_date_extended']) ? '🔥 Date Extended – ' : '';

        $parsed['meta_title'] = "{$extendPrefix}{$orgAbbr} {$postN} Recruitment {$yearStr}{$vStr} | {$lastDateHint} | JobOne.in";
        if (strlen($parsed['meta_title']) > 80)
            $parsed['meta_title'] = substr($parsed['meta_title'], 0, 77) . '...';

        $eduRaw = !empty($parsed['education']) ? array_slice($parsed['education'], 0, 3) : ['Graduate'];
        $eduList = implode(', ', array_map(function($e) {
            return ucwords(str_replace('_', ' ', $e));
        }, $eduRaw));
        $lastDateFull = !empty($parsed['last_date']) ? date('d-m-Y', strtotime($parsed['last_date'])) : 'soon';
        $parsed['meta_description'] = "{$parsed['organization']} has released " . ($vCount > 0 ? $vCount : 'various') . " {$postN} vacancies for {$yearStr}. Eligibility: {$eduList}. Last date to apply: {$lastDateFull}. Check qualification, salary, selection process and direct apply link here.";
        if (strlen($parsed['meta_description']) > 160)
            $parsed['meta_description'] = substr($parsed['meta_description'], 0, 157) . '...';

        // Social links
        $hasTg = $hasWa = false;
        foreach ($merged as $l) {
            if (!empty($l['url']) && str_contains($l['url'], 't.me/jobone'))
                $hasTg = true;
            if (!empty($l['url']) && str_contains($l['url'], 'whatsapp.com/channel'))
                $hasWa = true;
        }
        if (!$hasTg)
            $merged[] = ['title' => '📢 Telegram Channel – @jobone2026', 'url' => TG_CHANNEL];
        if (!$hasWa)
            $merged[] = ['title' => '🟢 WhatsApp Channel – JobOne.in', 'url' => WA_CHANNEL];
        $parsed['important_links'] = $merged;

        // Build FAQ
        $faqData = is_array($parsed['faq'] ?? null) ? $parsed['faq'] : [];
        $faqHtml = build_faq_html($faqData);
        $faqSchema = generate_faq_schema($faqData);

        // Build job schema (no real slug yet)
        $jobSchema = generate_job_schema($parsed, $merged);

        // Strip AI-generated links/FAQ sections from content
        $parsed['content'] = preg_replace('/<h3[^>]*>\s*[🔎🔗]?\s*important\s+links.*?<\/h3>[\s\S]*?(?=<h3|$)/si', '', $parsed['content'] ?? '');
        $parsed['content'] = preg_replace('/<h3[^>]*>\s*[❓🙋]?\s*frequently\s+asked.*?<\/h3>[\s\S]*?(?=<h3|$)/si', '', $parsed['content'] ?? '');
        $parsed['content'] = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $parsed['content'] ?? '');

        // Assemble content
        $quickInfoHtml = build_quick_info_table($parsed);
        $datesHtml = build_dates_table($parsed);
        $feeHtml = build_fee_table($parsed);
        $vacancyHtml = build_vacancy_table($parsed);
        $selectionHtml = build_selection_stages($parsed);
        $linksHtml = build_links_table($parsed['important_links']);

        $socialMarker = '<h3>📢 Stay Updated';
        $content = rtrim($parsed['content'] ?? '');
        $headerBlocks = $quickInfoHtml . "\n" . $datesHtml . "\n" . $vacancyHtml . "\n" . $feeHtml . "\n" . $selectionHtml;

        if (str_contains($content, $socialMarker)) {
            $content = $headerBlocks . "\n" . str_replace(
                $socialMarker,
                $faqHtml . "\n" . $linksHtml . "\n" . $socialMarker,
                $content
            );
        } else {
            $content = $headerBlocks . "\n" . $content . "\n" . $faqHtml . "\n" . $linksHtml;
        }

        $content .= "\n"; 
        $parsed['content'] = $content;

        $ogTags = generate_og_tags($parsed, JOBONE_SITE_URL . '/preview');
        $kwCount = !empty($parsed['meta_keywords'])
            ? count(array_filter(array_map('trim', explode(',', $parsed['meta_keywords'])))) : 0;

        send_json([
            'success' => true,
            'data' => $parsed,
            'kw_count' => $kwCount,
            'faq_count' => count($faqData),
            'og_tags' => $ogTags,
        ]);

    // â”€â”€ post_job â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    case 'post_job':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input)
            send_json(['success' => false, 'message' => 'Invalid request body']);

        foreach (['title', 'type', 'short_description', 'content', 'category_id'] as $field) {
            if (empty($input[$field]))
                send_json(['success' => false, 'message' => "Required field missing: {$field}"]);
        }

        foreach (['state_id', 'notification_date', 'start_date', 'end_date', 'last_date', 'salary', 'online_form', 'final_result'] as $f) {
            if (isset($input[$f]) && $input[$f] === '')
                unset($input[$f]);
        }
        if (empty($input['total_posts']))
            unset($input['total_posts']);

        if (isset($input['important_links'])) {
            $input['important_links'] = array_values(array_filter(
                $input['important_links'],
                fn($l) => !empty($l['title']) && !empty($l['url'])
            ));
        }

        $sourceUrl = $input['source_url'] ?? '';
        unset($input['state_name'], $input['category_name'], $input['source_url'], $input['qualifications_text'], $input['og_tags']);

        $intFields = [
            'total_posts',
            'age_min',
            'age_max_gen',
            'age_max_obc',
            'age_max_sc',
            'age_max_ph',
            'age_max_ex_serviceman',
            'fee_general',
            'fee_obc',
            'fee_sc_st',
            'fee_women',
            'fee_ph',
            'salary_min',
            'salary_max',
            'vacancy_gen',
            'vacancy_obc',
            'vacancy_sc',
            'vacancy_st',
            'vacancy_ews',
            'vacancy_ph'
        ];
        foreach ($intFields as $intField) {
            if (isset($input[$intField]))
                $input[$intField] = (int) $input[$intField];
        }

        if (empty($input['slug']))
            $input['slug'] = generate_slug($input);
        if (isset($input['direct_apply']))
            $input['direct_apply'] = (bool) $input['direct_apply'];

        // ── AUTO-FILL SAFETY NET ─────────────────────────────────────────────
        // If frontend sends category_name but no category_id, resolve it
        $categoryNameMap = [
            'Banking'             => 1,
            'Banking Jobs'        => 1,
            'Railways'            => 2,
            'Railway Jobs'        => 2,
            'SSC'                 => 3,
            'SSC Jobs'            => 3,
            'UPSC'                => 4,
            'UPSC Jobs'           => 4,
            'State PSC'           => 5,
            'State PSC Jobs'      => 5,
            'Defence'             => 6,
            'Defence Jobs'        => 6,
            'State Govt'          => 7,
            'State Govt Jobs'     => 7,
            'Police'              => 8,
            'Police Jobs'         => 8,
            'SSB'                 => 9,
            'SSB Jobs'            => 9,
            'Central PSU'         => 12,
            'PSU Jobs'            => 12,
            'Central Govt Jobs'   => 14,
            'Central Government'  => 14,
            'Central University'  => 15,
            'University Jobs'     => 15,
            'Armed Forces'        => 16,
            'Armed Forces Jobs'   => 16,
            'Paramilitary'        => 17,
            'Paramilitary Jobs'   => 17,
            'Apprentice/Trainee'  => 18,
            'Apprentice Jobs'     => 18,
        ];
        if (empty($input['category_id']) && !empty($input['category_name'])) {
            $input['category_id'] = $categoryNameMap[$input['category_name']] ?? 14; // default: Central Govt Jobs
        }
        if (empty($input['category_id'])) {
            $input['category_id'] = 14; // hard fallback: Central Government
        }

        // Auto-detect organization from title if missing
        if (empty($input['organization'])) {
            $titleForOrg = $input['title'] ?? '';
            // Known abbreviation → full name map
            $knownOrgs = [
                'SSC'   => 'Staff Selection Commission',
                'UPSC'  => 'Union Public Service Commission',
                'RRB'   => 'Railway Recruitment Board',
                'IBPS'  => 'Institute of Banking Personnel Selection',
                'SBI'   => 'State Bank of India',
                'RBI'   => 'Reserve Bank of India',
                'ESIC'  => 'Employees State Insurance Corporation',
                'EPFO'  => 'Employees Provident Fund Organisation',
                'NHB'   => 'National Housing Bank',
                'SSB'   => 'Services Selection Board',
                'DRDO'  => 'Defence Research and Development Organisation',
                'ISRO'  => 'Indian Space Research Organisation',
                'BARC'  => 'Bhabha Atomic Research Centre',
                'CRPF'  => 'Central Reserve Police Force',
                'BSF'   => 'Border Security Force',
                'CISF'  => 'Central Industrial Security Force',
                'ITBP'  => 'Indo-Tibetan Border Police',
                'SSF'   => 'Secretariat Security Force',
                'NTPC'  => 'National Thermal Power Corporation',
                'BHEL'  => 'Bharat Heavy Electricals Limited',
                'ONGC'  => 'Oil and Natural Gas Corporation',
                'BPCL'  => 'Bharat Petroleum Corporation Limited',
                'IOCL'  => 'Indian Oil Corporation Limited',
                'HAL'   => 'Hindustan Aeronautics Limited',
                'AAI'   => 'Airports Authority of India',
                'NHAI'  => 'National Highways Authority of India',
            ];
            // Extract org: strip trailing recruitment/vacancy/job keywords
            $orgRaw = preg_replace('/\s*(recruitment|vacancy|vacancies|bharti|jobs?|notification|apply|online|posts?|hiring|\d{4}|–|-).*/i', '', $titleForOrg);
            $orgRaw = trim($orgRaw);
            // Check if first word matches a known abbreviation
            $firstWord = strtoupper(preg_replace('/[^A-Za-z]/', '', explode(' ', $orgRaw)[0] ?? ''));
            if ($firstWord && isset($knownOrgs[$firstWord])) {
                $input['organization'] = $knownOrgs[$firstWord];
            } elseif (strlen($orgRaw) >= 3) {
                $input['organization'] = $orgRaw;
            } else {
                $input['organization'] = 'Government of India';
            }
        }

        // Auto-detect education from content if empty/missing
        $detectSrc = ($input['title'] ?? '') . ' ' . ($input['short_description'] ?? '') . ' ' . strip_tags($input['content'] ?? '');
        if (empty($input['education'])) {
            $input['education'] = auto_detect_education($detectSrc, []);
        }

        // Auto-detect tags from content if empty/missing
        if (empty($input['tags'])) {
            $detectedTags = auto_detect_tags($detectSrc, []);
            // Always add 'govt_job' and 'new_vacancy' for job type posts
            $defaultTags = ['govt_job', 'new_vacancy'];
            $type = $input['type'] ?? 'job';
            if ($type === 'admit_card') $defaultTags = ['admit_card'];
            elseif ($type === 'result')  $defaultTags = ['final_result'];
            elseif ($type === 'answer_key') $defaultTags = ['answer_key'];
            elseif ($type === 'syllabus')   $defaultTags = ['syllabus'];
            $input['tags'] = array_unique(array_merge($defaultTags, $detectedTags));
        }

        // Auto-correct category from org/title keywords
        $inputForCorrection = [
            'organization'    => $input['organization'] ?? '',
            'title'           => $input['title'] ?? '',
            'short_description' => $input['short_description'] ?? '',
            'category_name'   => $input['category_name'] ?? '',
            'state_name'      => $input['state_name'] ?? '',
        ];
        $corrected = correct_category($inputForCorrection);
        // Map corrected category_name back to id
        if (isset($corrected['category_name']) && isset($categoryNameMap[$corrected['category_name']])) {
            $input['category_id'] = $categoryNameMap[$corrected['category_name']];
        }

        // Auto-correct salary type (stipend for trainees, apprentices)
        $input = correct_employment_type($input);

        // Ensure meta_keywords has something if empty
        if (empty($input['meta_keywords'])) {
            $org    = $input['organization'] ?? '';
            $title  = $input['title'] ?? '';
            $year   = date('Y');
            $state  = $input['state_name'] ?? 'India';
            $input['meta_keywords'] = implode(', ', array_filter([
                $org, $title, "$org recruitment $year", "$title $year",
                'sarkari naukri', 'govt jobs', "$state govt jobs $year",
                'apply online', 'last date', 'notification', 'vacancy',
                'sarkari result', 'free job alert',
            ]));
        }
        // ── END AUTO-FILL ─────────────────────────────────────────────────────



        // Auto-inject notification_pdf into important_links if provided
        if (!empty($input['notification_pdf']) && filter_var($input['notification_pdf'], FILTER_VALIDATE_URL)) {
            $pdfExists = false;
            foreach ($input['important_links'] ?? [] as $il) {
                if (!empty($il['url']) && strtolower($il['url']) === strtolower($input['notification_pdf'])) {
                    $pdfExists = true; break;
                }
            }
            if (!$pdfExists) {
                array_unshift($input['important_links'], [
                    'title' => 'Official Notification PDF',
                    'url'   => $input['notification_pdf'],
                ]);
            }
        }

        $postResult = curl_request(
            JOBONE_API . '/posts',
            'POST',
            ['Authorization: Bearer ' . JOBONE_TOKEN, 'Content-Type: application/json', 'Accept: application/json'],
            json_encode($input)
        );

        if (!empty($postResult['success']) || !empty($postResult['data']['id'])) {
            $postData = $postResult['data'] ?? $postResult;
            $slug = $postData['slug'] ?? '';
            $jobUrl = $slug ? JOBONE_SITE_URL . '/' . $slug : JOBONE_SITE_URL;

            $input['id'] = $postData['id'] ?? '';
            $input['slug'] = $slug;

            $pingResult = ping_indexnow($jobUrl);
            $ogTags = generate_og_tags(array_merge($input, $postData), $jobUrl);
            $mergedData = array_merge($input, $postData);
            $finalSchema = generate_job_schema($mergedData, $input['important_links'] ?? []);

            $postResult['indexnow'] = $pingResult;
            $postResult['og_tags'] = $ogTags;
            $postResult['job_schema'] = $finalSchema;
            $postResult['job_url'] = $jobUrl;
        }

        send_json($postResult);

    // â”€â”€ 404 â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    default:
        http_response_code(404);
        send_json(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
}

// â”€â”€ JSON repair â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function repair_json(string $s): string
{
    $s = rtrim($s, ', ');
    $stack = [];
    $inStr = false;
    $esc = false;
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $ch = $s[$i];
        if ($esc) {
            $esc = false;
            continue;
        }
        if ($ch === '\\' && $inStr) {
            $esc = true;
            continue;
        }
        if ($ch === '"') {
            $inStr = !$inStr;
            continue;
        }
        if ($inStr)
            continue;
        if ($ch === '{')
            $stack[] = '}';
        elseif ($ch === '[')
            $stack[] = ']';
        elseif ($ch === '}' || $ch === ']')
            array_pop($stack);
    }
    if ($inStr)
        $s .= '"';
    return $s . implode('', array_reverse($stack));
}
