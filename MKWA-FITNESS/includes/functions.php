<?php
/**
 * Helper functions for MKWA Fitness Plugin
 * 
 * @package MkwaFitness
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get member ID from user ID
 */
function mkwa_get_member_id($user_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT member_id FROM {$wpdb->prefix}mkwa_members WHERE user_id = %d",
        $user_id
    ));
}

/**
 * Get user ID from member ID
 */
function mkwa_get_user_id($member_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}mkwa_members WHERE member_id = %d",
        $member_id
    ));
}

/**
 * Calculate level from points
 */
function mkwa_calculate_level($points) {
    return min(
        MKWA_MAX_LEVEL,
        floor(sqrt($points / MKWA_LEVEL_BASE_POINTS)) + 1
    );
}

/**
 * Get points needed for next level
 */
function mkwa_points_for_next_level($current_level) {
    return pow($current_level, 2) * MKWA_LEVEL_BASE_POINTS;
}

/**
 * Format points number
 */
function mkwa_format_points($points) {
    if ($points >= 1000000) {
        return round($points / 1000000, 1) . 'M';
    } elseif ($points >= 1000) {
        return round($points / 1000, 1) . 'K';
    }
    return number_format($points);
}

/**
 * Get activity type label
 */
function mkwa_get_activity_label($type) {
    $labels = array(
        MKWA_ACTIVITY_CHECKIN => __('Check-in', 'mkwa-fitness'),
        MKWA_ACTIVITY_CLASS => __('Class', 'mkwa-fitness'),
        MKWA_ACTIVITY_COLD_PLUNGE => __('Cold Plunge', 'mkwa-fitness'),
        MKWA_ACTIVITY_PR => __('Personal Record', 'mkwa-fitness'),
        MKWA_ACTIVITY_COMPETITION => __('Competition', 'mkwa-fitness')
    );
    
    return isset($labels[$type]) ? $labels[$type] : $type;
}

/**
 * Check if user is member
 */
function mkwa_is_member($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    return (bool)mkwa_get_member_id($user_id);
}

/**
 * Create member if not exists
 */
function mkwa_ensure_member($user_id) {
    if (!mkwa_is_member($user_id)) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'mkwa_members',
            array(
                'user_id' => $user_id,
                'current_level' => 1,
                'total_points' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s')
        );
        return $wpdb->insert_id;
    }
    return mkwa_get_member_id($user_id);
}

/**
 * Get member stats
 */
function mkwa_get_member_stats($member_id) {
    $activities = new MKWA_Activities();
    return $activities->get_member_stats($member_id);
}

/**
 * Format duration
 */
function mkwa_format_duration($seconds) {
    if ($seconds < 60) {
        return sprintf(_n('%d second', '%d seconds', $seconds, 'mkwa-fitness'), $seconds);
    }
    
    $minutes = floor($seconds / 60);
    if ($minutes < 60) {
        return sprintf(_n('%d minute', '%d minutes', $minutes, 'mkwa-fitness'), $minutes);
    }
    
    $hours = floor($minutes / 60);
    $remaining_minutes = $minutes % 60;
    
    if ($remaining_minutes == 0) {
        return sprintf(_n('%d hour', '%d hours', $hours, 'mkwa-fitness'), $hours);
    }
    
    return sprintf(
        __('%d hours %d minutes', 'mkwa-fitness'),
        $hours,
        $remaining_minutes
    );
}