<?php
/**
 * ExtraChill Network Security Settings
 *
 * Network admin page for configuring security settings across the multisite network,
 * including Cloudflare Turnstile configuration.
 *
 * @package ExtraChill\Multisite
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'network_admin_menu', 'ec_add_network_security_menu' );

/**
 * Add security settings page to network admin menu
 *
 * @since 1.0.0
 */
function ec_add_network_security_menu() {
    add_submenu_page(
        'extrachill-multisite',
        'ExtraChill Security',
        'ExtraChill Security',
        'manage_network_options',
        'extrachill-security',
        'ec_render_network_security_page'
    );
}

add_action( 'network_admin_edit_extrachill_security', 'ec_handle_network_security_save' );

/**
 * Handle security settings form submission
 *
 * @since 1.0.0
 */
function ec_handle_network_security_save() {
    if ( ! current_user_can( 'manage_network_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'extrachill-multisite' ) );
    }

    if ( ! wp_verify_nonce( $_POST['ec_security_nonce'], 'ec_security_settings' ) ) {
        wp_die( __( 'Security check failed.', 'extrachill-multisite' ) );
    }

    // Save Turnstile settings
    $site_key = isset( $_POST['ec_turnstile_site_key'] ) ? sanitize_text_field( $_POST['ec_turnstile_site_key'] ) : '';
    $secret_key = isset( $_POST['ec_turnstile_secret_key'] ) ? sanitize_text_field( $_POST['ec_turnstile_secret_key'] ) : '';

    ec_update_turnstile_site_key( $site_key );
    ec_update_turnstile_secret_key( $secret_key );

    // Redirect back with success message
    $redirect_url = add_query_arg(
        array(
            'page' => 'extrachill-security',
            'updated' => 'true',
        ),
        network_admin_url( 'admin.php' )
    );

    wp_redirect( $redirect_url );
    exit;
}

/**
 * Render network security settings page
 *
 * @since 1.0.0
 */
function ec_render_network_security_page() {
    $site_key = ec_get_turnstile_site_key();
    $secret_key = ec_get_turnstile_secret_key();
    $is_configured = ec_is_turnstile_configured();
    ?>
    <div class="wrap">
        <h1><?php _e( 'Extra Chill Security Settings', 'extrachill-multisite' ); ?></h1>

        <?php if ( isset( $_GET['updated'] ) ): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( 'Security settings updated successfully.', 'extrachill-multisite' ); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="edit.php?action=extrachill_security">
            <?php wp_nonce_field( 'ec_security_settings', 'ec_security_nonce' ); ?>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th colspan="2">
                            <h2><?php _e( 'Cloudflare Turnstile Configuration', 'extrachill-multisite' ); ?></h2>
                            <p class="description">
                                <?php _e( 'Configure Cloudflare Turnstile for spam protection across all sites in the network.', 'extrachill-multisite' ); ?>
                                <?php if ( $is_configured ): ?>
                                    <span style="color: #46b450; font-weight: bold;">✓ <?php _e( 'Currently configured', 'extrachill-multisite' ); ?></span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-weight: bold;">⚠ <?php _e( 'Not configured', 'extrachill-multisite' ); ?></span>
                                <?php endif; ?>
                            </p>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ec_turnstile_site_key"><?php _e( 'Site Key', 'extrachill-multisite' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="ec_turnstile_site_key"
                                   name="ec_turnstile_site_key"
                                   value="<?php echo esc_attr( $site_key ); ?>"
                                   class="regular-text"
                                   placeholder="0x4AAAAAAAPvQsUv5Z6QBB5n" />
                            <p class="description">
                                <?php _e( 'The site key from your Cloudflare Turnstile dashboard. This will be used in forms across all sites.', 'extrachill-multisite' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ec_turnstile_secret_key"><?php _e( 'Secret Key', 'extrachill-multisite' ); ?></label>
                        </th>
                        <td>
                            <input type="password"
                                   id="ec_turnstile_secret_key"
                                   name="ec_turnstile_secret_key"
                                   value="<?php echo esc_attr( $secret_key ); ?>"
                                   class="regular-text"
                                   placeholder="0x4AAAAAAAPvQp7DbBfqJD7LW-gbrAkiAb0" />
                            <p class="description">
                                <?php _e( 'The secret key from your Cloudflare Turnstile dashboard. Used for server-side verification.', 'extrachill-multisite' ); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2><?php _e( 'Affected Forms', 'extrachill-multisite' ); ?></h2>
            <p class="description">
                <?php _e( 'These forms across your multisite network will use the Turnstile configuration:', 'extrachill-multisite' ); ?>
            </p>
            <ul style="margin-left: 20px;">
                <li><?php _e( 'Contact forms (extrachill-contact plugin)', 'extrachill-multisite' ); ?></li>
                <li><?php _e( 'User registration (extrachill-users plugin)', 'extrachill-multisite' ); ?></li>
                <li><?php _e( 'Newsletter subscriptions (extrachill-newsletter plugin)', 'extrachill-multisite' ); ?></li>
                <li><?php _e( 'Festival tip submissions (extrachill-news-wire plugin)', 'extrachill-multisite' ); ?></li>
            </ul>

            <?php submit_button( __( 'Save Security Settings', 'extrachill-multisite' ) ); ?>
        </form>

        <div class="card" style="margin-top: 20px;">
            <h3><?php _e( 'Setup Instructions', 'extrachill-multisite' ); ?></h3>
            <ol>
                <li><?php _e( 'Create a Cloudflare account and add your domain', 'extrachill-multisite' ); ?></li>
                <li><?php _e( 'Navigate to Security → Turnstile in your Cloudflare dashboard', 'extrachill-multisite' ); ?></li>
                <li><?php _e( 'Create a new widget for your domain', 'extrachill-multisite' ); ?></li>
                <li><?php _e( 'Copy the Site Key and Secret Key from the widget settings', 'extrachill-multisite' ); ?></li>
                <li><?php _e( 'Paste the keys in the fields above and save', 'extrachill-multisite' ); ?></li>
            </ol>
            <p><strong><?php _e( 'Note:', 'extrachill-multisite' ); ?></strong> <?php _e( 'These settings apply to all sites in your multisite network.', 'extrachill-multisite' ); ?></p>
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