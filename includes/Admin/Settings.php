<?php

/**
 * Settings Page Class.
 * 
 * Handles the registration of plugin settings and renders the main configuration page using the WordPress Settings API.
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
 * Class Settings
 *
 * Manages the settings page and options for Core Events Pro.
 */
class Settings
{
    /**
     * Constructor.
     * 
     * Initializes hooks for the admin menu and settings registration.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add the Settings submenu page under the Main Event post type.
     *
     * @return void
     */
    public function add_settings_page()
    {
        add_submenu_page(
            'edit.php?post_type=main_event',
            esc_html__('Events Settings', 'core-events-pro'), // i18n: Page title.
            esc_html__('Settings & Help', 'core-events-pro'), // i18n: Menu title.
            'manage_options',                                   // Capability required.
            'cep-settings',                                     // Menu slug.
            [$this, 'render_page']                            // Callback function.
        );
    }

    /**
     * Register plugin settings with the WordPress Settings API.
     *
     * @return void
     */
    public function register_settings()
    {
        // --- 1. Features ---
        register_setting('cep_options_group', 'cep_enable_time');
        register_setting('cep_options_group', 'cep_enable_location');
        register_setting('cep_options_group', 'cep_enable_countdown');
        register_setting('cep_options_group', 'cep_enable_ics');
        register_setting('cep_options_group', 'cep_hide_past_events');

        // --- 2. Location System (NEW) ---
        register_setting('cep_options_group', 'cep_location_type'); // 'free_text' or 'predefined'
        register_setting('cep_options_group', 'cep_predefined_locations'); // List of venues

        // --- 3. Section Titles ---
        register_setting('cep_options_group', 'cep_label_schedule');
        register_setting('cep_options_group', 'cep_label_gallery');
        register_setting('cep_options_group', 'cep_label_video');
        register_setting('cep_options_group', 'cep_label_upcoming');
        register_setting('cep_options_group', 'cep_label_past');

        // --- 4. UI Labels ---
        register_setting('cep_options_group', 'cep_text_session');
        register_setting('cep_options_group', 'cep_text_back');
        register_setting('cep_options_group', 'cep_text_ends');
        register_setting('cep_options_group', 'cep_text_waitlist');

        // --- 5. Emails (NEW) ---
        register_setting('cep_options_group', 'cep_email_confirm_sub');
        register_setting('cep_options_group', 'cep_email_confirm_body');
        register_setting('cep_options_group', 'cep_email_remind_sub');
        register_setting('cep_options_group', 'cep_email_remind_body');
    }

