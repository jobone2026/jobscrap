<?php
/**
 * SambaNova AI Client for JobOne Auto-Poster
 * Replaces old AI with SambaNova AI integration
 */

require_once 'config.php';

class SambaNovaAI {
    private $api_key;
    private $chat_endpoint;
    private $model;
    
    public function __construct() {
        $this->api_key = SAMBANOVA_API_KEY;
        $this->chat_endpoint = SAMBANOVA_CHAT_ENDPOINT;
        $this->model = SAMBANOVA_MODEL;
    }
    
    /**
     * Enhance job content using SambaNova AI
     */
    public function enhanceJobContent($title, $description, $company = '', $location = '') {
        $system_prompt = AI_SYSTEM_PROMPT;
        $additional_instructions = AI_ADDITIONAL_INSTRUCTIONS;
        
        $user_prompt = "
Job Title: $title
Company: $company
Location: $location
Description: $description

Please enhance this job posting with:
1. SEO-optimized title (catchy and includes current year)
2. Meta title (60 chars max)
3. Meta description (160 chars max)
4. Keywords (comma-separated)
5. Enhanced description (professional but engaging)

$additional_instructions
";

        $messages = [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $user_prompt
            ]
        ];
        
        return $this->sendRequest($messages);
    }
    
    /**
     * Generate SEO metadata for job posts
     */
    public function generateSEOMetadata($title, $description) {
        $prompt = "
Create SEO metadata for this job:
Title: $title
Description: $description

Generate:
1. Meta Title (60 chars max, include 2026)
2. Meta Description (160 chars max)
3. Keywords (10 relevant keywords)
4. Slug (URL-friendly)

Format as JSON.
";

        $messages = [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];
        
        return $this->sendRequest($messages);
    }
    
    /**
     * Send request to SambaNova AI
     */
    private function sendRequest($messages, $max_tokens = 1000, $temperature = 0.7) {
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'stream' => false
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->chat_endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $http_code
            ];
        }
        
        $result = json_decode($response, true);
        
        if ($http_code == 200 && isset($result['choices'][0]['message']['content'])) {
            return [
                'success' => true,
                'content' => $result['choices'][0]['message']['content'],
                'usage' => $result['usage'] ?? null,
                'http_code' => $http_code
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error']['message'] ?? 'Unknown error',
                'http_code' => $http_code,
                'raw_response' => $result
            ];
        }
    }
    
    /**
     * Test the AI connection
     */
    public function testConnection() {
        $messages = [
            [
                'role' => 'user',
                'content' => 'Say "SambaNova AI is ready for JobOne!" to confirm you are working.'
            ]
        ];
        
        return $this->sendRequest($messages, 50, 0.1);
    }
}

// Example usage:
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    echo "🧠 Testing SambaNova AI Client...\n";
    
    $ai = new SambaNovaAI();
    $test = $ai->testConnection();
    
    if ($test['success']) {
        echo "✅ " . $test['content'] . "\n";
        
        // Test job enhancement
        echo "\n📝 Testing job enhancement...\n";
        $enhanced = $ai->enhanceJobContent(
            'Software Developer',
            'We are looking for a skilled developer to join our team.',
            'TechCorp',
            'Remote'
        );
        
        if ($enhanced['success']) {
            echo "✅ Job enhancement working!\n";
            echo "Response: " . substr($enhanced['content'], 0, 200) . "...\n";
        } else {
            echo "❌ Job enhancement failed: " . $enhanced['error'] . "\n";
        }
    } else {
        echo "❌ Connection failed: " . $test['error'] . "\n";
    }
}

?>