<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AAG_Alt_Generator {

    public static function init() {
        add_action( 'wp_ajax_aag_generate_alt', array( __CLASS__, 'ajax_generate' ) );
        add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'add_button_to_attachment_fields' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_attachment_page_assets' ) );
        add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );
        add_action( 'wp_enqueue_media', array( __CLASS__, 'enqueue_media_assets' ) );
    }

    public static function add_button_to_attachment_fields( array $form_fields, WP_Post $post ): array {
        if ( ! wp_attachment_is_image( $post->ID ) ) return $form_fields;

        $form_fields['aag_generate'] = array(
            'label' => 'AI Alt-Text',
            'input' => 'html',
            'html'  => sprintf(
                '<button type="button" class="button aag-generate-btn" data-id="%d">Alt-Text generieren</button>'
                . '<span class="aag-status" id="aag-status-%d" style="display:block;margin-top:6px;font-size:12px;"></span>',
                $post->ID,
                $post->ID
            ),
        );

        return $form_fields;
    }

    public static function enqueue_attachment_page_assets( string $hook ) {
        if ( ! in_array( $hook, array( 'upload.php', 'post.php', 'post-new.php' ), true ) ) return;

        wp_enqueue_style( 'aag-frontend', AAG_URL . 'assets/frontend.css', array(), AAG_VERSION );
        wp_enqueue_script( 'aag-attachment', AAG_URL . 'assets/attachment.js', array( 'jquery' ), AAG_VERSION, true );
        wp_localize_script( 'aag-attachment', 'aagData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aag_generate_nonce' ),
        ) );
    }

    public static function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'aag-block-editor',
            AAG_URL . 'assets/block-editor.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'jquery' ),
            AAG_VERSION,
            true
        );
        wp_localize_script( 'aag-block-editor', 'aagData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aag_generate_nonce' ),
            'labels'  => array(
                'generate' => 'Alt-Text generieren',
                'loading'  => 'Wird generiert...',
            ),
        ) );
    }

    public static function enqueue_media_assets() {
        wp_enqueue_style( 'aag-frontend', AAG_URL . 'assets/frontend.css', array(), AAG_VERSION );
        wp_enqueue_script(
            'aag-media-modal',
            AAG_URL . 'assets/media-modal.js',
            array( 'jquery', 'media-views' ),
            AAG_VERSION,
            true
        );
        wp_localize_script( 'aag-media-modal', 'aagData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aag_generate_nonce' ),
            'labels'  => array(
                'generate' => 'Alt-Text generieren',
                'loading'  => 'Wird generiert...',
            ),
        ) );
    }

    public static function ajax_generate() {
        if ( ! check_ajax_referer( 'aag_generate_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Sicherheitsfehler.' ) );
        }
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        if ( ! $attachment_id ) {
            wp_send_json_error( array( 'message' => 'Ungueltige Bild-ID.' ) );
        }

        $image_url = wp_get_attachment_url( $attachment_id );
        if ( ! $image_url ) {
            wp_send_json_error( array( 'message' => 'Bild nicht gefunden.' ) );
        }

        $opts     = get_option( AAG_OPTION, array() );
        $prompt   = $opts['prompt'] ?? self::default_prompt();
        $language = $opts['language'] ?? 'auto';
        $prompt   = self::inject_language( $prompt, $language );

        try {
            $alt_text = AAG_API_Handler::generate_alt( $image_url, $prompt );
            $alt_text = sanitize_text_field( trim( $alt_text ) );
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
            AAG_Stats::record( $opts['provider'] ?? 'gemini' );
            wp_send_json_success( array( 'alt' => $alt_text, 'attachment_id' => $attachment_id ) );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    public static function inject_language( string $prompt, string $lang ): string {
        $names = array(
            'de' => 'German',   'en' => 'English',  'fr' => 'French',
            'es' => 'Spanish',  'it' => 'Italian',  'nl' => 'Dutch',
            'pt' => 'Portuguese', 'pl' => 'Polish', 'tr' => 'Turkish',
            'ar' => 'Arabic',   'zh' => 'Chinese',  'ja' => 'Japanese',
        );

        if ( $lang === 'auto' || ! isset( $names[ $lang ] ) ) {
            $instruction = 'Write the alt text in the same language as the website content.';
        } else {
            $instruction = 'Write the alt text in ' . $names[ $lang ] . '.';
        }

        if ( strpos( $prompt, '{language}' ) !== false ) {
            return str_replace( '{language}', $instruction, $prompt );
        }
        return $prompt . "\n" . $instruction;
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
