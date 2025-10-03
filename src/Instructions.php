<?php
namespace WP_Easy\DocumentDownloader;

defined('ABSPATH') || exit;

final class Instructions
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'menu'], 30);
    }

    public static function menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . CPT::POST_TYPE,
            __('Instructions', 'document-downloader'),
            __('Instructions', 'document-downloader'),
            'edit_posts',
            'doc_search_instructions',
            [__CLASS__, 'render'],
            20 // Middle position
        );
    }

    public static function render(): void
    {
        ?>
        <style>
            .wrap .card {
                max-width: 1600px !important;
                width: 100%;
            }
        </style>
        <div class="wrap">
            <h1><?php esc_html_e('Document Downloader Instructions', 'document-downloader'); ?></h1>
            
            <div class="card">
                <h2><?php esc_html_e('Shortcode Usage', 'document-downloader'); ?></h2>
                
                <h3><?php esc_html_e('Basic Search Shortcode', 'document-downloader'); ?></h3>
                <p><?php esc_html_e('The search shortcode displays a search interface that shows results after typing at least 3 characters:', 'document-downloader'); ?></p>
                <code>[wpe_document_search]</code>
                <p class="description"><?php esc_html_e('This creates a search box that users can type into to find documents.', 'document-downloader'); ?></p>
                
                <h3><?php esc_html_e('Document List Shortcode', 'document-downloader'); ?></h3>
                <p><?php esc_html_e('The list shortcode displays all documents by default and allows filtering with the search box:', 'document-downloader'); ?></p>
                <code>[wpe_document_list]</code>
                <p class="description"><?php esc_html_e('This shows all documents immediately and filters them as users type.', 'document-downloader'); ?></p>
                
                <h3><?php esc_html_e('Taxonomy Filtering', 'document-downloader'); ?></h3>
                <p><?php esc_html_e('Both shortcodes support filtering by document types using the "tax" parameter:', 'document-downloader'); ?></p>
                <code>[wpe_document_search tax="brochures,manuals"]</code><br>
                <code>[wpe_document_list tax="brochures"]</code>
                
                <h4><?php esc_html_e('How to Find Taxonomy Slugs', 'document-downloader'); ?></h4>
                <ol>
                    <li><?php esc_html_e('Go to Documents &gt; Document Types in your admin menu', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Click on a document type to edit it', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Look at the "Slug" field - this is what you use in the tax parameter', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('For multiple types, separate them with commas: tax="type1,type2,type3"', 'document-downloader'); ?></li>
                </ol>
                
                <h3><?php esc_html_e('Pagination Options', 'document-downloader'); ?></h3>
                <p><?php esc_html_e('Both shortcodes support pagination to handle large document lists:', 'document-downloader'); ?></p>
                
                <h4><?php esc_html_e('Pagination Parameters', 'document-downloader'); ?></h4>
                <ul>
                    <li><strong>id:</strong> <?php esc_html_e('Unique identifier for the shortcode (used for external pagination)', 'document-downloader'); ?></li>
                    <li><strong>paginate:</strong> <?php esc_html_e('Enable/disable pagination (default: false)', 'document-downloader'); ?></li>
                    <li><strong>rows_per_page:</strong> <?php esc_html_e('Documents per page (default: 50)', 'document-downloader'); ?></li>
                    <li><strong>page_count:</strong> <?php esc_html_e('Number of page links to show (default: 10)', 'document-downloader'); ?></li>
                    <li><strong>show_pagination:</strong> <?php esc_html_e('Show automatic pagination controls (default: true)', 'document-downloader'); ?></li>
                </ul>
                
                <h4><?php esc_html_e('Pagination Examples', 'document-downloader'); ?></h4>
                
                <h5><?php esc_html_e('Document List Examples', 'document-downloader'); ?></h5>
                <code>[wpe_document_list paginate="true" rows_per_page="20"]</code><br>
                <p class="description"><?php esc_html_e('Shows 20 documents per page with automatic pagination controls', 'document-downloader'); ?></p>
                
                <code>[wpe_document_list id="my-list" paginate="true" page_count="5" show_pagination="false"]</code><br>
                <p class="description"><?php esc_html_e('Enables pagination but hides automatic controls (for custom pagination placement)', 'document-downloader'); ?></p>
                
                <h5><?php esc_html_e('Document Search Examples', 'document-downloader'); ?></h5>
                <code>[wpe_document_search paginate="true" rows_per_page="10" show_pagination="true"]</code><br>
                <p class="description"><?php esc_html_e('Shows 10 search results per page with pagination controls', 'document-downloader'); ?></p>
                
                <code>[wpe_document_search id="my-search" paginate="true" page_count="5" show_pagination="false"]</code><br>
                <p class="description"><?php esc_html_e('Enables search pagination but hides automatic controls (for custom pagination placement)', 'document-downloader'); ?></p>
                
                <h3><?php esc_html_e('Custom Pagination Placement', 'document-downloader'); ?></h3>
                <p><?php esc_html_e('You can place pagination controls anywhere using the pagination shortcode:', 'document-downloader'); ?></p>
                <code>[wpe_document_pagination target_id="my-search"]</code>
                <p class="description"><?php esc_html_e('Creates pagination controls that link to the shortcode with id="my-search"', 'document-downloader'); ?></p>
                
                <p><?php esc_html_e('This allows you to place pagination above the list, in sidebars, or anywhere else on the page.', 'document-downloader'); ?></p>
            </div>

            <div class="card">
                <h2><?php esc_html_e('Document Management', 'document-downloader'); ?></h2>
                
                <h3><?php esc_html_e('Adding Documents', 'document-downloader'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Go to Documents &gt; Add New', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Enter a title for your document', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Select a document type from the "Document Types" box', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('In the "Document File" section, click "Select File" to upload your document', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Documents are automatically stored in /wp-content/uploads/documents/', 'document-downloader'); ?></li>
                </ol>
                
                <h3><?php esc_html_e('Supported File Types', 'document-downloader'); ?></h3>
                <ul>
                    <li><?php esc_html_e('PDF documents (.pdf)', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Microsoft Word (.doc, .docx)', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Microsoft Excel (.xls, .xlsx)', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Images (.jpg, .jpeg, .png, .gif, .webp)', 'document-downloader'); ?></li>
                </ul>
            </div>

            <div class="card">
                <h2><?php esc_html_e('Settings & Configuration', 'document-downloader'); ?></h2>
                
                <h3><?php esc_html_e('General Settings', 'document-downloader'); ?></h3>
                <p><?php esc_html_e('Access settings at:', 'document-downloader'); ?> <strong><?php esc_html_e('Documents &gt; Settings', 'document-downloader'); ?></strong></p>
                <ul>
                    <li><strong><?php esc_html_e('Post Type Labels:', 'document-downloader'); ?></strong> <?php esc_html_e('Customize how "Documents" appears in your admin menu', 'document-downloader'); ?></li>
                    <li><strong><?php esc_html_e('Alpine.js:', 'document-downloader'); ?></strong> <?php esc_html_e('Disable if your theme already includes Alpine.js', 'document-downloader'); ?></li>
                    <li><strong><?php esc_html_e('Download Requirements:', 'document-downloader'); ?></strong> <?php esc_html_e('Require email, name, or phone before downloads', 'document-downloader'); ?></li>
                    <li><strong><?php esc_html_e('Search Exclusions:', 'document-downloader'); ?></strong> <?php esc_html_e('Words or phrases to exclude from search results', 'document-downloader'); ?></li>
                </ul>
                
                <h3><?php esc_html_e('Email Notifications', 'document-downloader'); ?></h3>
                <p><?php esc_html_e('Configure download notifications in the', 'document-downloader'); ?> <strong><?php esc_html_e('Notifications tab', 'document-downloader'); ?></strong> <?php esc_html_e('of settings:', 'document-downloader'); ?></p>
                <ul>
                    <li><?php esc_html_e('Get notified when someone downloads a document', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Customize the email subject and message', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Use placeholders like {email}, {name}, {filename} in your message', 'document-downloader'); ?></li>
                </ul>
                
                <h3><?php esc_html_e('Custom Styling', 'document-downloader'); ?></h3>
                <p><?php esc_html_e('Customize the appearance in the', 'document-downloader'); ?> <strong><?php esc_html_e('Style tab', 'document-downloader'); ?></strong> <?php esc_html_e('of settings:', 'document-downloader'); ?></p>
                <ul>
                    <li><?php esc_html_e('Uses CSS layers (@layer docSearch) so your theme can easily override styles', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Includes a CodeMirror editor with syntax highlighting', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Click "Restore defaults" to reset to original styling', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('All styles use BEM methodology with doc-search__ prefix', 'document-downloader'); ?></li>
                </ul>
            </div>

            <div class="card">
                <h2><?php esc_html_e('Download Tracking', 'document-downloader'); ?></h2>
                <p><?php esc_html_e('View download statistics at:', 'document-downloader'); ?> <strong><?php esc_html_e('Documents &gt; Downloads', 'document-downloader'); ?></strong></p>
                <ul>
                    <li><?php esc_html_e('See who downloaded what and when', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Export download data as CSV', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Clear download logs when needed', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Filter by date range and document type', 'document-downloader'); ?></li>
                </ul>
            </div>

            <div class="card">
                <h2><?php esc_html_e('Troubleshooting', 'document-downloader'); ?></h2>
                
                <h3><?php esc_html_e('Search Not Working', 'document-downloader'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Make sure you have published documents with attached files', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Check that Alpine.js is not disabled in settings if your theme doesn\'t include it', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Search requires at least 3 characters', 'document-downloader'); ?></li>
                </ul>
                
                <h3><?php esc_html_e('Download Issues', 'document-downloader'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Verify the file is properly attached to the document', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Check file permissions in /wp-content/uploads/documents/', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Ensure form validation is working if download requirements are enabled', 'document-downloader'); ?></li>
                </ul>
                
                <h3><?php esc_html_e('Styling Problems', 'document-downloader'); ?></h3>
                <ul>
                    <li><?php esc_html_e('CSS uses low-priority layers - your theme styles should override automatically', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Use browser developer tools to inspect element classes (doc-search__*)', 'document-downloader'); ?></li>
                    <li><?php esc_html_e('Try restoring default CSS and customizing from there', 'document-downloader'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}