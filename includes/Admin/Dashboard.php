<?php

/**
 * Dashboard Admin Page.
 * 
 * Handles the display of the statistics dashboard and the CSV import tool.
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
 * Class Dashboard
 *
 * Manages the statistics overview page for the event management system.
 */
class Dashboard
{

    /**
     * Constructor.
     * 
     * Hooks into the admin menu generation with a specific priority.
     */
    public function __construct()
    {
        // Priority 9 ensures it appears higher in the submenu list.
        add_action('admin_menu', [$this, 'add_dashboard_page'], 9);
    }

    /**
     * Register the Dashboard submenu page.
     *
     * @return void
     */
    public function add_dashboard_page()
    {
        add_submenu_page(
            'edit.php?post_type=main_event',
            esc_html__('Events Dashboard', 'core-events-pro'), // i18n: Page title.
            esc_html__('Dashboard 📊', 'core-events-pro'),     // i18n: Menu title.
            'manage_options',                                    // Capability required.
            'cep-dashboard',                                     // Menu slug.
            [$this, 'render_dashboard']                        // Callback function.
        );
    }

    /**
     * Render the Dashboard HTML content.
     * 
     * Computes statistics from the database and displays upcoming events along with the CSV import tool.
     *
     * @return void
     */
    public function render_dashboard()
    {
        // Security: Double-check user capabilities before rendering the page content.
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'core-events-pro'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        // Calculate Statistics securely.
        $count_posts      = wp_count_posts('main_event');
        // Ensure publish property exists to avoid PHP warnings.
        $total_events     = isset($count_posts->publish) ? $count_posts->publish : 0;
        $total_attendees  = 0;
        $total_checked_in = 0;

        // Security: Use wpdb->prepare even for SHOW TABLES to strictly adhere to DB security standards.
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
            // These queries are safe as $table is dynamically built from the WP prefix.
            $total_attendees  = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $total_checked_in = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE check_in = 1");
        }

        // Fetch the latest upcoming events.
        $upcoming_events = get_posts([
            'post_type'      => 'main_event',
            'meta_key'       => '_cep_status',
            'meta_value'     => 'upcoming',
            'posts_per_page' => 5
        ]);

?>
        <div class="wrap">
            <h1><?php esc_html_e('📊 Events Pro Overview', 'core-events-pro'); ?></h1>

            <!-- Statistics Section -->
            <div style="display:flex; gap:20px; margin-top:20px; flex-wrap:wrap;">

                <!-- Stat Box 1 -->
                <div style="flex:1; min-width:200px; background:#fff; padding:20px; border-radius:8px; border-left:5px solid #2563eb; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin:0 0 10px; color:#64748b;"><?php esc_html_e('Total Published Events', 'core-events-pro'); ?></h3>
                    <div style="font-size:32px; font-weight:bold; color:#1e293b;"><?php echo absint($total_events); ?></div>
                </div>

                <!-- Stat Box 2 -->
                <div style="flex:1; min-width:200px; background:#fff; padding:20px; border-radius:8px; border-left:5px solid #059669; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin:0 0 10px; color:#64748b;"><?php esc_html_e('Total Registrations', 'core-events-pro'); ?></h3>
                    <div style="font-size:32px; font-weight:bold; color:#1e293b;"><?php echo absint($total_attendees); ?></div>
                </div>

                <!-- Stat Box 3 -->
                <div style="flex:1; min-width:200px; background:#fff; padding:20px; border-radius:8px; border-left:5px solid #d97706; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin:0 0 10px; color:#64748b;"><?php esc_html_e('Total Checked-in', 'core-events-pro'); ?></h3>
                    <div style="font-size:32px; font-weight:bold; color:#1e293b;"><?php echo absint($total_checked_in); ?></div>
                </div>

            </div>

            <div style="display:flex; gap:20px; margin-top:30px; flex-wrap:wrap;">

                <!-- Upcoming Events Column -->
                <div style="flex:2; min-width:400px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <h2><?php esc_html_e('🚀 Next Upcoming Events', 'core-events-pro'); ?></h2>

                    <?php if ($upcoming_events) : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Event Title', 'core-events-pro'); ?></th>
                                    <th><?php esc_html_e('Start Date', 'core-events-pro'); ?></th>
                                    <th><?php esc_html_e('Action', 'core-events-pro'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_events as $ev) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($ev->post_title); ?></strong></td>
                                        <td><?php echo esc_html(get_post_meta($ev->ID, '_cep_start', true)); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($ev->ID)); ?>" class="button button-small">
                                                <?php esc_html_e('Manage', 'core-events-pro'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php esc_html_e('No upcoming events found. Time to create one!', 'core-events-pro'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- 🟢 CSV Import Tool Column -->
                <div style="flex:1; min-width:300px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <h2><?php esc_html_e('📥 Import Events (CSV)', 'core-events-pro'); ?></h2>
                    <p class="description"><?php esc_html_e('Upload a CSV file to bulk create main events.', 'core-events-pro'); ?></p>

                    <p style="font-size:12px; color:#666;">
                        <strong><?php esc_html_e('Format:', 'core-events-pro'); ?></strong>
                        <?php esc_html_e('Title, Content, Start Date (YYYY-MM-DDTHH:MM), End Date, Capacity, Location.', 'core-events-pro'); ?>
                    </p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="margin-top:15px;">
                        <input type="hidden" name="action" value="cep_import_csv">
                        <?php wp_nonce_field('cep_import_action', 'cep_import_nonce'); ?>
                        <input type="file" name="cep_csv_file" accept=".csv" required style="margin-bottom:15px; display:block;">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Upload & Import', 'core-events-pro'); ?></button>
                    </form>
                </div>

            </div>
        </div>
<?php
    }
}
