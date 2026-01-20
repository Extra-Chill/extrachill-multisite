<?php
/**
 * ExtraChill Network Shipping Settings
 *
 * Network admin page for configuring Shippo API key for shipping label generation.
 *
 * @package ExtraChill\Multisite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'network_admin_menu', 'ec_add_network_shipping_menu' );

/**
 * Add shipping settings page to network admin menu
 */
function ec_add_network_shipping_menu() {
	add_submenu_page(
		EXTRACHILL_MULTISITE_MENU_SLUG,
		'Shipping Settings',
		'Shipping',
		'manage_network_options',
		'extrachill-shipping',
		'ec_render_network_shipping_page'
	);
}

add_action( 'network_admin_edit_extrachill_shipping', 'ec_handle_network_shipping_save' );

/**
 * Handle shipping settings form submission
 */
function ec_handle_network_shipping_save() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'extrachill-multisite' ) );
	}

	check_admin_referer( 'ec_shipping_settings', 'ec_shipping_nonce' );

	$api_key = isset( $_POST['ec_shippo_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['ec_shippo_api_key'] ) ) : '';

	update_site_option( 'extrachill_shippo_api_key', $api_key );

	$redirect_url = add_query_arg(
		array(
			'page'    => 'extrachill-shipping',
			'updated' => 'true',
		),
		network_admin_url( 'admin.php' )
	);

	wp_safe_redirect( $redirect_url );
	exit;
}

/**
 * Render network shipping settings page
 */
function ec_render_network_shipping_page() {
	$api_key       = get_site_option( 'extrachill_shippo_api_key', '' );
	$is_configured = ! empty( $api_key );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Extra Chill Shipping Settings', 'extrachill-multisite' ); ?></h1>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Shipping settings updated successfully.', 'extrachill-multisite' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="edit.php?action=extrachill_shipping">
			<?php wp_nonce_field( 'ec_shipping_settings', 'ec_shipping_nonce' ); ?>

			<table class="form-table">
				<tbody>
					<tr>
						<th colspan="2">
							<h2><?php esc_html_e( 'Shippo Configuration', 'extrachill-multisite' ); ?></h2>
							<p class="description">
								<?php esc_html_e( 'Configure Shippo for artist shipping label generation.', 'extrachill-multisite' ); ?>
								<?php if ( $is_configured ) : ?>
									<span style="color: #46b450; font-weight: bold;">&#10003; <?php esc_html_e( 'Currently configured', 'extrachill-multisite' ); ?></span>
								<?php else : ?>
									<span style="color: #dc3232; font-weight: bold;">&#9888; <?php esc_html_e( 'Not configured', 'extrachill-multisite' ); ?></span>
								<?php endif; ?>
							</p>
						</th>
					</tr>
					<tr>
						<th scope="row">
							<label for="ec_shippo_api_key"><?php esc_html_e( 'Shippo API Key', 'extrachill-multisite' ); ?></label>
						</th>
						<td>
							<input type="password"
									id="ec_shippo_api_key"
									name="ec_shippo_api_key"
									value="<?php echo esc_attr( $api_key ); ?>"
									class="regular-text"
									placeholder="shippo_live_..." />
							<p class="description">
								<?php esc_html_e( 'Your Shippo API token. Keep this confidential.', 'extrachill-multisite' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Shipping Settings', 'extrachill-multisite' ) ); ?>
		</form>

		<div class="card" style="margin-top: 20px; max-width: 800px;">
			<h3><?php esc_html_e( 'Setup Instructions', 'extrachill-multisite' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Create a Shippo account at goshippo.com', 'extrachill-multisite' ); ?></li>
				<li><?php esc_html_e( 'Navigate to Settings > API in your Shippo dashboard', 'extrachill-multisite' ); ?></li>
				<li><?php esc_html_e( 'Copy your Live API Token', 'extrachill-multisite' ); ?></li>
				<li><?php esc_html_e( 'Paste the token above and save', 'extrachill-multisite' ); ?></li>
			</ol>
		</div>

		<div class="card" style="max-width: 800px;">
			<h3><?php esc_html_e( 'Shipping Configuration', 'extrachill-multisite' ); ?></h3>
			<table class="widefat" style="max-width: 400px;">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Carrier', 'extrachill-multisite' ); ?></strong></td>
						<td>USPS</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Rate Selection', 'extrachill-multisite' ); ?></strong></td>
						<td><?php esc_html_e( 'Auto-select cheapest', 'extrachill-multisite' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Customer Rate', 'extrachill-multisite' ); ?></strong></td>
						<td>$5 per artist</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Default Parcel', 'extrachill-multisite' ); ?></strong></td>
						<td>10" × 8" × 4", 1 lb</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Geographic Scope', 'extrachill-multisite' ); ?></strong></td>
						<td><?php esc_html_e( 'US Domestic Only', 'extrachill-multisite' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<style>
		.card {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			padding: 20px;
			margin: 20px 0;
		}
		.card h3 {
			margin-top: 0;
		}
	</style>
	<?php
}
