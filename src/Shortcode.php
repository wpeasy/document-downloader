<?php
namespace WP_Easy\DocumentDownloader;

defined('ABSPATH') || exit;

final class Shortcode
{
    public static function init(): void
    {
        add_shortcode('wpe_document_search', [__CLASS__, 'render_search']);
        add_shortcode('wpe_document_list', [__CLASS__, 'render_list']);
        add_shortcode('wpe_document_pagination', [__CLASS__, 'render_pagination']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);

        // Prevent wptexturize from breaking Alpine.js expressions
        add_filter('no_texturize_shortcodes', [__CLASS__, 'disable_wptexturize']);
    }

    public static function disable_wptexturize($shortcodes): array
    {
        $shortcodes[] = 'wpe_document_search';
        $shortcodes[] = 'wpe_document_list';
        $shortcodes[] = 'wpe_document_pagination';
        return $shortcodes;
    }

    public static function assets(): void
    {
        // Component JS - load with defer in footer
        wp_register_script(
            'doc-search-alpine-search',
            DD_PLUGIN_URL . 'assets/js/doc-search-alpine-search.js',
            [],
            '2.0.5',
            true
        );

        wp_register_script(
            'doc-search-alpine-list',
            DD_PLUGIN_URL . 'assets/js/doc-search-alpine-list.js',
            [],
            '2.0.5',
            true
        );

        // Alpine (optional) - load in footer with both components as dependencies
        if (! wp_script_is('alpine', 'registered') && ! wp_script_is('alpinejs', 'registered')) {
            wp_register_script(
                'alpinejs',
                DD_PLUGIN_URL . 'assets/vendor/alpine.min.js',
                ['doc-search-alpine-search', 'doc-search-alpine-list'],
                '3.15.0',
                true
            );
            if (function_exists('wp_script_add_data')) {
                wp_script_add_data('alpinejs', 'defer', true);
            }
        }

        // Nonces + options for JS
        wp_localize_script('doc-search-alpine-search', 'DocSearchRest', [
            'wpRestNonce' => wp_create_nonce('wp_rest'),
            'ddNonce'     => wp_create_nonce('doc_search_query'),
        ]);

        $opts = Settings::get_options();
        wp_localize_script('doc-search-alpine-search', 'DocSearchOptions', [
            'requireEmail' => (bool)$opts['require_email'],
            'requireName'  => (bool)$opts['require_name'],
            'requirePhone' => (bool)$opts['require_phone'],
            'notifyEmail'  => (string)$opts['notification_email'],
            'logEndpoint'  => rest_url('document-downloader/v1/log'),
        ]);

        // Inline SVG icons (per-extension, including individual image types)
        $plugin_path = rtrim(DD_PLUGIN_PATH, '/\\') . '/';
        $icon_dir    = $plugin_path . 'assets/icons/';

        $read_svg = static function (string $filename) use ($icon_dir): string {
            $path = $icon_dir . $filename;
            if (! file_exists($path)) return '';
            $svg = (string) file_get_contents($path);
            // strip headers & width/height, force currentColor
            $svg = preg_replace('/<\?xml.*?\?>/is', '', $svg);
            $svg = preg_replace('/<!DOCTYPE.*?>/is', '', $svg);
            $svg = preg_replace('/\s(width|height)="[^"]*"/i', '', $svg);
            $svg = preg_replace('/stroke="(?!none)[^"]*"/i', 'stroke="currentColor"', $svg);
            $svg = preg_replace('/fill="(?!none)[^"]*"/i', 'fill="currentColor"', $svg);
            $svg = preg_replace('/<svg/i', '<svg aria-hidden="true" focusable="false"', $svg, 1);
            return trim($svg);
        };

        // Map extensions to their dedicated SVG files
        $map = [
            'pdf'  => 'file-type-pdf.svg',
            'doc'  => 'file-type-doc.svg',
            'docx' => 'file-type-docx.svg',
            'xls'  => 'file-type-xls.svg',
            'xlsx' => 'file-type-xlsx.svg',

            // image types (each with its own icon)
            'jpg'  => 'file-type-jpg.svg',
            'jpeg' => 'file-type-jpeg.svg',
            'png'  => 'file-type-png.svg',
            'webp' => 'file-type-webp.svg',
            'gif'  => 'file-type-gif.svg',
            'svg'  => 'file-type-svg.svg',

            // generic fallback
            'file' => 'file.svg',
        ];

        $icons_inline = [];
        foreach ($map as $ext => $file) {
            $svg = $read_svg($file);
            if ($svg === '') {
                // If a specific icon file is missing, fall back to the generic file icon only.
                $svg = $read_svg('file.svg');
            }
            $icons_inline[$ext] = $svg;
        }

        wp_localize_script('doc-search-alpine-search', 'DocSearchIconsInline', $icons_inline);

        // Localize for list script as well
        wp_localize_script('doc-search-alpine-list', 'DocSearchRest', [
            'wpRestNonce' => wp_create_nonce('wp_rest'),
            'ddNonce'     => wp_create_nonce('doc_search_query'),
        ]);

        wp_localize_script('doc-search-alpine-list', 'DocSearchOptions', [
            'requireEmail' => (bool)$opts['require_email'],
            'requireName'  => (bool)$opts['require_name'],
            'requirePhone' => (bool)$opts['require_phone'],
            'notifyEmail'  => (string)$opts['notification_email'],
            'logEndpoint'  => rest_url('document-downloader/v1/log'),
        ]);

        wp_localize_script('doc-search-alpine-list', 'DocSearchIconsInline', $icons_inline);

        // Frontend CSS (from settings)
        wp_register_style('doc-search-frontend', false, [], '2.0.0');
        $css = Settings::get_frontend_css();
        if (is_string($css) && $css !== '') {
            wp_add_inline_style('doc-search-frontend', $css);
        }
    }

