<?php

/**
 * Attendees Module Class.
 * 
 * Handles frontend RSVP submissions, automated ticket generation (QR code),
 * manual check-ins via AJAX, QR code scanning logic, and CSV exports.
 *
 * @package CoreEventsPro\Modules
 * @since 4.0.0
 */

namespace CoreEventsPro\Modules;

use CoreEventsPro\Helpers\AntiSpam;
use CoreEventsPro\Helpers\EmailQueue;
use CoreEventsPro\Helpers\QrGenerator;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Attendees
 *
 * Core functional logic for managing event attendees and their data.
 */
class Attendees
{

    /**
     * Constructor.
     * 
     * Initializes AJAX endpoints, admin post actions, and generic hooks.
     */
    public function __construct()
    {
        // AJAX: RSVP Registration (Logged in and Guest users)
        add_action('wp_ajax_cep_submit_rsvp', [$this, 'handle_rsvp']);
        add_action('wp_ajax_nopriv_cep_submit_rsvp', [$this, 'handle_rsvp']);

        // Admin Post Action: CSV Export
        add_action('admin_post_cep_export_csv', [$this, 'export_csv']);

        // AJAX: Manual Check-in from the Admin Dashboard
        add_action('wp_ajax_cep_manual_checkin', [$this, 'manual_checkin']);

        // Endpoint: Handle QR Code scanning
        add_action('init', [$this, 'handle_qr_scan']);

        // Endpoint: Stream a generated QR PNG for a stored attendee token
        add_action('init', [$this, 'handle_qr_image']);
    }

