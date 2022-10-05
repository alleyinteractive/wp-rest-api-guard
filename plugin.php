<?php
/**
 * Plugin Name: rest-api-guard
 * Plugin URI: https://github.com/alleyinteractive/wp-rest-api-guard
 * Description: Restrict and control access to the REST API
 * Version: 0.1.0
 * Author: Sean Fisher
 * Author URI: https://github.com/alleyinteractive/wp-rest-api-guard
 * Requires at least: 5.9
 * Tested up to: 5.9
 *
 * Text Domain: plugin_domain
 * Domain Path: /languages/
 *
 * @package rest-api-guard
 */

namespace Alley\WP\REST_API_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if Composer is installed.
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	\add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Composer is not installed and rest-api-guard cannot load. Try using a `*-built` branch if the plugin is being loaded as a submodule.', 'plugin_domain' ); ?></p>
			</div>
			<?php
		}
	);

	return;
}

// Load Composer dependencies.
require_once __DIR__ . '/vendor/autoload.php';

// Load the plugin's main files.
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/src/assets.php';
require_once __DIR__ . '/src/meta.php';

/**
 * Instantiate the plugin.
 */
function main() {
	// ...
}
main();
