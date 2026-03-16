<?php

/**
 * Calendar Shortcode Class.
 * 
 * Registers and renders the [event_calendar] shortcode, which displays
 * an interactive, AJAX-powered monthly event calendar on the frontend.
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
 * Class Calendar
 *
 * Manages the rendering of the interactive calendar shortcode.
 */
class Calendar
{

    /**
     * Constructor.
     * 
     * Initializes the shortcode registration.
     */
    public function __construct()
    {
        add_shortcode('event_calendar', [$this, 'render']);
    }

    /**
     * Render the event calendar shortcode.
     * 
     * Generates the HTML structure required for the frontend interactive calendar,
     * including controls, the days grid, and the event details modal.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string The generated HTML markup.
     */
    public function render($atts)
    {
        // Parse shortcode attributes with defaults.
        $atts = shortcode_atts([
            'category' => '',
            'type'     => ''
        ], $atts);

        ob_start();
?>

        <div class="cep-calendar-wrapper"
            data-category="<?php echo esc_attr($atts['category']); ?>"
            data-type="<?php echo esc_attr($atts['type']); ?>">

            <!-- 1. Controls -->
            <div class="cep-cal-controls">
                <h3 id="cep-month-label"><?php esc_html_e('Loading...', 'core-events-pro'); ?></h3>
                <div class="cep-nav-group">
                    <button class="cep-nav-btn" id="cep-prev">&larr;</button>
                    <button class="cep-nav-btn" id="cep-today"><?php esc_html_e('Today', 'core-events-pro'); ?></button>
                    <button class="cep-nav-btn" id="cep-next">&rarr;</button>
                </div>
            </div>

            <!-- 2. Days Header (The Fix you asked for) ✅ -->
            <div class="cep-cal-days-header">
                <div><?php esc_html_e('Sun', 'core-events-pro'); ?></div>
                <div><?php esc_html_e('Mon', 'core-events-pro'); ?></div>
                <div><?php esc_html_e('Tue', 'core-events-pro'); ?></div>
                <div><?php esc_html_e('Wed', 'core-events-pro'); ?></div>
                <div><?php esc_html_e('Thu', 'core-events-pro'); ?></div>
                <div><?php esc_html_e('Fri', 'core-events-pro'); ?></div>
                <div><?php esc_html_e('Sat', 'core-events-pro'); ?></div>
            </div>

            <!-- 3. Grid -->
            <div class="cep-cal-grid" id="cep-cal-grid">
                <!-- JS will dynamically inject days here -->
            </div>

            <!-- Modal for Event Details (Better UX) -->
            <div id="cep-modal" class="cep-modal" style="display:none;">
                <div class="cep-modal-content">
                    <span id="cep-close-modal">&times;</span>
                    <h3 id="cep-modal-title"></h3>
                    <p id="cep-modal-date"></p>
                    <a id="cep-modal-link" href="#"><?php esc_html_e('View Full Event', 'core-events-pro'); ?></a>
                </div>
            </div>

        </div>

<?php
        // Return the buffered HTML content securely.
        return ob_get_clean();
    }
}
