<?php

/**
 * Anti-spam Helper.
 *
 * Layered defense for the public RSVP form so we do not need to lean on
 * any third-party CAPTCHA by default. The four layers are:
 *
 *   1. Honeypot field
 *      A visually hidden text input. Bots that auto-fill every field
 *      will populate it; humans never see it.
 *
 *   2. Submission age check
 *      A hidden timestamp inserted when the form is rendered. We reject
 *      submissions that arrive faster than a human can plausibly type
 *      (and submissions older than 24 hours, which catches stale tabs
 *      and primitive replay attacks).
 *
 *   3. Per-IP rate limit
 *      A short-lived transient counter. After 5 attempts in 10 minutes
 *      from the same IP, further submissions are rejected. Successful
 *      submissions reset the counter for that IP.
 *
 *   4. Optional disposable-email blocking
 *      Off by default. When enabled, submissions using a known throwaway
 *      provider are rejected. The list is filterable so site owners can
 *      tune it.
 *
 * Failure messages are deliberately generic to avoid telling bots which
 * check tripped them up.
 *
 * @package CoreEventsPro\Helpers
 * @since   1.1.0
 */

namespace CoreEventsPro\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

class AntiSpam
{
    /** @var string Hidden honeypot field name. */
    const HONEYPOT_NAME = 'cep_website_url';

    /** @var string Hidden timestamp field name. */
    const TIMESTAMP_NAME = 'cep_form_ts';

    /** @var int Minimum seconds between form render and submit. */
    const MIN_FORM_AGE = 3;

    /** @var int Maximum form age before we consider it stale. */
    const MAX_FORM_AGE = DAY_IN_SECONDS;

    /** @var int Max submissions allowed per IP inside the window. */
    const MAX_ATTEMPTS_PER_WINDOW = 5;

    /** @var int Rate-limit window length in seconds. */
    const RATE_LIMIT_WINDOW = 10 * MINUTE_IN_SECONDS;

    /** @var string Option key controlling the disposable email block. */
    const OPTION_BLOCK_DISPOSABLE = 'cep_block_disposable_emails';

    // -------------------------------------------------------------------
    // Form rendering
    // -------------------------------------------------------------------

    /**
     * Print the hidden honeypot + timestamp inputs.
     *
     * Should be called once inside any public-facing form whose handler
     * goes through self::check(). The honeypot is wrapped in absolute
     * positioning + tabindex=-1 so screen readers and keyboard users
     * also skip it - only naive auto-fillers touch it.
     *
     * @return void
     */
    public static function render_fields()
    {
        $ts = time();
        ?>
        <div style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
            <label for="<?php echo esc_attr(self::HONEYPOT_NAME); ?>">
                <?php esc_html_e('Leave this field empty', 'core-events-pro'); ?>
            </label>
            <input
                type="text"
                name="<?php echo esc_attr(self::HONEYPOT_NAME); ?>"
                id="<?php echo esc_attr(self::HONEYPOT_NAME); ?>"
                value=""
                autocomplete="off"
                tabindex="-1"
            />
        </div>
        <input
            type="hidden"
            name="<?php echo esc_attr(self::TIMESTAMP_NAME); ?>"
            value="<?php echo esc_attr((string) $ts); ?>"
        />
        <?php
    }

    // -------------------------------------------------------------------
    // Validation entry points
    // -------------------------------------------------------------------

    /**
     * Run the per-submission checks (honeypot + age).
     *
     * @param array $post The raw $_POST array (still slashed).
     * @return true|\WP_Error
     */
    public static function check(array $post)
    {
        // 1. Honeypot must be untouched.
        $honeypot = isset($post[self::HONEYPOT_NAME])
            ? trim((string) wp_unslash($post[self::HONEYPOT_NAME]))
            : '';

        if ('' !== $honeypot) {
            return new \WP_Error('spam_honeypot', __('Submission rejected.', 'core-events-pro'));
        }

        // 2. Timestamp must be present and the form age must be sensible.
        $ts = isset($post[self::TIMESTAMP_NAME])
            ? (int) wp_unslash($post[self::TIMESTAMP_NAME])
            : 0;

        if ($ts <= 0) {
            return new \WP_Error('spam_no_timestamp', __('Submission rejected.', 'core-events-pro'));
        }

        $age = time() - $ts;

        if ($age < self::MIN_FORM_AGE) {
            return new \WP_Error('spam_too_fast', __('Submission rejected.', 'core-events-pro'));
        }

        if ($age > self::MAX_FORM_AGE) {
            return new \WP_Error(
                'spam_form_expired',
                __('This form has expired. Please refresh the page and try again.', 'core-events-pro')
            );
        }

        return true;
    }

