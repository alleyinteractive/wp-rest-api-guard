<?php
/**
 * Plugin Settings
 *
 * @package rest-api-guard
 */

namespace Alley\WP\REST_API_Guard;

use Firebase\JWT\JWT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', __NAMESPACE__ . '\on_admin_menu' );
add_action( 'admin_init', __NAMESPACE__ . '\on_admin_init' );
add_action( 'admin_post_rest_api_guard_generate_jwt', __NAMESPACE__ . '\handle_generate_jwt' );
add_action( 'admin_post_rest_api_guard_revoke_jwt', __NAMESPACE__ . '\handle_revoke_jwt' );

/**
 * Slug for the settings.
 *
 * @var string
 */
const SETTINGS_KEY = 'rest_api_guard';

/**
 * Register the Admin Settings page.
 */
function on_admin_menu() {
	/**
	 * Filter to disable the admin settings page.
	 *
	 * @param bool $disable Whether to disable the admin settings page.
	 */
	if ( true === apply_filters( 'rest_api_guard_disable_admin_settings', false ) ) {
		return;
	}

	add_options_page(
		__( 'REST API Guard', 'rest-api-guard' ),
		__( 'REST API Guard', 'rest-api-guard' ),
		'manage_options',
		SETTINGS_KEY,
		__NAMESPACE__ . '\render_admin_page',
	);
}

/**
 * Render the admin settings.
 */
function render_admin_page() {
	?>
	<div class="wrap">
		<h2>
			<?php esc_html_e( 'REST API Guard', 'rest-api-guard' ); ?>
		</h2>

		<?php settings_errors(); ?>

		<form method="post" action="options.php">
			<?php
				settings_fields( SETTINGS_KEY );
				do_settings_sections( SETTINGS_KEY );
				submit_button();
			?>
		</form>

		<?php
		if ( class_exists( JWT::class ) ) {
			render_tokens_section();
		}
		?>
	</div>
	<?php
}

/**
 * Render the section to generate, list, and revoke tokens.
 */
