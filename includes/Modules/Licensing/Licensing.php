<?php

/**
 * Licensing Module.
 *
 * Activates and validates a CodeCanyon (Envato) purchase code, then gates
 * premium plugin features behind that activation.
 *
 * Verification strategy
 * ---------------------
 * The plugin supports three verification modes, picked automatically based
 * on what the site owner has configured:
 *
 *   1. `server` (preferred for production):
 *      The site sends the purchase code to a self-hosted license server
 *      controlled by the plugin author. That server holds the author's
 *      Envato Personal Token securely and returns a JSON envelope.
 *      Activated by setting the `cep_license_server_url` filter or the
 *      `CEP_LICENSE_SERVER` constant.
 *
 *   2. `envato_direct`:
 *      The site talks directly to Envato's API using the author's Personal
 *      Token (set via the `CEP_ENVATO_TOKEN` constant). Useful for
 *      single-site authors who do not want to run their own server, but
 *      the token MUST never be distributed in the plugin zip - the
 *      site owner sets it in their wp-config.php.
 *
 *   3. `format_only` (fallback):
 *      No remote check is performed. The plugin only verifies that the
 *      submitted purchase code matches Envato's UUID format. Premium
 *      features unlock immediately. This is the safe default for the
 *      first plugin release - the author can switch to `server` mode
 *      later without changing the plugin code.
 *
 * Heartbeat
 * ---------
 * Once a week the plugin re-validates the activation. Network failures
 * are tolerated for 30 days (grace period) so that an Envato outage
 * never disables a paying customer's plugin.
 *
 * @package CoreEventsPro\Modules
 * @since   1.0.0
 */

namespace CoreEventsPro\Modules;

if (! defined('ABSPATH')) {
    exit;
}

class Licensing
{
    /** @var string */
    const OPTION_PURCHASE_CODE    = 'core_events_pro_purchase_code';
    /** @var string */
    const OPTION_LICENSE_STATUS   = 'core_events_pro_license_status';
    /** @var string */
    const OPTION_ACTIVATED_DOMAIN = 'core_events_pro_activated_domain';
    /** @var string */
    const OPTION_LICENSE_DATA     = 'core_events_pro_license_data';
    /** @var string */
    const OPTION_LAST_CHECK       = 'core_events_pro_license_last_check';
    /** @var string */
    const OPTION_LAST_ERROR       = 'core_events_pro_license_last_error';

    /** @var string */
    const ENVATO_DIRECT_ENDPOINT = 'https://api.envato.com/v3/market/author/sale';

    /**
     * Maximum age (in seconds) for a healthy verification before we mark
     * the license stale. After this, the plugin will trigger a re-check
     * during the daily cron run.
     *
     * @var int
     */
    const HEARTBEAT_INTERVAL = 7 * DAY_IN_SECONDS;

    /**
     * Maximum time we tolerate without a successful re-verification
     * before the license is considered inactive. Generous on purpose -
     * a multi-day Envato outage should never break a paying customer's
     * site.
     *
     * @var int
     */
    const GRACE_PERIOD = 30 * DAY_IN_SECONDS;

    /**
     * Cron hook for the weekly heartbeat.
     *
     * @var string
     */
    const CRON_HOOK = 'cep_license_heartbeat';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_license_page']);
        add_action('admin_post_cep_activate_license',   [$this, 'handle_activate']);
        add_action('admin_post_cep_deactivate_license', [$this, 'handle_deactivate']);
        add_action('admin_post_cep_recheck_license',    [$this, 'handle_recheck']);
        add_action('admin_notices',                     [$this, 'maybe_show_admin_notices']);

