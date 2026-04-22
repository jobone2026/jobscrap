<?php
/**
 * AI Content Enhancer — Gemini 2.5 Flash
 * =========================================
 * - Primary:  Google Gemini 2.5 Flash  (human-like rewrite + SEO)
 * - Fallback: SambaNova  (title + SEO only)
 *
 * Gemini rewrites:
 *  • Job title  (catchy, SEO-optimised)
 *  • Full article body  (human-like, well-structured HTML)
 *  • Short description  (120-160 chars)
 *  • Meta title / description / keywords
 */

require_once 'config.php';

class AIContentEnhancer
{
    // ── Gemini connection ─────────────────────────────────────────────────────
    private string $geminiKey;
    private string $geminiModel;
    private string $geminiBaseUrl;

    // ── SambaNova fallback ────────────────────────────────────────────────────
    private string $sambaKey;
    private string $sambaEndpoint;
    private string $sambaModel;

    public function __construct()
    {
        $this->geminiKey     = defined('GEMINI_API_KEY')  ? GEMINI_API_KEY  : '';
        $this->geminiModel   = defined('GEMINI_MODEL')    ? GEMINI_MODEL    : 'gemini-2.5-flash';
        $this->geminiBaseUrl = defined('GEMINI_BASE_URL') ? GEMINI_BASE_URL : 'https://generativelanguage.googleapis.com/v1beta/models';

        $this->sambaKey      = defined('SAMBANOVA_API_KEY')           ? SAMBANOVA_API_KEY           : '';
        $this->sambaEndpoint = defined('SAMBANOVA_CHAT_ENDPOINT')     ? SAMBANOVA_CHAT_ENDPOINT     : 'https://api.sambanova.ai/v1/chat/completions';
        $this->sambaModel    = defined('SAMBANOVA_MODEL')             ? SAMBANOVA_MODEL             : 'Meta-Llama-3.3-70B-Instruct';
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PUBLIC ENTRY POINT
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Main method — called by scrape.php / auto-post-api.php
     *
     * @param  array $scrapedData  Keys: title, content, short_description, …
     * @return array               Enhanced data ready to post
     */
    public function enhanceContent(array $scrapedData): array
    {
        // ── Clean competitor brands FIRST ─────────────────────────────────────
        $scrapedData = $this->removeCompetitorBrands($scrapedData);
        
        // ── Try Gemini first ──────────────────────────────────────────────────
        if (!empty($this->geminiKey)) {
            try {
                $enhanced = $this->enhanceWithGemini($scrapedData);
                $enhanced['ai_provider'] = 'gemini-2.5-flash';
                $enhanced['ai_enhanced'] = true;
                return $enhanced;
            } catch (Exception $e) {
                error_log("[Gemini] Failed: " . $e->getMessage() . " — trying SambaNova fallback");
            }
        }

        // ── SambaNova fallback ────────────────────────────────────────────────
        if (!empty($this->sambaKey)) {
            try {
                $enhanced = $this->enhanceWithSambaNova($scrapedData);
                $enhanced['ai_provider'] = 'sambanova';
                $enhanced['ai_enhanced'] = true;
                return $enhanced;
            } catch (Exception $e) {
                error_log("[SambaNova] Failed: " . $e->getMessage());
            }
        }

        // ── Static fallback ───────────────────────────────────────────────────
        return $this->fallbackEnhancement($scrapedData);
    }
    
    /**
     * Remove competitor brand names from all text fields
     */
    private function removeCompetitorBrands(array $data): array
    {
        $competitors = [
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
            'sarkari-naukri.com',
            'employment news',
            'employmentnews.gov.in',
            'rojgar samachar',
        ];
        
        // Fields to clean
        $fieldsToClean = ['title', 'short_description', 'meta_title', 'meta_description', 'meta_keywords'];
        
        foreach ($fieldsToClean as $field) {
            if (!empty($data[$field])) {
                // Replace competitor names with 'JobOne' or remove them
                $cleaned = str_ireplace($competitors, 'JobOne', $data[$field]);
                
                // Remove any remaining mentions in parentheses like "(SarkariResult)"
                $cleaned = preg_replace('/\s*\([^)]*(?:sarkari|result|exam|alert|naukri)[^)]*\)/i', '', $cleaned);
                
                // Clean up multiple spaces and trim
                $cleaned = preg_replace('/\s+/', ' ', $cleaned);
                $data[$field] = trim($cleaned);
            }
        }
        
        return $data;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // GEMINI 2.5 FLASH — FULL HUMAN-LIKE REWRITE
    // ═════════════════════════════════════════════════════════════════════════

    private function enhanceWithGemini(array $data): array
    {
        $title   = $data['title']   ?? 'Government Job';
        $rawHtml = $data['content'] ?? '';
        $rawText = strip_tags($rawHtml);
        $rawText = preg_replace('/\s+/', ' ', $rawText);
        $snippet = mb_substr($rawText, 0, 3000); // give Gemini good context

        $prompt = $this->buildGeminiPrompt($title, $snippet);
        $responseText = $this->callGeminiAPI($prompt, 800, 0.4);
        $aiData = $this->parseGeminiResponse($responseText, $data);

        // ── Rewrite full content body ──────────────────────────────────────
        $aiData['content'] = $this->rewriteContentWithGemini($title, $rawHtml, $aiData);

        // ── Final SEO polish ───────────────────────────────────────────────
        $aiData = $this->ensureBasicSEO($aiData);

        return $aiData;
    }

    /**
     * Build the SEO + metadata prompt for Gemini
     */
    private function buildGeminiPrompt(string $title, string $rawText): string
    {
        return <<<PROMPT
You are a professional government job content writer for JobOne.in, India's top government job portal.

Original Job Title: {$title}
Scraped Content Preview: {$rawText}

Generate SEO metadata and an enhanced title. Return ONLY valid JSON (no markdown, no code fences):

{
  "title": "Enhanced, catchy SEO job title with year 2026. Keep it natural.",
  "meta_title": "Title under 60 chars | JobOne.in",
  "short_description": "2-3 complete, human-written sentences summarising this job. 120-160 characters total. End with a period.",
  "meta_description": "1-2 complete sentences for Google snippet. 135-150 characters. Must end with period.",
  "meta_keywords": "15-20 relevant comma-separated keywords, 250-350 characters total",
  "state_guess": "Name of the Indian state context, e.g. 'Uttar Pradesh', 'Gujarat'. If all India, return 'All India'.",
  "category_guess": "Use exact category from: State Govt, State PSC, Banking, Railways, UPSC, SSC, Defence, Police.",
  "notification_date": "YYYY-MM-DD",
  "last_date": "YYYY-MM-DD",
  "total_posts": 100,
  "education_tags": ["10th_pass", "12th_pass", "graduate", "diploma"],
  "image_prompt": "Analyze the specific job role and sector from the content. Write a highly descriptive, contextually accurate visual prompt for an AI image generator representing this exact job environment in India. Focus on the actual work setting (e.g., 'Modern Indian hospital ward with nurses, realistic' or 'Busy Indian railway station with train driver in uniform, 4k'). Specify 'no text, photorealistic'."
}

STRICT RULES:
1. short_description  → 120-160 chars, complete sentences, ends with period, no ellipsis
2. meta_description   → 135-150 chars, complete sentences, ends with period, no ellipsis
3. meta_keywords      → 250-350 chars total
4. image_prompt       → MUST be specifically based on the actual job role/industry analyzed from the text, highly visual, realistic, and contain NO words or text.
5. All text must sound human-written, not robotic
6. Return ONLY the raw JSON object
PROMPT;
    }

    /**
     * Rewrite scraped HTML content into beautiful, human-written HTML
     */
    private function rewriteContentWithGemini(string $title, string $rawHtml, array $aiData): string
    {
        $cleanTitle = $aiData['title'] ?? $title;
        // Gemini Flash handles huge context windows (1M+ tokens), so we can pass large HTML blocks safely
        $snippet    = mb_substr($rawHtml, 0, 20000);

        $prompt = <<<PROMPT
You are a senior content writer for JobOne.in. Rewrite the following raw scraped government job HTML content into a BEAUTIFUL, COMPREHENSIVE, HUMAN-WRITTEN article in clean HTML. 

Job Title: {$cleanTitle}
Raw Scraped HTML: 
{$snippet}

Requirements:
- DO NOT SUMMARIZE! Write a fully detailed, long-form article. Keep the length equivalent to or longer than the original.
- CONVERT all raw data tables (vacancies, age limits, syllabus, important dates, fees) into beautifully formatted HTML <table> structures. Do not omit any row or column.
- Use proper HTML: <h2>, <h3>, <p>, <ul>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <em>.
- Organize into logical sections: Overview, Key Highlights, Vacancy Details, Eligibility Criteria, Age Limit, Application Fee, Selection Process, Important Dates, How to Apply.
- EXCLUDE all social media links, generic channel invitations (e.g. Join Arattai Channel, Join WhatsApp, Join Telegram), and broken/unavailable official website links. JobOne handles these natively.
- Write in a friendly, helpful, conversational Indian English tone.
- Add a motivational opening and closing paragraph encouraging candidates to apply.
- Do NOT invent data not present in the original text; use "Not mentioned" if unknown.
- Make it look like it was written by an experienced journalist covering government jobs.
- NO markdown, NO code fences — return ONLY clean HTML starting with <div class="job-content">.

IMPORTANT: The output must be highly detailed and comprehensive. Do not leave out any details or sections from the original content, EXCEPT for the social links mentioned above.
PROMPT;

        $fallbackContent = ($aiData['content'] ?? '<p>Content extraction failed.</p>') . "\n<!-- GEMINI_CONTENT_FALLBACK -->";
        if (!str_contains($fallbackContent, 'class="job-content"')) {
            $fallbackContent = '<div class="job-content">' . $fallbackContent . '</div>';
        }
        
        try {
            // Give a massive token allowance for full-length rewrites
            $rewritten = $this->callGeminiAPI($prompt, 8192, 0.75);
            // Extract just the HTML part
            if (preg_match('/<div[^>]*class=["\']job-content["\'][^>]*>(.*)/is', $rewritten, $m)) {
                $rewritten = '<div class="job-content">' . $m[1];
                // Make sure it's closed
                if (!str_contains($rewritten, '</div>')) {
                    $rewritten .= '</div>';
                }
            } elseif (str_contains($rewritten, '<')) {
                // Has HTML but no wrapper — wrap it
                $rewritten = '<div class="job-content">' . $rewritten . '</div>';
            } else {
                // No HTML at all — convert plain text to simple HTML
                $rewritten = '<div class="job-content"><p>' . nl2br(htmlspecialchars($rewritten)) . '</p></div>';
            }
            
            $finalHtml = $rewritten . "\n<!-- GEMINI_AI_REWRITTEN -->";
        } catch (Exception $e) {
            error_log("[Gemini Content Rewrite] Failed: " . $e->getMessage());
            $finalHtml = $fallbackContent;
        }

        // Build image tag from prompt (always do this regardless of rewrite success)
        $promptStr = !empty($aiData['image_prompt']) ? $aiData['image_prompt'] : "Indian Government Job, " . $cleanTitle . " office background";
        $imageUrl = "https://image.pollinations.ai/prompt/" . urlencode($promptStr) . "?width=1200&height=630&nologo=true";
        
        $imgTag = '<div class="job-featured-image" style="margin-bottom: 24px; text-align: center;">' . 
                  '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($cleanTitle) . '" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">' .
                  '</div>';
        
        // Inject image tag right after <div class="job-content">
        if (preg_match('/(<div[^>]*class=["\']job-content["\'][^>]*>)/is', $finalHtml)) {
            $finalHtml = preg_replace('/(<div[^>]*class=["\']job-content["\'][^>]*>)/is', '$1' . "\n" . $imgTag . "\n", $finalHtml, 1);
        } else {
            $finalHtml = $imgTag . "\n" . $finalHtml;
        }

        return $finalHtml;
    }

    /**
     * Call Gemini generateContent API
     */
    private function callGeminiAPI(string $prompt, int $maxTokens = 600, float $temperature = 0.4): string
    {
        $endpoint = $this->geminiBaseUrl . '/' . $this->geminiModel . ':generateContent?key=' . $this->geminiKey;

        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => $maxTokens,
                'topP'            => 0.9,
            ],
        ]);

