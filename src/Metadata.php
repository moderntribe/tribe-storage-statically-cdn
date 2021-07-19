<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin\Statically;

use Tribe\Storage\Cache\Cache;

/**
 * Class Metadata
 *
 * @package Tribe\Storage\Plugin\Statically
 */
class Metadata {

	public const CACHE_KEY_PREFIX = 'sizes_';

	/**
	 * @var \Tribe\Storage\Cache\Cache
	 */
	protected $cache;

	/**
	 * Metadata constructor.
	 *
	 * @param  \Tribe\Storage\Cache\Lru  $cache
	 */
	public function __construct( Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Dynamically add image sizes when meta is requested.
	 *
	 * @filter wp_get_attachment_metadata
	 *
	 * @param  bool|array  $data
	 * @param  int         $attachment_id
	 *
	 * @return array|bool
	 */
	public function get( $data, int $attachment_id ) {
		if ( empty( $data ) ) {
			return $data;
		}

		$cache = $this->cache->get( self::CACHE_KEY_PREFIX . $attachment_id );

		if ( null !== $cache ) {
			return $cache;
		}

		$sizes                      = [];
		$_wp_additional_image_sizes = wp_get_additional_image_sizes();
		$intermediate_image_sizes   = get_intermediate_image_sizes();
		$mime                       = get_post_mime_type( $attachment_id );
		$path                       = get_attached_file( $attachment_id );
		$file                       = basename( $path );

		foreach ( $intermediate_image_sizes as $s ) {

			// Only use existing sizes if we're generating cropped thumbnails
			if ( apply_filters( 'tribe/storage/plugin/statically/create_thumbnails', true ) ) {
				if ( isset( $data['sizes'][ $s ] ) ) {
					$sizes[ $s ] = $data['sizes'][ $s ];
					continue;
				}
			}

			$sizes[ $s ] = [
				'width'  => '',
				'height' => '',
				'crop'   => false,
			];

			if ( isset( $_wp_additional_image_sizes[ $s ]['width'] ) ) {
				// For theme-added sizes.
				$sizes[ $s ]['width'] = (int) $_wp_additional_image_sizes[ $s ]['width'];
			} else {
				// For default sizes set in options.
				$sizes[ $s ]['width'] = get_option( "{$s}_size_w" );
			}

			if ( isset( $_wp_additional_image_sizes[ $s ]['height'] ) ) {
				// For theme-added sizes.
				$sizes[ $s ]['height'] = (int) $_wp_additional_image_sizes[ $s ]['height'];
			} else {
				// For default sizes set in options.
				$sizes[ $s ]['height'] = get_option( "{$s}_size_h" );
			}

			if ( isset( $_wp_additional_image_sizes[ $s ]['crop'] ) ) {
				// For theme-added sizes.
				$sizes[ $s ]['crop'] = $_wp_additional_image_sizes[ $s ]['crop'];
			} else {
				// For default sizes set in options.
				$sizes[ $s ]['crop'] = get_option( "{$s}_crop" );
			}

			$sizes[ $s ]['file']      = $file;
			$sizes[ $s ]['mime-type'] = $mime;
		}

		$data['sizes'] = $sizes;

		$this->cache->set( self::CACHE_KEY_PREFIX . $attachment_id, $data );

		return $data;
	}

}
