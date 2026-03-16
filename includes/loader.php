<?php

/**
 * Custom Post Types and Taxonomies Registration.
 * 
 * Handles the registration of 'main_event' and 'sub_event' custom post types,
 * along with the 'event_cat' taxonomy for categorized events.
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
 * Registers the Main Event custom post type and its associated taxonomy.
 */
class MainEvent
{

    /**
     * Constructor.
     * 
     * Hooks the registration method into the WordPress 'init' action.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    /**
     * Register the 'main_event' CPT and 'event_cat' Taxonomy.
     *
     * @return void
     */
    public function register()
    {

        // i18n: Make all admin labels translatable for the Main Event.
        $labels = [
            'name'          => esc_html__('Events', 'core-events-pro'),
            'singular_name' => esc_html__('Event', 'core-events-pro'),
            'menu_name'     => esc_html__('Events Manager', 'core-events-pro'),
            'add_new'       => esc_html__('Add New Event', 'core-events-pro'),
            'not_found'     => esc_html__('No events found', 'core-events-pro'),
        ];

        register_post_type('main_event', [
            'labels'          => $labels,
            'public'          => true,
            'show_in_rest'    => true, // Enables Gutenberg Editor Support.
            'supports'        => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon'       => 'dashicons-calendar-alt',
            'menu_position'   => 5,
            'rewrite'         => ['slug' => 'events'],
            'capability_type' => 'post',
        ]);

        // i18n: Translate taxonomy labels.
        $tax_labels = [
            'name' => esc_html__('Categories', 'core-events-pro'),
        ];

        // Register Taxonomy for Main Events.
        register_taxonomy('event_cat', 'main_event', [
            'labels'       => $tax_labels,
            'hierarchical' => true,
            'show_in_rest' => true,
        ]);
    }
}

/**
 * Class SubEvent
 *
 * Registers the Sub Event (Sessions) custom post type.
 */
class SubEvent
{

    /**
     * Constructor.
     * 
     * Hooks the registration method into the WordPress 'init' action.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    /**
     * Register the 'sub_event' CPT.
     *
     * @return void
     */
    public function register()
    {

        // i18n: Make all admin labels translatable for the Sub Event.
        $labels = [
            'name'          => esc_html__('Sessions / Sub-Events', 'core-events-pro'),
            'singular_name' => esc_html__('Session', 'core-events-pro'),
        ];

        register_post_type('sub_event', [
            'labels'       => $labels,
            'public'       => true,
            'show_in_rest' => true, // Enables Gutenberg Editor Support.
            'supports'     => ['title', 'editor', 'thumbnail'],
            'menu_icon'    => 'dashicons-clock',
            'show_in_menu' => 'edit.php?post_type=main_event', // Nested as a submenu of Main Events.
        ]);
    }
}
