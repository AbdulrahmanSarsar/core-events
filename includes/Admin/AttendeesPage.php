<?php

/**
 * Attendees Page Module.
 * 
 * Handles the rendering of the custom admin page for managing event attendees.
 *
 * @package CoreEventsPro\Admin
 * @since 4.0.0
 */

namespace CoreEventsPro\Admin;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class AttendeesPage
 *
 * Manages the "Attendees" submenu page in the WordPress admin dashboard.
 */
class AttendeesPage
{

    /**
     * Constructor.
     * 
     * Initializes the class and hooks into the WordPress admin menu.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu_page']);
    }

    /**
     * Add Submenu Page.
     * 
     * Registers the Attendees page under the "main_event" custom post type menu.
     *
     * @return void
     */
    public function add_menu_page()
    {
        add_submenu_page(
            'edit.php?post_type=main_event',
            esc_html__('All Attendees', 'core-events-pro'), // i18n: Page title.
            esc_html__('Attendees 👥', 'core-events-pro'),  // i18n: Menu title.
            'manage_cep_events',                              // Capability required.
            'cep-attendees',                                  // Menu slug.
            [$this, 'render_page']                          // Callback function.
        );
    }

    /**
     * Render the Attendees Page.
     * 
     * Outputs the HTML content, filter form, the WP_List_Table, and inline JavaScript.
     *
     * @return void
     */
    public function render_page()
    {
        // Security: Protect the page by checking user capabilities.
        if (! current_user_can('manage_cep_events')) {
            wp_die(esc_html__('Unauthorized', 'core-events-pro'));
        }

        // Require the custom WP_List_Table class file.
        require_once CEP_PATH . 'includes/Admin/AttendeesTable.php';

        // Instantiate the table object and prepare data.
        $table = new AttendeesTable();
        $table->prepare_items();

        // Fetch events to be used in the filter dropdown.
        $events = get_posts([
            'post_type'      => ['main_event', 'sub_event'],
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ]);

        // Security & Data Handling: Retrieve and sanitize the current event filter from the URL.
        $current_filter = isset($_GET['event_filter']) ? absint(wp_unslash($_GET['event_filter'])) : '';

?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('👥 Event Attendees', 'core-events-pro'); ?></h1>
            <p><?php esc_html_e('Manage all registrations across your events. Use the filter below to view attendees for a specific event or session.', 'core-events-pro'); ?></p>

            <!-- 1. Event Filter Form -->
            <form method="get" style="margin-bottom: 20px; background: #fff; padding: 15px; border-left: 4px solid #2563eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">

                <!-- Hidden inputs are necessary to keep WordPress on the same page when filtering -->
                <input type="hidden" name="post_type" value="main_event">
                <input type="hidden" name="page" value="cep-attendees">

                <label style="font-weight:bold; margin-right:10px;"><?php esc_html_e('Filter by Event:', 'core-events-pro'); ?></label>
                <select name="event_filter">
                    <option value=""><?php esc_html_e('All Events & Sessions', 'core-events-pro'); ?></option>
                    <?php foreach ($events as $e) : ?>
                        <option value="<?php echo esc_attr($e->ID); ?>" <?php selected($current_filter, $e->ID); ?>>
                            <?php
                            // Distinguish sub-events (sessions).
                            if ($e->post_type === 'sub_event') {
                                // i18n & Security: Safely output the session prefix.
                                echo esc_html__('— Session: ', 'core-events-pro');
                            }

                            // Security: Escape the post title.
                            echo esc_html($e->post_title);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button"><?php esc_html_e('Apply Filter', 'core-events-pro'); ?></button>

                <?php
                if ($current_filter) :
                    // The CSV export button appears only if a specific event is filtered.
                    $export_url = wp_nonce_url(admin_url("admin-post.php?action=cep_export_csv&event_id={$current_filter}"), 'cep_export_csv_nonce');
                ?>
                    <a href="<?php echo esc_url($export_url); ?>" class="button button-primary" style="margin-left:10px;">
                        <?php esc_html_e('📥 Export This List (CSV)', 'core-events-pro'); ?>
                    </a>
                <?php endif; ?>
            </form>

            <!-- 2. Display the professional table (Supports bulk actions and search if added later) -->
            <form id="attendees-table-form" method="post">
                <?php
                // WordPress will automatically render the table here based on the WP_List_Table class.
                $table->display();
                ?>
            </form>
        </div>

        <!-- 3. Script to update attendance status (Check-in) via AJAX without full page reload -->
        <script>
            jQuery(document).ready(function($) {
                $('.cep-checkin-btn').on('click', function(e) {
                    e.preventDefault();

                    var btn = $(this);
                    var originalText = btn.text();

                    // i18n & Security: Translating JS text and escaping it.
                    var updatingText = '<?php echo esc_js(__('Updating...', 'core-events-pro')); ?>';
                    var errorAlert = '<?php echo esc_js(__('Error updating status.', 'core-events-pro')); ?>';

                    btn.text(updatingText).prop('disabled', true);

                    $.post(ajaxurl, {
                        action: 'cep_manual_checkin', // Function located in Attendees.php
                        security: '<?php echo esc_js(wp_create_nonce('cep_checkin_nonce')); ?>',
                        attendee_id: btn.data('id'),
                        status: btn.data('val')
                    }, function(res) {
                        if (res.success) {
                            // Reload the page to see changes (Best to ensure counters and actions are updated)
                            location.reload();
                        } else {
                            alert(errorAlert);
                            btn.text(originalText).prop('disabled', false);
                        }
                    });
                });
            });
        </script>
<?php
    }
}
