<?php

/**
 * Setup Wizard Class.
 *
 * Provides a clean, step-by-step onboarding experience for new users
 * immediately after plugin activation.
 *
 * @package CoreEventsPro\Admin
 * @since 4.6.0
 */

namespace CoreEventsPro\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class SetupWizard
{
    /**
     * Current step of the wizard.
     * @var string
     */
    private $step = '';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_wizard_page']);
        add_action('admin_init', [$this, 'setup_wizard_logic']);
    }

    /**
     * Add a hidden admin page for the Setup Wizard.
     * Passing 'null' as the parent slug hides it from the sidebar menu.
     */
    public function add_wizard_page()
    {
        add_submenu_page(
            null,
            esc_html__('Events Pro Setup', 'core-events-pro'),
            esc_html__('Setup', 'core-events-pro'),
            'manage_options',
            'cep-setup',
            [$this, 'render_wizard']
        );
    }

    /**
     * Handle form submissions and redirects for the wizard steps.
     */
    public function setup_wizard_logic()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'cep-setup') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'core-events-pro'));
        }

        $this->step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : '1';

        // Handle Form Saving
        if (isset($_POST['cep_wizard_save'])) {
            if (!isset($_POST['cep_wizard_nonce']) || !wp_verify_nonce($_POST['cep_wizard_nonce'], 'cep_wizard_action')) {
                wp_die(esc_html__('Security check failed.', 'core-events-pro'));
            }

            // Save Step 1 Data
            if ($this->step === '1') {
                update_option('cep_enable_time', isset($_POST['cep_enable_time']) ? 1 : 0);
                update_option('cep_enable_countdown', isset($_POST['cep_enable_countdown']) ? 1 : 0);
                update_option('cep_enable_ics', isset($_POST['cep_enable_ics']) ? 1 : 0);
                wp_safe_redirect(admin_url('admin.php?page=cep-setup&step=2'));
                exit;
            }

            // Save Step 2 Data
            if ($this->step === '2') {
                if (isset($_POST['cep_location_type'])) {
                    update_option('cep_location_type', sanitize_text_field($_POST['cep_location_type']));
                }
                if (isset($_POST['cep_predefined_locations'])) {
                    update_option('cep_predefined_locations', sanitize_textarea_field($_POST['cep_predefined_locations']));
                }
                wp_safe_redirect(admin_url('admin.php?page=cep-setup&step=3'));
                exit;
            }
        }
    }

    /**
     * Render the UI of the Setup Wizard.
     * Uses inline CSS to hide standard WP Admin elements for an app-like feel.
     */
    public function render_wizard()
    {
?>
        <style>
            /* Hide Default WP Admin UI */
            #adminmenumain,
            #wpadminbar,
            #wpfooter {
                display: none !important;
            }

            #wpcontent {
                margin-left: 0 !important;
                padding-left: 0 !important;
            }

            html.wp-toolbar {
                padding-top: 0 !important;
            }

            body {
                background: #f1f5f9;
            }

            /* Wizard Styles */
            .cep-wizard-wrapper {
                max-width: 600px;
                margin: 50px auto;
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
                overflow: hidden;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }

            .cep-wizard-header {
                background: #2563eb;
                color: #fff;
                padding: 30px;
                text-align: center;
            }

            .cep-wizard-header h1 {
                margin: 0;
                color: #fff;
                font-size: 24px;
            }

            .cep-wizard-body {
                padding: 40px;
            }

            .cep-wizard-footer {
                padding: 20px 40px;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .cep-btn {
                padding: 10px 20px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: bold;
                cursor: pointer;
                border: none;
                font-size: 15px;
            }

            .cep-btn-primary {
                background: #2563eb;
                color: #fff;
            }

            .cep-btn-primary:hover {
                background: #1d4ed8;
                color: #fff;
            }

            .cep-btn-secondary {
                background: #e2e8f0;
                color: #475569;
            }

            .cep-btn-secondary:hover {
                background: #cbd5e1;
                color: #1e293b;
            }

            .cep-form-group {
                margin-bottom: 20px;
            }

            .cep-form-group label {
                display: block;
                font-weight: bold;
                margin-bottom: 8px;
                color: #1e293b;
            }

            .cep-form-group input[type="text"],
            .cep-form-group select,
            .cep-form-group textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
            }

            .cep-steps-nav {
                display: flex;
                justify-content: center;
                gap: 10px;
                margin-top: 15px;
            }

            .cep-step-dot {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: #93c5fd;
            }

            .cep-step-dot.active {
                background: #fff;
            }
        </style>

        <div class="cep-wizard-wrapper">
            <div class="cep-wizard-header">
                <h1>🎉 <?php esc_html_e('Welcome to Core Events Pro', 'core-events-pro'); ?></h1>
                <p><?php esc_html_e('Let\'s get your event system ready in 2 minutes.', 'core-events-pro'); ?></p>
                <div class="cep-steps-nav">
                    <div class="cep-step-dot <?php echo $this->step === '1' ? 'active' : ''; ?>"></div>
                    <div class="cep-step-dot <?php echo $this->step === '2' ? 'active' : ''; ?>"></div>
                    <div class="cep-step-dot <?php echo $this->step === '3' ? 'active' : ''; ?>"></div>
                </div>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('cep_wizard_action', 'cep_wizard_nonce'); ?>
                <input type="hidden" name="cep_wizard_save" value="1">

                <div class="cep-wizard-body">

                    <!-- STEP 1: General Features -->
                    <?php if ($this->step === '1') : ?>
                        <h2><?php esc_html_e('Essential Features', 'core-events-pro'); ?></h2>
                        <p style="color:#64748b; margin-bottom:20px;"><?php esc_html_e('Choose what information you want to display on your event pages.', 'core-events-pro'); ?></p>

                        <div class="cep-form-group">
                            <label>
                                <input type="checkbox" name="cep_enable_time" value="1" checked>
                                <?php esc_html_e('Show Event Time (Hours & Minutes)', 'core-events-pro'); ?>
                            </label>
                        </div>
                        <div class="cep-form-group">
                            <label>
                                <input type="checkbox" name="cep_enable_countdown" value="1" checked>
                                <?php esc_html_e('Enable Live Countdown Timer ⏳', 'core-events-pro'); ?>
                            </label>
                        </div>
                        <div class="cep-form-group">
                            <label>
                                <input type="checkbox" name="cep_enable_ics" value="1" checked>
                                <?php esc_html_e('Show "Add to Calendar" Button 📅', 'core-events-pro'); ?>
                            </label>
                        </div>

                        <!-- STEP 2: Venues -->
                    <?php elseif ($this->step === '2') : ?>
                        <h2><?php esc_html_e('Venue Management', 'core-events-pro'); ?></h2>
                        <p style="color:#64748b; margin-bottom:20px;"><?php esc_html_e('How do you want to assign locations to your events?', 'core-events-pro'); ?></p>

                        <div class="cep-form-group">
                            <label><?php esc_html_e('Location System:', 'core-events-pro'); ?></label>
                            <select name="cep_location_type" id="wiz_loc_type">
                                <option value="free_text"><?php esc_html_e('Free Text (Type any address)', 'core-events-pro'); ?></option>
                                <option value="predefined"><?php esc_html_e('Predefined Venues (Prevents Double-Booking)', 'core-events-pro'); ?></option>
                            </select>
                        </div>

                        <div class="cep-form-group" id="wiz_loc_list" style="display:none;">
                            <label><?php esc_html_e('Enter your venues (One per line):', 'core-events-pro'); ?></label>
                            <textarea name="cep_predefined_locations" rows="4" placeholder="<?php esc_attr_e("Main Hall\nRoom A", 'core-events-pro'); ?>"></textarea>
                        </div>

                        <script>
                            document.getElementById('wiz_loc_type').addEventListener('change', function() {
                                document.getElementById('wiz_loc_list').style.display = this.value === 'predefined' ? 'block' : 'none';
                            });
                        </script>

                        <!-- STEP 3: Finish -->
                    <?php elseif ($this->step === '3') : ?>
                        <div style="text-align:center;">
                            <span class="dashicons dashicons-yes-alt" style="font-size:60px; color:#10b981; width:60px; height:60px;"></span>
                            <h2><?php esc_html_e('You are all set!', 'core-events-pro'); ?></h2>
                            <p style="color:#64748b;"><?php esc_html_e('Your event system is configured and ready to use. You can always change these settings later from the Events > Settings menu.', 'core-events-pro'); ?></p>
                        </div>
                    <?php endif; ?>

                </div>

                <div class="cep-wizard-footer">
                    <?php if ($this->step === '3') : ?>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=main_event')); ?>" class="cep-btn cep-btn-primary" style="width:100%; text-align:center;">
                            <?php esc_html_e('Create Your First Event 🚀', 'core-events-pro'); ?>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=main_event')); ?>" class="cep-btn cep-btn-secondary"><?php esc_html_e('Skip to Dashboard', 'core-events-pro'); ?></a>
                        <button type="submit" class="cep-btn cep-btn-primary"><?php esc_html_e('Continue &rarr;', 'core-events-pro'); ?></button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
<?php
    }
}
