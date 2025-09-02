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
            'notify_email'        => 0,
            'notification_email'  => '',
            'frontend_css'        => self::default_css(),
        ];
    }

    /** Default frontend CSS (BEM `.dd__*`) */
    private static function default_css(): string
    {
        return <<<CSS
/* Low-priority cascade layer so themes override easily */
@layer dd {
  .dd {
    --dd-status-lines: 1;
    --dd-status-lh: 1.25;
    max-width: 640px;
    margin: 0;
  }

  .dd__label { display:block; margin-bottom:.25rem; font-weight:600; }

  .dd__input {
    width:100%;
    padding:.5rem .75rem;
    border:1px solid #ccc;
    border-radius:6px;
  }
  .dd__input:focus { outline:none; border-color:#999; }

  /* Fixed-height status to avoid layout shift */
  .dd__statuswrap {
    display:flex;
    align-items:center;
    line-height: var(--dd-status-lh);
    min-block-size: calc(var(--dd-status-lines) * 1em * var(--dd-status-lh));
  }

  .dd__status { margin:0; font-style:italic; opacity:.85; }

  .dd__list { list-style:none; padding:0; margin:.75rem 0 0 0; }
  .dd__item { margin:0 0 .5rem 0; }

  .dd__button {
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
  .dd__button:hover { background:#f7f7f7; }
  .dd__button:disabled { opacity:.6; cursor:not-allowed; }

  .dd__icon { line-height:0; display:inline-flex; }
  .dd__icon svg { height:1.3em; width:auto; display:block; }

  .dd__title { line-height:1.2; }

  /* Dialog */
  .dd__dialog { border:1px solid #ddd; border-radius:12px; padding:1rem; max-width:420px; width:calc(100% - 2rem); }
  .dd__dialog::backdrop { background:rgba(0,0,0,.45); }
  .dd__dialog-title { margin:0 0 .25rem; font-weight:600; font-size:1.05rem; }
  .dd__dialog-file { margin:0 0 .75rem; opacity:.8; word-break:break-all; }
  .dd__dialog-close { position:absolute; top:.5rem; right:.5rem; border:0; background:transparent; font-size:1rem; line-height:1; cursor:pointer; }

  .dd__field { display:block; margin:0 0 .75rem; }
  .dd__field-label { display:block; font-size:.9rem; margin-bottom:.25rem; }
  .dd__field-input { width:100%; padding:.5rem .75rem; border:1px solid #ccc; border-radius:6px; }

  .dd__actions { display:flex; gap:.5rem; }
  .dd__btn { border:1px solid #ddd; background:#fff; padding:.5rem .9rem; border-radius:8px; cursor:pointer; }
  .dd__btn--primary { border-color:#222; background:#222; color:#fff; }
  .dd__btn:disabled { opacity:.6; cursor:not-allowed; }
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
            'notify_email'        => (int)!empty($opt['notify_email']),
            'notification_email'  => sanitize_email((string)$opt['notification_email']),
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
            'notify_email'        => !empty($value['notify_email']) ? 1 : 0,
            'notification_email'  => isset($value['notification_email']) ? sanitize_email($value['notification_email']) : '',
            'frontend_css'        => isset($value['frontend_css']) ? preg_replace("/^\xEF\xBB\xBF/", '', (string)$value['frontend_css']) : $d['frontend_css'],
        ];
        if ($out['plural'] === '')   $out['plural']   = $d['plural'];
        if ($out['singular'] === '') $out['singular'] = $d['singular'];
        return $out;
    }

    public static function maybe_enqueue_assets(): void
    {
        $is_our_page = isset($_GET['page']) && $_GET['page'] === 'dd_document_settings'; // phpcs:ignore WordPress.Security.NonceVerification
        if (! $is_our_page) return;

        $handle = 'dd-cm6-admin';
        wp_register_style($handle, false, [], '1.0');
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, '#dd-cm6-wrapper{height:70vh} #dd-frontend-css{min-height:70vh;height:70vh;}');
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

            <form method="post" action="options.php" id="dd-settings-form">
                <?php settings_fields('dd_settings_group'); ?>

                <h2 class="nav-tab-wrapper" id="dd-tabs" role="tablist" style="margin-bottom:.75rem;">
                    <a href="#settings" class="nav-tab nav-tab-active" role="tab" aria-selected="true" data-tab="settings"><?php esc_html_e('Settings', 'document-downloader'); ?></a>
                    <a href="#style" class="nav-tab" role="tab" aria-selected="false" data-tab="style"><?php esc_html_e('Style', 'document-downloader'); ?></a>
                </h2>

                <div id="dd-tabpanes">
                    <div id="dd-tab-settings" class="dd-tabpane" role="tabpanel" style="display:block;">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="dd-plural"><?php esc_html_e('Post Type Plural Name', 'document-downloader'); ?></label></th>
                                    <td><?php self::field_text('plural', $opt['plural'], 'Documents', 'id="dd-plural"'); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="dd-singular"><?php esc_html_e('Post Type Singular Name', 'document-downloader'); ?></label></th>
                                    <td><?php self::field_text('singular', $opt['singular'], 'Document', 'id="dd-singular"'); ?></td>
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
                                    <th scope="row"><?php esc_html_e('Notify by email', 'document-downloader'); ?></th>
                                    <td><?php self::field_checkbox('notify_email', (int)$opt['notify_email'], __('Send email notification when documents are downloaded.', 'document-downloader')); ?></td>
                                </tr>
                                <tr id="dd-notify-row" <?php echo $opt['notify_email'] ? '' : 'style="display:none"'; ?>>
                                    <th scope="row"><label for="dd-notify-email"><?php esc_html_e('Notification Email Address', 'document-downloader'); ?></label></th>
                                    <td><?php self::field_email('notification_email', $opt['notification_email'], 'name@example.com'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="dd-tab-style" class="dd-tabpane" role="tabpanel" style="display:none;">
                        <p class="description" style="margin:8px 0 12px;">
                            <?php esc_html_e('Customize the frontend CSS used by the [wpe_document_search] shortcode. Uses a low-priority @layer so your theme can override it easily.', 'document-downloader'); ?>
                        </p>

                        <p>
                            <button type="button" class="button" id="dd-css-restore"><?php esc_html_e('Restore defaults', 'document-downloader'); ?></button>
                            <span class="description" style="margin-left:.5rem;"><?php esc_html_e('Restores the editor to the plugin’s default CSS. Click “Save Changes” to apply.', 'document-downloader'); ?></span>
                        </p>

                        <textarea id="dd-frontend-css" name="<?php echo esc_attr(self::OPTION); ?>[frontend_css]" rows="24" class="large-text code" style="width:100%;"><?php echo esc_textarea($opt['frontend_css']); ?></textarea>
                        <div id="dd-cm6-wrapper"></div>
                    </div>
                </div>

                <?php submit_button(__('Save Changes', 'document-downloader')); ?>
            </form>
        </div>

        <style>#dd-frontend-css{min-height:70vh;height:70vh;}</style>

        <script type="module">
        (async () => {
          // Tabs
          const tabs = document.querySelectorAll('#dd-tabs .nav-tab');
          const panes = { settings: document.getElementById('dd-tab-settings'), style: document.getElementById('dd-tab-style') };
          const selectTab = (k) => {
            tabs.forEach(t => { const a = t.dataset.tab === k; t.classList.toggle('nav-tab-active', a); t.setAttribute('aria-selected', a ? 'true' : 'false'); });
            for (const key in panes) panes[key].style.display = (key === k) ? 'block' : 'none';
            try { localStorage.setItem('ddSettingsTab', k); } catch(e){}
          };
          tabs.forEach(t => t.addEventListener('click', e => { e.preventDefault(); selectTab(t.dataset.tab); }));
          let initial = 'settings';
          try { const ls = localStorage.getItem('ddSettingsTab'); if (ls === 'style' || ls === 'settings') initial = ls; } catch(e){}
          if (location.hash === '#style') initial = 'style';
          selectTab(initial);

          // Toggle notification email row
          const notify = document.querySelector('input[name="<?php echo esc_js(self::OPTION); ?>[notify_email]"]');
          const row = document.getElementById('dd-notify-row');
          if (notify && row) notify.addEventListener('change', () => { row.style.display = notify.checked ? '' : 'none'; });

          // CM6 (via esm.sh)
          const ta   = document.getElementById('dd-frontend-css');
          const wrap = document.getElementById('dd-cm6-wrapper');
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
          const restoreBtn = document.getElementById('dd-css-restore');
          if (restoreBtn) restoreBtn.addEventListener('click', e => {
            e.preventDefault();
            view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: defaultCss } });
            view.focus();
          });

          // Sync to textarea on submit
          const form = document.getElementById('dd-settings-form');
          if (form) form.addEventListener('submit', () => { ta.value = view.state.doc.toString(); });
        })().catch(err => {
          try {
            const ta = document.getElementById('dd-frontend-css');
            const wrap = document.getElementById('dd-cm6-wrapper');
            if (ta) ta.style.display = '';
            if (wrap) wrap.style.display = 'none';
            console.warn('CM6 failed to load, falling back to textarea:', err);
          } catch(e){}
        });
        </script>
        <?php
    }
}
