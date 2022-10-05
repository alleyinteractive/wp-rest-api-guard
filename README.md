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

Activate the plugin in WordPress and use it like so:

```php
$plugin = Alley\WP\REST_API_Guard\Rest_Api_Guard\Rest_Api_Guard();
$plugin->perform_magic();
```

## Testing

Run `npm run test` to run Jest tests against JavaScript files. Run
`npm run test:watch` to keep the test runner open and watching for changes.

Run `npm run lint` to run ESLint against all JavaScript files. Linting will also
happen when running development or production builds.

Run `composer test` to run tests against PHPUnit and the PHP code in the plugin.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

This project is actively maintained by [Alley
Interactive](https://github.com/alleyinteractive). Like what you see? [Come work
with us](https://alley.co/careers/).

- [Sean Fisher](https://github.com/Sean Fisher)
- [All Contributors](../../contributors)

## License

The GNU General Public License (GPL) license. Please see [License File](LICENSE) for more information.
