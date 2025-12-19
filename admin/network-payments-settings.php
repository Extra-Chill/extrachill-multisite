<?php
/**
 * ExtraChill Network Payments Settings
 *
 * Network admin page for configuring payment provider API keys.
 * Currently supports Stripe Connect for the artist marketplace.
 *
 * @package ExtraChill\Multisite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'network_admin_menu', 'ec_add_network_payments_menu' );

/**
 * Add payments settings page to network admin menu
 */
function ec_add_network_payments_menu() {
	add_submenu_page(
		EXTRACHILL_MULTISITE_MENU_SLUG,
		'Payments Settings',
		'Payments',
		'manage_network_options',
		'extrachill-payments',
		'ec_render_network_payments_page'
	);
}

add_action( 'network_admin_edit_extrachill_payments', 'ec_handle_network_payments_save' );

/**
 * Handle payments settings form submission
 */
function ec_handle_network_payments_save() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( __( 'You do not have permission to access this page.', 'extrachill-multisite' ) );
	}

	check_admin_referer( 'ec_payments_settings', 'ec_payments_nonce' );

	$secret_key      = isset( $_POST['ec_stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['ec_stripe_secret_key'] ) ) : '';
	$publishable_key = isset( $_POST['ec_stripe_publishable_key'] ) ? sanitize_text_field( wp_unslash( $_POST['ec_stripe_publishable_key'] ) ) : '';
	$webhook_secret  = isset( $_POST['ec_stripe_webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['ec_stripe_webhook_secret'] ) ) : '';

	update_site_option( 'extrachill_stripe_secret_key', $secret_key );
	update_site_option( 'extrachill_stripe_publishable_key', $publishable_key );
	update_site_option( 'extrachill_stripe_webhook_secret', $webhook_secret );

	$redirect_url = add_query_arg(
		array(
			'page'    => 'extrachill-payments',
			'updated' => 'true',
		),
		network_admin_url( 'admin.php' )
	);

	wp_redirect( $redirect_url );
	exit;
}

/**
 * Render network payments settings page
 */
function ec_render_network_payments_page() {
	$secret_key      = get_site_option( 'extrachill_stripe_secret_key', '' );
	$publishable_key = get_site_option( 'extrachill_stripe_publishable_key', '' );
	$webhook_secret  = get_site_option( 'extrachill_stripe_webhook_secret', '' );
	$is_configured   = ! empty( $secret_key ) && ! empty( $publishable_key );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Extra Chill Payments Settings', 'extrachill-multisite' ); ?></h1>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Payments settings updated successfully.', 'extrachill-multisite' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="edit.php?action=extrachill_payments">
			<?php wp_nonce_field( 'ec_payments_settings', 'ec_payments_nonce' ); ?>

			<table class="form-table">
				<tbody>
					<tr>
						<th colspan="2">
							<h2><?php esc_html_e( 'Stripe Connect Configuration', 'extrachill-multisite' ); ?></h2>
							<p class="description">
								<?php esc_html_e( 'Configure Stripe Connect for the artist marketplace payment processing.', 'extrachill-multisite' ); ?>
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
							<label for="ec_stripe_secret_key"><?php esc_html_e( 'Secret Key', 'extrachill-multisite' ); ?></label>
						</th>
						<td>
							<input type="password"
								   id="ec_stripe_secret_key"
								   name="ec_stripe_secret_key"
								   value="<?php echo esc_attr( $secret_key ); ?>"
								   class="regular-text"
								   placeholder="sk_live_..." />
							<p class="description">
								<?php esc_html_e( 'Your Stripe secret API key. Keep this confidential.', 'extrachill-multisite' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ec_stripe_publishable_key"><?php esc_html_e( 'Publishable Key', 'extrachill-multisite' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="ec_stripe_publishable_key"
								   name="ec_stripe_publishable_key"
								   value="<?php echo esc_attr( $publishable_key ); ?>"
								   class="regular-text"
								   placeholder="pk_live_..." />
							<p class="description">
								<?php esc_html_e( 'Your Stripe publishable API key. Used for frontend integration.', 'extrachill-multisite' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ec_stripe_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'extrachill-multisite' ); ?></label>
						</th>
						<td>
							<input type="password"
								   id="ec_stripe_webhook_secret"
								   name="ec_stripe_webhook_secret"
								   value="<?php echo esc_attr( $webhook_secret ); ?>"
								   class="regular-text"
								   placeholder="whsec_..." />
							<p class="description">
								<?php esc_html_e( 'Webhook signing secret for verifying Stripe webhook events.', 'extrachill-multisite' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Payments Settings', 'extrachill-multisite' ) ); ?>
		</form>

		<div class="card" style="margin-top: 20px; max-width: 800px;">
			<h3><?php esc_html_e( 'Setup Instructions', 'extrachill-multisite' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Create a Stripe account at stripe.com', 'extrachill-multisite' ); ?></li>
				<li><?php esc_html_e( 'Enable Stripe Connect in your dashboard', 'extrachill-multisite' ); ?></li>
				<li><?php esc_html_e( 'Navigate to Developers > API keys', 'extrachill-multisite' ); ?></li>
				<li><?php esc_html_e( 'Copy your Secret Key and Publishable Key', 'extrachill-multisite' ); ?></li>
				<li><?php esc_html_e( 'Create a webhook endpoint pointing to your site', 'extrachill-multisite' ); ?></li>
				<li><?php esc_html_e( 'Copy the Webhook Signing Secret', 'extrachill-multisite' ); ?></li>
			</ol>
			<p>
				<strong><?php esc_html_e( 'Webhook URL:', 'extrachill-multisite' ); ?></strong>
				<code><?php echo esc_url( rest_url( 'extrachill/v1/shop/stripe-webhook' ) ); ?></code>
			</p>
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
