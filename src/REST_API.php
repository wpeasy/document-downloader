<?php
namespace WP_Easy\DocumentDownloader;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

defined('ABSPATH') || exit;

final class REST_API
{
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route(
            'document-downloader/v1',
            '/query',
            [[
                'methods'             => WP_REST_Server::CREATABLE, // POST
                'callback'            => [__CLASS__, 'handle_query'],
                'permission_callback' => [__CLASS__, 'permission'],
                'args'                => [
                    'search' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                    'tax'    => ['type' => 'array',  'required' => false], // array of slugs
                    'nonce'  => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ]]
        );

        register_rest_route(
            'document-downloader/v1',
            '/log',
            [[
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'handle_log_download'],
                'permission_callback' => [__CLASS__, 'permission'],
                'args'                => [
                    'email'    => ['type' => 'string', 'required' => true,  'sanitize_callback' => 'sanitize_email'],
                    'filename' => ['type' => 'string', 'required' => true,  'sanitize_callback' => 'sanitize_text_field'],
                    'title'    => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                    'url'      => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'esc_url_raw'],
                    'nonce'    => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ]]
        );
    }

    /** Same-origin + nonce (wp_rest or dd_query) */
    public static function permission(WP_REST_Request $request)
    {
        $site_host    = wp_parse_url(home_url(), PHP_URL_HOST);
        $origin_host  = isset($_SERVER['HTTP_ORIGIN'])  ? wp_parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST)  : '';
        $referer_host = isset($_SERVER['HTTP_REFERER']) ? wp_parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : '';

        $same_origin = false;
        if ($origin_host && $origin_host === $site_host) $same_origin = true;
        elseif ($referer_host && $referer_host === $site_host) $same_origin = true;
        elseif (!$origin_host && !$referer_host && is_user_logged_in()) $same_origin = true;

        if (!$same_origin) {
            return new WP_Error('dd_forbidden_origin', 'Forbidden: cross-origin request.', ['status' => 403]);
        }

        // Core cookie-auth via X-WP-Nonce
        $wp_nonce = $request->get_header('X-WP-Nonce');
        if ($wp_nonce && wp_verify_nonce($wp_nonce, 'wp_rest')) {
            return true;
        }

        // Custom header or body nonce
        $custom = $request->get_header('X-DD-Nonce');
        if (!$custom) {
            $json   = (array) $request->get_json_params();
            $custom = (string) ($json['nonce'] ?? '');
        }
        if ($custom && wp_verify_nonce($custom, 'dd_query')) {
            return true;
        }

        return new WP_Error('dd_missing_nonce', 'Forbidden: missing or invalid nonce.', ['status' => 403]);
    }

    private static function rate_limit(string $ip, int $limit = 30, int $window = 60): bool
    {
        $bucket = 'dd_rl_' . md5($ip . '|' . floor(time() / $window));
        $count  = (int) get_transient($bucket);
        if ($count >= $limit) return false;
        set_transient($bucket, $count + 1, $window);
        return true;
    }

    public static function ensure_table(): string
    {
        global $wpdb;
        // Keep existing table name to avoid migrations
        $table = $wpdb->prefix . 'das_downloads';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_title` VARCHAR(255) NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `downloaded_at` DATETIME NOT NULL,
            `ip` VARCHAR(45) NULL,
            `url` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `email_idx` (`email`(100)),
            KEY `date_idx` (`downloaded_at`)
        ) {$charset};";
        $wpdb->query($sql);
        return $table;
    }

    public static function handle_query(WP_REST_Request $req): WP_REST_Response
    {
        nocache_headers();

        $ctype = (string) $req->get_header('content-type');
        if ($ctype && stripos($ctype, 'application/json') === false) {
            return new WP_REST_Response(['error' => 'unsupported_media_type'], 415);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (! self::rate_limit($ip)) {
            return new WP_REST_Response(['error' => 'rate_limited'], 429);
        }

        $body = (array) $req->get_json_params();
        $s    = trim((string) ($body['search'] ?? ''));
        $tax  = isset($body['tax']) && is_array($body['tax']) ? array_filter(array_map('sanitize_title', $body['tax'])) : [];

        if ($s === '' || mb_strlen($s) < 3) return new WP_REST_Response([], 200);
        if (mb_strlen($s) > 100)        return new WP_REST_Response(['error' => 'too_long'], 400);

        $args = [
            'post_type'       => CPT::POST_TYPE,
            'post_status'     => 'publish',
            's'               => $s,
            'search_columns'  => ['post_title'],
            'nopaging'        => true,
            'no_found_rows'   => true,
            'orderby'         => 'title',
            'order'           => 'ASC',
            'fields'          => 'ids',
        ];

        if ($tax) {
            $args['tax_query'] = [[
                'taxonomy' => CPT::TAXONOMY,
                'field'    => 'slug',
                'terms'    => $tax,
                'operator' => 'IN',
            ]];
        }

        $q = new \WP_Query($args);
        $items = [];

        foreach ($q->posts as $post_id) {
            $att_id = (int) get_post_meta($post_id, DD_META_KEY, true);
            if (!$att_id) continue;

            $url = wp_get_attachment_url($att_id);

            if ($url) {
                $uploads  = wp_get_upload_dir();
                $rel      = (string) get_post_meta($att_id, '_wp_attached_file', true);
                $basename = basename($rel);
                if ($basename && strpos($rel, 'documents/') !== 0) {
                    $doc_path = trailingslashit($uploads['basedir']) . 'documents/' . $basename;
                    if (file_exists($doc_path)) {
                        $url = trailingslashit($uploads['baseurl']) . 'documents/' . $basename;
                    }
                }
            }
            if (!$url) continue;

            $rel   = (string) get_post_meta($att_id, '_wp_attached_file', true);
            $ext   = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            if ($ext === '') {
                $ft  = wp_check_filetype($url);
                $ext = strtolower((string) ($ft['ext'] ?? ''));
            }

            $title = get_post_field('post_title', $post_id, 'raw');
            if ($title === '' || $title === null) {
                $title = wp_specialchars_decode(get_the_title($post_id), ENT_QUOTES);
            }

            $items[] = [
                'id'    => $post_id,
                'title' => $title,
                'url'   => $url,
                'ext'   => $ext ?: 'file',
            ];
        }

        return new WP_REST_Response($items, 200);
    }

    public static function handle_log_download(WP_REST_Request $req): WP_REST_Response
    {
        nocache_headers();

        $body     = (array) $req->get_json_params();
        $email    = sanitize_email((string)($body['email'] ?? ''));
        $filename = sanitize_file_name((string)($body['filename'] ?? ''));
        $title    = sanitize_text_field((string)($body['title'] ?? ''));
        $url      = isset($body['url']) ? esc_url_raw((string)$body['url']) : '';

        if (!is_email($email) || $filename === '') {
            return new WP_REST_Response(['error' => 'bad_request'], 400);
        }

        global $wpdb;
        $table = self::ensure_table();
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
        $now   = current_time('mysql');

        $wpdb->insert($table, [
            'post_title'    => $title,
            'file_name'     => $filename,
            'email'         => $email,
            'downloaded_at' => $now,
            'ip'            => $ip,
            'url'           => $url,
        ], ['%s','%s','%s','%s','%s','%s']);

        // Send notification email if enabled
        self::maybe_send_notification($title, $filename, $email, $url);

        return new WP_REST_Response(['ok' => true], 201);
    }

    /**
     * Send notification email if notify_email setting is enabled
     */
    private static function maybe_send_notification(string $title, string $filename, string $email, string $url): void
    {
        $opts = Settings::get_options();
        
        if (empty($opts['notify_email']) || empty($opts['notification_email'])) {
            return;
        }
        
        $to = $opts['notification_email'];
        
        // Prepare placeholders
        $placeholders = [
            '{file_name}' => $filename,
            '{title}'     => $title ?: $filename,
            '{email}'     => $email,
            '{date}'      => current_time('F j, Y g:i A'),
            '{url}'       => $url ?: 'N/A',
            '{ip}'        => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        ];
        
        // Replace placeholders in subject and message
        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $opts['notification_subject']);
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $opts['notification_message']);
        
        // Apply wpautop to format paragraphs and line breaks properly
        $message = wpautop($message);
        
        // Set content type to HTML
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        wp_mail($to, $subject, $message);
        
        // Reset content type
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
    }
}
