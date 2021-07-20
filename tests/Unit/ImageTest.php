<?php

namespace Tribe\Storage\Plugin\Statically\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Tribe\Storage\Plugin\Statically\Image;
use Tribe\Storage\Plugin\Statically\Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class ImageTest extends TestCase {

	public function test_it_ignores_url_modification() {
		Functions\when( 'esc_url' )->returnArg( 1 );

		$url = ( new Image() )->attachment_url( 'https://example.com/wp-content/uploads/sites/2/2021/06/test.jpg', 123 );
		$this->assertSame( 'https://example.com/wp-content/uploads/sites/2/2021/06/test.jpg', $url );
	}

	public function test_it_ignores_url_modification_when_proxy_enabled() {
		define( 'TRIBE_STORAGE_STATICALLY_PROXY', true );
		Functions\when( 'esc_url' )->returnArg( 1 );

		$url = ( new Image() )->attachment_url( 'https://example.com/wp-content/uploads/sites/2/2021/06/test.jpg', 123 );
		$this->assertSame( 'https://example.com/wp-content/uploads/sites/2/2021/06/test.jpg', $url );
	}

	public function test_it_uses_storage_url_when_not_an_image_and_no_proxy_is_defined() {
		define( 'TRIBE_STORAGE_URL', 'https://s3-us-east-1.amazonaws.com/test-bucket' );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\expect( 'get_post_mime_type' )->once()->with( 123 )->andReturn( 'video/mp4' );

		$url = ( new Image() )->attachment_url( 'https://s3-us-east-1.amazonaws.com/test-bucket/sites/2/2021/06/test.jpg', 123 );
		$this->assertSame( 'https://s3-us-east-1.amazonaws.com/test-bucket/sites/2/2021/06/test.jpg', $url );
	}

	public function test_it_changes_to_statically_io_url() {
		define( 'TRIBE_STORAGE_URL', 'https://account.blob.core.windows.net/prod' );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\expect( 'get_post_mime_type' )->once()->with( 123 )->andReturn( 'image/jpeg' );

		$url = ( new Image() )->attachment_url( 'https://account.blob.core.windows.net/prod/sites/2/2021/06/test.jpg', 123 );
		$this->assertSame( 'https://cdn.statically.io/img/account.blob.core.windows.net/prod/sites/2/2021/06/test.jpg', $url );
	}

	public function test_it_downsizes_core_image_size() {
		define( 'TRIBE_STORAGE_STATICALLY_PROXY', true );
		define( 'WP_CONTENT_URL', 'https://example.com/wp-content' );

		$url  = 'https://example.com/wp-content/uploads/sites/2/2021/06/test.jpg';
		$size = 'medium';

		Functions\when( 'wp_basename' )->justReturn( 'test.jpg' );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( false );
		Functions\expect( 'wp_attachment_is_image' )->once()->with( 123 )->andReturn( true );
		Functions\expect( 'wp_get_attachment_metadata' )->once()->with( 123 ) ->andReturn( [
			'sizes' => [
				'medium' => [
					'width'  => 150,
					'height' => 150,
					'crop'   => true,
				],
			],
		] );
		Functions\expect( 'wp_get_attachment_url' )->once()->with( 123 )->andReturn( $url );
		Functions\expect( 'get_post_mime_type' )->once()->with( 123 )->andReturn( 'image/jpeg' );
		Functions\expect( 'image_get_intermediate_size' )->once()->with( 123, $size )->andReturn( [
			'url' => 'https://example.com/wp-content/uploads/sites/2/2021/06/test-150x150.jpg'
		] );
		Functions\expect( 'image_constrain_size_for_editor' )->once()->with( 150, 150, $size )->andReturn( [
			150,
			150,
		] );

		$image = new Image();

		$result = $image->downsize( false, 123, $size );

		$this->assertSame( [
			'https://example.com/wp-content/uploads/f=auto,w=150,h=150/sites/2/2021/06/test-150x150.jpg',
			150,
			150,
			true
		], $result );
	}

	public function test_it_downsizes_custom_image_size() {
		define( 'TRIBE_STORAGE_STATICALLY_PROXY', true );
		define( 'WP_CONTENT_URL', 'https://example.com/wp-content' );

		$url  = 'https://example.com/wp-content/uploads/sites/2/2021/06/test.jpg';
		$size = [
			250, // width
			250, // height
		];

		Functions\when( 'wp_basename' )->justReturn( 'test.jpg' );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( false );
		Functions\expect( 'wp_attachment_is_image' )->once()->with( 123 )->andReturn( true );
		Functions\expect( 'wp_get_attachment_metadata' )->once()->with( 123 )->andReturn( [] );
		Functions\expect( 'wp_get_attachment_url' )->once()->with( 123 )->andReturn( $url );
		Functions\expect( 'get_post_mime_type' )->once()->with( 123 )->andReturn( 'image/jpeg' );
		Functions\expect( 'image_get_intermediate_size' )->once()->with( 123, $size )->andReturn( [
			'url' => 'https://example.com/wp-content/uploads/sites/2/2021/06/test-250x250.jpg'
		] );
		Functions\expect( 'image_constrain_size_for_editor' )->once()->with( 250, 250, $size )->andReturn( [
			250,
			250,
		] );


		$image = new Image();

		$result = $image->downsize( true, 123, $size );

		$this->assertSame( [
			'https://example.com/wp-content/uploads/f=auto,w=250,h=250/sites/2/2021/06/test-250x250.jpg',
			250,
			250,
			true
		], $result );
	}

	public function test_it_filters_srcset_sizes_with_statically_proxy() {
		$original_sources = [
			150  =>
				[
					'url'        => 'https://example.com/wp-content/uploads/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 150,
				],
			300  =>
				[
					'url'        => 'https://example.com/wp-content/uploads/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => '300',
				],
			1024 =>
				[
					'url'        => 'https://example.com/wp-content/uploads/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => '1024',
				],
			1536 =>
				[
					'url'        => 'https://example.com/wp-content/uploads/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 1536,
				],
			2048 =>
				[
					'url'        => 'https://example.com/wp-content/uploads/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 2048,
				],
			376  =>
				[
					'url'        => 'https://example.com/wp-content/uploads/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 376,
				],
			650  =>
				[
					'url'        => 'https://example.com/wp-content/uploads/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 650,
				],
			1500 =>
				[
					'url'        => 'https://example.com/wp-content/uploads/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 1500,
				],
		];
		$size_array = [
			1500,
			1500,
		];
		$img_src = 'https://example.com/wp-content/uploads/f=auto,w=1500,h=1500/sites/4/2021/06/sample-1500.png';

		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\expect( 'get_post_mime_type' )->once()->with( 123 )->andReturn( 'image/png' );

		$image   = new Image();
		$results = $image->filter_srcset( $original_sources, $size_array, $img_src, [], 123 );

		$expected = [
			150  =>
				[
					'url'        => 'https://example.com/wp-content/uploads/f=auto,w=150/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 150,
				],
			300  =>
				[
					'url'        => 'https://example.com/wp-content/uploads/f=auto,w=300/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => '300',
				],
			1024 =>
				[
					'url'        => 'https://example.com/wp-content/uploads/f=auto,w=1024/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => '1024',
				],
			1536 =>
				[
					'url'        => 'https://example.com/wp-content/uploads/f=auto,w=1536/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 1536,
				],
			2048 =>
				[
					'url'        => 'https://example.com/wp-content/uploads/f=auto,w=2048/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 2048,
				],
			376  =>
				[
					'url'        => 'https://example.com/wp-content/uploads/f=auto,w=376/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 376,
				],
			650  =>
				[
					'url'        => 'https://example.com/wp-content/uploads/f=auto,w=650/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 650,
				],
			1500 =>
				[
					'url'        => 'https://example.com/wp-content/uploads/f=auto,w=1500/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 1500,
				],
		];

		$this->assertSame( $expected, $results );
	}

	public function test_it_filters_srcset_sizes_with_cloud_provider_url() {
		$original_sources = [
			150  =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 150,
				],
			300  =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => '300',
				],
			1024 =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => '1024',
				],
			1536 =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 1536,
				],
			2048 =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 2048,
				],
			376  =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 376,
				],
			650  =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 650,
				],
			1500 =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 1500,
				],
		];
		$size_array = [
			1500,
			1500,
		];
		$img_src = 'https://account.blob.core.windows.net/f=auto,w=1500,h=1500/container/sites/4/2021/06/sample-1500.png';

		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\expect( 'get_post_mime_type' )->once()->with( 123 )->andReturn( 'image/png' );

		$image   = new Image();
		$results = $image->filter_srcset( $original_sources, $size_array, $img_src, [], 123 );

		$expected = [
			150  =>
				[
					'url'        => 'https://account.blob.core.windows.net/f=auto,w=150/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 150,
				],
			300  =>
				[
					'url'        => 'https://account.blob.core.windows.net/f=auto,w=300/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => '300',
				],
			1024 =>
				[
					'url'        => 'https://account.blob.core.windows.net/f=auto,w=1024/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => '1024',
				],
			1536 =>
				[
					'url'        => 'https://account.blob.core.windows.net/f=auto,w=1536/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 1536,
				],
			2048 =>
				[
					'url'        => 'https://account.blob.core.windows.net/f=auto,w=2048/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 2048,
				],
			376  =>
				[
					'url'        => 'https://account.blob.core.windows.net/f=auto,w=376/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 376,
				],
			650  =>
				[
					'url'        => 'https://account.blob.core.windows.net/f=auto,w=650/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 650,
				],
			1500 =>
				[
					'url'        => 'https://account.blob.core.windows.net/f=auto,w=1500/container/sites/4/2021/06/sample-1500.png',
					'descriptor' => 'w',
					'value'      => 1500,
				],
		];

		$this->assertSame( $expected, $results );
	}

	public function test_it_ignores_svg_sizes() {
		$original_sources = [
			150  =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample.svg',
					'descriptor' => 'w',
					'value'      => 150,
				],
			300  =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample.svg',
					'descriptor' => 'w',
					'value'      => '300',
				],
			1500 =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample.svg',
					'descriptor' => 'w',
					'value'      => 1500,
				],
		];
		$size_array = [
			1500,
			1500,
		];
		$img_src = 'https://example.com/wp-content/uploads/f=auto,w=1500,h=1500/sites/4/2021/06/sample.svg';

		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\expect( 'get_post_mime_type' )->once()->with( 123 )->andReturn( 'image/svg+xml' );

		$image   = new Image();
		$results = $image->filter_srcset( $original_sources, $size_array, $img_src, [], 123 );

		$expected = [
			150  =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample.svg',
					'descriptor' => 'w',
					'value'      => 150,
				],
			300  =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample.svg',
					'descriptor' => 'w',
					'value'      => '300',
				],
			1500 =>
				[
					'url'        => 'https://account.blob.core.windows.net/container/sites/4/2021/06/sample.svg',
					'descriptor' => 'w',
					'value'      => 1500,
				],
		];

		$this->assertSame( $expected, $results );
	}

	public function test_it_removes_uncropped_image_sizes() {
		$original_sizes = [
			'thumbnail' => [
				'width'  => 150,
				'height' => 150,
				'crop'   => true,
			],
			'medium'    => [
				'width'  => 300,
				'height' => 300,
				'crop'   => false,
			],
			'large'     => [
				'width'  => 600,
				'height' => 500,
				'crop'   => false,
			],
		];

		$image = new Image();

		$sizes = $image->remove_uncropped_image_meta( $original_sizes );
		$expected = [
			'thumbnail' => [
				'width'  => 150,
				'height' => 150,
				'crop'   => true,
			],
		];

		$this->assertCount( 1, $sizes );
		$this->assertSame( $expected, $sizes );
	}

	public function test_it_ignores_thumbnail_generation() {
		$original_sizes = [
			'thumbnail' => [
				'width'  => 150,
				'height' => 150,
				'crop'   => true,
			],
			'medium'    => [
				'width'  => 300,
				'height' => 300,
				'crop'   => false,
			],
			'large'     => [
				'width'  => 600,
				'height' => 500,
				'crop'   => false,
			],
		];

		// Disable cropped thumbnail creation
		Filters\expectApplied( 'tribe/storage/plugin/statically/create_thumbnails' )
			->once()
			->andReturn( false );

		$image = new Image();
		$sizes = $image->remove_uncropped_image_meta( $original_sizes );

		$this->assertEmpty( $sizes );
	}

}
