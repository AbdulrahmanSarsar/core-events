<?php

/**
 * Template Loader Helper Class.
 * 
 * Responsible for loading custom templates from the plugin directory
 * for the custom post types (main_event and sub_event) instead of relying
 * on the active theme's default templates.
 *
 * @package CoreEventsPro\Helpers
 * @since 4.0.0
 */

namespace CoreEventsPro\Helpers;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class TemplateLoader
 *
 * Hooks into the WordPress template hierarchy to serve plugin-specific templates.
 */
class TemplateLoader
{

    /**
     * Constructor.
     * 
     * Initializes the template_include filter to override default WordPress templates.
     */
    public function __construct()
    {
        add_filter('template_include', [$this, 'load_template']);
    }

    /**
     * Load custom templates for specific custom post types.
     * 
     * Checks if the current page is a single view for 'main_event' or 'sub_event'.
     * If so, it attempts to load the corresponding template file from the plugin's
     * 'templates' directory. If the file doesn't exist, it safely falls back to the default theme template.
     *
     * @param string $template The absolute path to the template determined by WordPress.
     * @return string The path to the plugin's custom template, or the original template if no match is found.
     */
    public function load_template($template)
    {

        // 1. Main Event Template
        if (is_singular('main_event')) {
            $file = CEP_PATH . 'templates/single-event.php';

            if (file_exists($file)) {
                return $file;
            }
        }

        // 2. Sub Event Template (New Feature) ✅
        if (is_singular('sub_event')) {
            $file = CEP_PATH . 'templates/single-sub-event.php';

            if (file_exists($file)) {
                return $file;
            }
        }

        // Return the original template if no custom template was matched or found.
        return $template;
    }
}
