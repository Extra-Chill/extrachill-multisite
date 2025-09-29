<?php
/**
 * Network-wide admin access control restricting wp-admin to administrators.
 * Preserves AJAX functionality while blocking unauthorized admin access.
 */

function extrachill_redirect_admin() {
    if (!current_user_can('administrator') && is_admin() && !wp_doing_ajax()) {
        wp_safe_redirect(home_url('/'));
        exit();
    }
}
add_action('admin_init', 'extrachill_redirect_admin');

function extrachill_hide_admin_bar_for_non_admins() {
    if (!current_user_can('administrator')) {
        show_admin_bar(false);
    }
}
add_action('init', 'extrachill_hide_admin_bar_for_non_admins', 5);

function extrachill_prevent_admin_auth_redirect($redirect_to, $requested_redirect_to, $user) {
    if (isset($user->ID) && current_user_can('administrator', $user->ID)) {
        if (!empty($requested_redirect_to) && strpos($requested_redirect_to, '/wp-admin') !== false) {
            return $requested_redirect_to;
        }
        if (!empty($redirect_to) && strpos($redirect_to, '/wp-admin') !== false) {
            return $redirect_to;
        }
    }
    return $redirect_to;
}
add_filter('login_redirect', 'extrachill_prevent_admin_auth_redirect', 5, 3);