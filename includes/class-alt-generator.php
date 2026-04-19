<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Alt_Generator {

    public static function init() {
        add_action( 'wp_ajax_aag_generate_alt', [ __CLASS__, 'ajax_generate' ] );

        // Button auf der Einzelbild-Bearbeitungsseite (post.php?post=X&action=edit)
        add_filter( 'attachment_fields_to_edit', [ __CLASS__, 'add_button_to_attachment_fields' ], 10, 2 );

        // Scripts für die Medienbibliothek-Seite (upload.php) und post.php
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_attachment_page_assets' ] );

        // Scripts für den Block-Editor
        add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );

        // Scripts für das Medien-Modal (in Post-Editoren)
        add_action( 'wp_enqueue_media', [ __CLASS__, 'enqueue_media_assets' ] );
    }

    // ── Button-HTML im Anhang-Formular ──────────────────────
    public static function add_button_to_attachment_fields( array $form_fields, WP_Post $post ): array {
        if ( ! wp_attachment_is_image( $post->ID ) ) return $form_fields;

        $form_fields['aag_generate'] = [
            'label' => 'AI Alt-Text',
            'input' => 'html',
            'html'  => sprintf(
                '<button type="button" class="button aag-generate-btn" data-id="%d">
                    ✨ Alt-Text generieren
                </button>
                <span class="aag-status" id="aag-status-%d" style="display:block;margin-top:6px;font-size:12px;"></span>',
                $post->ID,
                $post->ID
            ),
        ];

        return $form_fields;
    }

    // ── Scripts für Medienbibliothek & Attachment-Seite ─────
    public static function enqueue_attachment_page_assets( string $hook ) {
        // upload.php  = Medienbibliothek (Grid + Listenansicht)
        // post.php    = Einzelbild-Bearbeitungsseite
        if ( ! in_array( $hook, [ 'upload.php', 'post.php', 'post-new.php' ] ) ) return;

        wp_enqueue_style( 'aag-frontend', AAG_URL . 'assets/frontend.css', [], AAG_VERSION );

        wp_enqueue_script(
            'aag-attachment',
            AAG_URL . 'assets/attachment.js',
            [ 'jquery' ],
            AAG_VERSION,
            true
        );

        wp_localize_script( 'aag-attachment', 'aagData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aag_generate_nonce' ),
        ] );
    }

    // ── Block-Editor ─────────────────────────────────────────
    public static function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'aag-block-editor',
            AAG_URL . 'assets/block-editor.js',
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'jquery' ],
            AAG_VERSION,
            true
        );
        wp_localize_script( 'aag-block-editor', 'aagData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aag_generate_nonce' ),
            'labels'  => [
                'generate' => 'Alt-Text generieren',
                'loading'  => 'Wird generiert…',
            ],
        ] );
    }

    // ── Medien-Modal ─────────────────────────────────────────
    public static function enqueue_media_assets() {
        wp_enqueue_style( 'aag-frontend', AAG_URL . 'assets/frontend.css', [], AAG_VERSION );

        wp_enqueue_script(
            'aag-media-modal',
            AAG_URL . 'assets/media-modal.js',
            [ 'jquery', 'media-views' ],
            AAG_VERSION,
            true
        );
        wp_localize_script( 'aag-media-modal', 'aagData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aag_generate_nonce' ),
            'labels'  => [
                'generate' => 'Alt-Text generieren',
                'loading'  => 'Wird generiert…',
            ],
        ] );
    }

    // ── AJAX ─────────────────────────────────────────────────
    public static function ajax_generate() {
        if ( ! check_ajax_referer( 'aag_generate_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Sicherheitsfehler.' ] );
        }
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        if ( ! $attachment_id ) {
            wp_send_json_error( [ 'message' => 'Ungültige Bild-ID.' ] );
        }

        $image_url = wp_get_attachment_url( $attachment_id );
        if ( ! $image_url ) {
            wp_send_json_error( [ 'message' => 'Bild nicht gefunden.' ] );
        }

        $opts     = get_option( AAG_OPTION, [] );
        $prompt   = $opts['prompt'] ?? self::default_prompt();
        $language = $opts['language'] ?? 'auto';
        $prompt   = self::inject_language( $prompt, $language );

        try {
            $alt_text = AAG_API_Handler::generate_alt( $image_url, $prompt );
            $alt_text = sanitize_text_field( trim( $alt_text ) );
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

            // Statistik
            AAG_Stats::record( $opts['provider'] ?? 'gemini' );

            wp_send_json_success( [ 'alt' => $alt_text, 'attachment_id' => $attachment_id ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public static function inject_language( string $prompt, string $lang ): string {
        $names = [
            'de' => 'German', 'en' => 'English', 'fr' => 'French',
            'es' => 'Spanish', 'it' => 'Italian', 'nl' => 'Dutch',
            'pt' => 'Portuguese', 'pl' => 'Polish', 'tr' => 'Turkish',
            'ar' => 'Arabic', 'zh' => 'Chinese', 'ja' => 'Japanese',
        ];
        if ( $lang === 'auto' || ! isset( $names[ $lang ] ) ) {
            $lang_instruction = 'Write the alt text in the same language as the website content.';
        } else {
            $lang_instruction = 'Write the alt text in ' . $names[ $lang ] . '.';
        }
        // {language} Platzhalter ersetzen falls vorhanden, sonst ans Ende
        if ( strpos( $prompt, '{language}' ) !== false ) {
            return str_replace( '{language}', $lang_instruction, $prompt );
        }
        return $prompt . "
" . $lang_instruction;
    }

    public static function default_prompt(): string {
        return 'You are an SEO expert. Generate a concise, descriptive alt text for this image that:
- Is between 5 and 15 words
- Describes the image accurately
- Includes relevant keywords naturally
- Does NOT start with "image of" or "photo of"
{language}
Return ONLY the alt text, nothing else.';
    }
}
