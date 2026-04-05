/* ═══════════════════════════════════════════════════════════════
   JobOne Auto-Poster — Frontend Logic
   ═══════════════════════════════════════════════════════════════ */

'use strict';

// ── State ──────────────────────────────────────────────────────
let currentType = '';
let postsPage = 1;
let linkIndex = 0;

// ── Init ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  checkApiStatus();
  loadRecentPosts();
  initForm();

  // Allow pressing Enter in URL field
  document.getElementById('urlInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') startScrape();
  });
});

// ── API Status ──────────────────────────────────────────────────
async function checkApiStatus() {
  const badge = document.getElementById('apiStatus');
  try {
    const res = await fetch('api_proxy.php?action=status');
    const data = await res.json();
    if (data.success) {
      badge.classList.add('online');
      badge.querySelector('.status-text').textContent = 'API Connected';
    } else {
      badge.classList.add('offline');
      badge.querySelector('.status-text').textContent = 'API Offline';
    }
  } catch {
    badge.classList.add('offline');
    badge.querySelector('.status-text').textContent = 'API Error';
  }
}

// ── Type pill selection ─────────────────────────────────────────
function setType(type) {
  currentType = type;
  document.getElementById('forcedType').value = type;
  document.querySelectorAll('.type-pill').forEach(p => {
    p.classList.toggle('active', p.textContent.toLowerCase().includes(type.replace('_', ' ')));
  });
  // Also update select if preview is shown
  const sel = document.getElementById('fType');
  if (sel) sel.value = type;
}

// ── Main scrape flow ────────────────────────────────────────────
async function startScrape() {
  const urlInput = document.getElementById('urlInput');
  const url = urlInput.value.trim();

  if (!url) {
    shakeElement(urlInput);
    urlInput.focus();
    return;
  }
  if (!isValidUrl(url)) {
    showToast('Please enter a valid URL.', 'error');
    shakeElement(urlInput);
    return;
  }

  // Show loading
  hide('urlCard');
  hide('previewCard');
  hide('resultCard');
  show('loadingCard');

  const steps = document.querySelectorAll('.lstep');
  let stepIdx = 0;
  const stepTimer = setInterval(() => {
    steps.forEach(s => s.classList.remove('active', 'done'));
    if (stepIdx > 0) {
      for (let i = 0; i < stepIdx; i++) steps[i]?.classList.add('done');
    }
    steps[stepIdx]?.classList.add('active');
    stepIdx++;
    if (stepIdx >= steps.length) clearInterval(stepTimer);
  }, 900);

  try {
    const res = await fetch('scrape.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        url,
        forced_type: document.getElementById('forcedType').value || ''
      })
    });

    clearInterval(stepTimer);
    steps.forEach(s => s.classList.add('done'));

    const data = await res.json();

    if (!data.success) {
      hide('loadingCard');
      show('urlCard');
      showToast(data.message || 'Failed to extract content.', 'error');
      return;
    }

    await new Promise(r => setTimeout(r, 400)); // Brief pause to show all done
    hide('loadingCard');
    populateForm(data.data, url);
    show('previewCard');

  } catch (err) {
    clearInterval(stepTimer);
    hide('loadingCard');
    show('urlCard');
    showToast('Network error: ' + err.message, 'error');
  }
}

