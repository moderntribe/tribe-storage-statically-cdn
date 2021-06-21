<?php

/**
 * Bootstrap tests
 */

namespace Tribe\Storage\Plugin\Statically\Tests;

use DI\ContainerBuilder;
use Tribe\Storage\Core;
use Tribe\Storage\Plugin\Plugin_Loader;

define( 'VENDOR_DIR', __DIR__ . '/../vendor' );

/**
 * Shorthand to get the instance of our main core plugin class.
 *
 * @return mixed
 *
 * @throws \Exception
 */
function tribe_storage(): Core {
	$builder       = new ContainerBuilder();
	$plugin_loader = Plugin_Loader::get_instance();

	// Load plugin container definitions
	foreach ( $plugin_loader->definition_providers() as $definition_provider ) {
		$builder->addDefinitions( $definition_provider->get_definitions() );
	}

	$builder->addDefinitions( VENDOR_DIR . '/moderntribe/tribe-storage/config.php' );
	$container = $builder->build();

	return Core::instance( $container );
}
