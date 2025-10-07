<?php
/**
 * Team Member Helper Functions
 *
 * Provides team member status checking with manual override support
 * and main site account verification across the multisite network.
 *
 * @package ExtraChill\Multisite
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check team member status with manual override support
 *
 * Checks manual override meta first, then falls back to standard team meta.
 * Supports both current user and specific user ID.
 *
 * @since 1.0.0
 * @param int $user_id User ID to check (0 = current user)
 * @return bool True if user is team member, false otherwise
 */
function ec_is_team_member($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    $manual_override = get_user_meta($user_id, 'extrachill_team_manual_override', true);

    if ($manual_override === 'add') {
        return true;
    }

    if ($manual_override === 'remove') {
        return false;
    }

    return get_user_meta($user_id, 'extrachill_team', true) == 1;
}

/**
 * Check if user has account on extrachill.com
 *
 * Uses domain-based site resolution to verify user membership on main site.
 *
 * @since 1.0.0
 * @param int $user_id User ID to check
 * @return bool True if user is member of main site, false otherwise
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