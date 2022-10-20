=== REST API Guard ===
Stable tag: 1.0.0
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 7.4
License: GPL v2 or later
Tags: alleyinteractive, rest-api-guard
Contributors: srtfisher

Restrict and control access to the REST API.

## Installation

You can install the package via composer:

	composer require alleyinteractive/wp-rest-api-guard

## Usage

The WordPress REST API is generally very public and can share a good deal of information with the internet anonymously. This plugin aims to make it easier to restrict access to the REST API for your WordPress site.

Out of the box the plugin can:

- Disable anonymous access to the REST API.
- Restrict and control anonymous access to the REST API by namespace, path, etc.

### Settings Page

The plugin can be configured via the Settings page (`Settings -> REST API Guard`) or via the relevant filter.

![Screenshot of plugin settings screen](https://user-images.githubusercontent.com/346399/194411352-aa05e939-3fd1-4e37-a3d5-276c1c5c288f.png)

### Preventing Access to User Information (`wp/v2/users`)

By default, the plugin will restrict anonymous access to the users endpoint. This can be prevented in the plugin's settings or via code:

	add_filter( 'rest_api_guard_allow_user_access', fn () => true );

### Preventing Access to Index (`/`) or Namespace Endpoints (`wp/v2`)

To prevent anonymous users from browsing your site and discovering what plugins/post types are setup, the plugin restricts access to the index (`/`) and namespace (`wp/v2`) endpoints. This can be prevented in the plugin's settings or via code:

	// Allow index access.
	add_filter( 'rest_api_guard_allow_index_access', fn () => true );

	// Allow namespace access.
	add_filter( 'rest_api_guard_allow_namespace_access', fn ( string $namespace ) => true );

### Restrict Anonymous Access to the REST API

The plugin can restrict anonymous access for any request to the REST API in the plugin's settings or via code:

	add_filter( 'rest_api_guard_prevent_anonymous_access', fn () => true );

### Limit Anonymous Access to Specific Namespaces/Routes (Allowlist)

Anonymous users can be granted access only to specific namespaces/routes. Requests outside of these paths will be denied. This can be configured in the plugin's settings or via code:

	add_filter(
		'rest_api_guard_anonymous_requests_allowlist',
		function ( array $paths, WP_REST_Request $request ): array {
			// Allow other paths not included here will be denied.
			$paths[] = 'wp/v2/post';
			$paths[] = 'custom-namespace/v1/public/*';

			return $paths;
		},
		10,
		2
	);

### Restrict Anonymous Access to Specific Namespaces/Routes (Denylist)

Anonymous users can be restricted from specific namespaces/routes. This acts as a denylist for specific paths that an anonymous user cannot access. The paths support regular expressions for matching. The use of the [Allowlist](#limit-anonymous-access-to-specific-namespacesroutes-allowlist) takes priority over this denylist. This can be configured in the plugin's settings or via code:

	add_filter(
		'rest_api_guard_anonymous_requests_denylist',
		function ( array $paths, WP_REST_Request $request ): array {
			$paths[] = 'wp/v2/user';
			$paths[] = 'custom-namespace/v1/private/*';

			return $paths;
		},
		10,
		2
	);