    /**
     * Render the Settings page HTML layout.
     *
     * @return void
     */
    public function render_page()
    {
        // Security Check: Ensure the user has the right capabilities.
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'core-events-pro'));
        }

        $location_type = get_option('cep_location_type', 'free_text');
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('⚙️ Events Pro Configuration', 'core-events-pro'); ?></h1>
            <hr class="wp-header-end">

            <form method="post" action="options.php">
                <?php settings_fields('cep_options_group'); ?>

                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
                    <!-- 🟢 First Column: General Settings & Emails -->
                    <div style="flex: 1; min-width: 450px;">

                        <!-- Features Control -->
                        <div style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:20px;">
                            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><?php esc_html_e('🔧 Features Control', 'core-events-pro'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e('Enable Time', 'core-events-pro'); ?></th>
                                    <td><label><input type="checkbox" name="cep_enable_time" value="1" <?php checked(1, get_option('cep_enable_time', 1)); ?>> <?php esc_html_e('Show Hours & Minutes', 'core-events-pro'); ?></label></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Enable Location', 'core-events-pro'); ?></th>
                                    <td><label><input type="checkbox" name="cep_enable_location" value="1" <?php checked(1, get_option('cep_enable_location', 1)); ?>> <?php esc_html_e('Clickable Maps', 'core-events-pro'); ?></label></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Event Countdown', 'core-events-pro'); ?></th>
                                    <td><label><input type="checkbox" name="cep_enable_countdown" value="1" <?php checked(1, get_option('cep_enable_countdown', 1)); ?>> <?php esc_html_e('Show Live Countdown ⏳', 'core-events-pro'); ?></label></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Add to Calendar', 'core-events-pro'); ?></th>
                                    <td><label><input type="checkbox" name="cep_enable_ics" value="1" <?php checked(1, get_option('cep_enable_ics', 1)); ?>> <?php esc_html_e('Show ICS button 📅', 'core-events-pro'); ?></label></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Hide Past Events', 'core-events-pro'); ?></th>
                                    <td><label><input type="checkbox" name="cep_hide_past_events" value="1" <?php checked(1, get_option('cep_hide_past_events', 0)); ?>> <?php esc_html_e('Auto-hide finished events', 'core-events-pro'); ?></label></td>
                                </tr>
                            </table>
                        </div>

                        <!-- 🚀 Smart Location / Venue System -->
                        <div style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:20px;">
                            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><?php esc_html_e('📍 Venue Management', 'core-events-pro'); ?></h2>
                            <p class="description"><?php esc_html_e('Choose how you want to input event locations. Predefined lists prevent double-booking.', 'core-events-pro'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e('Location Type', 'core-events-pro'); ?></th>
                                    <td>
                                        <select name="cep_location_type" id="cep_location_type">
                                            <option value="free_text" <?php selected($location_type, 'free_text'); ?>><?php esc_html_e('Free Text (Type any address)', 'core-events-pro'); ?></option>
                                            <option value="predefined" <?php selected($location_type, 'predefined'); ?>><?php esc_html_e('Predefined Venues (Dropdown)', 'core-events-pro'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="cep_predefined_row" style="display:<?php echo $location_type === 'predefined' ? 'table-row' : 'none'; ?>;">
                                    <th><?php esc_html_e('Available Venues', 'core-events-pro'); ?></th>
                                    <td>
                                        <textarea name="cep_predefined_locations" rows="5" class="large-text" placeholder="<?php esc_attr_e("Main Hall\nConference Room A\nOutdoor Arena", 'core-events-pro'); ?>"><?php echo esc_textarea(get_option('cep_predefined_locations', "")); ?></textarea>
                                        <p class="description"><?php esc_html_e('Enter one venue per line. The system will prevent two events from booking the same venue at the same time.', 'core-events-pro'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <script>
                                jQuery(document).ready(function($) {
                                    $('#cep_location_type').on('change', function() {
                                        if ($(this).val() === 'predefined') {
                                            $('#cep_predefined_row').fadeIn();
                                        } else {
                                            $('#cep_predefined_row').fadeOut();
                                        }
                                    });
                                });
                            </script>
                        </div>

                        <!-- ✅ Automated Emails -->
                        <div style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:20px;">
                            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><?php esc_html_e('📧 Automated Emails', 'core-events-pro'); ?></h2>
                            <p class="description">
                                <?php echo wp_kses_post(__('Available tags: <code>{name}</code>, <code>{event_name}</code>, <code>{status}</code> (confirmed/waitlist), <code>{event_date}</code>.', 'core-events-pro')); ?>
                            </p>
                            <h3 style="margin-top:20px;"><?php esc_html_e('1. Registration Confirmation Email', 'core-events-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e('Subject', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_email_confirm_sub" value="<?php echo esc_attr(get_option('cep_email_confirm_sub', __('Registration Confirmed: {event_name}', 'core-events-pro'))); ?>" class="large-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Body', 'core-events-pro'); ?></th>
                                    <td>
                                        <textarea name="cep_email_confirm_body" rows="4" class="large-text"><?php echo esc_textarea(get_option('cep_email_confirm_body', __("Hello {name},\n\nYour registration for {event_name} is {status}.\nWe look forward to seeing you!\n\nBest Regards,", 'core-events-pro'))); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                            <h3 style="margin-top:20px;"><?php esc_html_e('2. Event Reminder (24h Before)', 'core-events-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e('Subject', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_email_remind_sub" value="<?php echo esc_attr(get_option('cep_email_remind_sub', __('Reminder: {event_name} is starting soon!', 'core-events-pro'))); ?>" class="large-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Body', 'core-events-pro'); ?></th>
                                    <td>
                                        <textarea name="cep_email_remind_body" rows="4" class="large-text"><?php echo esc_textarea(get_option('cep_email_remind_body', __("Hello {name},\n\nThis is a friendly reminder that {event_name} will start on {event_date}.\nSee you there!", 'core-events-pro'))); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Text & Translation -->
                        <div style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><?php esc_html_e('📝 Text & Translation', 'core-events-pro'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e('Schedule Title', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_label_schedule" value="<?php echo esc_attr(get_option('cep_label_schedule', __('Event Schedule', 'core-events-pro'))); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Gallery Title', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_label_gallery" value="<?php echo esc_attr(get_option('cep_label_gallery', __('Event Gallery', 'core-events-pro'))); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Video Title', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_label_video" value="<?php echo esc_attr(get_option('cep_label_video', __('Event Video', 'core-events-pro'))); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('"Upcoming" Title', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_label_upcoming" value="<?php echo esc_attr(get_option('cep_label_upcoming', __('Upcoming Events', 'core-events-pro'))); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('"Past" Title', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_label_past" value="<?php echo esc_attr(get_option('cep_label_past', __('Past Events', 'core-events-pro'))); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('"Session" Label', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_text_session" value="<?php echo esc_attr(get_option('cep_text_session', __('Session', 'core-events-pro'))); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('"Back to" Text', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_text_back" value="<?php echo esc_attr(get_option('cep_text_back', __('Back to Event', 'core-events-pro'))); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('"Ends" Label', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_text_ends" value="<?php echo esc_attr(get_option('cep_text_ends', __('Ends:', 'core-events-pro'))); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('"Waitlist" Btn', 'core-events-pro'); ?></th>
                                    <td><input type="text" name="cep_text_waitlist" value="<?php echo esc_attr(get_option('cep_text_waitlist', __('Join Waitlist', 'core-events-pro'))); ?>" class="regular-text"></td>
                                </tr>
                            </table>
                            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                            <?php submit_button(esc_html__('Save Settings', 'core-events-pro'), 'primary large'); ?>
                        </div>

                    </div>

                    <!-- 🔵 Right Column: Shortcodes -->
                    <div style="flex: 1; min-width: 400px;">
                        <div style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); position: sticky; top: 20px;">
                            <h2 style="margin-top:0;"><?php esc_html_e('🧩 Shortcodes Cheatsheet', 'core-events-pro'); ?></h2>
                            <p class="description"><?php esc_html_e('Click to copy shortcodes.', 'core-events-pro'); ?></p>

                            <style>
                                .cep-sc-table {
                                    width: 100%;
                                    border-collapse: collapse;
                                    margin-top: 15px;
                                }

                                .cep-sc-table th {
                                    text-align: left;
                                    padding: 10px;
                                    background: #f8f9fa;
                                    border-bottom: 2px solid #eee;
                                }

                                .cep-sc-table td {
                                    padding: 12px 10px;
                                    border-bottom: 1px solid #eee;
                                    vertical-align: top;
                                    font-size: 14px;
                                }

                                .cep-code-box {
                                    background: #f0f0f1;
                                    padding: 6px 10px;
                                    border-radius: 4px;
                                    font-family: monospace;
                                    color: #d63638;
                                    display: inline-block;
                                    border: 1px solid #ccc;
                                    font-size: 13px;
                                    cursor: pointer;
                                    transition: 0.2s;
                                    position: relative;
                                }

                                .cep-code-box:hover {
                                    background: #e0e0e0;
                                    border-color: #999;
                                }

                                .cep-code-box::after {
                                    content: "<?php echo esc_js(__('Click to copy', 'core-events-pro')); ?>";
                                    position: absolute;
                                    top: -25px;
                                    left: 50%;
                                    transform: translateX(-50%);
                                    background: #333;
                                    color: #fff;
                                    padding: 3px 8px;
                                    border-radius: 4px;
                                    font-size: 11px;
                                    opacity: 0;
                                    transition: 0.2s;
                                    pointer-events: none;
                                    white-space: nowrap;
                                }

                                .cep-code-box:hover::after {
                                    opacity: 1;
                                }

                                #cep-toast {
                                    visibility: hidden;
                                    min-width: 250px;
                                    background-color: #333;
                                    color: #fff;
                                    text-align: center;
                                    border-radius: 4px;
                                    padding: 12px;
                                    position: fixed;
                                    z-index: 9999;
                                    left: 50%;
                                    bottom: 30px;
                                    transform: translateX(-50%);
                                    font-weight: bold;
                                    font-size: 14px;
                                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
                                }

                                #cep-toast.show {
                                    visibility: visible;
                                    animation: fadein 0.5s, fadeout 0.5s 2.5s;
                                }

                                @keyframes fadein {
                                    from {
                                        bottom: 0;
                                        opacity: 0;
                                    }

                                    to {
                                        bottom: 30px;
                                        opacity: 1;
                                    }
                                }

                                @keyframes fadeout {
                                    from {
                                        bottom: 30px;
                                        opacity: 1;
                                    }

                                    to {
                                        bottom: 0;
                                        opacity: 0;
                                    }
                                }
                            </style>

                            <table class="cep-sc-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Shortcode', 'core-events-pro'); ?></th>
                                        <th><?php esc_html_e('Description', 'core-events-pro'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="cep-code-box" data-code="[events_advanced_filter]">[events_advanced_filter]</span></td>
                                        <td><?php esc_html_e('AJAX search & filter form.', 'core-events-pro'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><span class="cep-code-box" data-code="[event_calendar]">[event_calendar]</span></td>
                                        <td><?php esc_html_e('Interactive monthly calendar.', 'core-events-pro'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><span class="cep-code-box" data-code="[next_event]">[next_event]</span></td>
                                        <td><?php esc_html_e('Single next upcoming event card.', 'core-events-pro'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><span class="cep-code-box" data-code="[events_grouped]">[events_grouped]</span></td>
                                        <td><?php esc_html_e('Upcoming vs Past events.', 'core-events-pro'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><span class="cep-code-box" data-code='[events_list status="upcoming" limit="6"]'>[events_list]</span></td>
                                        <td><?php esc_html_e('Simple grid of events.', 'core-events-pro'); ?></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div id="cep-toast"><?php esc_html_e('✅ Shortcode copied to clipboard!', 'core-events-pro'); ?></div>

                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const codeBoxes = document.querySelectorAll('.cep-code-box');
                                    const toast = document.getElementById('cep-toast');

                                    codeBoxes.forEach(box => {
                                        box.addEventListener('click', function() {
                                            navigator.clipboard.writeText(this.getAttribute('data-code')).then(() => {
                                                toast.className = "show";
                                                setTimeout(function() {
                                                    toast.className = toast.className.replace("show", "");
                                                }, 3000);
                                            });
                                        });
                                    });
                                });
                            </script>
                        </div>
                    </div>
                </div>
            </form>
        </div>
<?php
    }
}