    public static function render_search($atts = [], $content = ''): string
    {
        // Scripts / styles
        wp_enqueue_script('doc-search-alpine-search');
        $opts = Settings::get_options();
        if (empty($opts['disable_alpine'])) {
            if (wp_script_is('alpine', 'registered')) wp_enqueue_script('alpine');
            else wp_enqueue_script('alpinejs');
        }
        wp_enqueue_style('doc-search-frontend');

        $endpoint  = esc_url_raw(rest_url('document-downloader/v1/query'));
        $opts      = Settings::get_options();
        $labels    = Settings::get_labels();
        $plural    = $labels['plural'] ?? 'Documents';
        $plural_lc = function_exists('mb_strtolower') ? mb_strtolower($plural) : strtolower($plural);

        // Get custom settings with fallbacks
        $search_title = !empty($opts['search_title']) ? $opts['search_title'] : sprintf(__('Search %s', 'document-downloader'), $plural_lc);
        $min_chars = max(1, intval($opts['search_min_chars'] ?? 3));
        $placeholder = !empty($opts['search_placeholder']) ? $opts['search_placeholder'] : sprintf(__('Type at least %d characters...', 'document-downloader'), $min_chars);

        // Shortcode attributes with defaults
        $atts = shortcode_atts([
            'tax' => '',
            'id' => '',
            'paginate' => 'false',
            'rows_per_page' => '50',
            'page_count' => '10',
            'show_pagination' => 'true'
        ], $atts, 'wpe_document_search');
        $get_tax = isset($_GET['tax']) ? sanitize_text_field(wp_unslash($_GET['tax'])) : '';
        $tax_str = trim($atts['tax'] ?: $get_tax);
        $tax_slugs = array_values(array_filter(array_map('sanitize_title', preg_split('/\s*,\s*/', $tax_str, -1, PREG_SPLIT_NO_EMPTY))));

        // Generate unique ID if not provided
        $unique_id = !empty($atts['id']) ? sanitize_html_class($atts['id']) : 'doc-search-' . wp_rand(1000, 9999);
        
        // Parse pagination attributes
        $paginate = filter_var($atts['paginate'], FILTER_VALIDATE_BOOLEAN);
        $rows_per_page = max(1, intval($atts['rows_per_page']));
        $page_count = max(1, intval($atts['page_count']));
        $show_pagination = filter_var($atts['show_pagination'], FILTER_VALIDATE_BOOLEAN);
        
        // Pass tax slugs via data-attribute (safe JSON for HTML attr)
        $tax_json_attr = esc_attr(wp_json_encode($tax_slugs));
        
        // Pass pagination config to JavaScript
        $pagination_config = esc_attr(wp_json_encode([
            'enabled' => $paginate,
            'rowsPerPage' => $rows_per_page,
            'pageCount' => $page_count,
            'showPagination' => $show_pagination
        ]));

        ob_start(); ?>
<div
  id="<?php echo esc_attr($unique_id); ?>"
  class="doc-search doc-search-search"
  x-data="docSearchSearch('<?php echo esc_url($endpoint); ?>')"
  x-init="initFromData($el)"
  data-doc-search-tax="<?php echo $tax_json_attr; ?>"
  data-doc-search-pagination="<?php echo $pagination_config; ?>"
  data-doc-search-min-chars="<?php echo esc_attr($min_chars); ?>"
>
  <label class="doc-search__label" for="doc-search-search-input">
    <?php echo esc_html($search_title); ?>
  </label>

  <div class="doc-search__input-wrapper">
    <input
      id="doc-search-search-input"
      class="doc-search__input"
      type="search"
      placeholder="<?php echo esc_attr($placeholder); ?>"
      x-model="query"
      @input="debouncedSearch()"
      autocomplete="off"
    />
    
    <!-- Spinner icon while loading -->
    <div class="doc-search__input-icon doc-search__input-icon--spinner" x-show="loading" x-cloak>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="32" stroke-dashoffset="32">
          <animate attributeName="stroke-dasharray" dur="2s" values="0 32;16 16;0 32;0 32" repeatCount="indefinite"/>
          <animate attributeName="stroke-dashoffset" dur="2s" values="0;-16;-32;-32" repeatCount="indefinite"/>
        </circle>
      </svg>
    </div>
    
    <!-- Clear icon -->
    <button
      type="button"
      class="doc-search__input-icon doc-search__input-icon--clear"
      x-show="query.length > 0 && !loading"
      @click="query = ''; search();"
      aria-label="<?php echo esc_attr(__('Clear search', 'document-downloader')); ?>"
      x-cloak
    >
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>

  <div class="doc-search__statuswrap" aria-live="polite" role="status">
    <template x-if="query.length > 0 && query.length < minChars">
      <p class="doc-search__status doc-search__status--hint"><?php echo esc_html(sprintf(__('Please type at least %d characters.', 'document-downloader'), $min_chars)); ?></p>
    </template>

    <template x-if="loading">
      <p class="doc-search__status doc-search__status--loading"><?php esc_html_e('Searching…', 'document-downloader'); ?></p>
    </template>

    <template x-if="error">
      <p class="doc-search__status doc-search__status--error"><?php esc_html_e('Error: check internet connection', 'document-downloader'); ?></p>
    </template>

    <template x-if="!loading && !error && results.length === 0 && query.length >= minChars">
      <p class="doc-search__status doc-search__status--empty"><?php esc_html_e('No matching documents.', 'document-downloader'); ?></p>
    </template>
  </div>

  <div class="doc-search__list" :class="{ 'doc-search__list--visible': currentPageResults.length > 0 }" role="region" aria-label="Document List">
    <!-- Top pagination (if enabled) -->
    <?php echo self::render_pagination_html('top'); ?>

    <ul class="doc-search__list-items" role="list">
      <template x-for="item in currentPageResults" :key="item.id">
        <li class="doc-search__item">
          <button
            type="button"
            class="doc-search__button doc-search__button--doc"
            :data-ext="item.ext"
            @click="onItemClick(item)"
          >
            <span class="doc-search__icon" aria-hidden="true" x-html="iconFor(item.ext)"></span>
            <span class="doc-search__title" x-text="item.title"></span>
          </button>
        </li>
      </template>
    </ul>

    <!-- Bottom pagination (if enabled) -->
    <?php echo self::render_pagination_html('bottom'); ?>
  </div>

  <dialog x-ref="dlg" class="doc-search__dialog" @click.self="$refs.dlg.close()">
    <button type="button" class="doc-search__dialog-close" @click="$refs.dlg.close()" aria-label="<?php esc_attr_e('Close', 'document-downloader'); ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 6L6 18M6 6l12 12"/>
      </svg>
    </button>
    <form method="dialog" class="doc-search__dialog-form" @submit.prevent="submitEmailAndDownload()">
      <h3 class="doc-search__dialog-title"><?php esc_html_e('Enter your details to download', 'document-downloader'); ?></h3>
      <p class="doc-search__dialog-file" x-text="pendingFileName"></p>

      <label class="doc-search__field" x-show="requireName" :class="nameInvalid ? 'doc-search__field--invalid' : (name && name.length > 0 && !nameInvalid ? 'doc-search__field--valid' : '')">
        <span class="doc-search__field-label">
          <?php esc_html_e('Name', 'document-downloader'); ?> 
          <span class="doc-search__field-required" x-show="requireName">*</span>
        </span>
        <input type="text" class="doc-search__field-input" x-model="name" :required="requireName" @input="validateForm()" @blur="validateForm()" placeholder="Your name" />
        <span class="doc-search__field-message" x-show="requireName && nameInvalid" x-text="nameMessage"></span>
      </label>
      
      <label class="doc-search__field" x-show="requireEmail" :class="emailInvalid ? 'doc-search__field--invalid' : (email && email.length > 0 && !emailInvalid ? 'doc-search__field--valid' : '')">
        <span class="doc-search__field-label">
          <?php esc_html_e('Email address', 'document-downloader'); ?> 
          <span class="doc-search__field-required" x-show="requireEmail">*</span>
        </span>
        <input type="email" class="doc-search__field-input" x-model="email" :required="requireEmail" @input="validateForm()" @blur="validateForm()" placeholder="name@example.com" />
        <span class="doc-search__field-message" x-show="requireEmail && emailInvalid" x-text="emailMessage"></span>
      </label>
      
      <label class="doc-search__field" x-show="requirePhone" :class="phoneInvalid ? 'doc-search__field--invalid' : (phone && phone.length > 0 && !phoneInvalid ? 'doc-search__field--valid' : '')">
        <span class="doc-search__field-label">
          <?php esc_html_e('Phone number', 'document-downloader'); ?> 
          <span class="doc-search__field-required" x-show="requirePhone">*</span>
        </span>
        <input type="tel" class="doc-search__field-input" x-model="phone" :required="requirePhone" @input="validateForm()" @blur="validateForm()" placeholder="Your phone number" />
        <span class="doc-search__field-message" x-show="requirePhone && phoneInvalid" x-text="phoneMessage"></span>
      </label>

      <div class="doc-search__actions">
        <button type="submit" class="doc-search__btn doc-search__btn--primary" :disabled="!formValid || downloading">
          <span x-show="!downloading"><?php esc_html_e('Download', 'document-downloader'); ?></span>
          <span x-show="downloading"><?php esc_html_e('Working…', 'document-downloader'); ?></span>
        </button>
        <button type="button" class="doc-search__btn" @click="$refs.dlg.close()"><?php esc_html_e('Cancel', 'document-downloader'); ?></button>
      </div>
    </form>
  </dialog>
</div>
<?php
        $html = ob_get_clean();

        // Encode the entire output to bypass wptexturize, then decode with JavaScript
        $encoded = base64_encode($html);

        return '<div id="doc-search-wrapper-' . esc_attr($unique_id) . '"></div>
<script>
(function() {
    var wrapper = document.getElementById("doc-search-wrapper-' . esc_js($unique_id) . '");
    if (wrapper) {
        wrapper.outerHTML = atob("' . $encoded . '");
    }
})();
</script>';
    }

