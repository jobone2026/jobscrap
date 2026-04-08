<?php
// Load config
// API limits: meta_title ≤ 60, meta_description ≤ 160
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
  <title>JobOne Auto-Poster — Smart Content Publisher</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/style.css"/>
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
            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
            <path d="M2 17l10 5 10-5"/>
            <path d="M2 12l10 5 10-5"/>
          </svg>
        </div>
        <div class="logo-text">
          <span class="logo-brand">JobOne</span>
          <span class="logo-tagline">Auto-Poster</span>
        </div>
      </div>
      <div class="header-actions">
        <span class="api-status" id="apiStatus">
          <span class="status-dot"></span>
          <span class="status-text">Checking API…</span>
        </span>
      </div>
    </div>
  </header>

  <main class="main-content">
    <!-- Hero -->
    <section class="hero">
      <h1 class="hero-title">Paste a Link. <span class="gradient-text">Publish Instantly.</span></h1>
      <p class="hero-subtitle">Automatically extract job posts, admit cards, results & more — then publish to JobOne.in with one click.</p>
    </section>

    <!-- URL Input Card -->
    <div class="card url-card" id="urlCard">
      <div class="card-header">
        <div class="card-icon">🔗</div>
        <div>
          <h2 class="card-title">Step 1 — Paste URL</h2>
          <p class="card-desc">Paste any government job or exam notification link below</p>
        </div>
      </div>
      <div class="url-input-group">
        <input type="url" id="urlInput" placeholder="https://sarkariresult.com/..." class="url-input" autocomplete="off"/>
        <button id="scrapeBtn" class="btn btn-primary" onclick="startScrape()">
          <span class="btn-text">Extract Content</span>
          <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </button>
      </div>

      <!-- Quick type pills -->
      <div class="type-hint">
        <span class="hint-label">⚡ Quick type:</span>
        <div class="type-pills">
          <button class="type-pill" onclick="setType('job')">💼 Job</button>
          <button class="type-pill" onclick="setType('admit_card')">🎫 Admit Card</button>
          <button class="type-pill" onclick="setType('result')">📊 Result</button>
          <button class="type-pill" onclick="setType('answer_key')">🗝️ Answer Key</button>
          <button class="type-pill" onclick="setType('syllabus')">📚 Syllabus</button>
        </div>
      </div>
    </div>

    <!-- Loading state -->
    <div id="loadingCard" class="card loading-card hidden">
      <div class="loading-inner">
        <div class="spinner"></div>
        <div class="loading-steps" id="loadingSteps">
          <div class="lstep active" data-step="1">🌐 Fetching page content…</div>
          <div class="lstep" data-step="2">🧠 Parsing & extracting data…</div>
          <div class="lstep" data-step="3">✨ Generating post content…</div>
        </div>
      </div>
    </div>

    <!-- Preview & Edit Card -->
    <div id="previewCard" class="card preview-card hidden">
      <div class="card-header">
        <div class="card-icon">✏️</div>
        <div>
          <h2 class="card-title">Step 2 — Review & Edit</h2>
          <p class="card-desc">Extracted content is ready. Tweak anything before publishing.</p>
        </div>
        <button class="btn btn-ghost" onclick="resetForm()">← Start over</button>
      </div>

      <form id="postForm" class="post-form">
        <!-- Row 1: Title + Type -->
        <div class="form-row">
          <div class="form-group fg-2">
            <label class="form-label" for="fTitle">Post Title *</label>
            <input type="text" id="fTitle" name="title" class="form-input" placeholder="e.g. UPSC Civil Services 2024 Notification" required/>
          </div>
          <div class="form-group fg-1">
            <label class="form-label" for="fType">Post Type *</label>
            <select id="fType" name="type" class="form-input" required>
              <option value="job">💼 Job</option>
              <option value="admit_card">🎫 Admit Card</option>
              <option value="result">📊 Result</option>
              <option value="answer_key">🗝️ Answer Key</option>
              <option value="syllabus">📚 Syllabus</option>
              <option value="blog">📝 Blog</option>
            </select>
          </div>
        </div>

        <!-- Row 2: Category + State -->
        <div class="form-row">
          <div class="form-group fg-1">
            <label class="form-label" for="fCategory">Category *</label>
            <select id="fCategory" name="category_id" class="form-input" required>
              <option value="">— Select Category —</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group fg-1">
            <label class="form-label" for="fState">State (optional)</label>
            <select id="fState" name="state_id" class="form-input">
              <option value="">— All India —</option>
              <?php foreach ($states as $st): ?>
              <option value="<?= htmlspecialchars($st['id']) ?>"><?= htmlspecialchars($st['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Row 3: Dates + Total Posts -->
        <div class="form-row">
          <div class="form-group fg-1">
            <label class="form-label" for="fLastDate">Last Date</label>
            <input type="date" id="fLastDate" name="last_date" class="form-input"/>
          </div>
          <div class="form-group fg-1">
            <label class="form-label" for="fNotifDate">Notification Date</label>
            <input type="date" id="fNotifDate" name="notification_date" class="form-input"/>
          </div>
          <div class="form-group fg-1">
            <label class="form-label" for="fTotalPosts">Total Vacancies</label>
            <input type="number" id="fTotalPosts" name="total_posts" class="form-input" placeholder="e.g. 1000" min="0"/>
          </div>
        </div>

        <!-- Short Description -->
        <div class="form-group">
          <label class="form-label" for="fShortDesc">Short Description *</label>
          <textarea id="fShortDesc" name="short_description" class="form-input form-textarea" rows="3" placeholder="Brief one-liner description…" required></textarea>
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
              <button type="button" class="ve-btn ve-btn-wide" onclick="document.getElementById('veEditor')?.focus(); showToast('Press Ctrl+V to paste image from clipboard', 'info')" title="Paste Image (Ctrl+V)">📋</button>
              <button type="button" class="ve-btn" onclick="veCmd('insertHorizontalRule')" title="Horizontal Line">―</button>
              <div class="ve-sep"></div>
              <button type="button" class="ve-btn" onclick="veRemoveFormat()" title="Clear Formatting">🧹</button>
              <button type="button" class="ve-btn ve-btn-wide btn-danger" onclick="veDeleteSelected()" title="Delete Selected" style="color:var(--danger);">🗑️ Del</button>
            </div>
            <div class="ve-editor" id="veEditor" contenteditable="true"></div>
          </div>

          <!-- Code Editor -->
          <div id="codePane" class="hidden">
            <textarea id="fContent" name="content" class="form-input form-textarea code-area" rows="18" required></textarea>
          </div>

          <!-- Preview -->
          <div id="previewPane" class="preview-pane hidden">
            <div id="htmlPreview" class="html-preview-area"></div>
          </div>
        </div>

        <!-- Important Links -->
        <div class="form-group">
          <label class="form-label">Important Links</label>
          <div id="linksContainer"></div>
          <button type="button" class="btn btn-outline btn-sm" onclick="addLink()">+ Add Link</button>
        </div>

        <!-- SEO -->
        <details class="seo-section">
          <summary class="seo-summary">🔍 SEO Settings (optional)</summary>
          <div class="form-row" style="margin-top:1rem">
            <div class="form-group fg-1">
              <label class="form-label" for="fMetaTitle">Meta Title <span class="char-limit" id="metaTitleCount">0/60</span></label>
              <input type="text" id="fMetaTitle" name="meta_title" class="form-input" placeholder="SEO title" maxlength="60" oninput="updateCount('fMetaTitle','metaTitleCount',60)"/>
            </div>
            <div class="form-group fg-1">
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
          <button type="button" class="btn btn-ghost" onclick="resetForm()">Cancel</button>
          <button type="submit" id="submitBtn" class="btn btn-success btn-lg">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
            </svg>
            <span class="btn-text">Publish to JobOne.in</span>
          </button>
        </div>
      </form>
    </div>

    <!-- Result card -->
    <div id="resultCard" class="card result-card hidden">
      <div id="resultInner"></div>
    </div>

    <!-- Recent Posts -->
    <div class="card recent-card">
      <div class="card-header">
        <div class="card-icon">📋</div>
        <div>
          <h2 class="card-title">Recent Posts</h2>
          <p class="card-desc">Last posts published via this tool</p>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="loadRecentPosts()">↻ Refresh</button>
      </div>
      <div id="recentPosts">
        <div class="empty-state">Loading recent posts…</div>
      </div>
    </div>
  </main>

  <footer class="app-footer">
    <p>JobOne Auto-Poster &mdash; Powered by <strong>jobone.in API</strong></p>
  </footer>
</div>

<!-- Hidden form to track forced type -->
<input type="hidden" id="forcedType" value=""/>

<script src="assets/app.js"></script>
<script>
// Inject PHP data into JS
const CATEGORIES = <?= json_encode($categories) ?>;
const STATES = <?= json_encode($states) ?>;
</script>
</body>
</html>
