<?php
/**
 * Plugin Name: WithWine ACF Integration
 * Plugin URI: https://github.com/benkanizay/withwine-acf-integration
 * Description: Provides an admin page to enter field keys, and then ACF filters are used to dynamically populate choices. Works with choice based fields: select, radio, checkbox.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Tested up to: 7.0.1
 * Author: Ben Kanizay
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: withwine-acf-integration
 */

defined( 'ABSPATH' ) || exit;

defined( 'WITHWINE_ACF_INTEGRATION_VERSION' )
|| define( 'WITHWINE_ACF_INTEGRATION_VERSION', '1.0.0' );

defined( 'WITHWINE_ACF_INTEGRATION_PATH' )
|| define(
	'WITHWINE_ACF_INTEGRATION_PATH',
	plugin_dir_path( __FILE__ )
);

defined( 'WITHWINE_ACF_CHOICES_VERSION' )
|| define( 'WITHWINE_ACF_CHOICES_VERSION', 1 );

defined( 'WITHWINE_ACF_CHOICES_CACHE_LIFETIME' )
|| define(
	'WITHWINE_ACF_CHOICES_CACHE_LIFETIME',
	DAY_IN_SECONDS
);

defined( 'WITHWINE_ACF_INTEGRATION_URL' )
|| define(
	'WITHWINE_ACF_INTEGRATION_URL',
	plugin_dir_url( __FILE__ )
);

/**
 * Return any unavailable plugin dependencies.
 */
function withwine_acf_integration_get_missing_dependencies(): array {

	$missing = array();

	if ( ! class_exists( 'WithWine' ) ) {
		$missing[] = 'WithWine';
	}

	if (
		! class_exists( 'ACF' ) &&
		! function_exists( 'acf' )
	) {
		$missing[] = 'Advanced Custom Fields';
	}

	return $missing;
}


/**
 * Prevent activation without WithWine and ACF.
 */
function withwine_acf_integration_activate(): void {

	$missing = withwine_acf_integration_get_missing_dependencies();

	if ( empty( $missing ) ) {
		return;
	}

	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	deactivate_plugins( plugin_basename( __FILE__ ) );

	wp_die(
		sprintf(
		/* translators: %s: list of required plugins. */
			esc_html__(
				'This plugin requires the following active plugin(s): %s.',
				'withwine-acf-integration'
			),
			esc_html( implode( ', ', $missing ) )
		),
		esc_html__(
			'Plugin dependency missing',
			'withwine-acf-integration'
		),
		array(
			'back_link' => true,
		)
	);
}

register_activation_hook(
	__FILE__,
	'withwine_acf_integration_activate'
);


/**
 * Display a notice if a dependency is later disabled.
 */
function withwine_acf_integration_dependency_notice(): void {

	$missing = withwine_acf_integration_get_missing_dependencies();

	if ( empty( $missing ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
			/* translators: %s: list of required plugins. */
				esc_html__(
					'WithWine ACF Integration is inactive because it requires: %s.',
					'withwine-acf-integration'
				),
				esc_html( implode( ', ', $missing ) )
			);
			?>
		</p>
	</div>
	<?php
}


/**
 * Initialise the integration.
 */
function withwine_acf_integration_init(): void {

	if ( ! empty( withwine_acf_integration_get_missing_dependencies() ) ) {
		add_action(
			'admin_notices',
			'withwine_acf_integration_dependency_notice'
		);

		return;
	}

	require_once WITHWINE_ACF_INTEGRATION_PATH
		. 'includes/class-withwine-acf-cache.php';

	require_once WITHWINE_ACF_INTEGRATION_PATH
		. 'includes/class-withwine-acf-data.php';

	require_once WITHWINE_ACF_INTEGRATION_PATH
		. 'includes/class-withwine-acf-admin.php';

	WithWine_ACF_Data::init();
	WithWine_ACF_Admin::init();

	add_action(
		'siteground_optimizer_flush_cache',
		array( 'WithWine_ACF_Cache', 'clear_all' )
	);
}

add_action(
	'plugins_loaded',
	'withwine_acf_integration_init',
	20
);
