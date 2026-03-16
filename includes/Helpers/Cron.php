<?php

/**
 * Cron Helper Class.
 * 
 * Handles scheduled background tasks (Cron Jobs) for updating event statuses
 * automatically based on time, and sending automated reminder emails to attendees
 * 24 hours before the event starts.
 *
 * @package CoreEventsPro\Helpers
 * @since 4.0.0
 */

namespace CoreEventsPro\Helpers;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Cron
 *
 * Manages WordPress scheduled events for the plugin.
 */
class Cron
{

    /**
     * Constructor.
     * 
     * Hooks the custom hourly action and schedules it if it hasn't been scheduled yet.
     */
    public function __construct()
    {
        add_action('cep_hourly_check', [$this, 'update_status_and_reminders']);

        // Schedule the event if it's not already scheduled.
        if (! wp_next_scheduled('cep_hourly_check')) {
            wp_schedule_event(time(), 'hourly', 'cep_hourly_check');
        }
    }

    /**
     * Update event statuses and send reminder emails.
     * 
     * Runs every hour. Checks all events against the current time to update their
     * status (upcoming, ongoing, finished) and triggers reminder emails for events
     * starting tomorrow.
     *
     * @return void
     */
    public function update_status_and_reminders()
    {
        // Fetch all events without a pagination limit.
        $events = get_posts([
            'post_type'      => ['main_event', 'sub_event'],
            'posts_per_page' => -1
        ]);

        $now = current_time('Y-m-d H:i');

        // Calculate the time window (approx. 24 hours from now) to remind attendees.
        $tomorrow_start = date('Y-m-d H:i', strtotime('+23 hours'));
        $tomorrow_end   = date('Y-m-d H:i', strtotime('+25 hours'));

        foreach ($events as $event) {
            $status = get_post_meta($event->ID, '_cep_status', true);
            $start  = get_post_meta($event->ID, '_cep_start', true);
            $end    = get_post_meta($event->ID, '_cep_end', true);

            // Fallback: If no end date is provided, assume it equals the start date.
            if (! $end) {
                $end = $start;
            }

            // --- 1. Update Event Status ---
            if ($status !== 'cancelled') {
                $new_status = $status;

                if ($now < $start) {
                    $new_status = 'upcoming';
                } elseif ($now >= $start && $now <= $end) {
                    $new_status = 'ongoing';
                } elseif ($now > $end) {
                    $new_status = 'finished';
                }

                if ($new_status !== $status) {
                    update_post_meta($event->ID, '_cep_status', $new_status);
                }
            }

            // --- 2. Send Reminder Emails (24 hours before the event) ---
            if ($start >= $tomorrow_start && $start <= $tomorrow_end) {

                // Check if the reminder has already been sent to prevent spamming.
                $reminder_sent = get_post_meta($event->ID, '_cep_reminder_sent', true);

                if (! $reminder_sent) {
                    $this->send_reminders_for_event($event->ID, $start);
                    update_post_meta($event->ID, '_cep_reminder_sent', '1'); // Mark as sent.
                }
            }
        }
    }

    /**
     * Send reminder emails for a specific event.
     * 
     * Fetches confirmed attendees from the database and dispatches the customized
     * email template replacing the dynamic tags.
     *
     * @param int    $event_id   The Event Post ID.
     * @param string $start_date The start date of the event.
     * @return void
     */
    private function send_reminders_for_event($event_id, $start_date)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        // Security: Safely check for the table existence before running queries.
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return;
        }

        // Security: Cast $event_id to integer (absint) and prepare the query safely.
        // Fetch only 'confirmed' attendees (ignore waitlist).
        $attendees = $wpdb->get_results(
            $wpdb->prepare("SELECT name, email FROM {$table} WHERE event_id = %d AND status = 'confirmed'", absint($event_id))
        );

        if (empty($attendees)) {
            return;
        }

        $event_title    = get_the_title($event_id);
        $date_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start_date));

        // i18n: Provide translatable default strings for the email template.
        $default_subject = __('Reminder: {event_name} starts tomorrow!', 'core-events-pro');
        $default_body    = __("Hello {name},\n\nFriendly reminder that {event_name} starts on {event_date}.\nSee you there!", 'core-events-pro');

        $subject_template = get_option('cep_email_remind_sub', $default_subject);
        $body_template    = get_option('cep_email_remind_body', $default_body);

        // i18n: Make the status label translatable in emails.
        $status_label     = __('CONFIRMED', 'core-events-pro');

        foreach ($attendees as $att) {
            $replacements = [
                '{name}'       => $att->name,
                '{event_name}' => $event_title,
                '{event_date}' => $date_formatted,
                '{status}'     => $status_label
            ];

            $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
            $body    = str_replace(array_keys($replacements), array_values($replacements), $body_template);

            // Security: Sanitize the email address before sending.
            wp_mail(sanitize_email($att->email), $subject, $body);
        }
    }
}
