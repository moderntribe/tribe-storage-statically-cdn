<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin\Statically;

/**
 * An Image Attachment.
 *
 * @package Tribe\Storage\Plugin\Statically
 */
class Image {

	public const CACHE_GROUP = 'tribe_storage';

	/**
	 * Only generate thumbnails for cropped image sizes.
	 *
	 * @filter intermediate_image_sizes_advanced
	 *
	 * @param array $new_sizes
	 *
	 * @return array
	 */
	public function remove_uncropped_image_meta( array $new_sizes ): array {
		foreach ( $new_sizes as $name => $size ) {
			$crop = $size['crop'] ?? false;

			if ( ! $crop ) {
				unset( $new_sizes[ $name ] );
			}
		}

		return $new_sizes;
	}

	/**
	 * Update the main attachment URL depending on the proxy strategy
	 * being used.
	 *
	 * @filter tribe/storage/attachment_url
	 *
	 * @param string $url
	 * @param int    $attachment_id
	 *
	 * @return string
	 */
	public function attachment_url( string $url, int $attachment_id ): string {
		if ( ! defined( 'TRIBE_STORAGE_URL' ) || ! TRIBE_STORAGE_URL ) {
			return $url;
		}

		if ( defined( 'TRIBE_STORAGE_STATICALLY_PROXY' ) && TRIBE_STORAGE_STATICALLY_PROXY ) {
			return $url;
		}

		if ( ! $this->is_image( $attachment_id ) ) {
			return $url;
		}

		return esc_url( $this->build_statically_io_url( $url ) );
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
		$cache_key = md5( $id . '_' . json_encode( $size ) );

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

			$width  = $size_data['width'] ?? 0;
			$height = $size_data['height'] ?? 0;
		} else {
			[ $width, $height ] = $size;
		}

		$url              = wp_get_attachment_url( $id );
		$img_url_basename = wp_basename( $url );
		$intermediate     = image_get_intermediate_size( $id, $size );

		if ( $intermediate ) {
			$url = $intermediate['url'];
		}

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

		// Statically doesn't support size params with SVG's
		if ( $this->bypass_image_resizing( $id ) ) {
			$params = [];
		}

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
			$downsize,
		], $id, $size );

		wp_cache_set( $cache_key, $data, self::CACHE_GROUP );

		return $data;
	}

	/**
	 * Update each image size by replacing the main image's statically.io params with the proper dimensions.
	 *
	 * @filter wp_calculate_image_srcset
	 *
	 * @param array  $sources
	 * @param array  $size_array
	 * @param string $img_src
	 * @param array  $image_meta
	 * @param int    $attachment_id
	 *
	 * @return array
	 */
	public function filter_srcset( array $sources, array $size_array, string $img_src, array $image_meta, int $attachment_id ): array {
		if ( $this->bypass_image_resizing( $attachment_id ) ) {
			return (array) apply_filters( 'tribe/storage/plugin/statically/srcset/sources', $sources );
		}

		foreach ( $sources as &$source ) {
			if ( 'w' !== $source['descriptor'] ) {
				continue;
			}

			// Match statically.io params f=auto,w=500,h=500/
			$params = preg_match( "/.=.*?[\/]/", $img_src, $matches );

			if ( ! $params ) {
				continue;
			}

			$replace       = (string) apply_filters(
				'tribe/storage/plugin/statically/srcset/source_params',
				sprintf( 'f=auto,%s=%d/', $source['descriptor'], $source['value'] ),
				$params,
				$img_src,
				$source,
				$attachment_id,
			);
			$url           = str_replace( reset( $matches ), $replace, $img_src );
			$source['url'] = esc_url( $url );
		}

		return (array) apply_filters( 'tribe/storage/plugin/statically/srcset/sources', $sources );
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

	/**
	 * Check if this is an image.
	 *
	 * @param int $attachment_id
	 *
	 * @return bool
	 */
	protected function is_image( int $attachment_id ): bool {
		$mime_types = (array) apply_filters( 'tribe/storage/plugin/statically/image_mime_types', [
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/svg+xml',
		] );

		$mime_type = get_post_mime_type( $attachment_id );

		return in_array( $mime_type, $mime_types, true );
	}

	/**
	 * Whether we should bypass resizing this attachment.
	 *
	 * @param int $attachment_id
	 *
	 * @return bool
	 */
	protected function bypass_image_resizing( int $attachment_id ): bool {
		$mime_types = (array) apply_filters( 'tribe/storage/plugin/statically/bypass_resizing_mime_types', [
			'image/svg+xml',
		] );

		$mime_type = get_post_mime_type( $attachment_id );

		return in_array( $mime_type, $mime_types, true );
	}

}
