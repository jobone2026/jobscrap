<?php
/**
 * AI Chat Interface - Test and interact with AI
 */

if (file_exists('config.php')) {
    require_once 'config.php';
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'chat') {
        $userMessage = trim($_POST['message'] ?? '');
        
        if (!$userMessage) {
            echo json_encode(['success' => false, 'message' => 'Please enter a message']);
            exit;
        }
        
        // Call AI
        $apiKey = AGENTROUTER_API_KEY;
        $url = 'https://agentrouter.org/v1/chat/completions';
        
        $systemPrompt = defined('AI_SYSTEM_PROMPT') ? AI_SYSTEM_PROMPT : "You are a helpful assistant for job content formatting.";
        
        $payload = [
            'model' => defined('AI_MODEL') ? AI_MODEL : 'deepseek-v3.2',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ],
            'temperature' => defined('AI_TEMPERATURE') ? AI_TEMPERATURE : 0.3
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $json = json_decode($response, true);
            if (isset($json['choices'][0]['message']['content'])) {
                echo json_encode([
                    'success' => true,
                    'message' => $json['choices'][0]['message']['content']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid AI response']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'AI API Error: HTTP ' . $httpCode]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat - Test JobOne AI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 90vh;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .chat-area {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        .message.user { justify-content: flex-end; }
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        .message.user .message-bubble {
            background: #667eea;
            color: white;
            border-bottom-right-radius: 4px;
        }
        .message.ai .message-bubble {
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 4px;
        }
        .message-label {
            font-size: 11px;
            color: #666;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }
        .input-group {
            display: flex;
            gap: 10px;
        }
        #messageInput {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }
        #messageInput:focus {
            border-color: #667eea;
        }
        #sendBtn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 24px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        #sendBtn:hover { transform: scale(1.05); }
        #sendBtn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 10px;
            color: #666;
        }
        .loading.active { display: block; }
        .examples {
            padding: 15px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        .examples h3 {
            font-size: 14px;
            margin-bottom: 8px;
            color: #856404;
        }
        .example-btn {
            display: inline-block;
            padding: 6px 12px;
            margin: 4px;
            background: white;
            border: 1px solid #ffc107;
            border-radius: 16px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .example-btn:hover {
            background: #ffc107;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 AI Chat Interface</h1>
            <p>Test and interact with JobOne AI Assistant</p>
        </div>
        
        <div class="chat-area" id="chatArea">
            <div class="examples">
                <h3>💡 Try these examples:</h3>
                <button class="example-btn" onclick="sendExample('Format this job: UPSC Civil Services 2024, 1000 posts, apply before 15 March')">Format Job Info</button>
                <button class="example-btn" onclick="sendExample('Clean this text and remove spam: Join our Telegram channel for updates. UPSC Notification 2024.')">Remove Spam</button>
                <button class="example-btn" onclick="sendExample('Create a professional job description for Software Engineer position')">Create Job Description</button>
            </div>
            
            <div class="message ai">
                <div>
                    <div class="message-label">AI Assistant</div>
                    <div class="message-bubble">
                        👋 Hello! I'm your JobOne AI Assistant. I can help you:
                        <br><br>
                        • Format job notifications professionally
                        <br>• Remove spam and promotional content
                        <br>• Create clean HTML content
                        <br>• Test AI responses
                        <br><br>
                        Ask me anything!
                    </div>
                </div>
            </div>
        </div>
        
        <div class="loading" id="loading">
            <span>🤔 AI is thinking...</span>
        </div>
        
        <div class="input-area">
            <div class="input-group">
                <input type="text" id="messageInput" placeholder="Type your message here..." onkeypress="handleKeyPress(event)">
                <button id="sendBtn" onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>

    <script>
        function addMessage(text, isUser) {
            const chatArea = document.getElementById('chatArea');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + (isUser ? 'user' : 'ai');
            
            messageDiv.innerHTML = `
                <div>
                    <div class="message-label">${isUser ? 'You' : 'AI Assistant'}</div>
                    <div class="message-bubble">${escapeHtml(text)}</div>
                </div>
            `;
            
            chatArea.appendChild(messageDiv);
            chatArea.scrollTop = chatArea.scrollHeight;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message) return;
            
            // Add user message
            addMessage(message, true);
            input.value = '';
            
            // Show loading
            document.getElementById('loading').classList.add('active');
            document.getElementById('sendBtn').disabled = true;
            
            // Send to AI
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=chat&message=' + encodeURIComponent(message)
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                document.getElementById('sendBtn').disabled = false;
                
                if (data.success) {
                    addMessage(data.message, false);
                } else {
                    addMessage('❌ Error: ' + data.message, false);
                }
            })
            .catch(err => {
                document.getElementById('loading').classList.remove('active');
                document.getElementById('sendBtn').disabled = false;
                addMessage('❌ Network error: ' + err.message, false);
            });
        }
        
        function sendExample(text) {
            document.getElementById('messageInput').value = text;
            sendMessage();
        }
        
        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }
    </script>
</body>
</html>
