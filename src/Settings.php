<?php
namespace WP_Easy\DocumentDownloader;

defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION = 'dd_labels';

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'maybe_enqueue_assets']);
    }

    /** Defaults */
    public static function defaults(): array
    {
        return [
            'plural'              => 'Documents',
            'singular'            => 'Document',
            'disable_alpine'      => 0,
            'require_email'       => 0,
            'require_name'        => 0,
            'require_phone'       => 0,
            'notify_email'        => 0,
            'notification_email'  => '',
            'notification_subject' => '{file_name} downloaded',
            'notification_message' => self::default_notification_message(),
            'excluded_search_text' => '',
            'frontend_css'        => self::default_css(),
        ];
    }

    /** Default notification message HTML */
    private static function default_notification_message(): string
    {
        return <<<HTML
<h2>Document Downloaded</h2>
A document has been downloaded from your website.<br><br>

<strong>Document:</strong> {file_name}<br><br>
{?name:<strong>Downloaded by:</strong> {name}{?email: &lt;{email}&gt;}<br><br>}
{?email!name:<strong>Email:</strong> {email}<br><br>}
{?phone:<strong>Phone:</strong> {phone}<br><br>}
<strong>Date:</strong> {date}<br>
<strong>URL:</strong> {url}
HTML;
    }

    /** Default frontend CSS (BEM `.doc-search__*`) */
    private static function default_css(): string
    {
        return <<<CSS
/* Low-priority cascade layer so themes override easily */
@layer docSearch {
  /* BEM Root Classes */
  .doc-search {
    --doc-search-status-lines: 1;
    --doc-search-status-lh: 1.25;
    margin: 0;
    position: relative;
  }

  .doc-search-search {
    max-width: 640px;
  }

  .doc-search-list {
    max-width: none;
  }

  /* Legacy dd class support */
  .dd {
    --doc-search-status-lines: 1;
    --doc-search-status-lh: 1.25;
    max-width: 640px;
    margin: 0;
    position: relative;
  }

  .doc-search__label { display:block; margin-bottom:.25rem; font-weight:600; }

  .doc-search__input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
  }

  .doc-search__input {
    width:100%;
    padding:.5rem 2.5rem .5rem .75rem;
    border:1px solid #ccc;
    border-radius:6px;
    position: relative;
    z-index: 1;
    background: white;
  }
  .doc-search__input:focus { outline:none; border-color:#999; }
  
  /* Remove default search input styling that might interfere */
  .doc-search__input[type="search"]::-webkit-search-cancel-button {
    -webkit-appearance: none;
    display: none;
  }
  
  .doc-search__input-icon {
    position: absolute;
    right: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    pointer-events: none;
    z-index: 2;
  }
  
  .doc-search__input-icon--clear {
    pointer-events: auto;
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px;
    border-radius: 3px;
    color: #999;
  }
  
  .doc-search__input-icon--clear:hover {
    background: #f0f0f0;
    color: #666;
  }
  
  .doc-search__input-icon--spinner {
    color: #0073aa;
  }
  
  .doc-search__input-icon svg {
    width: 16px;
    height: 16px;
  }

  /* Fixed-height status to avoid layout shift */
  .doc-search__statuswrap {
    display:flex;
    align-items:center;
    line-height: var(--doc-search-status-lh);
    min-block-size: calc(var(--doc-search-status-lines) * 1em * var(--doc-search-status-lh));
  }

  .doc-search__status { margin:0; font-style:italic; opacity:.85; }
  .doc-search__status--error { color: #d63638; font-weight: 500; }

  .doc-search__list { 
    position: absolute; 
    list-style: none; 
    padding: 1rem; 
    margin: 0; 
    background: white; 
    box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
    max-height: 400px; 
    overflow-y: auto; 
    width: 100%; 
    z-index: 1000;
    border-radius: 6px;
    opacity: 0;
    transform: translateY(8px);
    transition: opacity 0.2s ease-out, transform 0.2s ease-out;
    visibility: hidden;
  }
  
  .doc-search__list.doc-search__list--visible {
    opacity: 1;
    transform: translateY(0);
    visibility: visible;
  }

  /* Static list for doc-search-list variant */
  .doc-search__list--static {
    position: static !important;
    opacity: 1 !important;
    transform: none !important;
    visibility: visible !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
    max-height: none !important;
    overflow-y: visible !important;
  }

  .doc-search-list .doc-search__list-container {
    margin-top: 1rem;
  }
  
  /* Custom scrollbar styling */
  .doc-search__list::-webkit-scrollbar { width: 8px; }
  .doc-search__list::-webkit-scrollbar-track { background: #f1f1f1; }
  .doc-search__list::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
  .doc-search__list::-webkit-scrollbar-thumb:hover { background: #555; }
  .doc-search__item { margin:0 0 .5rem 0; }

  .doc-search__button {
    display:inline-flex;
    align-items:center;
    gap:.5rem;
    padding:.5rem .75rem;
    border:1px solid #ddd;
    border-radius:999px;
    background:#fff;
    cursor:pointer;
    text-decoration:none;
    color:inherit;
  }
  .doc-search__button:hover { background:#f7f7f7; }
  .doc-search__button:disabled { opacity:.6; cursor:not-allowed; }

  .doc-search__icon { line-height:0; display:inline-flex; }
  .doc-search__icon svg { height:1.3em; width:auto; display:block; }

  .doc-search__title { line-height:1.2; }

  /* Dialog */
  .doc-search__dialog { border:1px solid #ddd; border-radius:12px; padding:1rem; max-width:420px; width:calc(100% - 2rem); }
  .doc-search__dialog::backdrop { background:rgba(0,0,0,.45); }
  .doc-search__dialog-title { margin:0 0 .25rem; font-weight:600; font-size:1.05rem; }
  .doc-search__dialog-file { margin:0 0 .75rem; opacity:.8; word-break:break-all; }
  .doc-search__dialog-close { position:absolute; top:.5rem; right:.5rem; border:0; background:transparent; font-size:1rem; line-height:1; cursor:pointer; }

  .doc-search__field { display:block; margin:0 0 .75rem; }
  .doc-search__field-label { display:block; font-size:.9rem; margin-bottom:.25rem; }
  .doc-search__field-required { color:#d73502; font-weight:bold; }
  .doc-search__field-input { width:100%; padding:.5rem .75rem; border:1px solid #ccc; border-radius:6px; transition:border-color .2s, box-shadow .2s; }
  .doc-search__field-input:focus { outline:none; border-color:#3582c4; box-shadow:0 0 0 1px #3582c4; }
  .doc-search__field-message { display:block; font-size:.8rem; margin-top:.25rem; }
  
  .doc-search__field--valid .doc-search__field-input { border-color:#46b450 !important; }
  .doc-search__field--valid .doc-search__field-message { color:#46b450 !important; }
  
  .doc-search__field--invalid .doc-search__field-input { border-color:#d73502 !important; box-shadow:0 0 0 1px rgba(215,53,2,.1) !important; }
  .doc-search__field--invalid .doc-search__field-message { color:#d73502 !important; }

  .doc-search__actions { display:flex; gap:.5rem; }
  .doc-search__btn { border:1px solid #ddd; background:#fff; padding:.5rem .9rem; border-radius:8px; cursor:pointer; }
  .doc-search__btn--primary { border-color:#222; background:#222; color:#fff; }
  .doc-search__btn:disabled { opacity:.6; cursor:not-allowed; }
}
CSS;
    }

    public static function get_options(): array
    {
        $opt = get_option(self::OPTION);
        if (!is_array($opt)) $opt = [];
        $opt = wp_parse_args($opt, self::defaults());

        $out = [
            'plural'              => trim((string)$opt['plural']),
            'singular'            => trim((string)$opt['singular']),
            'disable_alpine'      => (int)!empty($opt['disable_alpine']),
            'require_email'       => (int)!empty($opt['require_email']),
            'require_name'        => (int)!empty($opt['require_name']),
            'require_phone'       => (int)!empty($opt['require_phone']),
            'notify_email'        => (int)!empty($opt['notify_email']),
            'notification_email'  => sanitize_email((string)$opt['notification_email']),
            'notification_subject' => trim((string)$opt['notification_subject']),
            'notification_message' => (string)$opt['notification_message'],
            'excluded_search_text' => trim((string)$opt['excluded_search_text']),
            'frontend_css'        => (string)$opt['frontend_css'],
        ];
        if ($out['plural'] === '')   $out['plural']   = 'Documents';
        if ($out['singular'] === '') $out['singular'] = 'Document';
        return $out;
    }

    public static function get_labels(): array
    {
        $opt = self::get_options();
        return ['plural' => $opt['plural'], 'singular' => $opt['singular']];
    }

    public static function get_frontend_css(): string
    {
        $opt = self::get_options();
        return (string) ($opt['frontend_css'] ?? '');
    }

    public static function menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . CPT::POST_TYPE,
            __('Document Downloader Settings', 'document-downloader'),
            __('Settings', 'document-downloader'),
            'manage_options',
            'dd_document_settings',
            [__CLASS__, 'render']
        );
    }

    public static function register(): void
    {
        register_setting(
            'dd_settings_group',
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize'],
                'default'           => self::defaults(),
            ]
        );
    }

    public static function sanitize($value): array
    {
        $d = self::defaults();
        $out = [
            'plural'              => isset($value['plural']) ? sanitize_text_field($value['plural']) : $d['plural'],
            'singular'            => isset($value['singular']) ? sanitize_text_field($value['singular']) : $d['singular'],
            'disable_alpine'      => !empty($value['disable_alpine']) ? 1 : 0,
            'require_email'       => !empty($value['require_email']) ? 1 : 0,
            'require_name'        => !empty($value['require_name']) ? 1 : 0,
            'require_phone'       => !empty($value['require_phone']) ? 1 : 0,
            'notify_email'        => !empty($value['notify_email']) ? 1 : 0,
            'notification_email'  => isset($value['notification_email']) ? sanitize_email($value['notification_email']) : '',
            'notification_subject' => isset($value['notification_subject']) ? sanitize_text_field($value['notification_subject']) : $d['notification_subject'],
            'notification_message' => isset($value['notification_message']) ? wp_kses_post($value['notification_message']) : $d['notification_message'],
            'excluded_search_text' => isset($value['excluded_search_text']) ? sanitize_textarea_field($value['excluded_search_text']) : $d['excluded_search_text'],
            'frontend_css'        => isset($value['frontend_css']) ? preg_replace("/^\xEF\xBB\xBF/", '', (string)$value['frontend_css']) : $d['frontend_css'],
        ];
        if ($out['plural'] === '')   $out['plural']   = $d['plural'];
        if ($out['singular'] === '') $out['singular'] = $d['singular'];
        if ($out['notification_subject'] === '') $out['notification_subject'] = $d['notification_subject'];
        return $out;
    }

    public static function maybe_enqueue_assets(): void
    {
        $is_our_page = isset($_GET['page']) && $_GET['page'] === 'dd_document_settings'; // phpcs:ignore WordPress.Security.NonceVerification
        if (! $is_our_page) return;

        $handle = 'doc-search-cm6-admin';
        wp_register_style($handle, false, [], '1.0');
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, '#doc-search-cm6-wrapper{height:70vh} #doc-search-frontend-css{min-height:70vh;height:70vh;}');
    }

    private static function field_text(string $name, string $value, string $placeholder = '', string $attr = ''): void
    {
        printf(
            '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" %5$s />',
            esc_attr(self::OPTION), esc_attr($name), esc_attr($value), esc_attr($placeholder), $attr
        );
    }

    private static function field_checkbox(string $name, int $checked, string $label): void
    {
        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
            esc_attr(self::OPTION), esc_attr($name), checked(1, $checked, false), esc_html($label)
        );
    }

    private static function field_email(string $name, string $value, string $placeholder = ''): void
    {
        printf(
            '<input type="email" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" />',
            esc_attr(self::OPTION), esc_attr($name), esc_attr($value), esc_attr($placeholder)
        );
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'document-downloader'));
        }

        $opt         = self::get_options();
        $default_css = self::default_css();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Document Downloader Settings', 'document-downloader'); ?></h1>

            <form method="post" action="options.php" id="doc-search-settings-form">
                <?php settings_fields('dd_settings_group'); ?>

                <h2 class="nav-tab-wrapper" id="doc-search-tabs" role="tablist" style="margin-bottom:.75rem;">
                    <a href="#settings" class="nav-tab nav-tab-active" role="tab" aria-selected="true" data-tab="settings"><?php esc_html_e('Settings', 'document-downloader'); ?></a>
                    <a href="#notifications" class="nav-tab" role="tab" aria-selected="false" data-tab="notifications"><?php esc_html_e('Notifications', 'document-downloader'); ?></a>
                    <a href="#style" class="nav-tab" role="tab" aria-selected="false" data-tab="style"><?php esc_html_e('Style', 'document-downloader'); ?></a>
                </h2>

                <div id="doc-search-tabpanes">
                    <div id="doc-search-tab-settings" class="doc-search-tabpane" role="tabpanel" style="display:block;">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="doc-search-plural"><?php esc_html_e('Post Type Plural Name', 'document-downloader'); ?></label></th>
                                    <td><?php self::field_text('plural', $opt['plural'], 'Documents', 'id="doc-search-plural"'); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="doc-search-singular"><?php esc_html_e('Post Type Singular Name', 'document-downloader'); ?></label></th>
                                    <td><?php self::field_text('singular', $opt['singular'], 'Document', 'id="doc-search-singular"'); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Disable AlpineJS', 'document-downloader'); ?></th>
                                    <td><?php self::field_checkbox('disable_alpine', (int)$opt['disable_alpine'], __('If this plugin clashes with another plugin loading AlpineJS, check to disable loading.', 'document-downloader')); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Require email for download', 'document-downloader'); ?></th>
                                    <td><?php self::field_checkbox('require_email', (int)$opt['require_email'], __('Require users to enter an email address before downloading.', 'document-downloader')); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Require name for download', 'document-downloader'); ?></th>
                                    <td><?php self::field_checkbox('require_name', (int)$opt['require_name'], __('Require users to enter their name before downloading.', 'document-downloader')); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Require phone for download', 'document-downloader'); ?></th>
                                    <td><?php self::field_checkbox('require_phone', (int)$opt['require_phone'], __('Require users to enter their phone number before downloading.', 'document-downloader')); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="doc-search-excluded-text"><?php esc_html_e('Excluded Search Text', 'document-downloader'); ?></label></th>
                                    <td>
                                        <?php self::field_text('excluded_search_text', $opt['excluded_search_text'], 'pdf, .docx, *temp*, draft*', 'id="doc-search-excluded-text" class="large-text"'); ?>
                                        <p class="description"><?php esc_html_e('Comma-separated list of search terms to block. If someone searches for these terms, they get no results. Use * as wildcard (e.g., "pdf, .docx, *temp*, draft*"). Without wildcards, matches whole words only.', 'document-downloader'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="doc-search-tab-notifications" class="doc-search-tabpane" role="tabpanel" style="display:none;">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Notify by email', 'document-downloader'); ?></th>
                                    <td><?php self::field_checkbox('notify_email', (int)$opt['notify_email'], __('Send email notification when documents are downloaded.', 'document-downloader')); ?></td>
                                </tr>
                                <tr id="doc-search-notify-row" <?php echo $opt['notify_email'] ? '' : 'style="display:none"'; ?>>
                                    <th scope="row"><label for="doc-search-notify-email"><?php esc_html_e('Notification Email Address', 'document-downloader'); ?></label></th>
                                    <td><?php self::field_email('notification_email', $opt['notification_email'], 'name@example.com'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div id="doc-search-notification-fields" <?php echo $opt['notify_email'] ? '' : 'style="display:none"'; ?>>
                            <hr style="margin: 20px 0;">
                            
                            <h3><?php esc_html_e('Email Template', 'document-downloader'); ?></h3>
                            
                            <div style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 4px;">
                                <h4 style="margin-top: 0;"><?php esc_html_e('Available Placeholders (click to copy):', 'document-downloader'); ?></h4>
                                <div id="doc-search-placeholders">
                                    <span class="doc-search-placeholder" data-placeholder="{file_name}">{file_name}</span>
                                    <span class="doc-search-placeholder" data-placeholder="{title}">{title}</span>
                                    <span class="doc-search-placeholder" data-placeholder="{name}">{name}</span>
                                    <span class="doc-search-placeholder" data-placeholder="{email}">{email}</span>
                                    <span class="doc-search-placeholder" data-placeholder="{phone}">{phone}</span>
                                    <span class="doc-search-placeholder" data-placeholder="{date}">{date}</span>
                                    <span class="doc-search-placeholder" data-placeholder="{url}">{url}</span>
                                    <span class="doc-search-placeholder" data-placeholder="{ip}">{ip}</span>
                                </div>
                                
                                <h4 style="margin: 20px 0 10px 0;"><?php esc_html_e('Conditional Placeholders:', 'document-downloader'); ?></h4>
                                <p style="margin: 0 0 10px 0; color: #666;"><?php esc_html_e('Use conditional placeholders to show content only when specific fields have values:', 'document-downloader'); ?></p>
                                <ul style="margin: 0 0 10px 20px; color: #666; font-family: monospace; font-size: 12px;">
                                    <li style="margin-bottom: 5px;"><strong>{?field:content}</strong> - <?php esc_html_e('Show content only if field has a value', 'document-downloader'); ?></li>
                                    <li style="margin-bottom: 5px;"><strong>{?field!otherfield:content}</strong> - <?php esc_html_e('Show content if field has value and otherfield doesn\'t', 'document-downloader'); ?></li>
                                </ul>
                                <div style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 12px;">
                                    <p style="margin: 0 0 5px 0; font-weight: bold;"><?php esc_html_e('Examples:', 'document-downloader'); ?></p>
                                    <p style="margin: 0 0 3px 0; color: #0073aa;"><span class="doc-search-placeholder" data-placeholder="{?name:<strong>Name:</strong> {name}<br><br>}">{?name:&lt;strong&gt;Name:&lt;/strong&gt; {name}&lt;br&gt;&lt;br&gt;}</span></p>
                                    <p style="margin: 0 0 3px 0; color: #0073aa;"><span class="doc-search-placeholder" data-placeholder="{?email!name:<strong>Email:</strong> {email}<br><br>}">{?email!name:&lt;strong&gt;Email:&lt;/strong&gt; {email}&lt;br&gt;&lt;br&gt;}</span></p>
                                    <p style="margin: 0 0 8px 0; color: #666; font-size: 11px;"><?php esc_html_e('Click examples above to copy to clipboard', 'document-downloader'); ?></p>
                                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 8px; border-radius: 3px; margin-top: 10px;">
                                        <p style="margin: 0; color: #856404; font-size: 11px; font-weight: bold;">⚠️ <?php esc_html_e('Important:', 'document-downloader'); ?></p>
                                        <p style="margin: 2px 0 0 0; color: #856404; font-size: 11px;"><?php esc_html_e('If using conditional placeholders, stay in Text mode. Switching to Visual mode will break the conditional syntax.', 'document-downloader'); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="doc-search-notification-subject"><?php esc_html_e('Email Subject', 'document-downloader'); ?></label></th>
                                        <td><?php self::field_text('notification_subject', $opt['notification_subject'], '{file_name} downloaded', 'id="doc-search-notification-subject" class="large-text"'); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="doc-search-notification-message"><?php esc_html_e('Email Message', 'document-downloader'); ?></label></th>
                                        <td>
                                            <?php 
                                            wp_editor($opt['notification_message'], 'doc-search-notification-message', [
                                                'textarea_name' => self::OPTION . '[notification_message]',
                                                'textarea_rows' => 10,
                                                'media_buttons' => false,
                                                'teeny' => true,
                                                'tinymce' => [
                                                    'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,link,unlink',
                                                    'toolbar2' => '',
                                                    'setup' => 'function(editor) {
                                                        // Protect conditional placeholders from being mangled
                                                        editor.on("BeforeSetContent", function(e) {
                                                            if (e.content) {
                                                                e.content = e.content.replace(/\{\?([^}]+)\}/g, "<!--CONDITIONAL_START-->$&<!--CONDITIONAL_END-->");
                                                            }
                                                        });
                                                        editor.on("PostProcess", function(e) {
                                                            if (e.content) {
                                                                e.content = e.content.replace(/<!--CONDITIONAL_START-->([^<]+)<!--CONDITIONAL_END-->/g, "$1");
                                                            }
                                                        });
                                                        // Add warning when switching to visual mode
                                                        editor.on("activate", function() {
                                                            var content = editor.getContent();
                                                            if (content.indexOf("{?") !== -1) {
                                                                if (confirm("Warning: This template contains conditional placeholders that may be corrupted in Visual mode. Switch to Text mode to preserve the syntax. Continue anyway?")) {
                                                                    return true;
                                                                } else {
                                                                    // Switch back to text mode
                                                                    switchEditors.go("dd-notification-message", "html");
                                                                    return false;
                                                                }
                                                            }
                                                        });
                                                    }',
                                                ],
                                            ]);
                                            ?>
                                            <p class="description"><?php esc_html_e('Use the placeholders above to customize your notification email. HTML is supported.', 'document-downloader'); ?></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="doc-search-tab-style" class="doc-search-tabpane" role="tabpanel" style="display:none;">
                        <p class="description" style="margin:8px 0 12px;">
                            <?php esc_html_e('Customize the frontend CSS used by the [wpe_document_search] shortcode. Uses a low-priority @layer so your theme can override it easily.', 'document-downloader'); ?>
                        </p>

                        <p>
                            <button type="button" class="button" id="doc-search-css-restore"><?php esc_html_e('Restore defaults', 'document-downloader'); ?></button>
                            <span class="description" style="margin-left:.5rem;"><?php esc_html_e('Restores the editor to the plugin’s default CSS. Click “Save Changes” to apply.', 'document-downloader'); ?></span>
                        </p>

                        <textarea id="doc-search-frontend-css" name="<?php echo esc_attr(self::OPTION); ?>[frontend_css]" rows="24" class="large-text code" style="width:100%;"><?php echo esc_textarea($opt['frontend_css']); ?></textarea>
                        <div id="doc-search-cm6-wrapper"></div>
                    </div>
                </div>

                <?php submit_button(__('Save Changes', 'document-downloader')); ?>
            </form>
        </div>

        <style>
        #doc-search-frontend-css{min-height:70vh;height:70vh;}
        .doc-search-placeholder {
            display: inline-block;
            background: #0073aa;
            color: white;
            padding: 4px 8px;
            margin: 2px 4px 2px 0;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            font-family: monospace;
        }
        .doc-search-placeholder:hover {
            background: #005a87;
        }
        #doc-search-placeholders {
            line-height: 1.8;
        }
        </style>

        <script type="module">
        (async () => {
          // Tabs
          const tabs = document.querySelectorAll('#doc-search-tabs .nav-tab');
          const panes = { 
            settings: document.getElementById('doc-search-tab-settings'), 
            notifications: document.getElementById('doc-search-tab-notifications'),
            style: document.getElementById('doc-search-tab-style') 
          };
          const selectTab = (k) => {
            tabs.forEach(t => { const a = t.dataset.tab === k; t.classList.toggle('nav-tab-active', a); t.setAttribute('aria-selected', a ? 'true' : 'false'); });
            for (const key in panes) panes[key].style.display = (key === k) ? 'block' : 'none';
            try { localStorage.setItem('ddSettingsTab', k); } catch(e){}
          };
          tabs.forEach(t => t.addEventListener('click', e => { e.preventDefault(); selectTab(t.dataset.tab); }));
          let initial = 'settings';
          try { const ls = localStorage.getItem('ddSettingsTab'); if (['settings', 'notifications', 'style'].includes(ls)) initial = ls; } catch(e){}
          if (location.hash === '#notifications') initial = 'notifications';
          else if (location.hash === '#style') initial = 'style';
          selectTab(initial);

          // Toggle notification email row and fields
          const notify = document.querySelector('input[name="<?php echo esc_js(self::OPTION); ?>[notify_email]"]');
          const row = document.getElementById('doc-search-notify-row');
          const fields = document.getElementById('doc-search-notification-fields');
          if (notify && row) {
            notify.addEventListener('change', () => { 
              const display = notify.checked ? '' : 'none';
              row.style.display = display;
              if (fields) fields.style.display = display;
            });
          }
          
          // Placeholder click to copy functionality
          document.querySelectorAll('.doc-search-placeholder').forEach(placeholder => {
            placeholder.addEventListener('click', function() {
              const text = this.dataset.placeholder;
              
              // Copy to clipboard
              if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
              } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
              }
              
              // Visual feedback
              const original = this.textContent;
              this.textContent = 'Copied!';
              setTimeout(() => { this.textContent = original; }, 1000);
            });
          });

          // CM6 (via esm.sh)
          const ta   = document.getElementById('doc-search-frontend-css');
          const wrap = document.getElementById('doc-search-cm6-wrapper');
          if (!ta || !wrap) return;
          const defaultCss = <?php echo wp_json_encode($default_css); ?>;
          ta.style.display = 'none';

          const [
            {EditorState},
            {EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter},
            {defaultHighlightStyle, syntaxHighlighting, indentOnInput, foldGutter, foldKeymap, syntaxTree},
            {history, historyKeymap, defaultKeymap, indentWithTab},
            {autocompletion, closeBrackets, closeBracketsKeymap, completionKeymap},
            {linter, lintGutter},
            {css},
            {dracula}
          ] = await Promise.all([
            import('https://esm.sh/@codemirror/state'),
            import('https://esm.sh/@codemirror/view'),
            import('https://esm.sh/@codemirror/language'),
            import('https://esm.sh/@codemirror/commands'),
            import('https://esm.sh/@codemirror/autocomplete'),
            import('https://esm.sh/@codemirror/lint'),
            import('https://esm.sh/@codemirror/lang-css'),
            import('https://esm.sh/@uiw/codemirror-theme-dracula'),
          ]);

          const parserLinter = (view) => {
            const diagnostics = [];
            syntaxTree(view.state).iterate({
              enter(node) { if (node.type.isError) diagnostics.push({from: node.from, to: node.to, severity: 'error', message: 'Syntax error'}); }
            });
            return diagnostics;
          };

          const heightTheme = EditorView.theme({ "&": { height: "70vh", border: "1px solid #2a2a2a", borderRadius: "6px" }, ".cm-scroller": { overflow: "auto" } }, {dark: true});

          const extensions = [
            dracula, heightTheme,
            lineNumbers(),
            highlightActiveLineGutter(),
            history(),
            foldGutter(),
            indentOnInput(),
            closeBrackets(),
            autocompletion(),
            highlightActiveLine(),
            syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
            keymap.of([ indentWithTab, ...defaultKeymap, ...historyKeymap, ...foldKeymap, ...closeBracketsKeymap, ...completionKeymap ]),
            css(),
            lintGutter(),
            linter(parserLinter)
          ];

          const view  = new EditorView({ state: EditorState.create({ doc: ta.value, extensions }), parent: wrap });

          // Restore defaults
          const restoreBtn = document.getElementById('doc-search-css-restore');
          if (restoreBtn) restoreBtn.addEventListener('click', e => {
            e.preventDefault();
            view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: defaultCss } });
            view.focus();
          });

          // Sync to textarea on submit
          const form = document.getElementById('doc-search-settings-form');
          if (form) form.addEventListener('submit', () => { ta.value = view.state.doc.toString(); });
        })().catch(err => {
          try {
            const ta = document.getElementById('doc-search-frontend-css');
            const wrap = document.getElementById('doc-search-cm6-wrapper');
            if (ta) ta.style.display = '';
            if (wrap) wrap.style.display = 'none';
            console.warn('CM6 failed to load, falling back to textarea:', err);
          } catch(e){}
        });
        </script>
        <?php
    }
}
