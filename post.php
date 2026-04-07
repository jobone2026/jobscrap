<?php
/**
 * post.php — Submits prepared post data to jobone.in API
 * Accepts POST with JSON body matching jobone.in /api/posts schema
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    define('JOBONE_API_TOKEN', 'YOUR_TOKEN_HERE');
}

define('API_BASE', 'https://jobone.in/api');
define('API_TOKEN', JOBONE_API_TOKEN);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
    exit;
}

// ─── Validate required fields ─────────────────────────────────────────────────
$required = ['title', 'type', 'short_description', 'content', 'category_id'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$validTypes = ['job', 'admit_card', 'result', 'answer_key', 'syllabus', 'blog'];
if (!in_array($input['type'], $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid post type.']);
    exit;
}

// ─── Build payload ────────────────────────────────────────────────────────────
$payload = [
    'title'             => substr(trim($input['title']), 0, 255),
    'type'              => $input['type'],
    'short_description' => trim($input['short_description']),
    'content'           => $input['content'],
    'category_id'       => (int) $input['category_id'],
];

// Optional fields
if (!empty($input['state_id']))            $payload['state_id']            = (int) $input['state_id'];
if (!empty($input['last_date']))           $payload['last_date']           = $input['last_date'];
if (!empty($input['notification_date']))   $payload['notification_date']   = $input['notification_date'];
if (isset($input['total_posts']) && $input['total_posts'] !== '' && $input['total_posts'] !== null) {
    $payload['total_posts'] = (int) $input['total_posts'];
}
if (!empty($input['important_links']) && is_array($input['important_links'])) {
    $links = [];
    foreach ($input['important_links'] as $l) {
        if (!empty($l['title']) && !empty($l['url'])) {
            $links[] = [
                'title' => trim($l['title']),
                'url' => trim($l['url']),
                'name' => trim($l['title']),
                'label' => trim($l['title']),
                'link' => trim($l['url'])
            ];
        }
    }
    if (!empty($links)) {
        $payload['important_links'] = $links;
    }
}
if (isset($input['is_featured']))          $payload['is_featured']         = (bool) $input['is_featured'];
if (!empty($input['meta_title']))          $payload['meta_title']          = substr(trim($input['meta_title']), 0, 60);
if (!empty($input['meta_description']))    $payload['meta_description']    = substr(trim($input['meta_description']), 0, 160);
if (!empty($input['meta_keywords']))       $payload['meta_keywords']       = substr(trim($input['meta_keywords']), 0, 1000);

// ─── Call jobone.in API ───────────────────────────────────────────────────────
$ch = curl_init(API_BASE . '/posts');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . API_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$apiResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError   = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'cURL error: ' . $curlError]);
    exit;
}

$decoded = json_decode($apiResponse, true);

if ($httpCode === 201 || ($httpCode === 200 && !empty($decoded['success']))) {
    echo json_encode([
        'success' => true,
        'message' => 'Post published successfully!',
        'data'    => $decoded['data'] ?? $decoded,
        'http_code' => $httpCode,
    ]);
} else {
    $errMsg = $decoded['message'] ?? $decoded['error'] ?? 'API Error';
    $errors = $decoded['errors'] ?? [];
    echo json_encode([
        'success'    => false,
        'message'    => $errMsg,
        'errors'     => $errors,
        'http_code'  => $httpCode,
        'raw'        => $apiResponse,
    ]);
}
