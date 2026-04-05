<?php
/**
 * api_proxy.php — Proxy for API calls (token check, categories, states, list posts)
 * Called via GET: ?action=status|categories|states|posts&page=1&type=job
 */

header('Content-Type: application/json');

if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    define('JOBONE_API_TOKEN', 'YOUR_TOKEN_HERE');
}

define('API_BASE', 'https://jobone.in/api');
define('API_TOKEN', JOBONE_API_TOKEN);

function apiCall($method, $endpoint, $data = null) {
    $ch = curl_init(API_BASE . $endpoint);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . API_TOKEN,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ];
    if ($data) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => json_decode($res, true), 'code' => $code];
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'status':
        $r = apiCall('GET', '/token');
        echo json_encode(['success' => $r['code'] === 200, 'data' => $r['body'], 'code' => $r['code']]);
        break;

    case 'categories':
        $r = apiCall('GET', '/categories');
        echo json_encode($r['body'] ?? ['success' => false]);
        break;

    case 'states':
        $r = apiCall('GET', '/states');
        echo json_encode($r['body'] ?? ['success' => false]);
        break;

    case 'posts':
        $page  = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 10);
        $type  = $_GET['type'] ?? '';
        $qs = http_build_query(array_filter(['page' => $page, 'limit' => $limit, 'type' => $type]));
        $r = apiCall('GET', '/posts?' . $qs);
        echo json_encode($r['body'] ?? ['success' => false]);
        break;

    case 'delete':
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'No ID']); break; }
        $r = apiCall('DELETE', '/posts/' . $id);
        echo json_encode(['success' => in_array($r['code'], [200, 204]), 'code' => $r['code'], 'body' => $r['body']]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
