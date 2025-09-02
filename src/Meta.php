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

        // Ensure uploads dir for our chooser
        add_filter('upload_dir', [__CLASS__, 'filter_upload_dir']);
        // Allow extra mimes (images + office + pdf)
        add_filter('upload_mimes', [__CLASS__, 'extend_mimes']);
        // Enqueue media + our script for the field
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_media']);
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
        wp_nonce_field('dd_meta', 'dd_meta_nonce');
        $att_id = (int) get_post_meta($post->ID, DD_META_KEY, true);
        $url    = $att_id ? wp_get_attachment_url($att_id) : '';

        // UI: Select/Remove buttons and preview URL
        ?>
        <div id="dd-file-picker" style="margin: .5rem 0 0;">
            <p>
                <button type="button" class="button" id="dd-select"><?php esc_html_e('Select File', 'document-downloader'); ?></button>
                <button type="button" class="button" id="dd-remove" <?php disabled(!$att_id); ?>><?php esc_html_e('Remove', 'document-downloader'); ?></button>
            </p>
            <?php if ($url): ?>
                <p><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($url); ?></a></p>
            <?php endif; ?>
            <p class="description"><?php esc_html_e('Upload/select a file. It will be stored in /wp-content/uploads/documents.', 'document-downloader'); ?></p>
        </div>
        <script>
        (function($){
          $(function(){
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
            $('#dd-select').on('click', function(e){
              e.preventDefault();
              frame.open();
            });
            frame.on('select', function(){
              const file = frame.state().get('selection').first().toJSON();
              $('#dd-remove').prop('disabled', false);
              $('#dd-file-picker').find('p a').remove();
              $('#dd-file-picker').append('<p><a href="'+file.url+'" target="_blank" rel="noopener">'+file.url+'</a></p>');
              $('<input/>',{type:'hidden',name:'dd_file_id',value:file.id}).appendTo('#dd-file-picker');
            });
            $('#dd-remove').on('click', function(e){
              e.preventDefault();
              $('#dd-file-picker').find('input[name="dd_file_id"]').remove();
              $(this).prop('disabled', true);
              $('#dd-file-picker').find('p a').remove();
              $('<input/>',{type:'hidden',name:'dd_file_remove',value:'1'}).appendTo('#dd-file-picker');
            });
          });
        })(jQuery);
        </script>
        <?php
    }

    public static function save(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['dd_meta_nonce']) || !wp_verify_nonce($_POST['dd_meta_nonce'], 'dd_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['dd_file_remove'])) {
            delete_post_meta($post_id, DD_META_KEY);
            return;
        }

        if (isset($_POST['dd_file_id'])) {
            $att_id = (int) $_POST['dd_file_id'];
            if ($att_id > 0) {
                update_post_meta($post_id, DD_META_KEY, $att_id);
            }
        }
    }

    /** Put our files in /uploads/documents (guarded) */
    public static function filter_upload_dir(array $dirs): array
    {
        // Keep base dirs, only swap subdir and url if present
        $sub = '/documents';
        $dirs['subdir'] = '/documents' . (isset($dirs['subdir']) && $dirs['subdir'] !== '' ? '' : '');
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
