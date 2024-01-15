<?php
/**
 * WP-CLI commands.
 *
 * @package rest-api-guard
 */

use function Alley\WP\REST_API_Guard\generate_jwt;

WP_CLI::add_command(
	'rest-api-guard generate-jwt',
	function () {
		echo generate_jwt() . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	[
		'shortdesc' => __( 'Generate a JSON Web Token (JWT).', 'rest-api-guard' ),
	]
);
