<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="mkwa-dashboard-wrapper">
        <!-- Statistics Overview -->
        <div class="mkwa-card">
            <h2><?php _e('Statistics Overview', 'mkwa-fitness'); ?></h2>
            <?php
            $total_users = count(get_users(array('role__in' => array('mkwa_member', 'mkwa_trainer'))));
            $total_activities = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mkwa_activity_log");
            $total_badges_awarded = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mkwa_user_badges");
            ?>
            <div class="mkwa-stats-grid">
                <div class="mkwa-stat-box">
                    <span class="mkwa-stat-number"><?php echo esc_html($total_users); ?></span>
                    <span class="mkwa-stat-label"><?php _e('Active Members', 'mkwa-fitness'); ?></span>
                </div>
                <div class="mkwa-stat-box">
                    <span class="mkwa-stat-number"><?php echo esc_html($total_activities); ?></span>
                    <span class="mkwa-stat-label"><?php _e('Activities Logged', 'mkwa-fitness'); ?></span>
                </div>
                <div class="mkwa-stat-box">
                    <span class="mkwa-stat-number"><?php echo esc_html($total_badges_awarded); ?></span>
                    <span class="mkwa-stat-label"><?php _e('Badges Awarded', 'mkwa-fitness'); ?></span>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="mkwa-card">
            <h2><?php _e('Recent Activities', 'mkwa-fitness'); ?></h2>
            <?php
            $recent_activities = $wpdb->get_results(
                "SELECT a.*, u.display_name 
                FROM {$wpdb->prefix}mkwa_activity_log a 
                JOIN {$wpdb->users} u ON a.user_id = u.ID 
                ORDER BY a.logged_at DESC 
                LIMIT 10"
            );
            ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('User', 'mkwa-fitness'); ?></th>
                        <th><?php _e('Activity', 'mkwa-fitness'); ?></th>
                        <th><?php _e('Points', 'mkwa-fitness'); ?></th>
                        <th><?php _e('Date', 'mkwa-fitness'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activities as $activity): ?>
                        <tr>
                            <td><?php echo esc_html($activity->display_name); ?></td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $activity->activity_type))); ?></td>
                            <td><?php echo esc_html($activity->points); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activity->logged_at))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Performers -->
        <div class="mkwa-card">
            <h2><?php _e('Top Performers', 'mkwa-fitness'); ?></h2>
            <?php
            $top_performers = $wpdb->get_results(
                "SELECT u.ID, u.display_name, 
                    CAST(um.meta_value AS UNSIGNED) as total_points,
                    CAST(um2.meta_value AS UNSIGNED) as current_streak
                FROM {$wpdb->users} u
                JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'mkwa_total_points'
                LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'mkwa_current_streak'
                ORDER BY total_points DESC
                LIMIT 5"
            );
            ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Rank', 'mkwa-fitness'); ?></th>
                        <th><?php _e('Member', 'mkwa-fitness'); ?></th>
                        <th><?php _e('Total Points', 'mkwa-fitness'); ?></th>
                        <th><?php _e('Current Streak', 'mkwa-fitness'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_performers as $index => $performer): ?>
                        <tr>
                            <td><?php echo esc_html($index + 1); ?></td>
                            <td><?php echo esc_html($performer->display_name); ?></td>
                            <td><?php echo esc_html($performer->total_points); ?></td>
                            <td><?php echo esc_html($performer->current_streak); ?> <?php _e('days', 'mkwa-fitness'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>