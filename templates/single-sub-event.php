<?php

/**
 * Template Name: Single Sub Event - Pro V6 (Hybrid: Paid Tickets & Free RSVP)
 * 
 * Displays the individual session/workshop details, including its relationship
 * to the parent main event, video, gallery, and a standalone Hybrid RSVP/Ticket system.
 *
 * @package CoreEventsPro\Templates
 * @since 4.2.0
 */

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

use CoreEventsPro\Helpers\AntiSpam;
use CoreEventsPro\Helpers\Utils;

get_header();

$id           = get_the_ID();
$parent_id    = get_post_meta($id, '_cep_parent_id', true);
$start        = get_post_meta($id, '_cep_start', true);
$end          = get_post_meta($id, '_cep_end', true);
$gallery_ids  = get_post_meta($id, '_cep_gallery_ids', true);
$video_url    = get_post_meta($id, '_cep_video_url', true);
$location     = get_post_meta($id, '_cep_location', true);
$overview     = get_post_meta($id, '_cep_overview', true);
$status       = get_post_meta($id, '_cep_status', true) ?: 'upcoming';

// ✅ NEW LOGIC: Read the new registration settings
$enable_rsvp  = get_post_meta($id, '_cep_enable_rsvp', true); // '1' or '0'
$rsvp_type    = get_post_meta($id, '_cep_rsvp_type', true);   // 'free' or 'paid'

$tickets      = get_post_meta($id, '_cep_tickets', true);
$wc_active    = class_exists('WooCommerce');
$has_paid_tix = (is_array($tickets) && ! empty($tickets) && $wc_active && $rsvp_type === 'paid');

$parent_title = $parent_id ? get_the_title(absint($parent_id)) : '';
$parent_link  = $parent_id ? get_permalink(absint($parent_id)) : '#';

// Plugin Options & i18n
$show_time    = get_option('cep_enable_time', 1);
$show_loc     = get_option('cep_enable_location', 1);
$txt_session  = get_option('cep_text_session', __('Session', 'core-events-pro'));
$txt_back     = get_option('cep_text_back', __('Back to Event', 'core-events-pro'));
$lbl_gallery  = get_option('cep_label_gallery', __('Event Gallery', 'core-events-pro'));

// i18n: Dynamically build section titles based on the custom session text.
$lbl_about    = sprintf(esc_html__('About this %s', 'core-events-pro'), $txt_session);
$lbl_details  = sprintf(esc_html__('%s Details', 'core-events-pro'), $txt_session);