function render_tokens_section() {
	$notice = sanitize_key( $_GET['rest_api_guard_notice'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tokens = get_tokens();

	uasort( $tokens, fn ( $a, $b ) => $b['issued_at'] <=> $a['issued_at'] );
	?>
	<hr />
	<h2><?php esc_html_e( 'Tokens', 'rest-api-guard' ); ?></h2>
	<p><?php esc_html_e( 'JSON Web Tokens (JWTs) issued by the plugin. Revoking a token prevents it from being used again.', 'rest-api-guard' ); ?></p>

	<?php
	if ( 'generated' === $notice ) {
		$token = get_transient( 'rest_api_guard_new_jwt_' . get_current_user_id() );
		delete_transient( 'rest_api_guard_new_jwt_' . get_current_user_id() );

		if ( $token ) {
			printf(
				'<div class="notice notice-success"><p>%1$s</p><p><input type="text" class="large-text code" readonly value="%2$s" onfocus="this.select();" /></p></div>',
				esc_html__( 'Token generated. Copy it now, it will not be shown again.', 'rest-api-guard' ),
				esc_attr( $token ),
			);
		}
	} elseif ( 'revoked' === $notice ) {
		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html__( 'Token revoked.', 'rest-api-guard' ) );
	} elseif ( 'invalid_user' === $notice ) {
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'The user could not be found.', 'rest-api-guard' ) );
	}
	?>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'rest-api-guard' ); ?></th>
				<th><?php esc_html_e( 'User', 'rest-api-guard' ); ?></th>
				<th><?php esc_html_e( 'Issued', 'rest-api-guard' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'rest-api-guard' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $tokens ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No tokens have been issued.', 'rest-api-guard' ); ?></td>
				</tr>
			<?php endif; ?>

			<?php foreach ( $tokens as $jti => $token ) : ?>
				<?php $user = $token['user_id'] ? get_user_by( 'id', $token['user_id'] ) : null; ?>
				<tr>
					<td><?php echo esc_html( '' !== $token['name'] ? $token['name'] : $jti ); ?></td>
					<td>
						<?php
						if ( $user ) {
							echo esc_html( $user->user_login );
						} elseif ( $token['user_id'] ) {
							/* translators: %d: The user ID. */
							echo esc_html( sprintf( __( 'Deleted user #%d', 'rest-api-guard' ), $token['user_id'] ) );
						} else {
							esc_html_e( 'Anonymous', 'rest-api-guard' );
						}
						?>
					</td>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $token['issued_at'] ) ); ?></td>
					<td>
						<?php
						if ( null === $token['expires_at'] ) {
							esc_html_e( 'Never', 'rest-api-guard' );
						} elseif ( $token['expires_at'] < time() ) {
							esc_html_e( 'Expired', 'rest-api-guard' );
						} else {
							echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $token['expires_at'] ) );
						}
						?>
					</td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="rest_api_guard_revoke_jwt" />
							<input type="hidden" name="jti" value="<?php echo esc_attr( $jti ); ?>" />
							<?php wp_nonce_field( 'rest_api_guard_revoke_jwt' ); ?>
							<?php submit_button( __( 'Revoke', 'rest-api-guard' ), 'delete small', 'submit', false ); ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Generate Token', 'rest-api-guard' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="rest_api_guard_generate_jwt" />
		<?php wp_nonce_field( 'rest_api_guard_generate_jwt' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rest-api-guard-jwt-name"><?php esc_html_e( 'Name', 'rest-api-guard' ); ?></label></th>
				<td><input type="text" class="regular-text" name="name" id="rest-api-guard-jwt-name" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rest-api-guard-jwt-user"><?php esc_html_e( 'User', 'rest-api-guard' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="user" id="rest-api-guard-jwt-user" />
					<p class="description"><?php esc_html_e( 'Optional user ID or login. Leave empty for an anonymous token. User tokens require "Allow User Authentication with JSON Web Token" to be enabled.', 'rest-api-guard' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rest-api-guard-jwt-expiration"><?php esc_html_e( 'Expiration (days)', 'rest-api-guard' ); ?></label></th>
				<td>
					<input type="number" class="small-text" min="1" name="expiration" id="rest-api-guard-jwt-expiration" />
					<p class="description"><?php esc_html_e( 'Leave empty for a token that never expires.', 'rest-api-guard' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Generate Token', 'rest-api-guard' ), 'secondary' ); ?>
	</form>
	<?php
}

/**
 * Generate a token from the settings page.
 */
function handle_generate_jwt() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage tokens.', 'rest-api-guard' ), 403 );
	}

	check_admin_referer( 'rest_api_guard_generate_jwt' );

	$user       = null;
	$user_input = sanitize_text_field( wp_unslash( $_POST['user'] ?? '' ) );
	$expiration = absint( $_POST['expiration'] ?? 0 );

	if ( '' !== $user_input ) {
		$user = get_user_by( is_numeric( $user_input ) ? 'id' : 'login', $user_input );

		if ( ! $user ) {
			redirect_to_admin_page( 'invalid_user' );
		}
	}

	$token = generate_jwt(
		expiration: $expiration ? $expiration * DAY_IN_SECONDS : null,
		user: $user ? $user : null,
		name: sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
	);

	set_transient( 'rest_api_guard_new_jwt_' . get_current_user_id(), $token, MINUTE_IN_SECONDS );

	redirect_to_admin_page( 'generated' );
}

/**
 * Revoke a token from the settings page.
 */
function handle_revoke_jwt() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage tokens.', 'rest-api-guard' ), 403 );
	}

	check_admin_referer( 'rest_api_guard_revoke_jwt' );

	revoke_token( sanitize_text_field( wp_unslash( $_POST['jti'] ?? '' ) ) );

	redirect_to_admin_page( 'revoked' );
}

/**
 * Redirect back to the settings page with a notice.
 *
 * @param string $notice The notice to display.
 */
function redirect_to_admin_page( string $notice ): never {
	wp_safe_redirect(
		add_query_arg(
			[
				'page'                  => SETTINGS_KEY,
				'rest_api_guard_notice' => $notice,
			],
			admin_url( 'options-general.php' ),
		),
	);
	exit;
}

/**
 * Register the admin settings.
 */
