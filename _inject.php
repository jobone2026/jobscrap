<?php
$file = __DIR__ . '/api.php';
$content = file_get_contents($file);

// 1. Add PDF constants
$constants = <<<'PHP'
define('INDEXNOW_HOST', 'jobone.in');

// ── PDF Storage ──────────────────────────────────────────────────────────────
// On live server: /var/www/jobone/public/pdfs/
// Locally under XAMPP: adjust PDF_STORAGE_DIR if needed
define('PDF_STORAGE_DIR', dirname(__DIR__) . '/pdfs/');
define('PDF_STORAGE_URL', JOBONE_SITE_URL . '/pdfs/');
PHP;
$content = str_replace("define('INDEXNOW_HOST', 'jobone.in');", $constants, $content);

// 2. Add aggregator domains
$agg = <<<'PHP'
    'jobone.in',
    // Scraper / aggregator blog sites
    'rajasthanvacancy.com',
    'rajasthanhelp.com',
    'sarkarinaukrihelp.com',
    'govtjobadda.com',
    'latestjobs.in',
    'naukrimessenger.com',
];
PHP;
$content = str_replace("    'jobone.in',\r\n];", $agg, $content);
$content = str_replace("    'jobone.in',\n];", $agg, $content);

// 3. Add JSON_INVALID_UTF8_SUBSTITUTE to json_encode for openai
$search = "            'response_format' => ['type' => 'json_object'],\r\n        ]);";
$search2 = "            'response_format' => ['type' => 'json_object'],\n        ]);";
$replace = <<<'PHP'
            'response_format' => ['type' => 'json_object'],
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        if ($payload === false) {
            send_json(['success' => false, 'message' => 'Failed to build OpenAI payload: ' . json_last_error_msg()]);
        }
PHP;
if (strpos($content, $search) !== false) {
    $content = str_replace($search, str_replace("\n", "\r\n", $replace), $content);
} elseif (strpos($content, $search2) !== false) {
    $content = str_replace($search2, $replace, $content);
}

// 4. Inject download_pdf and serve_pdf
$newCases = <<<'PHPCODE'

    // ── download_pdf ──────────────────────────────────────────────────────────
    case 'download_pdf':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $pdfUrl = trim($input['url'] ?? '');
        if (!$pdfUrl)
            send_json(['success' => false, 'message' => 'URL is required']);
        if (!filter_var($pdfUrl, FILTER_VALIDATE_URL))
            send_json(['success' => false, 'message' => 'Invalid URL']);

        if (!is_dir(PDF_STORAGE_DIR)) {
            if (!mkdir(PDF_STORAGE_DIR, 0755, true))
                send_json(['success' => false, 'message' => 'Cannot create PDF storage directory']);
        }

        $urlPathPdf  = parse_url($pdfUrl, PHP_URL_PATH) ?? '';
        $baseNamePdf = basename($urlPathPdf);
        $baseNamePdf = preg_replace('/[^A-Za-z0-9._-]/', '-', $baseNamePdf);
        if (!str_ends_with(strtolower($baseNamePdf), '.pdf')) $baseNamePdf .= '.pdf';
        $fileNamePdf = date('Ymd_His') . '_' . $baseNamePdf;
        $savePathPdf = PDF_STORAGE_DIR . $fileNamePdf;

        $chPdf = curl_init($pdfUrl);
        curl_setopt_array($chPdf, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: application/pdf,*/*', 'Accept-Language: en-IN,en;q=0.9'],
        ]);
        $pdfContent    = curl_exec($chPdf);
        $httpCodePdf   = curl_getinfo($chPdf, CURLINFO_HTTP_CODE);
        $curlErrorPdf  = curl_error($chPdf);
        $contentTypePdf = curl_getinfo($chPdf, CURLINFO_CONTENT_TYPE);
        curl_close($chPdf);

        if ($curlErrorPdf) send_json(['success' => false, 'message' => 'cURL error: ' . $curlErrorPdf]);
        if ($httpCodePdf >= 400) send_json(['success' => false, 'message' => 'HTTP ' . $httpCodePdf . ' - could not fetch PDF']);
        if (empty($pdfContent)) send_json(['success' => false, 'message' => 'Empty response from source']);
        if (strncmp($pdfContent, '%PDF', 4) !== 0) send_json(['success' => false, 'message' => 'Not a valid PDF file (type: ' . $contentTypePdf . ')']);
        if (file_put_contents($savePathPdf, $pdfContent) === false) send_json(['success' => false, 'message' => 'Failed to save PDF to disk']);

        send_json([
            'success'    => true,
            'message'    => 'PDF downloaded and hosted successfully.',
            'hosted_url' => PDF_STORAGE_URL . $fileNamePdf,
            'file_name'  => $fileNamePdf,
            'file_size'  => strlen($pdfContent),
            'source_url' => $pdfUrl,
        ]);

    // ── serve_pdf ─────────────────────────────────────────────────────────────
    case 'serve_pdf':
        ob_end_clean();
        $fileNameServe = basename($_GET['file'] ?? '');
        if (!$fileNameServe || !preg_match('/^[A-Za-z0-9._-]+\.pdf$/i', $fileNameServe)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid file name']);
            exit;
        }
        $filePathServe = PDF_STORAGE_DIR . $fileNameServe;
        if (!file_exists($filePathServe)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'PDF not found']);
            exit;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $fileNameServe . '"');
        header('Content-Length: ' . filesize($filePathServe));
        header('Cache-Control: public, max-age=86400');
        header('X-Frame-Options: SAMEORIGIN');
        readfile($filePathServe);
        exit;

PHPCODE;

if (strpos($content, 'download_pdf') === false) {
    // Insert before default:
    $pos = strpos($content, "    default:\r\n        http_response_code(404);");
    if ($pos === false) $pos = strpos($content, "    default:\n        http_response_code(404);");
    
    if ($pos !== false) {
        $content = substr_replace($content, $newCases, $pos, 0);
    }
}

file_put_contents($file, $content);
echo "SUCCESS";
