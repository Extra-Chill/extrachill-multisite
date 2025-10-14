<?php
/**
 * Newsletter Sendy API Integration
 *
 * Centralized subscription bridge for extrachill-newsletter plugin integration system.
 *
 * @package ExtraChill\Multisite
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralized newsletter subscription bridge for integration system.
 *
 * @param string $email   Email address.
 * @param string $context Integration key (e.g., 'registration').
 * @return array Response with success boolean and message string.
 */
function extrachill_multisite_subscribe( $email, $context ) {
	if ( ! function_exists( 'get_newsletter_integrations' ) || ! function_exists( 'get_sendy_config' ) || ! function_exists( 'newsletter_integration_enabled' ) ) {
		return array(
			'success' => false,
			'message' => __( 'Newsletter system not available', 'extrachill-multisite' ),
		);
	}

	$integrations = get_newsletter_integrations();

	if ( ! isset( $integrations[ $context ] ) ) {
		return array(
			'success' => false,
			'message' => __( 'Newsletter integration not found', 'extrachill-multisite' ),
		);
	}

	$integration = $integrations[ $context ];

	if ( ! newsletter_integration_enabled( $integration['enable_key'] ) ) {
		return array(
			'success' => false,
			'message' => __( 'Newsletter integration is disabled', 'extrachill-multisite' ),
		);
	}

	$settings = get_site_option( 'extrachill_newsletter_settings', array() );
	$list_id = isset( $settings[ $integration['list_id_key'] ] ) ? $settings[ $integration['list_id_key'] ] : '';

	if ( empty( $list_id ) ) {
		return array(
			'success' => false,
			'message' => __( 'Newsletter list not configured for this integration', 'extrachill-multisite' ),
		);
	}

	$config = get_sendy_config();

	if ( ! is_email( $email ) ) {
		return array(
			'success' => false,
			'message' => __( 'Invalid email address', 'extrachill-multisite' ),
		);
	}

	$subscription_data = array(
		'email' => $email,
		'list' => $list_id,
		'boolean' => 'true',
		'api_key' => $config['api_key'],
	);

	$response = wp_remote_post(
		$config['sendy_url'] . '/subscribe',
		array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body' => $subscription_data,
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		error_log( 'Newsletter integration subscription failed: ' . $response->get_error_message() );
		return array(
			'success' => false,
			'message' => __( 'Subscription service unavailable', 'extrachill-multisite' ),
		);
	}

	$response_body = wp_remote_retrieve_body( $response );

	if ( $response_body === '1' || strpos( $response_body, 'Success' ) !== false ) {
		return array(
			'success' => true,
			'message' => __( 'Successfully subscribed to newsletter', 'extrachill-multisite' ),
		);
	} else {
		error_log( sprintf( 'Newsletter integration subscription failed for %s via %s: %s', $email, $context, $response_body ) );

		if ( strpos( $response_body, 'Already subscribed' ) !== false ) {
			return array(
				'success' => false,
				'message' => __( 'Email already subscribed', 'extrachill-multisite' ),
			);
		} elseif ( strpos( $response_body, 'Invalid' ) !== false ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid email address', 'extrachill-multisite' ),
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Subscription failed, please try again', 'extrachill-multisite' ),
			);
		}
	}
}
