<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init',            [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function add_menu() {
        add_menu_page(
            __( 'AI Alt-Text Generator', 'ai-alt-gen' ),
            __( 'AI Alt-Text', 'ai-alt-gen' ),
            'manage_options',
            'ai-alt-generator',
            [ __CLASS__, 'render_page' ],
            'dashicons-format-image',
            80
        );
    }

    public static function register_settings() {
        register_setting( 'aag_settings_group', AAG_OPTION, [
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
        ] );
    }

    public static function sanitize( $in ): array {
        $out = [];

        $out['provider']     = in_array( $in['provider'] ?? '', ['gemini','openai','claude'] ) ? $in['provider'] : 'gemini';
        $out['prompt']       = sanitize_textarea_field( $in['prompt'] ?? AAG_Alt_Generator::default_prompt() );

        // Gemini
        $out['gemini_key']   = sanitize_text_field( $in['gemini_key'] ?? '' );
        $out['gemini_model'] = sanitize_text_field( $in['gemini_model'] ?? 'gemini-2.5-flash' );

        // OpenAI
        $out['openai_key']   = sanitize_text_field( $in['openai_key'] ?? '' );
        $out['openai_model'] = sanitize_text_field( $in['openai_model'] ?? 'gpt-4o-mini' );

        // Claude
        $out['claude_key']   = sanitize_text_field( $in['claude_key'] ?? '' );
        $out['claude_model'] = sanitize_text_field( $in['claude_model'] ?? 'claude-haiku-4-5-20251001' );

        // Legacy (Vorschau – wird nur gelesen, nicht mehr aktiv verwendet)
        $out['ad_type']      = in_array( $in['ad_type'] ?? '', ['image','html'] ) ? $in['ad_type'] : 'image';
        $out['ad_image_url'] = esc_url_raw( $in['ad_image_url'] ?? '' );
        $out['ad_html']      = wp_kses_post( $in['ad_html'] ?? '' );
        $out['ad_link']      = esc_url_raw( $in['ad_link'] ?? '' );

        return $out;
    }

    public static function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_ai-alt-generator' ) return;
        wp_enqueue_style(  'aag-admin', AAG_URL . 'assets/admin.css', [], AAG_VERSION );
        wp_enqueue_script( 'aag-admin', AAG_URL . 'assets/admin.js',  [ 'jquery' ], AAG_VERSION, true );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );
        $opts = get_option( AAG_OPTION, [] );
        $provider = $opts['provider'] ?? 'gemini';
        ?>
        <div class="wrap aag-wrap">
            <h1 class="aag-page-title">
                <span class="dashicons dashicons-format-image"></span>
                AI Alt-Text Generator
            </h1>

            <?php settings_errors(); ?>

            <div class="aag-layout">

                <!-- ══ LINKE SPALTE: EINSTELLUNGEN ══ -->
                <div class="aag-main">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'aag_settings_group' ); ?>

                        <!-- SEKTION: ANBIETER WÄHLEN -->
                        <div class="aag-card">
                            <h2><?php esc_html_e( '🔌 AI-Anbieter wählen', 'ai-alt-gen' ); ?></h2>
                            <div class="aag-provider-grid">

                                <?php
                                $providers = [
                                    'gemini' => [
                                        'label'    => 'Google Gemini',
                                        'desc'     => 'Empfohlen — kostenlos nutzbar',
                                        'color'    => '#4285f4',
                                        'models'   => [
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
                                        'label'    => 'OpenAI',
                                        'desc'     => 'GPT-4o — sehr präzise',
                                        'color'    => '#10a37f',
                                        'models'   => [
                                            'gpt-4o-mini' => 'GPT-4o mini (günstig)',
                                            'gpt-4o'      => 'GPT-4o (leistungsstark)',
                                        ],
                                        'key_name'   => 'openai_key',
                                        'model_name' => 'openai_model',
                                        'key_hint'   => 'platform.openai.com/api-keys',
                                        'key_prefix' => 'sk-...',
                                    ],
                                    'claude' => [
                                        'label'    => 'Anthropic Claude',
                                        'desc'     => 'Claude Haiku — schnell',
                                        'color'    => '#cc785c',
                                        'models'   => [
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
                                foreach ( $providers as $key => $p ) :
                                    $active = ( $provider === $key );
                                ?>
                                <label class="aag-provider-card <?php echo $active ? 'active' : ''; ?>"
                                       style="--provider-color: <?php echo esc_attr( $p['color'] ); ?>">
                                    <input type="radio" name="<?php echo AAG_OPTION; ?>[provider]"
                                           value="<?php echo esc_attr( $key ); ?>"
                                           <?php checked( $provider, $key ); ?>>
                                    <span class="aag-provider-name"><?php echo esc_html( $p['label'] ); ?></span>
                                    <span class="aag-provider-desc"><?php echo esc_html( $p['desc'] ); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <!-- API-Key + Modell je Anbieter -->
                            <?php foreach ( $providers as $key => $p ) : ?>
                            <div class="aag-provider-fields" data-provider="<?php echo esc_attr( $key ); ?>"
                                 style="<?php echo $provider !== $key ? 'display:none' : ''; ?>">
                                <table class="form-table">
                                    <tr>
                                        <th><label><?php esc_html_e( 'API-Key', 'ai-alt-gen' ); ?></label></th>
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
                                            <p class="description">
                                                Key holen: <a href="https://<?php echo esc_html( $p['key_hint'] ); ?>" target="_blank">
                                                    <?php echo esc_html( $p['key_hint'] ); ?>
                                                </a>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php esc_html_e( 'Modell', 'ai-alt-gen' ); ?></label></th>
                                        <td>
                                            <select name="<?php echo AAG_OPTION; ?>[<?php echo $p['model_name']; ?>]">
                                                <?php foreach ( $p['models'] as $mval => $mlabel ) : ?>
                                                    <option value="<?php echo esc_attr( $mval ); ?>"
                                                        <?php selected( $opts[ $p['model_name'] ] ?? '', $mval ); ?>>
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

                        <!-- SEKTION: PROMPT -->
                        <div class="aag-card">
                            <h2><?php esc_html_e( '💬 Standard-Prompt', 'ai-alt-gen' ); ?></h2>
                            <p class="description">
                                Dieser Prompt wird an jede Bild-Analyse gesendet. Du kannst ihn für deine Website anpassen.
                            </p>
                            <textarea name="<?php echo AAG_OPTION; ?>[prompt]"
                                      rows="10" class="large-text code aag-prompt-editor"
                            ><?php echo esc_textarea( $opts['prompt'] ?? AAG_Alt_Generator::default_prompt() ); ?></textarea>
                            <button type="button" class="button aag-reset-prompt" data-default="<?php echo esc_attr( AAG_Alt_Generator::default_prompt() ); ?>">
                                ↺ Standard wiederherstellen
                            </button>
                        </div>

                        <!-- SEKTION: LEGACY (Vorschau alter Einstellungen) -->
                        <div class="aag-card aag-card-legacy">
                            <h2>📦 Legacy — Anzeigen-Einstellungen <span class="aag-badge">Vorschau</span></h2>
                            <p class="description">
                                Diese Einstellungen stammen aus der vorherigen Version (v1.x) und werden zur Zeit nicht aktiv genutzt.
                                Sie bleiben erhalten und können jederzeit wieder aktiviert werden.
                            </p>
                            <table class="form-table">
                                <tr>
                                    <th>Anzeigen-Typ</th>
                                    <td>
                                        <fieldset>
                                            <label><input type="radio" name="<?php echo AAG_OPTION; ?>[ad_type]" value="image" <?php checked( $opts['ad_type'] ?? 'image', 'image' ); ?>> Bild</label>
                                            &nbsp;&nbsp;
                                            <label><input type="radio" name="<?php echo AAG_OPTION; ?>[ad_type]" value="html"  <?php checked( $opts['ad_type'] ?? 'image', 'html' ); ?>> HTML / Code</label>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label>Anzeigen-Bild URL</label></th>
                                    <td><input type="url" name="<?php echo AAG_OPTION; ?>[ad_image_url]"
                                               value="<?php echo esc_url( $opts['ad_image_url'] ?? '' ); ?>"
                                               class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label>Anzeigen-Link</label></th>
                                    <td><input type="url" name="<?php echo AAG_OPTION; ?>[ad_link]"
                                               value="<?php echo esc_url( $opts['ad_link'] ?? '' ); ?>"
                                               class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label>HTML-Code</label></th>
                                    <td><textarea name="<?php echo AAG_OPTION; ?>[ad_html]"
                                                  rows="4" class="large-text code"><?php echo esc_textarea( $opts['ad_html'] ?? '' ); ?></textarea></td>
                                </tr>
                            </table>
                        </div>

                        <p class="submit">
                            <?php submit_button( 'Einstellungen speichern', 'primary large', 'submit', false ); ?>
                        </p>
                    </form>
                </div>

                <!-- ══ RECHTE SPALTE: ANLEITUNG & SHORTCODE ══ -->
                <div class="aag-sidebar">

                    <div class="aag-card">
                        <h3>🚀 Verwendung</h3>
                        <p>Der Button erscheint automatisch:</p>
                        <ul>
                            <li>In der <strong>Medienbibliothek</strong> beim Bearbeiten eines Bildes</li>
                            <li>Im <strong>Block-Editor</strong> bei jedem Bild-Block</li>
                            <li>Im <strong>Media-Upload-Modal</strong></li>
                        </ul>
                    </div>

                    <div class="aag-card">
                        <h3>🧪 Schnelltest</h3>
                        <p>Gehe zu <strong>Medien → Bibliothek</strong>, wähle ein Bild und klicke auf <em>"Alt-Text generieren"</em>.</p>
                        <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button button-secondary">
                            → Medienbibliothek öffnen
                        </a>
                    </div>

                    <div class="aag-card">
                        <h3>ℹ️ Aktiver Anbieter</h3>
                        <?php
                        $p = $providers[ $provider ];
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
        <?php
    }
}
