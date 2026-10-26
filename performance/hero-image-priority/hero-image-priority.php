<?php
/**
 * Make the right image win the LCP race.
 *
 * Two fixes for layouts built in page builders:
 *
 * 1. A separate mobile hero image (hidden on desktop, and the desktop one
 *    hidden on mobile) never gets fetchpriority="high". WordPress and Elementor
 *    give it to the first large image in the HTML, which is the desktop copy.
 *    This preloads the mobile image for small screens only, and keeps it from
 *    being lazy-loaded.
 * 2. A logo with its intrinsic size (an SVG exported at 2000x500, say) can be
 *    "the first large image" and steal fetchpriority from the hero. This gives
 *    configured images their display size instead, same aspect ratio, which
 *    stays below the 50,000 px² threshold that WordPress core
 *    (wp_min_priority_img_pixels) and Elementor's image optimisation use.
 *
 * @package WP_Snippets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings. Change the values here or filter them.
 *
 * @return array{condition: callable, mobile_image: int, mobile_media: string, eager_images: int[], display_sizes: array<int, int[]>}
 */
function wpsnip_hero_priority_config() {
	return apply_filters(
		'wpsnip_hero_priority_config',
		array(
			// Where the mobile hero is used.
			'condition'     => 'is_front_page',
			// Attachment ID of the image shown only below the breakpoint. 0 = off.
			'mobile_image'  => 0,
			'mobile_media'  => '(max-width: 767px)',
			// Above-the-fold attachment IDs that must never be lazy-loaded.
			'eager_images'  => array(),
			// Attachment ID => array( width, height ) at display size, same aspect
			// ratio as the file. Typical use: the logo.
			'display_sizes' => array(),
		)
	);
}

/**
 * Preloads the mobile hero for small screens only.
 */
function wpsnip_preload_mobile_hero() {
	$config = wpsnip_hero_priority_config();
	$id     = (int) $config['mobile_image'];

	if ( ! $id || ! call_user_func( $config['condition'] ) ) {
		return;
	}

	$src = wp_get_attachment_image_url( $id, 'full' );

	if ( ! $src ) {
		return;
	}

	$srcset = wp_get_attachment_image_srcset( $id, 'full' );
	$sizes  = wp_get_attachment_image_sizes( $id, 'full' );

	// Same srcset/sizes as the <img>, so the browser reuses the preloaded file.
	printf(
		'<link rel="preload" as="image" href="%1$s"%2$s%3$s media="%4$s" fetchpriority="high">' . "\n",
		esc_url( $src ),
		$srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : '',
		$sizes ? ' imagesizes="' . esc_attr( $sizes ) . '"' : '',
		esc_attr( $config['mobile_media'] )
	);
}
add_action( 'wp_head', 'wpsnip_preload_mobile_hero', 1 );

/**
 * Display size for configured images, at the source.
 *
 * The wp_get_attachment_image() function reads width/height from here and
 * decides on fetchpriority right after, so this is early enough for core.
 *
 * @param array|false $image         Array of url, width, height, is_intermediate; or false.
 * @param int         $attachment_id Attachment ID.
 * @return array|false
 */
function wpsnip_hero_priority_image_src( $image, $attachment_id ) {
	$sizes = wpsnip_hero_priority_config()['display_sizes'];

	if ( is_array( $image ) && isset( $sizes[ $attachment_id ] ) ) {
		list( $image[1], $image[2] ) = array_map( 'intval', $sizes[ $attachment_id ] );
	}

	return $image;
}
add_filter( 'wp_get_attachment_image_src', 'wpsnip_hero_priority_image_src', 10, 2 );

/**
 * Eager loading for above-the-fold images; display size again on the final
 * attributes, for images without file dimensions (SVG) and for optimisers that
 * read the finished HTML (Elementor).
 *
 * @param array    $attr       Image attributes.
 * @param \WP_Post $attachment Attachment.
 * @return array
 */
function wpsnip_hero_priority_image_attributes( $attr, $attachment ) {
	$config = wpsnip_hero_priority_config();
	$id     = (int) $attachment->ID;

	if ( isset( $config['display_sizes'][ $id ] ) ) {
		list( $attr['width'], $attr['height'] ) = array_map( 'intval', $config['display_sizes'][ $id ] );
	}

	$above_fold = in_array( $id, array_map( 'intval', $config['eager_images'] ), true )
		|| $id === (int) $config['mobile_image'];

	if ( $above_fold && call_user_func( $config['condition'] ) ) {
		$attr['loading'] = 'eager';
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'wpsnip_hero_priority_image_attributes', 10, 2 );
