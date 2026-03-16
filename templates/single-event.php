<?php

/**
 * Template part for Single Main Event.
 * 
 * Full Width Modern Layout V7 (Hybrid Edition: Paid Tickets & Free RSVP).
 * Displays hero section, countdown, Ticket Selection or RSVP form, sub-events, gallery, and video.
 *
 * @package CoreEventsPro\Templates
 * @since 4.2.0
 */

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

use CoreEventsPro\Helpers\Utils;

get_header();

$id             = get_the_ID();
$start          = get_post_meta($id, '_cep_start', true);
$end            = get_post_meta($id, '_cep_end', true);
$status         = get_post_meta($id, '_cep_status', true);
$video          = get_post_meta($id, '_cep_video_url', true);
$location       = get_post_meta($id, '_cep_location', true);
$gallery_ids    = get_post_meta($id, '_cep_gallery_ids', true);
$overview       = get_post_meta($id, '_cep_overview', true);
$custom_banner  = get_post_meta($id, '_cep_custom_banner', true);

// ✅ NEW LOGIC: Read the new registration settings
$enable_rsvp    = get_post_meta($id, '_cep_enable_rsvp', true); // '1' or '0'
$rsvp_type      = get_post_meta($id, '_cep_rsvp_type', true);   // 'free' or 'paid'

$tickets        = get_post_meta($id, '_cep_tickets', true);
$wc_active      = class_exists('WooCommerce');
// Only consider it has paid tickets if WC is active AND the admin selected 'paid'
$has_paid_tix   = (is_array($tickets) && ! empty($tickets) && $wc_active && $rsvp_type === 'paid');

$bg_image       = $custom_banner ?: get_the_post_thumbnail_url($id, 'full');
$show_time      = get_option('cep_enable_time', 1);
$show_location  = get_option('cep_enable_location', 1);
$show_countdown = get_option('cep_enable_countdown', 1);
$show_ics       = get_option('cep_enable_ics', 1);

// i18n: Provide translatable default options.
$label_video    = get_option('cep_label_video', __('Event Video', 'core-events-pro'));
$label_gallery  = get_option('cep_label_gallery', __('Event Gallery', 'core-events-pro'));
$label_schedule = get_option('cep_label_schedule', __('Event Schedule', 'core-events-pro'));
$txt_waitlist   = get_option('cep_text_waitlist', __('Join Waitlist', 'core-events-pro'));

$date_fmt       = get_option('date_format');
$time_fmt       = get_option('time_format');

// Generate ICS Data String safely.
$ics_data = "#";
if ($show_ics && $start) {
    $title_ics   = rawurlencode(get_the_title());
    $desc_ics    = rawurlencode(wp_trim_words(strip_tags(get_the_content()), 20));
    $loc_ics     = rawurlencode($location);
    $dtstart     = gmdate('Ymd\THis', strtotime($start));
    $dtend       = $end ? gmdate('Ymd\THis', strtotime($end)) : gmdate('Ymd\THis', strtotime($start . ' +1 hour'));

    $ics_data    = "data:text/calendar;charset=utf8,BEGIN:VCALENDAR%0AVERSION:2.0%0ABEGIN:VEVENT%0ADTSTART:{$dtstart}%0ADTEND:{$dtend}%0ASUMMARY:{$title_ics}%0ADESCRIPTION:{$desc_ics}%0ALOCATION:{$loc_ics}%0AEND:VEVENT%0AEND:VCALENDAR";
}

// Fetch Sub-Events (Sessions).
$subs_args = [
    'post_type'      => 'sub_event',
    'posts_per_page' => -1,
    'meta_query'     => [['key' => '_cep_parent_id', 'value' => $id, 'compare' => '=']],
    'meta_key'       => '_cep_start',
    'orderby'        => 'meta_value',
    'order'          => 'ASC'
];
$subs_query = new \WP_Query($subs_args);
$has_subs   = $subs_query->have_posts();

