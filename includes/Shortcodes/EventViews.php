<?php

/**
 * Event Views Shortcodes Class.
 * 
 * Registers and renders multiple shortcodes for displaying events,
 * including single event views, sub-events, lists, grouped lists,
 * and an advanced AJAX-powered search and filter system.
 *
 * @package CoreEventsPro\Shortcodes
 * @since 4.0.0
 */

namespace CoreEventsPro\Shortcodes;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class EventViews
 *
 * Handles frontend rendering logic for events.
 */
class EventViews
{

    /**
     * Constructor.
     * 
     * Initializes shortcodes and AJAX endpoints for filtering.
     */
    public function __construct()
    {
        // Shortcodes
        add_shortcode('main_event', [$this, 'render_single']);
        add_shortcode('sub_events', [$this, 'render_subs']);
        add_shortcode('events_list', [$this, 'render_list']);
        add_shortcode('next_event', [$this, 'render_next_event']);
        add_shortcode('events_grouped', [$this, 'render_grouped_list']);
        add_shortcode('events_advanced_filter', [$this, 'render_advanced_filter']); // ✅ Advanced Filter

        // AJAX Endpoints
        add_action('wp_ajax_cep_ajax_filter', [$this, 'ajax_filter_handler']);
        add_action('wp_ajax_nopriv_cep_ajax_filter', [$this, 'ajax_filter_handler']);
    }

	// --- 1. Advanced Filter (AJAX & Search) ---

    /**
     * Render the Advanced Filter Shortcode.
     * 
     * Displays a search bar, category dropdown, and status dropdown.
     * Uses AJAX to fetch and display results without page reload.
     *
     * @return string HTML output for the advanced filter.
     */
    public function render_advanced_filter()
    {
        ob_start();
        $categories = get_terms(['taxonomy' => 'event_cat', 'hide_empty' => false]);
?>
        <div class="cep-filter-container">
            <form id="cep-filter-form" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; background:#f8fafc; padding:20px; border-radius:8px;">

                <!-- Security Nonce for AJAX -->
                <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('cep_filter_nonce')); ?>">

                <input type="text" name="cep_search" placeholder="<?php esc_attr_e('🔍 Search events...', 'core-events-pro'); ?>" style="flex:1; min-width:200px; padding:10px; border:1px solid #cbd5e1; border-radius:4px;">

                <select name="cep_cat" style="padding:10px; border:1px solid #cbd5e1; border-radius:4px;">
                    <option value=""><?php esc_html_e('All Categories', 'core-events-pro'); ?></option>
                    <?php
                    if (! is_wp_error($categories) && ! empty($categories)) {
                        foreach ($categories as $cat) {
                            printf('<option value="%s">%s</option>', esc_attr($cat->slug), esc_html($cat->name));
                        }
                    }
                    ?>
                </select>

                <select name="cep_status" style="padding:10px; border:1px solid #cbd5e1; border-radius:4px;">
                    <option value=""><?php esc_html_e('All Statuses', 'core-events-pro'); ?></option>
                    <option value="upcoming"><?php esc_html_e('Upcoming', 'core-events-pro'); ?></option>
                    <option value="ongoing"><?php esc_html_e('Ongoing', 'core-events-pro'); ?></option>
                    <option value="finished"><?php esc_html_e('Finished', 'core-events-pro'); ?></option>
                </select>

                <button type="submit" style="padding:10px 20px; background:#2563eb; color:#fff; border:none; border-radius:4px; cursor:pointer;">
                    <?php esc_html_e('Filter', 'core-events-pro'); ?>
                </button>
            </form>

