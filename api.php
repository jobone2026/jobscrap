<?php
// ── JobOne Publisher — PHP API Proxy ─────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── CONSTANTS ─────────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

// Load secrets from Laravel .env file if available (Live server: /var/www/jobone/.env)
$envPath = __DIR__ . '/../../.env';
$env = file_exists($envPath) ? parse_ini_file($envPath) : [];

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

define('AI_MODEL', 'gpt-4o-mini');
define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('AI_API_KEY', $env['OPENAI_API_KEY'] ?? 'your_openai_key_here');

define('TG_CHANNEL', 'https://t.me/jobone2026');
define('WA_CHANNEL', 'https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22');

// ── IndexNow ─────────────────────────────────────────────────────────────────
// 1. Generate a GUID key and save it as /public_html/<key>.txt on your server.
// 2. Paste that same key here.
// 3. Submit once to: https://www.indexnow.org/indexnow?url=https://jobone.in/<key>.txt&key=<key>
define('INDEXNOW_KEY', 'YOUR_32CHAR_GUID_KEY_HERE'); // ← replace this
define('INDEXNOW_HOST', 'jobone.in');

// ═══════════════════════════════════════════════════════════════════════════════
// ── DOMAIN CLASSIFIER ─────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

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

// ═══════════════════════════════════════════════════════════════════════════════
// ── LINK EXTRACTOR ────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

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

// ═══════════════════════════════════════════════════════════════════════════════
// ── CURL HELPERS ──────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

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
    if ($decoded === null)
        return ['success' => false, 'message' => 'Invalid JSON from upstream', 'raw' => $response, 'http_code' => $httpCode];
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
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*;q=0.8', 'Accept-Language: en-IN,en;q=0.9', 'Accept-Encoding: identity', 'Cache-Control: no-cache'],
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

// ═══════════════════════════════════════════════════════════════════════════════
// ── LINKS TABLE BUILDER ────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

