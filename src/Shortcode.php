<?php
namespace WP_Easy\DocumentDownloader;

defined('ABSPATH') || exit;

final class Shortcode
{
    public static function init(): void
    {
        add_shortcode('wpe_document_search', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function assets(): void
    {
        // Component JS
        wp_register_script(
            'dd-alpine-search',
            DD_PLUGIN_URL . 'assets/js/alpine-search.js',
            [],
            '2.0.3',
            false
        );
        if (function_exists('wp_script_add_data')) {
            wp_script_add_data('dd-alpine-search', 'defer', true);
        }

        // Alpine (optional)
        if (! wp_script_is('alpine', 'registered') && ! wp_script_is('alpinejs', 'registered')) {
            wp_register_script(
                'alpinejs',
                'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
                [],
                null,
                false
            );
            if (function_exists('wp_script_add_data')) {
                wp_script_add_data('alpinejs', 'defer', true);
            }
        }

        // Nonces + options for JS
        wp_localize_script('dd-alpine-search', 'DDRest', [
            'wpRestNonce' => wp_create_nonce('wp_rest'),
            'ddNonce'     => wp_create_nonce('dd_query'),
        ]);

        $opts = Settings::get_options();
        wp_localize_script('dd-alpine-search', 'DDOptions', [
            'requireEmail' => (bool)$opts['require_email'],
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

        wp_localize_script('dd-alpine-search', 'DDIconsInline', $icons_inline);

        // Frontend CSS (from settings)
        wp_register_style('dd-frontend', false, [], '2.0.0');
        $css = Settings::get_frontend_css();
        if (is_string($css) && $css !== '') {
            wp_add_inline_style('dd-frontend', $css);
        }
    }

    public static function render($atts = [], $content = ''): string
    {
        // Scripts / styles
        wp_enqueue_script('dd-alpine-search');
        $opts = Settings::get_options();
        if (empty($opts['disable_alpine'])) {
            if (wp_script_is('alpine', 'registered')) wp_enqueue_script('alpine');
            else wp_enqueue_script('alpinejs');
        }
        wp_enqueue_style('dd-frontend');

        $endpoint  = esc_url_raw(rest_url('document-downloader/v1/query'));
        $labels    = Settings::get_labels();
        $plural    = $labels['plural'] ?? 'Documents';
        $plural_lc = function_exists('mb_strtolower') ? mb_strtolower($plural) : strtolower($plural);

        // Shortcode attr + GET param (?tax=slug-a,slug-b) -> slug array
        $atts = shortcode_atts(['tax' => ''], $atts, 'wpe_document_search');
        $get_tax = isset($_GET['tax']) ? sanitize_text_field(wp_unslash($_GET['tax'])) : '';
        $tax_str = trim($atts['tax'] ?: $get_tax);
        $tax_slugs = array_values(array_filter(array_map('sanitize_title', preg_split('/\s*,\s*/', $tax_str, -1, PREG_SPLIT_NO_EMPTY))));

        // Pass tax slugs via data-attribute (safe JSON for HTML attr)
        $tax_json_attr = esc_attr(wp_json_encode($tax_slugs));

        ob_start(); ?>
<div
  class="dd dd--component"
  x-data="ddSearch('<?php echo esc_url($endpoint); ?>')"
  x-init="initFromData($el)"
  data-dd-tax="<?php echo $tax_json_attr; ?>"
>
  <label class="dd__label" for="dd-search-input">
    <?php echo esc_html( sprintf( __('Search %s', 'document-downloader'), $plural_lc ) ); ?>
  </label>

  <div class="dd__input-wrapper">
    <input
      id="dd-search-input"
      class="dd__input"
      type="search"
      placeholder="<?php esc_attr_e('Type at least 3 characters…', 'document-downloader'); ?>"
      x-model="query"
      @input="debouncedSearch()"
      autocomplete="off"
    />
    
    <!-- Spinner icon while loading -->
    <div class="dd__input-icon dd__input-icon--spinner" x-show="loading" x-cloak>
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
      class="dd__input-icon dd__input-icon--clear" 
      x-show="query.length > 0 && !loading" 
      @click="query = ''; results = [];"
      :aria-label="'<?php esc_attr_e('Clear search', 'document-downloader'); ?>'"
      x-cloak
    >
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>

  <div class="dd__statuswrap" aria-live="polite" role="status">
    <template x-if="query.length > 0 && query.length < 3">
      <p class="dd__status dd__status--hint"><?php esc_html_e('Please type at least 3 characters.', 'document-downloader'); ?></p>
    </template>

    <template x-if="loading">
      <p class="dd__status dd__status--loading"><?php esc_html_e('Searching…', 'document-downloader'); ?></p>
    </template>

    <template x-if="error">
      <p class="dd__status dd__status--error"><?php esc_html_e('Error: check internet connection', 'document-downloader'); ?></p>
    </template>

    <template x-if="!loading && !error && results.length === 0 && query.length >= 3">
      <p class="dd__status dd__status--empty"><?php esc_html_e('No matching documents.', 'document-downloader'); ?></p>
    </template>
  </div>

  <ul class="dd__list" :class="{ 'dd__list--visible': results.length > 0 }" aria-title="Document List">
    <template x-for="item in results" :key="item.id">
      <li class="dd__item">
        <button
          type="button"
          class="dd__button dd__button--doc"
          :data-ext="item.ext"
          @click="onItemClick(item)"
        >
          <span class="dd__icon" aria-hidden="true" x-html="iconFor(item.ext)"></span>
          <span class="dd__title" x-text="item.title"></span>
        </button>
      </li>
    </template>
  </ul>

  <dialog x-ref="dlg" class="dd__dialog" @click.self="$refs.dlg.close()">
    <button type="button" class="dd__dialog-close" @click="$refs.dlg.close()" aria-label="<?php esc_attr_e('Close', 'document-downloader'); ?>">✕</button>
    <form method="dialog" class="dd__dialog-form" @submit.prevent="submitEmailAndDownload()">
      <h3 class="dd__dialog-title"><?php esc_html_e('Enter your Email Address to download', 'document-downloader'); ?></h3>
      <p class="dd__dialog-file" x-text="pendingFileName"></p>

      <label class="dd__field">
        <span class="dd__field-label"><?php esc_html_e('Email address', 'document-downloader'); ?></span>
        <input type="email" class="dd__field-input" x-model="email" required @input="validateEmail()" placeholder="name@example.com" />
      </label>

      <div class="dd__actions">
        <button type="submit" class="dd__btn dd__btn--primary" :disabled="!emailValid || downloading">
          <span x-show="!downloading"><?php esc_html_e('Download', 'document-downloader'); ?></span>
          <span x-show="downloading"><?php esc_html_e('Working…', 'document-downloader'); ?></span>
        </button>
        <button type="button" class="dd__btn" @click="$refs.dlg.close()"><?php esc_html_e('Cancel', 'document-downloader'); ?></button>
      </div>
    </form>
  </dialog>
</div>
<?php
        return ob_get_clean();
    }
}