            <div id="cep-filter-results" class="cep-events-grid" style="--cep-cols: 3;"></div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                function fetchEvents() {
                    // i18n & Security: Translating and escaping JS text
                    var loadingText = '<?php echo esc_js(__('Searching...', 'core-events-pro')); ?>';
                    $('#cep-filter-results').html('<p style="text-align:center; width:100%;">' + loadingText + '</p>');

                    $.ajax({
                        url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                        type: 'POST',
                        data: $('#cep-filter-form').serialize() + '&action=cep_ajax_filter',
                        success: function(res) {
                            $('#cep-filter-results').html(res);
                        }
                    });
                }

                $('#cep-filter-form').on('submit', function(e) {
                    e.preventDefault();
                    fetchEvents();
                });

                $('#cep-filter-form select').on('change', function() {
                    fetchEvents();
                });

                // Load initial events
                fetchEvents();
            });
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * AJAX Handler for the Advanced Filter.
     * 
     * Processes the search query, category, and status filters, then outputs HTML.
     *
     * @return void
     */
    public function ajax_filter_handler()
    {
        // Security Check: Verify AJAX nonce.
        check_ajax_referer('cep_filter_nonce', 'security');

        $args = [
            'post_type'      => 'main_event',
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ];

        // Security: Unslash and sanitize all POST inputs.
        if (! empty($_POST['cep_search'])) {
            $args['s'] = sanitize_text_field(wp_unslash($_POST['cep_search']));
        }

        if (! empty($_POST['cep_status'])) {
            $args['meta_query'][] = [
                'key'   => '_cep_status',
                'value' => sanitize_text_field(wp_unslash($_POST['cep_status']))
            ];
        }

        if (! empty($_POST['cep_cat'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'event_cat',
                'field'    => 'slug',
                'terms'    => sanitize_text_field(wp_unslash($_POST['cep_cat']))
            ];
        }

        // Hide past events if the option is enabled and no specific status is selected.
        if (get_option('cep_hide_past_events', 0) && empty($_POST['cep_status'])) {
            $args['meta_query'][] = [
                'key'     => '_cep_status',
                'value'   => 'finished',
                'compare' => '!='
            ];
        }

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                // If loop-event.php exists in theme, use it. Otherwise, use fallback inline UI.
                if (locate_template('cep-templates/loop-event.php')) {
                    get_template_part('cep-templates/loop-event');
                } else {
                    $start_date = get_post_meta(get_the_ID(), '_cep_start', true);
                    printf(
                        '<div style="background:#fff; padding:15px; border:1px solid #eee; border-radius:8px; margin-bottom:10px;"><a href="%s" style="font-weight:bold; font-size:18px;">%s</a><br>📅 %s</div>',
                        esc_url(get_permalink()),
                        esc_html(get_the_title()),
                        esc_html($start_date)
                    );
                }
            }
        } else {
            // i18n: Translate "No events found"
            printf(
                '<p style="text-align:center; width:100%%;">%s</p>',
                esc_html__('No events found matching your criteria.', 'core-events-pro')
            );
        }

        wp_die();
    }

	// --- 2. Old Shortcodes ---

    /**
     * Render a single event by ID.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_single($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts);
        $id   = absint($atts['id']);

        if (! $id) return '';

        $post = get_post($id);
        if (! $post || $post->post_type !== 'main_event') {
            return esc_html__('Event not found.', 'core-events-pro');
        }

        $start = get_post_meta($post->ID, '_cep_start', true);
        $img   = get_the_post_thumbnail_url($post->ID, 'large');

        // i18n & HTML Construction
        $starts_text = esc_html__('Starts:', 'core-events-pro');
        $img_html    = $img ? sprintf("<img src='%s' class='cep-hero-img' style='max-width:100%%; height:auto;'>", esc_url($img)) : "";
        $content     = apply_filters('the_content', $post->post_content);

        return sprintf(
            "<div class='cep-single-card'>%s<h2>%s</h2><div class='cep-meta'>📅 %s %s</div><div class='cep-content'>%s</div></div>",
            $img_html,
            esc_html($post->post_title),
            $starts_text,
            esc_html($start),
            $content // Content is already filtered by WP, so it's safe to output.
        );
    }

    /**
     * Render sub-events for a specific main event.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_subs($atts)
    {
        $atts = shortcode_atts(['main_event_id' => 0], $atts);
        $main_event_id = absint($atts['main_event_id']);

        if (! $main_event_id) return '';

        $posts = get_posts([
            'post_type'   => 'sub_event',
            'meta_key'    => '_cep_parent_id',
            'meta_value'  => $main_event_id,
            'numberposts' => -1
        ]);

        if (empty($posts)) {
            return '<p>' . esc_html__('No sub-events found.', 'core-events-pro') . '</p>';
        }

        $out = '<div class="cep-sub-grid">';
        $view_details_text = esc_html__('View Details', 'core-events-pro');

        foreach ($posts as $p) {
            $out .= sprintf(
                "<div class='cep-sub-item'><h4>%s</h4><p>%s</p><a href='%s'>%s</a></div>",
                esc_html($p->post_title),
                esc_html(wp_trim_words($p->post_content, 10)),
                esc_url(get_permalink($p->ID)),
                $view_details_text
            );
        }

        return $out . '</div>';
    }

    /**
     * Render a list/grid of events based on status.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_list($atts)
    {
        $atts = shortcode_atts([
            'status' => 'upcoming',
            'limit'  => 6,
            'col'    => 3
        ], $atts);

        $args = [
            'post_type'      => 'main_event',
            'posts_per_page' => absint($atts['limit']),
            'meta_key'       => '_cep_status',
            'meta_value'     => sanitize_text_field($atts['status']),
            'orderby'        => 'meta_value',
            'order'          => 'ASC'
        ];

        if ($atts['status'] !== 'finished') {
            $args['meta_key'] = '_cep_start';
            $args['orderby']  = 'meta_value';
        }

        if (get_option('cep_hide_past_events', 0) && $atts['status'] != 'finished') {
            $args['meta_query'][] = [
                'key'     => '_cep_status',
                'value'   => 'finished',
                'compare' => '!='
            ];
        }

        $query = new \WP_Query($args);

        if (! $query->have_posts()) {
            return '<p class="cep-no-events">' . esc_html__('No events found.', 'core-events-pro') . '</p>';
        }

        ob_start();
        echo '<div class="cep-events-grid" style="display:grid; grid-template-columns: repeat(' . esc_attr(absint($atts['col'])) . ', 1fr); gap:20px;">';

        while ($query->have_posts()) {
            $query->the_post();

            if (locate_template('cep-templates/loop-event.php')) {
                get_template_part('cep-templates/loop-event');
            } else {
                $start_date = get_post_meta(get_the_ID(), '_cep_start', true);
                printf(
                    '<div style="background:#fff; padding:15px; border:1px solid #eee; border-radius:8px;"><a href="%s" style="font-weight:bold; font-size:18px;">%s</a><br>📅 %s</div>',
                    esc_url(get_permalink()),
                    esc_html(get_the_title()),
                    esc_html($start_date)
                );
            }
        }

        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Render a hero card for the very next upcoming event.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_next_event($atts)
    {
        $today = current_time('Y-m-d');

        $query = new \WP_Query([
            'post_type'      => 'main_event',
            'posts_per_page' => 1,
            'meta_key'       => '_cep_start',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                ['key' => '_cep_start', 'value' => $today, 'compare' => '>=']
            ]
        ]);

        if (! $query->have_posts()) return '';

        $query->the_post();
        $start = get_post_meta(get_the_ID(), '_cep_start', true);

        ob_start();
    ?>
        <div class="cep-next-event-card" style="background:#1e293b; color:#fff; padding:30px; border-radius:12px; text-align:center;">
            <div style="color:#38bdf8; font-weight:bold; text-transform:uppercase; margin-bottom:10px;">
                <?php esc_html_e('🚀 Next Upcoming Event', 'core-events-pro'); ?>
            </div>
            <h2>
                <a href="<?php echo esc_url(get_permalink()); ?>" style="color:#fff; text-decoration:none; font-size:2rem;">
                    <?php echo esc_html(get_the_title()); ?>
                </a>
            </h2>
            <div style="font-size:1.2rem; margin:15px 0;">
                📅 <?php echo date_i18n(get_option('date_format'), strtotime($start)); ?>
            </div>
            <a href="<?php echo esc_url(get_permalink()); ?>" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 25px; border-radius:30px; text-decoration:none; font-weight:bold;">
                <?php esc_html_e('Join Now', 'core-events-pro'); ?>
            </a>
        </div>
    <?php
        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Render a grouped layout displaying upcoming events and past events side-by-side.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_grouped_list($atts)
    {
        $today          = current_time('Y-m-d');

        // i18n: Provide translatable defaults if options are empty.
        $label_upcoming = get_option('cep_label_upcoming', __('Upcoming Events', 'core-events-pro'));
        $label_past     = get_option('cep_label_past', __('Past Events', 'core-events-pro'));

        $upcoming = get_posts([
            'post_type'      => 'main_event',
            'posts_per_page' => -1,
            'meta_key'       => '_cep_start',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [['key' => '_cep_start', 'value' => $today, 'compare' => '>=']]
        ]);

        $past = get_posts([
            'post_type'      => 'main_event',
            'posts_per_page' => 5,
            'meta_key'       => '_cep_start',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_query'     => [['key' => '_cep_start', 'value' => $today, 'compare' => '<']]
        ]);

        ob_start();
    ?>
        <div class="cep-grouped-wrapper" style="display:flex; gap:30px; flex-wrap:wrap;">

            <div style="flex:1; min-width:300px;">
                <h3 style="border-bottom:2px solid #2563eb; padding-bottom:10px;"><?php echo esc_html($label_upcoming); ?></h3>
                <?php
                if ($upcoming) :
                    foreach ($upcoming as $p) :
                        $d = get_post_meta($p->ID, '_cep_start', true);
                ?>
                        <div style="padding:10px 0; border-bottom:1px solid #eee;">
                            <strong style="color:#2563eb; width:60px; display:inline-block;">
                                <?php echo date_i18n(_x('M d', 'Event grouped list date format', 'core-events-pro'), strtotime($d)); ?>
                            </strong>
                            <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" style="text-decoration:none; color:#333; font-weight:bold;">
                                <?php echo esc_html($p->post_title); ?>
                            </a>
                        </div>
                <?php
                    endforeach;
                else :
                    echo '<p>' . esc_html__('No upcoming events.', 'core-events-pro') . '</p>';
                endif;
                ?>
            </div>

            <?php if (! get_option('cep_hide_past_events', 0)) : ?>
                <div style="flex:1; min-width:300px;">
                    <h3 style="border-bottom:2px solid #ccc; padding-bottom:10px; color:#666;"><?php echo esc_html($label_past); ?></h3>
                    <?php
                    if ($past) :
                        foreach ($past as $p) :
                            $d = get_post_meta($p->ID, '_cep_start', true);
                    ?>
                            <div style="padding:10px 0; border-bottom:1px solid #eee; opacity:0.7;">
                                <strong style="width:60px; display:inline-block;">
                                    <?php echo date_i18n(_x('M d', 'Event grouped list date format', 'core-events-pro'), strtotime($d)); ?>
                                </strong>
                                <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" style="text-decoration:none; color:#333; font-weight:bold;">
                                    <?php echo esc_html($p->post_title); ?>
                                </a>
                            </div>
                    <?php
                        endforeach;
                    else :
                        echo '<p>' . esc_html__('No past events found.', 'core-events-pro') . '</p>';
                    endif;
                    ?>
                </div>
            <?php endif; ?>

        </div>
<?php
        return ob_get_clean();
    }
}
