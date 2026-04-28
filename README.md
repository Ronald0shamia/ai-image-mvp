# MRS SEO & Speed — WordPress Plugin

> **SEO & Performance issues in 1 click** — AI-powered Alt Text generation, PageSpeed analysis, image optimization, and Meta SEO fixes for WordPress.

**Author:** Raeed Shamia — [mrs-dev.com](https://mrs-dev.com/)  
**Version:** 4.0.0  
**License:** GPL-2.0  
**Requires:** WordPress 5.8+ · PHP 7.4+ · GD Library

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Screenshots](#screenshots)
- [Setup Guide](#setup-guide)
- [Shortcode](#shortcode)
- [Frequently Asked Questions](#frequently-asked-questions)

---

## Features

### ✨ AI Alt-Text Generator
Generate SEO-optimized `alt=""` attributes for every image — automatically, in any language.

| Where | How |
|---|---|
| Media Library (list view) | "Generate Alt Text" button next to each image |
| Single attachment edit page | Button with live status + cache info |
| Gutenberg Block Editor | Button appears when an Image block is selected |
| Media Upload Modal | Button injected into the sidebar |
| Frontend Shortcode | Visitors upload an image and get the alt text instantly |

- Supports **13 languages**: German, English, French, Spanish, Italian, Dutch, Portuguese, Polish, Turkish, Arabic, Chinese, Japanese + Auto-detect
- Editable system prompt with `{language}` placeholder
- Alt texts are saved directly to WordPress media meta (`_wp_attachment_image_alt`)

---

### ⚡ PageSpeed Scan
Run a full **Google Lighthouse** analysis directly from your WordPress dashboard.

- Scans any URL on your site (or any URL)
- Mobile & Desktop strategy selectable
- Shows **4 category scores**: Performance · SEO · Accessibility · Best Practices
- **Core Web Vitals**: FCP, LCP, TBT, CLS, Speed Index
- **Smart Recommendations** grouped by category (Performance, Images, SEO) with estimated savings
- Scan history: last 10 results stored and displayed
- Latest scores always visible in the **Dashboard Widget**

---

### 🖼 Image Optimizer
Compress images and generate WebP versions — directly in WordPress, no external service needed.

- JPEG compression (adjustable quality, default 82%)
- PNG compression with alpha channel preservation
- **WebP conversion** — creates a `.webp` copy alongside the original
- Bulk processing with progress bar, live log, and stop button
- Auto-optimize on upload (optional setting)
- Shows total savings in KB across all optimized images
- Requires PHP **GD extension** with WebP support

---

### 📝 Meta SEO Fixes
Scan all published posts and pages for SEO issues and fix them inline.

Detects:
- ❌ Missing Meta Description
- ⚠️ Meta Description too short (< 120 chars) or too long (> 160 chars)
- ⚠️ Page title too short (< 30 chars) or too long (> 60 chars)
- ⚠️ Thin content (posts under 300 words)
- 💡 No focus keyword set

Fixes:
- Edit Meta Description directly in the plugin — no need to open each post
- One-click **auto-generate** from post title + content excerpt
- Bulk "auto-generate all missing" button
- Compatible with **Yoast SEO**, **Rank Math**, and **AIOSEO**

---

### 📍 Image Usage Tracker
Know exactly where every image is used across your website.

- New **"Used in"** column in the Media Library list view
- Detailed list on the attachment edit page with "Refresh" button
- **Full overview page** with filter tabs: All · In Use · Not Used
- Scans: post content, featured images, widget areas, theme customizer, post meta (ACF, Elementor, Divi, etc.)
- Results are **cached for 12 hours** and auto-invalidated on post save

---

### ⚡ Bulk Alt-Text Generator
Process your entire media library in one go.

- Filter: only images without alt text, or overwrite all
- Configurable delay between requests (avoids API rate limits)
- Live progress bar + timestamped log
- Stop button at any time

---

### 📊 Statistics
Track how many alt texts have been generated over time.

- Total · Today · Last 30 days · Daily average
- 14-day bar chart
- Breakdown by AI provider
- Visible in Dashboard Widget + dedicated Stats page

---

### 📢 Frontend Shortcode with Ad Popup
Embed a public-facing alt text generator on any page.

- Drag & drop image upload (JPG, PNG, WebP — max 5 MB)
- On click: **Ad popup** shows immediately while AI analyzes
- Result displayed with one-click **Copy** button
- Configurable ad: image with link, or HTML/AdSense code
- Optional auto-close countdown for the popup

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Language** | PHP 7.4+ (OOP, namespaced classes) |
| **CMS** | WordPress 5.8+ |
| **AI Providers** | Google Gemini API · OpenAI API · Anthropic Claude API |
| **PageSpeed** | Google PageSpeed Insights API v5 (Lighthouse) |
| **Image Processing** | PHP GD Library (built-in) |
| **Frontend** | Vanilla JS + jQuery (WordPress bundled) |
| **Admin UI** | WordPress Settings API + custom CSS |
| **Block Editor** | WordPress Block Filters (`wp.hooks.addFilter`) |
| **Caching** | WordPress Options API + Post Meta |
| **AJAX** | WordPress `wp_ajax_*` hooks |
| **SEO Compatibility** | Yoast SEO · Rank Math · AIOSEO |

### File Structure

```
mrs-seo-speed/
├── mrs-seo-speed.php              # Plugin bootstrap, constants
├── includes/
│   ├── class-admin.php            # Admin menu, settings page, dashboard widget
│   ├── class-api-handler.php      # Unified AI API (Gemini / OpenAI / Claude)
│   ├── class-alt-generator.php    # Alt text generation + AJAX + WP hooks
│   ├── class-bulk.php             # Bulk processing with queue + progress
│   ├── class-frontend.php         # [aag_preview] shortcode + popup ad
│   ├── class-image-optimizer.php  # GD compress + WebP conversion
│   ├── class-meta-seo.php         # SEO audit + inline meta editor
│   ├── class-pagespeed.php        # Lighthouse API + results storage
│   ├── class-stats.php            # Usage statistics + daily tracking
│   └── class-usage-tracker.php   # Image usage scanning + cache
└── assets/
    ├── admin.css                  # Admin + stats + pagespeed styles
    ├── admin.js                   # Settings page interactions
    ├── attachment.js              # Media library + attachment page button
    ├── block-editor.js            # Gutenberg image block integration
    ├── media-modal.js             # Media upload modal button
    ├── frontend.css               # Admin-facing button styles
    └── frontend-shortcode.css     # Public shortcode + popup styles
```

---

## Screenshots

> *(Add your screenshots to a `/screenshots/` folder and update the paths below.)*

### 1. Dashboard Widget
Shows alt text statistics, PageSpeed scores, and quick-access links — visible on every admin dashboard.

```
screenshots/01-dashboard-widget.png
```

### 2. Settings Page — AI Provider Selection
Choose between Google Gemini, OpenAI GPT-4o, or Anthropic Claude. Each provider has its own API key and model selector.

```
screenshots/02-settings-provider.png
```

### 3. Settings Page — Language & Prompt
Select the output language from 13 options. Edit the system prompt with the `{language}` placeholder.

```
screenshots/03-settings-prompt.png
```

### 4. Media Library — "Used In" Column + Alt Text Button
The list view shows how many times each image is used and whether an alt text exists. Click to generate.

```
screenshots/04-media-library.png
```

### 5. PageSpeed Scan Results
Score rings for all 4 Lighthouse categories plus Core Web Vitals and Smart Recommendations.

```
screenshots/05-pagespeed-results.png
```

### 6. Image Optimizer
Bulk compression and WebP conversion with live progress bar and savings summary.

```
screenshots/06-image-optimizer.png
```

### 7. Meta SEO Fixes
Full audit of all published pages with inline Meta Description editor and auto-generate button.

```
screenshots/07-meta-seo.png
```

### 8. Frontend Shortcode + Ad Popup
Visitors upload an image, the ad popup appears while the AI processes it, then the alt text is shown with a copy button.

```
screenshots/08-frontend-shortcode.png
```

---

## Setup Guide

### 1. Installation

**Option A — Upload via WordPress Admin (recommended)**

1. Download `mrs-seo-speed.zip`
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Select the ZIP file → click **Install Now**
4. Click **Activate Plugin**

**Option B — Manual FTP Upload**

1. Extract `mrs-seo-speed.zip`
2. Upload the `mrs-seo-speed/` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin → Plugins** and activate **MRS SEO & Speed**

---

### 2. Get an API Key

The plugin supports three AI providers. You only need **one**.

#### Google Gemini (Recommended — Free tier available)
1. Go to [aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey)
2. Click **Create API Key**
3. Copy the key (starts with `AIza...`)

#### OpenAI
1. Go to [platform.openai.com/api-keys](https://platform.openai.com/api-keys)
2. Click **Create new secret key**
3. Copy the key (starts with `sk-...`)

#### Anthropic Claude
1. Go to [console.anthropic.com](https://console.anthropic.com)
2. Navigate to **API Keys → Create Key**
3. Copy the key (starts with `sk-ant-...`)

---

### 3. Configure the Plugin

1. Go to **MRS SEO & Speed → Settings** in your WordPress admin menu
2. Select your AI provider (Gemini / OpenAI / Claude)
3. Paste your API key
4. Select a model — recommended defaults:
   - Gemini: **Gemini 2.5 Flash** (fast + free tier)
   - OpenAI: **GPT-4o mini** (cost-efficient)
   - Claude: **Claude Haiku 4.5** (fastest)
5. Choose the output **language** for alt texts
6. Optionally customize the **prompt** (or leave the default)
7. Click **Save Settings**

---

### 4. Generate Alt Texts

**Single image:**
- Go to **Media → Library** (List View)
- Click **"✨ Generate Alt Text"** next to any image
- The alt text is saved automatically

**Bulk processing:**
- Go to **MRS SEO & Speed → Bulk Generator**
- Select "Only images without alt text"
- Click **Start** — watch the progress bar

---

### 5. Run a PageSpeed Scan

1. Go to **MRS SEO & Speed → PageSpeed Scan**
2. Enter the URL to scan (defaults to your homepage)
3. Choose Mobile or Desktop
4. Optionally add a [Google PageSpeed API key](https://developers.google.com/speed/docs/insights/v5/get-started) for higher rate limits (free)
5. Click **▶ Start Scan**

Results are saved and shown in your **Dashboard Widget** automatically.

---

### 6. Optimize Images

1. Go to **MRS SEO & Speed → Image Optimizer**
2. Check the system requirements (GD library must be active)
3. Select which images to process
4. Click **▶ Start Optimization**

WebP versions are created alongside originals. No original files are deleted.

---

### 7. Fix Meta SEO Issues

1. Go to **MRS SEO & Speed → Meta SEO Fixes**
2. Review the list of issues sorted by severity
3. Edit Meta Descriptions directly in the plugin or click **🤖 Auto-generate**
4. Click **💾 Save** — changes are saved to Yoast SEO / Rank Math / AIOSEO automatically

---

### 8. Frontend Shortcode

Add the shortcode to any page or post:

```
[aag_preview]
```

With custom options:

```
[aag_preview title="Check your Image Alt Text" button_text="Analyze Now"]
```

To show an ad while the AI works:
1. Go to **Settings → Ad Popup**
2. Upload an image or paste AdSense HTML code
3. Set a link and optionally an auto-close delay (0 = close when done)

---

## Frequently Asked Questions

**Does the plugin store my images on any server?**  
No. Images are converted to Base64 and sent directly to the selected AI provider's API. Nothing is stored externally.

**Which AI model should I use?**  
Start with **Gemini 2.5 Flash** — it's the fastest and has a free tier. Upgrade to Pro or GPT-4o for more detailed alt texts on complex images.

**Does image optimization overwrite my originals?**  
Yes — the original file is replaced with the compressed version. A WebP copy is created as an additional file. Back up your `uploads/` folder before bulk processing if needed.

**Is the Meta SEO fixer compatible with my SEO plugin?**  
Yes. It writes to Yoast SEO, Rank Math, and AIOSEO meta fields simultaneously. If you use a different plugin, the data will still be in post meta but may not appear in that plugin's UI.

**The PageSpeed scan fails — what do I do?**  
Without an API key, Google limits free scans. Add a free API key from [Google Cloud Console](https://developers.google.com/speed/docs/insights/v5/get-started) to resolve rate limit errors.

---

## Changelog

### 4.0.0
- Added PageSpeed Scan (Google Lighthouse API)
- Added Image Optimizer (compress + WebP)
- Added Meta SEO Fixes with inline editor
- Plugin rebranded to MRS SEO & Speed
- Dashboard Widget updated with PageSpeed scores

### 3.1.0
- Added Image Usage Tracker
- New column in Media Library list view
- Full usage overview page with filters

### 3.0.0
- Added Statistics page with 14-day chart
- Added Bulk Alt-Text Generator
- Added language selector (13 languages)

### 2.1.0
- Added Frontend Shortcode `[aag_preview]`
- Ad popup system

### 2.0.0
- Multi-provider support: Gemini, OpenAI, Claude
- Gutenberg Block Editor integration
- Media Modal integration

### 1.0.0
- Initial release: Alt text generation with Google Gemini

---

*Built with ❤️ by [Raeed Shamia](https://mrs-dev.com/)*
