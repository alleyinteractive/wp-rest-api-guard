<?php
/**
 * WP-CLI commands.
 *
 * @package rest-api-guard
 */

use function Alley\WP\REST_API_Guard\generate_jwt;
use function Alley\WP\REST_API_Guard\get_tokens;
use function Alley\WP\REST_API_Guard\revoke_token;

WP_CLI::add_command(
	'rest-api-guard generate-jwt',
	function ( $args, $assoc_args ) {
		$expiration = isset( $assoc_args['expiration'] ) ? (int) $assoc_args['expiration'] : null;
		$user       = isset( $assoc_args['user'] ) ? (int) $assoc_args['user'] : null;

		echo generate_jwt( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			expiration: $expiration,
			user: $user,
			name: (string) ( $assoc_args['name'] ?? '' ),
		) . PHP_EOL;
	},
	[
		'shortdesc' => __( 'Generate a JSON Web Token (JWT).', 'rest-api-guard' ),
		'synopsis'  => '[--expiration=<expiration>] [--user=<user>] [--name=<name>]',
	],
);

WP_CLI::add_command(
	'rest-api-guard list-jwts',
	function ( $args, $assoc_args ) {
		$items = [];

		foreach ( get_tokens() as $jti => $token ) {
			$items[] = [
				'id'      => $jti,
				'name'    => $token['name'],
				'user'    => $token['user_id'] ?? '',
				'issued'  => gmdate( 'Y-m-d H:i:s', $token['issued_at'] ),
				'expires' => null !== $token['expires_at'] ? gmdate( 'Y-m-d H:i:s', $token['expires_at'] ) : '',
			];
		}

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, [ 'id', 'name', 'user', 'issued', 'expires' ] );
	},
	[
		'shortdesc' => __( 'List issued JSON Web Tokens (JWTs).', 'rest-api-guard' ),
		'synopsis'  => '[--format=<format>]',
	],
);

WP_CLI::add_command(
	'rest-api-guard revoke-jwt',
	function ( $args ) {
		if ( ! revoke_token( $args[0] ) ) {
			WP_CLI::error( __( 'Token not found.', 'rest-api-guard' ) );
		}

		WP_CLI::success( __( 'Token revoked.', 'rest-api-guard' ) );
	},
	[
		'shortdesc' => __( 'Revoke a JSON Web Token (JWT) by its ID.', 'rest-api-guard' ),
		'synopsis'  => '<id>',
	],
);
