=== REST API Guard ===
Contributors: sean212
Tags: rest-api, security, jwt, authentication, privacy
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restrict and control anonymous access to the WordPress REST API, with optional JSON Web Token (JWT) authentication.

== Description ==

The WordPress REST API is public by default and shares a good deal of information about your site with anyone who asks. REST API Guard makes it easy to decide who can see what.

Out of the box, the plugin:

* Blocks anonymous access to the users endpoint (`/wp/v2/users`) so usernames aren't exposed.
* Blocks anonymous access to the REST API index (`/`) and namespace endpoints (`/wp/v2`) so visitors can't list your plugins and post types.

It can also:

* Block all anonymous access to the REST API.
* Allow anonymous access only to specific routes (allowlist), or block specific routes (denylist).
* Require anonymous requests to include a JSON Web Token (JWT).
* Let users authenticate with a JWT tied to their account.
* Generate, list, and revoke tokens from the settings page or WP-CLI.

Every option is available on the settings page (Settings → REST API Guard) and as a filter for configuring the plugin in code. A setting controlled by a filter is shown as read-only on the settings page.

= Restrict access to user information =

Anonymous access to `/wp/v2/users` is blocked by default. To allow it:

	add_filter( 'rest_api_guard_allow_user_access', '__return_true' );

= Restrict access to the index and namespace endpoints =

Anonymous access to the index (`/`) and namespace (`/wp/v2`) endpoints is blocked by default. To allow it:

	add_filter( 'rest_api_guard_allow_index_access', '__return_true' );
	add_filter( 'rest_api_guard_allow_namespace_access', '__return_true' );

= Block all anonymous access =

	add_filter( 'rest_api_guard_prevent_anonymous_access', '__return_true' );

= Allow anonymous access to specific routes (allowlist) =

When an allowlist is set, anonymous requests to any route not on the list are denied. The allowlist takes priority over the denylist. Use `*` as a wildcard.

	add_filter(
		'rest_api_guard_anonymous_requests_allowlist',
		fn () => [
			'/wp/v2/posts*',
			'/custom-namespace/v1/public/*',
		]
	);

= Deny anonymous access to specific routes (denylist) =

Anonymous requests to routes on the denylist are denied. All other routes are allowed. Use `*` as a wildcard.

	add_filter(
		'rest_api_guard_anonymous_requests_denylist',
		fn () => [
			'/wp/v2/comments*',
			'/custom-namespace/v1/private/*',
		]
	);

= Require a JSON Web Token (JWT) for anonymous requests =

Anonymous requests can be required to send a token in an `Authorization: Bearer <token>` header:

	add_filter( 'rest_api_guard_authentication_jwt', '__return_true' );

Tokens are expected to have an audience of `wordpress-rest-api` and an issuer of the site URL. Both can be changed:

	add_filter( 'rest_api_guard_jwt_audience', fn () => 'custom-audience' );
	add_filter( 'rest_api_guard_jwt_issuer', fn () => 'https://example.com' );

Tokens are signed with a secret generated automatically and stored in the `rest_api_guard_jwt_secret` option. It can also be set in code:

	add_filter( 'rest_api_guard_jwt_secret', fn () => 'my-custom-secret' );

= Authenticate users with a JWT =

Tokens can also be tied to a user. A request with a user token is treated as that user, with the same permissions they have:

	add_filter( 'rest_api_guard_user_authentication_jwt', '__return_true' );

= Generate, list, and revoke tokens =

Tokens can be generated from the Tokens section of the settings page. Give each one a name so you can tell them apart later, and optionally tie it to a user or set an expiration. The token is shown once after it's generated, so copy it somewhere safe.

The same section lists every token the plugin has issued, and any of them can be revoked. A revoked token stops working right away.

Tokens can also be managed with WP-CLI:

	wp rest-api-guard generate-jwt [--name=<name>] [--user=<user-id>] [--expiration=<seconds>]
	wp rest-api-guard list-jwts
	wp rest-api-guard revoke-jwt <id>

Or generated in code:

	$token = \Alley\WP\REST_API_Guard\generate_jwt(
		expiration: HOUR_IN_SECONDS, // Optional. Seconds until the token expires.
		user: 1,                     // Optional. A user ID or WP_User.
		name: 'Mobile App',          // Optional. A name to identify the token by.
	);

Tokens issued before version 1.5.0 aren't tracked, so they can't be listed or revoked one at a time. They're still accepted by default. To reject them:

	add_filter( 'rest_api_guard_allow_untracked_jwt', '__return_false' );

Changing the `rest_api_guard_jwt_secret` option invalidates every token issued so far, tracked or not.

= Caching =

Responses to REST API requests that include an `Authorization` header are sent with no-cache headers so a page cache or CDN doesn't store them.

== Installation ==

1. Install the plugin from the Plugins → Add New screen, or upload it to the `/wp-content/plugins/` directory.
2. Activate the plugin from the Plugins screen.
3. Configure it from Settings → REST API Guard.

The plugin can also be installed with Composer:

	composer require alleyinteractive/wp-rest-api-guard

== Frequently Asked Questions ==

= Does this affect logged-in users? =

No. The restrictions only apply to anonymous requests. Logged-in users have the same REST API access WordPress normally gives them.

= Why does the block editor still work? =

The block editor makes REST API requests as the logged-in user, so it isn't affected by the anonymous access rules.

= Can I hide the settings page? =

Yes. Configure the plugin in code and disable the settings page:

	add_filter( 'rest_api_guard_disable_admin_settings', '__return_true' );

= Are OPTIONS requests checked? =

Not by default, since browsers send them as CORS preflight requests without credentials. To check them too:

	add_filter( 'rest_api_guard_check_options_requests', '__return_true' );

== Screenshots ==

1. The settings page, including the Tokens section for generating and revoking JSON Web Tokens.

== Changelog ==

= 1.5.0 =

* Track issued JWTs and allow them to be revoked from the settings page or WP-CLI.
* Add a Tokens section to the settings page to generate, list, and revoke JWTs.
* Add the `rest_api_guard_allow_untracked_jwt` filter to reject JWTs issued before tracking.
* Send no-cache headers on REST API requests that include an `Authorization` header.

= 1.4.2 =

* Prevent mixed-case REST API routes from bypassing anonymous access restrictions.
* Add support for WordPress 7.1.
* Require PHP 8.3+.
* Update `firebase/php-jwt` to version 7.

= 1.4.1 =

* No changes, release to trigger deployment to WordPress.org.

= 1.4.0 =

* Add support for WordPress 6.8.
* Require PHP 8.1+.

= 1.3.1 =

* Ignore JWT authentication for the REST API if the user is already authenticated.

= 1.3.0 =

* Allow claims to be added to a generated JWT via filter.
* Don't check `OPTIONS` requests by default.

= 1.2.0 =

* Add support for authenticated users interacting with the REST API.
* Allow settings to be completely disabled via code.
* Increase the default length of the JWT secret to 32 characters.

= 1.1.0 =

* Require PHP 8.0.
* Add support for anonymous authentication with a JSON Web Token (JWT).

The full changelog is available on [GitHub](https://github.com/alleyinteractive/wp-rest-api-guard/blob/develop/CHANGELOG.md).
