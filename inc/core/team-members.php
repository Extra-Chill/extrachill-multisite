<?php
/**
 * Team Member Helper Functions - Manual override and extrachill_team meta checking
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check team member status with manual override support
 */
function ec_is_team_member($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    // Check manual override first
    $manual_override = get_user_meta($user_id, 'extrachill_team_manual_override', true);

    if ($manual_override === 'add') {
        return true;
    }

    if ($manual_override === 'remove') {
        return false;
    }

    // No override - check standard meta
    return get_user_meta($user_id, 'extrachill_team', true) == 1;
}

/**
 * Check if user has account on extrachill.com via domain-based site resolution
 */
function ec_has_main_site_account($user_id) {
    if (!$user_id) {
        return false;
    }

    $has_account = false;

    try {
        $main_site_id = get_blog_id_from_url( 'extrachill.com', '/' );
        switch_to_blog( $main_site_id );
        $has_account = is_user_member_of_blog( $user_id, $main_site_id );
    } finally {
        restore_current_blog();
    }

    return $has_account;
}