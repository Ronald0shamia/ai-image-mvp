<?php
/**
 * Plugin Name: AI Image Analyzer
 * Plugin URI:  https://example.com
 * Description: Analysiert Bilder mit Google Gemini AI und zeigt während der Analyse eine Werbeanzeige.
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// TEIL 1 — ADMIN / BACKEND
// ============================================================

class AIA_Admin {

    const OPTION_KEY = 'aia_settings';

    public static function init() {
        add_action( 'admin_menu',    [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init',    [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
    }

    public static function add_menu() {
        add_options_page(
            'AI Image Analyzer',
            'AI Image Analyzer',
            'manage_options',
            'ai-image-analyzer',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function register_settings() {
        register_setting(
            'aia_settings_group',
            self::OPTION_KEY,
            [ 'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ] ]
        );
    }

    public static function sanitize_settings( $input ) {
        $clean = [];
        $clean['api_key']       = sanitize_text_field( $input['api_key'] ?? '' );
        $clean['ai_model']      = sanitize_text_field( $input['ai_model'] ?? 'gemini-2.5-flash' );
        $clean['system_prompt'] = sanitize_textarea_field( $input['system_prompt'] ?? '' );
        $clean['ad_type']       = in_array( $input['ad_type'] ?? '', ['image','html'] ) ? $input['ad_type'] : 'image';
        $clean['ad_image_url']  = esc_url_raw( $input['ad_image_url'] ?? '' );
        $clean['ad_html']       = wp_kses_post( $input['ad_html'] ?? '' );
        $clean['ad_link']       = esc_url_raw( $input['ad_link'] ?? '' );
        return $clean;
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'settings_page_ai-image-analyzer' ) return;
        // Media-Uploader für Ad-Bild
        wp_enqueue_media();
        wp_enqueue_script( 'aia-admin', plugin_dir_url(__FILE__) . 'aia-admin.js', ['jquery'], '1.0', true );
    }

    public static function get_setting( $key, $default = '' ) {
        $options = get_option( self::OPTION_KEY, [] );
        return $options[ $key ] ?? $default;
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }
        $opts = get_option( self::OPTION_KEY, [] );
        ?>
        <div class="wrap">
            <h1>🤖 AI Image Analyzer — Einstellungen</h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'aia_settings_group' ); ?>

                <!-- ── SEKTION: API ── -->
                <h2 class="title">🔑 API-Einstellungen</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="aia_api_key">API Key</label></th>
                        <td>
                            <input
                                type="password"
                                id="aia_api_key"
                                name="aia_settings[api_key]"
                                value="<?php echo esc_attr( $opts['api_key'] ?? '' ); ?>"
                                class="regular-text"
                                autocomplete="off"
                            />
                            <p class="description">Dein Google AI API-Schlüssel von <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aia_ai_model">AI-Modell</label></th>
                        <td>
                            <select id="aia_ai_model" name="aia_settings[ai_model]">
                                <?php
                                $models = [
                                    'gemini-2.5-flash'   => 'Gemini 2.5 Flash (schnell & günstig) ⭐',
                                    'gemini-2.5-pro'     => 'Gemini 2.5 Pro (leistungsstark)',
                                    'gemini-2.0-flash'   => 'Gemini 2.0 Flash (stabil)',
                                ];
                                $current = $opts['ai_model'] ?? 'gemini-2.5-flash';
                                foreach ( $models as $val => $label ) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($val),
                                        selected( $current, $val, false ),
                                        esc_html($label)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <hr>

                <!-- ── SEKTION: PROMPT ── -->
                <h2 class="title">💬 System-Prompt</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="aia_system_prompt">Analyse-Anweisung</label></th>
                        <td>
                            <textarea
                                id="aia_system_prompt"
                                name="aia_settings[system_prompt]"
                                rows="8"
                                class="large-text"
                            ><?php echo esc_textarea( $opts['system_prompt'] ?? 'Analysiere dieses Bild und beschreibe detailliert, was du siehst. Gehe auf Farben, Objekte, Personen und die allgemeine Stimmung ein.' ); ?></textarea>
                            <p class="description">Dieser Prompt wird bei jeder Bildanalyse als Systemanweisung an die KI gesendet.</p>
                        </td>
                    </tr>
                </table>

                <hr>

                <!-- ── SEKTION: WERBEANZEIGE ── -->
                <h2 class="title">📢 Werbeanzeige (wird während der Analyse gezeigt)</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Anzeigen-Typ</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="aia_settings[ad_type]" value="image"
                                        <?php checked( $opts['ad_type'] ?? 'image', 'image' ); ?> />
                                    Bild-Anzeige
                                </label><br>
                                <label>
                                    <input type="radio" name="aia_settings[ad_type]" value="html"
                                        <?php checked( $opts['ad_type'] ?? 'image', 'html' ); ?> />
                                    HTML / Code (z.B. Google AdSense)
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr id="aia_row_image">
                        <th scope="row"><label for="aia_ad_image_url">Anzeigen-Bild URL</label></th>
                        <td>
                            <input
                                type="url"
                                id="aia_ad_image_url"
                                name="aia_settings[ad_image_url]"
                                value="<?php echo esc_url( $opts['ad_image_url'] ?? '' ); ?>"
                                class="regular-text"
                            />
                            <button type="button" class="button" id="aia_upload_btn">Bild auswählen</button>
                            <br>
                            <?php if ( ! empty( $opts['ad_image_url'] ) ) : ?>
                                <img src="<?php echo esc_url( $opts['ad_image_url'] ); ?>"
                                     style="max-width:300px;margin-top:8px;border:1px solid #ddd;border-radius:4px;">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aia_ad_link">Anzeigen-Link (optional)</label></th>
                        <td>
                            <input
                                type="url"
                                id="aia_ad_link"
                                name="aia_settings[ad_link]"
                                value="<?php echo esc_url( $opts['ad_link'] ?? '' ); ?>"
                                class="regular-text"
                                placeholder="https://example.com"
                            />
                        </td>
                    </tr>
                    <tr id="aia_row_html">
                        <th scope="row"><label for="aia_ad_html">HTML-Code der Anzeige</label></th>
                        <td>
                            <textarea
                                id="aia_ad_html"
                                name="aia_settings[ad_html]"
                                rows="6"
                                class="large-text code"
                                placeholder='<script async src="https://pagead2.googlesyndication.com/..."></script>'
                            ><?php echo esc_textarea( $opts['ad_html'] ?? '' ); ?></textarea>
                        </td>
                    </tr>
                </table>

                <hr>
                <p class="submit">
                    <?php submit_button( 'Einstellungen speichern', 'primary', 'submit', false ); ?>
                </p>

                <h3>Verwendung</h3>
                <p>Füge diesen Shortcode auf einer beliebigen Seite oder in einem Beitrag ein:</p>
                <code style="font-size:14px;padding:8px 16px;background:#f6f7f7;border:1px solid #ddd;display:inline-block;border-radius:4px;">[ai_image_analyzer]</code>

            </form>
        </div>

        <script>
        jQuery(function($){
            // Medien-Uploader
            $('#aia_upload_btn').on('click', function(){
                var frame = wp.media({ title: 'Anzeigen-Bild wählen', button: { text: 'Verwenden' }, multiple: false });
                frame.on('select', function(){
                    var url = frame.state().get('selection').first().toJSON().url;
                    $('#aia_ad_image_url').val(url);
                });
                frame.open();
            });

            // Radiobutton Toggle
            function toggleAdFields(){
                var type = $('input[name="aia_settings[ad_type]"]:checked').val();
                $('#aia_row_image').toggle( type === 'image' );
                $('#aia_row_html').toggle(  type === 'html'  );
            }
            $('input[name="aia_settings[ad_type]"]').on('change', toggleAdFields);
            toggleAdFields();
        });
        </script>
        <?php
    }
}

// ============================================================
// TEIL 2 — FRONTEND
// ============================================================

class AIA_Frontend {

    public static function init() {
        add_shortcode( 'ai_image_analyzer', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts',   [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_aia_analyze',        [ __CLASS__, 'ajax_analyze' ] );
        add_action( 'wp_ajax_nopriv_aia_analyze', [ __CLASS__, 'ajax_analyze' ] );
    }

    public static function enqueue_assets() {
        global $post;
        // Nur laden wenn Shortcode vorhanden
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ai_image_analyzer' ) ) {
            wp_enqueue_style(
                'aia-frontend',
                plugin_dir_url(__FILE__) . 'aia-frontend.css',
                [], '1.0'
            );
        }
    }

    public static function render_shortcode() {
        ob_start();
        $nonce = wp_create_nonce( 'aia_analyze_nonce' );
        $ad_type      = AIA_Admin::get_setting('ad_type', 'image');
        $ad_image_url = AIA_Admin::get_setting('ad_image_url');
        $ad_link      = AIA_Admin::get_setting('ad_link');
        $ad_html      = AIA_Admin::get_setting('ad_html');
        ?>
        <div class="aia-wrapper" id="aia-wrapper">

            <!-- Upload-Bereich -->
            <div class="aia-upload-area" id="aia-upload-area">
                <div class="aia-upload-icon">🖼️</div>
                <p class="aia-upload-label">Bild hier hinziehen oder klicken zum Auswählen</p>
                <input type="file" id="aia-file-input" accept="image/*" style="display:none">
                <div class="aia-preview" id="aia-preview" style="display:none">
                    <img id="aia-preview-img" src="" alt="Vorschau">
                    <button type="button" class="aia-btn-remove" id="aia-btn-remove">✕ Anderes Bild</button>
                </div>
            </div>

            <!-- Analyse-Button -->
            <div class="aia-action">
                <button type="button" class="aia-btn-analyze" id="aia-btn-analyze" disabled>
                    🔍 Bild analysieren
                </button>
            </div>

            <!-- Werbeanzeige (wird während Analyse gezeigt) -->
            <div class="aia-ad-container" id="aia-ad-container" style="display:none">
                <p class="aia-ad-label">Anzeige</p>
                <?php if ( $ad_type === 'image' && $ad_image_url ) : ?>
                    <div class="aia-ad-inner">
                        <?php if ( $ad_link ) : ?>
                            <a href="<?php echo esc_url($ad_link); ?>" target="_blank" rel="noopener">
                        <?php endif; ?>
                        <img src="<?php echo esc_url($ad_image_url); ?>" alt="Werbung" class="aia-ad-img">
                        <?php if ( $ad_link ) : ?></a><?php endif; ?>
                    </div>
                <?php elseif ( $ad_type === 'html' && $ad_html ) : ?>
                    <div class="aia-ad-inner"><?php echo $ad_html; ?></div>
                <?php else : ?>
                    <div class="aia-ad-placeholder">
                        <p>Hier könnte Ihre Werbung stehen</p>
                    </div>
                <?php endif; ?>
                <div class="aia-loader">
                    <div class="aia-spinner"></div>
                    <p>KI analysiert Ihr Bild…</p>
                </div>
            </div>

            <!-- Ergebnis-Bereich -->
            <div class="aia-result" id="aia-result" style="display:none">
                <h3>🤖 Analyseergebnis</h3>
                <div class="aia-result-text" id="aia-result-text"></div>
                <button type="button" class="aia-btn-reset" id="aia-btn-reset">🔄 Neues Bild analysieren</button>
            </div>

            <!-- Fehler-Anzeige -->
            <div class="aia-error" id="aia-error" style="display:none"></div>

        </div>

        <style>
        .aia-wrapper { max-width: 640px; margin: 24px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .aia-upload-area { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px 24px; text-align: center; cursor: pointer; transition: border-color .2s, background .2s; background: #f8fafc; }
        .aia-upload-area:hover, .aia-upload-area.drag-over { border-color: #6366f1; background: #eef2ff; }
        .aia-upload-icon { font-size: 48px; margin-bottom: 12px; }
        .aia-upload-label { color: #64748b; margin: 0; }
        .aia-preview img { max-width: 100%; border-radius: 8px; margin-top: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
        .aia-btn-remove { background: none; border: 1px solid #e2e8f0; color: #64748b; padding: 6px 14px; border-radius: 6px; cursor: pointer; margin-top: 10px; font-size: 13px; }
        .aia-btn-remove:hover { background: #f1f5f9; }
        .aia-action { text-align: center; margin: 20px 0; }
        .aia-btn-analyze { background: #6366f1; color: #fff; border: none; padding: 14px 36px; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background .2s, opacity .2s; }
        .aia-btn-analyze:hover:not(:disabled) { background: #4f46e5; }
        .aia-btn-analyze:disabled { opacity: .45; cursor: not-allowed; }
        .aia-ad-container { border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 20px; background: #fff; }
        .aia-ad-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .08em; margin: 0 0 10px; }
        .aia-ad-img { max-width: 100%; border-radius: 8px; }
        .aia-ad-placeholder { background: #f1f5f9; border-radius: 8px; padding: 40px; color: #94a3b8; }
        .aia-loader { display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 16px; color: #64748b; }
        .aia-spinner { width: 20px; height: 20px; border: 2px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: aia-spin .8s linear infinite; }
        @keyframes aia-spin { to { transform: rotate(360deg); } }
        .aia-result { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 24px; }
        .aia-result h3 { margin: 0 0 12px; color: #15803d; font-size: 18px; }
        .aia-result-text { color: #1e293b; line-height: 1.7; white-space: pre-wrap; }
        .aia-btn-reset { background: none; border: 1px solid #15803d; color: #15803d; padding: 8px 20px; border-radius: 8px; cursor: pointer; margin-top: 16px; font-size: 14px; }
        .aia-btn-reset:hover { background: #dcfce7; }
        .aia-error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 16px 20px; color: #dc2626; }
        </style>

        <script>
        (function(){
            var wrapper     = document.getElementById('aia-wrapper');
            var uploadArea  = document.getElementById('aia-upload-area');
            var fileInput   = document.getElementById('aia-file-input');
            var preview     = document.getElementById('aia-preview');
            var previewImg  = document.getElementById('aia-preview-img');
            var btnRemove   = document.getElementById('aia-btn-remove');
            var btnAnalyze  = document.getElementById('aia-btn-analyze');
            var adContainer = document.getElementById('aia-ad-container');
            var resultBox   = document.getElementById('aia-result');
            var resultText  = document.getElementById('aia-result-text');
            var btnReset    = document.getElementById('aia-btn-reset');
            var errorBox    = document.getElementById('aia-error');
            var selectedFile = null;

            // Klick auf Upload-Bereich
            uploadArea.addEventListener('click', function(e){
                if (e.target === btnRemove || btnRemove.contains(e.target)) return;
                fileInput.click();
            });

            // Drag & Drop
            uploadArea.addEventListener('dragover', function(e){ e.preventDefault(); uploadArea.classList.add('drag-over'); });
            uploadArea.addEventListener('dragleave', function(){ uploadArea.classList.remove('drag-over'); });
            uploadArea.addEventListener('drop', function(e){
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
            });

            fileInput.addEventListener('change', function(){
                if (this.files[0]) handleFile(this.files[0]);
            });

            function handleFile(file) {
                if (!file.type.startsWith('image/')) { showError('Bitte nur Bilddateien hochladen.'); return; }
                selectedFile = file;
                var reader = new FileReader();
                reader.onload = function(e){
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                    btnAnalyze.disabled = false;
                    hideError();
                };
                reader.readAsDataURL(file);
            }

            btnRemove.addEventListener('click', function(e){ e.stopPropagation(); resetAll(); });
            btnReset.addEventListener('click', resetAll);

            function resetAll(){
                selectedFile = null;
                fileInput.value = '';
                previewImg.src = '';
                preview.style.display = 'none';
                btnAnalyze.disabled = true;
                adContainer.style.display = 'none';
                resultBox.style.display = 'none';
                resultText.textContent = '';
                hideError();
            }

            // Analyse starten
            btnAnalyze.addEventListener('click', function(){
                if (!selectedFile) return;
                btnAnalyze.disabled = true;
                adContainer.style.display = 'block';
                resultBox.style.display = 'none';
                hideError();

                // Bild als base64
                var reader = new FileReader();
                reader.onload = function(e){
                    var base64 = e.target.result.split(',')[1];
                    var mimeType = selectedFile.type;

                    // AJAX an WordPress senden
                    var formData = new FormData();
                    formData.append('action', 'aia_analyze');
                    formData.append('nonce', '<?php echo esc_js($nonce); ?>');
                    formData.append('image_data', base64);
                    formData.append('mime_type', mimeType);

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        adContainer.style.display = 'none';
                        if (data.success) {
                            resultText.textContent = data.data.result;
                            resultBox.style.display = 'block';
                        } else {
                            showError(data.data || 'Fehler bei der Analyse.');
                            btnAnalyze.disabled = false;
                        }
                    })
                    .catch(function(){
                        adContainer.style.display = 'none';
                        showError('Verbindungsfehler. Bitte versuche es erneut.');
                        btnAnalyze.disabled = false;
                    });
                };
                reader.readAsDataURL(selectedFile);
            });

            function showError(msg){ errorBox.textContent = msg; errorBox.style.display = 'block'; }
            function hideError(){ errorBox.style.display = 'none'; }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // ── AJAX-Handler: Bild an Google Gemini API senden ──
    public static function ajax_analyze() {
        // Sicherheits-Check
        if ( ! check_ajax_referer( 'aia_analyze_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Ungültige Anfrage.' );
        }

        $api_key       = AIA_Admin::get_setting('api_key');
        $model         = AIA_Admin::get_setting('ai_model', 'gemini-2.5-flash');
        $system_prompt = AIA_Admin::get_setting('system_prompt', 'Analysiere dieses Bild detailliert.');

        if ( empty($api_key) ) {
            wp_send_json_error( 'Kein API-Schlüssel konfiguriert. Bitte im Admin-Bereich eintragen.' );
        }

        $image_data = sanitize_text_field( $_POST['image_data'] ?? '' );
        $mime_type  = sanitize_mime_type( $_POST['mime_type'] ?? 'image/jpeg' );

        if ( empty($image_data) ) {
            wp_send_json_error( 'Kein Bild empfangen.' );
        }

        // Google Gemini API-Aufruf
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            urlencode($model),
            urlencode($api_key)
        );

        $body = [
            'system_instruction' => [
                'parts' => [ [ 'text' => $system_prompt ] ]
            ],
            'contents' => [
                [
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => $mime_type,
                                'data'      => $image_data,
                            ]
                        ],
                        [
                            'text' => 'Bitte analysiere dieses Bild.'
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 1024,
                'temperature'     => 0.4,
            ]
        ];

        $response = wp_remote_post(
            $url,
            [
                'timeout' => 60,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode( $body ),
            ]
        );

        if ( is_wp_error($response) ) {
            wp_send_json_error( 'API-Verbindungsfehler: ' . $response->get_error_message() );
        }

        $code         = wp_remote_retrieve_response_code($response);
        $decoded_body = json_decode( wp_remote_retrieve_body($response), true );

        if ( $code !== 200 ) {
            $msg = $decoded_body['error']['message'] ?? 'Unbekannter API-Fehler (Code ' . $code . ')';
            wp_send_json_error( $msg );
        }

        $result = $decoded_body['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if ( empty($result) ) {
            wp_send_json_error( 'Die KI hat keine Antwort zurückgegeben.' );
        }

        wp_send_json_success( [ 'result' => $result ] );
    }
}

// ============================================================
// PLUGIN BOOTSTRAP
// ============================================================

add_action( 'plugins_loaded', function() {
    AIA_Admin::init();
    AIA_Frontend::init();
} );
