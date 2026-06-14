<?php

/**
 * Attendees Table Class.
 * 
 * Extends the core WP_List_Table to display, sort, filter, and manage event attendees.
 * Includes Auto-Promotion logic from Waitlist when a confirmed seat opens up.
 *
 * @package CoreEventsPro\Admin
 * @since 4.2.0
 */

namespace CoreEventsPro\Admin;

use CoreEventsPro\Helpers\EmailQueue;
use CoreEventsPro\Helpers\QrGenerator;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

// Ensure the core WP_List_Table class is loaded if it doesn't exist.
if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class AttendeesTable
 *
 * Handles the rendering and operations of the attendees data table.
 */
class AttendeesTable extends \WP_List_Table
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'attendee',
            'plural'   => 'attendees',
            'ajax'     => false
        ]);
    }

    /**
     * Define the columns that are going to be used in the table.
     */
    public function get_columns()
    {
        return [
            'cb'         => '<input type="checkbox" />',
            'name'       => esc_html__('Name', 'core-events-pro'),
            'email'      => esc_html__('Email', 'core-events-pro'),
            'phone'      => esc_html__('Phone', 'core-events-pro'),
            'event_id'   => esc_html__('Event', 'core-events-pro'),
            'status'     => esc_html__('Status', 'core-events-pro'),
            'check_in'   => esc_html__('Attended?', 'core-events-pro'),
            'created_at' => esc_html__('Registration Date', 'core-events-pro')
        ];
    }

    /**
     * Define the sortable columns.
     */
    protected function get_sortable_columns()
    {
        return [
            'name'       => ['name', false],
            'email'      => ['email', false],
            'event_id'   => ['event_id', false],
            'status'     => ['status', false],
            'created_at' => ['created_at', true]
        ];
    }

    /**
     * Render the default column data.
     */
    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'name':
                $delete_nonce   = wp_create_nonce('cep_delete_attendee');
                $current_filter = isset($_GET['event_filter']) ? '&event_filter=' . absint(wp_unslash($_GET['event_filter'])) : '';
                $page_slug      = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : '';
                $confirm_js     = esc_js(__('Are you sure you want to delete this attendee? If a seat opens, a waitlisted user may be promoted automatically.', 'core-events-pro'));
                $delete_text    = esc_html__('Delete', 'core-events-pro');

                $actions = [
                    'delete' => sprintf(
                        '<a href="?post_type=main_event&page=%s&action=%s&attendee=%s&_wpnonce=%s%s" onclick="return confirm(\'%s\');" style="color:red;">%s</a>',
                        esc_attr($page_slug),
                        'delete',
                        absint($item['id']),
                        $delete_nonce,
                        $current_filter,
                        $confirm_js,
                        $delete_text
                    )
                ];
                return sprintf('<strong>%1$s</strong> %2$s', esc_html($item['name']), $this->row_actions($actions));

            case 'email':
                return sprintf('<a href="mailto:%1$s">%1$s</a>', esc_attr($item['email']));

            case 'phone':
                return ! empty($item['phone']) ? esc_html($item['phone']) : '-';

            case 'event_id':
                $title     = get_the_title($item['event_id']);
                $edit_link = get_edit_post_link($item['event_id']);
                $unknown   = esc_html__('Unknown Event', 'core-events-pro');
                return $title ? sprintf('<a href="%s">%s</a>', esc_url($edit_link), esc_html($title)) : $unknown;

            case 'status':
                $color = ($item['status'] === 'waitlist') ? '#d97706' : '#059669';
                return sprintf('<span style="color:%s; font-weight:bold; text-transform:uppercase;">%s</span>', esc_attr($color), esc_html($item['status']));

            case 'check_in':
                $btn_text  = $item['check_in'] ? __('✅ Yes', 'core-events-pro') : __('Mark Attended', 'core-events-pro');
                $btn_class = $item['check_in'] ? 'button' : 'button button-primary';
                return sprintf(
                    '<button type="button" class="%s cep-checkin-btn" data-id="%d" data-val="%d">%s</button>',
                    esc_attr($btn_class),
                    absint($item['id']),
                    $item['check_in'] ? 0 : 1,
                    esc_html($btn_text)
                );

            case 'created_at':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['created_at']));

            default:
                return esc_html(print_r($item, true));
        }
    }

    /**
     * Render the checkbox column.
     */
    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="attendee[]" value="%s" />', absint($item['id']));
    }

    /**
     * Define the bulk actions.
     */
    public function get_bulk_actions()
    {
        return [
            'mark_attended'   => esc_html__('Mark as Attended', 'core-events-pro'),
            'mark_unattended' => esc_html__('Mark as Unattended', 'core-events-pro'),
            'bulk_delete'     => esc_html__('Delete', 'core-events-pro')
        ];
    }

    /**
     * ✅ NEW: Automatically promote waitlist users if a seat becomes available.
     *
     * @param int $event_id
     */
    private function auto_promote_waitlist($event_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        $capacity = (int) get_post_meta($event_id, '_cep_capacity', true);
        if ($capacity <= 0) return; // Unlimited capacity, no waitlist needed

        // Count currently confirmed users
        $confirmed_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = 'confirmed'", $event_id));

        // If we have available seats, let's promote waitlisted users!
        if ($confirmed_count < $capacity) {
            $seats_available = $capacity - $confirmed_count;

            // Get the oldest waitlisted users for this event
            $waitlisted_users = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE event_id = %d AND status = 'waitlist' ORDER BY created_at ASC LIMIT %d", $event_id, $seats_available));

            if ($waitlisted_users) {
                foreach ($waitlisted_users as $user) {
                    $qr_token = wp_generate_password(32, false);

                    // Update user to confirmed
                    $wpdb->update(
                        $table,
                        ['status' => 'confirmed', 'qr_token' => $qr_token],
                        ['id' => $user->id]
                    );

                    // Send the "Good News" Email
                    $this->send_promotion_email($user->email, $user->name, $event_id, $qr_token);
                }
            }
        }
    }

    /**
     * Helper to send promotion email to users upgraded from waitlist.
     */
    private function send_promotion_email($to_email, $name, $event_id, $qr_token)
    {
        $event_title    = get_the_title($event_id);
        $start_date     = get_post_meta($event_id, '_cep_start', true);
        $date_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start_date));

        $subject_template = get_option('cep_email_confirm_sub', __('Registration Confirmed: {event_name}', 'core-events-pro'));

        // Custom message for promoted users
        $body_template  = __("Great News {name}!\n\nA seat has opened up and your registration for {event_name} is now {status}.\nDate: {event_date}\n\nBest Regards,", 'core-events-pro');

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
     * Process bulk and individual actions.
     */
    public function process_bulk_action()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        $affected_events = []; // Track which events had deletions to trigger auto-promote

        // 1. Handle Single Deletion
        if ('delete' === $this->current_action() && isset($_GET['attendee'])) {
            if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'cep_delete_attendee')) {
                wp_die(esc_html__('Security Check Failed', 'core-events-pro'));
            }

            $attendee_id = absint(wp_unslash($_GET['attendee']));

            // Get event ID before deleting
            $event_id = $wpdb->get_var($wpdb->prepare("SELECT event_id FROM {$table} WHERE id = %d", $attendee_id));
            if ($event_id) $affected_events[$event_id] = true;

            $wpdb->delete($table, ['id' => $attendee_id]);
        }

        // 2. Handle Bulk Actions
        $action = $this->current_action();
        if ($action && isset($_POST['attendee']) && is_array($_POST['attendee'])) {
            if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bulk-' . $this->_args['plural'])) {
                wp_die(esc_html__('Security Check Failed', 'core-events-pro'));
            }

            $ids = array_map('absint', wp_unslash($_POST['attendee']));

            if ($action === 'bulk_delete') {
                foreach ($ids as $id) {
                    $event_id = $wpdb->get_var($wpdb->prepare("SELECT event_id FROM {$table} WHERE id = %d", $id));
                    if ($event_id) $affected_events[$event_id] = true;

                    $wpdb->delete($table, ['id' => $id]);
                }
            } elseif ($action === 'mark_attended') {
                foreach ($ids as $id) {
                    $wpdb->update($table, ['check_in' => 1], ['id' => $id]);
                }
            } elseif ($action === 'mark_unattended') {
                foreach ($ids as $id) {
                    $wpdb->update($table, ['check_in' => 0], ['id' => $id]);
                }
            }
        }

        // 3. ✅ TRIGGER AUTO-PROMOTE FOR AFFECTED EVENTS
        if (! empty($affected_events)) {
            foreach (array_keys($affected_events) as $e_id) {
                $this->auto_promote_waitlist($e_id);
            }
        }
    }

    /**
     * Prepare the items for the table.
     */
    public function prepare_items()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        $this->process_bulk_action();

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby_raw = isset($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'created_at';
        $order_raw   = isset($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'DESC';

        $valid_columns = array_keys($this->get_sortable_columns());
        $orderby       = in_array($orderby_raw, $valid_columns, true) ? $orderby_raw : 'created_at';
        $order         = (strtoupper($order_raw) === 'ASC') ? 'ASC' : 'DESC';

        $where = "WHERE 1=1";
        if (isset($_GET['event_filter']) && $_GET['event_filter'] !== '') {
            $event_id = absint(wp_unslash($_GET['event_filter']));
            $where .= $wpdb->prepare(" AND event_id = %d", $event_id);
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");

        $sql = "SELECT * FROM $table $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $this->items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset), ARRAY_A);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}
