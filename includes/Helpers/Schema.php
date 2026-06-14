<?php

/**
 * Schema.org JSON-LD helper.
 *
 * Emits an Event schema graph in the <head> of every single main_event
 * and sub_event page. This is what makes Google show the page in the
 * "Events" rich result (date pill + venue + price + a direct ticket
 * link) and also what feeds Bing, Yandex, and most aggregator crawlers.
 *
 * The schema follows the official Google guidelines:
 *   https://developers.google.com/search/docs/appearance/structured-data/event
 *
 * Filters that let site owners customise the output without touching
 * the plugin code:
 *
 *   cep_event_schema_data            (array) - the full JSON-LD array
 *   cep_event_schema_organizer       (array) - the organizer block
 *   cep_event_schema_attendance_mode (string) - Offline / Online / Mixed
 *
 * @package CoreEventsPro\Helpers
 * @since   1.1.0
 */

namespace CoreEventsPro\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

class Schema
{
    /**
     * Wire the schema printer into wp_head.
     *
     * Late priority (20) so other plugins/themes have already added
     * their own schema; this one stands alone in its own script tag and
     * does not interfere.
     */
    public function __construct()
    {
        add_action('wp_head', [$this, 'maybe_print_schema'], 20);
    }

    /**
     * Print Event schema if we are on a single main_event or sub_event.
     *
     * @return void
     */
    public function maybe_print_schema()
    {
        if (! is_singular(['main_event', 'sub_event'])) {
            return;
        }

        $post_id = get_queried_object_id();
        if (! $post_id) {
            return;
        }

        $data = $this->build_event_schema($post_id);
        if (empty($data)) {
            return;
        }

        // JSON_UNESCAPED_SLASHES keeps URLs readable in view-source.
        // JSON_UNESCAPED_UNICODE preserves Arabic / accented characters.
        $json = wp_json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if (! $json) {
            return;
        }

        // The <script> tag itself is plain HTML; the dynamic part is the
        // already-encoded JSON, so no further escaping is needed.
        echo "\n<!-- Core Events Pro: Event Schema -->\n";
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /**
     * Assemble the JSON-LD payload for a single event post.
     *
     * Returns an empty array when the event has no start date (in which
     * case Google would reject the schema anyway), so that callers can
     * skip rendering altogether.
     *
     * @param int $post_id
     * @return array<string, mixed>
     */
    public function build_event_schema($post_id)
    {
        $post_id = absint($post_id);
        $post    = get_post($post_id);
        if (! $post) {
            return [];
        }

        $start = (string) get_post_meta($post_id, '_cep_start', true);
        if ('' === $start) {
            return [];
        }

        $end       = (string) get_post_meta($post_id, '_cep_end', true);
        $status    = (string) get_post_meta($post_id, '_cep_status', true);
        $location  = (string) get_post_meta($post_id, '_cep_location', true);
        $overview  = (string) get_post_meta($post_id, '_cep_overview', true);

        $description = $overview !== ''
            ? $overview
            : wp_strip_all_tags(get_the_excerpt($post_id));

        $data = [
            '@context'          => 'https://schema.org',
            '@type'             => 'Event',
            'name'              => $post->post_title,
            'startDate'         => $this->format_iso8601($start),
            'eventStatus'       => $this->map_event_status($status),
            'eventAttendanceMode' => $this->map_attendance_mode($location),
            'description'       => $description,
            'url'               => get_permalink($post_id),
        ];

        if ('' !== $end) {
            $data['endDate'] = $this->format_iso8601($end);
        }

        $images = $this->collect_images($post_id);
        if (! empty($images)) {
            $data['image'] = $images;
        }

        $location_block = $this->build_location($location);
        if (! empty($location_block)) {
            $data['location'] = $location_block;
        }

        $offers = $this->build_offers($post_id);
        if (! empty($offers)) {
            $data['offers'] = $offers;
        }

        $data['organizer'] = apply_filters(
            'cep_event_schema_organizer',
            [
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url('/'),
            ],
            $post_id
        );

        return apply_filters('cep_event_schema_data', $data, $post_id);
    }

    // -------------------------------------------------------------------
    // Builders
    // -------------------------------------------------------------------

    /**
     * Map our internal status values to schema.org event statuses.
     *
     * Note: schema.org has no "finished" - past events keep
     * EventScheduled. Only cancellations get a special status.
     *
     * @param string $status
     * @return string Fully-qualified schema URL.
     */
    private function map_event_status($status)
    {
        switch ($status) {
            case 'cancelled':
                return 'https://schema.org/EventCancelled';
            case 'postponed':
                return 'https://schema.org/EventPostponed';
            case 'rescheduled':
                return 'https://schema.org/EventRescheduled';
            case 'upcoming':
            case 'ongoing':
            case 'finished':
            default:
                return 'https://schema.org/EventScheduled';
        }
    }

    /**
     * Pick an attendance mode based on the location string.
     *
     * Heuristic: a location that looks like a URL is treated as a
     * virtual event. Anything else (including empty) defaults to
     * offline. Site owners can override via filter.
     *
     * @param string $location
     * @return string Fully-qualified schema URL.
     */
    private function map_attendance_mode($location)
    {
        $location = trim((string) $location);
        $is_url   = ('' !== $location) && (false !== filter_var($location, FILTER_VALIDATE_URL));

        $mode = $is_url
            ? 'https://schema.org/OnlineEventAttendanceMode'
            : 'https://schema.org/OfflineEventAttendanceMode';

        return (string) apply_filters('cep_event_schema_attendance_mode', $mode, $location);
    }

    /**
     * Build the `location` portion of the schema.
     *
     * - If the field looks like a URL: VirtualLocation.
     * - If non-empty plain text: Place + PostalAddress (streetAddress only,
     *   since we don't ask for city/country in the UI yet).
     * - If empty: omit the block - Google still accepts events without a
     *   location for online-style events.
     *
     * @param string $location
     * @return array<string, mixed>
     */
    private function build_location($location)
    {
        $location = trim((string) $location);

        if ('' === $location) {
            return [];
        }

        if (false !== filter_var($location, FILTER_VALIDATE_URL)) {
            return [
                '@type' => 'VirtualLocation',
                'url'   => $location,
            ];
        }

        return [
            '@type'   => 'Place',
            'name'    => $location,
            'address' => [
                '@type'         => 'PostalAddress',
                'streetAddress' => $location,
            ],
        ];
    }

    /**
     * Build the `image` array.
     *
     * Google prefers multiple aspect ratios, so we surface every distinct
     * URL we have access to: custom banner, featured image, and the
     * first item from the gallery. Duplicates are removed but order is
     * preserved (banner first - it is usually the highest quality).
     *
     * @param int $post_id
     * @return array<int, string>
     */
    private function collect_images($post_id)
    {
        $images = [];

        $banner = (string) get_post_meta($post_id, '_cep_custom_banner', true);
        if ('' !== $banner && false !== filter_var($banner, FILTER_VALIDATE_URL)) {
            $images[] = $banner;
        }

        $thumb = get_the_post_thumbnail_url($post_id, 'full');
        if ($thumb) {
            $images[] = $thumb;
        }

        $gallery_csv = (string) get_post_meta($post_id, '_cep_gallery_ids', true);
        if ('' !== $gallery_csv) {
            $first_id = (int) trim(explode(',', $gallery_csv)[0]);
            if ($first_id > 0) {
                $url = wp_get_attachment_image_url($first_id, 'large');
                if ($url) {
                    $images[] = $url;
                }
            }
        }

        return array_values(array_unique(array_filter($images)));
    }

    /**
     * Build the `offers` array.
     *
     * Behaviour:
     *   - Registration disabled        -> no offers (omit the block).
     *   - Free RSVP                    -> a single Offer, price 0,
     *                                     availability mirrors capacity.
     *   - Paid tickets (WooCommerce)   -> one Offer per ticket tier with
     *                                     real pricing and stock data.
     *
     * @param int $post_id
     * @return array<int, array<string, mixed>>
     */
    private function build_offers($post_id)
    {
        $enable_rsvp = (string) get_post_meta($post_id, '_cep_enable_rsvp', true);
        if ('1' !== $enable_rsvp) {
            return [];
        }

        $rsvp_type = (string) get_post_meta($post_id, '_cep_rsvp_type', true);
        $valid_from = $this->format_iso8601((string) get_the_date('c', $post_id));
        $event_url  = get_permalink($post_id);

        // Paid tickets via WooCommerce.
        if ('paid' === $rsvp_type && class_exists('WooCommerce')) {
            $tickets = get_post_meta($post_id, '_cep_tickets', true);
            if (! is_array($tickets) || empty($tickets)) {
                return [];
            }

            $currency = function_exists('get_woocommerce_currency')
                ? get_woocommerce_currency()
                : 'USD';

            $offers = [];
            foreach ($tickets as $ticket) {
                $name  = isset($ticket['name'])  ? (string) $ticket['name']  : '';
                $price = isset($ticket['price']) ? (string) $ticket['price'] : '0';

                $availability = $this->ticket_availability($ticket);

                $offers[] = [
                    '@type'         => 'Offer',
                    'name'          => $name,
                    'price'         => $price,
                    'priceCurrency' => $currency,
                    'availability'  => $availability,
                    'validFrom'     => $valid_from,
                    'url'           => $event_url,
                ];
            }

            return $offers;
        }

        // Free RSVP (single offer).
        return [[
            '@type'         => 'Offer',
            'name'          => __('Free Registration', 'core-events-pro'),
            'price'         => '0',
            'priceCurrency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'availability'  => $this->free_rsvp_availability($post_id),
            'validFrom'     => $valid_from,
            'url'           => $event_url,
        ]];
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Convert a stored datetime-local value (e.g. "2026-05-01T18:30")
     * into a fully-qualified ISO 8601 string with the site timezone.
     *
     * @param string $datetime
     * @return string
     */
    private function format_iso8601($datetime)
    {
        $datetime = trim((string) $datetime);
        if ('' === $datetime) {
            return '';
        }

        $ts = strtotime($datetime);
        if (false === $ts) {
            return $datetime;
        }

        // Use the WP-configured timezone so the offset is correct.
        $tz = wp_timezone();

        try {
            $dt = new \DateTimeImmutable('@' . $ts);
            $dt = $dt->setTimezone($tz);
            return $dt->format('c');
        } catch (\Exception $e) {
            return gmdate('c', $ts);
        }
    }

    /**
     * Decide availability for a paid ticket tier.
     *
     * If a per-tier WooCommerce product exists we trust its stock state;
     * otherwise we fall back to the tier's `capacity` field.
     *
     * @param array $ticket
     * @return string Schema availability URL.
     */
    private function ticket_availability(array $ticket)
    {
        $product_id = isset($ticket['product_id']) ? absint($ticket['product_id']) : 0;

        if ($product_id > 0 && function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product) {
                return $product->is_in_stock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/SoldOut';
            }
        }

        $capacity = isset($ticket['capacity']) ? (int) $ticket['capacity'] : 0;
        if ($capacity > 0) {
            return 'https://schema.org/InStock';
        }

        // Capacity 0 in our admin = unlimited.
        return 'https://schema.org/InStock';
    }

    /**
     * Availability for the single Offer used by free RSVP events.
     *
     * Mirrors the same logic as the public registration form: when
     * confirmed registrations meet capacity we report SoldOut, even
     * though the site itself will still offer a waitlist.
     *
     * @param int $post_id
     * @return string Schema availability URL.
     */
    private function free_rsvp_availability($post_id)
    {
        $capacity = (int) get_post_meta($post_id, '_cep_capacity', true);
        if ($capacity <= 0) {
            return 'https://schema.org/InStock';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cep_attendees';

        // Defensive: schema rendering must never fatal a frontend page.
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists !== $table) {
            return 'https://schema.org/InStock';
        }

        $confirmed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = 'confirmed'",
            $post_id
        ));

        return ($confirmed >= $capacity)
            ? 'https://schema.org/SoldOut'
            : 'https://schema.org/InStock';
    }
}
