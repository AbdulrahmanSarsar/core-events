<?php

/**
 * Sub Event Custom Post Type Class.
 * 
 * Handles the registration of the 'sub_event' custom post type (sessions/workshops)
 * and customizes the columns displayed in the WordPress admin list table to show
 * the parent event relationship.
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
 * Class SubEvent
 *
 * Registers the post type and manages its admin interface columns.
 */
class SubEvent
{

    /**
     * Constructor.
     * 
     * Initializes actions and filters for the CPT and its custom columns.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register']);
        add_filter('manage_sub_event_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_sub_event_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
    }

    /**
     * Register the 'sub_event' Custom Post Type.
     *
     * @return void
     */
    public function register()
    {
        // i18n: Make all admin labels translatable.
        $labels = [
            'name'          => esc_html__('Sub Events', 'core-events-pro'),
            'singular_name' => esc_html__('Sub Event', 'core-events-pro'),
            'menu_name'     => esc_html__('Sub Events', 'core-events-pro'),
            'all_items'     => esc_html__('All Sub Events', 'core-events-pro')
        ];

        register_post_type('sub_event', [
            'labels'       => $labels,
            'public'       => true,
            'show_in_rest' => true, // Enables Gutenberg and REST API support.
            'supports'     => ['title', 'editor', 'thumbnail'],
            'show_in_menu' => 'edit.php?post_type=main_event', // Nested under Main Event menu.
            'menu_icon'    => 'dashicons-clock',
            'rewrite'      => ['slug' => 'sub-event'],
        ]);
    }

    /**
     * Add Custom Columns headers to the admin list table.
     * 
     * Inserts the 'Parent Event' column immediately after the 'Title' column.
     *
     * @param array $columns Existing columns array.
     * @return array Modified columns array.
     */
    public function set_custom_columns($columns)
    {
        $cols = $columns;
        $new  = [];

        foreach ($cols as $key => $title) {
            $new[$key] = $title;

            // Insert Parent column right after the Title column.
            if ($key === 'title') {
                // i18n: Translate custom column header.
                $new['cep_parent'] = esc_html__('Parent Event', 'core-events-pro');
            }
        }

        return $new;
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
        if ($column === 'cep_parent') {
            $parent_id = get_post_meta($post_id, '_cep_parent_id', true);

            if ($parent_id) {
                // Security: Escape the URL and the title, and enforce the ID as an absolute integer.
                printf(
                    '<a href="%s"><strong>%s</strong></a>',
                    esc_url(get_edit_post_link(absint($parent_id))),
                    esc_html(get_the_title(absint($parent_id)))
                );
            } else {
                // i18n & Security: Safely output the fallback translated text with the exact inline style.
                printf(
                    '<span style="color:red;">%s</span>',
                    esc_html__('Unlinked', 'core-events-pro')
                );
            }
        }
    }
}
