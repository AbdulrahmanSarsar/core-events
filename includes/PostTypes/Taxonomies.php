<?php

/**
 * Custom Taxonomies Registration Class.
 * 
 * Handles the creation of custom taxonomies (Categories, Types, and Tags)
 * associated with the 'main_event' custom post type to allow better organization.
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
 * Class Taxonomies
 *
 * Registers the necessary taxonomies for event classification.
 */
class Taxonomies
{

    /**
     * Constructor.
     * 
     * Hooks the taxonomy registration into the WordPress 'init' action.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    /**
     * Register custom taxonomies.
     *
     * Registers Event Categories, Event Types, and Event Tags.
     *
     * @return void
     */
    public function register()
    {

        // 1. Event Category (Hierarchical - like default WP Categories)
        $cat_labels = [
            'name'          => esc_html__('Event Categories', 'core-events-pro'),
            'singular_name' => esc_html__('Category', 'core-events-pro')
        ];

        register_taxonomy('event_cat', ['main_event'], [
            'labels'       => $cat_labels,
            'hierarchical' => true,
            'show_in_rest' => true, // Enables Gutenberg and REST API support.
            'rewrite'      => ['slug' => 'event-category'],
        ]);

        // 2. Event Type (Hierarchical for better organization)
        $type_labels = [
            'name'          => esc_html__('Event Types', 'core-events-pro'),
            'singular_name' => esc_html__('Type', 'core-events-pro')
        ];

        register_taxonomy('event_type', ['main_event'], [
            'labels'       => $type_labels,
            'hierarchical' => true, // Set to true for better UI organization as requested.
            'show_in_rest' => true,
        ]);

        // 3. Event Tags (Non-Hierarchical - like default WP Tags)
        $tag_labels = [
            'name'          => esc_html__('Event Tags', 'core-events-pro'),
            'singular_name' => esc_html__('Tag', 'core-events-pro')
        ];

        register_taxonomy('event_tag', ['main_event'], [
            'labels'       => $tag_labels,
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);
    }
}