// i18n: Map status to translatable strings.
$status_map = [
    'upcoming'  => __('Upcoming', 'core-events-pro'),
    'ongoing'   => __('Ongoing', 'core-events-pro'),
    'finished'  => __('Finished', 'core-events-pro'),
    'cancelled' => __('Cancelled', 'core-events-pro'),
];
$display_status = isset($status_map[$status]) ? $status_map[$status] : ucfirst($status);
?>
<div class="cep-page-wrapper">

    <!-- Hero Section -->
    <div class="cep-modern-hero">
        <?php if ($bg_image) : ?>
            <div class="cep-hero-bg" style="background-image: url('<?php echo esc_url($bg_image); ?>');"></div>
            <div class="cep-hero-overlay"></div>
        <?php endif; ?>

        <div class="cep-hero-container">
            <span class="cep-badge-lg <?php echo esc_attr($status); ?>"><?php echo esc_html($display_status); ?></span>
            <h1 class="cep-hero-title"><?php the_title(); ?></h1>
            <div class="cep-hero-meta">
                <?php if (! empty($start)) : ?>
                    <span>📅 <?php echo esc_html(date_i18n($date_fmt, strtotime($start))); ?></span>
                    <?php if ($show_time) : ?>
                        <span style="font-size:0.8em; opacity:0.9;">(<?php echo esc_html(date_i18n($time_fmt, strtotime($start))); ?>)</span>
                    <?php endif; ?>
                    <?php if (! empty($end)) : ?>
                        <span>&mdash; <?php echo esc_html(date_i18n($date_fmt, strtotime($end))); ?></span>
                    <?php endif; ?>
                <?php else : ?>
                    <span>📅 <?php esc_html_e('Date: TBA', 'core-events-pro'); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($show_location && $location) : ?>
                <div class="cep-hero-loc">
                    <a href="https://maps.google.com/?q=<?php echo urlencode($location); ?>" target="_blank" style="color:#e2e8f0; text-decoration:none;">
                        <span class="dashicons dashicons-location"></span> <?php echo esc_html($location); ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($show_ics && $start) : ?>
                <div style="margin-top:20px;">
                    <a href="<?php echo esc_url($ics_data); ?>" download="event.ics" style="display:inline-flex; align-items:center; gap:8px; background:#fff; color:#1e293b; padding:10px 20px; border-radius:50px; text-decoration:none; font-weight:bold; font-size:14px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                        <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Add to Calendar', 'core-events-pro'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="cep-content-container">

        <!-- Countdown Section -->
        <?php if ($show_countdown && $status === 'upcoming' && ! empty($start)) : ?>
            <div class="cep-countdown-box" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 25px; border-radius: 12px; text-align: center; margin-bottom: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #94a3b8; font-size: 16px; text-transform: uppercase;">
                    <?php esc_html_e('Event Starts In', 'core-events-pro'); ?>
                </h3>
                <div id="cep-timer" data-start="<?php echo esc_attr(date('Y-m-d\TH:i:s', strtotime($start))); ?>" style="font-size: 32px; font-weight: 800; color: #38bdf8; font-variant-numeric: tabular-nums;">
                    <?php esc_html_e('Calculating...', 'core-events-pro'); ?>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var timerEl = document.getElementById("cep-timer");
                    if (!timerEl) return;

                    var countDownDate = new Date(timerEl.getAttribute("data-start")).getTime();
                    var txtStarted = "<?php echo esc_js(__('EVENT STARTED!', 'core-events-pro')); ?>";
                    var txtD = "<?php echo esc_js(_x('d ', 'days in countdown', 'core-events-pro')); ?>";
                    var txtH = "<?php echo esc_js(_x('h ', 'hours in countdown', 'core-events-pro')); ?>";
                    var txtM = "<?php echo esc_js(_x('m ', 'minutes in countdown', 'core-events-pro')); ?>";
                    var txtS = "<?php echo esc_js(_x('s ', 'seconds in countdown', 'core-events-pro')); ?>";

                    var x = setInterval(function() {
                        var distance = countDownDate - new Date().getTime();
                        if (distance < 0) {
                            clearInterval(x);
                            timerEl.innerHTML = txtStarted;
                            return;
                        }
                        var d = Math.floor(distance / (1000 * 60 * 60 * 24));
                        var h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        var m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                        var s = Math.floor((distance % (1000 * 60)) / 1000);
                        timerEl.innerHTML = d + txtD + h + txtH + m + txtM + s + txtS;
                    }, 1000);
                });
            </script>
        <?php endif; ?>

        <!-- Overview Section -->
        <?php if ($overview) : ?>
            <div class="cep-overview-box" style="background:#f8fafc; border-left:5px solid #2563eb; padding:20px; border-radius:0 8px 8px 0; margin-bottom:30px;">
                <p class="cep-lead-text" style="font-size:18px; color:#334155; margin:0; line-height:1.6;">
                    <?php echo nl2br(esc_html($overview)); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- ✅ HYBRID REGISTRATION SYSTEM: STRICTLY CHECK ENABLE_RSVP = '1' -->
        <?php if ($enable_rsvp === '1') : ?>
            <div class="cep-section" style="background:#fff; padding:30px; border-radius:12px; border:1px solid #e2e8f0; margin-bottom:40px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
                <h2 class="cep-section-title" style="margin-top:0;">🎟️ <?php esc_html_e('Event Registration', 'core-events-pro'); ?></h2>

                <?php
                $is_closed = ($status === 'finished' || $status === 'cancelled');

                if ($is_closed) :
                ?>
                    <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; font-weight:bold;">
                        <?php printf(esc_html__('Registration closed (Event is %s).', 'core-events-pro'), esc_html($display_status)); ?>
                    </div>

                    <?php else :

                    // ---------------------------------------------------------
                    // MODE 1: WOOCOMMERCE PAID TICKETS (If selected)
                    // ---------------------------------------------------------
                    if ($has_paid_tix) :
                    ?>
                        <p style="margin-bottom:20px; color:#475569;"><?php esc_html_e('Please select your ticket type below:', 'core-events-pro'); ?></p>

                        <div class="cep-tickets-list" style="display:flex; flex-direction:column; gap:15px;">
                            <?php
                            foreach ($tickets as $ticket) :
                                $product_id = absint($ticket['product_id'] ?? 0);
                                if (! $product_id) continue;

                                $product = wc_get_product($product_id);
                                if (! $product || $product->get_status() !== 'publish') continue;

                                $in_stock   = $product->is_in_stock();
                                $price_html = $product->get_price_html();

                                // Direct to checkout
                                $checkout_url = wc_get_checkout_url();
                                $add_to_cart_url = esc_url(add_query_arg('add-to-cart', $product_id, $checkout_url));
                            ?>
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc;">
                                    <div>
                                        <strong style="font-size:1.1rem; color:#1e293b; display:block;"><?php echo esc_html($ticket['name']); ?></strong>
                                        <span style="color:#2563eb; font-weight:bold; font-size:1.2rem;"><?php echo wp_kses_post($price_html); ?></span>
                                    </div>
                                    <div>
                                        <?php if ($in_stock) : ?>
                                            <a href="<?php echo $add_to_cart_url; ?>" style="background:#2563eb; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:bold; display:inline-block; transition:0.2s;">
                                                <?php esc_html_e('Buy Ticket', 'core-events-pro'); ?>
                                            </a>
                                        <?php else : ?>
                                            <span style="background:#fef3c7; color:#92400e; padding:10px 20px; border-radius:6px; font-weight:bold; display:inline-block;">
                                                <?php esc_html_e('Sold Out', 'core-events-pro'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php
                    // ---------------------------------------------------------
                    // MODE 2: FREE RSVP & WAITLIST
                    // ---------------------------------------------------------
                    else :
                        $capacity   = (int) get_post_meta($id, '_cep_capacity', true);
                        $registered = 0;

                        if ($capacity > 0) {
                            global $wpdb;
                            $table = $wpdb->prefix . 'cep_attendees';
                            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
                                // ✅ BUG FIX: Count ONLY confirmed registrations towards capacity.
                                // Waitlisted or Cancelled users do not take up a seat!
                                $registered = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND (status = 'confirmed' OR status = '')", $id));
                            }
                        }

                        $is_full   = ($capacity > 0 && $registered >= $capacity);
                        $btn_text  = $is_full ? esc_html($txt_waitlist) : esc_html__('Confirm Registration', 'core-events-pro');
                        $btn_color = $is_full ? '#f59e0b' : '#2563eb';

                        if ($is_full) : ?>
                            <div style="background:#fffbeb; color:#92400e; padding:15px; border-radius:8px; font-weight:bold; font-size:15px; margin-bottom:15px; border-left:4px solid #f59e0b;">
                                ⚠️ <?php esc_html_e('Event is Fully Booked! You can still join the Waitlist.', 'core-events-pro'); ?>
                            </div>
                        <?php elseif ($capacity > 0) : ?>
                            <p style="color:#059669; font-weight:bold; margin-bottom:15px;">
                                <?php printf(esc_html__('Available Seats: %1$d / %2$d', 'core-events-pro'), max(0, $capacity - $registered), $capacity); ?>
                            </p>
                        <?php endif; ?>

                        <form id="cep-rsvp-form">
                            <input type="hidden" name="event_id" value="<?php echo absint($id); ?>">
                            <input type="hidden" name="action" value="cep_submit_rsvp">
                            <?php wp_nonce_field('cep_rsvp_nonce', 'security'); ?>

                            <?php if ($has_subs) : ?>
                                <div style="margin-bottom:15px;">
                                    <label style="font-weight:bold; display:block; margin-bottom:5px;">
                                        <?php esc_html_e('Select Session (Optional):', 'core-events-pro'); ?>
                                    </label>
                                    <select name="selected_event_id" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; background:#f8fafc;">
                                        <option value="<?php echo absint($id); ?>"><?php esc_html_e('Register for the Main Event', 'core-events-pro'); ?></option>
                                        <?php
                                        while ($subs_query->have_posts()) {
                                            $subs_query->the_post();
                                            echo '<option value="' . esc_attr(get_the_ID()) . '">' . esc_html(get_the_title()) . '</option>';
                                        }
                                        wp_reset_postdata();
                                        ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div style="display:flex; gap:15px; margin-bottom:15px; flex-wrap:wrap;">
                                <input type="text" name="name" placeholder="<?php esc_attr_e('Full Name', 'core-events-pro'); ?>" required style="flex:1; min-width:200px; padding:12px; border:1px solid #cbd5e1; border-radius:6px;">
                                <input type="email" name="email" placeholder="<?php esc_attr_e('Email Address', 'core-events-pro'); ?>" required style="flex:1; min-width:200px; padding:12px; border:1px solid #cbd5e1; border-radius:6px;">
                            </div>

                            <input type="tel" name="phone" placeholder="<?php esc_attr_e('Phone Number (Optional)', 'core-events-pro'); ?>" style="width:100%; margin-bottom:15px; padding:12px; border:1px solid #cbd5e1; border-radius:6px;">

                            <button type="submit" style="background:<?php echo esc_attr($btn_color); ?>; color:#fff; padding:12px 30px; border:none; border-radius:6px; font-weight:bold; cursor:pointer; font-size:16px; transition:0.2s;">
                                <?php echo esc_html($btn_text); ?>
                            </button>
                            <div id="rsvp-msg" style="margin-top:15px; font-weight:bold;"></div>
                        </form>

                        <script>
                            jQuery(document).ready(function($) {
                                $('#cep-rsvp-form').submit(function(e) {
                                    e.preventDefault();
                                    var btn = $(this).find('button');
                                    var originalText = btn.text();
                                    var processingTxt = '<?php echo esc_js(__('Processing...', 'core-events-pro')); ?>';

                                    btn.text(processingTxt).prop('disabled', true);

                                    $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', $(this).serialize(), function(res) {
                                        $('#rsvp-msg').text(res.data.message).css('color', res.success ? '#059669' : '#dc2626');
                                        if (res.success) {
                                            setTimeout(() => location.reload(), 2000);
                                        } else {
                                            btn.text(originalText).prop('disabled', false);
                                        }
                                    });
                                });
                            });
                        </script>
                <?php
                    endif; // End Hybrid Registration Check
                endif; // End $is_closed Check 
                ?>
            </div>
        <?php endif; // End Enable RSVP Check 
        ?>

        <!-- Sub Events Schedule -->
        <?php if ($has_subs) : ?>
            <div class="cep-section">
                <h2 class="cep-section-title"><?php echo esc_html($label_schedule); ?></h2>
                <div class="cep-timeline-wrapper">
                    <?php
                    while ($subs_query->have_posts()) :
                        $subs_query->the_post();
                        $sub_id  = get_the_ID();
                        $s_start = get_post_meta($sub_id, '_cep_start', true);
                        $s_end   = get_post_meta($sub_id, '_cep_end', true);
                    ?>
                        <div class="cep-sub-card" style="display:flex; align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:15px 20px; margin-bottom:15px; transition:0.2s;">
                            <div class="cep-sc-date" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; width:60px; height:60px; display:flex; flex-direction:column; align-items:center; justify-content:center; margin-right:20px; flex-shrink:0;">
                                <span class="d" style="font-size:1.4rem; font-weight:bold; color:#2563eb; line-height:1;">
                                    <?php echo $s_start ? esc_html(date_i18n('d', strtotime($s_start))) : '00'; ?>
                                </span>
                                <span class="m" style="font-size:0.7rem; text-transform:uppercase; font-weight:bold; color:#64748b; margin-top:2px;">
                                    <?php echo $s_start ? esc_html(date_i18n('M', strtotime($s_start))) : esc_html__('TBA', 'core-events-pro'); ?>
                                </span>
                            </div>
                            <div class="cep-sc-details" style="flex-grow:1;">
                                <div class="cep-sc-meta" style="font-size:0.8rem; color:#94a3b8; font-weight:600; margin-bottom:4px; text-transform:uppercase;">
                                    <?php if ($show_time) : ?>
                                        <span style="color:#2563eb; font-weight:bold;">
                                            ⏰ <?php echo esc_html(date_i18n($time_fmt, strtotime($s_start))); ?>
                                            <?php if ($s_end) echo esc_html(_x(' - ', 'Time separator', 'core-events-pro')) . esc_html(date_i18n($time_fmt, strtotime($s_end))); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($s_end && gmdate('Y-m-d', strtotime($s_start)) != gmdate('Y-m-d', strtotime($s_end))) : ?>
                                        <span style="margin-left:8px; color:#e11d48; font-size:0.85em;">
                                            (<?php printf(esc_html__('Ends: %s', 'core-events-pro'), esc_html(date_i18n('M d', strtotime($s_end)))); ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="cep-sc-title" style="margin:0 0 5px 0; font-size:1.15rem;">
                                    <a href="<?php the_permalink(); ?>" style="text-decoration:none; color:#1e293b;"><?php the_title(); ?></a>
                                </h3>
                                <div class="cep-sc-excerpt" style="font-size:0.9rem; color:#64748b;">
                                    <?php echo esc_html(wp_trim_words(get_the_excerpt(), 15)); ?>
                                </div>
                            </div>
                            <div class="cep-sc-action" style="margin-left:15px;">
                                <a href="<?php the_permalink(); ?>" class="cep-btn-arrow" style="width:40px; height:40px; border-radius:50%; background:#f1f5f9; color:#1e293b; display:flex; align-items:center; justify-content:center; text-decoration:none;">
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </a>
                            </div>
                        </div>
                    <?php
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Description Section -->
        <div class="cep-section">
            <div class="cep-description"><?php the_content(); ?></div>
        </div>

        <!-- Gallery Section -->
        <?php if (! empty($gallery_ids)) : ?>
            <div class="cep-section">
                <h2 class="cep-section-title"><?php echo esc_html($label_gallery); ?></h2>
                <div class="cep-gallery-masonry" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:15px;">
                    <?php
                    foreach (explode(',', $gallery_ids) as $img_id) :
                        $full_src = wp_get_attachment_image_url(absint($img_id), 'large');
                        $thumb_src = wp_get_attachment_image_url(absint($img_id), 'medium_large');
                        if ($full_src && $thumb_src) :
                    ?>
                            <div class="cep-gallery-thumb">
                                <a href="<?php echo esc_url($full_src); ?>" target="_blank">
                                    <img src="<?php echo esc_url($thumb_src); ?>" style="width:100%; height:150px; object-fit:cover; border-radius:8px;" alt="<?php esc_attr_e('Event Gallery Image', 'core-events-pro'); ?>">
                                </a>
                            </div>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Video Section -->
        <?php if ($video) : ?>
            <div class="cep-section">
                <h2 class="cep-section-title"><?php echo esc_html($label_video); ?></h2>
                <div class="cep-video-responsive" style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:12px; box-shadow:0 5px 15px rgba(0,0,0,0.1);">
                    <iframe src="<?php echo esc_url($video); ?>" style="position:absolute; top:0; left:0; width:100%; height:100%;" frameborder="0" allowfullscreen></iframe>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php get_footer(); ?>