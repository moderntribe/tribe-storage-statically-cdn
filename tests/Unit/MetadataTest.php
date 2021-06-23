<?php

namespace Tribe\Storage\Plugin\Statically\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use Tribe\Storage\Cache\Lru;
use Tribe\Storage\Plugin\Statically\Metadata;
use Tribe\Storage\Plugin\Statically\Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class MetadataTest extends TestCase {

	/**
	 * @var array|\Mockery\LegacyMockInterface|\Mockery\MockInterface|\Tribe\Storage\Cache\Lru
	 */
	private $cache;

	/**
	 * @var \Tribe\Storage\Plugin\Statically\Metadata
	 */
	private $metadata;

	protected function setUp(): void {
		parent::setUp();

		$this->cache    = Mockery::mock( Lru::class );
		$this->metadata = new Metadata( $this->cache );
	}

	public function test_it_gets_image_size_on_the_fly() {
		$original_meta = [
			'file'  => '2021/06/test.jpg',
			'sizes' => [],
		];

		$this->cache->shouldReceive( 'get' )->once()->with( Metadata::CACHE_KEY_PREFIX . 123 )->andReturnNull();

		Functions\expect( 'wp_get_additional_image_sizes' )->once()->andReturn( [
			'custom' => [
				'width'  => 500,
				'height' => 500,
				'crop'   => false,
			],
		] );
		Functions\expect( 'get_intermediate_image_sizes' )->once()->andReturn( [
			'medium',
			'custom',
		] );
		Functions\expect( 'get_post_mime_type' )->once()->with( 123 )->andReturn( 'image/jpeg' );
		Functions\expect( 'get_attached_file' )
			->once()
			->with( 123 )
			->andReturn( 'https://example.com/wp-content/uploads/2021/06/test.jpg' );
		Functions\expect( 'get_option' )->once()->with( 'medium_size_w' )->andReturn( 150 );
		Functions\expect( 'get_option' )->once()->with( 'medium_size_h' )->andReturn( 150 );
		Functions\expect( 'get_option' )->once()->with( 'medium_crop' )->andReturn( true );

		$expected = [
			'file'  => '2021/06/test.jpg',
			'sizes' => [
				'medium' => [
					'width'     => 150,
					'height'    => 150,
					'crop'      => true,
					'file'      => 'test.jpg',
					'mime-type' => 'image/jpeg',
				],
				'custom' => [
					'width'     => 500,
					'height'    => 500,
					'crop'      => false,
					'file'      => 'test.jpg',
					'mime-type' => 'image/jpeg',
				],
			],
		];

		$this->cache->shouldReceive( 'set' )->once()->with( Metadata::CACHE_KEY_PREFIX . 123, $expected );

		$data = $this->metadata->get( $original_meta, 123 );

		$this->assertSame( $expected, $data );
	}
}
