<?php

/**
 * REST API Event Controller Class.
 *
 * Registers custom REST API endpoints to fetch main events, sub-events,
 * calendar data, and attendee statistics for external usage (Frontend/Apps).
 *
 * @package CoreEventsPro\Api
 * @since 4.2.0
 */

namespace CoreEventsPro\Api;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class EventController
 *
 * Manages the custom REST API routes for Core Events Pro.
 */
class EventController
{
    /**
     * API Namespace.
     *
     * @var string
     */
    private $namespace = 'events/v1';

    /**
     * Constructor.
     *
     * Initializes the REST API route registration hook.
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register custom REST routes.
     *
     * @return void
     */
    public function register_routes()
    {
        // Route: Get all main events.
        register_rest_route($this->namespace, '/main-events', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_main_events'],
            'permission_callback' => '__return_true', // Publicly accessible.
        ]);

        // Route: Get a single main event by ID.
        register_rest_route($this->namespace, '/main-events/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_single_event'],
            'permission_callback' => '__return_true',
        ]);

        // Route: Get sub-events associated with a specific main event.
        register_rest_route($this->namespace, '/main-events/(?P<id>\d+)/sub-events', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_sub_events_route'],
            'permission_callback' => '__return_true',
        ]);

        // Route: Get events formatted for the calendar view.
        register_rest_route($this->namespace, '/calendar', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_calendar_events'],
            'permission_callback' => '__return_true',
        ]);
    }

    // --- Helpers ---

    /**
     * Retrieve gallery image URLs based on attachment IDs.
     *
     * @param int $post_id The Event Post ID.
     * @return array List of image URLs.
     */
    private function get_gallery_urls($post_id)
    {
        $ids_str = get_post_meta(absint($post_id), '_cep_gallery_ids', true);
        if (empty($ids_str)) {
            return [];
        }

        $ids  = explode(',', $ids_str);
        $urls = [];

        foreach ($ids as $id) {
            $url = wp_get_attachment_url(absint(trim($id)));
            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Get attendee statistics for the API.
     *
     * @param int $event_id The Event Post ID.
     * @return array Capacity and registration statistics.
     */
    private function get_attendee_stats($event_id)
    {
        $event_id   = absint($event_id);
        $capacity   = (int) get_post_meta($event_id, '_cep_capacity', true);
        $registered = 0;

        if ($capacity > 0) {
            global $wpdb;
            $table = $wpdb->prefix . 'cep_attendees';

            // Security: Safely check for the table existence.
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
                // BUG FIX: Only count people whose status is 'confirmed' (or empty fallback)
                $registered = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND (status = 'confirmed' OR status = '')", $event_id));
            }
        }

        // i18n: Make the "unlimited" text translatable for API consumers.
        $unlimited_text = __('Unlimited', 'core-events-pro');

        return [
            'has_limit'  => $capacity > 0,
            'capacity'   => $capacity,
            'registered' => $registered,
            'available'  => $capacity > 0 ? max(0, $capacity - $registered) : $unlimited_text,
            'is_full'    => ($capacity > 0 && $registered >= $capacity)
        ];
    }

    /**
     * Fetch sub-events (sessions) belonging to a main event.
     *
     * @param int $parent_id The Main Event ID.
     * @return array List of formatted sub-events.
     */
    private function fetch_sub_events($parent_id)
    {
        $posts = get_posts([
            'post_type'   => 'sub_event',
            'meta_query'  => [
                [
                    'key'   => '_cep_parent_id',
                    'value' => absint($parent_id),
                ]
            ],
            'numberposts' => -1,
            'meta_key'    => '_cep_start',
            'orderby'     => 'meta_value',
            'order'       => 'ASC'
        ]);

        return array_map(function ($p) {
            $meta = get_post_meta($p->ID);
            return [
                'id'            => $p->ID,
                'title'         => $p->post_title,
                'content'       => wp_strip_all_tags($p->post_content),
                'thumbnail'     => get_the_post_thumbnail_url($p->ID, 'medium'),
                'custom_banner' => $meta['_cep_custom_banner'][0] ?? '',
                'start_date'    => $meta['_cep_start'][0] ?? '',
                'end_date'      => $meta['_cep_end'][0] ?? '',
                'location'      => $meta['_cep_location'][0] ?? '',
                'video_url'     => $meta['_cep_video_url'][0] ?? '',
                'gallery'       => $this->get_gallery_urls($p->ID),
                'overview'      => $meta['_cep_overview'][0] ?? '',
            ];
        }, $posts);
    }

    /**
     * Format raw event post data into a clean API response array.
     */
    private function format_event_data($post, $include_content = false)
    {
        $meta = get_post_meta($post->ID);
        $data = [
            'id'              => $post->ID,
            'title'           => $post->post_title,
            'url'             => get_permalink($post->ID),
            'overview'        => $meta['_cep_overview'][0] ?? '',
            'location'        => $meta['_cep_location'][0] ?? '',
            'start_date'      => $meta['_cep_start'][0] ?? '',
            'end_date'        => $meta['_cep_end'][0] ?? '',
            'status'          => $meta['_cep_status'][0] ?? 'upcoming',
            'color'           => $meta['_cep_color'][0] ?? '#3b82f6',
            'thumbnail'       => get_the_post_thumbnail_url($post->ID, 'full'),
            'custom_banner'   => $meta['_cep_custom_banner'][0] ?? '',
            'capacity_stats'  => $this->get_attendee_stats($post->ID),
            'is_recurring'    => ($meta['_cep_is_recurring'][0] ?? '0') === '1',
            'recurrence_type' => $meta['_cep_recurrence_type'][0] ?? '',
            'video_url'       => $meta['_cep_video_url'][0] ?? '',
            'gallery'         => $this->get_gallery_urls($post->ID),
            'categories'      => wp_get_post_terms($post->ID, 'event_cat', ['fields' => 'names']),
            'type'            => wp_get_post_terms($post->ID, 'event_type', ['fields' => 'names']),
        ];

        if ($include_content) {
            $data['content_raw']  = wp_strip_all_tags($post->post_content);
            $data['content_html'] = apply_filters('the_content', $post->post_content);
        }

        return $data;
    }

    // --- Endpoints ---

    public function get_main_events($request)
    {
        $events = get_posts([
            'post_type'   => 'main_event',
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);

        $data = array_map(function ($post) {
            $event_data = $this->format_event_data($post, false);
            $event_data['sub_events'] = $this->fetch_sub_events($post->ID);
            return $event_data;
        }, $events);

        return rest_ensure_response($data);
    }

    public function get_single_event($request)
    {
        $post_id = absint($request['id']);
        $post    = get_post($post_id);

        if (! $post || $post->post_type !== 'main_event') {
            return new \WP_Error('no_event', __('Event not found.', 'core-events-pro'), ['status' => 404]);
        }

        $data = $this->format_event_data($post, true);
        $data['sub_events'] = $this->fetch_sub_events($post->ID);

        return rest_ensure_response($data);
    }

    public function get_sub_events_route($request)
    {
        $post_id = absint($request['id']);
        return rest_ensure_response($this->fetch_sub_events($post_id));
    }

    /**
     * Callback: Get all events formatted for a calendar view.
     * ✅ BUG FIX: Smart Recurrence generation now correctly handles the saved '1' or '0' string.
     */
    public function get_calendar_events($request)
    {
        $month_param = sanitize_text_field($request->get_param('month'));
        $month       = $month_param ?: gmdate('Y-m');
        $month_start = gmdate('Y-m-01', strtotime($month));
        $month_end   = gmdate('Y-m-t', strtotime($month));

        $args = [
            'post_type'      => ['main_event', 'sub_event'],
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ];

        $cat = sanitize_text_field($request->get_param('category'));
        if ($cat) {
            $args['tax_query'][] = [
                'taxonomy' => 'event_cat',
                'field'    => 'slug',
                'terms'    => $cat
            ];
        }

        $all_events = get_posts($args);
        $data       = [];

        foreach ($all_events as $post) {
            $raw_start = get_post_meta($post->ID, '_cep_start', true);
            $raw_end   = get_post_meta($post->ID, '_cep_end', true);

            if (empty($raw_start)) {
                continue;
            }

            $color        = '#3b82f6';
            $title_prefix = '';

            if ($post->post_type === 'main_event') {
                $color = get_post_meta($post->ID, '_cep_color', true) ?: '#3b82f6';
            } else {
                $parent_id = get_post_meta($post->ID, '_cep_parent_id', true);
                if ($parent_id) {
                    $parent_color = get_post_meta($parent_id, '_cep_color', true);
                    if ($parent_color) $color = $parent_color;
                }
                $title_prefix = '• ';
            }

            // ✅ FIX: Strict check for '1' (which is what our new MetaBox logic saves)
            $is_recurring = get_post_meta($post->ID, '_cep_is_recurring', true);

            if ($is_recurring === '1') {
                $recurrence_type = get_post_meta($post->ID, '_cep_recurrence_type', true);

                // Calculate duration to apply to each cloned occurrence
                $event_duration  = (strtotime($raw_end ?: $raw_start) - strtotime($raw_start));

                $current_start = strtotime($raw_start);
                // We check until the end of the currently viewed calendar month
                $view_end_time = strtotime($month_end . ' 23:59:59');

                // Safety net: Max 5 years into the future to prevent infinite loop crash
                $limit = strtotime('+5 years', strtotime($raw_start));

                while ($current_start <= $view_end_time && $current_start <= $limit) {
                    $current_start_date = gmdate('Y-m-d', $current_start);
                    $current_end_date   = gmdate('Y-m-d', $current_start + $event_duration);

                    // If this specific occurrence touches the currently viewed calendar month, add it!
                    if ($current_start_date <= $month_end && $current_end_date >= $month_start) {
                        $data[] = [
                            'id'       => $post->ID,
                            'title'    => $title_prefix . $post->post_title,
                            'start'    => $current_start_date,
                            'end'      => $current_end_date,
                            'color'    => $color,
                            'url'      => get_permalink($post->ID),
                            'location' => get_post_meta($post->ID, '_cep_location', true),
                            'type'     => $post->post_type
                        ];
                    }

                    // Increment the date strictly based on recurrence type
                    switch ($recurrence_type) {
                        case 'daily':
                            $current_start = strtotime('+1 day', $current_start);
                            break;
                        case 'weekly':
                            $current_start = strtotime('+1 week', $current_start);
                            break;
                        case 'monthly':
                            $current_start = strtotime('+1 month', $current_start);
                            break;
                        case 'yearly':
                            $current_start = strtotime('+1 year', $current_start);
                            break;
                        default:
                            $current_start = $view_end_time + 1; // Break loop if unknown
                    }
                }
            } else {
                // NORMAL EVENT (Non-recurring)
                $clean_start = gmdate('Y-m-d', strtotime($raw_start));
                $clean_end   = ! empty($raw_end) ? gmdate('Y-m-d', strtotime($raw_end)) : $clean_start;

                if ($clean_start <= $month_end && $clean_end >= $month_start) {
                    $data[] = [
                        'id'       => $post->ID,
                        'title'    => $title_prefix . $post->post_title,
                        'start'    => $clean_start,
                        'end'      => $clean_end,
                        'color'    => $color,
                        'url'      => get_permalink($post->ID),
                        'location' => get_post_meta($post->ID, '_cep_location', true),
                        'type'     => $post->post_type
                    ];
                }
            }
        }

        return rest_ensure_response($data);
    }
}
