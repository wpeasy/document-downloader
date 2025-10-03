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
                    'email'    => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_email'],
                    'name'     => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                    'phone'    => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
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
        if ($custom && wp_verify_nonce($custom, 'doc_search_query')) {
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
            `email` VARCHAR(190) NULL,
            `name` VARCHAR(255) NULL,
            `phone` VARCHAR(50) NULL,
            `downloaded_at` DATETIME NOT NULL,
            `ip` VARCHAR(45) NULL,
            `url` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `email_idx` (`email`(100)),
            KEY `date_idx` (`downloaded_at`)
        ) {$charset};";
        $wpdb->query($sql);
        
        // Add name and phone columns if they don't exist (for existing installations)
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'name'");
        if (empty($columns)) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `name` VARCHAR(255) NULL AFTER `email`");
        }
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'phone'");
        if (empty($columns)) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `phone` VARCHAR(50) NULL AFTER `name`");
        }
        
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

        // Allow empty search for listing all documents, but require 3+ chars for actual search
        if ($s !== '' && mb_strlen($s) < 3) return new WP_REST_Response([], 200);
        if (mb_strlen($s) > 100)        return new WP_REST_Response(['error' => 'too_long'], 400);
        
        // Check if search query matches excluded terms
        if (self::is_search_query_excluded($s)) {
            return new WP_REST_Response([], 200);
        }

        // Check if exact match mode is enabled
        $opts = Settings::get_options();
        $exact_match = !empty($opts['search_exact_match']);

        $args = [
            'post_type'       => CPT::POST_TYPE,
            'post_status'     => 'publish',
            'nopaging'        => true,
            'no_found_rows'   => true,
            'orderby'         => 'title',
            'order'           => 'ASC',
            'fields'          => 'ids',
        ];

        // Only add search if query is not empty
        if ($s !== '') {
            if (!$exact_match) {
                // Partial match - use WordPress search
                $args['s'] = $s;
                $args['search_columns'] = ['post_title'];
            }
            // For exact match, don't use WP search - we'll filter manually after getting all posts
        }

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
            $post = get_post($post_id);

            // Apply filtering based on match mode
            if ($s !== '') {
                if ($exact_match) {
                    // Exact match: title must exactly match search query (case-insensitive)
                    if (strcasecmp($post->post_title, $s) !== 0) {
                        continue;
                    }
                } else {
                    // Partial match: ALL words in search must be found (partial) in title
                    $title_lower = mb_strtolower($post->post_title);
                    $search_words = preg_split('/\s+/', mb_strtolower($s));
                    $matches_all = true;

                    foreach ($search_words as $word) {
                        if (empty($word)) continue;
                        // Check if word appears anywhere in title (partial match)
                        if (strpos($title_lower, $word) === false) {
                            $matches_all = false;
                            break;
                        }
                    }

                    if (!$matches_all) {
                        continue;
                    }
                }
            }

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
        $name     = sanitize_text_field((string)($body['name'] ?? ''));
        $phone    = sanitize_text_field((string)($body['phone'] ?? ''));
        $filename = sanitize_file_name((string)($body['filename'] ?? ''));
        $title    = sanitize_text_field((string)($body['title'] ?? ''));
        $url      = isset($body['url']) ? esc_url_raw((string)$body['url']) : '';

        if ($filename === '') {
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
            'name'          => $name,
            'phone'         => $phone,
            'downloaded_at' => $now,
            'ip'            => $ip,
            'url'           => $url,
        ], ['%s','%s','%s','%s','%s','%s','%s','%s']);

        // Send notification email if enabled
        self::maybe_send_notification($title, $filename, $email, $name, $phone, $url);

        return new WP_REST_Response(['ok' => true], 201);
    }

    /**
     * Send notification email if notify_individually setting is enabled
     */
    private static function maybe_send_notification(string $title, string $filename, string $email, string $name, string $phone, string $url): void
    {
        $opts = Settings::get_options();
        
        if (empty($opts['notify_individually']) || empty($opts['notification_email'])) {
            return;
        }
        
        $to = $opts['notification_email'];
        
        // Prepare placeholders
        $placeholders = [
            '{file_name}' => $filename,
            '{title}'     => $title ?: $filename,
            '{email}'     => $email ?: 'N/A',
            '{name}'      => $name ?: 'N/A',
            '{phone}'     => $phone ?: 'N/A',
            '{date}'      => current_time('F j, Y g:i A'),
            '{url}'       => $url ?: 'N/A',
            '{ip}'        => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        ];
        
        // Replace placeholders in subject and message
        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $opts['notification_subject']);
        $message = self::process_conditional_placeholders($opts['notification_message'], $placeholders);
        
        // Apply wpautop to format paragraphs and line breaks properly
        $message = wpautop($message);
        
        // Set content type to HTML
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        wp_mail($to, $subject, $message);
        
        // Reset content type
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
    }

    /**
     * Check if the search query matches any excluded terms
     */
    private static function is_search_query_excluded(string $query): bool
    {
        $opts = Settings::get_options();
        $excluded_text = trim($opts['excluded_search_text']);
        
        if ($excluded_text === '') {
            return false;
        }
        
        // Parse comma-delimited exclusion terms
        $exclusions = array_map('trim', explode(',', $excluded_text));
        $exclusions = array_filter($exclusions, function($term) { return $term !== ''; });
        
        if (empty($exclusions)) {
            return false;
        }
        
        $query_lower = strtolower($query);
        
        foreach ($exclusions as $exclusion) {
            $pattern = strtolower(trim($exclusion));
            if ($pattern === '') continue;
            
            if (self::query_matches_exclusion_pattern($query_lower, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if query matches an exclusion pattern
     */
    private static function query_matches_exclusion_pattern(string $query, string $pattern): bool
    {
        // No wildcards - whole word match only
        if (strpos($pattern, '*') === false) {
            // Split query into words and check for exact word match
            $words = preg_split('/\s+/', $query);
            return in_array($pattern, $words, true);
        }
        
        // Has wildcards - convert to regex and match entire query
        $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/';
        
        return preg_match($regex, $query) === 1;
    }

    /**
     * Process conditional placeholders in notification messages
     * 
     * Supports:
     * {?field:content} - Show content only if field has value
     * {?field!otherfield:content} - Show content only if field has value and otherfield doesn't
     * 
     * @param string $message The message template with conditional placeholders
     * @param array $placeholders Array of placeholder values
     * @return string Processed message with conditionals resolved
     */
    private static function process_conditional_placeholders(string $message, array $placeholders): string
    {
        // Process conditional placeholders with recursive approach to handle nested braces
        // Pattern: {?field:content} where content can contain other placeholders
        
        $processed = $message;
        $max_iterations = 10; // Prevent infinite loops
        $iteration = 0;
        
        while ($iteration < $max_iterations && preg_match('/\{\?([^:}]+):([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/s', $processed)) {
            $processed = preg_replace_callback(
                '/\{\?([^:}]+):([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/s',
                function ($matches) use ($placeholders) {
                    $condition = trim($matches[1]);
                    $content = $matches[2];
                    
                    // Check for negation syntax: field!otherfield
                    if (strpos($condition, '!') !== false) {
                        [$required_field, $excluded_field] = explode('!', $condition, 2);
                        $required_key = '{' . trim($required_field) . '}';
                        $excluded_key = '{' . trim($excluded_field) . '}';
                        
                        // Show content only if required field has value AND excluded field doesn't
                        $has_required = isset($placeholders[$required_key]) && $placeholders[$required_key] !== '' && $placeholders[$required_key] !== 'N/A';
                        $has_excluded = isset($placeholders[$excluded_key]) && $placeholders[$excluded_key] !== '' && $placeholders[$excluded_key] !== 'N/A';
                        
                        return ($has_required && !$has_excluded) ? $content : '';
                    } else {
                        // Simple condition: field
                        $field_key = '{' . trim($condition) . '}';
                        $has_value = isset($placeholders[$field_key]) && $placeholders[$field_key] !== '' && $placeholders[$field_key] !== 'N/A';
                        
                        return $has_value ? $content : '';
                    }
                },
                $processed
            );
            $iteration++;
        }
        
        // Replace remaining regular placeholders
        $processed = str_replace(array_keys($placeholders), array_values($placeholders), $processed);
        
        return $processed;
    }
}
