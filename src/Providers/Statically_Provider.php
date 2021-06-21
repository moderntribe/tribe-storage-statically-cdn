<?php declare(strict_types=1);

namespace Tribe\Storage\Statically\Providers;

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
		// Disable automatic creation of thumbnails, we generate them on the fly.
		add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );

		add_filter( 'image_downsize', function ( $downsize, $id, $size ) {
			return $this->image->downsize( $downsize, (int) $id, $size );
		}, 5, 3 );

		add_filter( 'tribe/storage/attachment_url', function ( $url ) {
			return $this->image->attachment_url( (string) $url );
		} );

		add_filter( 'wp_get_attachment_metadata', function ( $data, $attachment_id ) {
			return $this->metadata->get( $data, (int) $attachment_id );
		}, 20, 2 );
	}

}