function build_links_table(array $links): string
{
    $iconMap = [
        'apply online' => '📝',
        'register' => '📝',
        'application form' => '📝',
        'admit card' => '🪪',
        'hall ticket' => '🪪',
        'call letter' => '🪪',
        'final result' => '🏆',
        'merit list' => '🏆',
        'selection list' => '🏆',
        'provisional result' => '📊',
        'scorecard' => '📊',
        'marks' => '📊',
        'result' => '📊',
        'syllabus' => '📚',
        'exam pattern' => '📚',
        'answer key' => '🔑',
        'cut-off' => '✂️',
        'cut off' => '✂️',
        'cutoff' => '✂️',
        'interview' => '🗣️',
        'walk-in' => '🗣️',
        'walk in' => '🗣️',
        'official website' => '🏛️',
        'official' => '🏛️',
        'notification' => '📄',
        'pdf' => '📄',
        'telegram' => '📢',
        'whatsapp' => '📢',
        'channel' => '📢',
        'schedule' => '📅',
        'date' => '📅',
        'extension' => '📅',
        'fee' => '💳',
        'payment' => '💳',
        'login' => '🔐',
        'status' => '🔍',
        'download' => '⬇️',
    ];
    $getIcon = function (string $title) use ($iconMap): string {
        $lower = mb_strtolower($title);
        foreach ($iconMap as $kw => $icon) {
            if (str_contains($lower, $kw))
                return $icon;
        }
        return '🔗';
    };
    $validLinks = array_values(array_filter($links, fn($l) => !empty($l['url'])));
    if (empty($validLinks))
        return '';

    $rows = '';
    foreach ($validLinks as $i => $link) {
        $title = htmlspecialchars($link['title'] ?? 'Important Link');
        $url = htmlspecialchars($link['url']);
        $icon = $getIcon($link['title'] ?? '');
        $rowBg = $i % 2 === 0 ? '#ffffff' : '#f4f7ff';
        $isSocial = str_contains(strtolower($link['url']), 't.me/') || str_contains(strtolower($link['url']), 'whatsapp.com/channel');
        $btnStyle = $isSocial ? 'background:linear-gradient(135deg,#229ED9,#0d7abf);' : 'background:linear-gradient(135deg,#1a6ef5,#5b4ceb);';
        $rows .= "\n        <tr style=\"background:{$rowBg};\">
            <td style=\"padding:13px 18px;border-bottom:1px solid #e4e9f2;font-size:13px;font-weight:600;color:#0f1724;vertical-align:middle;\">{$icon}&nbsp; {$title}</td>
            <td style=\"padding:10px 18px;border-bottom:1px solid #e4e9f2;text-align:center;vertical-align:middle;\">
                <a href=\"{$url}\" target=\"_blank\" rel=\"noreferrer\" style=\"display:inline-block;{$btnStyle}color:#fff;border-radius:6px;padding:7px 20px;font-size:12px;font-weight:700;text-decoration:none;\">Click Here ↗</a>
            </td>
        </tr>";
    }
    return '
<h3>📎 Important Links</h3>
<table style="width:100%;border-collapse:collapse;border:1px solid #e4e9f2;border-radius:12px;overflow:hidden;margin:14px 0;font-family:system-ui,sans-serif;box-shadow:0 2px 12px rgba(15,23,36,.07);">
    <thead>
        <tr style="background:linear-gradient(135deg,#1a6ef5 0%,#5b4ceb 100%);">
            <th style="padding:14px 18px;color:#fff;font-size:13px;font-weight:700;text-align:left;">📋 Description</th>
            <th style="padding:14px 18px;color:#fff;font-size:13px;font-weight:700;text-align:center;width:150px;">🔗 Direct Link</th>
        </tr>
    </thead>
    <tbody>' . $rows . '
    </tbody>
</table>';
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── SCHEMA GENERATORS ─────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Build a complete JobPosting JSON-LD schema (all Google-required + recommended fields).
 * Returns a ready-to-inject <script> tag string.
 */
function generate_job_schema(array $p, array $importantLinks = []): string
{
    $now = date('c');
    $postSlug = $p['slug'] ?? '';
    $jobUrl = $postSlug ? JOBONE_SITE_URL . '/' . $postSlug : JOBONE_SITE_URL;

    // ── Hiring organisation ──────────────────────────────────────────────────
    $org = [
        '@type' => 'Organization',
        'name' => $p['organization'] ?: JOBONE_SITE_NAME,
        'sameAs' => JOBONE_SITE_URL,
    ];
    // If the official website link exists, use it as sameAs
    foreach ($importantLinks as $l) {
        if (!empty($l['url']) && preg_match('/official.?website|official.?site/i', $l['title'] ?? '')) {
            $org['sameAs'] = $l['url'];
            break;
        }
    }

    // ── Location ─────────────────────────────────────────────────────────────
    $region = !empty($p['state_name']) && $p['state_name'] !== 'All India' ? $p['state_name'] : null;
    $location = [
        '@type' => 'Place',
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'addressCountry' => 'IN',
            'addressRegion' => $region,
        ]),
    ];

    // ── Salary ───────────────────────────────────────────────────────────────
    $salaryBlock = null;
    if (!empty($p['salary'])) {
        $salaryBlock = [
            '@type' => 'MonetaryAmount',
            'currency' => 'INR',
            'value' => ['@type' => 'QuantitativeValue', 'description' => $p['salary'], 'unitText' => 'YEAR'],
        ];
    }

    // ── Education requirements from education chips ───────────────────────────
    $eduMap = [
        '10th_pass' => '10th Pass (Matriculation)',
        '12th_pass' => '12th Pass (Intermediate)',
        'graduate' => "Bachelor's Degree",
        'post_graduate' => "Master's Degree",
        'diploma' => 'Diploma',
        'iti' => 'ITI Certificate',
        'btech' => 'B.Tech / B.E.',
        'mtech' => 'M.Tech / M.E.',
        'mbbs' => 'MBBS',
        'bds' => 'BDS',
        'llb' => 'LLB (Law)',
        'ca' => 'Chartered Accountant (CA)',
        'phd' => 'PhD / Doctorate',
        'any_qualification' => 'Any Graduate',
    ];
    $eduReqs = [];
    foreach (($p['education'] ?? []) as $e) {
        if (isset($eduMap[$e]))
            $eduReqs[] = $eduMap[$e];
    }

    // ── Direct apply flag ─────────────────────────────────────────────────────
    $directApply = (bool)($p['direct_apply'] ?? !empty($p['apply_url'] ?? $p['online_form'] ?? ''));

    // ── Employment type ──────────────────────────────────────────────────────
    $typeMap = [
        'job'        => 'FULL_TIME',
        'admit_card' => 'FULL_TIME',
        'result'     => 'FULL_TIME',
        'syllabus'   => 'FULL_TIME',
        'scholarship'=> 'PART_TIME',
    ];
    $empType = $typeMap[$p['type'] ?? 'job'] ?? 'FULL_TIME';
    // Override for stipend/trainee/intern roles
    if (($p['salary_type'] ?? '') === 'stipend') $empType = 'OTHER';

    // ── Build schema object ──────────────────────────────────────────────────
    $schema = [
        '@context' => 'https://schema.org/',
        '@type' => 'JobPosting',
        'title' => $p['title'] ?? '',
        'description' => $p['short_description'] ?? '',
        'url' => $jobUrl,
        'datePosted' => $p['notification_date'] ?: date('Y-m-d'),
        'dateModified' => $now,                   // ← freshness signal
        'employmentType' => $empType,
        'directApply' => $directApply,
        'hiringOrganization' => $org,
        'jobLocation' => $location,
        'jobBenefits' => 'Government employment benefits: job security, pension, health insurance, HRA, TA, DA, medical allowance',
        'occupationalCategory' => '11-1000.00',           // Managers / Officials (O*NET)
        'industry' => 'Government / Public Sector',
        'workHours' => '8 hours/day, 5 days/week',
    ];

    // Optional recommended fields — only emit if values exist
    if (!empty($p['last_date']))
        $schema['validThrough'] = $p['last_date'] . 'T23:59:59+05:30';
    else
        $schema['validThrough'] = date('Y-m-d', strtotime('+90 days')) . 'T23:59:59+05:30';
    if (!empty($p['start_date']))
        $schema['jobStartDate'] = $p['start_date'];
    if ($salaryBlock)
        $schema['baseSalary'] = $salaryBlock;
    if (!empty($p['total_posts']))
        $schema['totalJobOpenings'] = (int) $p['total_posts'];
    $applyLink = $p['apply_url'] ?? $p['online_form'] ?? '';
    if (!empty($applyLink))
        $schema['applicationContact'] = ['@type' => 'ContactPoint', 'contactType' => 'Apply Online', 'url' => $applyLink];

    // Qualifications block — build from education + freetext qualifications
    $qualParts = [];
    if (!empty($eduReqs))
        $qualParts[] = 'Education: ' . implode(', ', $eduReqs);
    if (!empty($p['qualifications']))
        $qualParts[] = $p['qualifications'];
    if (!empty($qualParts)) {
        $schema['qualifications'] = implode('. ', $qualParts);
        $schema['educationRequirements'] = ['@type' => 'EducationalOccupationalCredential', 'credentialCategory' => $eduReqs[0] ?? 'Graduate'];
    }

    // Skills
    if (!empty($p['skills']))
        $schema['skills'] = $p['skills'];

    // Responsibilities
    if (!empty($p['responsibilities']))
        $schema['responsibilities'] = $p['responsibilities'];

    // Identifier (Post ID from the API response)
    if (!empty($p['id'])) {
        $schema['identifier'] = ['@type' => 'PropertyValue', 'name' => JOBONE_SITE_NAME, 'value' => (string) $p['id']];
    }

    // ── Breadcrumb embedded inside same script tag (cleaner than two tags) ──
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
 * Build FAQPage JSON-LD from an array of {question, answer} pairs.
 * Returns a ready-to-inject <script> tag string.
 */
