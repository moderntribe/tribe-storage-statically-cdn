<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin;

use Tribe\Storage\Plugin\Statically\Statically_Definition_Provider;
use Tribe\Storage\Statically\Providers\Statically_Provider;

// Load this plugin's container definitions
Plugin_Loader::get_instance()->add_definition_provider( new Statically_Definition_Provider() );

// Load this plugin's service providers
Plugin_Loader::get_instance()->add_service_provider( Statically_Provider::class );
