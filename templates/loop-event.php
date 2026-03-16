<?php

/**
 * Template part for displaying event summary in a list/loop.
 * 
 * @var \WP_Post $post (Available globally in the WordPress loop)
 * 
 * @package CoreEventsPro\Templates
 * @since 4.0.0
 */

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

use CoreEventsPro\Helpers\Utils;

// Ensure variables are properly assigned.
$event_id   = get_the_ID();
$start_date = get_post_meta($event_id, '_cep_start', true);
$end_date   = get_post_meta($event_id, '_cep_end', true);
$status     = get_post_meta($event_id, '_cep_status', true);
$location   = get_post_meta($event_id, '_cep_location', true); // Example for future custom field usage.
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('cep-loop-item'); ?>>
    <div class="cep-loop-inner">

        <!-- 1. Event Image -->
        <?php if (has_post_thumbnail()) : ?>
            <div class="cep-loop-thumb">
                <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('medium'); ?>
                </a>

                <!-- Badge overlaying the image -->
                <div class="cep-loop-badge">
                    <?php
                    // Security: Output is already escaped in the Utils class, but wp_kses_post adds strict safety.
                    echo wp_kses_post(Utils::get_status_badge($status));
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. Card Content -->
        <div class="cep-loop-content">

            <!-- Date -->
            <div class="cep-loop-meta">
                <span class="cep-meta-date">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php echo esc_html(Utils::format_date($start_date)); ?>
                    <?php
                    if ($end_date) {
                        // i18n & Security: Translating the separator for RTL/LTR compatibility safely.
                        echo esc_html(_x(' - ', 'Date separator', 'core-events-pro')) . esc_html(Utils::format_date($end_date));
                    }
                    ?>
                </span>
            </div>

            <!-- Title -->
            <h3 class="cep-loop-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>

            <!-- Excerpt -->
            <div class="cep-loop-excerpt">
                <?php
                // Security: Escape the output of the excerpt to prevent XSS.
                echo esc_html(Utils::get_excerpt($post, 15));
                ?>
            </div>

            <!-- Read More Button -->
            <div class="cep-loop-footer">
                <a href="<?php the_permalink(); ?>" class="cep-btn-outline">
                    <?php esc_html_e('View Details', 'core-events-pro'); ?> &rarr;
                </a>
            </div>

        </div>
    </div>
</article>