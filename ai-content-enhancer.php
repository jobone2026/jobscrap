<?php
/**
 * Optimized AI Content Enhancer for JobOne
 * Minimal token usage - only title and SEO metadata
 * Uses SambaNova AI
 */

require_once 'config.php';

class AIContentEnhancer {
    
    private $apiKey;
    private $chatEndpoint;
    private $model;

    public function __construct() {
        $this->apiKey = SAMBANOVA_API_KEY ?? '';
        $this->chatEndpoint = SAMBANOVA_CHAT_ENDPOINT ?? 'https://api.sambanova.ai/v1/chat/completions';
        $this->model = SAMBANOVA_MODEL ?? 'Meta-Llama-3.1-8B-Instruct';
    }
    
    /**
     * Enhance content with minimal AI usage (title + SEO only)
     */
    public function enhanceContent($scrapedData) {
        if (empty($this->apiKey)) {
            return $this->fallbackEnhancement($scrapedData);
        }
        
        try {
            $enhanced = $this->generateWithSambaNovaAI($scrapedData);
            $enhanced['content'] = $scrapedData['content'] ?? '';
            $enhanced = $this->ensureBasicSEO($enhanced);
            $enhanced['content'] .= "\n<!-- SAMBANOVA_SEO_OPTIMIZED -->";
            return $enhanced;
            
        } catch (Exception $e) {
            error_log("SambaNova AI failed: " . $e->getMessage());
            $scrapedData['ai_error'] = $e->getMessage();
            return $this->fallbackEnhancement($scrapedData);
        }
    }
    
    /**
     * Generate with SambaNova AI (minimal tokens)
     */
    private function generateWithSambaNovaAI($data) {
        $prompt = $this->buildMinimalPrompt($data);
        $response = $this->callSambaNovaAPI($prompt);
        return $this->parseAIResponse($response, $data);
    }

    /**
     * Build minimal prompt (save tokens)
     */
    private function buildMinimalPrompt($data) {
        $title = $data['title'] ?? 'Job';
        $content = strip_tags($data['content'] ?? '');
        $content = mb_substr($content, 0, 400);
        
        return "Create SEO metadata for job posting. Return ONLY valid JSON:

Title: $title
Content: $content

Required JSON format:
{
  \"title\": \"Enhanced SEO title with 2026\",
  \"meta_title\": \"Title | JobOne.in\",
  \"short_description\": \"Write 2-3 complete sentences summarizing the job. MUST be 120-160 characters. End with period.\",
  \"meta_description\": \"Write 1-2 complete sentences. MUST be 135-145 characters. End with period.\",
  \"meta_keywords\": \"15-20 relevant keywords, comma separated, 250-350 characters total\"
}

RULES:
1. short_description: 120-160 chars, complete sentences, end with period
2. meta_description: 135-145 chars, complete sentences, end with period  
3. meta_keywords: 250-350 chars total
4. NO ellipsis (...), NO mid-word cuts, NO incomplete sentences";
    }

    /**
     * Call SambaNova API (optimized with retry)
     */
    private function callSambaNovaAPI($prompt) {
        $data = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.3,
            'max_tokens' => 400, // Increased from 200 to 400 to prevent cutoffs
            'stream' => false
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        // Try API call with retry for rate limits
        $maxRetries = 3; // Increased retries
        $retryDelay = 5; // Longer delay for rate limits
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->chatEndpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Increased timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL Error: $error");
            }
            
            if ($httpCode === 200) {
                return $response; // Success
            }
            
            if ($httpCode === 429 && $attempt < $maxRetries) {
                // Rate limited, wait longer and retry
                error_log("SambaNova rate limited, waiting {$retryDelay}s before retry $attempt/$maxRetries");
                sleep($retryDelay);
                $retryDelay += 2; // Increase delay each retry
                continue;
            }
            
