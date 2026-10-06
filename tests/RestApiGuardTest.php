<?php
namespace Alley\WP\REST_API_Guard\Tests;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Mantle\Testing\Exceptions\WP_Die_Exception;
use Mantle\Testkit\Test_Case;
use PHPUnit\Framework\Attributes\DataProvider;

use function Alley\WP\REST_API_Guard\generate_jwt;
use function Alley\WP\REST_API_Guard\get_jwt_audience;
use function Alley\WP\REST_API_Guard\get_jwt_issuer;
use function Alley\WP\REST_API_Guard\get_jwt_secret;
use function Alley\WP\REST_API_Guard\get_token;
use function Alley\WP\REST_API_Guard\get_tokens;
use function Alley\WP\REST_API_Guard\handle_generate_jwt;
use function Alley\WP\REST_API_Guard\handle_revoke_jwt;
use function Alley\WP\REST_API_Guard\is_jwt_authentication_enabled;
use function Alley\WP\REST_API_Guard\revoke_token;

use const Alley\WP\REST_API_Guard\SETTINGS_KEY;
use const Alley\WP\REST_API_Guard\TOKENS_KEY;

/**
 * Visit {@see https://mantle.alley.co/testing/test-framework.html} to learn more.
 */
class RestApiGuardTest extends Test_Case {
	protected function setUp(): void {
		parent::setUp();

		delete_option( SETTINGS_KEY );
		delete_option( TOKENS_KEY );
	}

