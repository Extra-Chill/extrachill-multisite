<?php
/**
 * Ad-Free License Validation
 *
 * Cross-site license checking from shop.extrachill.com database.
 * Validates user ad-free status via direct database lookup.
 *
 * @package ExtraChill\Multisite
 * @since 1.0.0
 */

/**
 * Check if user has ad-free license
 *
 * Queries shop site database for ad-free license using domain-based resolution.
 *
 * @since 1.0.0
 * @param array|null $userDetails Optional user details array with 'username' key
 * @return bool True if user has ad-free license, false otherwise
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

    switch_to_blog( get_blog_id_from_url( 'shop.extrachill.com', '/' ) );

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
