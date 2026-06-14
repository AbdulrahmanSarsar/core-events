<?php

/**
 * Plugin Name: EventCore – Advanced Events & Booking Manager
 * Plugin URI:  https://almanarsoft.com
 * Description: Complete event management system with an interactive calendar, sub-events (sessions), RSVP & smart waitlist, local QR-code ticketing & check-in, paid tickets via WooCommerce, and a REST API.
 * Version:     1.0.0
 * Author:      Abdulrahman Sarsar
 * Author URI:  https://almanarsoft.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: core-events-pro
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CoreEventsPro
 */

namespace CoreEventsPro;

// Exit if accessed directly (Security measure).
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Plugin current version.
 * 
 * @var string
 */
define('CEP_VERSION', '1.0.0');

/**
 * Plugin absolute directory path.
 * 
 * @var string
 */
define('CEP_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin base URL.
 * 
 * @var string
 */
define('CEP_URL', plugin_dir_url(__FILE__));

/**
 * Class Plugin
 *
 * Main plugin class using the Singleton pattern.
 * Responsible for requiring files, initializing hooks, and loading assets.
 *
 * @package CoreEventsPro
 */
final class Plugin
{

    /**
     * The single instance of the class.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance of the Plugin.
     * Ensures only one instance of the plugin is loaded.
     *
     * @return Plugin
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     * Protected constructor to prevent creating a new instance.
     */
    private function __construct()
    {
        $this->require_files();
        $this->init_hooks();
    }

    /**
     * Require all necessary core files.
     *
     * @return void
     */
    private function require_files()
    {
        // Helpers
        require_once CEP_PATH . 'includes/Helpers/Utils.php';
        require_once CEP_PATH . 'includes/Helpers/Cron.php';
        require_once CEP_PATH . 'includes/Helpers/QrGenerator.php';
        require_once CEP_PATH . 'includes/Helpers/AntiSpam.php';
        require_once CEP_PATH . 'includes/Helpers/EmailQueue.php';
        require_once CEP_PATH . 'includes/Helpers/Schema.php';

        // Post Types & Taxonomies
        require_once CEP_PATH . 'includes/PostTypes/MainEvent.php';
        require_once CEP_PATH . 'includes/PostTypes/SubEvent.php';
        require_once CEP_PATH . 'includes/PostTypes/Taxonomies.php';

        // Admin & Settings
        require_once CEP_PATH . 'includes/Admin/MetaBoxes.php';
        require_once CEP_PATH . 'includes/Admin/Settings.php';
        require_once CEP_PATH . 'includes/Admin/Dashboard.php';
        require_once CEP_PATH . 'includes/Admin/EventTools.php';
        require_once CEP_PATH . 'includes/Admin/AttendeesPage.php'; // New Feature: Attendees Table
        require_once CEP_PATH . 'includes/Admin/SetupWizard.php'; // NEW: Setup Wizard


        // API
        require_once CEP_PATH . 'includes/Api/EventController.php';

        // Frontend & Shortcodes
        require_once CEP_PATH . 'includes/Shortcodes/Calendar.php';
        require_once CEP_PATH . 'includes/Shortcodes/EventViews.php';

        // Template Loader 
        require_once CEP_PATH . 'includes/Helpers/TemplateLoader.php';

        // Modules
        require_once CEP_PATH . 'includes/Modules/Attendees.php';
        require_once CEP_PATH . 'includes/Modules/WooCommerce.php';
        require_once CEP_PATH . 'includes/Modules/Licensing/Licensing.php';
    }

    /**
     * Initialize all WordPress hooks and instantiate classes.
     *
     * @return void
     */
    private function init_hooks()
    {
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Initialize Classes
        new PostTypes\MainEvent();
        new PostTypes\SubEvent();
        new PostTypes\Taxonomies();
        new Admin\MetaBoxes();
        new Admin\Settings();
        new Admin\Dashboard();
        new Admin\EventTools();
        new Admin\AttendeesPage(); // Initialize Attendees Page
        new Api\EventController();
        new Shortcodes\Calendar();
        new Shortcodes\EventViews();
        new Helpers\TemplateLoader();
        new Helpers\Cron();
        new Helpers\EmailQueue();
        new Helpers\Schema();
        new Modules\Attendees();
        new Modules\WooCommerce();
        new Modules\Licensing();
        new Admin\SetupWizard(); // Initialize Wizard

    }

    /**
     * Load the plugin text domain for translation.
     *
     * @return void
     */
    public function on_plugins_loaded()
    {
        load_plugin_textdomain('core-events-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueue admin styles and scripts.
     *
     * @return void
     */
    public function enqueue_admin_assets()
    {
        global $post_type;

        // Security: Sanitize and unslash the $_GET['page'] variable before usage.
        $page_slug = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        // Fix: Allow loading CSS on custom plugin pages (Settings, Dashboard, Attendees).
        $is_cep_page = strpos($page_slug, 'cep-') === 0;

        if ('main_event' !== $post_type && 'sub_event' !== $post_type && ! $is_cep_page) {
            return;
        }

        // 1. Enqueue WordPress core media library.
        wp_enqueue_media();

        // 2. Enqueue admin CSS.
        wp_enqueue_style('cep-admin', CEP_URL . 'assets/css/admin.css', [], CEP_VERSION);

        // 3. Enqueue new admin JS file.
        wp_enqueue_script('cep-admin-js', CEP_URL . 'assets/js/admin.js', ['jquery'], CEP_VERSION, true);
    }

    /**
     * Enqueue frontend styles and scripts.
     *
     * @return void
     */
    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('cep-front', CEP_URL . 'assets/css/frontend.css', [], CEP_VERSION);

        wp_enqueue_script('cep-js', CEP_URL . 'assets/js/calendar.js', ['jquery'], CEP_VERSION, true);

        // Localize script with API URL and security nonce.
        wp_localize_script('cep-js', 'cepData', [
            'api_url' => esc_url_raw(get_rest_url(null, 'events/v1/')),
            'nonce'   => wp_create_nonce('wp_rest')
        ]);
    }
}

/**
 * Main initialization function for the plugin.
 *
 * @return Plugin
 */
function init()
{
    return Plugin::instance();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\init');

/**
 * Add settings link to the plugins page.
 * (Moved outside the activation hook to run consistently).
 * 
 * @param array $links Array of plugin action links.
 * @return array Modified array of plugin action links.
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    // i18n & Security: Make the text translatable and escape it.
    $settings_text = esc_html__('Settings', 'core-events-pro');
    $settings_link = sprintf('<a href="edit.php?post_type=main_event&page=cep-settings">%s</a>', $settings_text);

    array_unshift($links, $settings_link);

    return $links;
});

/**
 * Register the activation hook.
 */
register_activation_hook(__FILE__, __NAMESPACE__ . '\\cep_activate_plugin');

/**
 * ACTIVATION HOOK
 * This code runs once when the plugin is activated.
 * Responsible for assigning capabilities, creating tables, and flushing rewrite rules.
 *
 * @return void
 */
function cep_activate_plugin()
{
    // Feature 24: Add custom capability for the administrator role.
    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('manage_cep_events');
    }

    // 1. Manually require the Database file since the autoloader might not be triggered yet.
    $db_file = plugin_dir_path(__FILE__) . 'includes/Helpers/Database.php';

    if (file_exists($db_file)) {
        require_once $db_file;

        // Create the custom table and ensure columns exist.
        if (class_exists('\\CoreEventsPro\\Helpers\\Database')) {
            \CoreEventsPro\Helpers\Database::create_tables();
        }
    }

    // 2. Flush rewrite rules to avoid 404 errors for Custom Post Types.
    require_once plugin_dir_path(__FILE__) . 'includes/PostTypes/MainEvent.php';
    require_once plugin_dir_path(__FILE__) . 'includes/PostTypes/SubEvent.php';

    // Temporarily register CPTs to flush rewrite rules.
    if (class_exists('\\CoreEventsPro\\PostTypes\\MainEvent')) {
        $main = new \CoreEventsPro\PostTypes\MainEvent();
        $main->register();
    }

    if (class_exists('\\CoreEventsPro\\PostTypes\\SubEvent')) {
        $sub = new \CoreEventsPro\PostTypes\SubEvent();
        $sub->register();
    }

    flush_rewrite_rules();
    // Set a transient to trigger the redirect to the setup wizard
    set_transient('_cep_activation_redirect', true, 30);
}

/**
 * Register the deactivation hook to clean up scheduled cron jobs.
 *
 * Without this, disabling the plugin still leaves orphan WP-Cron entries
 * that the scheduler keeps trying to run forever.
 */
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\cep_deactivate_plugin');

/**
 * DEACTIVATION HOOK
 *
 * Removes scheduled events created by the plugin. We deliberately do NOT
 * touch user data (events, attendees, settings) - those only get cleaned
 * up on full uninstall.
 *
 * @return void
 */
function cep_deactivate_plugin()
{
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
}

/**
 * Redirect to Setup Wizard after plugin activation.
 */
add_action('admin_init', function () {
    if (get_transient('_cep_activation_redirect')) {
        delete_transient('_cep_activation_redirect');
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('admin.php?page=cep-setup'));
            exit;
        }
    }
});
