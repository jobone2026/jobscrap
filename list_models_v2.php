<?php
require_once 'config.php';
$apiKey = GEMINI_API_KEY;
// Get EVERY available model
$url = "https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
echo "LIST OF MODELS:\n";
if (isset($data['models'])) {
    foreach ($data['models'] as $m) {
        echo "- " . $m['name'] . " (" . $m['displayName'] . ")\n";
    }
} else {
    echo "ERROR: Could not list models.\n";
    echo $response;
}
