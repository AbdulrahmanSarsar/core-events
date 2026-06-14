<?php

/**
 * Email Queue Helper.
 *
 * Replaces direct `wp_mail()` calls with a database-backed queue that a
 * background cron worker drains every minute. The motivation:
 *
 *   - A single HTTP request that needs to send 200 reminder emails would
 *     otherwise block the user (or the cron runner) for several seconds
 *     and frequently time out on shared hosting.
 *   - Failed deliveries can be retried with exponential backoff instead
 *     of being lost forever.
 *   - The admin gets an audit trail of what was attempted, when, and
 *     why it failed.
 *
 * Public surface:
 *   EmailQueue::queue($to, $subject, $body[, $headers[, $delay]])
 *
 * Filters:
 *   cep_email_queue_enabled       (bool, default true)
 *   cep_email_queue_batch_size    (int,  default 20)
 *   cep_email_queue_max_attempts  (int,  default 3)
 *
 * @package CoreEventsPro\Helpers
 * @since   1.1.0
 */

namespace CoreEventsPro\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

class EmailQueue
{
    /** @var string Custom cron interval slug. */
    const CRON_SCHEDULE = 'cep_every_minute';

    /** @var string Cron hook the worker listens on. */
    const CRON_HOOK = 'cep_email_queue_worker';

    /** @var string Transient key used as a worker lock. */
    const LOCK_KEY = 'cep_email_queue_lock';

    /** @var int Default rows pulled per worker tick. */
    const BATCH_SIZE = 20;

    /** @var int Default maximum delivery attempts before giving up. */
    const MAX_ATTEMPTS = 3;

    /** @var int Lock TTL - long enough to outlive a slow batch, short enough to recover from a crashed worker. */
    const LOCK_TTL = 5 * MINUTE_IN_SECONDS;