// ── Populate form with scraped data ────────────────────────────
function populateForm(d, sourceUrl) {
  // Basic fields
  setVal('fTitle', d.title || '');
  setVal('fType', d.type || 'job');
  setVal('fShortDesc', d.short_description || '');
  setVal('fContent', d.content || '');
  setVal('fLastDate', d.last_date || '');
  setVal('fNotifDate', d.notification_date || '');
  setVal('fTotalPosts', d.total_posts || '');
  setVal('fMetaTitle', (d.meta_title || d.title || '').slice(0, 60));
  setVal('fMetaKw', d.meta_keywords || '');
  setVal('fMetaDesc', (d.short_description_seo || d.short_description || '').slice(0, 160));

  // Update SEO character counters
  updateCount('fMetaTitle', 'metaTitleCount', 60);
  updateCount('fMetaDesc', 'metaDescCount', 160);
  updateCount('fMetaKw', 'metaKwCount', 1000);

  // Auto-select category based on guess
  if (d.category_guess) {
    autoSelectCategory(d.category_guess);
  }

  // Auto-select state based on guess
  if (d.state_guess) {
    autoSelectState(d.state_guess);
  }

  // Important links
  const linksContainer = document.getElementById('linksContainer');
  linksContainer.innerHTML = '';
  linkIndex = 0;

  // Add source URL as first link
  if (sourceUrl) {
    addLink('Source Article', sourceUrl);
  }
  if (d.important_links?.length) {
    d.important_links.forEach(l => addLink(l.title, l.url));
  }

  // Switch to edit tab
  switchTab('edit');
}

function autoSelectCategory(guess) {
  const select = document.getElementById('fCategory');
  if (!select) return;
  const g = guess.toLowerCase();
  for (const opt of select.options) {
    if (opt.text.toLowerCase().includes(g) || g.includes(opt.text.toLowerCase())) {
      select.value = opt.value;
      return;
    }
  }
}

function autoSelectState(guess) {
  const select = document.getElementById('fState');
  if (!select) return;
  const g = guess.toLowerCase();
  for (const opt of select.options) {
    if (opt.text.toLowerCase() === g || opt.text.toLowerCase().includes(g)) {
      select.value = opt.value;
      return;
    }
  }
}

// ── Content tab switcher ────────────────────────────────────────
function switchTab(tab) {
  const editPane = document.getElementById('editPane');
  const previewPane = document.getElementById('previewPane');
  const tabEdit = document.getElementById('tabEdit');
  const tabPreview = document.getElementById('tabPreview');

  if (tab === 'edit') {
    show('editPane');
    hide('previewPane');
    tabEdit.classList.add('active');
    tabPreview.classList.remove('active');
  } else {
    const html = document.getElementById('fContent').value;
    document.getElementById('htmlPreview').innerHTML = html;
    hide('editPane');
    show('previewPane');
    tabEdit.classList.remove('active');
    tabPreview.classList.add('active');
  }
}

// ── Important links management ──────────────────────────────────
function addLink(title = '', url = '') {
  const container = document.getElementById('linksContainer');
  const idx = linkIndex++;
  const row = document.createElement('div');
  row.className = 'link-row';
  row.id = `linkRow-${idx}`;
  row.innerHTML = `
    <input type="text" class="form-input" placeholder="Link title…"
           name="link_title_${idx}" value="${escHtml(title)}" style="flex:1.2"/>
    <input type="url" class="form-input" placeholder="https://…"
           name="link_url_${idx}" value="${escHtml(url)}" style="flex:2"/>
    <button type="button" class="btn btn-danger btn-sm" onclick="removeLink(${idx})"
            title="Remove link" style="padding:0.5rem 0.65rem">✕</button>
  `;
  container.appendChild(row);
}

function removeLink(idx) {
  document.getElementById(`linkRow-${idx}`)?.remove();
}

function collectLinks() {
  const links = [];
  document.querySelectorAll('.link-row').forEach(row => {
    const titleInput = row.querySelector('input[name^="link_title"]');
    const urlInput = row.querySelector('input[name^="link_url"]');
    const t = titleInput?.value.trim();
    const u = urlInput?.value.trim();
    if (t && u) links.push({ title: t, url: u });
  });
  return links;
}

// ── Form init & submit ──────────────────────────────────────────
function initForm() {
  document.getElementById('postForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await submitPost();
  });
}

