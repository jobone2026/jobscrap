<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<div id="ig-container" style="position: absolute; top: -9999px; left: -9999px; width: 800px; background: #f0f2f5; font-family: 'Inter', sans-serif; box-sizing: border-box;">
  <div style="background: #0d233a; color: white; padding: 25px; text-align: center; border-bottom: 5px solid #ff9900;">
    <h2 style="margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px;" id="ig-org">Organization Name</h2>
    <h1 style="margin: 15px 0 0 0; font-size: 36px; color: #ffeb3b; text-transform: uppercase;" id="ig-title">JOB TITLE RECRUITMENT 2026</h1>
    <div style="margin-top: 15px; font-size: 20px; font-weight: bold;">
      APPLY FOR <span style="color: #ff5722; background: white; padding: 4px 12px; border-radius: 4px; display: inline-block;" id="ig-vacancies">100</span> POSTS
    </div>
  </div>
  
  <div style="display: flex; justify-content: space-between; padding: 20px; background: white; margin: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08);">
    <div style="text-align: center; flex: 1; border-right: 1px solid #eee;">
      <div style="font-size: 14px; color: #666; font-weight: bold; text-transform: uppercase;">Apply By</div>
      <div style="font-size: 20px; font-weight: bold; color: #d32f2f; margin-top: 5px;" id="ig-last-date">14 MAY 2026</div>
    </div>
    <div style="text-align: center; flex: 1; border-right: 1px solid #eee;">
      <div style="font-size: 14px; color: #666; font-weight: bold; text-transform: uppercase;">Category</div>
      <div style="font-size: 20px; font-weight: bold; margin-top: 5px;" id="ig-category">Govt Job</div>
    </div>
    <div style="text-align: center; flex: 1;">
      <div style="font-size: 14px; color: #666; font-weight: bold; text-transform: uppercase;">Salary / Pay</div>
      <div style="font-size: 20px; font-weight: bold; color: #388e3c; margin-top: 5px;" id="ig-salary">₹26,000 / month</div>
    </div>
  </div>

  <div style="display: flex; gap: 15px; padding: 0 15px 15px 15px;">
    <div style="flex: 1; background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.08);">
      <h3 style="margin: 0 0 15px 0; border-bottom: 2px solid #0d233a; padding-bottom: 8px; color: #0d233a; font-size: 18px; text-transform: uppercase;">Important Dates</h3>
      <table style="width: 100%; font-size: 16px; line-height: 2.2;">
        <tr><td style="color: #555;">Notification</td><td style="text-align: right; font-weight: bold;" id="ig-notif-date">-</td></tr>
        <tr><td style="color: #555;">Apply Start</td><td style="text-align: right; font-weight: bold;" id="ig-start-date">-</td></tr>
        <tr><td style="color: #555;">Apply Last Date</td><td style="text-align: right; font-weight: bold; color: #d32f2f;" id="ig-end-date">-</td></tr>
      </table>
    </div>
    <div style="flex: 1; background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.08);">
      <h3 style="margin: 0 0 15px 0; border-bottom: 2px solid #0d233a; padding-bottom: 8px; color: #0d233a; font-size: 18px; text-transform: uppercase;">Job Details</h3>
      <table style="width: 100%; font-size: 16px; line-height: 2.2;">
        <tr><td style="color: #555;">Age Limit</td><td style="text-align: right; font-weight: bold;" id="ig-age">-</td></tr>
        <tr><td style="color: #555;">Location</td><td style="text-align: right; font-weight: bold;" id="ig-location">All India</td></tr>
        <tr><td style="color: #555;">Education</td><td style="text-align: right; font-weight: bold;" id="ig-education">10th Pass</td></tr>
      </table>
    </div>
  </div>
  
  <div style="background: #ff9900; color: #0d233a; padding: 15px; text-align: center; font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
    Official Updates & Application Link at: <span style="background: #0d233a; color: white; padding: 4px 10px; border-radius: 4px; margin-left: 8px;">JobOne.in</span>
  </div>
</div>

<script>
async function generateInfographicImage(data) {
    if (!html2canvas) return null;
    
    // Populate data
    document.getElementById('ig-org').innerText = data.organization || 'Government Job';
    document.getElementById('ig-title').innerText = data.title || 'Recruitment 2026';
    document.getElementById('ig-vacancies').innerText = data.total_posts || 'Various';
    document.getElementById('ig-last-date').innerText = data.last_date || 'Soon';
    
    document.getElementById('ig-category').innerText = data.category_name || 'Govt Job';
    document.getElementById('ig-salary').innerText = data.salary || 'As per rules';
    
    document.getElementById('ig-notif-date').innerText = data.notification_date || '-';
    document.getElementById('ig-start-date').innerText = data.start_date || '-';
    document.getElementById('ig-end-date').innerText = data.last_date || '-';
    
    document.getElementById('ig-age').innerText = data.age_min ? (data.age_min + ' - ' + (data.age_max_gen || '')) : '-';
    document.getElementById('ig-location').innerText = data.state_name || 'All India';
    
    let edu = 'Various';
    if (data.education && data.education.length > 0) {
        edu = data.education.join(', ');
    }
    document.getElementById('ig-education').innerText = edu;

    // Render to canvas
    const container = document.getElementById('ig-container');
    const canvas = await html2canvas(container, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#f0f2f5'
    });
    
    const base64Image = canvas.toDataURL('image/png');
    return base64Image;
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
