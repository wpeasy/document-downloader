<?php
namespace WP_Easy\DocumentDownloader;

defined('ABSPATH') || exit;

final class Meta
{
    private static $dir_filter_enabled = false;

    public static function init(): void
    {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post_' . CPT::POST_TYPE, [__CLASS__, 'save'], 10, 2);

        // Allow extra mimes (images + office + pdf)
        add_filter('upload_mimes', [__CLASS__, 'extend_mimes']);
        // Enqueue media + our script for the field
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_media']);
        // Hook into media upload process to redirect files to documents folder
        add_filter('wp_handle_upload_prefilter', [__CLASS__, 'enable_upload_filter']);
        add_filter('wp_handle_upload', [__CLASS__, 'disable_upload_filter']);
        // Also handle sideloaded files
        add_filter('wp_handle_sideload_prefilter', [__CLASS__, 'enable_upload_filter']);
        add_filter('wp_handle_sideload', [__CLASS__, 'disable_upload_filter']);
    }

    public static function add_metabox(): void
    {
        add_meta_box(
            'dd_file_meta',
            __('Document File', 'document-downloader'),
            [__CLASS__, 'render_metabox'],
            CPT::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_metabox(\WP_Post $post): void
    {
        wp_nonce_field('doc_search_meta', 'doc_search_meta_nonce');
        $att_id = (int) get_post_meta($post->ID, DD_META_KEY, true);
        $url    = $att_id ? wp_get_attachment_url($att_id) : '';

        // UI: Select/Remove buttons and preview URL
        ?>
        <div id="doc-search-file-picker" style="margin: .5rem 0 0;">
            <p>
                <button type="button" class="button" id="doc-search-select"><?php esc_html_e('Select File', 'document-downloader'); ?></button>
                <button type="button" class="button" id="doc-search-remove" <?php disabled(!$att_id); ?>><?php esc_html_e('Remove', 'document-downloader'); ?></button>
            </p>
            <?php if ($url): ?>
                <p><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($url); ?></a></p>
            <?php endif; ?>
            <p class="description"><?php esc_html_e('Upload/select a file. It will be stored in /wp-content/uploads/documents.', 'document-downloader'); ?></p>
        </div>
        <script>
        (function($){
          $(function(){
            console.log('Document uploader initializing...');
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
              console.error('wp.media is not available');
              return;
            }
            const frame = wp.media({
              title: '<?php echo esc_js(__('Select a file', 'document-downloader')); ?>',
              multiple: false,
              library: {
                type: [
                  'application/pdf',
                  'application/msword',
                  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                  'application/vnd.ms-excel',
                  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                  'image/jpeg',
                  'image/png',
                  'image/gif',
                  'image/webp'
                ]
              }
            });
            $('#doc-search-select').on('click', function(e){
              console.log('Select file button clicked');
              e.preventDefault();
              frame.open();
            });
            frame.on('select', function(){
              console.log('File selected from media library');
              const file = frame.state().get('selection').first().toJSON();
              console.log('Selected file:', file);
              $('#doc-search-remove').prop('disabled', false);
              $('#doc-search-file-picker').find('p a').remove();
              $('#doc-search-file-picker').append('<p><a href="'+file.url+'" target="_blank" rel="noopener">'+file.url+'</a></p>');
              $('<input/>',{type:'hidden',name:'doc_search_file_id',value:file.id}).appendTo('#doc-search-file-picker');
            });
            $('#doc-search-remove').on('click', function(e){
              e.preventDefault();
              $('#doc-search-file-picker').find('input[name="doc_search_file_id"]').remove();
              $(this).prop('disabled', true);
              $('#doc-search-file-picker').find('p a').remove();
              $('<input/>',{type:'hidden',name:'doc_search_file_remove',value:'1'}).appendTo('#doc-search-file-picker');
            });
          });
        })(jQuery);
        </script>
        <?php
    }

    public static function save(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['doc_search_meta_nonce']) || !wp_verify_nonce($_POST['doc_search_meta_nonce'], 'doc_search_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['doc_search_file_remove'])) {
            delete_post_meta($post_id, DD_META_KEY);
            return;
        }

        if (isset($_POST['doc_search_file_id'])) {
            $att_id = (int) $_POST['doc_search_file_id'];
            if ($att_id > 0) {
                update_post_meta($post_id, DD_META_KEY, $att_id);
            }
        }
    }

    /** Enable upload directory filter only for our plugin uploads */
    public static function enable_upload_filter($file)
    {
        // Check if we're in the context of our custom post type
        if (self::is_our_upload_context()) {
            add_filter('upload_dir', [__CLASS__, 'filter_upload_dir']);
        }
        return $file;
    }

    /** Disable upload directory filter after upload */
    public static function disable_upload_filter($upload)
    {
        remove_filter('upload_dir', [__CLASS__, 'filter_upload_dir']);
        return $upload;
    }

    /** Check if we're uploading for our post type */
    private static function is_our_upload_context(): bool
    {
        // Check if we're on our post type's edit screen
        global $pagenow;
        if (($pagenow === 'post.php' || $pagenow === 'post-new.php')) {
            $post_id = isset($_GET['post']) ? (int)$_GET['post'] : 0;
            if ($post_id) {
                return get_post_type($post_id) === CPT::POST_TYPE;
            }
            // For new posts, check the post_type parameter
            if (isset($_GET['post_type']) && $_GET['post_type'] === CPT::POST_TYPE) {
                return true;
            }
        }
        
        // Check for AJAX uploads from our post type
        if (wp_doing_ajax()) {
            // Check POST data for post_id
            if (isset($_POST['post_id'])) {
                $post_id = (int)$_POST['post_id'];
                if ($post_id && get_post_type($post_id) === CPT::POST_TYPE) {
                    return true;
                }
            }
            // Check for media library uploads - look at referer
            if (isset($_SERVER['HTTP_REFERER'])) {
                $referer = $_SERVER['HTTP_REFERER'];
                // Check if referer contains our post type
                if (strpos($referer, 'post_type=' . CPT::POST_TYPE) !== false) {
                    return true;
                }
                // Check if referer is editing our post type
                if (preg_match('/post=(\d+)/', $referer, $matches)) {
                    $post_id = (int)$matches[1];
                    if (get_post_type($post_id) === CPT::POST_TYPE) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /** Put our files in /uploads/documents */
    public static function filter_upload_dir(array $dirs): array
    {
        $dirs['subdir'] = '/documents';
        $dirs['path']   = trailingslashit($dirs['basedir']) . 'documents';
        $dirs['url']    = trailingslashit($dirs['baseurl']) . 'documents';
        return $dirs;
    }

    /** Add allowed mimes (images + office + pdf) */
    public static function extend_mimes(array $mimes): array
    {
        $mimes['pdf']  = 'application/pdf';
        $mimes['doc']  = 'application/msword';
        $mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $mimes['xls']  = 'application/vnd.ms-excel';
        $mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        $mimes['jpg|jpeg|jpe'] = 'image/jpeg';
        $mimes['png']          = 'image/png';
        $mimes['gif']          = 'image/gif';
        $mimes['webp']         = 'image/webp';

        return $mimes;
    }

    public static function enqueue_media(string $hook): void
    {
        if ($hook !== 'post-new.php' && $hook !== 'post.php') return;
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== CPT::POST_TYPE) return;
        wp_enqueue_media();
    }
}
