<?php
/**
 * Canonical blog ID map for the Extra Chill multisite network.
 * Single source of truth for hardcoded site IDs and domains.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Blog IDs.
if ( ! defined( 'EC_BLOG_ID_MAIN' ) ) {
    define( 'EC_BLOG_ID_MAIN', 1 );
}
if ( ! defined( 'EC_BLOG_ID_COMMUNITY' ) ) {
    define( 'EC_BLOG_ID_COMMUNITY', 2 );
}
if ( ! defined( 'EC_BLOG_ID_SHOP' ) ) {
    define( 'EC_BLOG_ID_SHOP', 3 );
}
if ( ! defined( 'EC_BLOG_ID_ARTIST' ) ) {
    define( 'EC_BLOG_ID_ARTIST', 4 );
}
if ( ! defined( 'EC_BLOG_ID_CHAT' ) ) {
    define( 'EC_BLOG_ID_CHAT', 5 );
}
if ( ! defined( 'EC_BLOG_ID_EVENTS' ) ) {
    define( 'EC_BLOG_ID_EVENTS', 7 );
}
if ( ! defined( 'EC_BLOG_ID_STREAM' ) ) {
    define( 'EC_BLOG_ID_STREAM', 8 );
}
if ( ! defined( 'EC_BLOG_ID_NEWSLETTER' ) ) {
    define( 'EC_BLOG_ID_NEWSLETTER', 9 );
}
if ( ! defined( 'EC_BLOG_ID_DOCS' ) ) {
    define( 'EC_BLOG_ID_DOCS', 10 );
}
if ( ! defined( 'EC_BLOG_ID_HOROSCOPE' ) ) {
    define( 'EC_BLOG_ID_HOROSCOPE', 11 );
}

/**
 * Return associative map of blog IDs keyed by logical slug.
 *
 * @return array
 */
function ec_get_blog_ids() {
    return array(
        'main'       => EC_BLOG_ID_MAIN,
        'community'  => EC_BLOG_ID_COMMUNITY,
        'shop'       => EC_BLOG_ID_SHOP,
        'artist'     => EC_BLOG_ID_ARTIST,
        'chat'       => EC_BLOG_ID_CHAT,
        'events'     => EC_BLOG_ID_EVENTS,
        'stream'     => EC_BLOG_ID_STREAM,
        'newsletter' => EC_BLOG_ID_NEWSLETTER,
        'docs'       => EC_BLOG_ID_DOCS,
        'horoscope'  => EC_BLOG_ID_HOROSCOPE,
    );
}

/**
 * Get a blog ID by logical key.
 *
 * @param string $key Logical site key, e.g. 'artist'.
 * @return int|null Blog ID or null if unknown.
 */
function ec_get_blog_id( $key ) {
    $map = ec_get_blog_ids();

    return isset( $map[ $key ] ) ? (int) $map[ $key ] : null;
}

/**
 * Map of domains to blog IDs for routing.
 * Includes extrachill.link mapping to artist site (blog ID 4).
 *
 * @return array
 */
function ec_get_domain_map() {
    return array(
        'extrachill.com'        => EC_BLOG_ID_MAIN,
        'community.extrachill.com' => EC_BLOG_ID_COMMUNITY,
        'shop.extrachill.com'   => EC_BLOG_ID_SHOP,
        'artist.extrachill.com' => EC_BLOG_ID_ARTIST,
        'chat.extrachill.com'   => EC_BLOG_ID_CHAT,
        'events.extrachill.com' => EC_BLOG_ID_EVENTS,
        'stream.extrachill.com' => EC_BLOG_ID_STREAM,
        'newsletter.extrachill.com' => EC_BLOG_ID_NEWSLETTER,
        'docs.extrachill.com'   => EC_BLOG_ID_DOCS,
        'horoscope.extrachill.com' => EC_BLOG_ID_HOROSCOPE,
        // Domain mapping for link pages.
        'extrachill.link'       => EC_BLOG_ID_ARTIST,
        'www.extrachill.link'   => EC_BLOG_ID_ARTIST,
    );
}

/**
 * Reverse lookup: get logical slug by blog ID.
 *
 * @param int $blog_id Blog ID to resolve.
 * @return string|null Slug or null if unknown.
 */
function ec_get_blog_slug_by_id( $blog_id ) {
    foreach ( ec_get_blog_ids() as $slug => $id ) {
        if ( (int) $blog_id === (int) $id ) {
            return $slug;
        }
    }

    return null;
}

/**
 * Get a site URL by logical key.
 *
 * @param string $key Logical site key, e.g. 'main'.
 * @return string|null Site URL or null if unknown.
 */
function ec_get_site_url( $key ) {
    $domain_map = ec_get_domain_map();
    $blog_id = ec_get_blog_id( $key );

    if ( $blog_id === null ) {
        return null;
    }

    // Allow override via filter (for dev environments, etc.)
    $override_url = apply_filters( 'ec_site_url_override', null, $key, $blog_id );
    if ( $override_url ) {
        return $override_url;
    }

    // Default: return production domains
    foreach ( $domain_map as $domain => $id ) {
        if ( (int) $id === (int) $blog_id ) {
            return 'https://' . $domain;
        }
    }

    return null;
}
