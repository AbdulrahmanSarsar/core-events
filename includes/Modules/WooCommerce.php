<?php

/**
 * WooCommerce Integration Module.
 *
 * Acts as the bridge between Core Events Pro and WooCommerce.
 * Handles the creation of event tickets as products, and processes successful
 * orders to generate QR codes and attendee records automatically.
 *
 * @package CoreEventsPro\Modules
 * @since 4.1.0
 */

namespace CoreEventsPro\Modules;

use CoreEventsPro\Helpers\EmailQueue;
use CoreEventsPro\Helpers\QrGenerator;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WooCommerce
 *
 * Manages ticket synchronization and post-checkout ticket generation.
 */
class WooCommerce
{
    /**
     * Constructor.
     *
     * Initializes hooks if WooCommerce is active.
     */
    public function __construct()
    {
        // Only run integration hooks if WooCommerce is installed and active.
        if ($this->is_woocommerce_active()) {
            // Hook into successful payments and completed orders
            add_action('woocommerce_payment_complete', [$this, 'process_event_tickets']);
            add_action('woocommerce_order_status_completed', [$this, 'process_event_tickets']);
        }
    }

    /**
     * Check if WooCommerce plugin is active.
     *
     * @return bool True if WooCommerce is active, false otherwise.
     */
    public function is_woocommerce_active()
    {
        return class_exists('WooCommerce');
    }

    /**
     * Synchronize an Event Ticket with a WooCommerce Product.
     *
     * @param int    $event_id    The ID of the main or sub event.
     * @param array  $ticket_data Array containing ticket details.
     * @param int    $product_id  Existing WC Product ID to update (Optional).
     * @return int|\WP_Error The WooCommerce Product ID.
     */
    public function sync_ticket_to_product($event_id, $ticket_data, $product_id = 0)
    {
        if (! $this->is_woocommerce_active()) {
            return new \WP_Error('wc_missing', __('WooCommerce is not active.', 'core-events-pro'));
        }

        $event_id     = absint($event_id);
        $product_id   = absint($product_id);

        // ✅ BUG FIX: Remove recursive string append issue.
        $ticket_name  = isset($ticket_data['name']) ? sanitize_text_field($ticket_data['name']) : __('Standard Ticket', 'core-events-pro');
        $ticket_price = isset($ticket_data['price']) ? sanitize_text_field($ticket_data['price']) : '0';
        $capacity     = isset($ticket_data['capacity']) ? absint($ticket_data['capacity']) : 0;
        $event_title  = get_the_title($event_id);

        // This ensures the title is always clean (e.g. "VIP - Event Name") without repeating.
        $product_args = [
            'post_status'  => 'publish',
            'post_type'    => 'product',
            'post_content' => sprintf(esc_html__('Ticket for event: %s', 'core-events-pro'), $event_title),
        ];

        // ✅ BUG FIX: Prevent infinite loops by temporarily unhooking our save_post action
        remove_action('save_post', ['\CoreEventsPro\Admin\MetaBoxes', 'save_data']);

        $is_updating = false;

        if ($product_id > 0 && get_post_type($product_id) === 'product') {
            $product_args['ID'] = $product_id;
            // Only update the title if we need to, to avoid compounding the name.
            $product_args['post_title'] = sprintf(esc_html__('%1$s - %2$s', 'core-events-pro'), $ticket_name, $event_title);
            $new_product_id = wp_update_post($product_args);
            $is_updating = true;
        } else {
            $product_args['post_title'] = sprintf(esc_html__('%1$s - %2$s', 'core-events-pro'), $ticket_name, $event_title);
            $new_product_id = wp_insert_post($product_args);
        }

        if (! is_wp_error($new_product_id) && $new_product_id > 0) {
            // ✅ BUG FIX: Ensure price is saved correctly for WooCommerce structure
            update_post_meta($new_product_id, '_regular_price', $ticket_price);
            update_post_meta($new_product_id, '_price', $ticket_price);

            // Standard Ticket attributes
            update_post_meta($new_product_id, '_virtual', 'yes');
            update_post_meta($new_product_id, '_downloadable', 'no');
            update_post_meta($new_product_id, '_sold_individually', 'no');
            update_post_meta($new_product_id, '_visibility', 'hidden'); // Hide from shop catalog

            // ✅ FIX: Only overwrite stock if it is a NEW product to preserve sales count
            if (! $is_updating) {
                if ($capacity > 0) {
                    update_post_meta($new_product_id, '_manage_stock', 'yes');
                    update_post_meta($new_product_id, '_stock', $capacity);
                    update_post_meta($new_product_id, '_stock_status', 'instock');
                    update_post_meta($new_product_id, '_backorders', 'no');
                } else {
                    update_post_meta($new_product_id, '_manage_stock', 'no');
                    delete_post_meta($new_product_id, '_stock');
                    update_post_meta($new_product_id, '_stock_status', 'instock');
                }
            } else {
                if ($capacity == 0) {
                    update_post_meta($new_product_id, '_manage_stock', 'no');
                    delete_post_meta($new_product_id, '_stock');
                    update_post_meta($new_product_id, '_stock_status', 'instock');
                }
            }

            // Link product to our event
            update_post_meta($new_product_id, '_cep_event_id', $event_id);
        }

        // Re-hook the save_post action just in case other plugins need it later in the cycle
        add_action('save_post', ['\CoreEventsPro\Admin\MetaBoxes', 'save_data']);

        return $new_product_id;
    }

