<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSS_Admin {

    const PLUGIN_AD = array(
        'image' => 'https://mrs-dev.com/wp-content/uploads/2025/05/cropped-IMG_0878.png',
        'link'  => 'https://mrs-dev.com/',
        'title' => 'MRS Dev',
        'text'  => 'Professionelle WordPress-Entwicklung, SEO & Speed-Optimierung von Raeed Shamia.',
        'cta'   => 'mrs-dev.com besuchen',
    );

    public static function init() {
        add_action( 'admin_menu',         array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init',         array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widget' ) );
    }

    public static function add_menu() {
        add_menu_page(
            'MRS SEO & Speed',
            'MRS SEO & Speed',
            'manage_options',
            'ai-alt-generator',
            array( __CLASS__, 'render_page' ),
            'dashicons-superhero',
            80
        );
        add_submenu_page( 'ai-alt-generator', 'Einstellungen',   'Einstellungen',   'manage_options', 'ai-alt-generator',        array( __CLASS__,           'render_page' ) );
        add_submenu_page( 'ai-alt-generator', 'Bulk-Generator',  'Bulk-Generator',  'manage_options', 'ai-alt-bulk',             array( 'AAG_Bulk',          'render_page' ) );
        add_submenu_page( 'ai-alt-generator', 'Statistik',       'Statistik',       'manage_options', 'ai-alt-stats',            array( 'AAG_Stats',         'render_page' ) );
        add_submenu_page( 'ai-alt-generator', 'Bild-Verwendung', 'Bild-Verwendung', 'manage_options', 'ai-alt-usage',            array( 'AAG_Usage_Tracker', 'render_page' ) );
        add_submenu_page( 'ai-alt-generator', 'PageSpeed Scan',  'PageSpeed Scan',  'manage_options', 'mss-pagespeed',           array( 'MSS_PageSpeed',     'render_page' ) );
        add_submenu_page( 'ai-alt-generator', 'Bilder optimieren','Bilder optimieren','manage_options','mss-image-optimizer',    array( 'MSS_Image_Optimizer','render_page') );
        add_submenu_page( 'ai-alt-generator', 'Meta SEO Fixes',  'Meta SEO Fixes',  'manage_options', 'mss-meta-seo',            array( 'MSS_Meta_SEO',      'render_page' ) );
    }

    public static function register_settings() {
        register_setting( 'aag_settings_group', AAG_OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
    }

    public static function sanitize( $in ): array {
        $out = array();
        $out['provider']       = in_array( $in['provider'] ?? '', array('gemini','openai','claude'), true ) ? $in['provider'] : 'gemini';
        $out['prompt']         = sanitize_textarea_field( $in['prompt']         ?? AAG_Alt_Generator::default_prompt() );
        $out['language']       = sanitize_text_field(     $in['language']       ?? 'auto' );
        $out['gemini_key']     = sanitize_text_field(     $in['gemini_key']     ?? '' );
        $out['gemini_model']   = sanitize_text_field(     $in['gemini_model']   ?? 'gemini-2.5-flash' );
        $out['openai_key']     = sanitize_text_field(     $in['openai_key']     ?? '' );
        $out['openai_model']   = sanitize_text_field(     $in['openai_model']   ?? 'gpt-4o-mini' );
        $out['claude_key']     = sanitize_text_field(     $in['claude_key']     ?? '' );
        $out['claude_model']   = sanitize_text_field(     $in['claude_model']   ?? 'claude-haiku-4-5-20251001' );
        $out['ad_type']        = in_array( $in['ad_type'] ?? '', array('image','html'), true ) ? $in['ad_type'] : 'image';
        $out['ad_image_url']   = esc_url_raw(             $in['ad_image_url']   ?? '' );
        $out['ad_html']        = wp_kses_post(            $in['ad_html']        ?? '' );
        $out['ad_link']        = esc_url_raw(             $in['ad_link']        ?? '' );
        $out['ad_popup_delay'] = intval(                  $in['ad_popup_delay'] ?? 0 );
        $out['pagespeed_key']  = sanitize_text_field(     $in['pagespeed_key']  ?? '' );
        return $out;
    }

    public static function enqueue_assets( $hook ) {
        $pages = array(
            'toplevel_page_ai-alt-generator',
            'index.php',
            'ai-alt-text_page_ai-alt-bulk',
            'ai-alt-text_page_ai-alt-stats',
            'ai-alt-text_page_ai-alt-usage',
            'ai-alt-text_page_mss-pagespeed',
            'ai-alt-text_page_mss-image-optimizer',
            'ai-alt-text_page_mss-meta-seo',
            'upload.php',
        );
        if ( ! in_array( $hook, $pages, true ) ) return;
        wp_enqueue_style( 'mss-admin', AAG_URL . 'assets/admin.css', array(), AAG_VERSION );
        if ( $hook === 'toplevel_page_ai-alt-generator' ) {
            wp_enqueue_media();
            wp_enqueue_script( 'mss-admin', AAG_URL . 'assets/admin.js', array('jquery'), AAG_VERSION, true );
        }
    }

    public static function add_dashboard_widget() {
        if ( ! current_user_can('manage_options') ) return;
        wp_add_dashboard_widget( 'mss_dashboard_widget', 'MRS SEO &amp; Speed', array( __CLASS__, 'render_dashboard_widget' ) );
    }

    public static function render_dashboard_widget() {
        $opts      = get_option( AAG_OPTION, array() );
        $provider  = $opts['provider'] ?? 'gemini';
        $key_map   = array( 'gemini' => 'gemini_key', 'openai' => 'openai_key', 'claude' => 'claude_key' );
        $name_map  = array( 'gemini' => 'Google Gemini', 'openai' => 'OpenAI', 'claude' => 'Claude' );
        $has_key   = ! empty( $opts[ $key_map[ $provider ] ?? 'gemini_key' ] );
        $prov_name = $name_map[ $provider ] ?? $provider;

        $total  = AAG_Stats::get_total();
        $today  = AAG_Stats::get_today();
        $last30 = AAG_Stats::get_last_30_days_total();
        $ps     = MSS_PageSpeed::get_last_scores();

        $sc = function($s){ return $s>=90?'#16a34a':($s>=50?'#b45309':'#dc2626'); };
        ?>
        <div class="aag-dw-wrap">

            <div class="aag-dw-status <?php echo $has_key ? 'ok' : 'warn'; ?>">
                <?php echo esc_html( $prov_name ); ?> &mdash;
                <?php if ( $has_key ) : ?>
                    API-Key gesetzt
                <?php else : ?>
                    API-Key fehlt &mdash;
                    <a href="<?php echo esc_url( admin_url('admin.php?page=ai-alt-generator') ); ?>">Jetzt einrichten</a>
                <?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px">
                <?php foreach ( array( 'Gesamt' => $total, 'Heute' => $today, '30 Tage' => $last30 ) as $lbl => $val ) : ?>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px;text-align:center">
                    <span style="display:block;font-size:20px;font-weight:700;color:#0f172a"><?php echo number_format($val); ?></span>
                    <span style="font-size:10px;color:#94a3b8;text-transform:uppercase"><?php echo $lbl; ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ( $ps ) : ?>
            <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin:0 0 6px">
                Letzter PageSpeed-Scan
            </p>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;margin-bottom:12px">
                <?php
                $scores = array( 'Performance' => $ps['performance'], 'SEO' => $ps['seo'], 'Accessibility' => $ps['accessibility'], 'Best Practices' => $ps['best_practices'] );
                foreach ( $scores as $lbl => $val ) : ?>
                <div style="background:#f8fafc;border-radius:6px;padding:5px 8px;display:flex;justify-content:space-between;align-items:center;font-size:12px">
                    <span style="color:#64748b"><?php echo $lbl; ?></span>
                    <strong style="color:<?php echo $sc($val); ?>"><?php echo $val; ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
            <p style="font-size:10px;color:#94a3b8;margin:0 0 10px">
                Gescannt: <?php echo esc_html($ps['scanned_at']); ?> &mdash;
                <a href="<?php echo esc_url(admin_url('admin.php?page=mss-pagespeed')); ?>">Neu scannen</a>
            </p>
            <?php endif; ?>

            <ul class="aag-dw-list">
                <li><a href="<?php echo esc_url(admin_url('upload.php')); ?>">Medienbibliothek</a> &mdash; Alt-Text pro Bild generieren</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=ai-alt-bulk')); ?>">Bulk-Generator</a> &mdash; Alle Bilder auf einmal</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=ai-alt-usage')); ?>">Bild-Verwendung</a> &mdash; Wo wird jedes Bild genutzt?</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=mss-pagespeed')); ?>">PageSpeed Scan</a> &mdash; Google Lighthouse Analyse</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=mss-image-optimizer')); ?>">Bilder optimieren</a> &mdash; Komprimieren &amp; WebP</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=mss-meta-seo')); ?>">Meta SEO Fixes</a> &mdash; Beschreibungen &amp; Titel</li>
            </ul>

            <?php $ad = self::PLUGIN_AD; if ( ! empty($ad['text']) || ! empty($ad['image']) ) : ?>
            <div class="aag-dw-ad">
                <?php if ( ! empty($ad['image']) ) : ?>
                <a href="<?php echo esc_url($ad['link']); ?>" target="_blank" rel="noopener">
                    <img src="<?php echo esc_url($ad['image']); ?>" alt="<?php echo esc_attr($ad['title']); ?>" class="aag-dw-ad-img">
                </a>
                <?php endif; ?>
                <?php if ( ! empty($ad['title']) ) : ?><strong class="aag-dw-ad-title"><?php echo esc_html($ad['title']); ?></strong><?php endif; ?>
                <?php if ( ! empty($ad['text'])  ) : ?><p class="aag-dw-ad-text"><?php echo esc_html($ad['text']); ?></p><?php endif; ?>
                <?php if ( ! empty($ad['link']) && ! empty($ad['cta']) ) : ?>
                <a href="<?php echo esc_url($ad['link']); ?>" target="_blank" rel="noopener" class="aag-dw-ad-cta"><?php echo esc_html($ad['cta']); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-alt-generator')); ?>" class="aag-dw-settings-link">
                Plugin-Einstellungen
            </a>
        </div>
        <?php
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) wp_die( 'Keine Berechtigung.' );
        $opts     = get_option( AAG_OPTION, array() );
        $provider = $opts['provider'] ?? 'gemini';

        $providers = array(
            'gemini' => array( 'label'=>'Google Gemini', 'desc'=>'Empfohlen - kostenloser Free-Tier verfuegbar',  'color'=>'#4285f4', 'models'=>array('gemini-2.5-flash'=>'Gemini 2.5 Flash (Standard)','gemini-2.5-pro'=>'Gemini 2.5 Pro','gemini-2.0-flash'=>'Gemini 2.0 Flash'), 'key_name'=>'gemini_key', 'model_name'=>'gemini_model', 'key_hint'=>'aistudio.google.com/app/apikey', 'key_prefix'=>'AIza...' ),
            'openai' => array( 'label'=>'OpenAI',         'desc'=>'GPT-4o - praezise Bildbeschreibungen',          'color'=>'#10a37f', 'models'=>array('gpt-4o-mini'=>'GPT-4o mini (kostenguenstig)','gpt-4o'=>'GPT-4o (leistungsstark)'),                              'key_name'=>'openai_key', 'model_name'=>'openai_model', 'key_hint'=>'platform.openai.com/api-keys',  'key_prefix'=>'sk-...' ),
            'claude' => array( 'label'=>'Anthropic Claude','desc'=>'Claude Haiku - schnell und effizient',          'color'=>'#cc785c', 'models'=>array('claude-haiku-4-5-20251001'=>'Claude Haiku 4.5 (Standard)','claude-sonnet-4-5'=>'Claude Sonnet 4.5','claude-opus-4-5'=>'Claude Opus 4.5'), 'key_name'=>'claude_key',  'model_name'=>'claude_model',  'key_hint'=>'console.anthropic.com',         'key_prefix'=>'sk-ant-...' ),
        );
        $languages = array( 'auto'=>'Automatisch (Website-Sprache)', 'de'=>'Deutsch', 'en'=>'English', 'fr'=>'Francais', 'es'=>'Espanol', 'it'=>'Italiano', 'nl'=>'Nederlands', 'pt'=>'Portugues', 'pl'=>'Polski', 'tr'=>'Turkce', 'ar'=>'Arabic', 'zh'=>'Chinese', 'ja'=>'Japanese' );
        $p_active  = $providers[ $provider ];
        $has_key   = ! empty( $opts[ $p_active['key_name'] ] );
        $ad        = self::PLUGIN_AD;
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-superhero"></span>
                MRS SEO &amp; Speed
            </h1>
            <?php settings_errors(); ?>

            <div class="aag-layout">
                <div class="aag-main">
                <form method="post" action="options.php">
                    <?php settings_fields( 'aag_settings_group' ); ?>

                    <!-- AI-Anbieter -->
                    <div class="aag-card">
                        <h2>AI-Anbieter</h2>
                        <p class="description" style="margin:0 0 16px">Waehle den Anbieter und trage deinen API-Key ein. Du benoenigst nur einen.</p>
                        <div class="aag-provider-grid">
                            <?php foreach ( $providers as $key => $p ) : ?>
                            <label class="aag-provider-card <?php echo $provider===$key?'active':''; ?>"
                                   style="--provider-color:<?php echo esc_attr($p['color']); ?>">
                                <input type="radio" name="<?php echo AAG_OPTION; ?>[provider]"
                                       value="<?php echo esc_attr($key); ?>" <?php checked($provider,$key); ?>>
                                <span class="aag-provider-name"><?php echo esc_html($p['label']); ?></span>
                                <span class="aag-provider-desc"><?php echo esc_html($p['desc']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ( $providers as $key => $p ) : ?>
                        <div class="aag-provider-fields" data-provider="<?php echo esc_attr($key); ?>"
                             <?php echo $provider!==$key?'style="display:none"':''; ?>>
                            <table class="form-table">
                                <tr>
                                    <th><label>API-Key</label></th>
                                    <td>
                                        <div class="aag-key-row">
                                            <input type="password"
                                                   name="<?php echo AAG_OPTION; ?>[<?php echo $p['key_name']; ?>]"
                                                   value="<?php echo esc_attr($opts[$p['key_name']]??''); ?>"
                                                   class="regular-text"
                                                   placeholder="<?php echo esc_attr($p['key_prefix']); ?>"
                                                   autocomplete="off">
                                            <button type="button" class="button aag-toggle-key">Anzeigen</button>
                                        </div>
                                        <p class="description">Key erstellen: <a href="https://<?php echo esc_html($p['key_hint']); ?>" target="_blank"><?php echo esc_html($p['key_hint']); ?></a></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label>Modell</label></th>
                                    <td>
                                        <select name="<?php echo AAG_OPTION; ?>[<?php echo $p['model_name']; ?>]">
                                            <?php foreach ( $p['models'] as $mval => $mlabel ) : ?>
                                            <option value="<?php echo esc_attr($mval); ?>" <?php selected($opts[$p['model_name']]??'',$mval); ?>>
                                                <?php echo esc_html($mlabel); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Sprache -->
                    <div class="aag-card">
                        <h2>Sprache</h2>
                        <p class="description" style="margin:0 0 16px">In welcher Sprache sollen Alt-Texte generiert werden?</p>
                        <table class="form-table">
                            <tr>
                                <th><label for="aag_language">Ausgabesprache</label></th>
                                <td>
                                    <select name="<?php echo AAG_OPTION; ?>[language]" id="aag_language">
                                        <?php foreach ( $languages as $code => $label ) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($opts['language']??'auto',$code); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">"Automatisch" erkennt die Sprache anhand des Website-Inhalts.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Prompt -->
                    <div class="aag-card">
                        <h2>System-Prompt</h2>
                        <p class="description" style="margin:0 0 12px">Diese Anweisung wird bei jeder Analyse an die KI gesendet. Verwende <code>{language}</code> als Platzhalter fuer die gewaehlte Sprache.</p>
                        <textarea name="<?php echo AAG_OPTION; ?>[prompt]" rows="10"
                                  class="large-text aag-prompt-editor"
                        ><?php echo esc_textarea($opts['prompt']??AAG_Alt_Generator::default_prompt()); ?></textarea>
                        <div style="margin-top:10px">
                            <button type="button" class="button aag-reset-prompt"
                                    data-default="<?php echo esc_attr(AAG_Alt_Generator::default_prompt()); ?>">
                                Standard wiederherstellen
                            </button>
                        </div>
                    </div>

                    <!-- Werbeanzeige -->
                    <div class="aag-card">
                        <h2>Werbeanzeige im Shortcode</h2>
                        <p class="description" style="margin:0 0 16px">Wird als Popup angezeigt waehrend die KI im Shortcode <code>[aag_preview]</code> das Bild analysiert.</p>
                        <table class="form-table">
                            <tr>
                                <th>Popup-Verhalten</th>
                                <td>
                                    <label>Automatisch schliessen nach
                                        <input type="number" name="<?php echo AAG_OPTION; ?>[ad_popup_delay]"
                                               value="<?php echo intval($opts['ad_popup_delay']??0); ?>"
                                               min="0" max="60" style="width:60px;margin:0 6px">
                                        Sekunden
                                    </label>
                                    <p class="description">0 = Popup bleibt bis Analyse abgeschlossen ist (empfohlen)</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Anzeigetyp</th>
                                <td>
                                    <label style="margin-right:16px"><input type="radio" name="<?php echo AAG_OPTION; ?>[ad_type]" value="image" <?php checked($opts['ad_type']??'image','image'); ?>> Bild-Anzeige</label>
                                    <label><input type="radio" name="<?php echo AAG_OPTION; ?>[ad_type]" value="html" <?php checked($opts['ad_type']??'image','html'); ?>> HTML / Code (z.B. AdSense)</label>
                                </td>
                            </tr>
                            <tr id="aag-ad-row-image">
                                <th><label>Bild-URL</label></th>
                                <td>
                                    <div class="aag-key-row">
                                        <input type="url" id="aag_ad_image_url" name="<?php echo AAG_OPTION; ?>[ad_image_url]"
                                               value="<?php echo esc_url($opts['ad_image_url']??''); ?>"
                                               class="regular-text" placeholder="https://...">
                                        <button type="button" class="button" id="aag-upload-ad-btn">Bild waehlen</button>
                                    </div>
                                    <?php if ( ! empty($opts['ad_image_url']) ) : ?>
                                    <div style="margin-top:10px">
                                        <img src="<?php echo esc_url($opts['ad_image_url']); ?>"
                                             style="max-width:260px;border-radius:6px;border:1px solid #e2e8f0;display:block">
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Ziel-Link</label></th>
                                <td>
                                    <input type="url" name="<?php echo AAG_OPTION; ?>[ad_link]"
                                           value="<?php echo esc_url($opts['ad_link']??''); ?>"
                                           class="regular-text" placeholder="https://...">
                                    <p class="description">Wohin fuehrt ein Klick auf die Anzeige?</p>
                                </td>
                            </tr>
                            <tr id="aag-ad-row-html">
                                <th><label>HTML-Code</label></th>
                                <td>
                                    <textarea name="<?php echo AAG_OPTION; ?>[ad_html]" rows="5" class="large-text code"
                                              placeholder="&lt;script async src=&quot;https://pagead2...&quot;&gt;&lt;/script&gt;"
                                    ><?php echo esc_textarea($opts['ad_html']??''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- PageSpeed API-Key -->
                    <div class="aag-card">
                        <h2>PageSpeed API-Key (optional)</h2>
                        <table class="form-table">
                            <tr>
                                <th><label>Google API-Key</label></th>
                                <td>
                                    <div class="aag-key-row">
                                        <input type="password" name="<?php echo AAG_OPTION; ?>[pagespeed_key]"
                                               value="<?php echo esc_attr($opts['pagespeed_key']??''); ?>"
                                               class="regular-text" placeholder="AIza..." autocomplete="off">
                                        <button type="button" class="button aag-toggle-key">Anzeigen</button>
                                    </div>
                                    <p class="description">Fuer hoehere Rate Limits beim PageSpeed Scan. Kostenlos erstellen unter <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">Google Cloud Console</a>.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="padding-top:4px">
                        <?php submit_button( 'Einstellungen speichern', 'primary large', 'submit', false ); ?>
                    </div>
                </form>
                </div>

                <!-- Sidebar -->
                <div class="aag-sidebar">
                    <div class="aag-card">
                        <h3>Status</h3>
                        <div class="aag-status-badge <?php echo $has_key?'ok':'warn'; ?>" style="margin-bottom:0">
                            <?php echo esc_html($p_active['label']); ?> &mdash;
                            <?php echo $has_key?'API-Key gesetzt':'API-Key fehlt'; ?>
                        </div>
                    </div>

                    <div class="aag-card">
                        <h3>Verwendung</h3>
                        <ul>
                            <li><strong>Medienbibliothek</strong><br><span style="font-size:12px;color:#64748b">Bild oeffnen &rarr; "Alt-Text generieren"</span></li>
                            <li><strong>Block-Editor</strong><br><span style="font-size:12px;color:#64748b">Bild-Block auswaehlen &rarr; Button klicken</span></li>
                            <li><strong>Frontend-Shortcode</strong><br><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px">[aag_preview]</code></li>
                        </ul>
                    </div>

                    <div class="aag-card">
                        <h3>Module</h3>
                        <ul>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=ai-alt-bulk')); ?>">Bulk-Generator</a><span style="display:block;font-size:12px;color:#64748b">Alle Bilder auf einmal verarbeiten</span></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=ai-alt-stats')); ?>">Statistik</a><span style="display:block;font-size:12px;color:#64748b">Generierungen im Zeitverlauf</span></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=ai-alt-usage')); ?>">Bild-Verwendung</a><span style="display:block;font-size:12px;color:#64748b">Wo wird jedes Bild eingesetzt?</span></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=mss-pagespeed')); ?>">PageSpeed Scan</a><span style="display:block;font-size:12px;color:#64748b">Google Lighthouse Analyse</span></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=mss-image-optimizer')); ?>">Bilder optimieren</a><span style="display:block;font-size:12px;color:#64748b">Komprimieren &amp; WebP erstellen</span></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=mss-meta-seo')); ?>">Meta SEO Fixes</a><span style="display:block;font-size:12px;color:#64748b">Meta Descriptions &amp; Titel pruefen</span></li>
                        </ul>
                    </div>

                    <?php if ( ! empty($ad['text']) || ! empty($ad['image']) ) : ?>
                    <div class="aag-card aag-card-plugin-ad" style="text-align:center">
                        <?php if ( ! empty($ad['image']) ) : ?>
                        <a href="<?php echo esc_url($ad['link']); ?>" target="_blank" rel="noopener">
                            <img src="<?php echo esc_url($ad['image']); ?>" alt="<?php echo esc_attr($ad['title']); ?>"
                                 style="width:100%;border-radius:6px;display:block;margin-bottom:12px">
                        </a>
                        <?php endif; ?>
                        <?php if ( ! empty($ad['title']) ) : ?><strong style="display:block;font-size:13px;margin-bottom:5px;color:#0f172a"><?php echo esc_html($ad['title']); ?></strong><?php endif; ?>
                        <?php if ( ! empty($ad['text'])  ) : ?><p style="font-size:12px;color:#64748b;margin:0 0 12px;line-height:1.5"><?php echo esc_html($ad['text']); ?></p><?php endif; ?>
                        <?php if ( ! empty($ad['link']) && ! empty($ad['cta']) ) : ?>
                        <a href="<?php echo esc_url($ad['link']); ?>" target="_blank" rel="noopener" class="button button-primary" style="width:100%;text-align:center;box-sizing:border-box"><?php echo esc_html($ad['cta']); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            $('input[name="<?php echo AAG_OPTION; ?>[provider]"]').on('change', function(){
                $('.aag-provider-card').removeClass('active');
                $(this).closest('.aag-provider-card').addClass('active');
                $('.aag-provider-fields').hide();
                $('.aag-provider-fields[data-provider="'+$(this).val()+'"]').show();
            });
            $(document).on('click','.aag-toggle-key',function(){
                var inp=$(this).prev('input'), vis=inp.attr('type')==='text';
                inp.attr('type',vis?'password':'text');
                $(this).text(vis?'Anzeigen':'Verbergen');
            });
            $('.aag-reset-prompt').on('click',function(){
                if(confirm('Standard-Prompt wiederherstellen?')) $('.aag-prompt-editor').val($(this).data('default'));
            });
            function toggleAdRows(t){ $('#aag-ad-row-image').toggle(t==='image'); $('#aag-ad-row-html').toggle(t==='html'); }
            toggleAdRows($('input[name="<?php echo AAG_OPTION; ?>[ad_type]"]:checked').val());
            $('input[name="<?php echo AAG_OPTION; ?>[ad_type]"]').on('change',function(){ toggleAdRows($(this).val()); });
            $('#aag-upload-ad-btn').on('click',function(){
                var frame=wp.media({title:'Anzeigenbild waehlen',button:{text:'Bild verwenden'},multiple:false});
                frame.on('select',function(){ $('#aag_ad_image_url').val(frame.state().get('selection').first().toJSON().url); });
                frame.open();
            });
        });
        </script>
        <?php
    }
}
