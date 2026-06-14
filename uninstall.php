<?php

/**
 * Fired when the plugin is uninstalled.
 * 
 * This file is executed exclusively when the site administrator deletes the plugin from the WordPress dashboard.
 * It cleans up the database by removing custom tables and saved options.
 * 28. Uninstall.php - Clears data upon deletion.
 *
 * @package CoreEventsPro
 * @since 4.0.0
 */

// Security: Prevent direct file access. Exit immediately if the request is not a valid WordPress uninstall process.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * WordPress database abstraction object.
 * 
 * @global \wpdb $wpdb
 */
global $wpdb;

/**
 * 1. Drop Custom Database Tables.
 *
 * Permanently delete the plugin's custom tables from the database.
 * Utilizing $wpdb->prefix ensures compatibility with the site's specific
 * table prefix.
 *
 * Secure from SQL Injection: the table names are hardcoded and do not
 * rely on any user input.
 */
$cep_tables = [
    $wpdb->prefix . 'cep_attendees',
    $wpdb->prefix . 'cep_email_queue',
];

foreach ($cep_tables as $cep_table) {
    $wpdb->query("DROP TABLE IF EXISTS {$cep_table}");
}

/**
 * 2. Delete Options from the `wp_options` table.
 * 
 * An array containing all option keys associated with the plugin's settings.
 * We iterate through this array and delete each option to prevent leaving orphan data in the database.
 * 
 * @var array $options List of option names to be deleted.
 */
$options = [
    // Feature toggles
    'cep_enable_time',
    'cep_enable_location',
    'cep_enable_countdown',
    'cep_enable_ics',
    'cep_hide_past_events',
    // Venue management
    'cep_location_type',
    'cep_predefined_locations',
    // Section labels
    'cep_label_schedule',
    'cep_label_gallery',
    'cep_label_video',
    'cep_label_upcoming',
    'cep_label_past',
    // UI labels
    'cep_text_session',
    'cep_text_back',
    'cep_text_ends',
    'cep_text_waitlist',
    // Email templates
    'cep_email_confirm_sub',
    'cep_email_confirm_body',
    'cep_email_remind_sub',
    'cep_email_remind_body',
    // Anti-spam
    'cep_block_disposable_emails',
    // Licensing
    'core_events_pro_purchase_code',
    'core_events_pro_license_status',
    'core_events_pro_activated_domain',
    'core_events_pro_license_data',
    'core_events_pro_license_last_check',
    'core_events_pro_license_last_error',
];

foreach ($options as $option) {
    delete_option($option);
}

/**
 * Clear scheduled cron events created by the plugin so we do not leave
 * orphan jobs in the WP-Cron queue after uninstall.
 */
$cron_hooks = [
    'cep_hourly_check',
    'cep_license_heartbeat',
    'cep_email_queue_worker',
];

foreach ($cron_hooks as $hook) {
    $timestamp = wp_next_scheduled($hook);
    while ($timestamp) {
        wp_unschedule_event($timestamp, $hook);
        $timestamp = wp_next_scheduled($hook);
    }
}

/**
 * 3. Delete All Events (Optional)
 * 
 * (Optional: Uncomment the block below if you wish to delete the Custom Post Types upon uninstallation).
 * By default, it is recommended to leave CPT data intact so the user does not lose their content if the plugin is deleted by mistake.
 */
/*
$events = get_posts( [
	'post_type'   => [ 'main_event', 'sub_event' ],
	'numberposts' => -1,
	'post_status' => 'any'
] );

foreach ( $events as $event ) {
	wp_delete_post( $event->ID, true );
}
*/
