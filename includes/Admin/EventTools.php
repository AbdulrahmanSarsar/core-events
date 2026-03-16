<?php

/**
 * Event Tools Class.
 * 
 * Provides advanced management tools for events including:
 * 1. Cloning (Duplicating) events.
 * 2. Bulk actions for changing event status.
 * 3. Importing events from a CSV file.
 *
 * @package CoreEventsPro\Admin
 * @since 4.0.0
 */

namespace CoreEventsPro\Admin;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class EventTools
 *
 * Handles advanced administrative operations for the custom post types.
 */
class EventTools
{

    /**
     * Constructor.
     * 
     * Initializes hooks for cloning, bulk actions, and CSV imports.
     */
    public function __construct()
    {
        // 1. Clone Event
        add_filter('post_row_actions', [$this, 'add_clone_button'], 10, 2);
        add_action('admin_action_cep_clone_event', [$this, 'process_clone_event']);

        // 2. Bulk Actions
        add_filter('bulk_actions-edit-main_event', [$this, 'register_bulk_actions']);
        add_filter('handle_bulk_actions-edit-main_event', [$this, 'process_bulk_actions'], 10, 3);
        add_action('admin_notices', [$this, 'bulk_action_admin_notice']);

        // 3. CSV Import
        add_action('admin_post_cep_import_csv', [$this, 'process_csv_import']);
    }

	// --- 1. CLONE EVENT ---

    /**
     * Add "Clone Event" button to the post row actions.
     *
     * @param array    $actions Existing row actions.
     * @param \WP_Post $post    The current post object.
     * @return array Modified row actions.
     */
    public function add_clone_button($actions, $post)
    {
        if ($post->post_type === 'main_event' || $post->post_type === 'sub_event') {
            $url = wp_nonce_url(admin_url("admin.php?action=cep_clone_event&post={$post->ID}"), 'cep_clone_nonce');

            // i18n & Security: Translating strings and escaping output.
            $clone_text = esc_html__('Clone Event', 'core-events-pro');
            $title_attr = esc_attr__('Duplicate this event', 'core-events-pro');

            $actions['clone'] = sprintf(
                '<a href="%s" title="%s" style="color:#2563eb;">%s</a>',
                esc_url($url),
                $title_attr,
                $clone_text
            );
        }

        return $actions;
    }

    /**
     * Process the cloning of an event.
     * 
     * Duplicates the event details, meta data, and taxonomies, then redirects to the edit page.
     *
     * @return void
     */
    public function process_clone_event()
    {
        // Security Check: Verify nonce and check if post ID is set.
        if (! isset($_GET['post']) || ! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'cep_clone_nonce')) {
            wp_die(esc_html__('Security check failed.', 'core-events-pro'));
        }

        // Security: Cast the post ID to an absolute integer.
        $post_id = absint(wp_unslash($_GET['post']));
        $post    = get_post($post_id);

        if (! $post) {
            wp_die(esc_html__('Event not found.', 'core-events-pro'));
        }

        // Create a new post as a draft based on the original.
        // i18n: Make the "(Copy)" suffix translatable.
        $new_post = [
            'post_title'   => $post->post_title . ' ' . __('(Copy)', 'core-events-pro'),
            'post_content' => $post->post_content,
            'post_status'  => 'draft',
            'post_type'    => $post->post_type,
            'post_author'  => get_current_user_id()
        ];

        $new_post_id = wp_insert_post($new_post);

