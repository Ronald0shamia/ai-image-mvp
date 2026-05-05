# MRS SEO & Speed - WordPress Plugin

AI-powered SEO and performance tools for WordPress: alt text generation, PageSpeed scans, image optimization, meta SEO fixes, usage tracking, statistics, bulk workflows, and a public frontend shortcode.

**Author:** Raeed Shamia - [mrs-dev.com](https://mrs-dev.com/)  
**Version:** 4.0.0  
**License:** GPL-2.0  
**Requires:** WordPress 5.8+, PHP 7.4+, GD Library

---

## Languages / Sprachen / Idiomas / اللغات

The plugin can generate AI output in multiple languages. The current language selector supports:

| Code | Language |
|---|---|
| `auto` | Auto-detect / same language as website content |
| `de` | German / Deutsch |
| `en` | English |
| `fr` | French / Francais |
| `es` | Spanish / Espanol |
| `it` | Italian / Italiano |
| `nl` | Dutch / Nederlands |
| `pt` | Portuguese / Portugues |
| `pl` | Polish / Polski |
| `tr` | Turkish / Turkce |
| `ar` | Arabic / العربية |
| `zh` | Chinese / 中文 |
| `ja` | Japanese / 日本語 |

The `{language}` placeholder in the system prompt is replaced automatically with the selected language instruction.

---

## Deutsch

### Funktionen

- KI Alt-Text Generator fuer WordPress-Bilder
- Medienbibliothek-Button, Attachment-Seite, Gutenberg-Integration und Medien-Modal
- Bulk-Generator fuer alle Bilder oder nur Bilder ohne Alt-Text
- Frontend-Shortcode `[aag_preview]` mit Upload, Ergebnis und optionaler Anzeige
- Google PageSpeed / Lighthouse Scan direkt im WordPress-Admin
- Bildoptimierung mit JPEG/PNG-Kompression und optionaler WebP-Erstellung
- Meta SEO Fixes fuer Titel und Meta Descriptions
- Bild-Verwendungs-Tracking fuer Medien
- Statistikseite mit Tageswerten und Provider-Aufteilung

### Installation

1. Plugin-ZIP in WordPress unter **Plugins > Installieren > Plugin hochladen** hochladen.
2. Plugin aktivieren.
3. Unter **MRS SEO & Speed > Einstellungen** einen KI-Anbieter auswaehlen.
4. API-Key eintragen.
5. Sprache und Prompt konfigurieren.

### Shortcode

```text
[aag_preview]
```

Mit Optionen:

```text
[aag_preview title="SEO & Alt-Text Generator" button_text="Alt-Text generieren"]
```

### Sicherheitshinweise

- Frontend-Bild-Uploads werden serverseitig auf Dateityp und Groesse geprueft.
- Oeffentliche AJAX-Endpunkte nutzen Rate-Limits.
- Externe URL-Scans blockieren lokale/private Netzwerkziele.
- Bildoptimierung schreibt zuerst Temp-Dateien und ersetzt Originale nur nach Validierung.

---

## English

### Features

- AI alt text generator for WordPress images
- Media Library button, attachment screen, Gutenberg integration, and media modal support
- Bulk generator for all images or only images without alt text
- Frontend shortcode `[aag_preview]` with upload, result output, and optional ad popup
- Google PageSpeed / Lighthouse scan inside WordPress admin
- Image optimization with JPEG/PNG compression and optional WebP generation
- Meta SEO fixes for titles and meta descriptions
- Image usage tracking across posts, pages, metadata, and theme settings
- Statistics page with daily counts and provider breakdown

### Installation

1. Upload the plugin ZIP via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin.
3. Open **MRS SEO & Speed > Settings**.
4. Select an AI provider and enter your API key.
5. Configure output language and prompt.

### Shortcode

```text
[aag_preview]
```

With options:

```text
[aag_preview title="SEO & Alt Text Generator" button_text="Generate Alt Text"]
```

### Security Notes

- Frontend image uploads are validated server-side for size and MIME type.
- Public AJAX endpoints use rate limiting.
- External URL scans block localhost, private IPs, and reserved network ranges.
- Image optimization uses temporary files and validates output before replacing originals.

---

## Espanol

### Funciones

- Generador de texto alternativo con IA para imagenes de WordPress
- Boton en la biblioteca de medios, pantalla de adjuntos, Gutenberg y modal de medios
- Generador masivo para todas las imagenes o solo imagenes sin texto alternativo
- Shortcode publico `[aag_preview]` con subida de imagen, resultado y anuncio opcional
- Analisis Google PageSpeed / Lighthouse desde el panel de WordPress
- Optimizacion de imagenes con compresion JPEG/PNG y WebP opcional
- Correcciones SEO para titulos y meta descripciones
- Seguimiento de uso de imagenes
- Pagina de estadisticas con desglose por proveedor

### Instalacion

1. Sube el ZIP del plugin en **Plugins > Anadir nuevo > Subir plugin**.
2. Activa el plugin.
3. Abre **MRS SEO & Speed > Ajustes**.
4. Selecciona un proveedor de IA e introduce tu API key.
5. Configura el idioma de salida y el prompt.

### Shortcode

```text
[aag_preview]
```

Con opciones:

```text
[aag_preview title="Generador SEO y Alt Text" button_text="Generar Alt Text"]
```

### Seguridad

- Las imagenes del frontend se validan en el servidor por tamano y tipo MIME.
- Los endpoints AJAX publicos usan limite de peticiones.
- Los analisis de URL bloquean localhost, IP privadas y rangos reservados.
- La optimizacion de imagenes usa archivos temporales y valida el resultado antes de reemplazar originales.

---

## العربية

### الميزات

- إنشاء نص بديل للصور باستخدام الذكاء الاصطناعي داخل ووردبريس
- زر داخل مكتبة الوسائط وصفحة المرفق ومحرر Gutenberg ونافذة الوسائط
- إنشاء جماعي للنصوص البديلة لكل الصور أو الصور التي لا تحتوي على نص بديل
- كود قصير عام `[aag_preview]` لرفع الصورة وعرض النتيجة مع إعلان اختياري
- فحص Google PageSpeed / Lighthouse من لوحة تحكم ووردبريس
- تحسين الصور بضغط JPEG/PNG وإنشاء WebP اختياري
- تحسين عناوين SEO ووصف Meta
- تتبع أماكن استخدام الصور داخل الموقع
- صفحة إحصائيات حسب اليوم ومزود الذكاء الاصطناعي

### التثبيت

1. ارفع ملف ZIP من خلال **Plugins > Add New > Upload Plugin**.
2. فعّل الإضافة.
3. افتح **MRS SEO & Speed > Settings**.
4. اختر مزود الذكاء الاصطناعي وأدخل مفتاح API.
5. اختر لغة الإخراج وعدّل النص التوجيهي إذا لزم الأمر.

### الكود القصير

```text
[aag_preview]
```

مع خيارات:

```text
[aag_preview title="SEO & Alt Text Generator" button_text="Generate Alt Text"]
```

### ملاحظات الأمان

- يتم التحقق من صور الواجهة الأمامية على الخادم من حيث الحجم ونوع MIME.
- تستخدم نقاط AJAX العامة حدودا لعدد الطلبات.
- يتم منع فحص localhost وعناوين IP الخاصة ونطاقات الشبكات المحجوزة.
- تحسين الصور يستخدم ملفات مؤقتة ويتحقق من النتيجة قبل استبدال الملفات الأصلية.

---

## AI Providers

The plugin supports:

- Google Gemini
- OpenAI
- Anthropic Claude

Only one provider is required. Configure it under **MRS SEO & Speed > Settings**.

---

## Technical Overview

| Area | Details |
|---|---|
| CMS | WordPress 5.8+ |
| PHP | 7.4+ |
| Image Processing | GD Library |
| AJAX | WordPress `wp_ajax_*` and selected public `wp_ajax_nopriv_*` endpoints |
| Storage | WordPress Options API and Post Meta |
| SEO Compatibility | Yoast SEO, Rank Math, AIOSEO |
| Frontend | Vanilla JavaScript and jQuery |

### File Structure

```text
mrs-seo-speed/
├── mrs-seo-speed.php
├── includes/
│   ├── class-admin.php
│   ├── class-api-handler.php
│   ├── class-alt-generator.php
│   ├── class-bulk.php
│   ├── class-frontend.php
│   ├── class-image-optimizer.php
│   ├── class-meta-seo.php
│   ├── class-pagespeed.php
│   ├── class-stats.php
│   └── class-usage-tracker.php
└── assets/
    ├── admin.css
    ├── admin.js
    ├── attachment.js
    ├── block-editor.js
    ├── media-modal.js
    ├── frontend.css
    └── frontend-shortcode.css
```

---

## FAQ

### Does the plugin store images on an external server?

No. Images are converted to Base64 and sent directly to the selected AI provider. The plugin does not store uploaded frontend images externally.

### Does image optimization overwrite original files?

Yes, JPEG/PNG optimization replaces the original file only after a temporary optimized file has been generated and validated. WebP files are created alongside the original file. Always keep backups before bulk optimization.

### Which provider should I start with?

Gemini is a good default for cost-efficient usage. OpenAI and Claude are also supported if you prefer those providers.

### Is the plugin multilingual?

Yes. The generated AI output can be controlled through the language selector and `{language}` prompt placeholder.

---

## Changelog

### 4.0.0

- Added PageSpeed Scan
- Added Image Optimizer
- Added Meta SEO Fixes
- Added dashboard widget improvements
- Rebranded plugin to MRS SEO & Speed

### 3.1.0

- Added Image Usage Tracker

### 3.0.0

- Added statistics page
- Added bulk alt text generator
- Added multilingual output selection

### 2.1.0

- Added frontend shortcode `[aag_preview]`

### 2.0.0

- Added multi-provider support
- Added Gutenberg and media modal integration

### 1.0.0

- Initial AI alt text generation release

---

Built by [Raeed Shamia](https://mrs-dev.com/).
