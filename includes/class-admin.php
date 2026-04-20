<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Admin {

    // Plugin-eigene Werbeanzeige — hier kannst du deine eigene Ad hinterlegen
    // die Nutzern des Plugins im Dashboard angezeigt wird.
    const PLUGIN_AD = [
        'image'   => '', // URL zu deinem Werbebild — z.B. 'https://deine-domain.de/banner.jpg'
        'link'    => '', // Ziel-URL
        'title'   => 'Mehr von uns',
        'text'    => 'Entdecke unsere anderen WordPress-Plugins für noch mehr SEO-Power.',
        'cta'     => 'Jetzt entdecken →',
    ];

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init',            [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_dashboard_setup',    [ __CLASS__, 'add_dashboard_widget' ] );
    }

    public static function add_menu() {
        add_menu_page(
            'AI Alt-Text Generator',
            'AI Alt-Text',
            'manage_options',
            'ai-alt-generator',
            [ __CLASS__, 'render_page' ],
            'dashicons-format-image',
            80
        );
        add_submenu_page(
            'ai-alt-generator',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'ai-alt-generator',
            [ __CLASS__, 'render_page' ]
        );
        add_submenu_page(
            'ai-alt-generator',
            'Bulk-Generator',
            'Bulk-Generator',
            'manage_options',
            'ai-alt-bulk',
            [ 'AAG_Bulk', 'render_page' ]
        );
        add_submenu_page(
            'ai-alt-generator',
            'Statistik',
            'Statistik',
            'manage_options',
            'ai-alt-stats',
            [ 'AAG_Stats', 'render_page' ]
        );
        add_submenu_page(
            'ai-alt-generator',
            'Bild-Verwendung',
            'Bild-Verwendung',
            'manage_options',
            'ai-alt-usage',
            [ 'AAG_Usage_Tracker', 'render_page' ]
        );
    }

    public static function register_settings() {
        register_setting( 'aag_settings_group', AAG_OPTION, [
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
        ] );
    }

    public static function sanitize( $in ): array {
        $out = [];
        $out['provider']       = in_array( $in['provider'] ?? '', ['gemini','openai','claude'] ) ? $in['provider'] : 'gemini';
        $out['prompt']         = sanitize_textarea_field( $in['prompt'] ?? AAG_Alt_Generator::default_prompt() );
        $out['gemini_key']     = sanitize_text_field( $in['gemini_key']     ?? '' );
        $out['gemini_model']   = sanitize_text_field( $in['gemini_model']   ?? 'gemini-2.5-flash' );
        $out['openai_key']     = sanitize_text_field( $in['openai_key']     ?? '' );
        $out['openai_model']   = sanitize_text_field( $in['openai_model']   ?? 'gpt-4o-mini' );
        $out['claude_key']     = sanitize_text_field( $in['claude_key']     ?? '' );
        $out['claude_model']   = sanitize_text_field( $in['claude_model']   ?? 'claude-haiku-4-5-20251001' );
        $out['ad_type']        = in_array( $in['ad_type'] ?? '', ['image','html'] ) ? $in['ad_type'] : 'image';
        $out['ad_image_url']   = esc_url_raw( $in['ad_image_url']  ?? '' );
        $out['ad_html']        = wp_kses_post( $in['ad_html']       ?? '' );
        $out['ad_link']        = esc_url_raw( $in['ad_link']        ?? '' );
        $out['ad_popup_delay'] = intval( $in['ad_popup_delay']      ?? 0 );
        $out['language']       = sanitize_text_field( $in['language'] ?? 'auto' );
        return $out;
    }

    public static function enqueue_assets( $hook ) {
        if ( ! in_array( $hook, [ 'toplevel_page_ai-alt-generator', 'index.php', 'ai-alt-text_page_ai-alt-bulk', 'ai-alt-text_page_ai-alt-stats', 'ai-alt-text_page_ai-alt-usage', 'upload.php' ] ) ) return;
        wp_enqueue_style(  'aag-admin', AAG_URL . 'assets/admin.css', [], AAG_VERSION );
        if ( $hook === 'toplevel_page_ai-alt-generator' ) {
            wp_enqueue_media();
            wp_enqueue_script( 'aag-admin', AAG_URL . 'assets/admin.js', [ 'jquery' ], AAG_VERSION, true );
        }
    }

    // ── Dashboard-Widget (Plugin-eigene Werbung für Nutzer) ──
    public static function add_dashboard_widget() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        wp_add_dashboard_widget(
            'aag_dashboard_widget',
            '✨ AI Alt-Text Generator',
            [ __CLASS__, 'render_dashboard_widget' ]
        );
    }

    public static function render_dashboard_widget() {
        $ad   = self::PLUGIN_AD;
        $opts = get_option( AAG_OPTION, [] );
        $providers = [ 'gemini' => 'gemini_key', 'openai' => 'openai_key', 'claude' => 'claude_key' ];
        $provider  = $opts['provider'] ?? 'gemini';
        $has_key   = ! empty( $opts[ $providers[ $provider ] ?? 'gemini_key' ] );
        $names     = [ 'gemini' => 'Google Gemini', 'openai' => 'OpenAI', 'claude' => 'Claude' ];
        ?>
        <div class="aag-dw-wrap">

            <!-- Status -->
            <div class="aag-dw-status <?php echo $has_key ? 'ok' : 'warn'; ?>">
                <?php if ( $has_key ) : ?>
                    ✓ Aktiv — <?php echo esc_html( $names[ $provider ] ?? $provider ); ?>
                <?php else : ?>
                    ⚠ API-Key fehlt —
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-alt-generator' ) ); ?>">Jetzt einrichten</a>
                <?php endif; ?>
            </div>

            <!-- Stats-Vorschau -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px">
                <?php
                $dw_total  = AAG_Stats::get_total();
                $dw_today  = AAG_Stats::get_today();
                $dw_last30 = AAG_Stats::get_last_30_days_total();
                $dw_stats  = [ 'Gesamt' => $dw_total, 'Heute' => $dw_today, '30 Tage' => $dw_last30 ];
                foreach ( $dw_stats as $label => $val ) : ?>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;text-align:center">
                    <span style="display:block;font-size:22px;font-weight:700;color:#1e293b"><?php echo number_format($val); ?></span>
                    <span style="font-size:11px;color:#94a3b8;text-transform:uppercase"><?php echo $label; ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Kurzanleitung -->
            <ul class="aag-dw-list">
                <li>📁 <strong>Medienbibliothek</strong> → Bild öffnen → <em>Alt-Text generieren</em></li>
                <li>🖊️ <strong>Block-Editor</strong> → Bild-Block auswählen → Button klicken</li>
                <li>🌐 Shortcode <code>[aag_preview]</code> für das Frontend</li>
                <li>⚡ <a href="<?php echo esc_url( admin_url('admin.php?page=ai-alt-bulk') ); ?>">Bulk-Generator</a> → alle Bilder auf einmal</li>
                <li>📍 <a href="<?php echo esc_url( admin_url('admin.php?page=ai-alt-usage') ); ?>">Bild-Verwendung</a> → wo wird welches Bild genutzt</li>
            </ul>

            <!-- Plugin-eigene Werbung -->
            <?php if ( ! empty( $ad['link'] ) || ! empty( $ad['text'] ) ) : ?>
            <div class="aag-dw-ad">
                <?php if ( ! empty( $ad['image'] ) ) : ?>
                    <a href="<?php echo esc_url( $ad['link'] ); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo esc_url( $ad['image'] ); ?>" alt="<?php echo esc_attr( $ad['title'] ); ?>" class="aag-dw-ad-img">
                    </a>
                <?php endif; ?>
                <div class="aag-dw-ad-body">
                    <?php if ( ! empty( $ad['title'] ) ) : ?>
                        <strong class="aag-dw-ad-title"><?php echo esc_html( $ad['title'] ); ?></strong>
                    <?php endif; ?>
                    <?php if ( ! empty( $ad['text'] ) ) : ?>
                        <p class="aag-dw-ad-text"><?php echo esc_html( $ad['text'] ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $ad['link'] ) && ! empty( $ad['cta'] ) ) : ?>
                        <a href="<?php echo esc_url( $ad['link'] ); ?>" target="_blank" rel="noopener" class="aag-dw-ad-cta">
                            <?php echo esc_html( $ad['cta'] ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-alt-generator' ) ); ?>" class="aag-dw-settings-link">
                ⚙️ Plugin-Einstellungen öffnen
            </a>
        </div>
        <?php
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );
        $opts     = get_option( AAG_OPTION, [] );
        $provider = $opts['provider'] ?? 'gemini';

        $providers = [
            'gemini' => [
                'label'      => 'Google Gemini',
                'desc'       => 'Empfohlen — kostenlos nutzbar',
                'color'      => '#4285f4',
                'models'     => [
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash ⭐',
                    'gemini-2.5-pro'   => 'Gemini 2.5 Pro',
                    'gemini-2.0-flash' => 'Gemini 2.0 Flash',
                ],
                'key_name'   => 'gemini_key',
                'model_name' => 'gemini_model',
                'key_hint'   => 'aistudio.google.com/app/apikey',
                'key_prefix' => 'AIza...',
            ],
            'openai' => [
                'label'      => 'OpenAI',
                'desc'       => 'GPT-4o — sehr präzise',
                'color'      => '#10a37f',
                'models'     => [
                    'gpt-4o-mini' => 'GPT-4o mini (günstig)',
                    'gpt-4o'      => 'GPT-4o (leistungsstark)',
                ],
                'key_name'   => 'openai_key',
                'model_name' => 'openai_model',
                'key_hint'   => 'platform.openai.com/api-keys',
                'key_prefix' => 'sk-...',
            ],
            'claude' => [
                'label'      => 'Anthropic Claude',
                'desc'       => 'Claude Haiku — schnell',
                'color'      => '#cc785c',
                'models'     => [
                    'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (günstig)',
                    'claude-sonnet-4-5'         => 'Claude Sonnet 4.5',
                    'claude-opus-4-5'           => 'Claude Opus 4.5',
                ],
                'key_name'   => 'claude_key',
                'model_name' => 'claude_model',
                'key_hint'   => 'console.anthropic.com',
                'key_prefix' => 'sk-ant-...',
            ],
        ];
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-format-image"></span>
                AI Alt-Text Generator
            </h1>
            <?php settings_errors(); ?>

            <div class="aag-layout">
                <div class="aag-main">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'aag_settings_group' ); ?>

                        <!-- ANBIETER -->
                        <div class="aag-card">
                            <h2>🔌 AI-Anbieter wählen</h2>
                            <div class="aag-provider-grid">
                                <?php foreach ( $providers as $key => $p ) : ?>
                                <label class="aag-provider-card <?php echo $provider === $key ? 'active' : ''; ?>"
                                       style="--provider-color:<?php echo esc_attr( $p['color'] ); ?>">
                                    <input type="radio" name="<?php echo AAG_OPTION; ?>[provider]"
                                           value="<?php echo esc_attr( $key ); ?>" <?php checked( $provider, $key ); ?>>
                                    <span class="aag-provider-name"><?php echo esc_html( $p['label'] ); ?></span>
                                    <span class="aag-provider-desc"><?php echo esc_html( $p['desc'] ); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php foreach ( $providers as $key => $p ) : ?>
                            <div class="aag-provider-fields" data-provider="<?php echo esc_attr( $key ); ?>"
                                 style="<?php echo $provider !== $key ? 'display:none' : ''; ?>">
                                <table class="form-table">
                                    <tr>
                                        <th><label>API-Key</label></th>
                                        <td>
                                            <div class="aag-key-row">
                                                <input type="password"
                                                       name="<?php echo AAG_OPTION; ?>[<?php echo $p['key_name']; ?>]"
                                                       value="<?php echo esc_attr( $opts[ $p['key_name'] ] ?? '' ); ?>"
                                                       class="regular-text"
                                                       placeholder="<?php echo esc_attr( $p['key_prefix'] ); ?>"
                                                       autocomplete="off">
                                                <button type="button" class="button aag-toggle-key">👁</button>
                                            </div>
                                            <p class="description">Key holen:
                                                <a href="https://<?php echo esc_html( $p['key_hint'] ); ?>" target="_blank">
                                                    <?php echo esc_html( $p['key_hint'] ); ?>
                                                </a>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label>Modell</label></th>
                                        <td>
                                            <select name="<?php echo AAG_OPTION; ?>[<?php echo $p['model_name']; ?>]">
                                                <?php foreach ( $p['models'] as $mval => $mlabel ) : ?>
                                                <option value="<?php echo esc_attr( $mval ); ?>" <?php selected( $opts[ $p['model_name'] ] ?? '', $mval ); ?>>
                                                    <?php echo esc_html( $mlabel ); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- SPRACHE + PROMPT -->
                        <div class="aag-card">
                            <h2>🌐 Sprache & Prompt</h2>
                            <table class="form-table" style="margin-bottom:0">
                                <tr>
                                    <th><label for="aag_language">Alt-Text Sprache</label></th>
                                    <td>
                                        <select name="<?php echo AAG_OPTION; ?>[language]" id="aag_language" class="regular-text">
                                            <?php
                                            $languages = [
                                                'auto' => 'Automatisch (Website-Sprache)',
                                                'de'   => '🇩🇪 Deutsch',
                                                'en'   => '🇬🇧 English',
                                                'fr'   => '🇫🇷 Français',
                                                'es'   => '🇪🇸 Español',
                                                'it'   => '🇮🇹 Italiano',
                                                'nl'   => '🇳🇱 Nederlands',
                                                'pt'   => '🇵🇹 Português',
                                                'pl'   => '🇵🇱 Polski',
                                                'tr'   => '🇹🇷 Türkçe',
                                                'ar'   => '🇸🇦 العربية',
                                                'zh'   => '🇨🇳 中文',
                                                'ja'   => '🇯🇵 日本語',
                                            ];
                                            $current_lang = $opts['language'] ?? 'auto';
                                            foreach ( $languages as $code => $label ) :
                                            ?>
                                                <option value="<?php echo esc_attr($code); ?>" <?php selected($current_lang, $code); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">In welcher Sprache soll der Alt-Text generiert werden?</p>
                                    </td>
                                </tr>
                            </table>
                            <hr style="border:none;border-top:1px solid #f1f5f9;margin:16px 0">
                            <p class="description">Anweisung die bei jeder Analyse an die KI gesendet wird.</p>
                            <textarea name="<?php echo AAG_OPTION; ?>[prompt]"
                                      rows="10" class="large-text code aag-prompt-editor"
                            ><?php echo esc_textarea( $opts['prompt'] ?? AAG_Alt_Generator::default_prompt() ); ?></textarea>
                            <div style="display:flex;gap:10px;align-items:center;margin-top:8px">
                                <button type="button" class="button aag-reset-prompt"
                                        data-default="<?php echo esc_attr( AAG_Alt_Generator::default_prompt() ); ?>">
                                    ↺ Standard wiederherstellen
                                </button>
                                <span style="font-size:12px;color:#94a3b8">
                                    Tipp: Schreibe <code>{language}</code> im Prompt — wird durch die gewählte Sprache ersetzt.
                                </span>
                            </div>
                        </div>

                        <!-- POPUP-AD EINSTELLUNGEN -->
                        <div class="aag-card">
                            <h2>📢 Popup-Werbeanzeige (Shortcode Frontend)</h2>
                            <p class="description">
                                Beim Klick auf <em>„Alt-Text generieren"</em> im Shortcode <code>[aag_preview]</code>
                                öffnet sich ein Popup mit dieser Anzeige — solange die KI das Bild analysiert.
                            </p>
                            <table class="form-table">
                                <tr>
                                    <th>Popup-Verhalten</th>
                                    <td>
                                        <label>
                                            Automatisch schließen nach
                                            <input type="number"
                                                   name="<?php echo AAG_OPTION; ?>[ad_popup_delay]"
                                                   value="<?php echo intval( $opts['ad_popup_delay'] ?? 0 ); ?>"
                                                   min="0" max="60" style="width:60px;margin:0 6px">
                                            Sekunden
                                        </label>
                                        <p class="description">0 = Popup bleibt bis die Analyse fertig ist (empfohlen)</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Anzeigen-Typ</th>
                                    <td>
                                        <fieldset>
                                            <label><input type="radio" name="<?php echo AAG_OPTION; ?>[ad_type]" value="image" <?php checked( $opts['ad_type'] ?? 'image', 'image' ); ?>> Bild-Anzeige</label>
                                            &nbsp;&nbsp;
                                            <label><input type="radio" name="<?php echo AAG_OPTION; ?>[ad_type]" value="html"  <?php checked( $opts['ad_type'] ?? 'image', 'html' ); ?>> HTML / Code (z.B. AdSense)</label>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr id="aag-ad-row-image">
                                    <th><label>Anzeigen-Bild</label></th>
                                    <td>
                                        <div class="aag-key-row">
                                            <input type="url" id="aag_ad_image_url"
                                                   name="<?php echo AAG_OPTION; ?>[ad_image_url]"
                                                   value="<?php echo esc_url( $opts['ad_image_url'] ?? '' ); ?>"
                                                   class="regular-text" placeholder="https://...">
                                            <button type="button" class="button" id="aag-upload-ad-btn">📁 Bild wählen</button>
                                        </div>
                                        <?php if ( ! empty( $opts['ad_image_url'] ) ) : ?>
                                        <div style="margin-top:10px">
                                            <img src="<?php echo esc_url( $opts['ad_image_url'] ); ?>"
                                                 style="max-width:280px;border-radius:8px;border:1px solid #e2e8f0">
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label>Anzeigen-Link</label></th>
                                    <td>
                                        <input type="url" name="<?php echo AAG_OPTION; ?>[ad_link]"
                                               value="<?php echo esc_url( $opts['ad_link'] ?? '' ); ?>"
                                               class="regular-text" placeholder="https://...">
                                        <p class="description">Wohin führt ein Klick auf die Anzeige?</p>
                                    </td>
                                </tr>
                                <tr id="aag-ad-row-html">
                                    <th><label>HTML-Code</label></th>
                                    <td>
                                        <textarea name="<?php echo AAG_OPTION; ?>[ad_html]"
                                                  rows="5" class="large-text code"
                                                  placeholder='<script async src="https://pagead2..."></script>'
                                        ><?php echo esc_textarea( $opts['ad_html'] ?? '' ); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <p class="submit">
                            <?php submit_button( 'Einstellungen speichern', 'primary large', 'submit', false ); ?>
                        </p>
                    </form>
                </div>

                <!-- SIDEBAR -->
                <div class="aag-sidebar">

                    <!-- Plugin-eigene Werbung im Admin-Sidebar -->
                    <?php $ad = self::PLUGIN_AD; ?>
                    <?php if ( ! empty( $ad['text'] ) || ! empty( $ad['image'] ) ) : ?>
                    <div class="aag-card aag-card-plugin-ad">
                        <?php if ( ! empty( $ad['image'] ) ) : ?>
                            <a href="<?php echo esc_url( $ad['link'] ); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url( $ad['image'] ); ?>" alt="<?php echo esc_attr( $ad['title'] ); ?>"
                                     style="width:100%;border-radius:8px;display:block;margin-bottom:12px">
                            </a>
                        <?php endif; ?>
                        <?php if ( ! empty( $ad['title'] ) ) : ?>
                            <strong style="display:block;font-size:14px;margin-bottom:6px"><?php echo esc_html( $ad['title'] ); ?></strong>
                        <?php endif; ?>
                        <?php if ( ! empty( $ad['text'] ) ) : ?>
                            <p style="font-size:13px;color:#475569;margin:0 0 12px"><?php echo esc_html( $ad['text'] ); ?></p>
                        <?php endif; ?>
                        <?php if ( ! empty( $ad['link'] ) && ! empty( $ad['cta'] ) ) : ?>
                            <a href="<?php echo esc_url( $ad['link'] ); ?>" target="_blank" rel="noopener" class="button button-primary" style="width:100%;text-align:center">
                                <?php echo esc_html( $ad['cta'] ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="aag-card">
                        <h3>🚀 Verwendung</h3>
                        <ul>
                            <li>In der <strong>Medienbibliothek</strong> beim Bearbeiten eines Bildes</li>
                            <li>Im <strong>Block-Editor</strong> bei jedem Bild-Block</li>
                            <li>Im <strong>Media-Upload-Modal</strong></li>
                        </ul>
                        <hr style="border:none;border-top:1px solid #e2e8f0;margin:12px 0">
                        <p style="font-size:13px;color:#475569"><strong>Frontend-Shortcode:</strong></p>
                        <code style="display:block;background:#f1f5f9;padding:8px 10px;border-radius:6px;font-size:13px">[aag_preview]</code>
                    </div>

                    <div class="aag-card">
                        <h3>ℹ️ Aktiver Anbieter</h3>
                        <?php
                        $p       = $providers[ $provider ];
                        $has_key = ! empty( $opts[ $p['key_name'] ] );
                        ?>
                        <div class="aag-status-badge <?php echo $has_key ? 'ok' : 'warn'; ?>">
                            <?php echo $has_key ? '✓' : '⚠'; ?>
                            <?php echo esc_html( $p['label'] ); ?>
                            — <?php echo $has_key ? 'Key gesetzt' : 'Key fehlt!'; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            // Anbieter-Toggle
            $('input[name="<?php echo AAG_OPTION; ?>[provider]"]').on('change', function(){
                $('.aag-provider-card').removeClass('active');
                $(this).closest('.aag-provider-card').addClass('active');
                $('.aag-provider-fields').hide();
                $('.aag-provider-fields[data-provider="'+$(this).val()+'"]').show();
            });
            // Key anzeigen/verstecken
            $(document).on('click', '.aag-toggle-key', function(){
                var inp = $(this).prev('input');
                inp.attr('type', inp.attr('type') === 'password' ? 'text' : 'password');
            });
            // Prompt reset
            $('.aag-reset-prompt').on('click', function(){
                if (confirm('Standard-Prompt wiederherstellen?'))
                    $('.aag-prompt-editor').val($(this).data('default'));
            });
            // Ad-Typ Toggle
            function toggleAdRows(type){
                $('#aag-ad-row-image').toggle(type === 'image');
                $('#aag-ad-row-html').toggle(type === 'html');
            }
            var adType = $('input[name="<?php echo AAG_OPTION; ?>[ad_type]"]:checked').val();
            toggleAdRows(adType);
            $('input[name="<?php echo AAG_OPTION; ?>[ad_type]"]').on('change', function(){ toggleAdRows($(this).val()); });
            // Medien-Upload für Ad-Bild
            $('#aag-upload-ad-btn').on('click', function(){
                var frame = wp.media({ title: 'Anzeigenbild wählen', button: { text: 'Verwenden' }, multiple: false });
                frame.on('select', function(){
                    $('#aag_ad_image_url').val(frame.state().get('selection').first().toJSON().url);
                });
                frame.open();
            });
        });
        </script>
        <?php
    }
}
