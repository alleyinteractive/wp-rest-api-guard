<?php
/**
 * WP-CLI commands.
 *
 * @package rest-api-guard
 */

use function Alley\WP\REST_API_Guard\generate_jwt;

WP_CLI::add_command(
	'rest-api-guard generate-jwt',
	function ( $args, $assoc_args ) {
		$expiration = isset( $assoc_args['expiration'] ) ? (int) $assoc_args['expiration'] : null;
		$user       = isset( $assoc_args['user'] ) ? (int) $assoc_args['user'] : null;

		echo generate_jwt( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			expiration: $expiration,
			user: $user,
		) . PHP_EOL;
	},
	[
		'shortdesc' => __( 'Generate a JSON Web Token (JWT).', 'rest-api-guard' ),
		'synopsis'  => '[--expiration=<expiration>] [--user=<user>]',
	],
);
