<?php
// Read api.php, remove BOM, and check encoding of get_ai_prompt()
$file = 'api.php';
$content = file_get_contents($file);

if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
    echo "BOM detected. Removing...\n";
    $content = substr($content, 3);
    file_put_contents($file, $content);
}

// Test json_encode on the prompt
require 'api.php';
$prompt = get_ai_prompt([], 'job');
$payload = json_encode([
    'model' => 'test',
    'messages' => [['role' => 'system', 'content' => $prompt]]
]);

if ($payload === false) {
    echo "json_encode FAILED. Error: " . json_last_error_msg() . "\n";
} else {
    echo "json_encode SUCCESS.\n";
}
