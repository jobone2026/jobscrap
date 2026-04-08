<?php
// Load config
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    define('JOBONE_API_TOKEN', 'YOUR_TOKEN_HERE');
}

define('API_BASE', 'https://jobone.in/api');
define('API_TOKEN', JOBONE_API_TOKEN);

// Fetch categories & states from API
function apiGet($endpoint) {
    $ch = curl_init(API_BASE . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . API_TOKEN,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return $data['data'] ?? [];
}

$categories = apiGet('/categories');
$states = apiGet('/states');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>JobOne Blog Poster — Smart Blog Scraper & Publisher</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --font: 'Inter', system-ui, -apple-system, sans-serif;
      --bg: #0b0f1a;
      --bg-2: #111827;
      --bg-3: #1f2937;
      --surface: rgba(30, 41, 59, 0.6);
      --surface-2: rgba(51, 65, 85, 0.4);
      --border: rgba(148, 163, 184, 0.12);
      --border-2: rgba(148, 163, 184, 0.2);
      --text-1: #f1f5f9;
      --text-2: #cbd5e1;
      --text-3: #94a3b8;
      --accent: #a78bfa;
      --accent-2: #8b5cf6;
      --accent-glow: rgba(139, 92, 246, 0.3);
      --success: #34d399;
      --success-2: #10b981;
      --danger: #f87171;
      --warn: #fbbf24;
      --info: #60a5fa;
      --radius: 14px;
      --radius-sm: 8px;
      --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
      --shadow-lg: 0 16px 48px rgba(0, 0, 0, 0.5);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: var(--font);
      background: var(--bg);
      color: var(--text-2);
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* Animated background */
    .bg-orbs { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
    .orb {
      position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.15;
      animation: floatOrb 20s ease-in-out infinite alternate;
    }
    .orb-1 { width: 500px; height: 500px; background: #8b5cf6; top: -10%; left: -5%; }
    .orb-2 { width: 400px; height: 400px; background: #ec4899; bottom: -10%; right: -5%; animation-delay: -7s; }
    .orb-3 { width: 350px; height: 350px; background: #06b6d4; top: 50%; left: 50%; animation-delay: -14s; }
    @keyframes floatOrb {
      0% { transform: translate(0, 0) scale(1); }
      50% { transform: translate(60px, -40px) scale(1.15); }
      100% { transform: translate(-30px, 50px) scale(0.9); }
    }

    .app-wrapper { position: relative; z-index: 1; max-width: 1100px; margin: 0 auto; padding: 0 24px 48px; }

    /* Header */
    .app-header {
      padding: 20px 0;
      border-bottom: 1px solid var(--border);
      margin-bottom: 32px;
    }
    .header-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .logo { display: flex; align-items: center; gap: 12px; text-decoration: none; }
    .logo-icon {
      width: 42px; height: 42px; background: linear-gradient(135deg, var(--accent), #ec4899);
      border-radius: 12px; display: flex; align-items: center; justify-content: center;
      color: white; box-shadow: 0 4px 16px var(--accent-glow);
    }
    .logo-icon svg { width: 22px; height: 22px; }
    .logo-brand { font-size: 1.3rem; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; }
    .logo-tagline { font-size: 0.72rem; color: var(--accent); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
    .logo-text { display: flex; flex-direction: column; }
    .header-nav { display: flex; gap: 8px; }
    .header-nav a {
      padding: 8px 16px; border-radius: var(--radius-sm); color: var(--text-3);
      text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s;
    }
    .header-nav a:hover, .header-nav a.active { background: var(--surface); color: var(--text-1); }

    /* Hero */
    .hero { text-align: center; padding: 24px 0 36px; }
    .hero-title { font-size: clamp(1.5rem, 3.5vw, 2.4rem); font-weight: 900; color: var(--text-1); letter-spacing: -1px; line-height: 1.2; }
    .gradient-text { background: linear-gradient(135deg, var(--accent), #ec4899, #f97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .hero-subtitle { color: var(--text-3); margin-top: 12px; font-size: 1rem; max-width: 600px; margin-inline: auto; line-height: 1.6; }

    /* Cards */
    .card {
      background: var(--surface); backdrop-filter: blur(16px);
      border: 1px solid var(--border); border-radius: var(--radius);
      padding: 28px; margin-bottom: 24px; box-shadow: var(--shadow);
      transition: all 0.3s ease;
    }
    .card:hover { border-color: var(--border-2); }
    .card-header { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; flex-wrap: wrap; }
    .card-icon { font-size: 1.5rem; background: var(--surface-2); padding: 10px; border-radius: 10px; line-height: 1; }
    .card-title { font-size: 1.15rem; font-weight: 700; color: var(--text-1); }
    .card-desc { font-size: 0.82rem; color: var(--text-3); margin-top: 2px; }

    /* URL Input */
    .url-input-group { display: flex; gap: 10px; }
    .url-input {
      flex: 1; padding: 14px 18px; background: var(--bg-2); border: 1px solid var(--border-2);
      border-radius: var(--radius-sm); color: var(--text-1); font-size: 0.95rem; font-family: var(--font);
      outline: none; transition: all 0.2s;
    }
    .url-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    .url-input::placeholder { color: var(--text-3); }

    /* Buttons */
    .btn {
      display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px;
      border-radius: var(--radius-sm); font-family: var(--font); font-size: 0.88rem;
      font-weight: 600; border: none; cursor: pointer; transition: all 0.2s ease; text-decoration: none;
      white-space: nowrap;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: white; box-shadow: 0 4px 16px var(--accent-glow);
    }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 24px var(--accent-glow); }
    .btn-success { background: linear-gradient(135deg, var(--success), var(--success-2)); color: white; }
    .btn-success:hover { transform: translateY(-1px); }
    .btn-ghost { background: transparent; color: var(--text-3); border: 1px solid var(--border-2); }
    .btn-ghost:hover { background: var(--surface-2); color: var(--text-1); }
    .btn-outline { background: transparent; color: var(--accent); border: 1px solid var(--accent); }
    .btn-outline:hover { background: rgba(139, 92, 246, 0.1); }
    .btn-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
    .btn-sm { padding: 6px 12px; font-size: 0.78rem; }
    .btn-lg { padding: 14px 28px; font-size: 0.95rem; }
    .btn-icon { width: 16px; height: 16px; }

    /* Loading */
    .hidden { display: none !important; }
    .loading-card { text-align: center; padding: 48px; }
    .loading-inner { display: flex; flex-direction: column; align-items: center; gap: 24px; }
    .spinner {
      width: 48px; height: 48px; border: 3px solid var(--border-2);
      border-top-color: var(--accent); border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .lstep { font-size: 0.88rem; color: var(--text-3); padding: 6px 0; transition: all 0.3s; }
    .lstep.active { color: var(--accent); font-weight: 600; }
    .lstep.done { color: var(--success); }

    /* Form */
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; }
    .fg-2 { grid-column: span 2; }
    @media (max-width: 640px) { .fg-2 { grid-column: span 1; } }
    .form-group { margin-bottom: 14px; }
    .form-label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-3); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
    .form-input {
      width: 100%; padding: 11px 14px; background: var(--bg-2); border: 1px solid var(--border-2);
      border-radius: var(--radius-sm); color: var(--text-1); font-size: 0.92rem; font-family: var(--font);
      outline: none; transition: all 0.2s;
    }
    .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    .form-textarea { resize: vertical; min-height: 60px; }
    .code-area { font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 0.82rem; line-height: 1.5; }
    select.form-input { cursor: pointer; }

    /* Content tabs */
    .label-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
    .content-tabs { display: flex; gap: 4px; }
    .ctab {
      padding: 5px 14px; border-radius: 6px; font-size: 0.78rem; font-weight: 600;
      background: transparent; color: var(--text-3); border: 1px solid transparent;
      cursor: pointer; font-family: var(--font); transition: all 0.2s;
    }
    .ctab.active { background: var(--accent); color: white; border-color: var(--accent); }

    /* ── Visual Editor ── */
    .ve-toolbar {
      display: flex; flex-wrap: wrap; gap: 4px; padding: 8px 10px;
      background: var(--bg-3); border: 1px solid var(--border-2);
      border-radius: var(--radius-sm) var(--radius-sm) 0 0;
      border-bottom: none; align-items: center;
    }
    .ve-toolbar .ve-sep {
      width: 1px; height: 24px; background: var(--border-2); margin: 0 4px;
    }
    .ve-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 32px; height: 32px; border-radius: 6px; border: none;
      background: transparent; color: var(--text-3); cursor: pointer;
      font-family: var(--font); font-size: 0.82rem; font-weight: 700;
      transition: all 0.15s; position: relative;
    }
    .ve-btn:hover { background: var(--surface-2); color: var(--text-1); }
    .ve-btn.active { background: var(--accent); color: white; }
    .ve-btn svg { width: 16px; height: 16px; }
    .ve-btn-wide { width: auto; padding: 0 10px; font-size: 0.75rem; font-weight: 600; gap: 4px; }
    .ve-btn[title]::after {
      content: attr(title); position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
      background: #0f172a; color: #f1f5f9; padding: 4px 8px; border-radius: 4px;
      font-size: 0.68rem; font-weight: 500; white-space: nowrap;
      opacity: 0; pointer-events: none; transition: opacity 0.2s; z-index: 10;
    }
    .ve-btn:hover[title]::after { opacity: 1; }

    .ve-editor {
      min-height: 400px; max-height: 600px; overflow-y: auto; padding: 20px;
      background: #ffffff; border: 1px solid var(--border-2);
      border-radius: 0 0 var(--radius-sm) var(--radius-sm);
      color: #1e293b; font-size: 16px; line-height: 1.8;
      font-family: system-ui, -apple-system, sans-serif;
      outline: none; cursor: text;
    }
    .ve-editor:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    .ve-editor h2 { margin: 24px 0 12px; padding: 12px 16px; background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 0 8px 8px 0; color: #1e3a8a; font-size: 22px; font-weight: 800; }
    .ve-editor h3 { margin: 20px 0 10px; padding: 10px 14px; background: #f1f5f9; border-left: 4px solid #475569; border-radius: 0 8px 8px 0; color: #0f172a; font-size: 19px; font-weight: 700; }
    .ve-editor h4 { margin: 16px 0 8px; padding: 8px 12px; background: #f8fafc; border-left: 3px solid #64748b; border-radius: 0 6px 6px 0; color: #1e293b; font-size: 17px; font-weight: 700; }
    .ve-editor p { margin: 10px 0; }
    .ve-editor a { color: #2563eb; text-decoration: underline; }
    .ve-editor ul, .ve-editor ol { padding-left: 24px; margin: 12px 0; }
    .ve-editor li { margin: 4px 0; }
    .ve-editor table { width: 100%; border-collapse: collapse; margin: 16px 0; }
    .ve-editor th, .ve-editor td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: left; }
    .ve-editor th { background: #e2e8f0; font-weight: 600; }
    .ve-editor blockquote { margin: 12px 0; padding: 12px 16px; border-left: 4px solid #f59e0b; background: #fffbeb; font-style: italic; }
    .ve-editor img {
      max-width: 100%; height: auto; border-radius: 8px; margin: 12px 0;
      display: block; cursor: pointer; transition: outline 0.2s;
    }
    .ve-editor img:hover { outline: 3px solid #ef4444; outline-offset: 2px; }
    .ve-editor img.ve-img-selected { outline: 3px solid #2563eb; outline-offset: 2px; }
    .ve-editor hr { border: none; border-top: 2px solid #e2e8f0; margin: 20px 0; }
    .ve-editor .ve-img-wrapper {
      position: relative; display: inline-block; max-width: 100%;
    }
    .ve-editor .ve-img-wrapper .ve-img-delete {
      position: absolute; top: 6px; right: 6px; width: 28px; height: 28px;
      background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%;
      cursor: pointer; display: none; align-items: center; justify-content: center;
      font-size: 16px; font-weight: bold; z-index: 5; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    .ve-editor .ve-img-wrapper:hover .ve-img-delete { display: flex; }

    /* Insert image modal */
    .ve-modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000;
      display: flex; align-items: center; justify-content: center;
      backdrop-filter: blur(4px); animation: fadeIn 0.2s ease;
    }
    .ve-modal {
      background: var(--bg-2); border: 1px solid var(--border-2); border-radius: var(--radius);
      padding: 24px; width: 90%; max-width: 480px; box-shadow: var(--shadow-lg);
    }
    .ve-modal h3 { color: var(--text-1); font-size: 1.1rem; margin-bottom: 16px; font-weight: 700; }
    .ve-modal .form-group { margin-bottom: 12px; }
    .ve-modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* Preview pane */
    .preview-pane { background: var(--bg-2); border: 1px solid var(--border-2); border-radius: var(--radius-sm); padding: 20px; max-height: 600px; overflow: auto; }
    .html-preview-area { color: var(--text-1); line-height: 1.7; }
    .html-preview-area img { max-width: 100%; height: auto; border-radius: 8px; margin: 12px 0; }
    .html-preview-area table { width: 100%; border-collapse: collapse; margin: 16px 0; }
    .html-preview-area th, .html-preview-area td { border: 1px solid var(--border-2); padding: 10px; text-align: left; }
    .html-preview-area th { background: var(--surface-2); font-weight: 600; }

    /* Images gallery */
    .images-gallery {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 12px; margin-top: 12px;
    }
    .img-card {
      position: relative; border-radius: 10px; overflow: hidden;
      border: 2px solid transparent; transition: all 0.2s; cursor: pointer;
      aspect-ratio: 16/10; background: var(--bg-2);
    }
    .img-card.selected { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    .img-card img {
      width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.3s;
    }
    .img-card:hover img { transform: scale(1.05); }
    .img-card .img-check {
      position: absolute; top: 8px; right: 8px; width: 24px; height: 24px;
      background: var(--accent); border-radius: 50%; display: none;
      align-items: center; justify-content: center; color: white; font-size: 14px; font-weight: bold;
    }
    .img-card.selected .img-check { display: flex; }
    .img-badge {
      position: absolute; bottom: 0; left: 0; right: 0; padding: 4px 8px;
      background: rgba(0,0,0,0.7); color: white; font-size: 0.68rem;
      text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .featured-img-preview {
      width: 100%; max-height: 300px; object-fit: cover; border-radius: 10px;
      margin-top: 10px; border: 2px solid var(--border-2);
    }

    /* Important links */
    .link-row { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }

    /* SEO section */
    .seo-section { margin-top: 16px; }
    .seo-summary { cursor: pointer; font-size: 0.92rem; font-weight: 600; color: var(--text-2); padding: 10px 0; }
    .char-limit { font-size: 0.72rem; font-weight: 400; color: var(--text-3); margin-left: 6px; }

    /* Toggle */
    .toggle-group { display: flex; align-items: center; }
    .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; }
    .toggle-input { display: none; }
    .toggle-track {
      width: 44px; height: 24px; background: var(--bg-3); border-radius: 12px;
      position: relative; transition: background 0.2s;
    }
    .toggle-thumb {
      width: 20px; height: 20px; background: var(--text-3); border-radius: 50%;
      position: absolute; top: 2px; left: 2px; transition: all 0.2s;
    }
    .toggle-input:checked ~ .toggle-track { background: var(--accent); }
    .toggle-input:checked ~ .toggle-track .toggle-thumb { left: 22px; background: white; }
    .toggle-text { font-size: 0.88rem; color: var(--text-2); }

    /* Form actions */
    .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; flex-wrap: wrap; }

    /* Result card */
    .result-success { text-align: center; padding: 24px; }
    .result-icon { font-size: 3rem; margin-bottom: 12px; }
    .result-title { font-size: 1.3rem; font-weight: 800; color: var(--success); }
    .result-subtitle { color: var(--text-3); margin: 6px 0 20px; }
    .result-meta { display: flex; flex-direction: column; gap: 4px; margin-bottom: 20px; font-size: 0.85rem; color: var(--text-3); }
    .result-meta span { color: var(--text-1); font-weight: 600; }
    .result-error { text-align: center; padding: 24px; }
    .err-title { font-size: 1.2rem; font-weight: 700; color: var(--danger); margin-bottom: 8px; }
    .err-msg { color: var(--text-3); font-size: 0.88rem; margin-bottom: 12px; }
    .err-raw { background: var(--bg-2); padding: 12px; border-radius: 8px; font-size: 0.78rem; color: var(--text-3); white-space: pre-wrap; word-break: break-all; margin-top: 8px; max-height: 200px; overflow: auto; }

    /* FAQs section */
    .faq-item { background: var(--bg-2); border: 1px solid var(--border-2); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 10px; }
    .faq-q { font-weight: 700; color: var(--text-1); font-size: 0.92rem; margin-bottom: 6px; }
    .faq-a { font-size: 0.85rem; color: var(--text-3); line-height: 1.5; }

    /* Responsive */
    @media (max-width: 640px) {
      .url-input-group { flex-direction: column; }
      .app-wrapper { padding: 0 14px 36px; }
      .card { padding: 18px; }
      .form-actions { flex-direction: column; }
      .form-actions .btn { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>
<!-- Animated background -->
<div class="bg-orbs">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>

<div class="app-wrapper">
  <!-- Header -->
  <header class="app-header">
    <div class="header-inner">
      <div class="logo">
        <div class="logo-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>
          </svg>
        </div>
        <div class="logo-text">
          <span class="logo-brand">JobOne</span>
          <span class="logo-tagline">Blog Poster</span>
        </div>
      </div>
      <div class="header-nav">
        <a href="index.php">📋 Job Poster</a>
        <a href="blog.php" class="active">📝 Blog Poster</a>
      </div>
    </div>
  </header>

  <main>
    <!-- Hero -->
    <section class="hero">
      <h1 class="hero-title">Scrape Any Blog. <span class="gradient-text">Publish Beautifully.</span></h1>
      <p class="hero-subtitle">Automatically extract blog content with images, tables & FAQs — then publish as a rich blog post to JobOne.in</p>
    </section>

    <!-- URL Input Card -->
    <div class="card" id="urlCard">
      <div class="card-header">
        <div class="card-icon">🔗</div>
        <div>
          <h2 class="card-title">Step 1 — Paste Blog URL</h2>
          <p class="card-desc">Paste any blog article link to scrape its content with images</p>
        </div>
      </div>
      <div class="url-input-group">
        <input type="url" id="urlInput" placeholder="https://karnatakahelp.in/article-name/" class="url-input" autocomplete="off"/>
        <button id="scrapeBtn" class="btn btn-primary" onclick="startBlogScrape()">
          <span class="btn-text">Scrape Blog</span>
          <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div id="loadingCard" class="card loading-card hidden">
      <div class="loading-inner">
        <div class="spinner"></div>
        <div id="loadingSteps">
          <div class="lstep active" data-step="1">🌐 Fetching blog page…</div>
          <div class="lstep" data-step="2">🖼️ Extracting images & content…</div>
          <div class="lstep" data-step="3">✨ Formatting & enhancing with AI…</div>
        </div>
      </div>
    </div>

    <!-- Preview & Edit Card -->
    <div id="previewCard" class="card hidden">
      <div class="card-header">
        <div class="card-icon">✏️</div>
        <div>
          <h2 class="card-title">Step 2 — Review & Edit Blog Post</h2>
          <p class="card-desc">Content extracted with images. Review before publishing.</p>
        </div>
        <button class="btn btn-ghost" onclick="resetBlogForm()">← Start over</button>
      </div>

      <form id="blogForm" class="post-form">
        <!-- Row 1: Title -->
        <div class="form-group">
          <label class="form-label" for="fTitle">Blog Title *</label>
          <input type="text" id="fTitle" name="title" class="form-input" placeholder="Blog post title…" required/>
        </div>

        <!-- Row 2: Category + State -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="fCategory">Category *</label>
            <select id="fCategory" name="category_id" class="form-input" required>
              <option value="">— Select Category —</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="fState">State (optional)</label>
            <select id="fState" name="state_id" class="form-input">
              <option value="">— All India —</option>
              <?php foreach ($states as $st): ?>
              <option value="<?= htmlspecialchars($st['id']) ?>"><?= htmlspecialchars($st['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Featured Image -->
        <div class="form-group" id="featuredImageGroup">
          <label class="form-label">Featured Image</label>
          <div id="featuredImagePreview"></div>
        </div>

        <!-- Images Gallery -->
        <div class="form-group" id="imagesGroup" style="display:none">
          <label class="form-label">Extracted Images <span id="imgCount" style="font-weight:400;"></span></label>
          <div class="images-gallery" id="imagesGallery"></div>
        </div>

        <!-- Short Description -->
        <div class="form-group">
          <label class="form-label" for="fShortDesc">Short Description *</label>
          <textarea id="fShortDesc" name="short_description" class="form-input form-textarea" rows="3" placeholder="Brief description of the blog post…" required></textarea>
        </div>

        <!-- Content Editor -->
        <div class="form-group">
          <div class="label-row">
            <label class="form-label">Content *</label>
            <div class="content-tabs">
              <button type="button" class="ctab active" id="tabVisual" onclick="switchTab('visual')">🎨 Visual</button>
              <button type="button" class="ctab" id="tabCode" onclick="switchTab('code')">💻 Code</button>
              <button type="button" class="ctab" id="tabPreview" onclick="switchTab('preview')">👁️ Preview</button>
            </div>
          </div>

          <!-- Visual Editor -->
          <div id="visualPane">
            <div class="ve-toolbar" id="veToolbar">
              <button type="button" class="ve-btn" onclick="veCmd('undo')" title="Undo">↶</button>
              <button type="button" class="ve-btn" onclick="veCmd('redo')" title="Redo">↷</button>
              <div class="ve-sep"></div>
              <button type="button" class="ve-btn" onclick="veCmd('bold')" title="Bold"><b>B</b></button>
              <button type="button" class="ve-btn" onclick="veCmd('italic')" title="Italic"><i>I</i></button>
              <button type="button" class="ve-btn" onclick="veCmd('underline')" title="Underline"><u>U</u></button>
              <button type="button" class="ve-btn" onclick="veCmd('strikeThrough')" title="Strikethrough"><s>S</s></button>
              <div class="ve-sep"></div>
              <button type="button" class="ve-btn ve-btn-wide" onclick="veHeading('H2')" title="Heading 2">H2</button>
              <button type="button" class="ve-btn ve-btn-wide" onclick="veHeading('H3')" title="Heading 3">H3</button>
              <button type="button" class="ve-btn ve-btn-wide" onclick="veHeading('H4')" title="Heading 4">H4</button>
              <button type="button" class="ve-btn ve-btn-wide" onclick="veHeading('P')" title="Paragraph">¶</button>
              <div class="ve-sep"></div>
              <button type="button" class="ve-btn" onclick="veCmd('insertUnorderedList')" title="Bullet List">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg>
              </button>
              <button type="button" class="ve-btn" onclick="veCmd('insertOrderedList')" title="Numbered List">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="2" y="8" fill="currentColor" font-size="7" font-weight="600" stroke="none">1</text><text x="2" y="14" fill="currentColor" font-size="7" font-weight="600" stroke="none">2</text><text x="2" y="20" fill="currentColor" font-size="7" font-weight="600" stroke="none">3</text></svg>
              </button>
              <div class="ve-sep"></div>
              <button type="button" class="ve-btn" onclick="veInsertLink()" title="Insert Link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
              </button>
              <button type="button" class="ve-btn" onclick="veInsertImageModal()" title="Insert Image">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              </button>
              <button type="button" class="ve-btn ve-btn-wide" onclick="document.getElementById('veEditor')?.focus(); showToast('Press Ctrl+V to paste image from clipboard, or drag & drop', 'info')" title="Paste Image (Ctrl+V)">📋</button>
              <button type="button" class="ve-btn" onclick="veCmd('insertHorizontalRule')" title="Horizontal Line">―</button>
              <div class="ve-sep"></div>
              <button type="button" class="ve-btn" onclick="veRemoveFormat()" title="Clear Formatting">🧹</button>
              <button type="button" class="ve-btn ve-btn-wide btn-danger" onclick="veDeleteSelected()" title="Delete Selected" style="color:var(--danger);">🗑️ Del</button>
            </div>
            <div class="ve-editor" id="veEditor" contenteditable="true"></div>
          </div>

          <!-- Code Editor -->
          <div id="codePane" class="hidden">
            <textarea id="fContent" name="content" class="form-input form-textarea code-area" rows="20" required></textarea>
          </div>

          <!-- Preview -->
          <div id="previewPane" class="preview-pane hidden">
            <div id="htmlPreview" class="html-preview-area"></div>
          </div>
        </div>

        <!-- FAQs -->
        <div class="form-group" id="faqsGroup" style="display:none">
          <label class="form-label">Extracted FAQs</label>
          <div id="faqsContainer"></div>
        </div>

        <!-- Important Links -->
        <div class="form-group">
          <label class="form-label">Important Links</label>
          <div id="linksContainer"></div>
          <button type="button" class="btn btn-outline btn-sm" onclick="addLink()">+ Add Link</button>
        </div>

        <!-- SEO Settings -->
        <details class="seo-section">
          <summary class="seo-summary">🔍 SEO Settings (optional)</summary>
          <div class="form-row" style="margin-top:1rem">
            <div class="form-group">
              <label class="form-label" for="fMetaTitle">Meta Title <span class="char-limit" id="metaTitleCount">0/60</span></label>
              <input type="text" id="fMetaTitle" name="meta_title" class="form-input" placeholder="SEO title" maxlength="60" oninput="updateCount('fMetaTitle','metaTitleCount',60)"/>
            </div>
            <div class="form-group">
              <label class="form-label" for="fMetaKw">Meta Keywords <span class="char-limit" id="metaKwCount">0/1000</span></label>
              <textarea id="fMetaKw" name="meta_keywords" class="form-input form-textarea" rows="2" placeholder="comma, separated, keywords" maxlength="1000" oninput="updateCount('fMetaKw','metaKwCount',1000)"></textarea>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="fMetaDesc">Meta Description <span class="char-limit" id="metaDescCount">0/160</span></label>
            <textarea id="fMetaDesc" name="meta_description" class="form-input form-textarea" rows="2" placeholder="SEO meta description…" maxlength="160" oninput="updateCount('fMetaDesc','metaDescCount',160)"></textarea>
          </div>
        </details>

        <!-- Featured toggle -->
        <div class="form-group toggle-group">
          <label class="toggle-label">
            <input type="checkbox" id="fFeatured" name="is_featured" class="toggle-input"/>
            <span class="toggle-track">
              <span class="toggle-thumb"></span>
            </span>
            <span class="toggle-text">⭐ Mark as Featured Post</span>
          </label>
        </div>

        <!-- Actions -->
        <div class="form-actions">
          <button type="button" class="btn btn-ghost" onclick="resetBlogForm()">Cancel</button>
          <button type="submit" id="submitBtn" class="btn btn-success btn-lg">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
            </svg>
            <span class="btn-text">Publish Blog to JobOne.in</span>
          </button>
        </div>
      </form>
    </div>

    <!-- Result card -->
    <div id="resultCard" class="card hidden">
      <div id="resultInner"></div>
    </div>
  </main>

  <footer style="text-align:center;padding:24px 0;color:var(--text-3);font-size:0.82rem;">
    <p>JobOne Blog Poster &mdash; Powered by <strong>jobone.in API</strong></p>
  </footer>
</div>

<script>
'use strict';

let linkIndex = 0;
let extractedImages = [];

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('urlInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') startBlogScrape();
  });
  document.getElementById('blogForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await submitBlogPost();
  });

  // Visual editor: paste & drop image support
  const veEditor = document.getElementById('veEditor');
  if (veEditor) {
    veEditor.addEventListener('paste', veHandlePaste);
    veEditor.addEventListener('drop', veHandleDrop);
    veEditor.addEventListener('dragover', e => { e.preventDefault(); e.stopPropagation(); });
  }
});

// ── Scrape flow ────────────────────────────────────────────────
async function startBlogScrape() {
  const urlInput = document.getElementById('urlInput');
  const url = urlInput.value.trim();

  if (!url) { shakeElement(urlInput); urlInput.focus(); return; }
  if (!isValidUrl(url)) { showToast('Please enter a valid URL.', 'error'); shakeElement(urlInput); return; }

  hide('urlCard'); hide('previewCard'); hide('resultCard');
  show('loadingCard');

  const steps = document.querySelectorAll('.lstep');
  let stepIdx = 0;
  const stepTimer = setInterval(() => {
    steps.forEach(s => s.classList.remove('active', 'done'));
    if (stepIdx > 0) { for (let i = 0; i < stepIdx; i++) steps[i]?.classList.add('done'); }
    steps[stepIdx]?.classList.add('active');
    stepIdx++;
    if (stepIdx >= steps.length) clearInterval(stepTimer);
  }, 1200);

  try {
    const res = await fetch('blog_scrape.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url })
    });

    clearInterval(stepTimer);
    steps.forEach(s => s.classList.add('done'));

    const data = await res.json();

    if (!data.success) {
      hide('loadingCard'); show('urlCard');
      showToast(data.message || 'Failed to scrape blog.', 'error');
      return;
    }

    await new Promise(r => setTimeout(r, 500));
    hide('loadingCard');
    populateBlogForm(data.data, url);
    show('previewCard');

  } catch (err) {
    clearInterval(stepTimer);
    hide('loadingCard'); show('urlCard');
    showToast('Network error: ' + err.message, 'error');
  }
}

// ── Populate form ──────────────────────────────────────────────
function populateBlogForm(d, sourceUrl) {
  setVal('fTitle', d.title || '');
  setVal('fShortDesc', d.short_description || '');
  setVal('fContent', d.content || '');
  setVal('fMetaTitle', (d.meta_title || d.title || '').slice(0, 60));
  setVal('fMetaKw', d.meta_keywords || '');
  setVal('fMetaDesc', (d.short_description_seo || d.short_description || '').slice(0, 160));

  updateCount('fMetaTitle', 'metaTitleCount', 60);
  updateCount('fMetaDesc', 'metaDescCount', 160);
  updateCount('fMetaKw', 'metaKwCount', 1000);

  // Auto-select category
  if (d.category_guess) autoSelect('fCategory', d.category_guess);
  if (d.state_guess) autoSelect('fState', d.state_guess);

  // Featured image
  const featuredDiv = document.getElementById('featuredImagePreview');
  if (d.featured_image) {
    featuredDiv.innerHTML = `<img src="${escHtml(d.featured_image)}" class="featured-img-preview" alt="Featured Image" onerror="this.style.display='none'" />`;
  } else {
    featuredDiv.innerHTML = '<p style="color:var(--text-3);font-size:0.85rem;">No featured image found</p>';
  }

  // Images gallery
  extractedImages = d.images || [];
  const gallery = document.getElementById('imagesGallery');
  const group = document.getElementById('imagesGroup');
  const imgCount = document.getElementById('imgCount');

  if (extractedImages.length > 0) {
    group.style.display = 'block';
    imgCount.textContent = `(${extractedImages.length} found)`;
    let html = '';
    extractedImages.forEach((img, i) => {
      html += `
        <div class="img-card" id="imgCard-${i}" onclick="toggleImage(${i})">
          <img src="${escHtml(img.src)}" alt="${escHtml(img.alt || '')}" onerror="this.parentElement.style.display='none'" />
          <div class="img-check">✓</div>
          <div class="img-badge">${escHtml(img.alt || 'Image ' + (i+1))}</div>
        </div>
      `;
    });
    gallery.innerHTML = html;
  } else {
    group.style.display = 'none';
  }

  // FAQs
  const faqsGroup = document.getElementById('faqsGroup');
  const faqsContainer = document.getElementById('faqsContainer');
  if (d.faqs && d.faqs.length > 0) {
    faqsGroup.style.display = 'block';
    let faqHtml = '';
    d.faqs.forEach(faq => {
      faqHtml += `
        <div class="faq-item">
          <div class="faq-q">❓ ${escHtml(faq.question)}</div>
          <div class="faq-a">${escHtml(faq.answer)}</div>
        </div>
      `;
    });
    faqsContainer.innerHTML = faqHtml;
  } else {
    faqsGroup.style.display = 'none';
  }

  // Important links
  const linksContainer = document.getElementById('linksContainer');
  linksContainer.innerHTML = '';
  linkIndex = 0;
  if (sourceUrl) addLink('Source Article', sourceUrl);
  if (d.important_links?.length) {
    d.important_links.forEach(l => addLink(l.title, l.url));
  }

  // Load content into visual editor
  veLoadContent(d.content || '');
  switchTab('visual');
}

function toggleImage(idx) {
  const card = document.getElementById(`imgCard-${idx}`);
  card.classList.toggle('selected');
}

function autoSelect(selectId, guess) {
  const select = document.getElementById(selectId);
  if (!select) return;
  const g = guess.toLowerCase();
  for (const opt of select.options) {
    if (opt.text.toLowerCase().includes(g) || g.includes(opt.text.toLowerCase())) {
      select.value = opt.value;
      return;
    }
  }
}

// ── Content tab switcher ────────────────────────────────────────
let currentTab = 'visual';

function switchTab(tab) {
  const tabs = ['tabVisual', 'tabCode', 'tabPreview'];
  tabs.forEach(t => document.getElementById(t)?.classList.remove('active'));
  hide('visualPane'); hide('codePane'); hide('previewPane');

  // Sync content between editors when switching
  if (currentTab === 'visual' && tab !== 'visual') {
    // Save visual → code textarea
    syncVisualToCode();
  } else if (currentTab === 'code' && tab !== 'code') {
    // Save code textarea → visual
    syncCodeToVisual();
  }

  if (tab === 'visual') {
    show('visualPane');
    document.getElementById('tabVisual').classList.add('active');
  } else if (tab === 'code') {
    show('codePane');
    document.getElementById('tabCode').classList.add('active');
  } else {
    // Preview — get latest HTML
    const html = currentTab === 'visual' ? getEditorHtml() : getVal('fContent');
    document.getElementById('htmlPreview').innerHTML = html;
    show('previewPane');
    document.getElementById('tabPreview').classList.add('active');
  }

  currentTab = tab;
}

function syncVisualToCode() {
  const html = getEditorHtml();
  setVal('fContent', html);
}

function syncCodeToVisual() {
  const html = getVal('fContent');
  veLoadContent(html);
}

function getEditorHtml() {
  const editor = document.getElementById('veEditor');
  if (!editor) return '';
  // Clone and clean up editor-specific elements
  const clone = editor.cloneNode(true);
  // Remove delete buttons from img wrappers
  clone.querySelectorAll('.ve-img-delete').forEach(btn => btn.remove());
  // Unwrap img wrappers to just img tags
  clone.querySelectorAll('.ve-img-wrapper').forEach(wrapper => {
    const img = wrapper.querySelector('img');
    if (img) {
      img.classList.remove('ve-img-selected');
      wrapper.replaceWith(img);
    } else {
      wrapper.remove();
    }
  });
  return clone.innerHTML;
}

// ── Visual Editor Functions ─────────────────────────────────────
function veLoadContent(html) {
  const editor = document.getElementById('veEditor');
  if (!editor) return;
  // Strip the <style> and wrapper div from blog styling so we just get raw HTML content
  let cleanHtml = html;
  // Remove <style>...</style>
  cleanHtml = cleanHtml.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
  // Remove jobone wrapper divs but keep inner content
  cleanHtml = cleanHtml.replace(/<div class="jobone-blog-ui">/gi, '');
  cleanHtml = cleanHtml.replace(/<div class="jobone-premium-ui">/gi, '');
  // Remove trailing </div> that closed the wrapper
  cleanHtml = cleanHtml.replace(/<\/div>\s*$/i, '');
  // Remove table wrapper divs
  cleanHtml = cleanHtml.replace(/<div class="jobone-blog-table-wrap">/gi, '');
  cleanHtml = cleanHtml.replace(/<div class="jobone-table-wrapper">/gi, '');

  editor.innerHTML = cleanHtml;
  veWrapImages();
}

function veWrapImages() {
  const editor = document.getElementById('veEditor');
  if (!editor) return;
  editor.querySelectorAll('img').forEach(img => {
    if (img.parentElement?.classList?.contains('ve-img-wrapper')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 've-img-wrapper';
    wrapper.contentEditable = 'false';
    const deleteBtn = document.createElement('button');
    deleteBtn.className = 've-img-delete';
    deleteBtn.type = 'button';
    deleteBtn.innerHTML = '✕';
    deleteBtn.title = 'Delete this image';
    deleteBtn.onclick = function(e) {
      e.preventDefault();
      e.stopPropagation();
      if (confirm('Delete this image?')) {
        wrapper.remove();
        showToast('Image deleted', 'success');
      }
    };
    img.parentNode.insertBefore(wrapper, img);
    wrapper.appendChild(deleteBtn);
    wrapper.appendChild(img);
  });
}

function veCmd(command, value = null) {
  document.getElementById('veEditor')?.focus();
  document.execCommand(command, false, value);
}

function veHeading(tag) {
  document.getElementById('veEditor')?.focus();
  document.execCommand('formatBlock', false, `<${tag}>`);
}

function veRemoveFormat() {
  document.getElementById('veEditor')?.focus();
  document.execCommand('removeFormat', false, null);
  document.execCommand('formatBlock', false, '<p>');
}

function veDeleteSelected() {
  const editor = document.getElementById('veEditor');
  if (!editor) return;
  // Delete selected text/element
  const selection = window.getSelection();
  if (selection && !selection.isCollapsed) {
    document.execCommand('delete', false, null);
    showToast('Selection deleted', 'success');
    return;
  }
  // Try to delete the element the cursor is inside
  const anchor = selection?.anchorNode;
  if (anchor) {
    let target = anchor.nodeType === 3 ? anchor.parentElement : anchor;
    while (target && target !== editor) {
      if (['IMG', 'TABLE', 'FIGURE', 'BLOCKQUOTE', 'HR'].includes(target.tagName)) {
        if (confirm(`Delete this ${target.tagName.toLowerCase()}?`)) {
          target.remove();
          showToast('Element deleted', 'success');
        }
        return;
      }
      if (['P', 'H2', 'H3', 'H4', 'H5', 'H6', 'LI', 'DIV'].includes(target.tagName) && target.parentElement === editor) {
        if (confirm('Delete this block?')) {
          target.remove();
          showToast('Block deleted', 'success');
        }
        return;
      }
      target = target.parentElement;
    }
  }
  showToast('Click on content or select text to delete', 'info');
}

function veInsertLink() {
  const selection = window.getSelection();
  const text = selection?.toString() || '';
  const url = prompt('Enter URL:', 'https://');
  if (!url) return;
  if (text) {
    document.execCommand('createLink', false, url);
  } else {
    const linkText = prompt('Link text:', 'Click here');
    if (!linkText) return;
    const editor = document.getElementById('veEditor');
    editor.focus();
    document.execCommand('insertHTML', false, `<a href="${escHtml(url)}" target="_blank">${escHtml(linkText)}</a>`);
  }
}

function veInsertImageModal() {
  // Create modal
  const overlay = document.createElement('div');
  overlay.className = 've-modal-overlay';
  overlay.id = 'veImageModal';
  overlay.innerHTML = `
    <div class="ve-modal">
      <h3>🖼️ Insert Image</h3>
      <div class="form-group">
        <label class="form-label" for="veImgUrl">Image URL *</label>
        <input type="url" id="veImgUrl" class="form-input" placeholder="https://example.com/image.jpg" autofocus />
      </div>
      <div class="form-group">
        <label class="form-label" for="veImgAlt">Alt Text (optional)</label>
        <input type="text" id="veImgAlt" class="form-input" placeholder="Describe the image…" />
      </div>
      <div class="ve-modal-actions">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('veImageModal')?.remove()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="veDoInsertImage()">Insert Image</button>
      </div>
    </div>
  `;
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
  document.body.appendChild(overlay);
  setTimeout(() => document.getElementById('veImgUrl')?.focus(), 100);
}

function veDoInsertImage() {
  const url = document.getElementById('veImgUrl')?.value.trim();
  const alt = document.getElementById('veImgAlt')?.value.trim() || '';
  if (!url) { showToast('Please enter an image URL', 'error'); return; }
  document.getElementById('veImageModal')?.remove();

  const editor = document.getElementById('veEditor');
  editor.focus();
  const imgHtml = `<img src="${escHtml(url)}" alt="${escHtml(alt)}" style="max-width:100%;height:auto;border-radius:8px;margin:16px 0;display:block;" />`;
  document.execCommand('insertHTML', false, imgHtml);

  // Wrap the newly inserted image
  setTimeout(() => veWrapImages(), 100);
  showToast('Image inserted', 'success');
}

// ── Clipboard paste & drag-drop image support ──────────────────
function veHandlePaste(e) {
  const items = e.clipboardData?.items;
  if (!items) return;
  for (const item of items) {
    if (item.type.startsWith('image/')) {
      e.preventDefault();
      const file = item.getAsFile();
      if (file) veInsertImageFile(file);
      return;
    }
  }
  const html = e.clipboardData?.getData('text/html');
  if (html && html.includes('<img')) {
    e.preventDefault();
    document.execCommand('insertHTML', false, html);
    setTimeout(() => veWrapImages(), 150);
    return;
  }
}

function veHandleDrop(e) {
  e.preventDefault();
  e.stopPropagation();
  const files = e.dataTransfer?.files;
  if (!files || files.length === 0) return;
  for (const file of files) {
    if (file.type.startsWith('image/')) {
      veInsertImageFile(file);
    }
  }
}

function veInsertImageFile(file) {
  const reader = new FileReader();
  reader.onload = function(e) {
    const dataUrl = e.target.result;
    const editor = document.getElementById('veEditor');
    if (!editor) return;
    editor.focus();
    const imgHtml = `<img src="${dataUrl}" alt="Pasted image" style="max-width:100%;height:auto;border-radius:8px;margin:16px 0;display:block;" />`;
    document.execCommand('insertHTML', false, imgHtml);
    setTimeout(() => veWrapImages(), 150);
    showToast('Image pasted ✓', 'success');
  };
  reader.readAsDataURL(file);
}

// ── Links ───────────────────────────────────────────────────────
function addLink(title = '', url = '') {
  const container = document.getElementById('linksContainer');
  const idx = linkIndex++;
  const row = document.createElement('div');
  row.className = 'link-row';
  row.id = `linkRow-${idx}`;
  row.innerHTML = `
    <input type="text" class="form-input" placeholder="Link title…" name="link_title_${idx}" value="${escHtml(title)}" style="flex:1.2"/>
    <input type="url" class="form-input" placeholder="https://…" name="link_url_${idx}" value="${escHtml(url)}" style="flex:2"/>
    <button type="button" class="btn btn-danger btn-sm" onclick="removeLink(${idx})" title="Remove" style="padding:0.5rem 0.65rem">✕</button>
  `;
  container.appendChild(row);
}

function removeLink(idx) { document.getElementById(`linkRow-${idx}`)?.remove(); }

function collectLinks() {
  const links = [];
  document.querySelectorAll('.link-row').forEach(row => {
    const t = row.querySelector('input[name^="link_title"]')?.value.trim();
    const u = row.querySelector('input[name^="link_url"]')?.value.trim();
    if (t && u) links.push({ title: t, url: u });
  });
  return links;
}

// ── Submit ──────────────────────────────────────────────────────
async function submitBlogPost() {
  const btn = document.getElementById('submitBtn');
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = `<div class="spinner" style="width:18px;height:18px;border-width:2px"></div> Publishing…`;

  const payload = {
    title:             getVal('fTitle'),
    type:              'blog',
    short_description: getVal('fShortDesc'),
    content:           currentTab === 'visual' ? getEditorHtml() : getVal('fContent'),
    category_id:       getVal('fCategory'),
    state_id:          getVal('fState') || null,
    is_featured:       document.getElementById('fFeatured').checked,
    meta_title:        getVal('fMetaTitle') || null,
    meta_description:  getVal('fMetaDesc') || null,
    meta_keywords:     getVal('fMetaKw') || null,
    important_links:   collectLinks(),
  };

  Object.keys(payload).forEach(k => {
    if (payload[k] === null || payload[k] === '') delete payload[k];
  });

  try {
    const res = await fetch('post.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    hide('previewCard'); show('resultCard');
    const inner = document.getElementById('resultInner');

    if (data.success) {
      const p = data.data;
      inner.innerHTML = `
        <div class="result-success">
          <div class="result-icon">🎉</div>
          <div class="result-title">Blog Published Successfully!</div>
          <div class="result-subtitle">Your blog post is now live on JobOne.in</div>
          <div class="result-meta">
            ${p?.id ? `<div>Post ID: <span>#${p.id}</span></div>` : ''}
            ${p?.slug ? `<div>Slug: <span>${p.slug}</span></div>` : ''}
            <div>Title: <span>${escHtml(payload.title)}</span></div>
          </div>
          <div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center">
            <button class="btn btn-success" onclick="resetBlogForm()">📝 Post Another Blog</button>
            ${p?.id ? `<a class="btn btn-outline" href="https://jobone.in/post/${p.slug || p.id}" target="_blank">↗ View Post</a>` : ''}
          </div>
        </div>
      `;
    } else {
      const errs = data.errors ? Object.entries(data.errors).map(([k, v]) => `• ${k}: ${v}`).join('\n') : '';
      inner.innerHTML = `
        <div class="result-error">
          <div class="err-title">❌ Publishing Failed</div>
          <div class="err-msg">${escHtml(data.message || 'Unknown error')}</div>
          ${errs ? `<div class="err-raw">${escHtml(errs)}</div>` : ''}
          ${data.raw ? `<details style="margin-top:0.5rem"><summary style="cursor:pointer;font-size:0.8rem;color:var(--text-3)">Raw API Response</summary><div class="err-raw">${escHtml(data.raw)}</div></details>` : ''}
          <button class="btn btn-ghost" onclick="hide('resultCard');show('previewCard')">← Back to Edit</button>
        </div>
      `;
    }
  } catch (err) {
    hide('previewCard'); show('resultCard');
    document.getElementById('resultInner').innerHTML = `
      <div class="result-error">
        <div class="err-title">❌ Network Error</div>
        <div class="err-msg">${escHtml(err.message)}</div>
        <button class="btn btn-ghost" onclick="hide('resultCard');show('previewCard')">← Back to Edit</button>
      </div>
    `;
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
  }
}

// ── Reset ────────────────────────────────────────────────────────
function resetBlogForm() {
  document.getElementById('blogForm').reset();
  document.getElementById('linksContainer').innerHTML = '';
  document.getElementById('urlInput').value = '';
  document.getElementById('imagesGallery').innerHTML = '';
  document.getElementById('featuredImagePreview').innerHTML = '';
  document.getElementById('faqsContainer').innerHTML = '';
  document.getElementById('imagesGroup').style.display = 'none';
  document.getElementById('faqsGroup').style.display = 'none';
  const editor = document.getElementById('veEditor');
  if (editor) editor.innerHTML = '';
  extractedImages = [];
  linkIndex = 0;
  currentTab = 'visual';

  hide('previewCard'); hide('resultCard'); hide('loadingCard');
  show('urlCard');
  switchTab('visual');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Helpers ─────────────────────────────────────────────────────
function show(id) { document.getElementById(id)?.classList.remove('hidden'); }
function hide(id) { document.getElementById(id)?.classList.add('hidden'); }
function getVal(id) { return document.getElementById(id)?.value.trim() || ''; }
function setVal(id, val) { const el = document.getElementById(id); if (el) el.value = val ?? ''; }
function updateCount(inputId, counterId, max) {
  const el = document.getElementById(inputId);
  const counter = document.getElementById(counterId);
  if (!el || !counter) return;
  const len = el.value.length;
  counter.textContent = `${len}/${max}`;
  counter.style.color = len > max * 0.9 ? (len >= max ? 'var(--danger)' : 'var(--warn)') : 'var(--text-3)';
}
function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function isValidUrl(str) {
  try { return ['http:', 'https:'].includes(new URL(str).protocol); } catch { return false; }
}
function shakeElement(el) {
  el.style.animation = 'none'; el.offsetHeight;
  el.style.animation = 'shake 0.4s ease';
  setTimeout(() => { el.style.animation = ''; }, 400);
}
function showToast(message, type = 'info') {
  document.querySelectorAll('.toast').forEach(t => t.remove());
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.style.cssText = `
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
    background: ${type === 'error' ? 'rgba(239,68,68,0.95)' : type === 'success' ? 'rgba(16,185,129,0.95)' : 'rgba(139,92,246,0.95)'};
    color: white; padding: 0.85rem 1.25rem; border-radius: 10px;
    font-size: 0.88rem; font-weight: 500; font-family: var(--font);
    box-shadow: 0 8px 32px rgba(0,0,0,0.4); backdrop-filter: blur(10px);
    animation: slideInToast 0.3s ease; max-width: 340px; line-height: 1.4;
  `;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
}

// Inject animations
const animStyles = document.createElement('style');
animStyles.textContent = `
  @keyframes slideInToast { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
  @keyframes shake { 0%,100%{transform:translateX(0);} 20%{transform:translateX(-8px);} 40%{transform:translateX(8px);} 60%{transform:translateX(-5px);} 80%{transform:translateX(5px);} }
`;
document.head.appendChild(animStyles);
</script>
</body>
</html>