    /**
     * Process successful WooCommerce orders to generate Event Tickets.
     *
     * Runs automatically when an order is paid or marked as completed.
     * Generates QR codes and adds the buyer to the attendees database.
     *
     * @param int $order_id The WooCommerce Order ID.
     * @return void
     */
    public function process_event_tickets($order_id)
    {
        // Prevent duplicate processing for the same order.
        if (get_post_meta($order_id, '_cep_tickets_generated', true)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        // Get buyer details
        $buyer_name  = sanitize_text_field($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $buyer_email = sanitize_email($order->get_billing_email());
        $buyer_phone = sanitize_text_field($order->get_billing_phone());

        $tickets_created = 0;
        $generated_qrs   = []; // To store QR tokens for the email

        // Loop through all items in the order
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = absint($item->get_product_id());
            $event_id   = absint(get_post_meta($product_id, '_cep_event_id', true));

            // If this product is linked to an event
            if ($event_id > 0) {
                $quantity = absint($item->get_quantity());

                // Generate an attendee record for EACH ticket purchased (e.g., Qty 3 = 3 Tickets)
                for ($i = 0; $i < $quantity; $i++) {
                    $qr_token = wp_generate_password(32, false);

                    $inserted = $wpdb->insert($table, [
                        'event_id'   => $event_id,
                        'name'       => $buyer_name,
                        'email'      => $buyer_email,
                        'phone'      => $buyer_phone,
                        'status'     => 'confirmed', // Paid tickets are always confirmed
                        'qr_token'   => $qr_token,
                        'created_at' => current_time('mysql')
                    ]);

                    if ($inserted) {
                        $generated_qrs[] = [
                            'event_id' => $event_id,
                            'token'    => $qr_token
                        ];
                        $tickets_created++;
                    }
                }
            }
        }

        // If tickets were successfully created, send the ticket email and mark order as processed.
        if ($tickets_created > 0) {
            update_post_meta($order_id, '_cep_tickets_generated', 'yes');
            $this->send_wc_ticket_email($buyer_email, $buyer_name, $generated_qrs, $order_id);
        }
    }

    /**
     * Send the consolidated Ticket Email to the WooCommerce buyer.
     *
     * @param string $to_email      The buyer's email.
     * @param string $name          The buyer's name.
     * @param array  $generated_qrs Array containing event IDs and their QR tokens.
     * @param int    $order_id      The WooCommerce Order ID.
     * @return void
     */
    private function send_wc_ticket_email($to_email, $name, $generated_qrs, $order_id)
    {
        // i18n: Email Subject
        $subject = sprintf(esc_html__('Your Event Tickets - Order #%d', 'core-events-pro'), $order_id);

        // i18n: Email Body Header
        $body  = sprintf(esc_html__("Hello %s,", 'core-events-pro'), $name) . "<br><br>";
        $body .= esc_html__("Thank you for your purchase! Here are your event tickets:", 'core-events-pro') . "<br><br>";

        // Group tickets by Event ID to display nicely
        $grouped_tickets = [];
        foreach ($generated_qrs as $qr) {
            $grouped_tickets[$qr['event_id']][] = $qr['token'];
        }

        foreach ($grouped_tickets as $ev_id => $tokens) {
            $event_title    = get_the_title($ev_id);
            $start_date     = get_post_meta($ev_id, '_cep_start', true);
            $date_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start_date));

            $body .= "<div style='border:1px solid #ddd; padding:15px; margin-bottom:20px; border-radius:8px;'>";
            $body .= "<h3 style='margin-top:0; color:#2563eb;'>" . esc_html($event_title) . "</h3>";
            $body .= "<p><strong>" . esc_html__('Date:', 'core-events-pro') . "</strong> " . esc_html($date_formatted) . "</p>";

            $ticket_count = 1;
            foreach ($tokens as $token) {
                $scan_url     = QrGenerator::get_scan_url($token);
                $qr_image_src = QrGenerator::get_data_uri($scan_url);

                $body .= "<hr style='border:0; border-top:1px dashed #eee; margin:15px 0;'>";
                $body .= "<h4>" . sprintf(esc_html__('Ticket #%d', 'core-events-pro'), $ticket_count) . "</h4>";
                $body .= "<p>" . esc_html__('Please present this QR code at the entrance:', 'core-events-pro') . "</p>";

                if (! empty($qr_image_src)) {
                    $body .= "<img src='" . esc_attr($qr_image_src) . "' alt='" . esc_attr__('QR Ticket', 'core-events-pro') . "' width='200' height='200' style='margin-bottom:10px;'><br>";
                }

                $body .= "<a href='" . esc_url($scan_url) . "'>" . esc_html__('Or click here to view ticket link', 'core-events-pro') . "</a>";
                $ticket_count++;
            }
            $body .= "</div>";
        }

        $body .= "<p>" . esc_html__('Best Regards,', 'core-events-pro') . "<br>" . get_bloginfo('name') . "</p>";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        EmailQueue::queue($to_email, $subject, $body, $headers);
    }
}
