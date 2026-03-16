<?php

/**
 * Utility Helpers Class.
 * 
 * Provides static helper methods used across the plugin for formatting dates,
 * generating status HTML badges, and extracting clean post excerpts.
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
 * Class Utils
 *
 * A collection of reusable static functions for frontend and backend formatting.
 */
class Utils
{

    /**
     * Convert a raw date string to a localized readable format.
     *
     * @param string $date_string The raw date string.
     * @return string Formatted date based on WordPress settings, or an empty string if none provided.
     */
    public static function format_date($date_string)
    {
        if (! $date_string) {
            return '';
        }

        // date_i18n automatically translates months and days based on WP locale.
        return date_i18n(get_option('date_format'), strtotime($date_string));
    }

    /**
     * Generate HTML markup for a visual status badge.
     *
     * @param string $status The internal event status (e.g., 'upcoming', 'ongoing', 'finished').
     * @return string Safe HTML span element representing the status badge.
     */
    public static function get_status_badge($status)
    {
        // i18n & Security: Translating and escaping the human-readable labels.
        $labels = [
            'upcoming' => esc_html__('Upcoming', 'core-events-pro'),
            'ongoing'  => esc_html__('Happening Now', 'core-events-pro'),
            'finished' => esc_html__('Ended', 'core-events-pro')
        ];

        // Security: Sanitize the status to be used safely as a CSS class.
        $class = 'cep-badge-' . sanitize_html_class($status);

        // Fallback for an unknown status.
        $label = isset($labels[$status]) ? $labels[$status] : esc_html__('Unknown', 'core-events-pro');

        // Return the cleanly formatted HTML string.
        return sprintf(
            '<span class="cep-badge %s">%s</span>',
            esc_attr($class),
            $label // $label is already safely escaped via esc_html__ above.
        );
    }

    /**
     * Generate a clean, truncated excerpt from an event post.
     * 
     * Strips all HTML tags and shortcodes to prevent layout breakage before trimming.
     *
     * @param \WP_Post $post   The WordPress Post object.
     * @param int      $length The maximum number of words to return (default is 20).
     * @return string The sanitized and truncated text.
     */
    public static function get_excerpt($post, $length = 20)
    {
        // Use the explicit excerpt if available, otherwise fallback to the main content.
        $text = empty($post->post_excerpt) ? $post->post_content : $post->post_excerpt;

        // Security & Formatting: Force the length to be an absolute integer, strip tags/shortcodes.
        return wp_trim_words(strip_shortcodes(strip_tags($text)), absint($length));
    }
}
