<?php
namespace WP_Easy\DocumentDownloader;

defined('ABSPATH') || exit;

final class Activator {
    public static function activate(): void {
        // Ensure /wp-content/uploads/documents exists
        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit($upload_dir['basedir']) . DAS_UPLOADS_SUBDIR;

        if (! file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        // .htaccess to disable indexing
        $htaccess = $target_dir . '/.htaccess';
        if (! file_exists($htaccess)) {
            $rules  = "Options -Indexes\n";
            $rules .= "<IfModule mod_autoindex.c>\nIndexIgnore *\n</IfModule>\n";
            @file_put_contents($htaccess, $rules);
        }

        // blank index.html
        $index_html = $target_dir . '/index.html';
        if (! file_exists($index_html)) {
            @file_put_contents($index_html, '');
        }

        // Register CPT immediately and flush rules
        CPT::register();
        flush_rewrite_rules();
    }
}