    /**
     * Per-IP rate-limit check. Should be called BEFORE check() so that a
     * single bot hammering the endpoint stops us early without doing any
     * DB work for each attempt.
     *
     * @return true|\WP_Error
     */
    public static function check_rate_limit()
    {
        $ip = self::get_client_ip();
        if ('' === $ip) {
            // We cannot rate-limit an unknown source. Fail open here -
            // the honeypot + age check still applies.
            return true;
        }

        $key      = self::rate_limit_key($ip);
        $attempts = (int) get_transient($key);

        if ($attempts >= self::MAX_ATTEMPTS_PER_WINDOW) {
            return new \WP_Error(
                'rate_limited',
                __('Too many attempts from your network. Please try again in a few minutes.', 'core-events-pro')
            );
        }

        set_transient($key, $attempts + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }

    /**
     * Reset the rate-limit counter for the current IP.
     *
     * Call this after a successful registration so a legitimate visitor
     * who registers two or three friends in quick succession does not
     * get locked out.
     *
     * @return void
     */
    public static function reset_rate_limit()
    {
        $ip = self::get_client_ip();
        if ('' === $ip) {
            return;
        }
        delete_transient(self::rate_limit_key($ip));
    }

    /**
     * Decide whether the supplied email belongs to a known disposable
     * provider. Off by default - enabled per-site through the
     * `cep_block_disposable_emails` option.
     *
     * @param string $email Sanitized email address.
     * @return bool True if the email should be rejected.
     */
    public static function is_disposable_email($email)
    {
        if (! get_option(self::OPTION_BLOCK_DISPOSABLE, 0)) {
            return false;
        }

        $email = strtolower(trim((string) $email));
        $parts = explode('@', $email);

        if (count($parts) !== 2 || '' === $parts[1]) {
            // Malformed addresses are "disposable" for our purposes - we
            // do not want them either way.
            return true;
        }

        $domain = $parts[1];
        return in_array($domain, self::get_disposable_domains(), true);
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    /**
     * Resolve the client IP, with a small amount of proxy awareness.
     *
     * Order of trust:
     *   1. Cloudflare's CF-Connecting-IP (only if present).
     *   2. The first entry in X-Forwarded-For (only if it looks public).
     *   3. REMOTE_ADDR.
     *
     * Strict IP validation prevents spoofed headers from poisoning the
     * rate-limit key with attacker-controlled values.
     *
     * @return string Empty string when nothing usable is found.
     */
    private static function get_client_ip()
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'];

        foreach ($headers as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }
            $raw   = sanitize_text_field(wp_unslash($_SERVER[$header]));
            $first = trim(explode(',', $raw)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $first;
            }
        }

        if (! empty($_SERVER['REMOTE_ADDR'])) {
            $remote = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
            if (filter_var($remote, FILTER_VALIDATE_IP)) {
                return $remote;
            }
        }

        return '';
    }

    /**
     * Build a transient key for a given IP. We hash the IP so the raw
     * value never lands in the options table.
     *
     * @param string $ip
     * @return string
     */
    private static function rate_limit_key($ip)
    {
        return 'cep_rl_' . md5($ip);
    }

    /**
     * Curated list of common disposable email domains.
     *
     * Filterable so site owners can extend or replace it without
     * touching the plugin code.
     *
     * @return array<int, string>
     */
    private static function get_disposable_domains()
    {
        $domains = [
            'mailinator.com', '10minutemail.com', 'guerrillamail.com',
            'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'yopmail.com',
            'throwaway.email', 'getnada.com', 'maildrop.cc', 'mintemail.com',
            'sharklasers.com', 'spam4.me', 'trashmail.com', 'tempinbox.com',
            'fakeinbox.com', 'mohmal.com', 'inboxbear.com', 'emailondeck.com',
            'discard.email', 'dispostable.com', 'mytemp.email', 'mail.tm',
            'tempmailo.com', 'minuteinbox.com', 'tmail.io', 'mailcatch.com',
        ];

        return (array) apply_filters('cep_disposable_email_domains', $domains);
    }
}
