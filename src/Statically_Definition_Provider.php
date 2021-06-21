<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin\Statically;

use DI\Definition\Source\DefinitionArray;
use DI\Definition\Source\DefinitionSource;
use Tribe\Storage\Cache\Cache;
use Tribe\Storage\Cache\Lru;
use Tribe\Storage\Plugin\Definition_Provider;

class Statically_Definition_Provider implements Definition_Provider {

	public function get_definitions(): DefinitionSource {
		return new DefinitionArray( [
			Cache::class => static function () {
				return new Lru();
			},
		] );
	}

}
