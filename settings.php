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
	</div>
	<?php
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
