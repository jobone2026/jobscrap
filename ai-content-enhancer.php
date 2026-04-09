<?php
/**
 * AI Content Enhancer for JobOne
 * Generates 100% SEO-optimized, human-like content that passes all SEO checks
 * Makes scraped content undetectable as AI-generated
 */

require_once 'config.php';

class AIContentEnhancer {
    
    private $geminiApiKey;
    private $model = 'gemini-2.0-flash-exp';
    
    public function __construct() {
        $this->geminiApiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    }
    
    /**
     * Enhance scraped content to achieve 100% SEO score
     */
    public function enhanceContent($scrapedData) {
        if (empty($this->geminiApiKey)) {
            return $this->fallbackEnhancement($scrapedData);
        }
        
        try {
            // Generate all fields with AI
            $enhanced = $this->generateWithAI($scrapedData);
            
            // Validate and ensure 100% SEO score
            $enhanced = $this->ensureSEOPerfection($enhanced);
            
            // Humanize the content
            $enhanced = $this->humanizeContent($enhanced);
            
            return $enhanced;
            
        } catch (Exception $e) {
            error_log("AI Enhancement failed: " . $e->getMessage());
            return $this->fallbackEnhancement($scrapedData);
        }
    }
    
    /**
     * Generate content using Gemini AI
     */
    private function generateWithAI($data) {
        $prompt = $this->buildPrompt($data);
        
        $response = $this->callGeminiAPI($prompt);
        
        if (!$response) {
            throw new Exception("AI API call failed");
        }
        
        return $this->parseAIResponse($response, $data);
    }
    
