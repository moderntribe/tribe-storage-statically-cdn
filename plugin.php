<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin;

use Tribe\Storage\Plugin\Statically\Providers\Statically_Provider;
use Tribe\Storage\Plugin\Statically\Statically_Definition_Provider;

$loader = Plugin_Loader::get_instance();

// Load this plugin's container definitions
$loader->add_definition_provider( new Statically_Definition_Provider() );

// Load this plugin's service providers
$loader->add_service_provider( Statically_Provider::class );
