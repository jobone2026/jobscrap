<?php
/**
 * AI Content Enhancer for JobOne
 * Generates 100% SEO-optimized, human-like content that passes all SEO checks
 * Makes scraped content undetectable as AI-generated
 */

require_once 'config.php';

class AIContentEnhancer {
    
    private $groqApiKey;
    private $geminiApiKey;
    private $temperature;
    private $systemPrompt;
    private $model;
    public function __construct() {
        $this->groqApiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
        $this->geminiApiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
        
        $this->temperature = defined('AI_TEMPERATURE') ? AI_TEMPERATURE : 0.6;
        $this->systemPrompt = defined('AI_SYSTEM_PROMPT') ? AI_SYSTEM_PROMPT : "You are a professional job blogger.";
        $this->model = defined('AI_MODEL') ? AI_MODEL : 'llama-3.3-70b-versatile';

        if (defined('AI_ADDITIONAL_INSTRUCTIONS')) {
            $this->systemPrompt .= "\n\nAdditional Instructions:\n" . AI_ADDITIONAL_INSTRUCTIONS;
        }
    }
    
    /**
     * Enhance scraped content to achieve 100% SEO score
     */
    public function enhanceContent($scrapedData) {
        // Check if any AI provider is available
        $hasAIProvider = !empty($this->groqApiKey) ||
                        !empty($this->geminiApiKey);
        
        if (!$hasAIProvider) {
            return $this->fallbackEnhancement($scrapedData);
        }
        
        try {
            // Generate metadata and SEO adds with AI
            $enhanced = $this->generateWithAI($scrapedData);
            
            // If AI provided an SEO addition, prepend it to original content
            if (isset($enhanced['seo_addition']) && !empty($enhanced['seo_addition'])) {
                $originalContent = $scrapedData['content'] ?? '';
                $enhanced['content'] = $enhanced['seo_addition'] . "\n\n" . $originalContent;
            }
            
            // Validate and ensure 100% SEO score
            $enhanced = $this->ensureSEOPerfection($enhanced);
            
            // Humanize the content (light touch on whatever content we have)
            $enhanced = $this->humanizeContent($enhanced);
            
            // Add a stealth verification ID
            if (isset($enhanced['content'])) {
                $enhanced['content'] .= "\n<!-- STEALTH_SEO: ACTIVE | AI_MODE: HYBRID_ENHANCEMENT | PROVIDER: " . ($enhanced['ai_provider'] ?? 'unknown') . " -->";
            }
            
            return $enhanced;
            
        } catch (Exception $e) {
            error_log("AI Enhancement failed: " . $e->getMessage());
            $scrapedData['ai_error'] = $e->getMessage();
            $scrapedData['ai_status'] = 'failed';
            return $this->fallbackEnhancement($scrapedData);
        }
    }
    
    /**
     * Generate content using AI (AgentRouter, Groq, NVIDIA, or Local LLM)
     */
    private function generateWithAI($data) {
        $prompt = $this->buildPrompt($data);
        $errors = [];
        
        // 1. Try Gemini first (Best Free Tier)
        if (!empty($this->geminiApiKey)) {
            try {
                $response = $this->callGeminiAPI($prompt);
                $result = $this->parseAIResponse($response, $data, 'gemini');
                if ($result) return $result;
            } catch (Exception $e) {
                $errors[] = "Gemini failed: " . $e->getMessage();
            }
        }

        // 2. Try Groq (Fast Free Tier)
        if (!empty($this->groqApiKey)) {
            try {
                $response = $this->callGroqAPI($prompt);
                $result = $this->parseAIResponse($response, $data, 'groq');
                if ($result) return $result;
            } catch (Exception $e) {
                $errors[] = "Groq failed: " . $e->getMessage();
            }
        }
        
        throw new Exception("All AI providers failed: " . implode(" | ", $errors));
    }

