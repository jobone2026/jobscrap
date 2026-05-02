<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Hidden infographic container rendered by html2canvas -->
<div id="ig-container" style="position:absolute;top:-9999px;left:-9999px;width:800px;font-family:'Segoe UI',Arial,sans-serif;box-sizing:border-box;"></div>

<script>
/* ── Type config ─────────────────────────────────────────────────── */
const TYPE_CONFIG = {
    job: {
        headerBg: '#0d233a', accentColor: '#ff9900', labelColor: '#ffeb3b',
        icon: '💼', badge: 'RECRUITMENT', actionLabel: 'APPLY NOW',
        subLabel: 'VACANCIES', subField: d => d.total_posts || 'Various Posts',
        fields: d => [
            ['📅 Notification', d.notification_date || '-'],
            ['🟢 Apply Start',  d.start_date || '-'],
            ['🔴 Last Date',    d.last_date || '-'],
            ['💰 Salary',       d.salary || 'As per rules'],
            ['🎓 Education',    eduLabel(d)],
            ['📍 Location',     d.state_name || 'All India'],
            ['👥 Age Limit',    ageLabel(d)],
        ],
    },
    admit_card: {
        headerBg: '#1a237e', accentColor: '#3f51b5', labelColor: '#bbdefb',
        icon: '🎫', badge: 'ADMIT CARD', actionLabel: 'DOWNLOAD NOW',
        subLabel: 'STATUS', subField: d => 'Released',
        fields: d => [
            ['📅 Released Date', d.notification_date || '-'],
            ['⏰ Available Till', d.last_date || 'Till Exam'],
            ['📍 Location',      d.state_name || 'All India'],
            ['🎓 Qualification', eduLabel(d)],
        ],
    },
    result: {
        headerBg: '#1b5e20', accentColor: '#43a047', labelColor: '#c8e6c9',
        icon: '🏆', badge: 'RESULT DECLARED', actionLabel: 'CHECK RESULT',
        subLabel: 'STATUS', subField: d => 'Declared',
        fields: d => [
            ['📅 Result Date',  d.notification_date || '-'],
            ['📍 Location',     d.state_name || 'All India'],
            ['🎓 Qualification', eduLabel(d)],
        ],
    },
    answer_key: {
        headerBg: '#4a148c', accentColor: '#ab47bc', labelColor: '#e1bee7',
        icon: '🔑', badge: 'ANSWER KEY', actionLabel: 'DOWNLOAD KEY',
        subLabel: 'STATUS', subField: d => 'Available',
        fields: d => [
            ['📅 Released',            d.notification_date || '-'],
            ['⏰ Objection Last Date', d.last_date || '-'],
            ['📍 Location',            d.state_name || 'All India'],
        ],
    },
    syllabus: {
        headerBg: '#e65100', accentColor: '#ff7043', labelColor: '#ffe0b2',
        icon: '📚', badge: 'SYLLABUS', actionLabel: 'DOWNLOAD PDF',
        subLabel: 'EXAM TYPE', subField: d => d.category_name || 'Written Exam',
        fields: d => [
            ['📍 Location',     d.state_name || 'All India'],
            ['🎓 Qualification', eduLabel(d)],
        ],
    },
    scholarship: {
        headerBg: '#006064', accentColor: '#00acc1', labelColor: '#b2ebf2',
        icon: '🎓', badge: 'SCHOLARSHIP', actionLabel: 'APPLY NOW',
        subLabel: 'AMOUNT', subField: d => d.salary || 'Check Notification',
        fields: d => [
            ['📅 Notification',  d.notification_date || '-'],
            ['🔴 Last Date',     d.last_date || '-'],
            ['📍 Location',      d.state_name || 'All India'],
            ['🎓 Qualification', eduLabel(d)],
        ],
    },
    blog: {
        headerBg: '#37474f', accentColor: '#78909c', labelColor: '#eceff1',
        icon: '📝', badge: 'NEWS & UPDATES', actionLabel: 'READ MORE',
        subLabel: 'CATEGORY', subField: d => d.category_name || 'General',
        fields: d => [
            ['📅 Published', d.notification_date || new Date().toLocaleDateString('en-IN')],
            ['📍 Relevant',  d.state_name || 'All India'],
        ],
    },
};

function eduLabel(d) {
    if (!d.education || !d.education.length) return 'Various';
    const map = {'10th_pass':'10th','12th_pass':'12th','graduate':'Graduate','post_graduate':'PG','diploma':'Diploma','iti':'ITI','btech':'B.Tech','mtech':'M.Tech','mbbs':'MBBS','any_qualification':'Any'};
    return d.education.slice(0,3).map(e => map[e] || e).join(', ');
}

function ageLabel(d) {
    if (!d.age_min) return '-';
    return d.age_min + ' – ' + (d.age_max_gen || '?') + ' Years';
}

/* ── Build the HTML for the container ───────────────────────────── */
function buildInfographic(data) {
    const postType = (data.type || 'job').toLowerCase().replace(' ','_');
    const cfg = TYPE_CONFIG[postType] || TYPE_CONFIG['job'];
    const fields = cfg.fields(data);

    const fieldsHtml = fields.map(([label, val]) => `
        <tr>
            <td style="padding:7px 0;color:#555;font-size:15px;">${label}</td>
            <td style="padding:7px 0;text-align:right;font-weight:700;font-size:15px;color:#222;">${val}</td>
        </tr>`).join('');

    return `
    <div style="background:${cfg.headerBg};color:white;padding:28px 30px 22px;text-align:center;border-bottom:6px solid ${cfg.accentColor};">
        <div style="font-size:42px;margin-bottom:8px;">${cfg.icon}</div>
        <div style="font-size:13px;letter-spacing:3px;color:${cfg.labelColor};font-weight:600;text-transform:uppercase;margin-bottom:6px;">${cfg.badge}</div>
        <div id="ig-title" style="font-size:26px;font-weight:800;color:white;line-height:1.3;text-transform:uppercase;">${data.title || 'Update 2026'}</div>
        <div style="margin-top:10px;font-size:15px;color:${cfg.labelColor};opacity:0.85;">
            ${data.organization || 'Government of India'}
        </div>
        <div style="margin-top:14px;display:inline-block;background:${cfg.accentColor};color:#000;font-size:18px;font-weight:800;padding:8px 24px;border-radius:6px;text-transform:uppercase;letter-spacing:1px;">
            ${cfg.subLabel}: &nbsp;${cfg.subField(data)}
        </div>
    </div>

    <div style="background:#f7f8fa;padding:20px 25px;">
        <table style="width:100%;border-collapse:collapse;">
            ${fieldsHtml}
        </table>
    </div>

    <div style="background:${cfg.accentColor};color:#000;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
        <div style="font-size:16px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">
            ${cfg.actionLabel} → <strong>JobOne.in</strong>
        </div>
        <div style="background:#0d233a;color:white;font-size:14px;font-weight:800;padding:6px 16px;border-radius:4px;letter-spacing:1px;">
            JOBONE.IN
        </div>
    </div>`;
}

/* ── Main generate function ─────────────────────────────────────── */
async function generateInfographicImage(data) {
    const container = document.getElementById('ig-container');
    if (!container || typeof html2canvas === 'undefined') return null;

    container.innerHTML = buildInfographic(data);

    const canvas = await html2canvas(container, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#f0f2f5',
        logging: false,
    });

    return canvas.toDataURL('image/png');
}

async function uploadInfographic(base64Image, title) {
    const res = await fetch('api.php?action=upload_base64_image', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ image: base64Image, title: title })
    });
    const result = await res.json();
    return result.url || null;
}
</script>
