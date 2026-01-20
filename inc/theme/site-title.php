<?php
/**
 * Extra Chill Site Title Override
 *
 * Forces "Extra Chill" as site title across the network.
 *
 * @package ExtraChill\Multisite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'extrachill_site_title', 'extrachill_multisite_site_title' );

function extrachill_multisite_site_title( $title ) {
	return 'Extra Chill';
}
