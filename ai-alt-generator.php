<?php
/**
 * Plugin Name: AI Alt Text Generator
 * Plugin URI:  https://example.com
 * Description: Generiert SEO-optimierte Alt-Attribute mit KI (OpenAI, Gemini, Claude) — in der Medienbibliothek, im Block-Editor und per Shortcode im Frontend.
 * Version:     2.1.0
 * Author:      Your Name
 * License:     GPL2
 * Text Domain: ai-alt-gen
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AAG_VERSION', '2.1.0' );
define( 'AAG_DIR',     plugin_dir_path( __FILE__ ) );
define( 'AAG_URL',     plugin_dir_url( __FILE__ ) );
define( 'AAG_OPTION',  'aag_settings' );

require_once AAG_DIR . 'includes/class-api-handler.php';
require_once AAG_DIR . 'includes/class-alt-generator.php';
require_once AAG_DIR . 'includes/class-admin.php';
require_once AAG_DIR . 'includes/class-frontend.php';

add_action( 'plugins_loaded', function () {
    AAG_Admin::init();
    AAG_Alt_Generator::init();
    AAG_Frontend::init();
} );
