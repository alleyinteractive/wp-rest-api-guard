<?php
/**
 * Plugin Name: REST API Guard
 * Plugin URI: https://github.com/alleyinteractive/wp-rest-api-guard
 * Description: Restrict and control access to the REST API
 * Version: 1.3.2
 * Author: Sean Fisher
 * Author URI: https://alley.com/
 * Requires at least: 6.0
 * Tested up to: 6.3
 *
 * Text Domain: plugin_domain
 * Domain Path: /languages/
 *
 * @package rest-api-guard
 */

namespace Alley\WP\REST_API_Guard;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instantiate the plugin.
 */
function main() {
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
	}

	require_once __DIR__ . '/settings.php';

	add_filter( 'rest_pre_dispatch', __NAMESPACE__ . '\on_rest_pre_dispatch', 10, 3 );
}
main();

/**
 * Check if anonymous access should be prevented for the current request.
 *
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request The request object.
 * @return WP_Error|bool
 *
 * @throws InvalidArgumentException If the JWT is invalid.
 */
function should_prevent_anonymous_access( WP_REST_Server $server, WP_REST_Request $request ): WP_Error|bool {
	$settings = (array) get_option( SETTINGS_KEY );

	if ( ! is_array( $settings ) ) {
		$settings = [];
	}

	/**
	 * Filters whether the REST API Guard should check OPTIONS requests.
	 *
	 * This is useful for CORS preflight requests.
	 *
	 * @param bool             $check Whether to check OPTIONS requests. Default false.
	 * @param \WP_REST_Request $request REST API Request.
	 */
	if ( 'OPTIONS' === $request->get_method() && ! apply_filters( 'rest_api_guard_check_options_requests', $settings['check_options_requests'] ?? false, $request ) ) {
		return false;
	}

	if ( class_exists( JWT::class ) && ! is_user_logged_in() ) {
		/**
		 * Check if the anonymous request requires a JSON Web Token (JWT).
		 *
		 * @param bool             $require Whether to require a JWT, default false.
		 * @param \WP_REST_Request $request REST API Request.
		 */
		$require_anonymous_jwt = true === apply_filters( 'rest_api_guard_authentication_jwt', $settings['authentication_jwt'] ?? false, $request );
		$allow_user_jwt        = true === apply_filters( 'rest_api_guard_user_authentication_jwt', $settings['user_authentication_jwt'] ?? false, $request );

		if ( $require_anonymous_jwt || $allow_user_jwt ) {
			try {
				$jwt = $request->get_header( 'Authorization' );

				if ( empty( $jwt ) && $require_anonymous_jwt ) {
					throw new InvalidArgumentException( __( 'No authorization header token was found and is required for this request.', 'rest-api-guard' ) );
				}

				if ( ! empty( $jwt ) ) {
					if ( 0 !== strpos( $jwt, 'Bearer ' ) ) {
						throw new InvalidArgumentException( __( 'Invalid authorization header.', 'rest-api-guard' ) );
					}

					$decoded = JWT::decode(
						substr( $jwt, 7 ),
						new Key( get_jwt_secret(), 'HS256' ),
					);

					// Verify the contents of the JWT.
					if ( empty( $decoded->iss ) || get_jwt_issuer() !== $decoded->iss ) {
						throw new InvalidArgumentException( __( 'Invalid JWT issuer.', 'rest-api-guard' ) );
					}

					if ( empty( $decoded->aud ) || get_jwt_audience() !== $decoded->aud ) {
						throw new InvalidArgumentException( __( 'Invalid JWT audience.', 'rest-api-guard' ) );
					}

					if ( $allow_user_jwt && ! empty( $decoded->sub ) ) {
						$user = get_user_by( 'id', $decoded->sub );

						if ( ! $user instanceof WP_User ) {
							throw new InvalidArgumentException( __( 'Invalid user in JWT sub.', 'rest-api-guard' ) );
						}

						wp_set_current_user( $user->ID );

						return false;
					}
				}
			} catch ( \Exception $error ) {
				return new WP_Error(
					'rest_api_guard_unauthorized',
					/**
					 * Filter the authorization error message.
					 *
					 * @param string     $message The error message being returned.
					 * @param \Throwable $error The error that occurred.
					 */
					apply_filters(
						'rest_api_guard_invalid_jwt_message',
						sprintf(
							/* translators: %s: The error message. */
							__( 'Error authentication with token: %s', 'rest-api-guard' ),
							$error->getMessage(),
						),
						$error,
					),
					[
						'status' => rest_authorization_required_code(),
					]
				);
			}
		}
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
	 * @param bool   $prevent Whether to allow anonymous access to the REST API index. Default false.
	 * @param string $endpoint The endpoint of the request.
	 */
	if ( '/' === $endpoint && false === apply_filters( 'rest_api_guard_allow_index_access', $settings['allow_index_access'] ?? false, $endpoint ) ) {
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
	 * @param bool   $pre Whether to allow access to the /wp/v2/users endpoints.
	 * @param string $endpoint The endpoint of the request.
	 */
	if ( preg_match( '#^/wp/v\d+/users($|/)#', $endpoint ) && false === apply_filters( 'rest_api_guard_allow_user_access', $settings['allow_user_access'] ?? false, $endpoint ) ) {
		return true;
	}

	/**
	 * Filter the allowlist for allowed anonymous requests.
	 *
	 * @param string[]         $allowlist Allowlist of requests.
	 * @param \WP_REST_Request $request   REST API Request.
	 */
	$allowlist = apply_filters( 'rest_api_guard_anonymous_requests_allowlist', $settings['anonymous_requests_allowlist'] ?? [] );

	if ( ! empty( $allowlist ) ) {
		if ( ! is_array( $allowlist ) ) {
			$allowlist = preg_split( '/\r\n|\r|\n/', $allowlist );
		}

		foreach ( $allowlist as $allowlist_endpoint ) {
			// Strip off /wp-json from the beginning of the endpoint if it was included.
			if ( 0 === strpos( $allowlist_endpoint, '/wp-json' ) ) {
				$allowlist_endpoint = substr( $allowlist_endpoint, 8 );
			}

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
			$denylist = preg_split( '/\r\n|\r|\n/', $denylist );
		}

		foreach ( $denylist as $denylist_endpoint ) {
			// Strip off /wp-json from the beginning of the endpoint if it was included.
			if ( 0 === strpos( $denylist_endpoint, '/wp-json' ) ) {
				$denylist_endpoint = substr( $denylist_endpoint, 8 );
			}

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

	$should_prevent = should_prevent_anonymous_access( $server, $request );

	if ( is_wp_error( $should_prevent ) ) {
		return $should_prevent;
	} elseif ( $should_prevent ) {
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

/**
 * Get the JSON Web Token (JWT) issuer.
 *
 * @return string
 */
function get_jwt_issuer(): string {
	/**
	 * Filter the issuer of the JWT.
	 *
	 * @param string $issuer The issuer of the JWT.
	 */
	return apply_filters( 'rest_api_guard_jwt_issuer', get_bloginfo( 'url' ) );
}

/**
 * Get the JSON Web Token (JWT) audience.
 *
 * @return string
 */
function get_jwt_audience(): string {
	/**
	 * Filter the audience of the JWT.
	 *
	 * @param string $audience The audience of the JWT.
	 */
	return apply_filters( 'rest_api_guard_jwt_audience', 'wordpress-rest-api' );
}

/**
 * Get the JSON Web Token (JWT) secret.
 *
 * @return string
 */
function get_jwt_secret(): string {
	// Generate the JWT secret if it does not exist.
	if ( empty( get_option( 'rest_api_guard_jwt_secret' ) ) ) {
		update_option( 'rest_api_guard_jwt_secret', wp_generate_password( 32, false ) );
	}

	/**
	 * Filter the secret of the JWT. By default, the WordPress secret key is used.
	 *
	 * @param string $secret The secret of the JWT.
	 */
	return apply_filters( 'rest_api_guard_jwt_secret', get_option( 'rest_api_guard_jwt_secret' ) );
}

/**
 * Generate a JSON Web Token (JWT).
 *
 * @param int|null         $expiration The expiration time of the JWT in seconds or null for no expiration.
 * @param WP_User|int|null $user The user to include in the JWT or null for no user.
 * @return string
 *
 * @throws InvalidArgumentException If the user is invalid or unknown.
 */
function generate_jwt( ?int $expiration = null, WP_User|int|null $user = null ): string {
	$payload = [
		'iss' => get_jwt_issuer(),
		'aud' => get_jwt_audience(),
		'iat' => time(),
	];

	if ( null !== $expiration ) {
		$payload['exp'] = time() + $expiration;
	}

	if ( null !== $user ) {
		$user = $user instanceof WP_User ? $user : get_user_by( 'id', $user );

		if ( ! $user instanceof WP_User ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid user.', 'rest-api-guard' ) );
		}

		$payload['sub']        = $user->ID;
		$payload['user_login'] = $user->user_login;

		/**
		 * Filter the additional claims to include in the JWT.
		 *
		 * The filer cannot modify any existing claims, only add new ones.
		 *
		 * @param array<string, mixed> $additional_claims The additional claims to include in the JWT.
		 * @param WP_User|null         $user The user to include in the JWT.
		 * @param array<string, mixed> $payload The payload of the JWT.
		 */
		$additional_claims = apply_filters( 'rest_api_guard_jwt_additional_claims', [], $user, $payload );

		if ( is_array( $additional_claims ) ) {
			$payload = array_merge( $additional_claims, $payload );
		}
	}

	return JWT::encode( $payload, get_jwt_secret(), 'HS256' );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/cli.php';
}
