<?php
/**
 * Asset Management for ExtraChill Multisite
 *
 * Handles CSS and JavaScript enqueuing for multisite plugin features.
 * Conditional loading based on site context with filemtime() cache busting.
 *
 * @package ExtraChillMultisite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue avatar menu assets on community and artist sites
 */
function extrachill_multisite_enqueue_avatar_menu_assets() {
    // Only load on community and artist sites where avatar menu is active
    $current_blog_id = get_current_blog_id();
    $community_blog_id = get_blog_id_from_url('community.extrachill.com', '/');
    $artist_blog_id = get_blog_id_from_url('artist.extrachill.com', '/');

    if (!in_array($current_blog_id, array($community_blog_id, $artist_blog_id))) {
        return;
    }

    // Enqueue avatar menu CSS
    $css_path = EXTRACHILL_MULTISITE_PLUGIN_DIR . 'inc/assets/css/avatar-menu.css';
    if (file_exists($css_path)) {
        wp_enqueue_style(
            'extrachill-multisite-avatar-menu',
            EXTRACHILL_MULTISITE_PLUGIN_URL . 'inc/assets/css/avatar-menu.css',
            array(),
            filemtime($css_path),
            'all'
        );
    }

    // Enqueue avatar menu JavaScript
    $js_path = EXTRACHILL_MULTISITE_PLUGIN_DIR . 'inc/assets/js/avatar-menu.js';
    if (file_exists($js_path)) {
        wp_enqueue_script(
            'extrachill-multisite-avatar-menu',
            EXTRACHILL_MULTISITE_PLUGIN_URL . 'inc/assets/js/avatar-menu.js',
            array(),
            filemtime($js_path),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'extrachill_multisite_enqueue_avatar_menu_assets');