        if ($new_post_id) {
            // Copy post meta (Date, Capacity, Colors, etc.)
            $meta_keys = get_post_custom($post_id);

            foreach ($meta_keys as $key => $values) {
                // Exclude internal meta keys that shouldn't be duplicated.
                if (strpos($key, '_edit_') === 0) {
                    continue;
                }

                foreach ($values as $value) {
                    add_post_meta($new_post_id, $key, maybe_unserialize($value));
                }
            }

            // Copy Taxonomies (Categories)
            $taxonomies = get_object_taxonomies($post->post_type);

            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'slugs']);
                wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
            }

            // Redirect to the newly created cloned event's edit screen.
            wp_redirect(admin_url("post.php?action=edit&post={$new_post_id}"));
            exit;
        } else {
            wp_die(esc_html__('Failed to clone event.', 'core-events-pro'));
        }
    }

	// --- 2. BULK ACTIONS ---

    /**
     * Register custom bulk actions in the dropdown.
     *
     * @param array $bulk_actions Existing bulk actions.
     * @return array Modified bulk actions.
     */
    public function register_bulk_actions($bulk_actions)
    {
        // i18n: Make options translatable.
        $bulk_actions['cep_mark_finished']  = esc_html__('Mark as Finished', 'core-events-pro');
        $bulk_actions['cep_mark_cancelled'] = esc_html__('Mark as Cancelled', 'core-events-pro');

        return $bulk_actions;
    }

    /**
     * Handle the execution of custom bulk actions.
     *
     * @param string $redirect_to The URL to redirect to after processing.
     * @param string $doaction    The action being executed.
     * @param array  $post_ids    Array of selected post IDs.
     * @return string The modified redirect URL.
     */
    public function process_bulk_actions($redirect_to, $doaction, $post_ids)
    {
        if ($doaction !== 'cep_mark_finished' && $doaction !== 'cep_mark_cancelled') {
            return $redirect_to;
        }

        $new_status = ($doaction === 'cep_mark_finished') ? 'finished' : 'cancelled';
        $count      = 0;

        foreach ($post_ids as $post_id) {
            update_post_meta(absint($post_id), '_cep_status', $new_status);
            $count++;
        }

        $redirect_to = add_query_arg('cep_bulk_done', $count, $redirect_to);
        $redirect_to = add_query_arg('cep_bulk_status', $new_status, $redirect_to);

        return $redirect_to;
    }

    /**
     * Display an admin notice after a bulk action or CSV import.
     *
     * @return void
     */
    public function bulk_action_admin_notice()
    {
        // Notice for bulk status update.
        if (! empty($_GET['cep_bulk_done'])) {
            $count  = absint(wp_unslash($_GET['cep_bulk_done']));
            $status = sanitize_text_field(wp_unslash($_GET['cep_bulk_status']));

            // i18n & Security: Translating string with placeholders and allowing strong tags.
            $message = sprintf(
                /* translators: 1: Number of events, 2: Event status */
                __('Successfully marked %1$d events as <strong>%2$s</strong>.', 'core-events-pro'),
                $count,
                esc_html($status)
            );

            printf('<div class="updated notice is-dismissible"><p>%s</p></div>', wp_kses_post($message));
        }

        // Notice for CSV Import.
        if (! empty($_GET['cep_import_done'])) {
            $count = absint(wp_unslash($_GET['cep_import_done']));

            // i18n: Translating string with placeholder.
            $message = sprintf(
                /* translators: %d: Number of imported events */
                __('✅ Successfully imported <strong>%d</strong> events from CSV.', 'core-events-pro'),
                $count
            );

            printf('<div class="updated notice is-dismissible"><p>%s</p></div>', wp_kses_post($message));
        }
    }

	// --- 3. CSV IMPORT ---

    /**
     * Process the CSV file upload to bulk import events.
     *
     * @return void
     */
    public function process_csv_import()
    {
        // Security Check: Verify nonce.
        if (! isset($_POST['cep_import_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cep_import_nonce'])), 'cep_import_action')) {
            wp_die(esc_html__('Security check failed.', 'core-events-pro'));
        }

        // Security Check: Verify user capabilities.
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'core-events-pro'));
        }

        // Check if a file was uploaded.
        if (empty($_FILES['cep_csv_file']['tmp_name'])) {
            wp_die(esc_html__('Please upload a valid CSV file.', 'core-events-pro'));
        }

        // Security: Sanitize the uploaded file path.
        $file   = sanitize_text_field(wp_unslash($_FILES['cep_csv_file']['tmp_name']));
        $handle = fopen($file, "r");

        if ($handle !== FALSE) {
            $header = fgetcsv($handle); // Skip the first row (headers)
            $count  = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Assumed CSV column order:
                // Title(0), Content(1), Start Date(2), End Date(3), Capacity(4), Location(5)
                $title    = sanitize_text_field($data[0] ?? __('Untitled Event', 'core-events-pro'));
                $content  = wp_kses_post($data[1] ?? ''); // Allow safe HTML in content.
                $start    = sanitize_text_field($data[2] ?? ''); // format: YYYY-MM-DDTHH:MM
                $end      = sanitize_text_field($data[3] ?? '');
                $capacity = absint($data[4] ?? 0);
                $location = sanitize_text_field($data[5] ?? '');

                // Create the event post
                $new_post_id = wp_insert_post([
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_status'  => 'publish',
                    'post_type'    => 'main_event'
                ]);

                if ($new_post_id) {
                    update_post_meta($new_post_id, '_cep_start', $start);
                    update_post_meta($new_post_id, '_cep_end', $end);
                    update_post_meta($new_post_id, '_cep_capacity', $capacity);
                    update_post_meta($new_post_id, '_cep_location', $location);
                    update_post_meta($new_post_id, '_cep_status', 'upcoming');
                    update_post_meta($new_post_id, '_cep_color', '#3b82f6'); // Default color

                    $count++;
                }
            }

            fclose($handle);

            // Redirect with success message.
            wp_redirect(admin_url("edit.php?post_type=main_event&page=cep-dashboard&cep_import_done={$count}"));
            exit;
        } else {
            wp_die(esc_html__('Failed to open the CSV file.', 'core-events-pro'));
        }
    }
}