    public function __construct()
    {
        // Register the 1-minute cron interval (WordPress only ships with
        // hourly / twicedaily / daily by default).
        add_filter('cron_schedules', [$this, 'register_cron_interval']);

        // Worker hook
        add_action(self::CRON_HOOK, [$this, 'process_batch']);

        // Schedule the worker if it has not been scheduled yet.
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    /**
     * Register a custom 1-minute cron interval used by the worker.
     *
     * @param array<string, array<string, mixed>> $schedules Existing schedules.
     * @return array
     */
    public function register_cron_interval($schedules)
    {
        $schedules[self::CRON_SCHEDULE] = [
            'interval' => MINUTE_IN_SECONDS,
            'display'  => __('Every Minute (Core Events Pro Email Queue)', 'core-events-pro'),
        ];
        return $schedules;
    }

    /**
     * Enqueue an email for asynchronous delivery.
     *
     * If the queue has been disabled via the `cep_email_queue_enabled`
     * filter, this falls back to a direct `wp_mail()` call so behaviour
     * stays identical for sites that prefer synchronous delivery.
     *
     * @param string|array       $to       Recipient(s).
     * @param string             $subject  Email subject.
     * @param string             $body     Email body (HTML allowed).
     * @param array<int, string> $headers  Headers (defaults to UTF-8 HTML).
     * @param int                $delay    Seconds to wait before the worker
     *                                     considers the email eligible.
     * @return int|\WP_Error  Inserted row id on success, WP_Error on failure.
     */
    public static function queue($to, $subject, $body, $headers = [], $delay = 0)
    {
        if (! apply_filters('cep_email_queue_enabled', true)) {
            return self::send_now($to, $subject, $body, $headers);
        }

        $headers = self::normalize_headers($headers);
        $to      = self::normalize_recipients($to);

        if ('' === $to) {
            return new \WP_Error('queue_no_recipient', __('Cannot queue an email without a recipient.', 'core-events-pro'));
        }

        global $wpdb;

        $delay   = max(0, (int) $delay);
        $sched   = $delay > 0
            ? gmdate('Y-m-d H:i:s', time() + $delay)
            : current_time('mysql', true);

        $inserted = $wpdb->insert(
            self::table_name(),
            [
                'recipient'     => $to,
                'subject'       => (string) $subject,
                'body'          => (string) $body,
                'headers'       => wp_json_encode($headers),
                'status'        => 'pending',
                'attempts'      => 0,
                'last_error'    => '',
                'scheduled_for' => $sched,
                'created_at'    => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        if (false === $inserted) {
            return new \WP_Error('queue_insert_failed', __('Could not enqueue email.', 'core-events-pro'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Process one batch of pending emails. Called by WP-Cron every minute.
     *
     * A short transient acts as a worker lock: if a previous tick is still
     * running (slow SMTP, large batch), this tick exits immediately rather
     * than double-sending. The lock TTL is short enough that a crashed
     * worker recovers automatically on the next minute.
     *
     * @return void
     */
    public function process_batch()
    {
        // Acquire lock. add_option-style atomic check via transient.
        if (false !== get_transient(self::LOCK_KEY)) {
            return;
        }
        set_transient(self::LOCK_KEY, 1, self::LOCK_TTL);

        try {
            $this->process_batch_inner();
        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }

    /**
     * The actual worker body, separated so the lock cleanup in
     * process_batch() always runs even when this throws.
     *
     * @return void
     */
    private function process_batch_inner()
    {
        global $wpdb;

        $batch_size   = max(1, (int) apply_filters('cep_email_queue_batch_size', self::BATCH_SIZE));
        $max_attempts = max(1, (int) apply_filters('cep_email_queue_max_attempts', self::MAX_ATTEMPTS));
        $table        = self::table_name();
        $now          = current_time('mysql', true);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, recipient, subject, body, headers, attempts
             FROM {$table}
             WHERE status = 'pending'
               AND scheduled_for <= %s
               AND attempts < %d
             ORDER BY scheduled_for ASC, id ASC
             LIMIT %d",
            $now,
            $max_attempts,
            $batch_size
        ));

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $headers = self::decode_headers($row->headers);
            $sent    = wp_mail($row->recipient, $row->subject, $row->body, $headers);

            if ($sent) {
                $wpdb->update(
                    $table,
                    [
                        'status'  => 'sent',
                        'sent_at' => current_time('mysql', true),
                    ],
                    ['id' => (int) $row->id],
                    ['%s', '%s'],
                    ['%d']
                );
                continue;
            }

            $new_attempts = (int) $row->attempts + 1;

            if ($new_attempts >= $max_attempts) {
                $wpdb->update(
                    $table,
                    [
                        'status'     => 'failed',
                        'attempts'   => $new_attempts,
                        'last_error' => __('wp_mail() returned false on the final attempt.', 'core-events-pro'),
                    ],
                    ['id' => (int) $row->id],
                    ['%s', '%d', '%s'],
                    ['%d']
                );
                continue;
            }

            // Reschedule with exponential-ish backoff.
            $delay     = self::backoff_seconds($new_attempts);
            $next_when = gmdate('Y-m-d H:i:s', time() + $delay);

            $wpdb->update(
                $table,
                [
                    'attempts'      => $new_attempts,
                    'scheduled_for' => $next_when,
                    'last_error'    => __('wp_mail() returned false; will retry.', 'core-events-pro'),
                ],
                ['id' => (int) $row->id],
                ['%d', '%s', '%s'],
                ['%d']
            );
        }
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    /**
     * Direct synchronous send used as a fallback when the queue is
     * disabled by filter.
     *
     * @param mixed              $to
     * @param string             $subject
     * @param string             $body
     * @param array<int, string> $headers
     * @return int|\WP_Error 1 if delivered, WP_Error otherwise.
     */
    private static function send_now($to, $subject, $body, $headers)
    {
        $sent = wp_mail($to, $subject, $body, self::normalize_headers($headers));
        if ($sent) {
            return 1;
        }
        return new \WP_Error('mail_failed', __('wp_mail() returned false.', 'core-events-pro'));
    }

    /**
     * Default headers for HTML emails when the caller did not supply any.
     *
     * @param mixed $headers
     * @return array<int, string>
     */
    private static function normalize_headers($headers)
    {
        if (empty($headers)) {
            return ['Content-Type: text/html; charset=UTF-8'];
        }
        if (is_string($headers)) {
            return [$headers];
        }
        return array_values(array_map('strval', (array) $headers));
    }

    /**
     * Recipients are stored as a single comma-joined string so the
     * column stays a simple varchar. wp_mail() accepts that format
     * natively.
     *
     * @param mixed $to
     * @return string
     */
    private static function normalize_recipients($to)
    {
        if (is_array($to)) {
            $clean = array_filter(array_map('sanitize_email', $to));
            return implode(',', $clean);
        }
        return sanitize_email((string) $to);
    }

    /**
     * Decode a JSON-encoded headers column back to an array, falling back
     * to the safe HTML default if the row is malformed.
     *
     * @param string $raw
     * @return array<int, string>
     */
    private static function decode_headers($raw)
    {
        if ('' === (string) $raw) {
            return ['Content-Type: text/html; charset=UTF-8'];
        }
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded) || empty($decoded)) {
            return ['Content-Type: text/html; charset=UTF-8'];
        }
        return array_values(array_map('strval', $decoded));
    }

    /**
     * Backoff schedule between retries.
     *
     * Attempt 1 fail -> retry in 5 minutes
     * Attempt 2 fail -> retry in 30 minutes
     * Attempt 3 fail -> retry in 2 hours (only relevant if max_attempts is raised)
     *
     * @param int $attempts
     * @return int Seconds to wait.
     */
    private static function backoff_seconds($attempts)
    {
        $minutes = [5, 30, 120];
        $idx     = max(0, min((int) $attempts - 1, count($minutes) - 1));
        return $minutes[$idx] * MINUTE_IN_SECONDS;
    }

    /**
     * Resolve the prefixed table name lazily.
     *
     * @return string
     */
    private static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'cep_email_queue';
    }
}
