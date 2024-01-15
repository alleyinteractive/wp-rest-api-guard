<?php
namespace Alley\WP\REST_API_Guard\Tests;

use Firebase\JWT\JWT;

use function Alley\WP\REST_API_Guard\generate_jwt;

use const Alley\WP\REST_API_Guard\SETTINGS_KEY;

/**
 * Visit {@see https://mantle.alley.co/testing/test-framework.html} to learn more.
 */
class Test_REST_API_Guard extends Test_Case {
	protected function setUp(): void {
		parent::setUp();

		delete_option( SETTINGS_KEY );
	}

	public function test_default_anonymous_access() {
		$this->get( rest_url( '/wp/v2/categories' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/posts' ) )->assertOk();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertOk();

		// By default users, index, or namespaces are not allowed.
		$this->get( rest_url( '/wp/v2/users' ) )->assertUnauthorized();
		$this->get( rest_url( '/' ) )->assertUnauthorized();

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
		$this->get( rest_url( '/wp/v2/tags' ) )->assertOk();
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
		$this->get( rest_url( '/wp/v2/types' ) )->assertUnauthorized();
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

	/**
	 * @dataProvider jwtDataProvider
	 */
	public function test_jwt_authentication( $type, $token ) {
		$this->expectApplied( 'rest_api_guard_authentication_jwt' );

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
	}

	public static function jwtDataProvider(): array {
		return [
			'valid' => [ 'valid', generate_jwt() ],
			'invalid' => [ 'invalid', 'invalid' ],
			'empty' => [ 'invalid', '' ],
		];
	}
}
