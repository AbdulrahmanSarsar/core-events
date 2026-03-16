<?php

/**
 * Licensing Module.
 *
 * Handles purchase code activation/deactivation and license state checks
 * for premium features.
 *
 * @package CoreEventsPro\Modules
 * @since 1.0.0
 */

namespace CoreEventsPro\Modules;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Licensing
 */
class Licensing
{
    /**
     * Purchase code option key.
     *
     * @var string
     */
    const OPTION_PURCHASE_CODE = 'core_events_pro_purchase_code';

    /**
     * License status option key.
     *
     * @var string
     */
    const OPTION_LICENSE_STATUS = 'core_events_pro_license_status';

    /**
     * Activated domain option key.
     *
     * @var string
     */
    const OPTION_ACTIVATED_DOMAIN = 'core_events_pro_activated_domain';

    /**
     * Envato verification endpoint.
     *
     * @var string
     */
    const ENVATO_VERIFY_ENDPOINT = 'https://api.envato.com/v3/market/author/sale';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_license_page']);
        add_action('admin_post_cep_activate_license', [$this, 'handle_activate']);
        add_action('admin_post_cep_deactivate_license', [$this, 'handle_deactivate']);
        add_action('admin_notices', [$this, 'maybe_show_admin_notices']);

        add_filter('cep_license_is_active', [$this, 'is_license_active']);
        add_filter('cep_premium_updates_enabled', [$this, 'can_use_automatic_updates']);
        add_filter('cep_premium_templates_enabled', [$this, 'can_use_premium_templates']);
        add_filter('cep_advanced_modules_enabled', [$this, 'can_use_advanced_modules']);
    }

    /**
     * Register license submenu under Events.
     *
     * @return void
     */
    public function register_license_page()
    {
        add_submenu_page(
            'edit.php?post_type=main_event',
            esc_html__('License', 'core-events-pro'),
            esc_html__('License', 'core-events-pro'),
            'manage_options',
            'cep-license',
            [$this, 'render_license_page']
        );
    }

    /**
     * Render license settings page.
     *
     * @return void
     */
    public function render_license_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'core-events-pro'));
        }

        $status          = $this->get_license_status();
        $is_active       = ('active' === $status);
        $purchase_code   = (string) get_option(self::OPTION_PURCHASE_CODE, '');
        $activated_domain = (string) get_option(self::OPTION_ACTIVATED_DOMAIN, '');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Core Events Pro License', 'core-events-pro'); ?></h1>
            <p><?php esc_html_e('Activate your CodeCanyon purchase code to unlock premium features.', 'core-events-pro'); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('License Status', 'core-events-pro'); ?></th>
                    <td>
                        <?php if ($is_active) : ?>
                            <span style="color:#067d17;font-weight:600;"><?php esc_html_e('Active', 'core-events-pro'); ?></span>
                        <?php else : ?>
                            <span style="color:#b32d2e;font-weight:600;"><?php esc_html_e('Inactive', 'core-events-pro'); ?></span>
                        <?php endif; ?>
                        <?php if (! empty($activated_domain)) : ?>
                            <p class="description">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: domain URL. */
                                        __('Activated domain: %s', 'core-events-pro'),
                                        $activated_domain
                                    )
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('cep_activate_license_action', 'cep_activate_license_nonce'); ?>
                <input type="hidden" name="action" value="cep_activate_license" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="cep-purchase-code"><?php esc_html_e('Purchase Code', 'core-events-pro'); ?></label></th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="cep-purchase-code"
                                name="purchase_code"
                                value="<?php echo esc_attr($purchase_code); ?>"
                                autocomplete="off"
                            />
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Activate License', 'core-events-pro'), 'primary', 'submit', false); ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
                <?php wp_nonce_field('cep_deactivate_license_action', 'cep_deactivate_license_nonce'); ?>
                <input type="hidden" name="action" value="cep_deactivate_license" />
                <?php submit_button(__('Deactivate License', 'core-events-pro'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle license activation request.
     *
     * @return void
     */
    public function handle_activate()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'core-events-pro'));
        }

        check_admin_referer('cep_activate_license_action', 'cep_activate_license_nonce');

        $purchase_code = isset($_POST['purchase_code']) ? sanitize_text_field(wp_unslash($_POST['purchase_code'])) : '';

        if (empty($purchase_code)) {
            $this->redirect_with_notice('invalid', 'empty_code');
        }

        $is_valid = $this->verify_purchase_code($purchase_code);

        if ($is_valid) {
            update_option(self::OPTION_PURCHASE_CODE, $purchase_code);
            update_option(self::OPTION_LICENSE_STATUS, 'active');
            update_option(self::OPTION_ACTIVATED_DOMAIN, home_url());
            $this->redirect_with_notice('success', 'activated');
        }

        update_option(self::OPTION_LICENSE_STATUS, 'inactive');
        $this->redirect_with_notice('invalid', 'verification_failed');
    }

    /**
     * Handle license deactivation request.
     *
     * @return void
     */
    public function handle_deactivate()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'core-events-pro'));
        }

        check_admin_referer('cep_deactivate_license_action', 'cep_deactivate_license_nonce');

        delete_option(self::OPTION_PURCHASE_CODE);
        update_option(self::OPTION_LICENSE_STATUS, 'inactive');
        delete_option(self::OPTION_ACTIVATED_DOMAIN);

        $this->redirect_with_notice('success', 'deactivated');
    }

    /**
     * Verify purchase code against Envato API.
     *
     * @param string $purchase_code Purchase code.
     * @return bool
     */
    private function verify_purchase_code($purchase_code)
    {
        $response = wp_remote_get(
            self::ENVATO_VERIFY_ENDPOINT,
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $purchase_code,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        return (200 === (int) $response_code);
    }

    /**
     * Get normalized license status.
     *
     * @return string
     */
    private function get_license_status()
    {
        $status = get_option(self::OPTION_LICENSE_STATUS, 'inactive');

        return ('active' === $status) ? 'active' : 'inactive';
    }

    /**
     * Check whether the current domain matches activated domain.
     *
     * @return bool
     */
    private function is_current_domain_valid()
    {
        $activated_domain = (string) get_option(self::OPTION_ACTIVATED_DOMAIN, '');

        if (empty($activated_domain)) {
            return true;
        }

        return untrailingslashit(home_url()) === untrailingslashit($activated_domain);
    }

    /**
     * Determine if license is active and bound to this domain.
     *
     * @return bool
     */
    public function is_license_active()
    {
        return ('active' === $this->get_license_status()) && $this->is_current_domain_valid();
    }

    /**
     * Restrict automatic updates to active licenses.
     *
     * @return bool
     */
    public function can_use_automatic_updates()
    {
        return $this->is_license_active();
    }

    /**
     * Restrict premium templates to active licenses.
     *
     * @return bool
     */
    public function can_use_premium_templates()
    {
        return $this->is_license_active();
    }

    /**
     * Restrict advanced modules to active licenses.
     *
     * @return bool
     */
    public function can_use_advanced_modules()
    {
        return $this->is_license_active();
    }

    /**
     * Display notices related to license status.
     *
     * @return void
     */
    public function maybe_show_admin_notices()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['page']) && 'cep-license' === sanitize_text_field(wp_unslash($_GET['page'])) && isset($_GET['cep_license_notice'])) {
            $notice = sanitize_text_field(wp_unslash($_GET['cep_license_notice']));
            $this->render_notice_from_query($notice);
        }

        if ('active' !== $this->get_license_status()) {
            $license_url = admin_url('edit.php?post_type=main_event&page=cep-license');
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                wp_kses_post(
                    sprintf(
                        /* translators: %s: license page URL. */
                        __('Core Events Pro is running in limited mode. Activate your license to unlock automatic updates, premium templates, and advanced modules. <a href="%s">Activate now</a>.', 'core-events-pro'),
                        esc_url($license_url)
                    )
                )
            );
            return;
        }

        if (! $this->is_current_domain_valid()) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__('Your license is active but bound to another domain. Premium features are restricted on this domain.', 'core-events-pro')
            );
        }
    }

    /**
     * Render admin notice from URL query state.
     *
     * @param string $notice Notice key.
     * @return void
     */
    private function render_notice_from_query($notice)
    {
        switch ($notice) {
            case 'activated':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('License activated successfully.', 'core-events-pro') . '</p></div>';
                break;
            case 'deactivated':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('License deactivated successfully.', 'core-events-pro') . '</p></div>';
                break;
            case 'empty_code':
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Please enter a purchase code.', 'core-events-pro') . '</p></div>';
                break;
            case 'verification_failed':
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid purchase code or verification failed.', 'core-events-pro') . '</p></div>';
                break;
        }
    }

    /**
     * Redirect to license page with notice.
     *
     * @param string $result Result key.
     * @param string $notice Notice key.
     * @return void
     */
    private function redirect_with_notice($result, $notice)
    {
        $url = add_query_arg(
            [
                'post_type'          => 'main_event',
                'page'               => 'cep-license',
                'cep_license_result' => $result,
                'cep_license_notice' => $notice,
            ],
            admin_url('edit.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
