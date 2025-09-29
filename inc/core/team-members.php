<?php
/**
 * Team Member Helper Functions
 *
 * Centralized helper functions for checking team member status across the multisite network.
 *
 * @package ExtraChillMultisite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if user is a team member
 *
 * Checks manual override first, then falls back to extrachill_team meta.
 *
 * @param int $user_id User ID to check (default: current user)
 * @return bool True if team member, false otherwise
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
 * Check if user has account on main site (extrachill.com)
 *
 * @param int $user_id User ID to check
 * @return bool True if user has main site account, false otherwise
 */
function ec_has_main_site_account($user_id) {
    if (!$user_id) {
        return false;
    }

    $main_site_id = 1; // extrachill.com
    $has_account = false;

    try {
        switch_to_blog($main_site_id);
        $has_account = is_user_member_of_blog($user_id, $main_site_id);
    } finally {
        restore_current_blog();
    }

    return $has_account;
}