    public static function render_list($atts = [], $content = ''): string
    {
        // Scripts / styles
        wp_enqueue_script('doc-search-alpine-list');
        $opts = Settings::get_options();
        if (empty($opts['disable_alpine'])) {
            if (wp_script_is('alpine', 'registered')) wp_enqueue_script('alpine');
            else wp_enqueue_script('alpinejs');
        }
        wp_enqueue_style('doc-search-frontend');

        // Parse attributes with pagination defaults
        $atts = shortcode_atts([
            'tax' => '',
            'id' => '',
            'paginate' => 'false',
            'rows_per_page' => '50',
            'page_count' => '10',
            'show_pagination' => 'true'
        ], $atts);
        $tax_param = trim($atts['tax']);

        // Get taxonomy filter
        $tax_slugs = [];
        if ($tax_param !== '') {
            $raw = array_map('trim', explode(',', $tax_param));
            foreach ($raw as $slug) {
                if ($slug && term_exists($slug, CPT::TAXONOMY)) {
                    $tax_slugs[] = $slug;
                }
            }
        }

        // Build endpoints
        $endpoint = rest_url('document-downloader/v1/query');
        $log_endpoint = rest_url('document-downloader/v1/log');

        // Generate unique ID if not provided
        $unique_id = !empty($atts['id']) ? sanitize_html_class($atts['id']) : 'doc-search-list-' . wp_rand(1000, 9999);
        
        // Parse pagination attributes
        $paginate = filter_var($atts['paginate'], FILTER_VALIDATE_BOOLEAN);
        $rows_per_page = max(1, intval($atts['rows_per_page']));
        $page_count = max(1, intval($atts['page_count']));
        $show_pagination = filter_var($atts['show_pagination'], FILTER_VALIDATE_BOOLEAN);
        
        $plural = strtolower($opts['plural']);
        $tax_json_attr = esc_attr(wp_json_encode($tax_slugs));
        
        // Pass pagination config to JavaScript
        $pagination_config = esc_attr(wp_json_encode([
            'enabled' => $paginate,
            'rowsPerPage' => $rows_per_page,
            'pageCount' => $page_count,
            'showPagination' => $show_pagination
        ]));

        ob_start(); ?>
<div
  id="<?php echo esc_attr($unique_id); ?>"
  class="doc-search doc-search-list"
  x-data="docSearchList('<?php echo esc_url($endpoint); ?>')"
  x-init="initFromData($el); loadAllDocuments()"
  data-doc-search-tax="<?php echo $tax_json_attr; ?>"
  data-doc-search-pagination="<?php echo $pagination_config; ?>"
>

  <label class="doc-search__label" for="doc-search-list-input">
    <?php echo esc_html( sprintf( __('Filter %s', 'document-downloader'), $plural ) ); ?>
  </label>

  <div class="doc-search__input-wrapper">
    <input
      id="doc-search-list-input"
      class="doc-search__input"
      type="search"
      placeholder="<?php esc_attr_e('Filter documents…', 'document-downloader'); ?>"
      x-model="query"
      @input="debouncedFilter()"
      autocomplete="off"
    />
    
    <div class="doc-search__input-icon doc-search__input-icon--spinner" x-show="loading" x-cloak>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="32" stroke-dashoffset="32">
          <animate attributeName="stroke-dasharray" dur="2s" values="0 32;16 16;0 32;0 32" repeatCount="indefinite"/>
          <animate attributeName="stroke-dashoffset" dur="2s" values="0;-16;-32;-48" repeatCount="indefinite"/>
        </circle>
      </svg>
    </div>

    <button
      type="button"
      class="doc-search__input-icon doc-search__input-icon--clear"
      x-show="query.length > 0 && !loading"
      @click="query = ''; filterDocuments();"
      aria-label="<?php echo esc_attr(__('Clear filter', 'document-downloader')); ?>"
      x-cloak
    >
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 6L6 18M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <div class="doc-search__statuswrap" aria-live="polite" role="status">
    <template x-if="loading">
      <p class="doc-search__status doc-search__status--loading"><?php esc_html_e('Loading documents…', 'document-downloader'); ?></p>
    </template>

    <template x-if="error">
      <p class="doc-search__status doc-search__status--error"><?php esc_html_e('Error loading documents', 'document-downloader'); ?></p>
    </template>

    <template x-if="!loading && !error && filteredResults.length === 0 && allDocuments.length > 0">
      <p class="doc-search__status doc-search__status--empty"><?php esc_html_e('No documents match your filter.', 'document-downloader'); ?></p>
    </template>

    <template x-if="!loading && !error && allDocuments.length === 0">
      <p class="doc-search__status doc-search__status--empty"><?php esc_html_e('No documents found.', 'document-downloader'); ?></p>
    </template>
  </div>

  <div class="doc-search__list doc-search__list--static" x-show="currentPageResults.length > 0" role="region" aria-label="Document List">
    <!-- Top pagination (if enabled) -->
    <?php echo self::render_pagination_html('top'); ?>

    <ul class="doc-search__list-items" role="list">
      <template x-for="item in currentPageResults" :key="item.id">
        <li class="doc-search__item">
          <button
            type="button"
            class="doc-search__button doc-search__button--doc"
            :data-ext="item.ext"
            @click="onItemClick(item)"
          >
            <span class="doc-search__icon" aria-hidden="true" x-html="iconFor(item.ext)"></span>
            <span class="doc-search__title" x-text="item.title"></span>
          </button>
        </li>
      </template>
    </ul>

    <!-- Bottom pagination (if enabled) -->
    <?php echo self::render_pagination_html('bottom'); ?>
  </div>

  <dialog x-ref="dlg" class="doc-search__dialog" @click.self="$refs.dlg.close()">
    <button type="button" class="doc-search__dialog-close" @click="$refs.dlg.close()" aria-label="<?php esc_attr_e('Close', 'document-downloader'); ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 6L6 18M6 6l12 12"/>
      </svg>
    </button>
    <form method="dialog" class="doc-search__dialog-form" @submit.prevent="submitEmailAndDownload()">
      <h3 class="doc-search__dialog-title"><?php esc_html_e('Enter your details to download', 'document-downloader'); ?></h3>
      <p class="doc-search__dialog-file" x-text="pendingFileName"></p>

      <label class="doc-search__field" x-show="requireName" :class="nameInvalid ? 'doc-search__field--invalid' : (name && name.length > 0 && !nameInvalid ? 'doc-search__field--valid' : '')">
        <span class="doc-search__field-label">
          <?php esc_html_e('Name', 'document-downloader'); ?> 
          <span class="doc-search__field-required" x-show="requireName">*</span>
        </span>
        <input type="text" class="doc-search__field-input" x-model="name" :required="requireName" @input="validateForm()" @blur="validateForm()" placeholder="Your name" />
        <span class="doc-search__field-message" x-show="requireName && nameInvalid" x-text="nameMessage"></span>
      </label>
      
      <label class="doc-search__field" x-show="requireEmail" :class="emailInvalid ? 'doc-search__field--invalid' : (email && email.length > 0 && !emailInvalid ? 'doc-search__field--valid' : '')">
        <span class="doc-search__field-label">
          <?php esc_html_e('Email address', 'document-downloader'); ?> 
          <span class="doc-search__field-required" x-show="requireEmail">*</span>
        </span>
        <input type="email" class="doc-search__field-input" x-model="email" :required="requireEmail" @input="validateForm()" @blur="validateForm()" placeholder="name@example.com" />
        <span class="doc-search__field-message" x-show="requireEmail && emailInvalid" x-text="emailMessage"></span>
      </label>
      
      <label class="doc-search__field" x-show="requirePhone" :class="phoneInvalid ? 'doc-search__field--invalid' : (phone && phone.length > 0 && !phoneInvalid ? 'doc-search__field--valid' : '')">
        <span class="doc-search__field-label">
          <?php esc_html_e('Phone number', 'document-downloader'); ?> 
          <span class="doc-search__field-required" x-show="requirePhone">*</span>
        </span>
        <input type="tel" class="doc-search__field-input" x-model="phone" :required="requirePhone" @input="validateForm()" @blur="validateForm()" placeholder="Your phone number" />
        <span class="doc-search__field-message" x-show="requirePhone && phoneInvalid" x-text="phoneMessage"></span>
      </label>

      <div class="doc-search__actions">
        <button type="submit" class="doc-search__btn doc-search__btn--primary" :disabled="!formValid || downloading">
          <span x-show="!downloading"><?php esc_html_e('Download', 'document-downloader'); ?></span>
          <span x-show="downloading"><?php esc_html_e('Working…', 'document-downloader'); ?></span>
        </button>
        <button type="button" class="doc-search__btn" @click="$refs.dlg.close()"><?php esc_html_e('Cancel', 'document-downloader'); ?></button>
      </div>
    </form>
  </dialog>
</div>
<?php
        return ob_get_clean();
    }