        $maxRetries = 3;
        $delay      = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                throw new Exception("cURL Error: $curlErr");
            }

            if ($httpCode === 200) {
                $decoded = json_decode($response, true);
                $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text !== null) {
                    return $text;
                }
                throw new Exception("Gemini returned empty text. Response: " . substr($response, 0, 300));
            }

            if (($httpCode === 429 || $httpCode === 503) && $attempt < $maxRetries) {
                error_log("[Gemini] Rate limited / unavailable (HTTP $httpCode), retrying in {$delay}s...");
                sleep($delay);
                $delay += 3;
                continue;
            }

            throw new Exception("Gemini API returned HTTP $httpCode: " . substr($response, 0, 300));
        }

        throw new Exception("Gemini API failed after $maxRetries attempts");
    }

    /**
     * Parse Gemini's JSON response for SEO fields
     */
    private function parseGeminiResponse(string $text, array $originalData): array
    {
        // Strip possible markdown code fences
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        // Extract JSON object
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false) {
            $jsonStr = substr($text, $start, $end - $start + 1);
            $aiData  = json_decode($jsonStr, true);
            if (is_array($aiData)) {
                return array_merge($originalData, $aiData);
            }
            // Try to repair truncated JSON — close open brackets
            $repaired = $jsonStr;
            $openBraces = substr_count($repaired, '{') - substr_count($repaired, '}');
            $repaired .= str_repeat('}', max(0, $openBraces));
            $aiData = json_decode($repaired, true);
            if (is_array($aiData)) {
                error_log("[Gemini] Repaired truncated JSON successfully");
                return array_merge($originalData, $aiData);
            }
        }

        // JSON parse fully failed — try to extract individual fields with regex
        $extracted = [];
        if (preg_match('/"title"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['title'] = $m[1];
        if (preg_match('/"meta_title"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['meta_title'] = $m[1];
        if (preg_match('/"short_description"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['short_description'] = $m[1];
        if (preg_match('/"meta_description"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['meta_description'] = $m[1];
        if (preg_match('/"meta_keywords"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['meta_keywords'] = $m[1];
        if (preg_match('/"state_guess"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['state_guess'] = $m[1];
        if (preg_match('/"category_guess"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['category_guess'] = $m[1];
        if (preg_match('/"notification_date"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['notification_date'] = $m[1];
        if (preg_match('/"last_date"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['last_date'] = $m[1];
        if (preg_match('/"total_posts"\s*:\s*(\d+)/', $text, $m)) $extracted['total_posts'] = (int)$m[1];
        if (preg_match('/"education_tags"\s*:\s*\[(.*?)\]/', $text, $m)) {
            $extracted['education_tags'] = array_filter(array_map(function($kw) {
                return trim($kw, " \t\n\r\0\x0B\"'");
            }, explode(',', $m[1])));
        }
        if (preg_match('/"image_prompt"\s*:\s*"([^"]+)"/', $text, $m)) $extracted['image_prompt'] = $m[1];

        if (!empty($extracted)) {
            error_log("[Gemini] Extracted " . count($extracted) . " fields via regex fallback");
            return array_merge($originalData, $extracted);
        }

        error_log("[Gemini] Failed to parse JSON from: " . substr($text, 0, 300));
        return $originalData;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SAMBANOVA FALLBACK — Title + SEO only (no content rewrite)
    // ═════════════════════════════════════════════════════════════════════════

    private function enhanceWithSambaNova(array $data): array
    {
        $title   = $data['title']   ?? 'Job';
        $snippet = mb_substr(strip_tags($data['content'] ?? ''), 0, 400);

        $prompt = "Create SEO metadata for government job posting. Return ONLY valid JSON:\n\n"
                . "Title: $title\nContent: $snippet\n\n"
                . "Required JSON format:\n"
                . '{"title":"Enhanced SEO title with 2026","meta_title":"Title | JobOne.in",'
                . '"short_description":"2-3 complete sentences. 120-160 chars. End with period.",'
                . '"meta_description":"1-2 complete sentences. 135-145 chars. End with period.",'
                . '"meta_keywords":"15-20 keywords, comma separated, 250-350 chars total"}'
                . "\n\nRULES: No ellipsis, no mid-word cuts, no incomplete sentences.";

        $payload = [
            'model'       => $this->sambaModel,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.3,
            'max_tokens'  => 500,
            'stream'      => false,
        ];

        $maxRetries = 3;
        $delay      = 5;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($this->sambaEndpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->sambaKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) throw new Exception("cURL Error: $curlErr");

            if ($httpCode === 200) {
                $decoded = json_decode($response, true);
                $content = $decoded['choices'][0]['message']['content'] ?? null;
                if (!$content) throw new Exception("Empty SambaNova response");

                $start = strpos($content, '{');
                $end   = strrpos($content, '}');
                if ($start !== false && $end !== false) {
                    $aiData = json_decode(substr($content, $start, $end - $start + 1), true);
                    if (is_array($aiData)) {
                        $merged = array_merge($data, $aiData);
                        $merged['content'] = $data['content'] ?? '';
                        $merged['content'] .= "\n<!-- SAMBANOVA_FALLBACK -->";
                        return $this->ensureBasicSEO($merged);
                    }
                }
                throw new Exception("Failed to parse SambaNova JSON");
            }

            if ($httpCode === 429 && $attempt < $maxRetries) {
                sleep($delay);
                $delay += 2;
                continue;
            }

            throw new Exception("SambaNova returned HTTP $httpCode");
        }

        throw new Exception("SambaNova failed after $maxRetries attempts");
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SEO POLISH  — runs after any AI provider
    // ═════════════════════════════════════════════════════════════════════════

    private function ensureBasicSEO(array $data): array
    {
        $clean = function (string $text): string {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = str_ireplace(['&amp;amp;', '&amp;'], '&', $text);
            $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
            $text = preg_replace('/&#13;?|&#10;?|&#x0D;?|&#x0A;?/i', ' ', $text);
            return trim(preg_replace('/\s+/', ' ', $text));
        };

        // Clean all string fields immediately to destroy stray &amp; anywhere
        if (isset($data['title'])) $data['title'] = $clean($data['title']);
        if (isset($data['meta_title'])) $data['meta_title'] = $clean($data['meta_title']);
        if (isset($data['short_description'])) $data['short_description'] = $clean($data['short_description']);
        if (isset($data['meta_description'])) $data['meta_description'] = $clean($data['meta_description']);
        if (isset($data['meta_keywords'])) $data['meta_keywords'] = $clean($data['meta_keywords']);

        // ── short_description ─────────────────────────────────────────────────
        $sd = $clean($data['short_description'] ?? '');
        if (mb_strlen($sd) < 100) {
            $raw = $clean(strip_tags($data['content'] ?? ''));
            $sd  = mb_substr($raw, 0, 160);
            $lp  = max(mb_strrpos($sd, '.'), mb_strrpos($sd, '!'), mb_strrpos($sd, '?'));
            $sd  = ($lp !== false && $lp >= 100) ? mb_substr($raw, 0, $lp + 1) : mb_substr($raw, 0, 157) . '.';
        }
        $data['short_description'] = $sd;

        // ── meta_title ────────────────────────────────────────────────────────
        $mt = $data['meta_title'] ?? '';
        if (empty($mt)) {
            $mt = mb_substr($data['title'] ?? 'Job', 0, 47) . ' | JobOne.in';
        } else {
            if (mb_strlen($mt) > 60) $mt = mb_substr($mt, 0, 57) . '...';
            if (!str_contains($mt, 'JobOne.in')) {
                $mt = str_replace(' | JobOne.in', '', $mt) . ' | JobOne.in';
            }
        }
        $data['meta_title'] = $mt;

        // ── meta_description ──────────────────────────────────────────────────
        $md = $clean($data['meta_description'] ?? '');
        if (empty($md)) {
            $base = mb_substr($data['title'] ?? '', 0, 100);
            $md   = "$base. Apply now on JobOne.in!";
        }
        $len = mb_strlen($md);
        if ($len < 130 && !str_contains($md, 'JobOne.in')) {
            $md .= ' Apply now on JobOne.in!';
        } elseif ($len < 130) {
            $md .= ' Check eligibility and apply!';
        }
        if (mb_strlen($md) > 150) {
            $trimmed = mb_substr($md, 0, 147);
            $lp      = max(mb_strrpos($trimmed, '.'), mb_strrpos($trimmed, '!'), mb_strrpos($trimmed, '?'));
            $md      = ($lp !== false && $lp >= 130)
                ? mb_substr($md, 0, $lp + 1)
                : mb_substr($md, 0, 149) . '.';
        }
        if (mb_strlen($md) < 130 && !str_contains($md, 'Check')) $md .= ' Check details now!';
        // Remove any duplicate phrases
        $md = preg_replace('/( Apply now on JobOne\.in!){2,}/', ' Apply now on JobOne.in!', $md);
        $data['meta_description'] = $clean($md);

        // ── meta_keywords ─────────────────────────────────────────────────────
        $padKeywords = [
            'Jobs 2026', 'Apply Online', 'Government Jobs',
            'Latest Recruitment', 'Job Notification', 'Career Opportunities',
            'Employment News', 'Job Vacancy', 'Online Application',
            'Eligibility Criteria', 'Selection Process', 'Job Portal',
            'JobOne.in', 'Sarkari Naukri', 'Govt Jobs India',
            'Job Alert', 'Recruitment 2026', 'Job Opening', 'Apply Now',
            'Job Search', 'Employment Opportunity', 'Sarkari Job 2026',
            'Free Job Alert', 'Govt Job India',
        ];
        $kwBase = empty($data['meta_keywords'])
            ? ($data['title'] ?? '')
            : $data['meta_keywords'];

        // Always pad to at least 200 chars
        $kwStr = $kwBase;
        foreach ($padKeywords as $kw) {
            if (mb_strlen($kwStr) >= 200) break;
            if (!str_contains(strtolower($kwStr), strtolower($kw))) {
                $kwStr .= ', ' . $kw;
            }
        }
        // Trim to max 400 chars at a clean word boundary
        if (mb_strlen($kwStr) > 400) {
            $kwStr = mb_substr($kwStr, 0, 397);
            $last  = mb_strrpos($kwStr, ',');
            $kwStr = ($last !== false && $last > 300) ? mb_substr($kwStr, 0, $last) : $kwStr . '...';
        }
        $data['meta_keywords'] = $kwStr;

        return $data;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // STATIC FALLBACK — No AI at all
    // ═════════════════════════════════════════════════════════════════════════

    private function fallbackEnhancement(array $data): array
    {
        $title = $data['title'] ?? 'Government Job';

        // Enhance title without AI
        if (!str_contains($title, '2026')) $title .= ' 2026';
        if (!str_contains(strtolower($title), 'job') && !str_contains(strtolower($title), 'recruitment')) {
            $title .= ' Jobs';
        }
        if (!str_contains(strtolower($title), 'latest')) $title = 'Latest ' . $title;
        $title = trim(preg_replace('/\s+/', ' ', $title));

        $data['title']        = $title;
        $data['meta_title']   = mb_substr($title, 0, 47) . ' | JobOne.in';
        $data['ai_provider']  = 'fallback';
        $data['ai_enhanced']  = false;

        $base = "Apply for $title. Find eligibility, important dates and application process on JobOne.in";
        $len  = mb_strlen($base);
        if ($len < 130) {
            $data['meta_description'] = "$base for latest government job updates!";
        } elseif ($len > 150) {
            $data['meta_description'] = mb_substr($base, 0, 149) . '.';
        } else {
            $data['meta_description'] = $base;
        }

        $kws = [
            $title, 'Jobs 2026', 'Apply Online', 'Government Jobs',
            'Latest Recruitment', 'Job Notification', 'Career Opportunities',
            'Employment News', 'Job Vacancy', 'Online Application',
            'Eligibility Criteria', 'Selection Process', 'Job Portal',
            'JobOne.in', 'Sarkari Naukri', 'Govt Jobs India',
            'Job Alert', 'Recruitment 2026', 'Job Opening', 'Apply Now',
            'Job Search', 'Employment Opportunity',
        ];
        $data['meta_keywords'] = mb_substr(implode(', ', $kws), 0, 400);

        return $data;
    }
}