            throw new Exception("API Error: HTTP $httpCode");
        }
        
        throw new Exception("API failed after $maxRetries attempts");
    }
    
    /**
     * Parse AI response
     */
    private function parseAIResponse($response, $originalData) {
        $decoded = json_decode($response, true);
        
        if (!isset($decoded['choices'][0]['message']['content'])) {
            throw new Exception("Invalid response format");
        }
        
        $content = $decoded['choices'][0]['message']['content'];
        
        // Extract JSON from response
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false) {
            $jsonStr = substr($content, $start, $end - $start + 1);
            $aiData = json_decode($jsonStr, true);
            
            if ($aiData) {
                $aiData['ai_provider'] = 'sambanova';
                return array_merge($originalData, $aiData);
            }
        }
        
        throw new Exception("Failed to parse JSON response");
    }
    
    /**
     * Ensure basic SEO requirements
     */
    private function ensureBasicSEO($data) {
        // Clean function to remove line break entities
        $cleanText = function($text) {
            if (empty($text)) return '';
            // Remove line breaks and their HTML entities
            $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
            $text = preg_replace('/&#13;?|&#10;?|&#x0D;?|&#x0A;?/i', ' ', $text);
            $text = preg_replace('/\s+/', ' ', $text);
            return trim($text);
        };
        
        // Generate short_description if not present OR too short (100-160 chars)
        $shortDescLen = mb_strlen($data['short_description'] ?? '');
        if (empty($data['short_description']) || $shortDescLen < 100) {
            $content = strip_tags($data['content'] ?? '');
            $content = $cleanText($content);
            
            // Try to get 100-160 chars ending at a complete sentence
            $target = mb_substr($content, 0, 160);
            
            // Find last sentence ending (period, exclamation, question mark) after position 100
            $lastPeriod = max(
                mb_strrpos($target, '.'),
                mb_strrpos($target, '!'),
                mb_strrpos($target, '?')
            );
            
            if ($lastPeriod !== false && $lastPeriod >= 100) {
                // Cut at last sentence
                $data['short_description'] = mb_substr($content, 0, $lastPeriod + 1);
            } else {
                // No good sentence break, find last complete word before 160
                $lastSpace = mb_strrpos($target, ' ');
                if ($lastSpace !== false && $lastSpace >= 100) {
                    $data['short_description'] = mb_substr($content, 0, $lastSpace) . '.';
                } else {
                    // Last resort - just take 157 chars and add period
                    $data['short_description'] = mb_substr($content, 0, 157) . '.';
                }
            }
        } else {
            // Clean existing short_description
            $data['short_description'] = $cleanText($data['short_description']);
        }
        
        // Fix meta_title
        if (isset($data['meta_title'])) {
            if (mb_strlen($data['meta_title']) > 60) {
                $data['meta_title'] = mb_substr($data['meta_title'], 0, 57) . '...';
            }
            if (!str_contains($data['meta_title'], 'JobOne.in')) {
                $data['meta_title'] = str_replace(' | JobOne.in', '', $data['meta_title']) . ' | JobOne.in';
            }
        } else {
            $data['meta_title'] = mb_substr($data['title'], 0, 47) . ' | JobOne.in';
        }
        
        // Fix meta_description (130-150 chars) - ensure complete sentences and words
        if (isset($data['meta_description'])) {
            // Clean line breaks first
            $data['meta_description'] = $cleanText($data['meta_description']);
            
            $len = mb_strlen($data['meta_description']);
            
            // If too short, add more content
            if ($len < 130) {
                $data['meta_description'] .= ' Apply now on JobOne.in!';
            }
            
            // If too long, trim to complete word before 150 chars
            if ($len > 150) {
                // First try to find last complete sentence before 145 chars
                $trimmed = mb_substr($data['meta_description'], 0, 145);
                $lastPeriod = max(
                    mb_strrpos($trimmed, '.'),
                    mb_strrpos($trimmed, '!'),
                    mb_strrpos($trimmed, '?')
                );
                
                if ($lastPeriod !== false && $lastPeriod >= 130) {
                    // Cut at last sentence
                    $data['meta_description'] = mb_substr($data['meta_description'], 0, $lastPeriod + 1);
                } else {
                    // No good sentence break, find last complete word before 150
                    $trimmed = mb_substr($data['meta_description'], 0, 150);
                    $lastSpace = mb_strrpos($trimmed, ' ');
                    
                    if ($lastSpace !== false && $lastSpace >= 130) {
                        $data['meta_description'] = mb_substr($data['meta_description'], 0, $lastSpace) . '.';
                    } else {
                        $data['meta_description'] = mb_substr($data['meta_description'], 0, 149) . '.';
                    }
                }
            }
            
            // Final length check
            $finalLen = mb_strlen($data['meta_description']);
            if ($finalLen < 130) {
                $data['meta_description'] .= ' Check details now!';
            }
        } else {
            $baseDesc = mb_substr($data['title'], 0, 100);
            $data['meta_description'] = "$baseDesc. Apply now on JobOne.in!";
            
            $len = mb_strlen($data['meta_description']);
            if ($len < 130) {
                $data['meta_description'] = "$baseDesc. Find eligibility and apply on JobOne.in!";
            }
            if ($len > 150) {
                $trimmed = mb_substr($data['meta_description'], 0, 150);
                $lastSpace = mb_strrpos($trimmed, ' ');
                if ($lastSpace !== false && $lastSpace >= 130) {
                    $data['meta_description'] = mb_substr($data['meta_description'], 0, $lastSpace) . '.';
                }
            }
        }
        
        // Final clean of meta_description
        $data['meta_description'] = $cleanText($data['meta_description']);
        
        if (empty($data['meta_keywords'])) {
            // Generate comprehensive keywords (200-400 chars)
            $baseKeywords = [
                $data['title'],
                'Jobs 2026',
                'Apply Online',
                'Government Jobs',
                'Latest Recruitment',
                'Job Notification',
                'Career Opportunities',
                'Employment News',
                'Job Vacancy',
                'Online Application',
                'Eligibility Criteria',
                'Selection Process',
                'Job Portal',
                'JobOne.in',
                'Sarkari Naukri',
                'Govt Jobs India',
                'Job Alert',
                'Recruitment 2026',
                'Job Opening',
                'Apply Now'
            ];
            $data['meta_keywords'] = implode(', ', $baseKeywords);
            
            // Ensure 200-400 character range
            if (mb_strlen($data['meta_keywords']) < 200) {
                $extraKeywords = [
                    'Job Search',
                    'Employment Opportunity',
                    'Career Growth',
                    'Job Application',
                    'Hiring Process',
                    'Job Requirements',
                    'Work Opportunity',
                    'Professional Jobs',
                    'Job Listings',
                    'Employment Portal'
                ];
                $data['meta_keywords'] .= ', ' . implode(', ', $extraKeywords);
            }
            
            // Trim if over 400 characters
            if (mb_strlen($data['meta_keywords']) > 400) {
                $data['meta_keywords'] = mb_substr($data['meta_keywords'], 0, 397) . '...';
            }
        }
        
        return $data;
    }
    
    /**
     * Fallback enhancement (no AI) - Enhanced titles
     */
    private function fallbackEnhancement($data) {
        $originalTitle = $data['title'] ?? 'Government Job';
        
        // Enhance title even without AI
        $enhancedTitle = $this->enhanceTitleFallback($originalTitle);
        
        $data['title'] = $enhancedTitle;
        $data['meta_title'] = mb_substr($enhancedTitle, 0, 47) . ' | JobOne.in';
        
        // Generate meta description (130-150 chars) - complete sentences
        $baseDesc = "Apply for $enhancedTitle. Find eligibility, important dates and application process on JobOne.in";
        $len = mb_strlen($baseDesc);
        
        if ($len < 130) {
            $data['meta_description'] = "$baseDesc for latest government job updates!";
        } elseif ($len > 150) {
            // Trim to last complete sentence before 150
            $trimmed = mb_substr($baseDesc, 0, 147);
            $lastPeriod = max(
                mb_strrpos($trimmed, '.'),
                mb_strrpos($trimmed, '!')
            );
            
            if ($lastPeriod !== false && $lastPeriod > 120) {
                $data['meta_description'] = mb_substr($baseDesc, 0, $lastPeriod + 1);
            } else {
                $data['meta_description'] = mb_substr($baseDesc, 0, 149) . '.';
            }
        } else {
            $data['meta_description'] = $baseDesc;
        }
        
        // Final check for 130-150 range with complete sentences
        $finalLen = mb_strlen($data['meta_description']);
        if ($finalLen < 130) {
            $data['meta_description'] .= ' Check now!';
        }
        if ($finalLen > 150) {
            // Find last sentence before 150
            $trimmed = mb_substr($data['meta_description'], 0, 147);
            $lastPeriod = max(
                mb_strrpos($trimmed, '.'),
                mb_strrpos($trimmed, '!')
            );
            if ($lastPeriod !== false && $lastPeriod > 120) {
                $data['meta_description'] = mb_substr($data['meta_description'], 0, $lastPeriod + 1);
            } else {
                $data['meta_description'] = mb_substr($data['meta_description'], 0, 149) . '.';
            }
        }
        
        // Generate comprehensive keywords for fallback (200-400 chars)
        $keywords = [
            $enhancedTitle,
            'Jobs 2026',
            'Apply Online',
            'Government Jobs',
            'Latest Recruitment',
            'Job Notification',
            'Career Opportunities',
            'Employment News',
            'Job Vacancy',
            'Online Application',
            'Eligibility Criteria',
            'Selection Process',
            'Job Portal',
            'JobOne.in',
            'Sarkari Naukri',
            'Govt Jobs India',
            'Job Alert',
            'Recruitment 2026',
            'Job Opening',
            'Apply Now',
            'Job Search',
            'Employment Opportunity'
        ];
        
        $data['meta_keywords'] = implode(', ', $keywords);
        
        // Ensure 200-400 character range
        if (mb_strlen($data['meta_keywords']) > 400) {
            $data['meta_keywords'] = mb_substr($data['meta_keywords'], 0, 397) . '...';
        }
        
        return $data;
    }
    
    /**
     * Enhance title without AI (fallback method)
     */
    private function enhanceTitleFallback($title) {
        // Add current year if not present
        if (!str_contains($title, '2026')) {
            $title = $title . ' 2026';
        }
        
        // Add "Jobs" if not present and it's a job posting
        if (!str_contains(strtolower($title), 'job') && !str_contains(strtolower($title), 'recruitment')) {
            $title = $title . ' Jobs';
        }
        
        // Add "Latest" for more appeal
        if (!str_contains(strtolower($title), 'latest')) {
            $title = 'Latest ' . $title;
        }
        
        // Clean up any double spaces
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);
        
        return $title;
    }
}

?>