function generate_faq_schema(array $faq): string
{
    if (empty($faq))
        return '';
    $items = [];
    foreach ($faq as $item) {
        if (empty($item['question']) || empty($item['answer']))
            continue;
        $items[] = [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
        ];
    }
    if (empty($items))
        return '';
    $schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items];
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

/**
 * Build a HTML FAQ section from the faq array — injected into content so
 * users can also read it, and Google sees it as visible on-page text.
 */
function build_faq_html(array $faq): string
{
    if (empty($faq))
        return '';
    $items = '';
    foreach ($faq as $i => $item) {
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
    return '
<h3>❓ Frequently Asked Questions (FAQ)</h3>
<div style="margin:14px 0;">' . $items . '</div>';
}

/**
 * Build Open Graph + Twitter Card meta tags.
 * Returns an HTML string to be emitted in <head> — pass it back to the
 * frontend so it can store / display it for copy-paste into the CMS head.
 */
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

// ═══════════════════════════════════════════════════════════════════════════════
// ── INDEXNOW PING ─────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Notify Google, Bing, and Yandex via IndexNow immediately after a post goes live.
 * Returns an array with success status and engine responses.
 */
function ping_indexnow(string $jobUrl): array
{
    if (INDEXNOW_KEY === 'YOUR_32CHAR_GUID_KEY_HERE') {
        return ['skipped' => true, 'reason' => 'IndexNow key not configured'];
    }

    $payload = json_encode([
        'host' => INDEXNOW_HOST,
        'key' => INDEXNOW_KEY,
        'keyLocation' => JOBONE_SITE_URL . '/' . INDEXNOW_KEY . '.txt',
        'urlList' => [$jobUrl],
    ]);

    $headers = ['Content-Type: application/json; charset=utf-8'];
    $engines = [
        'indexnow' => 'https://api.indexnow.org/indexnow',
        'bing' => 'https://www.bing.com/indexnow',
    ];

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
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $results[$name] = ['http_code' => $code, 'error' => $err ?: null, 'success' => $code === 200 || $code === 202];
    }
    return ['pinged' => true, 'url' => $jobUrl, 'results' => $results];
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── POST-PARSE HELPERS ────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

function sanitize_total_posts(mixed $val): int
{
    if (is_int($val) && $val >= 0) return $val;
    if (is_numeric($val)) return max(0, (int)$val);
    if (preg_match('/(\d+)/', (string)$val, $m)) return (int)$m[1];
    return 0; // "as per requirement" / "various" → 0
}

function sanitize_apply_url(string $url): string
{
    if (!$url) return '';
    $p = parse_url($url);
    $path = rtrim($p['path'] ?? '/', '/');
    $query = $p['query'] ?? '';
    // Reject bare homepages (path is empty/root and no query string)
    if (($path === '' || $path === '') && !$query) return '';
    return $url;
}

function auto_detect_education(string $content, array $current): array
{
    if (!empty($current)) return $current;
    $t = strtolower($content);
    $map = [
        'phd'           => '/ph\.?d\b|doctorate|doctoral/',
        'mba'           => '/\bmba\b|pgdm|post.?graduate.?diploma.?manage/',
        'llm'           => '/\bllm\b|master.?of.?law/',
        'llb'           => '/\bllb\b|bachelor.?of.?law|law.?graduate/',
        'mbbs'          => '/\bmbbs\b|bachelor.?of.?medicine/',
        'mtech'         => '/\bm\.?tech\b|\bm\.?e\.\b|master.?of.?technology/',
        'mpharm'        => '/\bm\.?pharm\b/',
        'bpharm'        => '/\bb\.?pharm\b/',
        'msc'           => '/\bm\.?sc\b|master.?of.?science/',
        'mcom'          => '/\bm\.?com\b|master.?of.?commerce/',
        'ma'            => '/\bm\.?a\.\b|master.?of.?arts/',
        'mba'           => '/\bmba\b|pgdm/',
        'ca'            => '/chartered.?accountant|\bca\b/',
        'cs'            => '/company.?secretary|\bcs\b/',
        'cma'           => '/\bcma\b|cost.?accountant/',
        'post_graduate'  => '/post.?graduate|master.?degree|\bm\.?sc\b|\bm\.?com\b|\bm\.?a\.\b|\bmca\b/',
        'btech'         => '/\bb\.?tech\b|\bb\.?e\.\b|bachelor.?of.?engineering|bachelor.?of.?technology/',
        'bsc'           => '/\bb\.?sc\b|bachelor.?of.?science/',
        'bcom'          => '/\bb\.?com\b|bachelor.?of.?commerce/',
        'ba'            => '/\bb\.?a\.\b|bachelor.?of.?arts/',
        'bed'           => '/\bb\.?ed\b|bachelor.?of.?education/',
        'nursing'       => '/\bnursing\b|\bgnm\b|\bbnsc\b/',
        'graduate'      => '/\bgraduate\b|graduation|bachelor.?degree|degree.?holder/',
        'diploma'       => '/\bdiploma\b/',
        'iti'           => '/\biti\b|industrial.?training.?institute/',
        '12th_pass'     => '/12th|hsc|higher.?secondary|intermediate|10\+2/',
        '10th_pass'     => '/10th|ssc|matriculation|\bssle\b|secondary.?school/',
    ];
    $detected = [];
    foreach ($map as $chip => $rx) {
        if (preg_match($rx, $t)) $detected[] = $chip;
    }
    // Ensure parent chip present
    if (array_intersect(['mba','mtech','mpharm','llm','msc','mcom','ma'], $detected)
        && !in_array('post_graduate', $detected)) $detected[] = 'post_graduate';
    if (array_intersect(['btech','bsc','bcom','ba','bpharm','bed','nursing'], $detected)
        && !in_array('graduate', $detected)) $detected[] = 'graduate';
    $detected = array_unique($detected);
    return $detected ?: ['graduate'];
}

function auto_detect_tags(string $content, array $current): array
{
    if (!empty($current)) return $current;
    $t = strtolower($content);
    $map = [
        'cutoff'             => '/cut.?off|cutoff/',
        'merit_list'         => '/merit.?list/',
        'selection_list'     => '/selection.?list/',
        'final_result'       => '/final.?result/',
        'provisional_result' => '/provisional.?result/',
        'revised_result'     => '/revised.?result/',
        'scorecard'          => '/scorecard|score.?card/',
        'marks'              => '/marks.?(released|published|available)/',
    ];
    $out = [];
    foreach ($map as $tag => $rx) {
        if (preg_match($rx, $t)) $out[] = $tag;
    }
    return $out;
}

function correct_category(array $p): array
{
    $combined = strtolower(($p['organization'] ?? '') . ' ' . ($p['title'] ?? '') . ' ' . ($p['short_description'] ?? ''));
    $psu = ['iffco','bpcl','hpcl','iocl','ongc','gail','ntpc','bhel','sail','hal','drdo','isro',
            'barc','npcil','coalindia','coal india','power grid','powergrid','rvnl','aai','concor',
            'rites','ircon','irctc','nhpc','nlc','mecl','bsnl','mtnl','nhai','hecl','beml',
            'public sector undertaking','central public sector','psu'];
    foreach ($psu as $kw) {
        if (str_contains($combined, $kw)) {
            $p['category_name'] = 'PSU Jobs';
            $p['state_name']    = 'All India';
            return $p;
        }
    }
    $central = ['ministry of','department of','government of india','high court','supreme court',
                'upsc','staff selection','rrb ','rail','ibps','reserve bank','rbi ','esic','epfo',
                'central government','union government'];
    if (($p['category_name'] ?? '') === 'State Govt Jobs') {
        foreach ($central as $kw) {
            if (str_contains($combined, $kw)) {
                $p['category_name'] = 'Central Govt Jobs';
                $p['state_name']    = 'All India';
                return $p;
            }
        }
    }
    return $p;
}

function correct_employment_type(array $p): array
{
    $salType = strtolower($p['salary_type'] ?? 'salary');
    $salary  = strtolower($p['salary'] ?? '');
    $title   = strtolower($p['title'] ?? '');
    if ($salType === 'stipend'
        || str_contains($salary, 'stipend')
        || preg_match('/trainee|intern|apprentice/', $title)) {
        $p['salary_type'] = 'stipend';
        if (!empty($p['salary']) && !str_contains(strtolower($p['salary']), 'stipend')) {
            $p['salary'] = 'Stipend: ' . $p['salary'];
        }
    }
    return $p;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── AI PROMPT ─────────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

function get_ai_prompt(array $preFilteredLinks = []): string
{
    $tg = TG_CHANNEL;
    $wa = WA_CHANNEL;

    $linkInstruction = '';
    if (!empty($preFilteredLinks)) {
        $lines = [];
        foreach ($preFilteredLinks as $i => $l) {
            $lines[] = ($i + 1) . '. Title: "' . addslashes($l['title']) . '" | URL: ' . $l['url'];
        }
        $linkBlock = implode("\n", $lines);
        $linkInstruction = <<<LINKSEC

━━━━ PRE-EXTRACTED OFFICIAL LINKS (USE ONLY THESE) ━━━━
Use EXACTLY these URLs in the important_links array. Do NOT invent or modify URLs.

{$linkBlock}

LINKSEC;
    } else {
        $linkInstruction = <<<LINKSEC

━━━━ LINKS NOTE ━━━━
Extract only direct official government/recruitment URLs (.gov.in, .nic.in).
Do NOT include aggregator or third-party job portal links.

LINKSEC;
    }

    return <<<PROMPT
You are a senior SEO content strategist for JobOne.in — India's top government job portal.
Analyze the provided job notification and return a FULLY SEO-OPTIMIZED, structured JSON object.

━━━━ FIELD INSTRUCTIONS ━━━━

① title (SEO-optimized, max 70 chars):
   Format: "[Org Abbreviation] [Post Name] [Year] – Apply for [N] Posts"

② type: one of job|admit_card|result|answer_key|syllabus|blog|scholarship

③ short_description (max 160 chars): org name, post count, key dates, eligibility.

④ content (HTML only — <h3><p><ul><li><a> tags):
   Structure: Overview → Key Highlights (bullet list) → Vacancy Details → Eligibility → Important Dates → Application Fee → How to Apply → Selection Process
   — Embed 5–8 internal links using these hrefs:
       <a href="https://jobone.in/">Latest Government Jobs 2026</a>
       <a href="https://jobone.in/category/ssc-jobs">SSC Jobs 2026</a>
       <a href="https://jobone.in/category/railway-jobs">Railway Jobs 2026</a>
       <a href="https://jobone.in/category/bank-jobs">Bank Jobs 2026</a>
       <a href="https://jobone.in/category/upsc-jobs">UPSC Jobs 2026</a>
       <a href="https://jobone.in/category/engineering-jobs">Engineering Jobs 2026</a>
       <a href="https://jobone.in/category/state-govt-jobs">State Govt Jobs 2026</a>
       <a href="https://jobone.in/category/central-govt-jobs">Central Govt Jobs 2026</a>
       <a href="https://jobone.in/admit-card">Admit Card 2026</a>
       <a href="https://jobone.in/results">Results 2026</a>
       <a href="https://jobone.in/syllabus">Exam Syllabus</a>
   — DO NOT add any "Important Links" section — it will be auto-generated.
   — At the END of content add this EXACTLY:
       <h3>📢 Stay Updated — Join Our Channels</h3>
       <p>Never miss a government job update! Join the official JobOne.in channels:</p>
       <ul>
         <li>🔵 <a href="{$tg}" target="_blank" rel="noreferrer"><strong>Telegram Channel – @jobone2026</strong></a> — Instant job alerts</li>
         <li>🟢 <a href="{$wa}" target="_blank" rel="noreferrer"><strong>WhatsApp Channel – JobOne.in</strong></a> — Daily updates</li>
       </ul>
       <p>Bookmark <a href="https://jobone.in/">JobOne.in</a> and stay ahead in your preparation journey.</p>

⑤ organization: Full official name of recruiting body
⑥ state_name: Exact Indian state OR "All India"
⑦ category_name: Central Govt Jobs | State Govt Jobs | Railway Jobs | Bank Jobs | SSC Jobs | UPSC Jobs | Police Jobs | Teaching Jobs | Defence Jobs | PSU Jobs | Engineering Jobs | Medical Jobs | Scholarship | Admit Card | Result | Answer Key | Syllabus
⑧ notification_date, start_date, end_date, last_date: YYYY-MM-DD or ""
⑨ total_posts: integer or 0
⑩ salary: Exact pay scale e.g. "Pay Level 6 (₹35,400 – ₹1,12,400)"
⑪ online_form: https:// URL or ""

⑫ important_links: Array of {"title":"...","url":"https://..."}.
{$linkInstruction}
   ✅ GOOD titles: "Official Notification PDF" | "Apply Online" | "Download Admit Card"
   ❌ BAD titles: "1" | "2" | "Link" | "Click Here"

⑬ tags: subset of [cutoff,merit_list,selection_list,final_result,provisional_result,revised_result,scorecard,marks]
⑭ education: subset of [10th_pass,12th_pass,graduate,post_graduate,diploma,iti,btech,mtech,bsc,msc,bcom,mcom,ba,ma,bba,mba,ca,cs,cma,llb,llm,mbbs,bds,bpharm,mpharm,nursing,bed,med,phd,any_qualification]
⑮ meta_title: max 60 chars, keyword-rich, include year
⑯ meta_description: max 160 chars, include CTA
⑰ meta_keywords: MINIMUM 200 comma-separated keywords (long-tails, state variations, Hindi terms, date-based, salary-based, jobone.in branded)

⑱ qualifications: One paragraph summarising educational qualifications, age limit, experience required.
   Example: "Candidates must be graduates aged 18–32 years. SC/ST get 5-year age relaxation. No prior experience required."

⑲ skills: Comma-separated list of relevant skills/competencies.
   Example: "General Awareness, Reasoning Ability, Quantitative Aptitude, English Language"

⑳ responsibilities: One paragraph describing the role/duties of the position.
   Example: "Perform clerical duties, maintain records, assist public with government services, coordinate with departments."

㉑ faq: Array of EXACTLY 7 objects {"question":"...","answer":"..."}.
   CRITICAL — Questions must match REAL Google search queries for this notification.
   Cover: eligibility, how to apply, last date, age limit, salary, selection process, application fee.
   Answers must be specific (actual dates, figures, steps from the notification).
   Example questions:
   - "What is the eligibility for [Org] [Post] [Year]?"
   - "How to apply for [Org] [Post] [Year] online?"
   - "What is the last date to apply for [Org] [Post]?"
   - "What is the age limit for [Org] [Post] [Year]?"
   - "What is the salary for [Org] [Post] [Year]?"
   - "What is the selection process for [Org] [Post]?"
   - "What is the application fee for [Org] [Post] [Year]?"

━━━━ OUTPUT RULES ━━━━
Return ONLY valid compact JSON. No markdown, no backticks, no comments.
{"title":"","type":"job","short_description":"","content":"","organization":"","state_name":"","category_name":"","notification_date":"","start_date":"","end_date":"","last_date":"","total_posts":0,"salary":"","online_form":"","important_links":[{"title":"","url":""}],"tags":[],"education":[],"meta_title":"","meta_description":"","meta_keywords":"","qualifications":"","skills":"","responsibilities":"","faq":[{"question":"","answer":""}]}
PROMPT;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── ROUTER ────────────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── categories ──────────────────────────────────────────────────────────
    case 'categories':
        echo json_encode(curl_request(JOBONE_API . '/categories', 'GET', ['Accept: application/json']));
        break;

    // ── states ───────────────────────────────────────────────────────────────
    case 'states':
        echo json_encode(curl_request(JOBONE_API . '/states', 'GET', ['Accept: application/json']));
        break;

    // ── scrape_url ───────────────────────────────────────────────────────────
    case 'scrape_url':
        $input = json_decode(file_get_contents('php://input'), true);
        $url = trim($input['url'] ?? '');
        if (!$url) {
            echo json_encode(['success' => false, 'message' => 'URL is required']);
            break;
        }
        if (!preg_match('/^https?:\/\//i', $url))
            $url = 'https://' . ltrim($url, '/');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
            break;
        }

        $raw = curl_request_raw($url);
        if (!$raw['success']) {
            echo json_encode($raw);
            break;
        }

        $classified = extract_and_classify_links($raw['content'], $url);
        $officialLinks = $classified['official'];
        $skippedCount = count($classified['aggregator']) + count($classified['unknown']);

        foreach ($officialLinks as $idx => &$link) {
            $link['title'] = infer_link_title($link['title'], $link['url'], $idx + 1);
        }
        unset($link);

        // De-duplicate by URL
        $seen = [];
        $officialLinks = array_values(array_filter($officialLinks, function ($l) use (&$seen) {
            $key = strtolower($l['url']);
            if (isset($seen[$key]))
                return false;
            return $seen[$key] = true;
        }));

        $text = html_to_text($raw['content']);
        if (strlen($text) < 100) {
            echo json_encode(['success' => false, 'message' => 'Page content too short or blocked. Try pasting the text manually.']);
            break;
        }
        echo json_encode(['success' => true, 'text' => $text, 'chars' => strlen($text), 'official_links' => $officialLinks, 'skipped_count' => $skippedCount]);
        break;

    // ── analyze ──────────────────────────────────────────────────────────────
    case 'analyze':
        $input = json_decode(file_get_contents('php://input'), true);
        $rawText = trim($input['raw_text'] ?? '');
        $officialLinks = $input['official_links'] ?? [];
        $sourceUrl = $input['source_url'] ?? '';

        if (!$rawText) {
            echo json_encode(['success' => false, 'message' => 'raw_text is required']);
            break;
        }

        $payload = json_encode([
            'model' => AI_MODEL,
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'system', 'content' => get_ai_prompt($officialLinks)],
                ['role' => 'user', 'content' => "Analyze this job notification and return the complete JSON:\n\n" . $rawText],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        $response = curl_request(AI_API_URL, 'POST', ['Content-Type: application/json', 'Authorization: Bearer ' . AI_API_KEY], $payload);
        if (isset($response['success']) && $response['success'] === false) {
            echo json_encode($response);
            break;
        }
        if (isset($response['error']) && !isset($response['choices'])) {
            echo json_encode(['success' => false, 'message' => $response['error']['message'] ?? 'API error', 'raw' => $response]);
            break;
        }

        $aiText = $response['choices'][0]['message']['content'] ?? '';
        if (!$aiText) {
            echo json_encode(['success' => false, 'message' => 'AI returned empty response', 'raw' => $response]);
            break;
        }

        $clean = trim(preg_replace('/```json|```/i', '', $aiText));
        $parsed = json_decode($clean, true);
        if ($parsed === null)
            $parsed = json_decode(repair_json($clean), true);
        if ($parsed === null) {
            echo json_encode(['success' => false, 'message' => 'Failed to parse AI response', 'raw' => $clean]);
            break;
        }

        // ── Sanitize online_form ─────────────────────────────────────────────
        // ── Sanitize total_posts → always integer
        $parsed['total_posts'] = sanitize_total_posts($parsed['total_posts'] ?? 0);

        // ── Sanitize online_form / apply_url → reject bare homepage URLs
        foreach (['online_form', 'apply_url'] as $urlField) {
            if (!empty($parsed[$urlField])) {
                $u = trim($parsed[$urlField]);
                if (!preg_match('/^https?:\/\//i', $u)) $u = 'https://' . ltrim($u, '/');
                $parsed[$urlField] = filter_var($u, FILTER_VALIDATE_URL) ? sanitize_apply_url($u) : '';
            }
        }
        $parsed['direct_apply'] = !empty($parsed['online_form']);
        if (empty($parsed['apply_url']) && !empty($parsed['online_form']))
            $parsed['apply_url'] = $parsed['online_form'];

        // ── Category correction (PSU / Central mislabelled as State)
        $parsed = correct_category($parsed);

        // ── Education: AI fallback → keyword detection from raw text
        $detectSrc = ($parsed['content'] ?? '') . ' ' . ($parsed['qualifications'] ?? '') . ' ' . $rawText;
        $parsed['education'] = auto_detect_education($detectSrc, $parsed['education'] ?? []);

        // ── Tags: keyword detection if AI returned none
        $parsed['tags'] = auto_detect_tags($detectSrc, $parsed['tags'] ?? []);

        // ── Employment type: detect stipend/trainee roles
        $parsed = correct_employment_type($parsed);

        // ── Merge + sanitize links ───────────────────────────────────────────
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
        foreach ($merged as $idx => &$link) {
            $link['title'] = infer_link_title($link['title'], $link['url'], $idx + 1);
        }
        unset($link);

        // Ensure social links present
        $hasTg = false;
        $hasWa = false;
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

        // ── Build FAQ HTML block ─────────────────────────────────────────────
        $faqData = is_array($parsed['faq'] ?? null) ? $parsed['faq'] : [];
        $faqHtml = build_faq_html($faqData);
        $faqSchema = generate_faq_schema($faqData);

        // ── Build complete JobPosting + Breadcrumb schema block ──────────────
        // (slug not available at analyze time; will be re-generated post_job)
        $jobSchema = generate_job_schema($parsed, $merged);

        // ── Strip any AI-generated "Important Links" or "FAQ" section ────────
        $parsed['content'] = preg_replace(
            '/<h3[^>]*>\s*[📎🔗]?\s*important\s+links.*?<\/h3>[\s\S]*?(?=<h3|$)/si',
            '',
            $parsed['content'] ?? ''
        );
        $parsed['content'] = preg_replace(
            '/<h3[^>]*>\s*[❓🙋]?\s*frequently\s+asked.*?<\/h3>[\s\S]*?(?=<h3|$)/si',
            '',
            $parsed['content'] ?? ''
        );

        // ── Assemble final content: links table + FAQ + social + schemas ─────
        $linksHtml = build_links_table($parsed['important_links']);
        $socialMarker = '<h3>📢 Stay Updated';
        $content = rtrim($parsed['content'] ?? '');

        if (str_contains($content, $socialMarker)) {
            // Insert: FAQ → Links → Social
            $content = str_replace(
                $socialMarker,
                $faqHtml . "\n" . $linksHtml . "\n" . $socialMarker,
                $content
            );
        } else {
            $content .= "\n" . $faqHtml . "\n" . $linksHtml;
        }

        // Inject JSON-LD schemas at the very end of content
        $content .= "\n" . $faqSchema . "\n" . $jobSchema;
        $parsed['content'] = $content;

        // ── Build OG tags (returned to frontend for display / CMS head) ──────
        $ogTags = generate_og_tags($parsed, JOBONE_SITE_URL . '/preview');

        $kwCount = !empty($parsed['meta_keywords'])
            ? count(array_filter(array_map('trim', explode(',', $parsed['meta_keywords'])))) : 0;

        echo json_encode([
            'success' => true,
            'data' => $parsed,
            'kw_count' => $kwCount,
            'faq_count' => count($faqData),
            'og_tags' => $ogTags,           // ← copy-paste into CMS <head>
        ]);
        break;

    // ── post_job ─────────────────────────────────────────────────────────────
    case 'post_job':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            echo json_encode(['success' => false, 'message' => 'Invalid request body']);
            break;
        }

        foreach (['title', 'type', 'short_description', 'content', 'category_id'] as $field) {
            if (empty($input[$field])) {
                echo json_encode(['success' => false, 'message' => "Required field missing: {$field}"]);
                break 2;
            }
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

        // Strip display-only fields; keep all structured data fields for API
        $sourceUrl = $input['source_url'] ?? '';
        unset($input['state_name'], $input['category_name'], $input['source_url'], $input['qualifications_text'], $input['og_tags']);

        // Cast integer fields so API validation passes
        foreach (['total_posts','age_min','age_max_gen','age_max_obc','age_max_sc'] as $intField) {
            if (isset($input[$intField])) $input[$intField] = (int)$input[$intField];
        }
        // Ensure direct_apply is boolean
        if (isset($input['direct_apply'])) $input['direct_apply'] = (bool)$input['direct_apply'];

        $postResult = curl_request(
            JOBONE_API . '/posts',
            'POST',
            ['Authorization: Bearer ' . JOBONE_TOKEN, 'Content-Type: application/json', 'Accept: application/json'],
            json_encode($input)
        );

        // ── If the post succeeded, regenerate schemas with real slug + ping ──
        if (!empty($postResult['success']) || !empty($postResult['data']['id'])) {
            $postData = $postResult['data'] ?? $postResult;
            $slug = $postData['slug'] ?? '';
            $jobUrl = $slug ? JOBONE_SITE_URL . '/' . $slug : JOBONE_SITE_URL;

            // Regenerate content with real slug in schemas
            $input['id'] = $postData['id'] ?? '';
            $input['slug'] = $slug;
            // (Re-generate schema and patch content here if your CMS supports update endpoint)

            // ── IndexNow ping ────────────────────────────────────────────────
            $pingResult = ping_indexnow($jobUrl);

            // ── Build final OG tags with real URL ───────────────────────────
            $ogTags = generate_og_tags(array_merge($input, $postData), $jobUrl);

            // ── BreadcrumbList + JobPosting schema with real slug ────────────
            $mergedData = array_merge($input, $postData);
            $finalSchema = generate_job_schema($mergedData, $input['important_links'] ?? []);

            $postResult['indexnow'] = $pingResult;
            $postResult['og_tags'] = $ogTags;
            $postResult['job_schema'] = $finalSchema;
            $postResult['job_url'] = $jobUrl;
        }

        echo json_encode($postResult);
        break;

    // ── 404 ──────────────────────────────────────────────────────────────────
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
}

// ── JSON repair ───────────────────────────────────────────────────────────────
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