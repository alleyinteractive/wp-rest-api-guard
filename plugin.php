<?php
/**
 * Plugin Name: REST API Guard
 * Plugin URI: https://github.com/alleyinteractive/wp-rest-api-guard
 * Description: Restrict and control access to the REST API
 * Version: 1.0.2
 * Author: Sean Fisher
 * Author URI: https://alley.co/
 * Requires at least: 6.0
 * Tested up to: 6.0
 *
 * Text Domain: plugin_domain
 * Domain Path: /languages/
 *
 * @package rest-api-guard
 */

namespace Alley\WP\REST_API_Guard;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instantiate the plugin.
 */
function main() {
	require_once __DIR__ . '/settings.php';

	add_filter( 'rest_pre_dispatch', __NAMESPACE__ . '\on_rest_pre_dispatch', 10, 3 );
}
main();

/**
 * Check if anonymous access should be prevented for the current request.
 *
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request The request object.
 * @return bool
 */
function should_prevent_anonymous_access( WP_REST_Server $server, WP_REST_Request $request ): bool {
	$settings = (array) get_option( SETTINGS_KEY );

	if ( ! is_array( $settings ) ) {
		$settings = [];
	}

	/**
	 * Check if anonymous access is prevent by default.
	 *
	 * @param bool             $prevent Whether to prevent anonymous access, default false.
	 * @param \WP_REST_Request $request REST API Request.
	 */
	if ( true === apply_filters( 'rest_api_guard_prevent_anonymous_access', $settings['prevent_anonymous_access'] ?? false, $request ) ) {
		return true;
	}

	$endpoint = $request->get_route();

	/**
	 * Prevent access to the root of the REST API.
	 *
	 * @param bool $prevent Whether to prevent anonymous access, default false.
	 */
	if ( '/' === $endpoint && false === apply_filters( 'rest_api_guard_allow_index_access', $settings['allow_index_access'] ?? false ) ) {
		return true;
	}

	if (
		in_array( substr( $endpoint, 1 ), $server->get_namespaces(), true )
		/**
		 * Prevent access to the namespace index of the REST API.
		 *
		 * @param bool  $prevent    Whether to prevent anonymous access, default false.
		 * @param string $namespace The namespace of the request.
		 */
		&& false === apply_filters( 'rest_api_guard_allow_namespace_access', $settings['allow_namespace_access'] ?? false, substr( $endpoint, 1 ) )
	) {
		return true;
	}

	/**
	 * Prevent access to the /wp/v2/users endpoints by default.
	 *
	 * @param bool $pre Whether to prevent access to the /wp/v2/users endpoints.
	 */
	if ( preg_match( '#^/wp/v\d+/users($|/)#', $endpoint ) && false === apply_filters( 'rest_api_guard_allow_user_access', $settings['allow_user_access'] ?? false ) ) {
		return true;
	}

	// todo: check settings.

	/**
	 * Filter the allowlist for allowed anonymous requests.
	 *
	 * @param string[]         $allowlist Allowlist of requests.
	 * @param \WP_REST_Request $request   REST API Request.
	 */
	$allowlist = apply_filters( 'rest_api_guard_anonymous_requests_allowlist', $settings['anonymous_requests_allowlist'] ?? [] );

	if ( ! empty( $allowlist ) ) {
		if ( ! is_array( $allowlist ) ) {
			$allowlist = explode( "\n", $allowlist );
		}

		foreach ( $allowlist as $allowlist_endpoint ) {
			if ( preg_match( '/' . str_replace( '\*', '.*', preg_quote( $allowlist_endpoint, '/' ) ) . '/', $endpoint ) ) {
				return false;
			}
		}

		// If no route on the allowlist was matched, prevent anonymous access.
		return true;
	}

	/**
	 * Filter the denylist for allowed anonymous requests.
	 *
	 * @param string[]         $denylist Denylist of requests.
	 * @param \WP_REST_Request $request  REST API Request.
	 */
	$denylist = apply_filters( 'rest_api_guard_anonymous_requests_denylist', $settings['anonymous_requests_denylist'] ?? [] );

	if ( ! empty( $denylist ) ) {
		if ( ! is_array( $denylist ) ) {
			$denylist = explode( "\n", $denylist );
		}

		foreach ( $denylist as $denylist_endpoint ) {
			if ( preg_match( '/' . str_replace( '\*', '.*', preg_quote( $denylist_endpoint, '/' ) ) . '/', $endpoint ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Short-circuit the REST API request if the user is not allowed to access it.
 *
 * @param mixed           $pre     Dispatched value. Will be used if not empty.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request REST API Request.
 * @return mixed
 */
function on_rest_pre_dispatch( $pre, $server, $request ) {
	if ( ! empty( $pre ) || is_user_logged_in() ) {
		return $pre;
	}

	if ( should_prevent_anonymous_access( $server, $request ) ) {
		return new WP_Error(
			'rest_api_guard_unauthorized',
			/**
			 * Filter the authorization error message.
			 *
			 * @param string $message The error message.
			 */
			apply_filters(
				'rest_api_guard_unauthorized_message',
				__( 'Sorry, you are not allowed to access this page.', 'rest-api-guard' ),
			),
			[
				'status' => rest_authorization_required_code(),
			]
		);
	}

	return $pre;
}