        // Heartbeat scheduling
        add_action(self::CRON_HOOK, [$this, 'run_heartbeat']);
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }

        // Public filters for premium gating
        add_filter('cep_license_is_active',         [$this, 'is_license_active']);
        add_filter('cep_premium_updates_enabled',   [$this, 'can_use_automatic_updates']);
        add_filter('cep_premium_templates_enabled', [$this, 'can_use_premium_templates']);
        add_filter('cep_advanced_modules_enabled',  [$this, 'can_use_advanced_modules']);
    }

    // -------------------------------------------------------------------
    // Admin page
    // -------------------------------------------------------------------

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

    public function render_license_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'core-events-pro'));
        }

        $is_active        = $this->is_license_active();
        $purchase_code    = (string) get_option(self::OPTION_PURCHASE_CODE, '');
        $activated_domain = (string) get_option(self::OPTION_ACTIVATED_DOMAIN, '');
        $license_data     = (array)  get_option(self::OPTION_LICENSE_DATA, []);
        $last_check       = (int)    get_option(self::OPTION_LAST_CHECK, 0);
        $last_error       = (string) get_option(self::OPTION_LAST_ERROR, '');
        $mode             = $this->get_verification_mode();

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
                    </td>
                </tr>

                <?php if (! empty($activated_domain)) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Activated Domain', 'core-events-pro'); ?></th>
                        <td><code><?php echo esc_html($activated_domain); ?></code></td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <th scope="row"><?php esc_html_e('Verification Mode', 'core-events-pro'); ?></th>
                    <td>
                        <code><?php echo esc_html($mode); ?></code>
                        <p class="description"><?php echo esc_html($this->describe_mode($mode)); ?></p>
                    </td>
                </tr>

                <?php if (! empty($license_data['buyer'])) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Buyer', 'core-events-pro'); ?></th>
                        <td><?php echo esc_html($license_data['buyer']); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if (! empty($license_data['item_id'])) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Item', 'core-events-pro'); ?></th>
                        <td>
                            <?php
                            if (! empty($license_data['item_name'])) {
                                echo esc_html($license_data['item_name']);
                            }
                            echo ' (#' . esc_html($license_data['item_id']) . ')';
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php if (! empty($license_data['supported_until'])) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Support Valid Until', 'core-events-pro'); ?></th>
                        <td><?php echo esc_html($license_data['supported_until']); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($last_check) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Last Verified', 'core-events-pro'); ?></th>
                        <td>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %s: human-readable time difference. */
                                    __('%s ago', 'core-events-pro'),
                                    human_time_diff($last_check, current_time('timestamp'))
                                )
                            );
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php if (! empty($last_error)) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Last Verification Error', 'core-events-pro'); ?></th>
                        <td><span style="color:#b32d2e;"><?php echo esc_html($last_error); ?></span></td>
                    </tr>
                <?php endif; ?>
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
                                placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            />
                            <p class="description">
                                <?php esc_html_e('Find your purchase code in your CodeCanyon account under Downloads.', 'core-events-pro'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Activate License', 'core-events-pro'), 'primary', 'submit', false); ?>
            </form>

            <?php if ($is_active) : ?>
                <div style="margin-top:20px; display:flex; gap:10px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('cep_recheck_license_action', 'cep_recheck_license_nonce'); ?>
                        <input type="hidden" name="action" value="cep_recheck_license" />
                        <?php submit_button(__('Re-check Now', 'core-events-pro'), 'secondary', 'submit', false); ?>
                    </form>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('cep_deactivate_license_action', 'cep_deactivate_license_nonce'); ?>
                        <input type="hidden" name="action" value="cep_deactivate_license" />
                        <?php submit_button(__('Deactivate License', 'core-events-pro'), 'delete', 'submit', false); ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------
    // Action handlers
    // -------------------------------------------------------------------

    public function handle_activate()
    {
        $this->require_cap_and_nonce('cep_activate_license_action', 'cep_activate_license_nonce');

        $purchase_code = isset($_POST['purchase_code'])
            ? sanitize_text_field(wp_unslash($_POST['purchase_code']))
            : '';

        // Step 1: format guard. Always runs, even before any network call.
        if (! self::is_valid_format($purchase_code)) {
            $this->store_failure(__('Invalid purchase code format. Expected an Envato UUID (8-4-4-4-12).', 'core-events-pro'));
            $this->redirect_with_notice('invalid', 'invalid_format');
        }

        // Step 2: remote verification (or format-only fallback).
        $result = $this->verify_purchase_code($purchase_code);

        if (is_wp_error($result)) {
            $this->store_failure($result->get_error_message());
            $this->redirect_with_notice('invalid', 'verification_failed');
        }

        // Step 3: success - persist everything.
        update_option(self::OPTION_PURCHASE_CODE,    $purchase_code);
        update_option(self::OPTION_LICENSE_STATUS,   'active');
        update_option(self::OPTION_ACTIVATED_DOMAIN, home_url());
        update_option(self::OPTION_LICENSE_DATA,     is_array($result) ? $result : []);
        update_option(self::OPTION_LAST_CHECK,       current_time('timestamp'));
        delete_option(self::OPTION_LAST_ERROR);

        $this->redirect_with_notice('success', 'activated');
    }

    public function handle_deactivate()
    {
        $this->require_cap_and_nonce('cep_deactivate_license_action', 'cep_deactivate_license_nonce');

        delete_option(self::OPTION_PURCHASE_CODE);
        update_option(self::OPTION_LICENSE_STATUS, 'inactive');
        delete_option(self::OPTION_ACTIVATED_DOMAIN);
        delete_option(self::OPTION_LICENSE_DATA);
        delete_option(self::OPTION_LAST_CHECK);
        delete_option(self::OPTION_LAST_ERROR);

        $this->redirect_with_notice('success', 'deactivated');
    }

    public function handle_recheck()
    {
        $this->require_cap_and_nonce('cep_recheck_license_action', 'cep_recheck_license_nonce');

        $code = (string) get_option(self::OPTION_PURCHASE_CODE, '');
        if ('' === $code) {
            $this->redirect_with_notice('invalid', 'no_code');
        }

        $result = $this->verify_purchase_code($code);

        if (is_wp_error($result)) {
            $this->store_failure($result->get_error_message());
            $this->redirect_with_notice('invalid', 'verification_failed');
        }

        update_option(self::OPTION_LICENSE_DATA, is_array($result) ? $result : []);
        update_option(self::OPTION_LAST_CHECK,   current_time('timestamp'));
        delete_option(self::OPTION_LAST_ERROR);

        // A successful re-check brings us out of grace-period limbo.
        update_option(self::OPTION_LICENSE_STATUS, 'active');

        $this->redirect_with_notice('success', 'rechecked');
    }

    /**
     * Cron callback. Runs daily, but only re-verifies once per
     * HEARTBEAT_INTERVAL to avoid hammering the verification server.
     *
     * On network/transient errors we keep the license active until the
     * grace period expires, then mark it inactive (the user gets an
     * admin notice).
     *
     * @return void
     */
    public function run_heartbeat()
    {
        $code = (string) get_option(self::OPTION_PURCHASE_CODE, '');
        if ('' === $code) {
            return;
        }

        $last  = (int) get_option(self::OPTION_LAST_CHECK, 0);
        $now   = current_time('timestamp');

        if ($last && ($now - $last) < self::HEARTBEAT_INTERVAL) {
            return;
        }

        $result = $this->verify_purchase_code($code);

        if (is_wp_error($result)) {
            // Network or remote failure: do not flip status if we are still
            // inside the grace period. Just record the error.
            update_option(self::OPTION_LAST_ERROR, $result->get_error_message());

            $reference = $last ?: $now;
            if ($last && ($now - $reference) > self::GRACE_PERIOD) {
                update_option(self::OPTION_LICENSE_STATUS, 'inactive');
            }
            return;
        }

        update_option(self::OPTION_LICENSE_DATA,   is_array($result) ? $result : []);
        update_option(self::OPTION_LAST_CHECK,     $now);
        update_option(self::OPTION_LICENSE_STATUS, 'active');
        delete_option(self::OPTION_LAST_ERROR);
    }

    // -------------------------------------------------------------------
    // Verification core
    // -------------------------------------------------------------------

    /**
     * Validate that a string matches the Envato purchase code format.
     *
     * Envato codes are RFC 4122 UUIDs (lowercase hex, 8-4-4-4-12).
     *
     * @param string $code Raw user input.
     * @return bool
     */
    public static function is_valid_format($code)
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $code
        );
    }

    /**
     * Decide which verification mode applies based on configuration.
     *
     * @return string One of: server, envato_direct, format_only.
     */
    private function get_verification_mode()
    {
        if ('' !== $this->get_license_server_url()) {
            return 'server';
        }
        if (defined('CEP_ENVATO_TOKEN') && '' !== (string) CEP_ENVATO_TOKEN) {
            return 'envato_direct';
        }
        return 'format_only';
    }

    /**
     * Human-readable description of a verification mode for the admin UI.
     *
     * @param string $mode Mode key.
     * @return string
     */
    private function describe_mode($mode)
    {
        switch ($mode) {
            case 'server':
                return __('Verifying through your custom license server.', 'core-events-pro');
            case 'envato_direct':
                return __('Verifying directly with the Envato API using the configured Personal Token.', 'core-events-pro');
            case 'format_only':
            default:
                return __('No remote verification configured. The plugin only validates the purchase code format.', 'core-events-pro');
        }
    }

    /**
     * Resolve the optional self-hosted license server URL.
     *
     * @return string Empty string if none configured.
     */
    private function get_license_server_url()
    {
        $url = '';

        if (defined('CEP_LICENSE_SERVER') && '' !== (string) CEP_LICENSE_SERVER) {
            $url = (string) CEP_LICENSE_SERVER;
        }

        $url = (string) apply_filters('cep_license_server_url', $url);
        return esc_url_raw($url);
    }

    /**
     * Run the actual verification according to the active mode.
     *
     * Returns the parsed license metadata on success (an associative array
     * with keys like buyer, item_id, item_name, supported_until) or a
     * WP_Error on failure. An empty array is a valid success result and
     * means "verified but no metadata available" (format_only mode).
     *
     * @param string $purchase_code Already format-validated code.
     * @return array|\WP_Error
     */
    private function verify_purchase_code($purchase_code)
    {
        // Always re-validate format defensively, in case this is called
        // from a stored value rather than fresh user input.
        if (! self::is_valid_format($purchase_code)) {
            return new \WP_Error('invalid_format', __('Invalid purchase code format.', 'core-events-pro'));
        }

        switch ($this->get_verification_mode()) {
            case 'server':
                return $this->verify_via_server($purchase_code);
            case 'envato_direct':
                return $this->verify_via_envato($purchase_code);
            case 'format_only':
            default:
                return [];
        }
    }

    /**
     * Verify via a self-hosted license server.
     *
     * Expected protocol: POST JSON `{ "code": "...", "domain": "..." }` and
     * receive back JSON `{ "valid": true, "buyer": "...", "item_id": ...,
     * "item_name": "...", "supported_until": "..." }`.
     *
     * @param string $purchase_code
     * @return array|\WP_Error
     */
    private function verify_via_server($purchase_code)
    {
        $url = $this->get_license_server_url();

        $response = wp_remote_post(
            $url,
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'code'   => $purchase_code,
                    'domain' => home_url(),
                    'plugin' => 'core-events-pro',
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('server_unreachable', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (200 !== $code || ! is_array($data)) {
            return new \WP_Error(
                'server_bad_response',
                /* translators: %d: HTTP status code. */
                sprintf(__('License server returned an unexpected response (HTTP %d).', 'core-events-pro'), $code)
            );
        }

        if (empty($data['valid'])) {
            $message = isset($data['message'])
                ? (string) $data['message']
                : __('The license server rejected this purchase code.', 'core-events-pro');
            return new \WP_Error('server_rejected', $message);
        }

        return [
            'buyer'           => isset($data['buyer'])           ? sanitize_text_field((string) $data['buyer'])           : '',
            'item_id'         => isset($data['item_id'])         ? absint($data['item_id'])                               : 0,
            'item_name'       => isset($data['item_name'])       ? sanitize_text_field((string) $data['item_name'])       : '',
            'supported_until' => isset($data['supported_until']) ? sanitize_text_field((string) $data['supported_until']) : '',
        ];
    }

    /**
     * Verify directly against the Envato API using the configured Personal
     * Token. The token must be set in wp-config.php as `CEP_ENVATO_TOKEN`
     * and is never sent to the user.
     *
     * @param string $purchase_code
     * @return array|\WP_Error
     */
    private function verify_via_envato($purchase_code)
    {
        $token = (string) CEP_ENVATO_TOKEN;

        $response = wp_remote_get(
            add_query_arg(['code' => $purchase_code], self::ENVATO_DIRECT_ENDPOINT),
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'User-Agent'    => 'Core Events Pro license check',
                ],
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('envato_unreachable', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (404 === $code) {
            return new \WP_Error('envato_not_found', __('Envato could not find a sale for this purchase code.', 'core-events-pro'));
        }

        if (200 !== $code || ! is_array($data)) {
            return new \WP_Error(
                'envato_bad_response',
                /* translators: %d: HTTP status code. */
                sprintf(__('Envato API returned an unexpected response (HTTP %d).', 'core-events-pro'), $code)
            );
        }

        // Envato response shape (simplified):
        // { "amount": "...", "sold_at": "...", "supported_until": "...",
        //   "buyer": "...", "item": { "id": ..., "name": "..." } }
        $item = isset($data['item']) && is_array($data['item']) ? $data['item'] : [];

        return [
            'buyer'           => isset($data['buyer'])           ? sanitize_text_field((string) $data['buyer'])           : '',
            'item_id'         => isset($item['id'])              ? absint($item['id'])                                    : 0,
            'item_name'       => isset($item['name'])            ? sanitize_text_field((string) $item['name'])            : '',
            'supported_until' => isset($data['supported_until']) ? sanitize_text_field((string) $data['supported_until']) : '',
        ];
    }

    // -------------------------------------------------------------------
    // Public API (filters)
    // -------------------------------------------------------------------

    /**
     * True if the plugin should treat itself as licensed on this domain.
     *
     * @return bool
     */
    public function is_license_active()
    {
        $status = get_option(self::OPTION_LICENSE_STATUS, 'inactive');
        if ('active' !== $status) {
            return false;
        }
        return $this->is_current_domain_valid();
    }

    public function can_use_automatic_updates()  { return $this->is_license_active(); }
    public function can_use_premium_templates()  { return $this->is_license_active(); }
    public function can_use_advanced_modules()   { return $this->is_license_active(); }

    /**
     * Whether the current site URL still matches the activated one.
     *
     * @return bool
     */
    private function is_current_domain_valid()
    {
        $activated = (string) get_option(self::OPTION_ACTIVATED_DOMAIN, '');
        if ('' === $activated) {
            return true;
        }
        return untrailingslashit(home_url()) === untrailingslashit($activated);
    }

    // -------------------------------------------------------------------
    // Admin notices
    // -------------------------------------------------------------------

    public function maybe_show_admin_notices()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Per-action notice from the URL (after redirects).
        if (isset($_GET['page']) && 'cep-license' === sanitize_text_field(wp_unslash($_GET['page'])) && isset($_GET['cep_license_notice'])) {
            $notice = sanitize_text_field(wp_unslash($_GET['cep_license_notice']));
            $this->render_notice_from_query($notice);
        }

        // Persistent banner: nag inactive sites to activate.
        if (! $this->is_license_active()) {
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

        // Domain mismatch warning.
        if (! $this->is_current_domain_valid()) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__('Your license is active but bound to another domain. Premium features are restricted on this domain.', 'core-events-pro')
            );
        }
    }

    private function render_notice_from_query($notice)
    {
        switch ($notice) {
            case 'activated':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('License activated successfully.', 'core-events-pro') . '</p></div>';
                break;
            case 'deactivated':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('License deactivated successfully.', 'core-events-pro') . '</p></div>';
                break;
            case 'rechecked':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('License re-verified successfully.', 'core-events-pro') . '</p></div>';
                break;
            case 'invalid_format':
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid purchase code format. Please check and try again.', 'core-events-pro') . '</p></div>';
                break;
            case 'verification_failed':
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Could not verify the purchase code. See the error message below the form for details.', 'core-events-pro') . '</p></div>';
                break;
            case 'no_code':
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('No purchase code is currently saved.', 'core-events-pro') . '</p></div>';
                break;
        }
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    private function require_cap_and_nonce($action, $nonce_field)
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'core-events-pro'));
        }
        check_admin_referer($action, $nonce_field);
    }

    private function store_failure($message)
    {
        update_option(self::OPTION_LAST_ERROR, (string) $message);
        update_option(self::OPTION_LAST_CHECK, current_time('timestamp'));
    }

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
