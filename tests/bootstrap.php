<?php
/**
 * rest-api-guard Test Bootstrap
 */

/**
 * Visit {@see https://mantle.alley.com/testing/test-framework.html} to learn more.
 */
\Mantle\Testing\manager()
	->with_sqlite()
	->maybe_rsync_plugin()
	->loaded( fn () => require_once __DIR__ . '/../plugin.php' )
	->install();
