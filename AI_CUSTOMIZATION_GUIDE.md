# AI Customization Guide

The jobscrap tool now supports fully customizable AI instructions! You can control how the AI processes and formats your scraped content.

## Configuration Options

Edit `config.php` to customize these settings:

### 1. Enable/Disable AI Enhancement

```php
define('AI_ENHANCEMENT_ENABLED', true);  // true = AI on, false = AI off
```

When disabled, the tool will only use auto-scraping without AI enhancement.

### 2. Choose AI Model

```php
define('AI_MODEL', 'deepseek-v3.2');
```

Available models (via AgentRouter):
- `deepseek-v3.2` - Fast, cost-effective (recommended)
- `gpt-4` - OpenAI's GPT-4
- `gpt-4-turbo` - Faster GPT-4
- `claude-3-5-sonnet` - Anthropic's Claude
- `gemini-pro` - Google's Gemini

### 3. Adjust AI Creativity

```php
define('AI_TEMPERATURE', 0.3);
```

- `0.0` - Very focused and consistent (same input = same output)
- `0.3` - Balanced (recommended for job content)
- `0.7` - More creative and varied
- `1.0` - Maximum creativity

### 4. Customize AI System Prompt

```php
define('AI_SYSTEM_PROMPT', "Your custom instructions here...");
```

This is the main instruction that tells the AI how to process content. The default focuses on:
- SEO-optimized titles (max 60 chars)
- Crisp summaries (max 150 chars)
- Professional HTML formatting
- Removing spam and promotional content

### 5. Add Additional Instructions

```php
define('AI_ADDITIONAL_INSTRUCTIONS', "
- Your specific requirement 1
- Your specific requirement 2
- Your specific requirement 3
");
```

## Example Customizations

### Example 1: Focus on Exam Results

```php
define('AI_SYSTEM_PROMPT', "You are an expert at formatting exam results and merit lists. Return JSON with:
1. \"title\": SEO title highlighting the exam name and year (max 60 chars)
2. \"short_description\": Summary mentioning result date and cutoff (max 150 chars)
3. \"content\": HTML with clear sections for cutoff marks, merit list, and next steps");

define('AI_ADDITIONAL_INSTRUCTIONS', "
- Highlight cutoff marks prominently
- Format merit lists in tables
- Include instructions for downloading scorecards
- Mention important dates for next steps
");
```

### Example 2: Focus on Admit Cards

```php
define('AI_SYSTEM_PROMPT', "You are an expert at formatting admit card notifications. Return JSON with:
1. \"title\": SEO title with exam name and admit card (max 60 chars)
2. \"short_description\": Summary with download dates (max 150 chars)
3. \"content\": HTML with download links, exam dates, and instructions");

define('AI_ADDITIONAL_INSTRUCTIONS', "
- Make download links very prominent
- Include exam date, time, and venue details
- Add instructions for downloading admit cards
- Mention documents required for exam day
");
```

### Example 3: Minimal AI Processing

```php
define('AI_TEMPERATURE', 0.1);  // Very consistent

define('AI_SYSTEM_PROMPT', "Format the job content as clean HTML. Return JSON with:
1. \"title\": Clean title (max 60 chars)
2. \"short_description\": Brief summary (max 150 chars)
3. \"content\": Organized HTML with headings and tables. Keep original content, just clean formatting.");

define('AI_ADDITIONAL_INSTRUCTIONS', "
- Keep original wording as much as possible
- Only fix formatting and structure
- Remove spam and promotional links
");
```

### Example 4: Disable AI Completely

```php
define('AI_ENHANCEMENT_ENABLED', false);
```

This will use only auto-scraping without any AI processing.

## Testing Your Changes

1. Edit `config.php` with your custom settings
2. Save the file
3. Upload to server: `sudo cp config.php /var/www/jobone/public/jobscrap/`
4. Test by scraping a job URL at https://jobone.in/jobscrap/
5. Review the generated content
6. Adjust settings as needed

## Tips

- Start with small changes to the `AI_ADDITIONAL_INSTRUCTIONS`
- Test with different types of content (jobs, results, admit cards)
- Lower temperature (0.1-0.3) for consistent formatting
- Higher temperature (0.5-0.7) for more creative rewrites
- Use specific models for specific needs:
  - DeepSeek: Fast, cheap, good for bulk processing
  - GPT-4: Best quality, more expensive
  - Claude: Great for long content

## Troubleshooting

If AI enhancement fails:
- Check your `AGENTROUTER_API_KEY` is valid
- Verify the model name is correct
- Check API quota/credits
- The tool will fall back to auto-scraped content automatically

## Cost Optimization

To reduce API costs:
- Use `deepseek-v3.2` (cheapest)
- Set `AI_TEMPERATURE` to 0.1 (faster processing)
- Disable AI for simple content: `AI_ENHANCEMENT_ENABLED = false`
- Process in batches during off-peak hours