    /**
     * Build comprehensive prompt for AI
     */
    private function buildPrompt($data) {
        $rawTitle = $data['title'] ?? 'Government Job';
        $rawContent = $data['content'] ?? '';
        $type = $data['type'] ?? 'job';
        
        return <<<PROMPT
You are an expert SEO content writer for JobOne.in, India's #1 government job portal.

TASK: Transform this raw job notification into a PERFECT, SEO-optimized, human-written blog post.

RAW DATA:
Title: {$rawTitle}
Type: {$type}
Content: {$rawContent}

REQUIREMENTS FOR 100% SEO SCORE:

1. **title** (Main Title):
   - Length: 50-100 characters
   - Must be DIFFERENT from raw title (rewrite completely)
   - Include: Organization name + Post name + "2026"
   - Example: "CRPF Constable Recruitment 2026: Apply for 9195 Posts"
   - Make it click-worthy and unique

2. **meta_title** (SEO Title):
   - Length: EXACTLY 50-60 characters (STRICT)
   - Must end with " | JobOne.in"
   - Include main keyword
   - Example: "CRPF Constable 2026: 9195 Posts | JobOne.in"

3. **meta_description** (SEO Description):
   - Length: EXACTLY 120-160 characters (STRICT)
   - Include: Organization, post count, deadline
   - Call-to-action at end
   - Example: "CRPF Constable Recruitment 2026: Apply online for 9195 Technical & Tradesmen posts. Last date: 15 March 2026. Check eligibility & apply now!"

4. **meta_keywords** (SEO Keywords):
   - 8-12 comma-separated keywords
   - Include: job name, organization, year, category
   - Example: "CRPF Constable 2026, CRPF Recruitment, Government Jobs 2026, Police Jobs, Central Govt Jobs, CRPF Bharti, Constable Vacancy"

5. **short_description** (Summary):
   - 2-3 sentences, 150-200 characters
   - Professional tone
   - Highlight key points
   - Example: "Central Reserve Police Force (CRPF) has announced recruitment for 9195 Constable posts. Eligible candidates can apply online before 15 March 2026."

6. **content** (Main HTML Content):
   - Word count: 300-500 words minimum
   - Use MODERN HTML BOXES (MANDATORY):
     * <div class="box-info"><b>🔍 Job Highlights:</b> Summary of posts & salary</div>
     * <div class="box-success"><b>🔗 Important Links:</b> Official website & apply link</div>
     * <div class="box-warning"><b>⚠️ Note:</b> Application fees & age limits</div>
     * <div class="box-danger"><b>🚨 Deadline:</b> Last date in RED box</div>
   - Include sections:
     * Job Highlights (in box-info)
     * Important Dates (clean HTML table)
     * Important Links (in box-success)
     * Eligibility Criteria (table)
     * Application Fee (in box-warning)
     * How to Apply (numbered steps)
     * Deadline (in box-danger)
   - Add 2-3 internal links to JobOne.in pages
   - Natural keyword placement (not stuffed)
   - Use emojis sparingly (only in boxes)
   - Write in active voice
   - Make it conversational and helpful

7. **category** (Job Category):
   - Choose from: Banking, Railways, SSC, UPSC, State PSC, Defence, Police, State Govt
   - Based on organization type

8. **state** (Location):
   - Specific state name OR "All India" for central jobs
   - Examples: "Karnataka", "All India", "Maharashtra"

9. **organization** (Recruiting Body):
   - Full official name
   - Example: "Central Reserve Police Force (CRPF)"

10. **total_posts** (Vacancy Count):
    - Integer number only
    - Example: 9195

11. **last_date** (Deadline):
    - Format: YYYY-MM-DD
    - Example: "2026-03-15"

12. **notification_date** (Release Date):
    - Format: YYYY-MM-DD
    - Example: "2026-02-01"

CRITICAL RULES:
- Title MUST be 50-60 chars for meta_title
- Description MUST be 120-160 chars for meta_description
- Content MUST have 300+ words
- Content MUST use the box classes (box-info, box-success, box-warning, box-danger)
- Content MUST include 2-3 internal links
- Write like a human, not AI (vary sentence length, use contractions)
- NO promotional external links
- NO spam or keyword stuffing
- Make it helpful and informative
- NEVER use HTML entities: Use & not &amp;, use ' not &#39;, use " not &quot;
- Write plain text for title, meta_title, meta_description, short_description
- Only use HTML tags in the content field
- If you see &amp; in your output, replace it with &
- If you see &#39; in your output, replace it with '
- Use actual characters, not their HTML codes

OUTPUT FORMAT (JSON):
{
  "title": "Your rewritten title here",
  "meta_title": "SEO title 50-60 chars | JobOne.in",
  "meta_description": "SEO description 120-160 characters with CTA",
  "meta_keywords": "keyword1, keyword2, keyword3, ...",
  "short_description": "2-3 sentence summary",
  "content": "<div class='box-info'>...</div><h3>Important Dates</h3><table>...</table>...",
  "category": "Category name",
  "state": "State or All India",
  "organization": "Full organization name",
  "total_posts": 9195,
  "last_date": "2026-03-15",
  "notification_date": "2026-02-01"
}

Generate the JSON now:
PROMPT;
    }
    
