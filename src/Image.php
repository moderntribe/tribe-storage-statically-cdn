<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin\Statically;

/**
 * Class Image
 *
 * @package Tribe\Storage\Plugin\Statically
 */
class Image {

	public const CACHE_GROUP = 'tribe_storage';

	/**
	 * Update the main attachment URL depending on the proxy strategy
	 * being used.
	 *
	 * @filter tribe/storage/attachment_url
	 *
	 * @param  string  $url
	 *
	 * @return string
	 */
	public function attachment_url( string $url ): string {
		if ( ! defined( 'TRIBE_STORAGE_URL' ) || ! TRIBE_STORAGE_URL ) {
			return $url;
		}

		if ( defined( 'TRIBE_STORAGE_STATICALLY_PROXY' ) && TRIBE_STORAGE_STATICALLY_PROXY ) {
			return $url;
		}

		return $this->build_statically_io_url( $url );
	}

	/**
	 * Modify the image URL.
	 *
	 * @filter image_downsize
	 *
	 * @see https://statically.io/docs/using-images/
	 *
	 * @param  bool|array    $downsize
	 * @param  int           $id
	 * @param  string|int[]  $size
	 *
	 * @return array|bool
	 */
	public function downsize( $downsize, int $id, $size ) {
		$cache_key = md5( "{$id}_$size" );

		$data = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( ! empty( $data ) ) {
			return $data;
		}

		$is_img = wp_attachment_is_image( $id );

		if ( empty( $is_img ) ) {
			return false;
		}

		$meta = wp_get_attachment_metadata( $id );

		if ( is_string( $size ) ) {
			if ( 'full' === $size ) {
				$size_data = [
					'width'  => $meta['width'] ?? 0,
					'height' => $meta['height'] ?? 0,
				];
			} else {
				$size_data = $meta['sizes'][ $size ] ?? [];
			}

			if ( empty( $size_data ) ) {
				return false;
			}
		}

		$url    = wp_get_attachment_url( $id );
		$width  = $size_data['width'] ?? 0;
		$height = $size_data['height'] ?? 0;

		/**
		 * Filter statically.io params.
		 *
		 * @see https://statically.io/docs/using-images/
		 *
		 * @param array $params The default statically params.
		 * @param int $id The attachment ID.
		 * @param string|int[]  $size The WordPress thumbnail size.
		 */
		$params = (array) apply_filters( 'tribe/storage/plugin/statically/params', [
			'f' => 'auto',
			'w' => $width,
			'h' => $height,
		], $id, $size );

		$params      = array_filter( $params );
		$params      = http_build_query( $params, '', ',' );
		$uploads_url = (string) apply_filters( 'tribe/storage/plugin/statically/wp_content_url', WP_CONTENT_URL . '/uploads/' );

		// Continue using the site URL as that will be proxied behind the scenes to statically.io if the user properly configured Nginx
		// e.g. https://domain.com/wp-content/uploads/f=auto,w=300,h=300/sites/4/2021/06/image.jpg
		if ( defined( 'TRIBE_STORAGE_STATICALLY_PROXY' ) && TRIBE_STORAGE_STATICALLY_PROXY ) {
			$path      = str_replace( $uploads_url, "$params/", $url );
			$proxy_url = $uploads_url . $path;
		} else {
			if ( ! defined( 'TRIBE_STORAGE_URL' ) || ! TRIBE_STORAGE_URL ) {
				error_log( 'constant TRIBE_STORAGE_URL is not defined! Define it to your provider\'s public cloud storage URL, e.g. https://account.blob.core.windows.net/container' );

				return false;
			}

			// Use cdn.statically.io directly
			// e.g. https://cdn.statically.io/img/$BUCKET_CNAME/f=auto,w=300,h=300/$BUCKET_PATH/sites/4/2021/06/image.jpg
			$storage_url = TRIBE_STORAGE_URL;
			$parsed      = parse_url( $storage_url );
			$domain      = $parsed['host'];
			$proxy_url   = str_replace( $domain, "$domain/$params", $url );
		}

		// we have the actual image size, but might need to further constrain it if
		// content_width is narrower
		[ $width, $height ] = image_constrain_size_for_editor( $width, $height, $size );

		/**
		 * Filter downsize data.
		 *
		 * @param array $data The downsize data.
		 * @param int $id The attachment ID.
		 * @param string|int[]  $size The WordPress thumbnail size.
		 *
		 * @type string $0 Image source URL.
		 * @type int    $1 Image width in pixels.
		 * @type int    $2 Image height in pixels.
		 * @type bool   $3 Whether the image is a resized image.
		 */
		$data = (array) apply_filters( 'tribe/storage/plugin/statically/image_downsize', [
			$proxy_url,
			$width,
			$height,
			true,
		], $id, $size );

		wp_cache_set( $cache_key, $data, self::CACHE_GROUP );

		return $data;
	}

	/**
	 * Build a statically.io CDN URL.
	 *
	 * @example https://cdn.statically.io/img/$BUCKET_CNAME/f=auto,w=300,h=300/$BUCKET_PATH/sites/4/2021/06/image.jpg
	 *
	 * @param  string  $original_url  The URL to modify.
	 *
	 * @return string
	 */
	protected function build_statically_io_url( string $original_url ): string {
		$storage_url = TRIBE_STORAGE_URL;
		$parsed      = parse_url( $storage_url );
		$domain      = $parsed['host'];
		$bucket      = $parsed['path'] ?? '';

		return str_replace( $storage_url, "https://cdn.statically.io/img/$domain$bucket", $original_url );
	}

}
