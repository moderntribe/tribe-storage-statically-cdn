<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin;

use Tribe\Storage\Plugin\Statically\Statically_Definition_Provider;
use Tribe\Storage\Statically\Providers\Statically_Provider;

// Load this plugin's container definitions
Plugin_Loader::get_instance()->add_definitions( new Statically_Definition_Provider() );

if ( function_exists( 'tribe_storage' ) && function_exists( 'add_filter' ) ) {
	add_filter( 'tribe/storage/providers', static function ( $providers ) {
		$providers[] = tribe_storage()->container()->make( Statically_Provider::class );

		return $providers;
	} );
}
