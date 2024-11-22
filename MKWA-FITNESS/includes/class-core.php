<?php
/**
 * Core functionality for MKWA Fitness
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKWA_Core {
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // User related hooks
        add_action('init', array($this, 'register_user_roles'));
        add_action('user_register', array($this, 'setup_new_user'));
        
        // Activity tracking hooks
        add_action('wp', array($this, 'schedule_daily_tasks'));
        add_action('mkwa_daily_streak_check', array($this, 'process_daily_streaks'));
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_mkwa_log_activity', array($this, 'handle_activity_logging'));
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            __('MKWA Fitness', 'mkwa-fitness'),
            __('MKWA Fitness', 'mkwa-fitness'),
            'manage_options',
            'mkwa-fitness',
            array($this, 'render_dashboard_page'),
            'dashicons-universal-access',
            30
        );

        add_submenu_page(
            'mkwa-fitness',
            __('Dashboard', 'mkwa-fitness'),
            __('Dashboard', 'mkwa-fitness'),
            'manage_options',
            'mkwa-fitness',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'mkwa-fitness',
            __('Settings', 'mkwa-fitness'),
            __('Settings', 'mkwa-fitness'),
            'manage_options',
            'mkwa-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('mkwa_settings', 'mkwa_points_checkin');
        register_setting('mkwa_settings', 'mkwa_points_class');
        register_setting('mkwa_settings', 'mkwa_points_cold_plunge');
        register_setting('mkwa_settings', 'mkwa_points_pr');
        register_setting('mkwa_settings', 'mkwa_points_competition');
        register_setting('mkwa_settings', 'mkwa_notification_email_template');
    }

    /**
     * Register custom user roles
     */
    public function register_user_roles() {
        add_role(
            'mkwa_member',
            __('MKWA Member', 'mkwa-fitness'),
            array(
                'read' => true,
                'mkwa_log_activity' => true,
                'mkwa_view_badges' => true
            )
        );

        add_role(
            'mkwa_trainer',
            __('MKWA Trainer', 'mkwa-fitness'),
            array(
                'read' => true,
                'mkwa_log_activity' => true,
                'mkwa_view_badges' => true,
                'mkwa_verify_activities' => true,
                'mkwa_manage_members' => true
            )
        );
    }

    /**
     * Setup new user defaults
     */
    public function setup_new_user($user_id) {
        add_user_meta($user_id, 'mkwa_total_points', 0, true);
        add_user_meta($user_id, 'mkwa_current_streak', 0, true);
        add_user_meta($user_id, 'mkwa_longest_streak', 0, true);
        add_user_meta($user_id, 'mkwa_last_activity_date', '', true);
    }

    /**
     * Schedule daily tasks
     */
    public function schedule_daily_tasks() {
        if (!wp_next_scheduled('mkwa_daily_streak_check')) {
            wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'mkwa_daily_streak_check');
        }
    }

    /**
     * Process daily streaks
     */
    public function process_daily_streaks() {
        $users = get_users(array('role__in' => array('mkwa_member', 'mkwa_trainer')));
        
        foreach ($users as $user) {
            $last_activity = get_user_meta($user->ID, 'mkwa_last_activity_date', true);
            
            if (empty($last_activity)) {
                continue;
            }

            $today = current_time('Y-m-d');
            $last_activity_date = date('Y-m-d', strtotime($last_activity));
            $days_difference = (strtotime($today) - strtotime($last_activity_date)) / DAY_IN_SECONDS;

            if ($days_difference > 1) {
                // Reset streak
                update_user_meta($user->ID, 'mkwa_current_streak', 0);
            }
        }
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (is_user_logged_in()) {
            wp_enqueue_style(
                'mkwa-frontend',
                MKWA_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                MKWA_VERSION
            );

            wp_enqueue_script(
                'mkwa-frontend',
                MKWA_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                MKWA_VERSION,
                true
            );

            wp_localize_script('mkwa-frontend', 'mkwaAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mkwa_frontend')
            ));
        }
    }

    /**
     * Handle activity logging
     */
    public function handle_activity_logging() {
        check_ajax_referer('mkwa_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }

        $activity_type = sanitize_text_field($_POST['activity_type']);
        $user_id = get_current_user_id();

        // Get points for activity
        $points = $this->get_activity_points($activity_type);
        
        if ($points > 0) {
            // Update user points
            $current_points = (int) get_user_meta($user_id, 'mkwa_total_points', true);
            $new_points = $current_points + $points;
            update_user_meta($user_id, 'mkwa_total_points', $new_points);

            // Update streak
            $this->update_user_streak($user_id);

            // Log activity
            $this->log_activity($user_id, $activity_type, $points);

            do_action('mkwa_after_activity_logged', $user_id, $activity_type, $points);

            wp_send_json_success(array(
                'message' => __('Activity logged successfully!', 'mkwa-fitness'),
                'points' => $points,
                'total_points' => $new_points
            ));
        }

        wp_send_json_error('Invalid activity type');
    }

    /**
     * Get points for activity type
     */
    private function get_activity_points($activity_type) {
        $points_map = array(
            'checkin' => get_option('mkwa_points_checkin', 3),
            'class' => get_option('mkwa_points_class', 15),
            'cold_plunge' => get_option('mkwa_points_cold_plunge', 20),
            'pr' => get_option('mkwa_points_pr', 25),
            'competition' => get_option('mkwa_points_competition', 50)
        );

        return isset($points_map[$activity_type]) ? $points_map[$activity_type] : 0;
    }

    /**
     * Update user streak
     */
    private function update_user_streak($user_id) {
        $current_streak = (int) get_user_meta($user_id, 'mkwa_current_streak', true);
        $longest_streak = (int) get_user_meta($user_id, 'mkwa_longest_streak', true);
        
        $current_streak++;
        update_user_meta($user_id, 'mkwa_current_streak', $current_streak);
        update_user_meta($user_id, 'mkwa_last_activity_date', current_time('mysql'));

        if ($current_streak > $longest_streak) {
            update_user_meta($user_id, 'mkwa_longest_streak', $current_streak);
        }
    }

    /**
     * Log activity
     */
    private function log_activity($user_id, $activity_type, $points) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mkwa_activity_log',
            array(
                'user_id' => $user_id,
                'activity_type' => $activity_type,
                'points' => $points,
                'logged_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s')
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        include MKWA_PLUGIN_DIR . 'admin/templates/dashboard.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include MKWA_PLUGIN_DIR . 'admin/templates/settings.php';
    }
}