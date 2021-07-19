<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin\Statically\Providers;

use Tribe\Storage\Plugin\Statically\Image;
use Tribe\Storage\Plugin\Statically\Metadata;
use Tribe\Storage\Providers\Providable;

/**
 * Service Provider for the Tribe Storage Statically Plugin.
 *
 * @package Tribe\Storage\Statically\Providers
 */
class Statically_Provider implements Providable {

	/**
	 * @var \Tribe\Storage\Plugin\Statically\Image
	 */
	private $image;

	/**
	 * @var \Tribe\Storage\Plugin\Statically\Metadata
	 */
	private $metadata;

	public function __construct( Image $image, Metadata $metadata ) {
		$this->image    = $image;
		$this->metadata = $metadata;
	}

	public function register(): void {

		/**
		 * Determine if we should create thumbnails for WordPress images that require cropping
		 * as statically.io does not support cropping ability the way WordPress crops images.
		 *
		 * If your image sizes do not require cropping or or you don't care about cropping you
		 * can disable this for an extra performance boost when uploading files as the system
		 * only needs to create a single image.
		 *
		 * @param bool $create Whether we should create cropped thumbnails or not
		 */
		add_filter( 'tribe/storage/plugin/statically/create_thumbnails', static function ( bool $create ): bool {
			if ( ! defined( 'TRIBE_STORAGE_STATICALLY_CREATE_THUMBNAILS' ) ) {
				return true;
			}

			return (bool) TRIBE_STORAGE_STATICALLY_CREATE_THUMBNAILS;
		}, 9, 1 );

		add_filter( 'intermediate_image_sizes_advanced', function ( $new_sizes ) {
			return $this->image->remove_uncropped_image_meta( $new_sizes );
		}, 10, 1 );

		add_filter( 'image_downsize', function ( $downsize, $id, $size ) {
			return $this->image->downsize( $downsize, (int) $id, $size );
		}, 10, 3 );

		add_filter( 'tribe/storage/attachment_url', function ( $url, $attachment_id ) {
			return $this->image->attachment_url( (string) $url, (int) $attachment_id );
		}, 10, 2 );

		add_filter( 'wp_calculate_image_srcset', function ( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
			return $this->image->filter_srcset( (array) $sources, (array) $size_array, (string) $image_src, (array) $image_meta, (int) $attachment_id );
		}, 10, 5 );

		add_filter( 'wp_get_attachment_metadata', function ( $data, $attachment_id ) {
			return $this->metadata->get( $data, (int) $attachment_id );
		}, 20, 2 );
	}

}
