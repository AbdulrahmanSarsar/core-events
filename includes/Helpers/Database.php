<?php

/**
 * Database Helper Class.
 * 
 * Handles the creation and modification of custom database tables
 * required by the plugin upon activation.
 *
 * @package CoreEventsPro\Helpers
 * @since 4.0.0
 */

namespace CoreEventsPro\Helpers;

// Security: Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Database
 *
 * Responsible for setting up the custom database schema.
 */
class Database
{

    /**
     * Create or update the custom tables.
     * 
     * Uses WordPress dbDelta to create the table and runs manual ALTER TABLE
     * queries as a robust fallback to ensure missing columns are added successfully
     * during updates.
     *
     * @return void
     */
    public static function create_tables()
    {
        global $wpdb;

        // Set up the table name dynamically using the site's database prefix.
        $table_name      = $wpdb->prefix . 'cep_attendees';
        $charset_collate = $wpdb->get_charset_collate();

        // Construct the SQL statement for dbDelta.
        // Note: dbDelta requires very specific formatting (e.g., exactly two spaces after PRIMARY KEY).
        $sql = "CREATE TABLE {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			event_id mediumint(9) NOT NULL,
			name tinytext NOT NULL,
			email varchar(100) NOT NULL,
			phone varchar(20) DEFAULT '' NOT NULL,
			status varchar(20) DEFAULT 'confirmed' NOT NULL,
			qr_token varchar(64) DEFAULT '' NOT NULL, 
			check_in tinyint(1) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY event_id (event_id)
		) {$charset_collate};";

        // Include the necessary WordPress file for the dbDelta function.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Execute dbDelta safely.
        dbDelta($sql);

        // 🚀 Radical Fix: Force WordPress to add missing columns if dbDelta fails during plugin updates.

        // Fetch currently existing columns in the table.
        // Security Note: The table name is derived securely from the prefix and is hardcoded,
        // making this query safe from SQL injection.
        $existing_columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);

        if (is_array($existing_columns)) {

            // Add 'status' column if missing.
            if (! in_array('status', $existing_columns, true)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD status varchar(20) DEFAULT 'confirmed' NOT NULL AFTER phone");
            }

            // Add 'qr_token' column if missing.
            if (! in_array('qr_token', $existing_columns, true)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD qr_token varchar(64) DEFAULT '' NOT NULL AFTER status");
            }

            // Add 'check_in' column if missing.
            if (! in_array('check_in', $existing_columns, true)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD check_in tinyint(1) DEFAULT 0 NOT NULL AFTER qr_token");
            }
        }
    }
}
