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
 * 1. Drop Custom Database Table.
 * 
 * Permanently delete the `cep_attendees` table from the database.
 * Utilizing $wpdb->prefix ensures compatibility with the site's specific table prefix.
 */
$table_name = $wpdb->prefix . 'cep_attendees';

// Secure from SQL Injection: The table name is hardcoded and does not rely on any user input.
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

/**
 * 2. Delete Options from the `wp_options` table.
 * 
 * An array containing all option keys associated with the plugin's settings.
 * We iterate through this array and delete each option to prevent leaving orphan data in the database.
 * 
 * @var array $options List of option names to be deleted.
 */
$options = [
    'cep_enable_time',
    'cep_enable_location',
    'cep_enable_countdown',
    'cep_enable_ics',
    'cep_hide_past_events',
    'cep_label_schedule',
    'cep_label_gallery',
    'cep_label_video',
    'cep_label_upcoming',
    'cep_label_past',
    'cep_text_session',
    'cep_text_back',
    'cep_text_ends',
    'cep_text_waitlist',
    'cep_email_confirm_sub',
    'cep_email_confirm_body',
    'cep_email_remind_sub',
    'cep_email_remind_body'
];

foreach ($options as $option) {
    delete_option($option);
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