function on_admin_init() {
	register_setting(
		SETTINGS_KEY,
		SETTINGS_KEY,
		[
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_settings',
			'show_in_rest'      => false,
			'type'              => 'array',
		],
	);

	add_settings_section(
		SETTINGS_KEY,
		__( 'Settings', 'rest-api-guard' ),
		'__return_empty_string',
		SETTINGS_KEY,
	);

	add_settings_field(
		'prevent_anonymous_access',
		__( 'Prevent Anonymous Access', 'rest-api-guard' ),
		__NAMESPACE__ . '\render_field',
		SETTINGS_KEY,
		SETTINGS_KEY,
		[
			'description' => __( 'Prevent any anonyous access to the REST API.', 'rest-api-guard' ),
			'filter'      => 'rest_api_guard_prevent_anonymous_access',
			'id'          => 'prevent_anonymous_access',
			'type'        => 'checkbox',
		],
	);

	add_settings_field(
		'allow_index_access',
		__( 'Allow Index Access', 'rest-api-guard' ),
		__NAMESPACE__ . '\render_field',
		SETTINGS_KEY,
		SETTINGS_KEY,
		[
			'description' => __( 'Allow access to the REST API Index (/wp-json/).', 'rest-api-guard' ),
			'filter'      => 'rest_api_guard_allow_index_access',
			'id'          => 'allow_index_access',
			'type'        => 'checkbox',
		],
	);

	add_settings_field(
		'allow_namespace_access',
		__( 'Allow Namespace Access', 'rest-api-guard' ),
		__NAMESPACE__ . '\render_field',
		SETTINGS_KEY,
		SETTINGS_KEY,
		[
			'description' => __( 'Allow access to the REST API Namespaces (/wp-json/wp/v2/).', 'rest-api-guard' ),
			'filter'      => 'rest_api_guard_allow_namespace_access',
			'id'          => 'allow_namespace_access',
			'type'        => 'checkbox',
		],
	);

	add_settings_field(
		'allow_user_access',
		__( 'Allow User Access', 'rest-api-guard' ),
		__NAMESPACE__ . '\render_field',
		SETTINGS_KEY,
		SETTINGS_KEY,
		[
			'description' => __( 'Allow access to the users endpoint (/wp-json/wp/v2/users/).', 'rest-api-guard' ),
			'filter'      => 'rest_api_guard_allow_user_access',
			'id'          => 'allow_user_access',
			'type'        => 'checkbox',
		],
	);

	add_settings_field(
		'check_options_requests',
		__( 'Apply checks to OPTIONS requests', 'rest-api-guard' ),
		__NAMESPACE__ . '\render_field',
		SETTINGS_KEY,
		SETTINGS_KEY,
		[
			'description' => __( 'Apply the same checks to OPTIONS requests as other requests.', 'rest-api-guard' ),
			'additional'  => __( 'By default, the plugin will not apply any checks to OPTIONS requests. This setting will force the plugin to apply the same checks to OPTIONS requests as other requests. For CORS requests, this may need to be disabled to allow authentication with a JWT.', 'rest-api-guard' ),
			'filter'      => 'rest_api_guard_check_options_requests',
			'id'          => 'check_options_requests',
			'type'        => 'checkbox',
		],
	);

	add_settings_field(
		'anonymous_requests_allowlist',
		__( 'Anonymous Request Allowlist', 'rest-api-guard' ),
		__NAMESPACE__ . '\render_field',
		SETTINGS_KEY,
		SETTINGS_KEY,
		[
			'description' => __( 'Line-seperated allowlist for anonymous requests that should be allowed. All other requests not matching the list will be denied. This setting takes priority over the denylist below. Supports * as a wildcard.', 'rest-api-guard' ),
			'filter'      => 'rest_api_guard_anonymous_requests_allowlist',
			'id'          => 'anonymous_requests_allowlist',
			'type'        => 'textarea',
		],
	);

	add_settings_field(
		'anonymous_requests_denylist',
		__( 'Anonymous Request Denylist', 'rest-api-guard' ),
		__NAMESPACE__ . '\render_field',
		SETTINGS_KEY,
		SETTINGS_KEY,
		[
			'description' => __( 'Line-seperated denylist for anonymous requests that should be denied. All other requests not matching the list will be allowed. Supports * as a wildcard.', 'rest-api-guard' ),
			'filter'      => 'rest_api_guard_anonymous_requests_denylist',
			'id'          => 'anonymous_requests_denylist',
			'type'        => 'textarea',
		],
	);

	if ( class_exists( JWT::class ) ) {
		add_settings_field(
			'authentication_jwt',
			__( 'Require Authentication with JSON Web Token', 'rest-api-guard' ),
			__NAMESPACE__ . '\render_field',
			SETTINGS_KEY,
			SETTINGS_KEY,
			[
				'description' => __( 'Require authentication with a JSON Web Token (JWT) for all anonymous requests.', 'rest-api-guard' ),
				'additional'  => sprintf(
					/* translators: 1: The JWT audience. 2: The JWT issuer. */
					__( 'When enabled, the plugin will require anonymous users to pass an "Authorization: Bearer <token>" with the token being a valid JSON Web Token (JWT). The plugin will be expecting a JWT with an audience of "%1$s", issuer of "%2$s", and secret that matches the value of the "rest_api_guard_jwt_secret" option. When using the token, the user will have unrestricted read-only access to the REST API.', 'rest-api-guard' ),
					get_jwt_audience(),
					get_jwt_issuer(),
				),
				'filter'      => 'rest_api_guard_authentication_jwt',
				'id'          => 'authentication_jwt',
				'type'        => 'checkbox',
			],
		);

		add_settings_field(
			'user_authentication_jwt',
			__( 'Allow User Authentication with JSON Web Token', 'rest-api-guard' ),
			__NAMESPACE__ . '\render_field',
			SETTINGS_KEY,
			SETTINGS_KEY,
			[
				'description' => __( 'Allow user authentication with a JSON Web Token (JWT) for all requests.', 'rest-api-guard' ),
				'additional'  => sprintf(
					/* translators: 1: The JWT audience. 2: The JWT issuer. */
					__( 'When enabled, the plugin will allow JWTs to be generated against authenticated users. They can be passed as a "Authorization: Bearer <token>" with the token being a valid JSON Web Token (JWT). The plugin will be expecting a JWT with an audience of "%1$s", issuer of "%2$s", and secret that matches the value of the "rest_api_guard_jwt_secret" option. When using the token, the user will have unrestricted access to the REST API mirroring whatever permissions the user associated with the token would have.', 'rest-api-guard' ),
					get_jwt_audience(),
					get_jwt_issuer(),
				),
				'filter'      => 'rest_api_guard_user_authentication_jwt',
				'id'          => 'user_authentication_jwt',
				'type'        => 'checkbox',
			],
		);
	}
}

