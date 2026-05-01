<?php
/**
 * Plugin Name: MRS SEO & Speed issues in 1 click
 * Plugin URI:  https://mrs-dev.com/
 * Description: SEO Alt-Texte mit KI generieren - Medienbibliothek, Block-Editor, Shortcode, Bulk-Generator, Statistik, Bild-Verwendungs-Tracking, PageSpeed-Scan, Bildoptimierung und Meta-SEO-Fixes.
 * Version:     4.0.0
 * Author:      Raeed Shamia
 * Author URI:  https://mrs-dev.com/
 * License:     GPL2
 * Text Domain: mrs-seo-speed
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MSS_VERSION', '4.0.0' );
define( 'MSS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'MSS_URL',     plugin_dir_url( __FILE__ ) );
define( 'MSS_OPTION',  'mss_settings' );

// Kompatibilitaets-Konstanten fuer bestehende Klassen
define( 'AAG_VERSION', MSS_VERSION );
define( 'AAG_DIR',     MSS_DIR );
define( 'AAG_URL',     MSS_URL );
define( 'AAG_OPTION',  'mss_settings' );

require_once MSS_DIR . 'includes/class-api-handler.php';
require_once MSS_DIR . 'includes/class-stats.php';
require_once MSS_DIR . 'includes/class-bulk.php';
require_once MSS_DIR . 'includes/class-alt-generator.php';
require_once MSS_DIR . 'includes/class-pagespeed.php';
require_once MSS_DIR . 'includes/class-image-optimizer.php';
require_once MSS_DIR . 'includes/class-meta-seo.php';
require_once MSS_DIR . 'includes/class-usage-tracker.php';
require_once MSS_DIR . 'includes/class-admin.php';
require_once MSS_DIR . 'includes/class-frontend.php';

add_action( 'plugins_loaded', function () {
    MSS_Admin::init();
    AAG_Alt_Generator::init();
    AAG_Frontend::init();
    AAG_Bulk::init();
    AAG_Usage_Tracker::init();
    MSS_PageSpeed::init();
    MSS_Image_Optimizer::init();
    MSS_Meta_SEO::init();
} );