    /**
     * Call Gemini API
     */
    private function callGeminiAPI($prompt) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->geminiApiKey}";
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Gemini API returned HTTP $httpCode");
        }
        
        return $response;
    }
    
    /**
     * Parse AI response
     */
    private function parseAIResponse($response, $originalData) {
        $decoded = json_decode($response, true);
        
        if (!isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid AI response format");
        }
        
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'];
        
        // Extract JSON from response (handle markdown code blocks)
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*$/i', '', $text);
        $text = trim($text);
        
        $enhanced = json_decode($text, true);
        
        if (!$enhanced) {
            throw new Exception("Failed to parse AI JSON response");
        }
        
        // Decode HTML entities in all text fields
        $enhanced = $this->decodeHtmlEntities($enhanced);
        
        // Merge with original data (keep type and other fields)
        return array_merge($originalData, $enhanced);
    }
    
    /**
     * Decode HTML entities in all text fields
     */
    private function decodeHtmlEntities($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    // Decode multiple times to handle double/triple encoding
                    // &amp;amp; -> &amp; -> &
                    $decoded = $value;
                    $maxIterations = 5; // Prevent infinite loop
                    
                    for ($i = 0; $i < $maxIterations; $i++) {
                        $newDecoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        
                        // If no change, we're done
                        if ($newDecoded === $decoded) {
                            break;
                        }
                        
                        $decoded = $newDecoded;
                    }
                    
                    $data[$key] = $decoded;
                } elseif (is_array($value)) {
                    $data[$key] = $this->decodeHtmlEntities($value);
                }
            }
        }
        return $data;
    }
    
    /**
     * Ensure content meets 100% SEO requirements
     */
    private function ensureSEOPerfection($data) {
        // Clean HTML entities from all text fields (except content)
        $textFields = ['title', 'meta_title', 'meta_description', 'meta_keywords', 'short_description', 'organization'];
        foreach ($textFields as $field) {
            if (isset($data[$field])) {
                // Decode any HTML entities
                $data[$field] = html_entity_decode($data[$field], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Remove any remaining HTML tags
                $data[$field] = strip_tags($data[$field]);
            }
        }
        
        // CRITICAL: Fix meta_title length (MUST be 50-60 chars for 20 points)
        if (isset($data['meta_title'])) {
            $len = mb_strlen($data['meta_title']);
            
            if ($len < 50) {
                // Too short - pad it
                if (!str_contains($data['meta_title'], '| JobOne.in')) {
                    $data['meta_title'] = $data['meta_title'] . ' | JobOne.in';
                }
                // Still too short? Add year
                if (mb_strlen($data['meta_title']) < 50) {
                    $data['meta_title'] = str_replace(' | JobOne.in', ' 2026 | JobOne.in', $data['meta_title']);
                }
            } elseif ($len > 60) {
                // Too long - truncate
                $data['meta_title'] = mb_substr($data['meta_title'], 0, 47) . ' | JobOne.in';
            }
            
            // Final check - ensure it's exactly in range
            $finalLen = mb_strlen($data['meta_title']);
            if ($finalLen < 50 || $finalLen > 60) {
                // Force it to be in range
                if ($finalLen < 50) {
                    $data['meta_title'] = $data['title'] . ' | JobOne.in';
                    $data['meta_title'] = mb_substr($data['meta_title'], 0, 60);
                }
            }
        } else {
            // No meta_title - create one from title
            $data['meta_title'] = mb_substr($data['title'], 0, 47) . ' | JobOne.in';
        }
        
        // CRITICAL: Fix meta_description length (MUST be 120-160 chars for 20 points)
        if (isset($data['meta_description'])) {
            $len = mb_strlen($data['meta_description']);
            
            if ($len < 120) {
                // Too short - add more text
                $padding = ' Check eligibility, important dates, and application process. Apply now on JobOne.in!';
                $data['meta_description'] .= $padding;
                
                // Trim if too long now
                if (mb_strlen($data['meta_description']) > 160) {
                    $data['meta_description'] = mb_substr($data['meta_description'], 0, 157) . '...';
                }
            } elseif ($len > 160) {
                // Too long - truncate
                $data['meta_description'] = mb_substr($data['meta_description'], 0, 157) . '...';
            }
        } else {
            // No meta_description - create one
            $data['meta_description'] = mb_substr($data['short_description'] ?? $data['title'], 0, 120) . ' Apply now on JobOne.in for latest updates!';
            if (mb_strlen($data['meta_description']) > 160) {
                $data['meta_description'] = mb_substr($data['meta_description'], 0, 157) . '...';
            }
        }
        
        // CRITICAL: Ensure meta_keywords exist and appear in title/description (35 points)
        if (empty($data['meta_keywords'])) {
            // Generate keywords from title
            $words = explode(' ', $data['title']);
            $keywords = array_slice($words, 0, 8);
            $data['meta_keywords'] = implode(', ', $keywords) . ', Government Jobs 2026, Apply Online';
        }
        
        // Ensure at least one keyword appears in title (20 points)
        $keywords = array_map('trim', explode(',', $data['meta_keywords']));
        $titleLower = strtolower($data['meta_title'] ?? $data['title']);
        $keywordInTitle = false;
        foreach ($keywords as $kw) {
            if (!empty($kw) && str_contains($titleLower, strtolower($kw))) {
                $keywordInTitle = true;
                break;
            }
        }
        
        // If no keyword in title, add first keyword
        if (!$keywordInTitle && !empty($keywords[0])) {
            $data['meta_title'] = $keywords[0] . ' - ' . $data['meta_title'];
            // Re-trim to 60 chars
            if (mb_strlen($data['meta_title']) > 60) {
                $data['meta_title'] = mb_substr($data['meta_title'], 0, 57) . '...';
            }
        }
        
        // Ensure at least one keyword appears in description (15 points)
        $descLower = strtolower($data['meta_description']);
        $keywordInDesc = false;
        foreach ($keywords as $kw) {
            if (!empty($kw) && str_contains($descLower, strtolower($kw))) {
                $keywordInDesc = true;
                break;
            }
        }
        
        // If no keyword in description, add first keyword
        if (!$keywordInDesc && !empty($keywords[0])) {
            $data['meta_description'] = $keywords[0] . ': ' . $data['meta_description'];
            // Re-trim to 160 chars
            if (mb_strlen($data['meta_description']) > 160) {
                $data['meta_description'] = mb_substr($data['meta_description'], 0, 157) . '...';
            }
        }
        
        // CRITICAL: Ensure content has minimum 300 words (15 points)
        if (isset($data['content'])) {
            $wordCount = str_word_count(strip_tags($data['content']));
            if ($wordCount < 300) {
                $data['content'] .= $this->generateFillerContent($data);
            }
        }
        
        // CRITICAL: Ensure at least 2 internal links (10 points)
        if (isset($data['content'])) {
            $linkCount = substr_count($data['content'], '<a href=');
            if ($linkCount < 2) {
                $data['content'] = $this->addInternalLinks($data['content'], $data);
            }
        }
        
        return $data;
    }
    
    /**
     * Humanize content to avoid AI detection
     */
    private function humanizeContent($data) {
        if (isset($data['content'])) {
            // Add natural variations
            $data['content'] = $this->addNaturalVariations($data['content']);
            
            // Fix common AI patterns
            $data['content'] = str_replace('In conclusion,', 'Finally,', $data['content']);
            $data['content'] = str_replace('It is important to note', 'Note that', $data['content']);
            $data['content'] = str_replace('Furthermore,', 'Also,', $data['content']);
        }
        
        return $data;
    }
    
    /**
     * Add natural variations to content
     */
    private function addNaturalVariations($content) {
        // Add occasional contractions
        $contractions = [
            'do not' => "don't",
            'will not' => "won't",
            'cannot' => "can't",
            'should not' => "shouldn't",
        ];
        
        foreach ($contractions as $full => $short) {
            // Replace randomly (50% chance)
            if (rand(0, 1)) {
                $content = str_replace($full, $short, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Add internal links to content
     */
    private function addInternalLinks($content, $data) {
        $category = $data['category'] ?? 'jobs';
        $state = $data['state'] ?? 'all-india';
        $type = $data['type'] ?? 'job';
        
        // Add at least 2 internal links
        $linksAdded = 0;
        
        // Link 1: Category page
        if ($linksAdded < 2) {
            $categorySlug = strtolower(str_replace(' ', '-', $category));
            $categoryLink = "<p>Explore more <a href=\"https://jobone.in/category/{$categorySlug}\">{$category} jobs</a> on JobOne.in.</p>";
            $content .= "\n" . $categoryLink;
            $linksAdded++;
        }
        
        // Link 2: State page or home page
        if ($linksAdded < 2) {
            if ($state !== 'All India') {
                $stateSlug = strtolower(str_replace(' ', '-', $state));
                $stateLink = "<p>Check all <a href=\"https://jobone.in/state/{$stateSlug}\">{$state} government jobs</a> here.</p>";
                $content .= "\n" . $stateLink;
            } else {
                $homeLink = "<p>Visit <a href=\"https://jobone.in/\">JobOne.in</a> for latest government job notifications across India.</p>";
                $content .= "\n" . $homeLink;
            }
            $linksAdded++;
        }
        
        // Link 3: Type-specific page (bonus)
        if ($linksAdded < 3 && $type !== 'job') {
            $typeLink = "<p>Find more <a href=\"https://jobone.in/{$type}\">{$type} updates</a> on our portal.</p>";
            $content .= "\n" . $typeLink;
            $linksAdded++;
        }
        
        return $content;
    }
    
    /**
     * Generate filler content if word count is low
     */
    private function generateFillerContent($data) {
        $org = $data['organization'] ?? 'the organization';
        $category = $data['category'] ?? 'Government';
        $type = $data['type'] ?? 'job';
        
        $typeText = match($type) {
            'job' => 'recruitment',
            'admit_card' => 'admit card',
            'result' => 'result',
            'answer_key' => 'answer key',
            'syllabus' => 'syllabus',
            default => 'notification'
        };
        
        return <<<HTML

<h3>📋 About This {$category} {$typeText}</h3>
<p>This {$typeText} by {$org} offers excellent career opportunities for eligible candidates across India. The selection process will be conducted as per the official notification guidelines and government norms.</p>

<h3>💡 Important Tips for Applicants</h3>
<ul>
<li>Read the official notification carefully before applying</li>
<li>Keep all required documents ready (educational certificates, ID proof, photographs)</li>
<li>Apply well before the deadline to avoid last-minute technical issues</li>
<li>Take a printout of your application form for future reference</li>
<li>Keep checking the official website for updates and announcements</li>
<li>Prepare thoroughly for the selection process as per the syllabus</li>
</ul>

<h3>📱 Stay Connected</h3>
<p>Don't miss any important updates! Follow JobOne.in for the latest government job notifications, admit cards, results, and answer keys. We provide timely updates for all major recruitments across India.</p>

<div class="box-info">
<b>🚀 Join Our Community:</b> Stay updated with instant notifications by joining our <a href="https://t.me/jobone2026" target="_blank">Telegram channel</a> and <a href="https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22" target="_blank">WhatsApp channel</a>. Get alerts for new jobs, admit cards, results, and answer keys directly on your phone!
</div>

<h3>⚠️ Disclaimer</h3>
<p>All information provided here is based on the official notification. Candidates are advised to verify all details from the official website before applying. JobOne.in is not responsible for any inadvertent errors. This is an informational website and not the official recruitment portal.</p>
HTML;
    }
    
    /**
     * Fallback enhancement without AI
     */
    private function fallbackEnhancement($data) {
        $title = $data['title'] ?? 'Government Job Notification';
        $org = $data['organization'] ?? 'Government Organization';
        
        // Generate basic SEO fields
        $data['meta_title'] = mb_substr($title, 0, 47) . ' | JobOne.in';
        $data['meta_description'] = "Apply for {$title}. Check eligibility, important dates, and application process. Apply now on JobOne.in!";
        $data['meta_keywords'] = "government jobs 2026, {$org}, recruitment, vacancy, apply online";
        $data['short_description'] = "{$org} has announced a new recruitment notification. Eligible candidates can apply online.";
        
        return $data;
    }
}

// Usage example
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // Test the enhancer
    $testData = [
        'title' => 'CRPF Constable Recruitment 2026',
        'content' => 'CRPF has announced recruitment for constable posts...',
        'type' => 'job',
    ];
    
    $enhancer = new AIContentEnhancer();
    $enhanced = $enhancer->enhanceContent($testData);
    
    header('Content-Type: application/json');
    echo json_encode($enhanced, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
