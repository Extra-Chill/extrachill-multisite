<?php
/**
 * Ad-Free License Validation Functions
 *
 * Handles ad-free license validation via WordPress multisite cross-site database lookup.
 * Uses switch_to_blog(3) to check shop site's license database from main site.
 *
 * @package ExtraChill
 * @since 69.57
 */

/**
 * Uses switch_to_blog(3) to check shop site's ad-free license table
 * following WordPress multisite cross-site data access pattern.
 */
function is_user_ad_free($userDetails = null) {
    if (!is_user_logged_in()) {
        return false;
    }

    if (!$userDetails) {
        $user = wp_get_current_user();
        $username = $user->user_nicename;
    } else {
        $username = $userDetails['username'];
    }

    if (empty($username)) {
        return false;
    }

    switch_to_blog(3);

    global $wpdb;
    $table = $wpdb->prefix . 'extrachill_ad_free';

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

    if (!$table_exists) {
        restore_current_blog();
        return false;
    }

    $has_license = (bool) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE username = %s", sanitize_text_field($username))
    );

    restore_current_blog();

    return $has_license;
}
