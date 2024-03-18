# Changelog

All notable changes to `wp-rest-guard` will be documented in this file.

## v1.3.1 - 2024-03-18

- Ignore JWT authentication for the REST API if the user is already authenticated.

## v1.3.0 - 2024-02-27

- Allow the claims to be added to a generated JWT via filter.
- Don't check `OPTIONS` requests by default.

## v1.2.0 - 2024-02-22

- Add support for authenticated users interacting with the REST API.
- Allow settings to be completely disabled via code.
- Increase the default length of the JWT secret to 32 characters.

## v1.1.1 - 2024-01-15

- Re-releasing to re-trigger the deployment to WordPress.org.

## v1.1.0 - 2024-012-12

- Drops support for PHP 7.4 and requires PHP 8.0.
- Add feature to allow anonymous authentication with a JSON Web Token (JWT).

## v1.0.4 - 2024-01-12

- Fixing an issue splitting lines by `\n` instead of `\r\n` on Windows.
- Allow `/wp-json/` to be included in the allow/deny lists.

## v1.0.3 - 2023-08-28

- Bumping tested version to 6.3

## v1.0.2 - 2022-11-03

- Fixing another typo in the plugin name.

## v1.0.1 - 2022-10-26

- Fixing a typo on the settings page.

## v1.0.0 - 2022-10-19

- Stable re-release 🎊
