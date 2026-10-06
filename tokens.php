<?php
/**
 * Registry of issued JSON Web Tokens (JWTs) so they can be listed and revoked.
 *
 * @package rest-api-guard
 */

namespace Alley\WP\REST_API_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option name that stores the issued tokens.
 *
 * @var string
 */
const TOKENS_KEY = 'rest_api_guard_jwts';

/**
 * Get all tracked tokens keyed by their ID (the JWT "jti" claim).
 *
 * @return array<string, array{name: string, user_id: int|null, issued_at: int, expires_at: int|null}>
 */
function get_tokens(): array {
	$tokens = get_option( TOKENS_KEY, [] );

	return is_array( $tokens ) ? $tokens : [];
}

/**
 * Get a tracked token by its ID.
 *
 * @param string $jti Token ID.
 * @return array{name: string, user_id: int|null, issued_at: int, expires_at: int|null}|null
 */
function get_token( string $jti ): ?array {
	return get_tokens()[ $jti ] ?? null;
}

/**
 * Track an issued token.
 *
 * @param string                                                                       $jti  Token ID.
 * @param array{name: string, user_id: int|null, issued_at: int, expires_at: int|null} $data Token data.
 */
function track_token( string $jti, array $data ): void {
	$tokens         = get_tokens();
	$tokens[ $jti ] = $data;

	update_option( TOKENS_KEY, $tokens );
}

/**
 * Revoke a token so it can no longer be used.
 *
 * @param string $jti Token ID.
 * @return bool Whether the token existed.
 */
function revoke_token( string $jti ): bool {
	$tokens = get_tokens();

	if ( ! isset( $tokens[ $jti ] ) ) {
		return false;
	}

	unset( $tokens[ $jti ] );

	update_option( TOKENS_KEY, $tokens );

	return true;
}
