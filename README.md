# rest-api-guard

Stable tag: 0.1.0

Requires at least: 5.9

Tested up to: 5.9

Requires PHP: 7.4

License: GPL v2 or later

Tags: alleyinteractive, rest-api-guard

Contributors: srtfisher

[![Coding Standards](https://github.com/alleyinteractive/wp-rest-api-guard/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/alleyinteractive/wp-rest-api-guard/actions/workflows/coding-standards.yml)
[![Testing Suite](https://github.com/alleyinteractive/wp-rest-api-guard/actions/workflows/unit-test.yml/badge.svg)](https://github.com/alleyinteractive/wp-rest-api-guard/actions/workflows/unit-test.yml)

Restrict and control access to the REST API

## Installation

You can install the package via composer:

```bash
composer require alleyinteractive/wp-rest-api-guard
```

## Usage

The WordPress REST API is generally very public and can share a good deal of
information with the internet anonymously. This plugin aims to make it easier to
restrict access to the REST API for your WordPress site.

### Preventing Access to User Information (`wp/v2/users`)

By default, the plugin will restrict anonymous access to the users endpoint.
This can be prevented in the plugin's settings or via code:

```php
add_filter( 'rest_api_guard_allow_user_access', fn () => true );
```

### Restrict Anonymous Access to the REST API

The plugin can restrict anonymous access for any request to the REST API:

[screenshot from settings]

Or via code:

```php
add_filter( 'rest_api_guard_prevent_anonymous_access', fn () => true );
```

### Limit Anonymous Access to Specific Namespaces/Routes (Allowlist)

Anonymous users can be granted access only to specific namespaces/routes.
Requests outside of these paths will be denied.

[screenshot from settings]

Or via code:

```php
add_filter(
	'rest_api_guard_anonymous_requests_allowlist',
	function ( array $paths, WP_REST_Request $request ) {
		// Allow other paths not included here will be denied.
		$paths[] = 'wp/v2/post';
		$paths[] = 'custom-namespace/v1/public/*';

		return $paths;
	},
	10,
	2
);
```

### Restrict Anonymous Access to Specific Namespaces/Routes (Denylist)

Anonymous users can be restricted from specific namespaces/routes. This acts as
a denylist for specific paths that an anonymous user cannot access. The paths
support regular expressions for matching. The use of the
[Allowlist](#limit-anonymous-access-to-specific-namespacesroutes-allowlist)
takes priority over this denylist.

[screenshot from settings]

Or via code:

```php
add_filter(
	'rest_api_guard_anonymous_requests_denylist',
	function ( array $paths, WP_REST_Request $request ) {
		$paths[] = 'wp/v2/user';
		$paths[] = 'custom-namespace/v1/private/*';

		return $paths;
	},
	10,
	2
);
```

## Testing

Run `composer test` to run tests against PHPUnit and the PHP code in the plugin.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

This project is actively maintained by [Alley
Interactive](https://github.com/alleyinteractive). Like what you see? [Come work
with us](https://alley.co/careers/).

![Alley logo](https://avatars.githubusercontent.com/u/1733454?s=200&v=4)

- [Sean Fisher](https://github.com/srtfisher)
- [All Contributors](../../contributors)

## License

The GNU General Public License (GPL) license. Please see [License File](LICENSE) for more information.
