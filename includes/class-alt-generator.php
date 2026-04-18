<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Alt_Generator {

    public static function init() {
        // AJAX-Handler
        add_action( 'wp_ajax_aag_generate_alt', [ __CLASS__, 'ajax_generate' ] );

        // Button in der Medienbibliothek (Anhang-Bearbeitungsseite)
        add_filter( 'attachment_fields_to_edit', [ __CLASS__, 'add_button_to_attachment_fields' ], 10, 2 );

        // Button im Block-Editor & Classic Editor via JS
        add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
        add_action( 'wp_enqueue_media',            [ __CLASS__, 'enqueue_media_assets' ] );
    }

    // ── Anhang-Bearbeitungsseite: Button neben dem Alt-Feld ──
    public static function add_button_to_attachment_fields( array $form_fields, WP_Post $post ): array {
        if ( ! wp_attachment_is_image( $post->ID ) ) return $form_fields;

        $image_url = wp_get_attachment_url( $post->ID );
        $nonce     = wp_create_nonce( 'aag_generate_nonce' );

        $form_fields['aag_generate'] = [
            'label' => __( 'AI Alt-Text', 'ai-alt-gen' ),
            'input' => 'html',
            'html'  => sprintf(
                '<button type="button" class="button aag-generate-btn"
                    data-id="%d"
                    data-url="%s"
                    data-nonce="%s">
                    <span class="aag-btn-icon">&#10024;</span>
                    %s
                </button>
                <span class="aag-status" id="aag-status-%d"></span>',
                $post->ID,
                esc_url( $image_url ),
                esc_attr( $nonce ),
                __( 'Alt-Text generieren', 'ai-alt-gen' ),
                $post->ID
            ),
        ];

        return $form_fields;
    }

    // ── Block-Editor: Script der Button-Logik injiziert ──
    public static function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'aag-block-editor',
            AAG_URL . 'assets/block-editor.js',
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'jquery' ],
            AAG_VERSION,
            true
        );
        self::localize_script( 'aag-block-editor' );
    }

    // ── Medien-Modal: Script ──
    public static function enqueue_media_assets() {
        wp_enqueue_script(
            'aag-media-modal',
            AAG_URL . 'assets/media-modal.js',
            [ 'jquery', 'media-views' ],
            AAG_VERSION,
            true
        );
        self::localize_script( 'aag-media-modal' );

        wp_enqueue_style(
            'aag-media-modal-css',
            AAG_URL . 'assets/frontend.css',
            [],
            AAG_VERSION
        );
    }

    private static function localize_script( string $handle ) {
        wp_localize_script( $handle, 'aagData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aag_generate_nonce' ),
            'labels'  => [
                'generate' => __( 'Alt-Text generieren', 'ai-alt-gen' ),
                'loading'  => __( 'Wird generiert…',     'ai-alt-gen' ),
                'success'  => __( 'Alt-Text gesetzt!',   'ai-alt-gen' ),
                'error'    => __( 'Fehler: ',             'ai-alt-gen' ),
            ],
        ] );
    }

    // ── AJAX: Alt-Text generieren & speichern ──
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

        $opts   = get_option( AAG_OPTION, [] );
        $prompt = $opts['prompt'] ?? self::default_prompt();

        try {
            $alt_text = AAG_API_Handler::generate_alt( $image_url, $prompt );
            $alt_text = sanitize_text_field( trim( $alt_text ) );

            // Alt-Text in der WordPress-Medienbibliothek speichern
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

            wp_send_json_success( [
                'alt'           => $alt_text,
                'attachment_id' => $attachment_id,
            ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public static function default_prompt(): string {
        return 'You are an SEO expert. Generate a concise, descriptive alt text for this image that:
- Is between 5 and 15 words
- Describes the image accurately
- Includes relevant keywords naturally
- Does NOT start with "image of" or "photo of"
- Is in the same language as the website content
Return ONLY the alt text, nothing else.';
    }
}
