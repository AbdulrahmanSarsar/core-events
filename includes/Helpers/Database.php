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

        $charset_collate = $wpdb->get_charset_collate();

        // Include the necessary WordPress file for the dbDelta function.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        self::create_attendees_table($wpdb, $charset_collate);
        self::create_email_queue_table($wpdb, $charset_collate);
    }

    /**
     * Create / migrate the attendees table.
     *
     * @param \wpdb  $wpdb            WordPress DB object.
     * @param string $charset_collate Charset/collate clause from wpdb.
     * @return void
     */
    private static function create_attendees_table($wpdb, $charset_collate)
    {
        $table_name = $wpdb->prefix . 'cep_attendees';

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

        dbDelta($sql);

        // Force WordPress to add missing columns if dbDelta fails during plugin updates.
        // Security Note: The table name is derived securely from the prefix and is hardcoded,
        // making this query safe from SQL injection.
        $existing_columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);

        if (is_array($existing_columns)) {
            if (! in_array('status', $existing_columns, true)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD status varchar(20) DEFAULT 'confirmed' NOT NULL AFTER phone");
            }
            if (! in_array('qr_token', $existing_columns, true)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD qr_token varchar(64) DEFAULT '' NOT NULL AFTER status");
            }
            if (! in_array('check_in', $existing_columns, true)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD check_in tinyint(1) DEFAULT 0 NOT NULL AFTER qr_token");
            }
        }
    }

    /**
     * Create the asynchronous email queue table.
     *
     * The queue lets us hand wp_mail() work off to a background cron worker
     * so that requests which trigger many emails at once (e.g. a 1000-seat
     * event sending reminders) never block the user's HTTP request.
     *
     * Status values:
     *   - pending: not yet sent, waiting for the worker.
     *   - sent:    delivered successfully (kept briefly for audit, then purged).
     *   - failed:  exhausted retries; left in place for the admin to inspect.
     *
     * The (status, scheduled_for) composite index is what the worker
     * filters on every minute, so it must always remain.
     *
     * @param \wpdb  $wpdb
     * @param string $charset_collate
     * @return void
     */
    private static function create_email_queue_table($wpdb, $charset_collate)
    {
        $table_name = $wpdb->prefix . 'cep_email_queue';

        $sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			recipient varchar(190) NOT NULL,
			subject text NOT NULL,
			body longtext NOT NULL,
			headers text NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			attempts tinyint(3) unsigned DEFAULT 0 NOT NULL,
			last_error text NOT NULL,
			scheduled_for datetime NOT NULL,
			sent_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY status_scheduled (status, scheduled_for),
			KEY recipient (recipient)
		) {$charset_collate};";

        dbDelta($sql);
    }
}