?>
<div class="cep-page-wrapper">

    <!-- Header with Back Link -->
    <div class="cep-sub-header">
        <div class="cep-content-container" style="padding-bottom:0;">
            <a href="<?php echo esc_url($parent_link); ?>" class="cep-back-link">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php echo esc_html($txt_back); ?>: <strong><?php echo esc_html($parent_title); ?></strong>
            </a>
        </div>
    </div>

    <!-- Sub Event Hero -->
    <div class="cep-sub-hero">
        <div class="cep-content-container">
            <span class="cep-label-session"><?php echo esc_html($txt_session); ?></span>
            <h1 class="cep-sub-title"><?php the_title(); ?></h1>

            <div class="cep-sub-meta">
                <!-- Date Meta -->
                <span class="cep-meta-item">
                    📅 <?php echo esc_html(date_i18n(_x('M d', 'Sub event date format', 'core-events-pro'), strtotime($start))); ?>
                    <?php
                    if ($end && gmdate('Ymd', strtotime($start)) != gmdate('Ymd', strtotime($end))) {
                        echo esc_html(_x(' - ', 'Date separator', 'core-events-pro')) . esc_html(date_i18n(_x('M d', 'Sub event date format', 'core-events-pro'), strtotime($end)));
                    }
                    ?>
                </span>

                <!-- Time Meta -->
                <?php if ($show_time) : ?>
                    <span class="cep-meta-item">
                        ⏰ <?php echo esc_html(date_i18n(_x('H:i', 'Sub event time format', 'core-events-pro'), strtotime($start))); ?>
                        <?php
                        if ($end) {
                            echo esc_html(_x(' - ', 'Time separator', 'core-events-pro')) . esc_html(date_i18n(_x('H:i', 'Sub event time format', 'core-events-pro'), strtotime($end)));
                        }
                        ?>
                    </span>
                <?php endif; ?>

                <!-- Location Meta -->
                <?php if ($show_loc && $location) : ?>
                    <span class="cep-meta-item">
                        📍 <?php echo esc_html($location); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="cep-content-container cep-grid-layout">

        <!-- Main Column -->
        <div class="cep-col-main">
            <!-- Video -->
            <?php if ($video_url) : ?>
                <div class="cep-section-box no-pad">
                    <div class="cep-video-responsive">
                        <?php echo wp_oembed_get(esc_url($video_url)); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Overview -->
            <?php if ($overview) : ?>
                <div class="cep-overview-box" style="margin-bottom: 30px;">
                    <p class="cep-lead-text"><?php echo nl2br(esc_html($overview)); ?></p>
                </div>
            <?php endif; ?>

            <!-- Content -->
            <div class="cep-section-box">
                <h3 class="cep-box-title"><?php echo esc_html($lbl_about); ?></h3>
                <div class="cep-text-content"><?php the_content(); ?></div>
            </div>

            <!-- Gallery -->
            <?php if (! empty($gallery_ids)) : ?>
                <div class="cep-section-box">
                    <h3 class="cep-box-title"><?php echo esc_html($lbl_gallery); ?></h3>
                    <div class="cep-gallery-grid-small">
                        <?php
                        foreach (explode(',', $gallery_ids) as $img_id) :
                            $img_id = absint(trim($img_id));
                            if ($img_id) :
                        ?>
                                <div class="cep-gal-item">
                                    <?php echo wp_get_attachment_image($img_id, 'medium'); ?>
                                </div>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar Column -->
        <div class="cep-col-sidebar">
            <div class="cep-info-card">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="cep-session-thumb">
                        <?php the_post_thumbnail('medium_large'); ?>
                    </div>
                <?php endif; ?>

                <div class="cep-info-body">
                    <h4><?php echo esc_html($lbl_details); ?></h4>
                    <ul>
                        <li>
                            <strong><?php esc_html_e('Date:', 'core-events-pro'); ?></strong>
                            <?php echo $start ? esc_html(date_i18n(_x('F j, Y', 'Sub event full date format', 'core-events-pro'), strtotime($start))) : esc_html__('TBA', 'core-events-pro'); ?>
                        </li>
                        <?php if ($show_time) : ?>
                            <li>
                                <strong><?php esc_html_e('Time:', 'core-events-pro'); ?></strong>
                                <?php echo $start ? esc_html(date_i18n(_x('g:i A', 'Sub event am/pm time format', 'core-events-pro'), strtotime($start))) : esc_html__('TBA', 'core-events-pro'); ?>
                            </li>
                        <?php endif; ?>
                        <li>
                            <strong><?php esc_html_e('Parent Event:', 'core-events-pro'); ?></strong>
                            <a href="<?php echo esc_url($parent_link); ?>"><?php echo esc_html($parent_title); ?></a>
                        </li>
                    </ul>

                    <!-- ✅ HYBRID REGISTRATION FOR SUB-EVENT (CHECK ENABLE = 1) -->
                    <?php
                    if ($enable_rsvp === '1' && $status !== 'cancelled' && $status !== 'finished') :
                        echo '<hr>';

                        // ---------------------------------------------------------
                        // MODE 1: WOOCOMMERCE PAID TICKETS (If selected)
                        // ---------------------------------------------------------
                        if ($has_paid_tix) :
                    ?>
                            <h4 style="margin-top:10px; margin-bottom:15px;"><?php esc_html_e('Buy Ticket for this Session', 'core-events-pro'); ?></h4>
                            <div class="cep-sub-tickets-list" style="display:flex; flex-direction:column; gap:10px;">
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
                                    <div style="padding:10px; border:1px solid #e2e8f0; border-radius:6px; background:#f8fafc; text-align:center;">
                                        <strong style="display:block; color:#1e293b; font-size:1rem;"><?php echo esc_html($ticket['name']); ?></strong>
                                        <div style="color:#2563eb; font-weight:bold; font-size:1.1rem; margin:5px 0;">
                                            <?php echo wp_kses_post($price_html); ?>
                                        </div>
                                        <?php if ($in_stock) : ?>
                                            <a href="<?php echo $add_to_cart_url; ?>" style="background:#2563eb; color:#fff; padding:8px 15px; border-radius:4px; text-decoration:none; font-weight:bold; display:block; transition:0.2s; font-size:0.9rem;">
                                                <?php esc_html_e('Buy Ticket', 'core-events-pro'); ?>
                                            </a>
                                        <?php else : ?>
                                            <span style="background:#fef3c7; color:#92400e; padding:8px 15px; border-radius:4px; font-weight:bold; display:block; font-size:0.9rem;">
                                                <?php esc_html_e('Sold Out', 'core-events-pro'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php
                        // ---------------------------------------------------------
                        // MODE 2: FREE RSVP FOR SUB-EVENT
                        // ---------------------------------------------------------
                        else :
                            $capacity = (int) get_post_meta($id, '_cep_capacity', true);
                            $reg      = 0;

                            global $wpdb;
                            $t = $wpdb->prefix . 'cep_attendees';
                            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) === $t) {
                                // ✅ BUG FIX: Count ONLY confirmed registrations
                                $reg = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE event_id = %d AND (status = 'confirmed' OR status = '')", $id));
                            }

                            if ($capacity > 0 && $reg >= $capacity) :
                            ?>
                                <div style="background:#fef3c7; color:#92400e; padding:10px; border-radius:5px; text-align:center; font-weight:bold; margin-bottom:15px;">
                                    <?php esc_html_e('Fully Booked', 'core-events-pro'); ?>
                                </div>
                            <?php else : ?>
                                <h4 style="margin-top:10px; margin-bottom:15px;"><?php esc_html_e('Register for this Session', 'core-events-pro'); ?></h4>
                                <form id="cep-sub-rsvp-form">
                                    <input type="hidden" name="event_id" value="<?php echo absint($id); ?>">
                                    <input type="hidden" name="action" value="cep_submit_rsvp">
                                    <?php wp_nonce_field('cep_rsvp_nonce', 'security'); ?>
                                    <?php AntiSpam::render_fields(); ?>

                                    <input type="text" name="name" placeholder="<?php esc_attr_e('Name', 'core-events-pro'); ?>" required style="width:100%; margin-bottom:8px; padding:8px;">
                                    <input type="email" name="email" placeholder="<?php esc_attr_e('Email', 'core-events-pro'); ?>" required style="width:100%; margin-bottom:8px; padding:8px;">

                                    <button type="submit" style="width:100%; background:#2563eb; color:#fff; padding:10px; border:none; border-radius:5px; font-weight:bold; cursor:pointer;">
                                        <?php esc_html_e('Register', 'core-events-pro'); ?>
                                    </button>
                                    <div id="sub-rsvp-msg" style="margin-top:10px; font-size:12px; text-align:center;"></div>
                                </form>

                                <script>
                                    jQuery(document).ready(function($) {
                                        $('#cep-sub-rsvp-form').submit(function(e) {
                                            e.preventDefault();
                                            var btn = $(this).find('button');
                                            var waitText = '<?php echo esc_js(__('Wait...', 'core-events-pro')); ?>';
                                            var registerText = '<?php echo esc_js(__('Register', 'core-events-pro')); ?>';

                                            btn.text(waitText).prop('disabled', true);

                                            $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', $(this).serialize(), function(res) {
                                                $('#sub-rsvp-msg').text(res.data.message).css('color', res.success ? 'green' : 'red');
                                                if (res.success) {
                                                    setTimeout(() => location.reload(), 2000);
                                                } else {
                                                    btn.text(registerText).prop('disabled', false);
                                                }
                                            });
                                        });
                                    });
                                </script>
                    <?php
                            endif; // End Capacity Check
                        endif; // End Hybrid Registration Check
                    endif; // End enable_rsvp Check 
                    ?>

                    <!-- Back Button -->
                    <a href="<?php echo esc_url($parent_link); ?>" class="cep-btn-block" style="margin-top:15px;">
                        <?php echo esc_html($txt_back); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>