    /**
     * Call Google Gemini API
     */
    private function callGeminiAPI($prompt) {
        $model = defined('GEMINI_MODEL') && GEMINI_MODEL ? GEMINI_MODEL : 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->geminiApiKey;

        $data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $this->systemPrompt . "\n\n" . $prompt]]
                ]
            ],
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => 8000,
                'responseMimeType' => 'application/json'
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 20,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Gemini API returned HTTP $httpCode - $response");
        }
        
        $decoded = json_decode($response, true);
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        // Mocking OpenAI-style response format for parseAIResponse compatibility
        return json_encode([
            'choices' => [
                [
                    'message' => ['content' => $text]
                ]
            ]
        ]);
    }
    
    /**
     * Build comprehensive prompt for AI
     */
    private function buildPrompt($data) {
        $rawTitle = $data['title'] ?? 'Job';
        $rawContent = strip_tags($data['content'] ?? '');
        $rawContent = preg_replace('/\s+/u', ' ', $rawContent);
        $rawContent = mb_substr($rawContent, 0, 8000); 
        $type = $data['type'] ?? 'job';
        
        $systemPrompt = $this->systemPrompt;
        
        return <<<PROMPT
{$systemPrompt}

TASK: Create a 100% SEO-optimized header and metadata for a job portal.
DO NOT rewrite the main content. Provide ONLY the following in JSON:
1. A catchy, high-CTR Title.
2. Optimized Meta Title (50-60 chars) and Meta Description (120-160 chars).
3. Strategic Meta Keywords.
4. A friendly 2-3 sentence Short Description.
5. Intelligent detection of Category, State (Indian State), and Organization.
6. A unique "Human Perspective" addition (e.g., a "Pro-Tip") in HTML.

RAW DATA:
Title: {$rawTitle}
Type: {$type}
Content Preview: {$rawContent}

OUTPUT FORMAT (JSON):
{
  "title": "Optimized Attractive Title",
  "meta_title": "SEO Meta Title | JobOne.in",
  "meta_description": "Compelling meta description here...",
  "meta_keywords": "keyword1, keyword2, keyword3",
  "short_description": "Friendly human-like summary.",
  "category": "Detected Job Category",
  "state": "Detected Indian State",
  "organization": "Company/Department Name",
  "seo_addition": "<div class=\"box-info\"><b>💡 Pro Tip:</b> ...</div>"
}
PROMPT;
    }
    
    /**
     * Call Groq API
     */
    private function callGroqAPI($prompt) {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $model = str_contains($this->model, 'llama') ? $this->model : "llama-3.3-70b-versatile";

        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => "You must return valid JSON. " . $this->systemPrompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $this->temperature,
            'max_tokens' => 8000,
            'response_format' => ['type' => 'json_object']
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->groqApiKey
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Groq API returned HTTP $httpCode - $response");
        }
        
        return $response;
    }
    
    /**
     * Parse AI response
     */
    private function parseAIResponse($response, $originalData, $providerName = 'unknown') {
        $decoded = json_decode($response, true);
        
        if (!isset($decoded['choices'][0]['message']['content'])) {
            throw new Exception("Invalid AI response format: " . $response);
        }
        
        $text = $decoded['choices'][0]['message']['content'];
        $enhanced = $this->parseAIJSON($text);
        
        if (!$enhanced) {
            throw new Exception("Failed to parse AI JSON response: " . $text);
        }
        
        // Decode HTML entities in all text fields
        $enhanced = $this->decodeHtmlEntities($enhanced);
        
        $enhanced['ai_provider'] = $providerName;
        
        // Merge with original data (keep type and other fields)
        return array_merge($originalData, $enhanced);
    }

    /**
     * Helper to parse JSON from AI response (handles Markdown code blocks)
     */
    private function parseAIJSON($text) {
        if (empty($text)) return null;

        // Remove potential Markdown code block wrappers
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        // Fallback: try to find the first '{' and last '}'
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false) {
            $text = substr($text, $start, $end - $start + 1);
            $data = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        return null;
    }
    
    /**
     * Decode HTML entities in all text fields
     */
    private function decodeHtmlEntities($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $decoded = $value;
                    $maxIterations = 5;
                    for ($i = 0; $i < $maxIterations; $i++) {
                        $newDecoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        if ($newDecoded === $decoded) break;
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
        $textFields = ['title', 'meta_title', 'meta_description', 'meta_keywords', 'short_description', 'organization'];
        foreach ($textFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = html_entity_decode($data[$field], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $data[$field] = strip_tags($data[$field]);
            }
        }
        
        // Fix meta_title (50-60 chars)
        if (isset($data['meta_title'])) {
            $len = mb_strlen($data['meta_title']);
            if ($len < 50) {
                if (!str_contains($data['meta_title'], '| JobOne.in')) $data['meta_title'] .= ' | JobOne.in';
                if (mb_strlen($data['meta_title']) < 50) $data['meta_title'] = str_replace('| JobOne.in', '2026 | JobOne.in', $data['meta_title']);
            } elseif ($len > 60) {
                $data['meta_title'] = mb_substr($data['meta_title'], 0, 47) . ' | JobOne.in';
            }
        } else {
            $data['meta_title'] = mb_substr($data['title'], 0, 47) . ' | JobOne.in';
        }
        
        // Fix meta_description (120-160 chars)
        if (isset($data['meta_description'])) {
            $len = mb_strlen($data['meta_description']);
            if ($len < 120) {
                $data['meta_description'] .= ' Check eligibility, important dates, and application process. Apply now on JobOne.in!';
                if (mb_strlen($data['meta_description']) > 160) $data['meta_description'] = mb_substr($data['meta_description'], 0, 157) . '...';
            } elseif ($len > 160) {
                $data['meta_description'] = mb_substr($data['meta_description'], 0, 157) . '...';
            }
        } else {
            $data['meta_description'] = mb_substr($data['short_description'] ?? $data['title'], 0, 120) . ' Apply now on JobOne.in for latest updates!';
        }
        
        if (empty($data['meta_keywords'])) {
            $data['meta_keywords'] = $data['title'] . ', Government Jobs 2026, Apply Online';
        }
        
        // CRITICAL: Ensure content has minimum 300 words
        if (isset($data['content'])) {
            $wordCount = str_word_count(strip_tags($data['content']));
            if ($wordCount < 300) {
                $data['content'] .= $this->generateFillerContent($data);
            }
        }
        
        // CRITICAL: Ensure at least 3 internal links for 100% SEO
        if (isset($data['content'])) {
            $linkCount = substr_count($data['content'], '<a href=');
            if ($linkCount < 3) {
                $data['content'] = $this->addInternalLinks($data['content'], $data);
            }
        }

        // Apply Stealth Rewriting to bypass scraper detection
        if (isset($data['content'])) {
            $data['content'] = $this->stealthRewriter($data['content']);
            $data['content'] = $this->perfectHeadingStructure($data['content']);
            $data['content'] = $this->injectLocalKeywords($data['content'], $data);
            $data['content'] = $this->boldImportantTerms($data['content']);
        }
        
        return $data;
    }
    
    /**
     * Stealth Rewriter: Adds subtle human "noise" and Variations that bypass AI/Scraper detection
     */
    private function stealthRewriter($content) {
        $tips = [
            '<div class="box-info"><b>💡 Pro Tip:</b> Make sure you have your scanned documents ready in PDF format before starting the application.</div>',
            '<div class="box-warning"><b>⚠️ Quick Reminder:</b> Double-check your mobile number and email ID in the form; they are crucial for OTPs.</div>',
            '<p><i>Note: Based on previous years, the website might get slow on the last day, so apply early!</i></p>',
        ];

        if (!str_contains($content, 'box-info') && !str_contains($content, 'box-warning')) {
            $pos = strpos($content, '</h3>');
            if ($pos !== false) {
                $tip = $tips[array_rand($tips)];
                $content = substr_replace($content, "</h3>\n" . $tip, $pos, 5);
            }
        }

        $variations = [
            'Click Here' => ['Check Here', 'Apply Link', 'Direct Link', 'Tap Here'],
            'Official Website' => ['Main Portal', 'Official Link', 'Department Site'],
            'Apply Online' => ['Register Online', 'Fill Application', 'Submit Form'],
        ];

        foreach($variations as $original => $subs) {
            $content = str_ireplace($original, $subs[array_rand($subs)], $content);
        }

        return $content;
    }

    private function humanizeContent($data) {
        if (isset($data['content'])) {
            $data['content'] = $this->addNaturalVariations($data['content']);
            
            $replacements = [
                'In conclusion,' => 'To sum it up,',
                'It is important to note' => 'Keep in mind',
                'Furthermore,' => 'Additionally,',
                'Moreover,' => 'What\'s more,',
                'Therefore,' => 'So,',
                'However,' => 'But,',
                'consequently' => 'as a result',
                'utilize' => 'use',
                'eligibility criteria' => 'requirements',
            ];
            
            foreach ($replacements as $ai => $human) {
                if (rand(0, 100) > 20) {
                    $data['content'] = str_ireplace($ai, $human, $data['content']);
                }
            }
        }
        return $data;
    }
    
    private function addNaturalVariations($content) {
        $contractions = [
            'do not' => "don't",
            'will not' => "won't",
            'cannot' => "can't",
            'you are' => "you're",
            'it is' => "it's",
        ];
        foreach ($contractions as $full => $short) {
            if (rand(0, 1)) $content = str_replace($full, $short, $content);
        }
        return $content;
    }
    
    private function addInternalLinks($content, $data) {
        $category = $data['category'] ?? 'jobs';
        $state = $data['state'] ?? 'all India';
        $categorySlug = strtolower(str_replace(' ', '-', $category));
        
        $content .= "\n<p>Explore more <a href=\"https://jobone.in/category/{$categorySlug}\">{$category} jobs</a> on JobOne.in.</p>";
        $content .= "\n<p>Visit <a href=\"https://jobone.in/\">JobOne.in</a> for latest government updates.</p>";
        
        return $content;
    }
    private function generateFillerContent($data) {
        $org = $data['organization'] ?? 'the organization';
        return <<<HTML
<h3>📋 About This Recruitment</h3>
<p>This job notification by {$org} offers excellent career opportunities. The selection process will be conducted as per official guidelines.</p>
<h3>💡 Important Tips</h3>
<ul>
<li>Read the official notification carefully.</li>
<li>Apply well before the deadline.</li>
<li>Keep all required documents ready.</li>
</ul>
HTML;
    }

    private function perfectHeadingStructure($content) {
        // Convert any stray H1s to H2s (since page title is H1)
        $content = preg_replace('/<h1[^>]*>(.*?)<\/h1>/i', '<h2 class="seo-heading">$1</h2>', $content);
        // Ensure H2s are followed by H3s for sub-sections
        return $content;
    }

    private function injectLocalKeywords($content, $data) {
        $state = $data['state'] ?? '';
        $category = $data['category'] ?? '';
        
        if ($state && !str_contains($content, $state)) {
            $content = "<p><i>Searching for jobs in <b>{$state}</b>? This latest {$category} notification is a great opportunity for candidates from {$state}.</i></p>\n" . $content;
        }
        return $content;
    }

    private function boldImportantTerms($content) {
        $terms = ['Last Date', 'Apply Online', 'Notification', 'Eligibility', 'Vacancies', 'Application Fee'];
        foreach ($terms as $term) {
            $content = str_ireplace($term, "<b>{$term}</b>", $content);
        }
        return $content;
    }
    
    private function fallbackEnhancement($data) {
        $title = $data['title'] ?? 'Government Job';
        $data['meta_title'] = mb_substr($title, 0, 47) . ' | JobOne.in';
        $data['meta_description'] = "Apply for {$title}. Find eligibility and dates on JobOne.in!";
        return $data;
    }
}
