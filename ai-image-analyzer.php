<?php
/**
 * Plugin Name: AI Image MVP Tool
 * Description: Upload image, analyze with AI, show ads while loading.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

/* -------------------------
   ADMIN SETTINGS
------------------------- */

add_action('admin_menu', function() {
    add_menu_page('AI Tool', 'AI Tool', 'manage_options', 'ai-tool', 'ai_settings_page');
});

function ai_settings_page() {
?>
<div class="wrap">
    <h1>AI Image Tool Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields('ai_settings_group'); ?>

        <h3>API Key</h3>
        <input type="text" name="ai_api_key"
            value="<?php echo esc_attr(get_option('ai_api_key')); ?>" style="width:100%;" />

        <h3>Prompt</h3>
        <textarea name="ai_prompt" rows="5" style="width:100%;"><?php
            echo esc_textarea(get_option('ai_prompt'));
        ?></textarea>

        <h3>Ads Code</h3>
        <textarea name="ai_ads_code" rows="5" style="width:100%;"><?php
            echo esc_textarea(get_option('ai_ads_code'));
        ?></textarea>

        <?php submit_button(); ?>
    </form>
</div>
<?php
}

add_action('admin_init', function() {
    register_setting('ai_settings_group', 'ai_api_key');
    register_setting('ai_settings_group', 'ai_prompt');
    register_setting('ai_settings_group', 'ai_ads_code');
});


/* -------------------------
   FRONTEND SHORTCODE
------------------------- */

add_shortcode('ai_image_tool', function() {

    wp_enqueue_script('ai-script', plugin_dir_url(__FILE__) . 'assets/script.js', [], null, true);

    wp_localize_script('ai-script', 'AI_TOOL', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'ads_code' => get_option('ai_ads_code')
    ]);

    ob_start();
    ?>
    <div id="ai-tool">
        <input type="file" id="ai-image" accept="image/*" />
        <button id="ai-btn">Analyze</button>

        <div id="ai-ads" style="display:none; margin-top:20px;"></div>
        <div id="ai-result" style="margin-top:20px;"></div>
    </div>
    <?php
    return ob_get_clean();
});


/* -------------------------
   AJAX HANDLER
------------------------- */

add_action('wp_ajax_ai_analyze', 'ai_analyze');
add_action('wp_ajax_nopriv_ai_analyze', 'ai_analyze');

function ai_analyze() {

    if (empty($_FILES['image'])) {
        wp_send_json_error('No image');
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');

    $upload = wp_handle_upload($_FILES['image'], ['test_form' => false]);

    if (!isset($upload['url'])) {
        wp_send_json_error('Upload failed');
    }

    $result = ai_call($upload['url']);

    wp_send_json_success($result);
}


/* -------------------------
   AI API CALL
------------------------- */

function ai_call($image_url) {

    $api_key = get_option('ai_api_key');
    $prompt  = get_option('ai_prompt');

    $response = wp_remote_post('https://api.openai.com/v1/responses', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'gpt-4.1-mini',
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $prompt
                        ],
                        [
                            'type' => 'input_image',
                            'image_url' => $image_url
                        ]
                    ]
                ]
            ]
        ])
    ]);

    if (is_wp_error($response)) {
        return 'API Error';
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return $body['output'][0]['content'][0]['text'] ?? 'No result';
}