<?php
namespace WP_Easy\DocumentDownloader;

defined('ABSPATH') || exit;

final class CPT
{
    // Keep the requested slug name for CPT (unchanged)
    public const POST_TYPE = 'das_document';
    // New taxonomy slug
    public const TAXONOMY  = 'document_type';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('init', [__CLASS__, 'register_taxonomy']);
    }

    public static function register_cpt(): void
    {
        $labels = Settings::get_labels(); // uses DD settings internally

        register_post_type(self::POST_TYPE, [
            'label'               => $labels['plural'],
            'labels'              => [
                'name'               => $labels['plural'],
                'singular_name'      => $labels['singular'],
                'add_new'            => __('Add New', 'document-downloader'),
                'add_new_item'       => sprintf(__('Add New %s', 'document-downloader'), $labels['singular']),
                'edit_item'          => sprintf(__('Edit %s', 'document-downloader'), $labels['singular']),
                'new_item'           => sprintf(__('New %s', 'document-downloader'), $labels['singular']),
                'view_item'          => sprintf(__('View %s', 'document-downloader'), $labels['singular']),
                'search_items'       => sprintf(__('Search %s', 'document-downloader'), $labels['plural']),
                'not_found'          => __('No items found.', 'document-downloader'),
                'not_found_in_trash' => __('No items found in Trash.', 'document-downloader'),
                'all_items'          => sprintf(__('All %s', 'document-downloader'), $labels['plural']),
            ],
            'public'              => true,
            'menu_position'       => 20,
            'menu_icon'           => 'dashicons-media-document',
            'supports'            => ['title'],   // title only; PDF meta under title
            'show_in_rest'        => true,
            'has_archive'         => false,
            'rewrite'             => ['slug' => self::POST_TYPE],
        ]);
    }

    public static function register_taxonomy(): void
    {
        $labels = [
            'name'              => __('Document Types', 'document-downloader'),
            'singular_name'     => __('Document Type', 'document-downloader'),
            'search_items'      => __('Search Document Types', 'document-downloader'),
            'all_items'         => __('All Document Types', 'document-downloader'),
            'edit_item'         => __('Edit Document Type', 'document-downloader'),
            'update_item'       => __('Update Document Type', 'document-downloader'),
            'add_new_item'      => __('Add New Document Type', 'document-downloader'),
            'new_item_name'     => __('New Document Type', 'document-downloader'),
            'menu_name'         => __('Document Types', 'document-downloader'),
        ];

        register_taxonomy(self::TAXONOMY, [self::POST_TYPE], [
            'hierarchical'      => true,   // behaves like categories
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => self::TAXONOMY],
        ]);
    }
}