async function submitPost() {
  const btn = document.getElementById('submitBtn');
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = `<div class="spinner" style="width:18px;height:18px;border-width:2px"></div> Publishing…`;

  const payload = {
    title:             getVal('fTitle'),
    type:              getVal('fType'),
    short_description: getVal('fShortDesc'),
    content:           getVal('fContent'),
    category_id:       getVal('fCategory'),
    state_id:          getVal('fState') || null,
    last_date:         getVal('fLastDate') || null,
    notification_date: getVal('fNotifDate') || null,
    total_posts:       getVal('fTotalPosts') ? parseInt(getVal('fTotalPosts')) : null,
    is_featured:       document.getElementById('fFeatured').checked,
    meta_title:        getVal('fMetaTitle') || null,
    meta_description:  getVal('fMetaDesc') || null,
    meta_keywords:     getVal('fMetaKw') || null,
    important_links:   collectLinks(),
  };

  // Remove nulls to keep payload clean
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

    hide('previewCard');
    show('resultCard');

    const inner = document.getElementById('resultInner');
    if (data.success) {
      const postData = data.data;
      inner.innerHTML = `
        <div class="result-success">
          <div class="result-icon">🎉</div>
          <div class="result-title">Published Successfully!</div>
          <div class="result-subtitle">Your post is now live on JobOne.in</div>
          <div class="result-meta">
            ${postData?.id ? `<div>Post ID: <span>#${postData.id}</span></div>` : ''}
            ${postData?.slug ? `<div>Slug: <span>${postData.slug}</span></div>` : ''}
            <div>Title: <span>${escHtml(payload.title)}</span></div>
            <div>Type: <span>${payload.type}</span></div>
          </div>
          <div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center">
            <button class="btn btn-success" onclick="resetForm()">
              <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
              Post Another
            </button>
            ${postData?.id ? `
            <a class="btn btn-outline" href="https://jobone.in/post/${postData.slug || postData.id}" target="_blank">
              <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
              View Post
            </a>` : ''}
          </div>
        </div>
      `;
      loadRecentPosts();
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
    hide('previewCard');
    show('resultCard');
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

// ── Reset form ──────────────────────────────────────────────────
function resetForm() {
  document.getElementById('postForm').reset();
  document.getElementById('linksContainer').innerHTML = '';
  document.getElementById('urlInput').value = '';
  document.getElementById('forcedType').value = '';
  currentType = '';
  linkIndex = 0;
  document.querySelectorAll('.type-pill').forEach(p => p.classList.remove('active'));

  hide('previewCard');
  hide('resultCard');
  hide('loadingCard');
  show('urlCard');

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Recent posts loader ─────────────────────────────────────────
async function loadRecentPosts(page = 1) {
  postsPage = page;
  const container = document.getElementById('recentPosts');
  container.innerHTML = '<div class="empty-state">Loading…</div>';

  try {
    const res = await fetch(`api_proxy.php?action=posts&page=${page}&limit=8`);
    const data = await res.json();

    if (!data.success || !data.data?.length) {
      container.innerHTML = '<div class="empty-state">No posts yet. Start by pasting a URL above!</div>';
      return;
    }

    const posts = data.data;
    const total = data.meta?.total || data.total || posts.length;
    const lastPage = data.meta?.last_page || Math.ceil(total / 8) || 1;

    let html = '<div class="posts-grid">';
    posts.forEach(p => {
      const typeLabel = p.type?.replace('_', ' ') || 'post';
      const date = p.created_at ? new Date(p.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
      html += `
        <div class="post-item">
          <span class="post-type-badge badge-${p.type}">${typeLabel}</span>
          <div class="post-info">
            <div class="post-title" title="${escHtml(p.title)}">${escHtml(p.title)}</div>
            <div class="post-meta">#${p.id} ${date ? '· ' + date : ''} ${p.category?.name ? '· ' + escHtml(p.category.name) : ''}</div>
          </div>
          <div class="post-actions">
            <a href="https://jobone.in/post/${p.slug || p.id}" target="_blank" class="btn btn-ghost btn-sm" title="View post">↗</a>
            <button class="btn btn-danger btn-sm" onclick="deletePost(${p.id})" title="Delete post">🗑</button>
          </div>
        </div>
      `;
    });
    html += '</div>';

    // Pagination
    if (lastPage > 1) {
      html += `<div class="pagination">`;
      if (page > 1) html += `<button class="btn btn-ghost btn-sm" onclick="loadRecentPosts(${page - 1})">← Prev</button>`;
      html += `<span style="font-size:0.82rem;color:var(--text-3)">Page ${page} / ${lastPage}</span>`;
      if (page < lastPage) html += `<button class="btn btn-ghost btn-sm" onclick="loadRecentPosts(${page + 1})">Next →</button>`;
      html += `</div>`;
    }

    container.innerHTML = html;
  } catch (err) {
    container.innerHTML = `<div class="error-state">Failed to load posts: ${escHtml(err.message)}</div>`;
  }
}

// ── Delete post ─────────────────────────────────────────────────
async function deletePost(id) {
  if (!confirm(`Delete post #${id}? This cannot be undone.`)) return;
  try {
    const res = await fetch(`api_proxy.php?action=delete&id=${id}`);
    const data = await res.json();
    if (data.success) {
      showToast(`Post #${id} deleted.`, 'success');
      loadRecentPosts(postsPage);
    } else {
      showToast('Delete failed: ' + (data.body?.message || 'Unknown error'), 'error');
    }
  } catch (err) {
    showToast('Network error: ' + err.message, 'error');
  }
}

// ── Toast notification ──────────────────────────────────────────
function showToast(message, type = 'info') {
  const existing = document.querySelectorAll('.toast');
  existing.forEach(t => t.remove());

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.style.cssText = `
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
    background: ${type === 'error' ? 'rgba(239,68,68,0.95)' : type === 'success' ? 'rgba(16,185,129,0.95)' : 'rgba(99,102,241,0.95)'};
    color: white; padding: 0.85rem 1.25rem; border-radius: 10px;
    font-size: 0.88rem; font-weight: 500; font-family: var(--font);
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    backdrop-filter: blur(10px);
    animation: slideInToast 0.3s ease;
    max-width: 340px; line-height: 1.4;
  `;
  toast.textContent = message;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = 'slideOutToast 0.3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

// Inject toast animations
const toastStyles = document.createElement('style');
toastStyles.textContent = `
  @keyframes slideInToast { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
  @keyframes slideOutToast { from { opacity:1; transform:translateY(0); } to { opacity:0; transform:translateY(16px); } }
`;
document.head.appendChild(toastStyles);

// ── Helpers ─────────────────────────────────────────────────────
function show(id) { document.getElementById(id)?.classList.remove('hidden'); }
function hide(id) { document.getElementById(id)?.classList.add('hidden'); }
function getVal(id) { return document.getElementById(id)?.value.trim() || ''; }
function setVal(id, val) { const el = document.getElementById(id); if (el) el.value = val ?? ''; }

// ── Character counter ──────────────────────────────────────────
function updateCount(inputId, counterId, max) {
  const el = document.getElementById(inputId);
  const counter = document.getElementById(counterId);
  if (!el || !counter) return;
  const len = el.value.length;
  counter.textContent = `${len}/${max}`;
  counter.style.color = len > max * 0.9
    ? (len >= max ? 'var(--danger)' : 'var(--warn)')
    : 'var(--text-3)';
}

function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function isValidUrl(str) {
  try { return ['http:', 'https:'].includes(new URL(str).protocol); }
  catch { return false; }
}

function shakeElement(el) {
  el.style.animation = 'none';
  el.offsetHeight; // reflow
  el.style.animation = 'shake 0.4s ease';
  setTimeout(() => { el.style.animation = ''; }, 400);
}

// Inject shake animation
const shakeStyle = document.createElement('style');
shakeStyle.textContent = `
  @keyframes shake {
    0%,100% { transform: translateX(0); }
    20% { transform: translateX(-8px); }
    40% { transform: translateX(8px); }
    60% { transform: translateX(-5px); }
    80% { transform: translateX(5px); }
  }
`;
document.head.appendChild(shakeStyle);