    /**
     * Stream a freshly-generated QR PNG for a stored attendee token.
     *
     * URL pattern: /?cep_qr_image={token}
     *
     * The token must already exist in the attendees table; we never
     * generate a QR for arbitrary input. This prevents attackers from
     * using the endpoint to render arbitrary payloads (which a QR
     * scanner would then execute on the scanning device).
     *
     * Browsers and mail clients are sent strong cache headers via
     * QrGenerator::stream_png() so repeated views do not hammer the
     * server.
     *
     * @return void
     */
    public function handle_qr_image()
    {
        if (! isset($_GET['cep_qr_image'])) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['cep_qr_image']));

        if ('' === $token) {
            status_header(400);
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        // Confirm the token belongs to a real attendee before rendering.
        $valid = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE qr_token = %s LIMIT 1", $token)
        );

        if (! $valid) {
            status_header(404);
            exit;
        }

        // The QR encodes the scan URL, not the raw token, so a single scan
        // immediately performs check-in for staff.
        $scan_url = QrGenerator::get_scan_url($token);
        QrGenerator::stream_png($scan_url);
    }

    /**
     * Handle frontend RSVP form submission via AJAX.
     * 
     * Validates data, checks capacity, generates a QR token, saves the attendee,
     * and triggers the confirmation email.
     *
     * @return void
     */
    public function handle_rsvp()
    {
        // Security Check: Verify AJAX nonce.
        check_ajax_referer('cep_rsvp_nonce', 'security');

        // Anti-spam: rate-limit per IP first so repeat offenders never even
        // get to do any database work.
        $rate = AntiSpam::check_rate_limit();
        if (is_wp_error($rate)) {
            wp_send_json_error(['message' => $rate->get_error_message()]);
        }

        // Anti-spam: honeypot + form age validation.
        $spam_check = AntiSpam::check($_POST);
        if (is_wp_error($spam_check)) {
            wp_send_json_error(['message' => $spam_check->get_error_message()]);
        }

        // Security: Unslash and sanitize inputs safely.
        $event_id_raw = isset($_POST['selected_event_id']) ? $_POST['selected_event_id'] : ($_POST['event_id'] ?? 0);
        $event_id     = absint(wp_unslash($event_id_raw));
        $name         = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email        = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone        = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

        if (! $email || ! $name || ! $event_id) {
            wp_send_json_error(['message' => __('Required fields are missing.', 'core-events-pro')]);
        }

        // Anti-spam: optionally reject disposable / throwaway email
        // providers (off by default; toggled in Settings & Help).
        if (AntiSpam::is_disposable_email($email)) {
            wp_send_json_error([
                'message' => __('Please use a permanent email address.', 'core-events-pro'),
            ]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        // Check for duplicate registration for the same event.
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE event_id = %d AND email = %s", $event_id, $email));

        if ($exists) {
            wp_send_json_error(['message' => __('You are already registered.', 'core-events-pro')]);
        }

        // Check Capacity & Set Status
        $capacity = (int) get_post_meta($event_id, '_cep_capacity', true);
        $status   = 'confirmed';

        if ($capacity > 0) {
            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = 'confirmed'", $event_id));
            if ($count >= $capacity) {
                $status = 'waitlist';
            }
        }

        // Generate a secure, unique QR token for the ticket.
        $qr_token = wp_generate_password(32, false);

        // Save the attendee to the database.
        $inserted = $wpdb->insert($table, [
            'event_id'   => $event_id,
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'status'     => $status,
            'qr_token'   => $qr_token, // Save the generated token
            'created_at' => current_time('mysql')
        ]);

        if ($inserted) {
            $attendee_id = $wpdb->insert_id;

            // A successful registration clears the per-IP rate limit so a
            // legitimate visitor can register a few friends in a row
            // without tripping the throttle.
            AntiSpam::reset_rate_limit();

            // Send Confirmation Email. Only confirmed attendees get the QR code.
            if ($status === 'confirmed') {
                $this->send_confirmation_email($email, $name, $event_id, $status, $qr_token);
            } else {
                $this->send_confirmation_email($email, $name, $event_id, $status, '');
            }

            // i18n: Prepare success messages.
            $msg_waitlist  = __('Added to Waitlist! You will be notified if a seat opens up.', 'core-events-pro');
            $msg_confirmed = __('Registration successful! Check your email for your ticket.', 'core-events-pro');
            $msg           = ($status === 'waitlist') ? $msg_waitlist : $msg_confirmed;

            wp_send_json_success(['message' => $msg, 'status' => $status]);
        } else {
            wp_send_json_error(['message' => __('Database error.', 'core-events-pro')]);
        }
    }

    /**
     * Send the confirmation email with the optional QR ticket.
     *
     * @param string $to_email The recipient email address.
     * @param string $name     The attendee's name.
     * @param int    $event_id The Event Post ID.
     * @param string $status   The attendee status (confirmed/waitlist).
     * @param string $qr_token The secure QR token (empty if waitlisted).
     * @return void
     */
    private function send_confirmation_email($to_email, $name, $event_id, $status, $qr_token)
    {
        $event_title    = get_the_title($event_id);
        $start_date     = get_post_meta($event_id, '_cep_start', true);
        $date_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start_date));

        $subject_template = get_option('cep_email_confirm_sub', __('Registration Confirmed: {event_name}', 'core-events-pro'));
        $body_template    = get_option('cep_email_confirm_body', __("Hello {name},\n\nYour registration for {event_name} is {status}.\nDate: {event_date}\n\nBest Regards,", 'core-events-pro'));

        // Prepare QR Code section. The image is generated locally (no
        // third-party service) and inlined as a base64 data URI so it
        // renders in mail clients even when remote images are blocked.
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

        // i18n: Translate the status label dynamically for the email
        $status_label = ($status === 'waitlist') ? __('WAITLIST', 'core-events-pro') : __('CONFIRMED', 'core-events-pro');

        $replacements = [
            '{name}'       => $name,
            '{event_name}' => $event_title,
            '{status}'     => $status_label,
            '{event_date}' => $date_formatted
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
        $body    = str_replace(array_keys($replacements), array_values($replacements), $body_template);

        // Hand off to the asynchronous email queue. Using HTML headers so
        // the QR image renders inline.
        $headers   = ['Content-Type: text/html; charset=UTF-8'];
        $html_body = nl2br($body) . $qr_html;

        EmailQueue::queue($to_email, $subject, $html_body, $headers);
    }

    /**
     * Handle manual check-in status update from the admin dashboard via AJAX.
     *
     * @return void
     */
    public function manual_checkin()
    {
        // Security Check: Capabilities
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(__('Unauthorized', 'core-events-pro'));
        }

        // Security: Unslash and cast to absolute integer
        $attendee_id     = isset($_POST['attendee_id']) ? absint(wp_unslash($_POST['attendee_id'])) : 0;
        $check_in_status = isset($_POST['status']) ? absint(wp_unslash($_POST['status'])) : 0; // 1 or 0

        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        $updated = $wpdb->update($table, ['check_in' => $check_in_status], ['id' => $attendee_id]);

        if ($updated !== false) {
            wp_send_json_success(__('Updated', 'core-events-pro'));
        } else {
            wp_send_json_error(__('Error updating status', 'core-events-pro'));
        }
    }

    /**
     * Handle the QR scan logic when the scan URL is visited.
     * 
     * Validates the token, checks the attendee in, and outputs the result using wp_die().
     *
     * @return void
     */
    public function handle_qr_scan()
    {
        if (isset($_GET['cep_qr_scan'])) {

            // Security Check: The user must be logged in with editing capabilities to scan tickets.
            if (! current_user_can('edit_posts')) {
                wp_die(esc_html__('Unauthorized: You must be logged in as an admin/editor to scan tickets.', 'core-events-pro'));
            }

            // Security: Unslash and sanitize the token
            $token = sanitize_text_field(wp_unslash($_GET['cep_qr_scan']));

            global $wpdb;
            $table = $wpdb->prefix . 'cep_attendees';

            $attendee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE qr_token = %s", $token));

            if ($attendee) {
                if ($attendee->check_in == 1) {
                    // Ticket already used
                    wp_die(
                        sprintf(
                            '<h3>⚠️ %s</h3><p>%s: %s</p><p>%s</p>',
                            esc_html__('ALREADY CHECKED IN', 'core-events-pro'),
                            esc_html__('Name', 'core-events-pro'),
                            esc_html($attendee->name),
                            esc_html__('This ticket was already used.', 'core-events-pro')
                        )
                    );
                } else {
                    // Perform check-in
                    $wpdb->update($table, ['check_in' => 1], ['id' => $attendee->id]);

                    wp_die(
                        sprintf(
                            '<h3>✅ %s</h3><p>%s: %s</p><p>%s</p>',
                            esc_html__('SUCCESS', 'core-events-pro'),
                            esc_html__('Name', 'core-events-pro'),
                            esc_html($attendee->name),
                            esc_html__('Check-in confirmed!', 'core-events-pro')
                        )
                    );
                }
            } else {
                // Invalid Ticket
                wp_die(
                    sprintf(
                        '<h3>❌ %s</h3><p>%s</p>',
                        esc_html__('INVALID TICKET', 'core-events-pro'),
                        esc_html__('This QR code is not recognized.', 'core-events-pro')
                    )
                );
            }
        }
    }

    /**
     * Export the attendees list for a specific event as a CSV file.
     *
     * @return void
     */
    public function export_csv()
    {
        // Security Check: Capabilities
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'core-events-pro'));
        }

        // Security Check: Verify Nonce
        if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'cep_export_csv_nonce')) {
            wp_die(esc_html__('Security check failed. Link may have expired.', 'core-events-pro'));
        }

        // Security: Unslash and cast to integer
        $event_id = isset($_GET['event_id']) ? absint(wp_unslash($_GET['event_id'])) : 0;

        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT name, email, phone, status, check_in, created_at FROM {$table} WHERE event_id = %d", $event_id),
            ARRAY_A
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=event_' . $event_id . '_attendees.csv');

        $output = fopen('php://output', 'w');

        // i18n: Translate CSV Header row
        fputcsv($output, [
            __('Name', 'core-events-pro'),
            __('Email', 'core-events-pro'),
            __('Phone', 'core-events-pro'),
            __('Status', 'core-events-pro'),
            __('Attended', 'core-events-pro'),
            __('Registration Date', 'core-events-pro')
        ]);

        foreach ($results as $row) {
            // i18n: Translate boolean values
            $row['check_in'] = $row['check_in'] ? __('Yes', 'core-events-pro') : __('No', 'core-events-pro');
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