/**
 * Sanitize the settings before saving.
 *
 * @param array $input The settings to sanitize.
 * @return array
 */
function sanitize_settings( $input ) {
	if ( empty( $input ) || ! is_array( $input ) ) {
		$input = [];
	}

	return [
		'prevent_anonymous_access'     => ! empty( $input['prevent_anonymous_access'] ),
		'allow_index_access'           => ! empty( $input['allow_index_access'] ),
		'allow_namespace_access'       => ! empty( $input['allow_namespace_access'] ),
		'allow_user_access'            => ! empty( $input['allow_user_access'] ),
		'check_options_requests'       => ! empty( $input['check_options_requests'] ),
		'anonymous_requests_allowlist' => ! empty( $input['anonymous_requests_allowlist'] ) ? sanitize_textarea_field( $input['anonymous_requests_allowlist'] ) : '',
		'anonymous_requests_denylist'  => ! empty( $input['anonymous_requests_denylist'] ) ? sanitize_textarea_field( $input['anonymous_requests_denylist'] ) : '',
		'authentication_jwt'           => ! empty( $input['authentication_jwt'] ),
		'user_authentication_jwt'      => ! empty( $input['user_authentication_jwt'] ),
	];
}

/**
 * Render a settings field.
 *
 * @param array $input Input settings.
 */
function render_field( array $input ) {
	$disabled = ! empty( $input['filter'] ) && has_filter( $input['filter'] );
	$value    = get_option( SETTINGS_KEY )[ $input['id'] ] ?? '';

	switch ( $input['type'] ) {
		case 'checkbox':
			if ( $disabled ) {
				/* Documented in plugin.php. */
				$value = apply_filters( $input['filter'], false, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			}

			printf(
				'<label for="%1$s"><input type="checkbox" name="%2$s[%1$s]" id="%1$s" value="1" %3$s %4$s /> %5$s</label>',
				esc_attr( $input['id'] ),
				esc_attr( SETTINGS_KEY ),
				checked( $value, 1, false ),
				disabled( $disabled, true, false ),
				esc_html( $input['description'] )
			);

			break;

		case 'textarea':
			if ( $disabled ) {
				/* Documented in plugin.php. */
				$value = apply_filters( $input['filter'], $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			}

			printf(
				'<p><label for="%1$s">%2$s</label></p><p><textarea name="%3$s[%1$s]" id="%1$s" rows="10" cols="50" %4$s>%5$s</textarea></p>',
				esc_attr( $input['id'] ),
				esc_html( $input['description'] ),
				esc_attr( SETTINGS_KEY ),
				disabled( $disabled, true, false ),
				esc_html( $value )
			);
			break;

		default:
			esc_html_e( 'Unknown field type.', 'rest-api-guard' );
			break;
	}

	if ( ! empty( $input['additional'] ) ) {
		printf(
			'<p><em>%s</em></p>',
			esc_html( $input['additional'] )
		);
	}

	if ( $disabled ) {
		printf(
			'<p><em>%s</em></p>',
			sprintf(
				/* translators: %s: The name of the filter. */
				esc_html__( 'This setting is controlled by a filter: %s.', 'rest-api-guard' ),
				'<code>' . esc_html( $input['filter'] ?? '' ) . '</code>'
			)
		);
	}
}