	public function test_default_anonymous_access() {
		$this->get( rest_url( '/wp/v2/categories' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/posts' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertOk();

		// By default users, index, or namespaces are not allowed.
		$this->get( rest_url( '/wp/v2/users' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/Users' ) )->assertUnauthorized();
		$this->get( rest_url( '/' ) )->assertUnauthorized();
		$this->get( rest_url( '/WP/v2' ) )->assertUnauthorized();

		$this->acting_as( 'administrator' );

		$this->get( rest_url( '/wp/v2/users' ) )->assertOk();
	}

	public function test_allow_user_access_code() {
		$this->get( rest_url( '/wp/v2/users' ) )->assertUnauthorized();

		add_filter( 'rest_api_guard_allow_user_access', fn () => true );

		$this->get( rest_url( '/wp/v2/users' ) )->assertOk();
	}

	public function test_allow_user_access_settings() {
		$this->get( rest_url( '/wp/v2/users' ) )->assertUnauthorized();

		update_option(
			SETTINGS_KEY,
			[
				'allow_user_access' => true,
			]
		);

		$this->get( rest_url( '/wp/v2/users' ) )->assertOk();
	}

	public function test_allow_index_access_code() {
		$this->get( rest_url( '/' ) )->assertUnauthorized();

		add_filter( 'rest_api_guard_allow_index_access', fn () => true );

		$this->get( rest_url( '/' ) )->assertOk();
	}

	public function test_allow_index_access_settings() {
		$this->get( rest_url( '/' ) )->assertUnauthorized();

		update_option(
			SETTINGS_KEY,
			[
				'allow_index_access' => true,
			]
		);

		$this->get( rest_url( '/' ) )->assertOk();
	}

	public function test_allow_namespace_access_code() {
		$this->get( rest_url( '/wp/v2' ) )->assertUnauthorized();

		add_filter( 'rest_api_guard_allow_namespace_access', fn () => true );

		$this->get( rest_url( '/wp/v2' ) )->assertOk();
	}

	public function test_allow_namespace_access_settings() {
		$this->get( rest_url( '/wp/v2' ) )->assertUnauthorized();

		update_option(
			SETTINGS_KEY,
			[
				'allow_namespace_access' => true,
			]
		);

		$this->get( rest_url( '/wp/v2' ) )->assertOk();
	}

	public function test_prevent_anonymous_access_code() {
		$this->get( rest_url( '/wp/v2/categories' ) )->assertOk();

		add_filter( 'rest_api_guard_prevent_anonymous_access', fn () => true );

		$this->get( rest_url( '/wp/v2/categories' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/posts' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertUnauthorized();
	}

	public function test_prevent_anonymous_access_settings() {
		$this->get( rest_url( '/wp/v2/categories' ) )->assertOk();

		update_option(
			SETTINGS_KEY,
			[
				'prevent_anonymous_access' => true,
			]
		);

		$this->get( rest_url( '/wp/v2/categories' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/posts' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertUnauthorized();
	}

	public function test_check_options_requests() {
		$this->assertNotAuthenticated();
		$this->expectApplied( 'rest_api_guard_check_options_requests' )->times( 8 );

		// Check the default settings.
		update_option(
			SETTINGS_KEY,
			[
				'prevent_anonymous_access' => true,
			]
		);

		$this->options( rest_url( '/wp/v2/categories' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/categories' ) )->assertUnauthorized();

		update_option(
			SETTINGS_KEY,
			[
				'prevent_anonymous_access' => true,
				'check_options_requests'   => true,
			]
		);

		$this->options( rest_url( '/wp/v2/categories' ) )->assertUnauthorized();

		update_option(
			SETTINGS_KEY,
			[
				'prevent_anonymous_access' => true,
			]
		);

		add_filter( 'rest_api_guard_check_options_requests', fn () => true );

		$this->options( rest_url( '/wp/v2/categories' ) )->assertUnauthorized();
	}

	public function test_prevent_access_allowlist_code() {
		$this->get( rest_url( '/wp/v2/categories' ) )->assertOk();

		add_filter(
			'rest_api_guard_anonymous_requests_allowlist',
			fn () => [
				'/wp/v2/posts/*',
				'/wp/v2/tags',
			]
		);

		$post_id = static::factory()->post->create();

		$this->get( rest_url( '/wp/v2/categories' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/posts' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/posts/' . $post_id ) )->assertOk();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertOk();
	}

	public function test_prevent_access_allowlist_setting() {
		$this->get( rest_url( '/wp/v2/categories' ) )->assertOk();

		update_option(
			SETTINGS_KEY,
			[
				'anonymous_requests_allowlist' => "/wp/v2/posts/*\n/wp/v2/tags",
			]
		);

		$post_id = static::factory()->post->create();

		$this->get( rest_url( '/wp/v2/categories' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/posts' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/posts/' . $post_id ) )->assertOk();
		$this->get( rest_url( '/wp/v2/POSTS/' . $post_id ) )->assertOk();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/TAGS' ) )->assertOk();
	}

	public function test_prevent_access_denylist_code() {
		$this->get( rest_url( '/wp/v2/tags' ) )->assertOk();

		add_filter(
			'rest_api_guard_anonymous_requests_denylist',
			fn () => [
				'/wp/v2/tags',
				'/wp/v2/types',
			]
		);

		$this->get( rest_url( '/wp/v2/categories' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/posts' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/posts/' . static::factory()->post->create() ) )->assertOk();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/types' ) )->assertUnauthorized();
	}

	public function test_prevent_access_denylist_setting() {
		$this->get( rest_url( '/wp/v2/tags' ) )->assertOk();

		update_option(
			SETTINGS_KEY,
			[
				'anonymous_requests_denylist' => "/wp/v2/tags\n/wp/v2/types",
			]
		);

		$this->get( rest_url( '/wp/v2/categories' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/posts' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/posts/' . static::factory()->post->create() ) )->assertOk();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/TAGS' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/types' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/TYPES' ) )->assertUnauthorized();
	}

	public function test_prevent_access_denylist_priority() {
		add_filter(
			'rest_api_guard_anonymous_requests_allowlist',
			fn () => [
				'/wp/v2/posts/*',
				'/wp/v2/tags',
			]
		);

		add_filter(
			'rest_api_guard_anonymous_requests_denylist',
			fn () => [
				'/wp/v2/posts/*',
				'/wp/v2/tags',
			]
		);

		$this->get( rest_url( '/wp/v2/posts/' . static::factory()->post->create() ) )->assertOk();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/categories' ) )->assertUnauthorized();
	}

	#[DataProvider( 'jwtDataProviderAnonymous' )]
	public function test_jwt_authentication_anonymous( string $type, ?string $token ) {
		$this->expectApplied( 'rest_api_guard_authentication_jwt' );

		$token ??= generate_jwt();

		add_filter( 'rest_api_guard_authentication_jwt', fn () => true );

		if ( 'valid' === $type ) {
			$this->expectApplied( 'rest_api_guard_jwt_issuer' );
			$this->expectApplied( 'rest_api_guard_jwt_audience' );
			$this->expectApplied( 'rest_api_guard_jwt_secret' );
		}


		$request = $this
			->with_header( 'Authorization', "Bearer $token" )
			->get( '/wp-json/wp/v2/posts' );

		if ( 'valid' === $type ) {
			$request->assertOk();
		} else {
			$request->assertUnauthorized();
		}

		// Ensure they are always unauthenticated.
		$this->get( '/wp-json/wp/v2/users/me' )->assertUnauthorized();
	}

	public static function jwtDataProviderAnonymous(): array {
		return [
			'valid' => [ 'valid', null ],
			'invalid' => [ 'invalid', 'invalid' ],
			'empty' => [ 'invalid', '' ],
		];
	}

	#[DataProvider( 'jwtDataProviderAuthenticated' )]
	public function test_jwt_authentication_authenticated( string $type, ?string $token ) {
		add_filter( 'rest_api_guard_authentication_jwt', fn () => true );
		add_filter( 'rest_api_guard_user_authentication_jwt', fn () => true );

		if ( null === $token ) {
			$token = generate_jwt( user: static::factory()->user->create_and_get() );
		}

		$request = $this
			->with_header( 'Authorization', "Bearer $token" )
			->get( '/wp-json/wp/v2/users/me' );

		if ( 'valid' === $type ) {
			$request->assertOk()->assertJsonPathExists( 'id' );

			// Ensure they can access the REST API normally.
			$this->get( '/wp-json/wp/v2/posts' )->assertOk();
		} else {
			$request->assertUnauthorized();

			// Ensure they cannot access the REST API normally.
			$this->get( '/wp-json/wp/v2/posts' )->assertUnauthorized();
		}
	}

	public static function jwtDataProviderAuthenticated(): array {
		return [
			'valid' => [ 'valid', null ],
			'invalid' => [ 'invalid', substr( generate_jwt(), 0, 20 ) ],
			'empty' => [ 'invalid', '' ],
		];
	}

	public function test_additional_jwt_claims() {
		add_filter(
			'rest_api_guard_jwt_additional_claims',
			function ( $claims, $user ) {
				$claims['user_email'] = $user->user_email;
				$claims['sub']        = 1234;

				return $claims;
			},
			10,
			2,
		);

		add_filter( 'rest_api_guard_user_authentication_jwt', fn () => true );

		$user = static::factory()->user->create_and_get();

		$token = generate_jwt( user: $user );

		$this
			->with_header( 'Authorization', "Bearer {$token}" )
			->get( '/wp-json/wp/v2/users/me' )
			->assertOk();

		// Ensure the additional claim is present.
		$decoded = JWT::decode( $token, new Key( get_jwt_secret(), 'HS256' ) );

		$this->assertEquals( $user->user_email, $decoded->user_email );
		$this->assertEquals( $user->ID, $decoded->sub ); // Ensure it cannot overwrite a claim.
	}

	public function test_generate_jwt_tracks_token() {
		$user  = static::factory()->user->create_and_get();
		$token = generate_jwt( expiration: 3600, user: $user, name: 'Example' );

		$decoded = JWT::decode( $token, new Key( get_jwt_secret(), 'HS256' ) );

		$this->assertNotEmpty( $decoded->jti );
		$this->assertSame(
			[
				'name'       => 'Example',
				'user_id'    => $user->ID,
				'issued_at'  => $decoded->iat,
				'expires_at' => $decoded->exp,
			],
			get_token( $decoded->jti ),
		);
	}

	public function test_revoked_jwt_is_rejected() {
		add_filter( 'rest_api_guard_authentication_jwt', fn () => true );

		$token = generate_jwt();

		$this->with_header( 'Authorization', "Bearer $token" )->get( '/wp-json/wp/v2/posts' )->assertOk();

		revoke_token( JWT::decode( $token, new Key( get_jwt_secret(), 'HS256' ) )->jti );

		$this->with_header( 'Authorization', "Bearer $token" )->get( '/wp-json/wp/v2/posts' )->assertUnauthorized();
	}

	public function test_untracked_jwt() {
		add_filter( 'rest_api_guard_authentication_jwt', fn () => true );

		$token = JWT::encode(
			[
				'iss' => get_jwt_issuer(),
				'aud' => get_jwt_audience(),
				'iat' => time(),
			],
			get_jwt_secret(),
			'HS256',
		);

		$this->with_header( 'Authorization', "Bearer $token" )->get( '/wp-json/wp/v2/posts' )->assertOk();

		add_filter( 'rest_api_guard_allow_untracked_jwt', fn () => false );

		$this->with_header( 'Authorization', "Bearer $token" )->get( '/wp-json/wp/v2/posts' )->assertUnauthorized();
	}

	public function test_nocache_headers_with_authorization_header() {
		$this->get( '/wp-json/wp/v2/posts' )->assertOk();

		$this->assertFalse( apply_filters( 'rest_send_nocache_headers', false ) );

		$this->with_header( 'Authorization', 'Bearer ' . generate_jwt() )->get( '/wp-json/wp/v2/posts' )->assertOk();

		$this->assertTrue( apply_filters( 'rest_send_nocache_headers', false ) );
	}

	public function test_admin_generate_and_revoke_jwt() {
		$this->acting_as( 'administrator' );

		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new \RuntimeException( $location );
			},
		);

		$_POST = $_REQUEST = [
			'name'       => 'From Admin',
			'expiration' => '2',
			'_wpnonce'   => wp_create_nonce( 'rest_api_guard_generate_jwt' ),
		];

		try {
			handle_generate_jwt();
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'rest_api_guard_notice=generated', $e->getMessage() );
		}

		$tokens = get_tokens();
		$jti    = array_key_first( $tokens );

		$this->assertCount( 1, $tokens );
		$this->assertSame( 'From Admin', $tokens[ $jti ]['name'] );
		$this->assertNull( $tokens[ $jti ]['user_id'] );
		$this->assertSame( $tokens[ $jti ]['issued_at'] + 2 * DAY_IN_SECONDS, $tokens[ $jti ]['expires_at'] );
		$this->assertNotEmpty( get_transient( 'rest_api_guard_new_jwt_' . get_current_user_id() ) );

		$_POST = $_REQUEST = [
			'jti'      => $jti,
			'_wpnonce' => wp_create_nonce( 'rest_api_guard_revoke_jwt' ),
		];

		try {
			handle_revoke_jwt();
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'rest_api_guard_notice=revoked', $e->getMessage() );
		}

		$this->assertEmpty( get_tokens() );
	}

	public function test_non_admin_cannot_generate_jwt() {
		$this->acting_as( 'editor' );

		$_POST = $_REQUEST = [
			'name'     => 'Nope',
			'_wpnonce' => wp_create_nonce( 'rest_api_guard_generate_jwt' ),
		];

		try {
			handle_generate_jwt();
			$this->fail( 'Expected wp_die().' );
		} catch ( WP_Die_Exception ) {
			$this->assertEmpty( get_tokens() );
		}
	}

	public function test_jwt_authentication_enabled() {
		$this->assertFalse( is_jwt_authentication_enabled() );

		update_option( SETTINGS_KEY, [ 'user_authentication_jwt' => true ] );

		$this->assertTrue( is_jwt_authentication_enabled() );

		delete_option( SETTINGS_KEY );
		add_filter( 'rest_api_guard_authentication_jwt', '__return_true' );

		$this->assertTrue( is_jwt_authentication_enabled() );
	}
}