    /**
     * Render pagination shortcode
     */
    public static function render_pagination($atts = [], $content = ''): string
    {
        $atts = shortcode_atts([
            'target_id' => ''
        ], $atts, 'wpe_document_pagination');

        $target_id = sanitize_html_class($atts['target_id']);
        if (empty($target_id)) {
            return '<!-- wpe_document_pagination: target_id is required -->';
        }

        ob_start(); ?>
<nav class="doc-search__pagination doc-search__pagination--hidden" data-pagination-target="<?php echo esc_attr($target_id); ?>" aria-label="<?php esc_attr_e('Document pagination', 'document-downloader'); ?>">
  <div class="doc-search__pagination-wrapper">
    <ul class="doc-search__pagination-list" role="list">
      <!-- Pagination will be populated by JavaScript -->
    </ul>
  </div>
</nav>
<?php
        return ob_get_clean();
    }

    /**
     * Render pagination HTML for automatic inclusion
     */
    public static function render_pagination_html(string $position = 'bottom'): string
    {
        ob_start(); ?>
<nav class="doc-search__pagination doc-search__pagination--<?php echo esc_attr($position); ?>" x-show="pagination.enabled && pagination.showPagination && pagination.totalPages > 1 && currentPageResults.length > 0" x-cloak style="display: none;">
  <div class="doc-search__pagination-wrapper">
    <ul class="doc-search__pagination-list" role="list" :aria-label="'<?php echo esc_js(__('Document pagination', 'document-downloader')); ?> ' + (pagination.currentPage + 1) + ' <?php echo esc_js(__('of', 'document-downloader')); ?> ' + pagination.totalPages">

    <!-- Previous button -->
    <li class="doc-search__pagination-item">
      <button
        type="button"
        class="doc-search__pagination-link doc-search__pagination-link--prev"
        @click="goToPage(pagination.currentPage - 1)"
        :disabled="pagination.currentPage === 0"
        aria-label="<?php echo esc_attr(__('Go to previous page', 'document-downloader')); ?>"
      >
        <span aria-hidden="true">&laquo;</span>
        <span class="doc-search__pagination-text"><?php esc_html_e('Prev', 'document-downloader'); ?></span>
      </button>
    </li>

    <!-- Page number buttons -->
    <template x-for="page in pagination.visiblePages" :key="page">
      <li class="doc-search__pagination-item">
        <button
          type="button"
          class="doc-search__pagination-link"
          :class="{ 'doc-search__pagination-link--current': page === pagination.currentPage + 1 }"
          @click="goToPage(page - 1)"
          :aria-label="'<?php echo esc_js(__('Go to page', 'document-downloader')); ?> ' + page"
          :aria-current="page === pagination.currentPage + 1 ? 'page' : null"
          x-text="page"
        ></button>
      </li>
    </template>

    <!-- Next button -->
    <li class="doc-search__pagination-item">
      <button
        type="button"
        class="doc-search__pagination-link doc-search__pagination-link--next"
        @click="goToPage(pagination.currentPage + 1)"
        :disabled="pagination.currentPage === pagination.totalPages - 1"
        aria-label="<?php echo esc_attr(__('Go to next page', 'document-downloader')); ?>"
      >
        <span class="doc-search__pagination-text"><?php esc_html_e('Next', 'document-downloader'); ?></span>
        <span aria-hidden="true">&raquo;</span>
      </button>
    </li>
    
    </ul>
  </div>
</nav>
<?php
        return ob_get_clean();
    }
}
