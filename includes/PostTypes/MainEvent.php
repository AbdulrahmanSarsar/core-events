<?php

/**
 * Main Event Custom Post Type Class.
 * 
 * Handles the registration of the 'main_event' custom post type and 
 * customizes the columns displayed in the WordPress admin list table.
 *
 * @package CoreEventsPro\PostTypes
 * @since 4.0.0
 */

namespace CoreEventsPro\PostTypes;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class MainEvent
 *
 * Registers the post type and manages its admin interface columns.
 */
class MainEvent
{

    /**
     * Constructor.
     * 
     * Initializes actions and filters for the CPT and its columns.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register']);
        add_filter('manage_main_event_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_main_event_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
    }

    /**
     * Register the 'main_event' Custom Post Type.
     *
     * @return void
     */
    public function register()
    {
        // i18n: Make all admin labels translatable.
        $labels = [
            'name'          => esc_html__('Main Events', 'core-events-pro'),
            'singular_name' => esc_html__('Main Event', 'core-events-pro'),
            'menu_name'     => esc_html__('Events Pro', 'core-events-pro'),
            'add_new'       => esc_html__('Add Event', 'core-events-pro'),
            'add_new_item'  => esc_html__('Add New Main Event', 'core-events-pro'),
            'edit_item'     => esc_html__('Edit Main Event', 'core-events-pro'),
            'all_items'     => esc_html__('All Main Events', 'core-events-pro'),
        ];

        register_post_type('main_event', [
            'labels'        => $labels,
            'public'        => true,
            'has_archive'   => true,
            'show_in_rest'  => true, // Enables Gutenberg and REST API support.
            'supports'      => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon'     => 'dashicons-calendar-alt',
            'menu_position' => 5,
            'rewrite'       => ['slug' => 'events'],
        ]);
    }

    /**
     * Add Custom Columns headers to the admin list table.
     *
     * @param array $columns Existing columns array.
     * @return array Modified columns array.
     */
    public function set_custom_columns($columns)
    {
        $new_columns = [];

        // Maintain the checkbox and title columns at the beginning.
        $new_columns['cb']         = $columns['cb'];
        $new_columns['title']      = $columns['title'];

        // i18n: Translate custom column headers.
        $new_columns['cep_date']   = esc_html__('Event Dates', 'core-events-pro');
        $new_columns['cep_status'] = esc_html__('Status', 'core-events-pro');

        // Maintain the default date column at the end.
        $new_columns['date']       = $columns['date'];

        return $new_columns;
    }

    /**
     * Render the content for the Custom Columns.
     *
     * @param string $column  The name of the column to display.
     * @param int    $post_id The ID of the current post.
     * @return void
     */
    public function render_custom_columns($column, $post_id)
    {
        switch ($column) {
            case 'cep_date':
                $start = get_post_meta($post_id, '_cep_start', true);
                $end   = get_post_meta($post_id, '_cep_end', true);

                // Output start date safely.
                echo $start ? esc_html($start) : '&mdash;';

                // Output end date safely with a translatable separator.
                if ($end) {
                    echo ' ' . esc_html__('to', 'core-events-pro') . ' ' . esc_html($end);
                }
                break;

            case 'cep_status':
                $status = get_post_meta($post_id, '_cep_status', true);

                // Define status colors (Kept exactly as requested).
                $colors = [
                    'upcoming' => '#f39c12',
                    'ongoing'  => '#27ae60',
                    'finished' => '#7f8c8d'
                ];

                // i18n: Provide translatable labels for the status.
                $labels = [
                    'upcoming' => __('Upcoming', 'core-events-pro'),
                    'ongoing'  => __('Ongoing', 'core-events-pro'),
                    'finished' => __('Finished', 'core-events-pro')
                ];

                // Fallback color and text if status is undefined.
                $color         = isset($colors[$status]) ? $colors[$status] : '#ccc';
                $display_label = isset($labels[$status]) ? $labels[$status] : $status;

                // Security: Print the HTML string using printf to escape attributes and content safely.
                printf(
                    '<span style="background:%s; color:#fff; padding:3px 8px; border-radius:4px; font-size:11px; text-transform:uppercase;">%s</span>',
                    esc_attr($color),
                    esc_html($display_label)
                );
                break;
        }
    }
}
