<?php

/**
 * Meta Boxes Class.
 *
 * Handles the registration, rendering, and saving of custom meta boxes for the Event post types.
 * Includes the integration logic for WooCommerce Paid Tickets, Auto-calculated Status, 
 * Auto-Promotion from Waitlist upon capacity increase, and Venue Conflict Check.
 *
 * @package CoreEventsPro\Admin
 * @since 4.5.3
 */

namespace CoreEventsPro\Admin;

use CoreEventsPro\Helpers\EmailQueue;
use CoreEventsPro\Helpers\QrGenerator;

if (! defined('ABSPATH')) {
    exit;
}

class MetaBoxes
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_boxes']);
        add_action('save_post', [$this, 'save_data'], 10, 2);
        add_action('admin_footer', [$this, 'dynamic_ui_scripts']);
        add_action('admin_notices', [$this, 'display_conflict_notice']);

        // 🚀 THE ULTIMATE FIX FOR GUTENBERG CONFLICTS:
        // Intercept before WP even attempts to insert/update the post into the DB.
        add_filter('wp_insert_post_empty_content', [$this, 'prevent_conflict_hard'], 10, 2);
    }

    /**
     * 🚀 Strict filter to stop WordPress/Gutenberg from saving if there is a conflict.
     * Returning 'true' here tells WP "this post is empty/invalid, don't save it".
     */
    public function prevent_conflict_hard($maybe_empty, $postarr)
    {
        // Only run for our custom post types
        if (!isset($postarr['post_type']) || !in_array($postarr['post_type'], ['main_event', 'sub_event'])) {
            return $maybe_empty;
        }

        // We only care if they are trying to publish or schedule it
        if (!isset($postarr['post_status']) || !in_array($postarr['post_status'], ['publish', 'future'])) {
            return $maybe_empty;
        }

        $loc_type = get_option('cep_location_type', 'free_text');

        if ($loc_type !== 'predefined') {
            return $maybe_empty;
        }

        $post_id    = isset($postarr['ID']) ? absint($postarr['ID']) : 0;
        $location   = isset($_POST['_cep_location']) ? sanitize_text_field(wp_unslash($_POST['_cep_location'])) : '';
        $start_time = isset($_POST['_cep_start']) ? sanitize_text_field(wp_unslash($_POST['_cep_start'])) : '';
        $end_time   = isset($_POST['_cep_end']) ? sanitize_text_field(wp_unslash($_POST['_cep_end'])) : '';

        // If data isn't in POST, it might be an AJAX request from Gutenberg where meta isn't passed here.
        // In that case, we fall back to the save_data method's validation.
        if (empty($location) || empty($start_time)) {
            return $maybe_empty;
        }

        $conflict = $this->check_venue_conflict($post_id, $location, $start_time, $end_time);

        if ($conflict) {
            // Set the error message to display to the user
            set_transient('cep_conflict_error_' . get_current_user_id(), sprintf(__('🚨 Venue Conflict! "%s" is already booked for the event "%s" during this time. The event was NOT published.', 'core-events-pro'), $location, $conflict), 45);

            // Return true to abort the save process entirely!
            return true;
        }

        return $maybe_empty;
    }

    public function add_boxes()
    {
        $screens = ['main_event', 'sub_event'];

        foreach ($screens as $screen) {
            add_meta_box(
                'cep_main_config',
                esc_html__('⚙️ Event Configuration & Details', 'core-events-pro'),
                [$this, 'render_main_config'],
                $screen,
                'normal',
                'high'
            );

            add_meta_box(
                'cep_tickets_meta',
                esc_html__('🎟️ Tickets & Pricing (WooCommerce)', 'core-events-pro'),
                [$this, 'render_tickets_meta'],
                $screen,
                'normal',
                'high'
            );

            add_meta_box(
                'cep_media',
                esc_html__('🎬 Media (Banner, Gallery & Video)', 'core-events-pro'),
                [$this, 'render_media_meta'],
                $screen,
                'normal',
                'default'
            );

            add_meta_box(
                'cep_attendees',
                esc_html__('👥 Attendees List', 'core-events-pro'),
                [$this, 'render_attendees_list'],
                $screen,
                'normal',
                'default'
            );

            if (get_option('cep_enable_location', 1)) {
                add_meta_box(
                    'cep_location',
                    esc_html__('📍 Location', 'core-events-pro'),
                    [$this, 'render_location_meta'],
                    $screen,
                    'normal',
                    'default'
                );
            }
        }

        add_meta_box(
            'cep_sub_link',
            esc_html__('🔗 Parent Event Connection', 'core-events-pro'),
            [$this, 'render_sub_meta'],
            'sub_event',
            'side',
            'default'
        );
    }

    public function render_main_config($post)
    {
        $meta            = get_post_meta($post->ID);
        $start           = $meta['_cep_start'][0] ?? '';
        $end             = $meta['_cep_end'][0] ?? '';
        $status          = $meta['_cep_status'][0] ?? 'upcoming';
        $color           = $meta['_cep_color'][0] ?? '#3b82f6';
        $overview        = $meta['_cep_overview'][0] ?? '';
        $capacity        = $meta['_cep_capacity'][0] ?? '';
        $registered      = $this->get_attendee_count($post->ID);
        $is_recurring    = $meta['_cep_is_recurring'][0] ?? '0';
        $recurrence_type = $meta['_cep_recurrence_type'][0] ?? 'daily';

        $enable_rsvp     = $meta['_cep_enable_rsvp'][0] ?? '0';
        $rsvp_type       = $meta['_cep_rsvp_type'][0] ?? 'free';

        $time_enabled    = get_option('cep_enable_time', 1);
        $input_type      = $time_enabled ? 'datetime-local' : 'date';

        wp_nonce_field('cep_save_action_secure', 'cep_nonce_field');
?>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:15px;">
            <p>
                <label><strong><?php esc_html_e('Start Date', 'core-events-pro'); ?> <?php echo $time_enabled ? esc_html__('& Time', 'core-events-pro') : ''; ?>:</strong></label><br>
                <input type="<?php echo esc_attr($input_type); ?>" name="_cep_start" value="<?php echo esc_attr($start); ?>" style="width:100%" required>
            </p>
            <p>
                <label><strong><?php esc_html_e('End Date', 'core-events-pro'); ?> <?php echo $time_enabled ? esc_html__('& Time', 'core-events-pro') : ''; ?>:</strong></label><br>
                <input type="<?php echo esc_attr($input_type); ?>" name="_cep_end" value="<?php echo esc_attr($end); ?>" style="width:100%">
            </p>
        </div>

        <hr>

        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; margin-bottom:15px;">
            <div style="background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e2e8f0;">
                <label><strong><?php esc_html_e('Event Status:', 'core-events-pro'); ?></strong></label><br>
                <?php
                $status_display = ucfirst($status);
                $status_color   = ($status === 'cancelled') ? '#dc2626' : ($status === 'finished' ? '#64748b' : '#059669');
                ?>
                <span style="display:inline-block; margin:8px 0; padding:4px 12px; background:<?php echo esc_attr($status_color); ?>; color:#fff; border-radius:4px; font-size:12px; font-weight:bold;">
                    <?php echo esc_html($status_display); ?>
                    <?php echo ($status !== 'cancelled') ? esc_html__('(Auto-calculated)', 'core-events-pro') : ''; ?>
                </span>
                <br>
                <label style="color:#dc2626; font-weight:bold; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="_cep_cancel_event" value="1" <?php checked($status, 'cancelled'); ?>>
                    <?php esc_html_e('🚫 Cancel this event', 'core-events-pro'); ?>
                </label>
            </div>

            <div>
                <label><strong><?php esc_html_e('Total Capacity (Seats):', 'core-events-pro'); ?></strong></label><br>
                <input type="number" name="_cep_capacity" value="<?php echo esc_attr($capacity); ?>" style="width:100%" placeholder="<?php esc_attr_e('Unlimited', 'core-events-pro'); ?>">
                <div style="margin-top:5px; font-size:12px; color:#666;">
                    <?php esc_html_e('Registered:', 'core-events-pro'); ?> <strong><?php echo absint($registered); ?></strong>
                </div>
            </div>

            <div>
                <label><strong><?php esc_html_e('Calendar Color:', 'core-events-pro'); ?></strong></label><br>
                <input type="color" name="_cep_color" value="<?php echo esc_attr($color); ?>" style="width:100%; height:35px; cursor:pointer;">
            </div>
        </div>

        <hr>

        <div style="background:#e6fffa; padding:15px; border:1px solid #b2f5ea; border-radius:5px; margin-bottom:15px;">
            <label style="font-size:15px; display:flex; align-items:center; gap:8px;">
                <input type="checkbox" id="cep_toggle_registration" name="_cep_enable_rsvp" value="1" <?php checked($enable_rsvp, '1'); ?>>
                <strong><?php esc_html_e('Enable Registration / Tickets for this event', 'core-events-pro'); ?></strong>
            </label>

            <div id="cep_registration_options" style="margin-top:15px; padding-top:15px; border-top:1px dashed #4fd1c5; display:<?php echo ($enable_rsvp === '1') ? 'block' : 'none'; ?>;">
                <div style="display:flex; gap:20px; align-items:flex-start;">
                    <div style="flex:1;">
                        <label><strong><?php esc_html_e('Registration Type:', 'core-events-pro'); ?></strong></label><br>
                        <select name="_cep_rsvp_type" id="cep_rsvp_type" style="width:100%; margin-top:5px;">
                            <option value="free" <?php selected($rsvp_type, 'free'); ?>><?php esc_html_e('Free RSVP & Waitlist', 'core-events-pro'); ?></option>
                            <?php if (class_exists('WooCommerce')) : ?>
                                <option value="paid" <?php selected($rsvp_type, 'paid'); ?>><?php esc_html_e('Paid Tickets (WooCommerce)', 'core-events-pro'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div style="flex:1;" id="cep_capacity_wrapper">
                        <!-- Removed capacity here because it's now global above. -->
                    </div>
                </div>
            </div>
        </div>

        <div style="background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:5px; margin-bottom:15px;">
            <label style="font-size:15px; display:flex; align-items:center; gap:8px;">
                <input type="checkbox" id="cep_toggle_recurrence" name="_cep_is_recurring" value="1" <?php checked($is_recurring, '1'); ?>>
                <strong><?php esc_html_e('Enable Recurring Event', 'core-events-pro'); ?></strong>
            </label>

            <div id="cep_recurrence_options" style="margin-top:10px; display:<?php echo ($is_recurring === '1') ? 'flex' : 'none'; ?>; gap:10px; align-items:center;">
                <label><strong><?php esc_html_e('Repeat Every:', 'core-events-pro'); ?></strong></label>
                <select name="_cep_recurrence_type" style="min-width:150px;">
                    <option value="daily" <?php selected($recurrence_type, 'daily'); ?>><?php esc_html_e('Daily', 'core-events-pro'); ?></option>
                    <option value="weekly" <?php selected($recurrence_type, 'weekly'); ?>><?php esc_html_e('Weekly', 'core-events-pro'); ?></option>
                    <option value="monthly" <?php selected($recurrence_type, 'monthly'); ?>><?php esc_html_e('Monthly', 'core-events-pro'); ?></option>
                    <option value="yearly" <?php selected($recurrence_type, 'yearly'); ?>><?php esc_html_e('Yearly', 'core-events-pro'); ?></option>
                </select>
            </div>
        </div>

        <p>
            <label><strong><?php esc_html_e('Event Overview (Short Summary):', 'core-events-pro'); ?></strong></label><br>
            <textarea name="_cep_overview" rows="3" style="width:100%;"><?php echo esc_textarea($overview); ?></textarea>
        </p>
    <?php
    }

    public function dynamic_ui_scripts()
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['main_event', 'sub_event'])) {
            return;
        }
    ?>
        <script>
            jQuery(document).ready(function($) {
                $('#cep_toggle_recurrence').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#cep_recurrence_options').slideDown();
                    } else {
                        $('#cep_recurrence_options').slideUp();
                    }
                });

                function handleRegistrationUI() {
                    var isEnabled = $('#cep_toggle_registration').is(':checked');
                    var type = $('#cep_rsvp_type').val();
                    if (isEnabled) {
                        $('#cep_registration_options').slideDown();
                        if (type === 'paid') {
                            $('#cep_capacity_wrapper').hide();
                            $('#cep_tickets_meta').show();
                        } else {
                            $('#cep_capacity_wrapper').show();
                            $('#cep_tickets_meta').hide();
                        }
                    } else {
                        $('#cep_registration_options').slideUp();
                        $('#cep_tickets_meta').hide();
                    }
                }

                handleRegistrationUI();
                $('#cep_toggle_registration, #cep_rsvp_type').on('change', handleRegistrationUI);
            });
        </script>
    <?php
    }

    public function render_tickets_meta($post)
    {
        if (! class_exists('WooCommerce')) {
            echo '<div style="background:#fef2f2; border-left:4px solid #ef4444; padding:15px;">';
            echo '<p style="margin:0; color:#991b1b;"><strong>' . esc_html__('WooCommerce is Required!', 'core-events-pro') . '</strong></p>';
            echo '</div>';
            return;
        }

        $tickets = get_post_meta($post->ID, '_cep_tickets', true);
        if (! is_array($tickets)) {
            $tickets = [];
        }
        $currency = get_woocommerce_currency_symbol();
    ?>
        <p class="description">
            <?php esc_html_e('Create ticket tiers (e.g., VIP, Standard). Capacity is managed per ticket here.', 'core-events-pro'); ?>
        </p>

        <table class="widefat striped" style="margin-bottom:15px;" id="cep-tickets-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Ticket Name', 'core-events-pro'); ?></th>
                    <th><?php printf(esc_html__('Price (%s)', 'core-events-pro'), esc_html($currency)); ?></th>
                    <th><?php esc_html_e('Capacity', 'core-events-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'core-events-pro'); ?></th>
                </tr>
            </thead>
            <tbody id="cep-tickets-body">
                <?php
                $ticket_index = 0;
                foreach ($tickets as $ticket) :
                    $t_name  = isset($ticket['name']) ? $ticket['name'] : '';
                    $t_price = isset($ticket['price']) ? $ticket['price'] : '';
                    $t_cap   = isset($ticket['capacity']) ? $ticket['capacity'] : '';
                    $t_pid   = isset($ticket['product_id']) ? $ticket['product_id'] : 0;
                ?>
                    <tr>
                        <td>
                            <input type="text" name="cep_tickets[<?php echo esc_attr($ticket_index); ?>][name]" value="<?php echo esc_attr($t_name); ?>" style="width:100%;" required>
                            <input type="hidden" name="cep_tickets[<?php echo esc_attr($ticket_index); ?>][product_id]" value="<?php echo esc_attr($t_pid); ?>">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" name="cep_tickets[<?php echo esc_attr($ticket_index); ?>][price]" value="<?php echo esc_attr($t_price); ?>" style="width:100%;">
                        </td>
                        <td>
                            <input type="number" min="0" name="cep_tickets[<?php echo esc_attr($ticket_index); ?>][capacity]" value="<?php echo esc_attr($t_cap); ?>" style="width:100%;" placeholder="<?php esc_attr_e('Unlimited', 'core-events-pro'); ?>">
                        </td>
                        <td>
                            <button type="button" class="button cep-remove-ticket" style="color:#dc2626; border-color:#dc2626;">&times; <?php esc_html_e('Remove', 'core-events-pro'); ?></button>
                            <?php if ($t_pid) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($t_pid)); ?>" target="_blank" style="margin-left:5px; font-size:11px;"><?php esc_html_e('Edit in WC', 'core-events-pro'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                    $ticket_index++;
                endforeach;
                ?>
            </tbody>
        </table>
        <button type="button" class="button button-primary" id="cep-add-ticket-btn">+ <?php esc_html_e('Add Ticket Tier', 'core-events-pro'); ?></button>

        <script>
            jQuery(document).ready(function($) {
                let tIndex = <?php echo absint($ticket_index); ?>;

                $('#cep-add-ticket-btn').on('click', function(e) {
                    e.preventDefault();
                    let row = `
                        <tr>
                            <td>
                                <input type="text" name="cep_tickets[${tIndex}][name]" value="" style="width:100%;" placeholder="<?php esc_attr_e('e.g. VIP', 'core-events-pro'); ?>" required>
                                <input type="hidden" name="cep_tickets[${tIndex}][product_id]" value="0">
                            </td>
                            <td><input type="number" step="0.01" min="0" name="cep_tickets[${tIndex}][price]" value="0" style="width:100%;"></td>
                            <td><input type="number" min="0" name="cep_tickets[${tIndex}][capacity]" value="" style="width:100%;" placeholder="<?php esc_attr_e('Unlimited', 'core-events-pro'); ?>"></td>
                            <td><button type="button" class="button cep-remove-ticket" style="color:#dc2626; border-color:#dc2626;">&times; <?php esc_html_e('Remove', 'core-events-pro'); ?></button></td>
                        </tr>
                    `;
                    $('#cep-tickets-body').append(row);
                    tIndex++;
                });

                $(document).on('click', '.cep-remove-ticket', function(e) {
                    e.preventDefault();
                    if (confirm('<?php echo esc_js(__('Are you sure you want to remove this ticket?', 'core-events-pro')); ?>')) {
                        $(this).closest('tr').remove();
                    }
                });
            });
        </script>
    <?php
    }

    public function render_media_meta($post)
    {
        $video   = get_post_meta($post->ID, '_cep_video_url', true);
        $gallery = get_post_meta($post->ID, '_cep_gallery_ids', true);
        $banner  = get_post_meta($post->ID, '_cep_custom_banner', true);
    ?>
        <p>
            <label><strong><?php esc_html_e('🖼️ Custom Banner URL (Optional):', 'core-events-pro'); ?></strong></label><br>
            <input type="url" name="_cep_custom_banner" value="<?php echo esc_url($banner); ?>" class="widefat">
        </p>
        <hr>
        <p>
            <label><strong><?php esc_html_e('Video URL:', 'core-events-pro'); ?></strong></label>
            <input type="url" name="_cep_video_url" value="<?php echo esc_url($video); ?>" class="widefat">
        </p>
        <hr>
        <div class="cep-gallery-box">
            <label><strong><?php esc_html_e('Gallery Images:', 'core-events-pro'); ?></strong></label>
            <div id="cep_gallery_preview" style="display:flex; gap:10px; flex-wrap:wrap; margin:10px 0;">
                <?php
                if (! empty($gallery)) {
                    foreach (explode(',', $gallery) as $id) {
                        $url = wp_get_attachment_image_url(absint($id), 'thumbnail');
                        if ($url) {
                            echo '<div class="cep-img-wrap" data-id="' . esc_attr(absint($id)) . '">';
                            echo '<img src="' . esc_url($url) . '" style="width:80px;height:80px;object-fit:cover;">';
                            echo '<span class="cep-remove-img" style="cursor:pointer;color:red;">&times;</span>';
                            echo '</div>';
                        }
                    }
                }
                ?>
            </div>
            <input type="hidden" name="_cep_gallery_ids" id="cep_gallery_ids" value="<?php echo esc_attr($gallery); ?>">
            <button type="button" class="button" id="cep_upload_gallery_btn"><?php esc_html_e('Manage Images', 'core-events-pro'); ?></button>
        </div>
<?php
    }

    public function render_attendees_list($post)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            echo '<p style="color:red">' . esc_html__('Database table not found.', 'core-events-pro') . '</p>';
            return;
        }

        $total    = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d", $post->ID));
        $attended = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND check_in = 1", $post->ID));

        echo "<div style='padding:15px; background:#f8f9fa; border:1px solid #ccc; border-radius:5px; text-align:center;'>";
        echo "<h3 style='margin-top:0;'>" . esc_html__('Total Registered:', 'core-events-pro') . " <span style='color:#2563eb;'>" . absint($total) . "</span></h3>";
        echo "<h3>" . esc_html__('Checked-in:', 'core-events-pro') . " <span style='color:#059669;'>" . absint($attended) . "</span></h3>";

        $table_url = admin_url("edit.php?post_type=main_event&page=cep-attendees&event_filter={$post->ID}");
        $csv_url   = wp_nonce_url(admin_url("admin-post.php?action=cep_export_csv&event_id={$post->ID}"), 'cep_export_csv_nonce');

        echo "<div style='margin-top:20px; display:flex; gap:10px; justify-content:center;'>";
        echo "<a href='" . esc_url($table_url) . "' class='button button-primary'>🔍 " . esc_html__('Manage Attendees', 'core-events-pro') . "</a>";
        if ($total > 0) {
            echo "<a href='" . esc_url($csv_url) . "' class='button'>📥 " . esc_html__('Export CSV', 'core-events-pro') . "</a>";
        }
        echo "</div></div>";
    }

    public function render_location_meta($post)
    {
        $loc_type = get_option('cep_location_type', 'free_text');
        $current_loc = get_post_meta($post->ID, '_cep_location', true);

        if ($loc_type === 'predefined') {
            $venues_raw = get_option('cep_predefined_locations', '');
            $venues = array_filter(array_map('trim', explode("\n", $venues_raw)));

            echo '<select name="_cep_location" class="widefat">';
            echo '<option value="">' . esc_html__('-- Select Venue --', 'core-events-pro') . '</option>';
            foreach ($venues as $venue) {
                echo '<option value="' . esc_attr($venue) . '" ' . selected($current_loc, $venue, false) . '>' . esc_html($venue) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Selecting a venue will check for time conflicts automatically.', 'core-events-pro') . '</p>';
        } else {
            echo '<input type="text" name="_cep_location" value="' . esc_attr($current_loc) . '" class="widefat" placeholder="Address...">';
        }
    }

    public function render_sub_meta($post)
    {
        $parent = get_post_meta($post->ID, '_cep_parent_id', true);
        $events = get_posts(['post_type' => 'main_event', 'numberposts' => -1]);

        echo '<select name="_cep_parent_id" class="widefat"><option value="">-- No Parent --</option>';
        foreach ($events as $e) {
            echo '<option value="' . esc_attr($e->ID) . '" ' . selected($parent, $e->ID, false) . '>' . esc_html($e->post_title) . '</option>';
        }
        echo '</select>';
    }

    private function get_attendee_count($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND (status = 'confirmed' OR status = '')", absint($id)));
    }

    private function auto_promote_waitlist($event_id, $new_capacity)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        if ($new_capacity <= 0) return;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return;
        }

        $confirmed_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = 'confirmed'", $event_id));

        if ($confirmed_count < $new_capacity) {
            $seats_available = $new_capacity - $confirmed_count;

            $waitlisted_users = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE event_id = %d AND status = 'waitlist' ORDER BY created_at ASC LIMIT %d", $event_id, $seats_available));

            if ($waitlisted_users) {
                foreach ($waitlisted_users as $user) {
                    $qr_token = wp_generate_password(32, false);

                    $wpdb->update(
                        $table,
                        ['status' => 'confirmed', 'qr_token' => $qr_token],
                        ['id' => $user->id]
                    );

                    $this->send_promotion_email($user->email, $user->name, $event_id, $qr_token);
                }
            }
        }
    }

    private function send_promotion_email($to_email, $name, $event_id, $qr_token)
    {
        $event_title    = get_the_title($event_id);
        $start_date     = get_post_meta($event_id, '_cep_start', true);
        $date_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start_date));

        $subject_template = get_option('cep_email_confirm_sub', __('Registration Confirmed: {event_name}', 'core-events-pro'));
        $body_template    = __("Great News {name}!\n\nA seat has opened up and your registration for {event_name} is now {status}.\nDate: {event_date}\n\nBest Regards,", 'core-events-pro');

        $qr_html = "";

        if (! empty($qr_token)) {
            $scan_url     = QrGenerator::get_scan_url($qr_token);
            $qr_image_src = QrGenerator::get_data_uri($scan_url);

            $qr_html .= "\n\n" . __('--- YOUR TICKET ---', 'core-events-pro') . "\n";
            $qr_html .= __('Please present this QR code at the entrance:', 'core-events-pro') . "\n";

            if (! empty($qr_image_src)) {
                $qr_html .= "<img src='" . esc_attr($qr_image_src) . "' alt='" . esc_attr__('QR Ticket', 'core-events-pro') . "' width='200' height='200'>\n";
            }

            $qr_html .= sprintf(__('Or keep this link: %s', 'core-events-pro'), esc_url($scan_url)) . "\n";
        }

        $replacements = [
            '{name}'       => $name,
            '{event_name}' => $event_title,
            '{status}'     => __('CONFIRMED', 'core-events-pro'),
            '{event_date}' => $date_formatted
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
        $body    = str_replace(array_keys($replacements), array_values($replacements), $body_template);

        $headers   = array('Content-Type: text/html; charset=UTF-8');
        $html_body = nl2br($body) . $qr_html;

        EmailQueue::queue($to_email, $subject, $html_body, $headers);
    }

    /**
     * ✅ STRICT CONFLICT LOGIC
     */
    private function check_venue_conflict($post_id, $venue, $start_time, $end_time)
    {
        if (empty($venue) || empty($start_time)) return false;

        $venue = trim($venue);

        $end_time = empty($end_time) ? date('Y-m-d\TH:i', strtotime($start_time . ' +2 hours')) : $end_time;

        $start_ts = strtotime($start_time);
        $end_ts   = strtotime($end_time);

        $args = [
            'post_type'      => ['main_event', 'sub_event'],
            'post_status'    => ['publish', 'future'],
            'post__not_in'   => [$post_id],
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_cep_location',
                    'value' => $venue,
                ]
            ]
        ];

        $other_events = get_posts($args);

        foreach ($other_events as $other) {
            $other_start = get_post_meta($other->ID, '_cep_start', true);
            $other_end   = get_post_meta($other->ID, '_cep_end', true);

            if (empty($other_start)) continue;

            $other_start_ts = strtotime($other_start);
            $other_end_ts   = empty($other_end) ? strtotime($other_start . ' +2 hours') : strtotime($other_end);

            if ($start_ts < $other_end_ts && $end_ts > $other_start_ts) {
                return $other->post_title;
            }
        }

        return false;
    }

    /**
     * ✅ Make $post optional in save_data to avoid 'Too few arguments' error
     */
    public function save_data($post_id, $post = null)
    {
        if (! isset($_POST['cep_nonce_field']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cep_nonce_field'])), 'cep_save_action_secure')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $post_type = get_post_type($post_id);
        if ($post_type !== 'main_event' && $post_type !== 'sub_event') {
            return;
        }

        $start_str = isset($_POST['_cep_start']) ? sanitize_text_field(wp_unslash($_POST['_cep_start'])) : '';
        $end_str   = isset($_POST['_cep_end']) ? sanitize_text_field(wp_unslash($_POST['_cep_end'])) : '';

        $loc_type = get_option('cep_location_type', 'free_text');
        $location = isset($_POST['_cep_location']) ? sanitize_text_field(wp_unslash($_POST['_cep_location'])) : '';

        // If the 'wp_insert_post_empty_content' hook didn't catch it (e.g. classic editor), we check again here.
        if ($loc_type === 'predefined' && !empty($location) && ($post && $post->post_status !== 'draft')) {
            $conflict = $this->check_venue_conflict($post_id, $location, $start_str, $end_str);
            if ($conflict) {
                set_transient('cep_conflict_error_' . get_current_user_id(), sprintf(__('🚨 Venue Conflict! "%s" is already booked for the event "%s" during this time.', 'core-events-pro'), $location, $conflict), 45);
                $location = get_post_meta($post_id, '_cep_location', true);
            }
        }

        update_post_meta($post_id, '_cep_location', $location);


        $status = 'upcoming';

        if (isset($_POST['_cep_cancel_event']) && $_POST['_cep_cancel_event'] === '1') {
            $status = 'cancelled';
        } else {
            if (! empty($start_str)) {
                $now = current_time('timestamp');
                $start_time = strtotime($start_str);
                $end_time   = ! empty($end_str) ? strtotime($end_str) : $start_time;

                if ($now < $start_time) {
                    $status = 'upcoming';
                } elseif ($now >= $start_time && $now <= $end_time) {
                    $status = 'ongoing';
                } else {
                    $status = 'finished';
                }
            }
        }

        update_post_meta($post_id, '_cep_status', $status);

        $fields = [
            '_cep_start',
            '_cep_end',
            '_cep_color',
            '_cep_overview',
            '_cep_video_url',
            '_cep_parent_id',
            '_cep_custom_banner'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = wp_unslash($_POST[$field]);
                if ($field === '_cep_overview') {
                    update_post_meta($post_id, $field, sanitize_textarea_field($value));
                } else {
                    update_post_meta($post_id, $field, sanitize_text_field($value));
                }
            }
        }

        if (isset($_POST['_cep_capacity'])) {
            $new_capacity = absint($_POST['_cep_capacity']);
            $old_capacity = (int) get_post_meta($post_id, '_cep_capacity', true);

            update_post_meta($post_id, '_cep_capacity', $new_capacity);

            if ($new_capacity > 0 && $new_capacity > $old_capacity) {
                $this->auto_promote_waitlist($post_id, $new_capacity);
            }
        }

        $enable_rsvp = isset($_POST['_cep_enable_rsvp']) ? '1' : '0';
        update_post_meta($post_id, '_cep_enable_rsvp', $enable_rsvp);

        if (isset($_POST['_cep_rsvp_type'])) {
            update_post_meta($post_id, '_cep_rsvp_type', sanitize_text_field($_POST['_cep_rsvp_type']));
        }

        $is_recurring = isset($_POST['_cep_is_recurring']) ? '1' : '0';
        update_post_meta($post_id, '_cep_is_recurring', $is_recurring);

        if (isset($_POST['_cep_recurrence_type'])) {
            update_post_meta($post_id, '_cep_recurrence_type', sanitize_text_field($_POST['_cep_recurrence_type']));
        }

        if (isset($_POST['_cep_gallery_ids'])) {
            update_post_meta($post_id, '_cep_gallery_ids', sanitize_text_field($_POST['_cep_gallery_ids']));
        } else {
            delete_post_meta($post_id, '_cep_gallery_ids');
        }

        if (isset($_POST['cep_tickets']) && is_array($_POST['cep_tickets']) && $enable_rsvp === '1' && isset($_POST['_cep_rsvp_type']) && $_POST['_cep_rsvp_type'] === 'paid') {
            $sanitized_tickets = [];
            $wc_module_exists = class_exists('\\CoreEventsPro\\Modules\\WooCommerce');
            $wc_module        = $wc_module_exists ? new \CoreEventsPro\Modules\WooCommerce() : null;

            foreach (wp_unslash($_POST['cep_tickets']) as $ticket) {
                $name       = isset($ticket['name']) ? sanitize_text_field($ticket['name']) : '';
                $price      = isset($ticket['price']) ? sanitize_text_field($ticket['price']) : '0';
                $capacity   = isset($ticket['capacity']) ? absint($ticket['capacity']) : 0;
                $product_id = isset($ticket['product_id']) ? absint($ticket['product_id']) : 0;

                if (! empty($name)) {
                    if ($wc_module) {
                        $new_product_id = $wc_module->sync_ticket_to_product($post_id, [
                            'name'     => $name,
                            'price'    => $price,
                            'capacity' => $capacity
                        ], $product_id);

                        if (! is_wp_error($new_product_id) && $new_product_id > 0) {
                            $product_id = $new_product_id;
                        }
                    }

                    $sanitized_tickets[] = [
                        'name'       => $name,
                        'price'      => $price,
                        'capacity'   => $capacity,
                        'product_id' => $product_id
                    ];
                }
            }
            update_post_meta($post_id, '_cep_tickets', $sanitized_tickets);
        } else {
            delete_post_meta($post_id, '_cep_tickets');
        }
    }

    public function display_conflict_notice()
    {
        $user_id = get_current_user_id();
        $error   = get_transient('cep_conflict_error_' . $user_id);

        if ($error) {
            echo '<div class="notice notice-error is-dismissible" style="background:#fef2f2; border-left-color:#ef4444; border-left-width:4px;"><p style="color:#991b1b; font-size:16px;"><strong>' . esc_html($error) . '</strong></p></div>';
            delete_transient('cep_conflict_error_' . $user_id);
        }
    }
}
