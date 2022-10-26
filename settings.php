<?php
/**
 * Plugin Settings
 *
 * @package rest-api-guard
 */

namespace Alley\WP\REST_API_Guard;

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
		'anonymous_requests_allowlist' => ! empty( $input['anonymous_requests_allowlist'] ) ? sanitize_textarea_field( $input['anonymous_requests_allowlist'] ) : '',
		'anonymous_requests_denylist'  => ! empty( $input['anonymous_requests_denylist'] ) ? sanitize_textarea_field( $input['anonymous_requests_denylist'] ) : '',
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
