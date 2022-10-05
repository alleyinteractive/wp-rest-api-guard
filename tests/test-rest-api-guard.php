<?php
namespace Alley\WP\REST_API_Guard\Tests;

/**
 * Visit {@see https://mantle.alley.co/testing/test-framework.html} to learn more.
 */
class Test_REST_API_Guard extends Test_Case {
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

	public function test_allow_user_access() {
		$this->get( rest_url( '/wp/v2/users' ) )->assertUnauthorized();

		add_filter( 'rest_api_guard_allow_user_access', fn () => true );

		$this->get( rest_url( '/wp/v2/users' ) )->assertOk();
	}

	public function test_allow_index_access() {
		$this->get( rest_url( '/' ) )->assertUnauthorized();

		add_filter( 'rest_api_guard_allow_index_access', fn () => true );

		$this->get( rest_url( '/' ) )->assertOk();
	}

	public function test_allow_namespace_access() {
		$this->get( rest_url( '/wp/v2' ) )->assertUnauthorized();

		add_filter( 'rest_api_guard_allow_namespace_access', fn () => true );

		$this->get( rest_url( '/wp/v2' ) )->assertOk();
	}

	// public function test_prevent_anonymous_access_settings() {}

	public function test_prevent_anonymous_access_code() {
		add_filter( 'rest_api_guard_prevent_anonymous_access', fn () => true );

		$this->get( rest_url( '/wp/v2/categories' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/posts' ) )->assertUnauthorized();
		$this->get( rest_url( '/wp/v2/tags' ) )->assertUnauthorized();
	}

	// public function test_prevent_access_allowlist_setting() {}

	public function test_prevent_access_allowlist_code() {
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

	// public function test_prevent_access_denylist_setting() {}

	public function test_prevent_access_denylist_code() {
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
}
