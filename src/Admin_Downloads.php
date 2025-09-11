<?php
namespace WP_Easy\DocumentDownloader;

defined('ABSPATH') || exit;

/**
 * Admin screen for viewing & exporting download submissions.
 */
final class Admin_Downloads
{
    /** @var string|null */
    private static $hook_suffix = null;

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'menu'], 20);
    }

    public static function menu(): void
    {
        self::$hook_suffix = add_submenu_page(
            'edit.php?post_type=' . CPT::POST_TYPE,
            __('Downloads', 'document-address-search'),
            __('Downloads', 'document-address-search'),
            'edit_posts',
            'doc_search_downloads',
            [__CLASS__, 'render'],
            10 // First position
        );

        // Handle actions before admin page renders to avoid "headers already sent"
        if (self::$hook_suffix) {
            add_action('load-' . self::$hook_suffix, [__CLASS__, 'maybe_export']);
            add_action('load-' . self::$hook_suffix, [__CLASS__, 'maybe_clear_log']);
        }
    }

    /**
     * If 'doc_search_clear=1' is present, clear the downloads table.
     */
    public static function maybe_clear_log(): void
    {
        if (! current_user_can('edit_posts')) {
            return;
        }
        if (empty($_GET['doc_search_clear'])) {
            return;
        }
        if (! isset($_GET['doc_search_clear_nonce']) || ! wp_verify_nonce((string) $_GET['doc_search_clear_nonce'], 'doc_search_clear_log')) {
            wp_die(__('Invalid clear request.', 'document-downloader'));
        }

        global $wpdb;
        $table = REST_API::ensure_table();
        $wpdb->query("TRUNCATE TABLE `{$table}`");

        // Redirect back to avoid re-clearing on refresh
        $redirect_url = remove_query_arg(['doc_search_clear', 'doc_search_clear_nonce']);
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * If 'doc_search_export=1' is present, stream CSV and exit before any HTML is printed.
     */
    public static function maybe_export(): void
    {
        if (! current_user_can('edit_posts')) {
            return;
        }
        if (empty($_GET['doc_search_export'])) {
            return;
        }
        if (! isset($_GET['doc_search_export_nonce']) || ! wp_verify_nonce((string) $_GET['doc_search_export_nonce'], 'doc_search_export_csv')) {
            wp_die(__('Invalid export request.', 'document-address-search'));
        }

        // Read and sanitize filters from query
        $file_name = isset($_GET['file_name']) ? sanitize_text_field(wp_unslash($_GET['file_name'])) : '';
        $email     = isset($_GET['email']) ? sanitize_text_field(wp_unslash($_GET['email'])) : '';
        $name      = isset($_GET['name']) ? sanitize_text_field(wp_unslash($_GET['name'])) : '';
        $phone     = isset($_GET['phone']) ? sanitize_text_field(wp_unslash($_GET['phone'])) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to   = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

        global $wpdb;
        $table = REST_API::ensure_table();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        self::export_csv($table, $file_name, $email, $name, $phone, $date_from, $date_to);
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'document-address-search'));
        }

        global $wpdb;

        // Ensure table exists (lazy create)
        $table = REST_API::ensure_table();

        // Read filters (allow * and ? wildcards)
        $file_name  = isset($_GET['file_name'])  ? sanitize_text_field(wp_unslash($_GET['file_name'])) : '';
        $email      = isset($_GET['email'])      ? sanitize_text_field(wp_unslash($_GET['email']))     : '';
        $name       = isset($_GET['name'])       ? sanitize_text_field(wp_unslash($_GET['name']))      : '';
        $phone      = isset($_GET['phone'])      ? sanitize_text_field(wp_unslash($_GET['phone']))     : '';
        $date_from  = isset($_GET['date_from'])  ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to    = isset($_GET['date_to'])    ? sanitize_text_field(wp_unslash($_GET['date_to']))   : '';

        // Build query (ASC by date)
        [$sql, $args] = self::build_query($table, $file_name, $email, $name, $phone, $date_from, $date_to);

        // Fetch rows
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        // Build export URL with current filters + nonce
        $base_url = admin_url('edit.php?post_type=' . CPT::POST_TYPE . '&page=doc_search_downloads');
        $query    = array_filter([
            'page'       => 'doc_search_downloads',
            'file_name'  => $file_name,
            'email'      => $email,
            'name'       => $name,
            'phone'      => $phone,
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            'doc_search_export' => 1,
        ], static function($v){ return $v !== '' && $v !== null; });

        $export_url = add_query_arg($query, $base_url);
        $export_url = wp_nonce_url($export_url, 'doc_search_export_csv', 'doc_search_export_nonce');

        // Build clear log URL + nonce
        $clear_url = add_query_arg(['page' => 'doc_search_downloads', 'doc_search_clear' => 1], $base_url);
        $clear_url = wp_nonce_url($clear_url, 'doc_search_clear_log', 'doc_search_clear_nonce');

        // Reset URL (clear filters)
        $reset_url = remove_query_arg(['file_name','email','name','phone','date_from','date_to'], $base_url);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Downloads', 'document-address-search'); ?></h1>

            <p class="description" style="margin:8px 0 16px;">
                <?php
                echo esc_html__(
                    'You can use * and ? as wildcards in the File Name and Email filters (example: *file_name*).',
                    'document-address-search'
                );
                ?>
            </p>

            <form method="get" action="">
                <input type="hidden" name="post_type" value="<?php echo esc_attr(CPT::POST_TYPE); ?>" />
                <input type="hidden" name="page" value="doc_search_downloads" />

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="doc-search-filter-file"><?php esc_html_e('File Name', 'document-address-search'); ?></label></th>
                            <td>
                                <input id="doc-search-filter-file" type="text" class="regular-text" name="file_name"
                                       value="<?php echo esc_attr($file_name); ?>"
                                       placeholder="<?php esc_attr_e('e.g. brochure.pdf or *brochure*', 'document-address-search'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="doc-search-filter-email"><?php esc_html_e('Email Address', 'document-address-search'); ?></label></th>
                            <td>
                                <input id="doc-search-filter-email" type="text" class="regular-text" name="email"
                                       value="<?php echo esc_attr($email); ?>"
                                       placeholder="<?php esc_attr_e('e.g. user@example.com or *@example.com', 'document-address-search'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="doc-search-filter-name"><?php esc_html_e('Name', 'document-downloader'); ?></label></th>
                            <td>
                                <input id="doc-search-filter-name" type="text" class="regular-text" name="name"
                                       value="<?php echo esc_attr($name); ?>"
                                       placeholder="<?php esc_attr_e('e.g. John Doe or *John*', 'document-downloader'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="doc-search-filter-phone"><?php esc_html_e('Phone', 'document-downloader'); ?></label></th>
                            <td>
                                <input id="doc-search-filter-phone" type="text" class="regular-text" name="phone"
                                       value="<?php echo esc_attr($phone); ?>"
                                       placeholder="<?php esc_attr_e('e.g. 123-456-7890 or *555*', 'document-downloader'); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Date Range', 'document-address-search'); ?></th>
                            <td>
                                <label for="doc-search-filter-from" style="margin-right:.5rem;"><?php esc_html_e('From', 'document-address-search'); ?></label>
                                <input id="doc-search-filter-from" type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
                                <label for="doc-search-filter-to" style="margin:0 .5rem 0 1rem;"><?php esc_html_e('To', 'document-address-search'); ?></label>
                                <input id="doc-search-filter-to" type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'document-address-search'); ?></button>
                    <a href="<?php echo esc_url($reset_url); ?>" class="button"><?php esc_html_e('Reset', 'document-address-search'); ?></a>
                    <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary" style="margin-left:.5rem;"><?php esc_html_e('Export to CSV', 'document-address-search'); ?></a>
                </p>
            </form>

            <hr />

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div></div>
                <button type="button" id="doc-search-clear-log" class="button button-secondary" data-clear-url="<?php echo esc_url($clear_url); ?>" style="color: #b32d2e;">
                    <?php esc_html_e('Clear Log', 'document-downloader'); ?>
                </button>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'document-address-search'); ?></th>
                        <th style="width:150px;"><?php esc_html_e('Date', 'document-address-search'); ?></th>
                        <th><?php esc_html_e('File Name', 'document-address-search'); ?></th>
                        <th style="width:200px;"><?php esc_html_e('Email', 'document-address-search'); ?></th>
                        <th style="width:150px;"><?php esc_html_e('Name', 'document-downloader'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('Phone', 'document-downloader'); ?></th>
                        <th style="width:100px;"><?php esc_html_e('IP', 'document-address-search'); ?></th>
                        <th><?php esc_html_e('URL', 'document-address-search'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8"><?php esc_html_e('No downloads found for the current filters.', 'document-address-search'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo esc_html($r['post_title']); ?></td>
                                <td><?php echo esc_html($r['downloaded_at']); ?></td>
                                <td><?php echo esc_html($r['file_name']); ?></td>
                                <td><?php echo esc_html($r['email'] ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($r['name'] ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($r['phone'] ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($r['ip']); ?></td>
                                <td style="word-break:break-all;">
                                    <?php if (!empty($r['url'])): ?>
                                        <a href="<?php echo esc_url($r['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['url']); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#doc-search-clear-log').on('click', function(e) {
                e.preventDefault();
                
                var clearUrl = $(this).data('clear-url');
                
                if (confirm('<?php echo esc_js(__('Are you sure you want to clear all download logs? This action cannot be undone.', 'document-downloader')); ?>')) {
                    window.location.href = clearUrl;
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Build the SQL + args for current filters (ASC by date).
     * Supports user wildcards: * -> %, ? -> _
     *
     * @return array{0:string,1:array}
     */
    private static function build_query(string $table, string $file_name, string $email, string $name, string $phone, string $date_from, string $date_to): array
    {
        global $wpdb;

        $where = [];
        $args  = [];

        // File name wildcard pattern
        $file_like = self::wildcard_like($file_name);
        if ($file_like !== null) {
            $where[] = 'file_name LIKE %s';
            $args[]  = $file_like;
        }

        // Email wildcard pattern
        $email_like = self::wildcard_like($email);
        if ($email_like !== null) {
            $where[] = 'email LIKE %s';
            $args[]  = $email_like;
        }

        // Name wildcard pattern
        $name_like = self::wildcard_like($name);
        if ($name_like !== null) {
            $where[] = 'name LIKE %s';
            $args[]  = $name_like;
        }

        // Phone wildcard pattern
        $phone_like = self::wildcard_like($phone);
        if ($phone_like !== null) {
            $where[] = 'phone LIKE %s';
            $args[]  = $phone_like;
        }

        // Dates
        $from_dt = '';
        $to_dt   = '';
        if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $from_dt = $date_from . ' 00:00:00';
        }
        if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $to_dt = $date_to . ' 23:59:59';
        }

        if ($from_dt && $to_dt) {
            $where[] = 'downloaded_at BETWEEN %s AND %s';
            $args[]  = $from_dt;
            $args[]  = $to_dt;
        } elseif ($from_dt) {
            $where[] = 'downloaded_at >= %s';
            $args[]  = $from_dt;
        } elseif ($to_dt) {
            $where[] = 'downloaded_at <= %s';
            $args[]  = $to_dt;
        }

        $sql = "SELECT id, post_title, file_name, email, name, phone, downloaded_at, ip, url FROM {$table}";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY downloaded_at DESC';

        return [$sql, $args];
    }

    /**
     * Convert a user-entered string with wildcards to a safe SQL LIKE pattern.
     * - User '*' => SQL '%'
     * - User '?' => SQL '_'
     * - If user provided no wildcards, default to contains: wrap with %...%
     * - Properly escapes any literal % and _ using $wpdb->esc_like semantics.
     */
    private static function wildcard_like(string $input): ?string
    {
        global $wpdb;

        $s = trim($input);
        if ($s === '') {
            return null;
        }

        $has_user_wildcards = (strpos($s, '*') !== false) || (strpos($s, '?') !== false);

        // Placeholders to preserve wildcards through esc_like
        $STAR = "\x01"; // placeholder for *
        $Q    = "\x02"; // placeholder for ?

        // Replace user wildcards with placeholders
        $s = str_replace(['*', '?'], [$STAR, $Q], $s);

        // Escape LIKE special chars (% and _) and backslashes safely
        $s = $wpdb->esc_like($s);

        // Restore wildcards as SQL wildcards
        $s = str_replace([$STAR, $Q], ['%', '_'], $s);

        // If the user didn't include any wildcards, default to "contains"
        if (!$has_user_wildcards) {
            $s = '%' . $s . '%';
        }

        return $s;
    }

    private static function export_csv(string $table, string $file_name, string $email, string $name, string $phone, string $date_from, string $date_to): void
    {
        global $wpdb;

        [$sql, $args] = self::build_query($table, $file_name, $email, $name, $phone, $date_from, $date_to);
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        // Headers
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="doc-search-downloads-' . gmdate('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');

        // Header row (Title first)
        fputcsv($out, ['Title', 'Date', 'File Name', 'Email', 'Name', 'Phone', 'IP', 'URL']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['post_title'],
                $r['downloaded_at'],
                $r['file_name'],
                $r['email'] ?: '',
                $r['name'] ?: '',
                $r['phone'] ?: '',
                $r['ip'],
                $r['url'],
            ]);
        }

        fclose($out);
    }
}
