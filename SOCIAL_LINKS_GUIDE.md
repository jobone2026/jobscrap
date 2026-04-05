# Auto Social Media Links Feature

Every scraped job post will automatically include your Telegram and WhatsApp channel links at the end!

## What It Does

Automatically adds a beautiful, styled section at the end of every post with:
- 📱 Telegram Channel button
- 💬 WhatsApp Channel button

## Preview

```
┌─────────────────────────────────────────────────┐
│ 📢 Stay Updated with JobOne                     │
│                                                  │
│ Join our channels for instant job notifications,│
│ admit cards, results & exam updates!            │
│                                                  │
│ [📱 Join Telegram Channel] [💬 Join WhatsApp]  │
└─────────────────────────────────────────────────┘
```

## Configuration

Edit `config.php` to customize:

### Your Channel URLs

```php
// Telegram Channel URL
define('TELEGRAM_CHANNEL_URL', 'https://t.me/jobone2026');

// WhatsApp Channel URL
define('WHATSAPP_CHANNEL_URL', 'https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22');
```

### Enable/Disable Feature

```php
// Enable/Disable auto-adding social links
define('AUTO_ADD_SOCIAL_LINKS', true);  // true = add, false = don't add
```

## Current Settings

- ✅ Telegram: https://t.me/jobone2026
- ✅ WhatsApp: https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22
- ✅ Auto-add: Enabled

## How It Works

1. User scrapes a job URL
2. Content is extracted and cleaned
3. AI enhances the content
4. **Social media links are automatically added at the end**
5. Final content is ready to publish

## Benefits

✅ Automatic promotion of your channels
✅ Every post drives traffic to your social media
✅ Consistent branding across all posts
✅ No manual work needed
✅ Professional, styled buttons
✅ Mobile-friendly design

## Styling

The links are styled with:
- Eye-catching colors (Telegram blue, WhatsApp green)
- Emoji icons for visual appeal
- Responsive design (works on mobile)
- Hover effects
- Professional spacing and padding

## To Disable

If you want to disable this feature:

```php
define('AUTO_ADD_SOCIAL_LINKS', false);
```

## To Change URLs

Simply update the URLs in `config.php`:

```php
define('TELEGRAM_CHANNEL_URL', 'https://t.me/your_new_channel');
define('WHATSAPP_CHANNEL_URL', 'https://whatsapp.com/channel/your_new_channel');
```

No code changes needed - just update config and it works!

## Example Output

When you scrape a job, the final HTML will look like:

```html
<h3>Job Details</h3>
<p>Job information here...</p>

<table>
  <tr><td>Post Name</td><td>Clerk</td></tr>
  <tr><td>Vacancies</td><td>100</td></tr>
</table>

<!-- Auto-added social links -->
<div style="margin-top: 30px; padding: 20px; background: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 8px;">
    <h3 style="color: #0369a1; margin-top: 0;">📢 Stay Updated with JobOne</h3>
    <p style="margin: 10px 0;">Join our channels for instant job notifications, admit cards, results & exam updates!</p>
    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;">
        <a href="https://t.me/jobone2026" target="_blank">
            📱 Join Telegram Channel
        </a>
        <a href="https://whatsapp.com/channel/0029VbD9cau2P59hFZ1nwh22" target="_blank">
            💬 Join WhatsApp Channel
        </a>
    </div>
</div>
```

Perfect for building your audience with every post! 🚀
