<?php
/**
 * Plugin Name:  Document Downloader
 * Description:  Document Download Manager
 * Version:      1.1.2(beta)
 * Requires PHP: 7.4
 * Author:       Alan Blair<alan@wpeasy.au>
 * Text Domain:  document-downloader
 */

defined('ABSPATH') || exit;

// Core paths
define('DD_PLUGIN_FILE', __FILE__);
define('DD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DD_PLUGIN_URL',  plugin_dir_url(__FILE__));

// Meta key used to store the selected attachment ID for a Document post
define('DD_META_KEY', '_dd_file_id');

// Composer autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

add_action('plugins_loaded', static function () {
    load_plugin_textdomain('document-downloader', false, dirname(plugin_basename(__FILE__)) . '/languages');

    WP_Easy\DocumentDownloader\CPT::init();                     // CPT + taxonomy
    WP_Easy\DocumentDownloader\Meta::init();                    // Meta field under title
    WP_Easy\DocumentDownloader\Settings::init();                // Settings (tabs + CSS editor)
    WP_Easy\DocumentDownloader\Shortcode::init();               // Shortcode + assets
    WP_Easy\DocumentDownloader\REST_API::init();                // Endpoints
    WP_Easy\DocumentDownloader\Admin_Downloads::init();         // Downloads admin
    WP_Easy\DocumentDownloader\Instructions::init();            // Instructions page
    WP_Easy\DocumentDownloader\Scheduled_Notifications::init(); // Scheduled email reports
});

// Ensure uploads/documents exists
register_activation_hook(__FILE__, static function () {
    $upload_dir = wp_get_upload_dir();
    $dir = trailingslashit($upload_dir['basedir']) . 'documents';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }
    // .htaccess to disable indexing
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Options -Indexes\n");
    }
});
