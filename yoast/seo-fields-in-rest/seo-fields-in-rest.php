<?php
/**
 * Yoast SEO: make the SEO title and meta description writable via the REST API.
 *
 * Yoast stores them in post meta (_yoast_wpseo_title, _yoast_wpseo_metadesc)
 * but does not register that meta for REST, so it is missing from the `meta`
 * object of /wp/v2/posts and cannot be written by a script.
 *
 * Registering the existing keys exposes them to users who can edit the post.
 * Nothing changes on the front end: Yoast keeps reading the same fields, and
 * editors keep seeing the values in the Yoast box.
 *
 * Requires: Yoast SEO.
 *
 * @package WP_Snippets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Yoast fields on every post type that is in the REST API.
 */
function wpsnip_register_yoast_rest_meta() {
	$post_types = get_post_types(
		array(
			'public'       => true,
			'show_in_rest' => true,
		)
	);

	foreach ( $post_types as $post_type ) {
		// The REST API only exposes `meta` for post types that support custom fields.
		if ( ! post_type_supports( $post_type, 'custom-fields' ) ) {
			continue;
		}

		foreach ( array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ) as $meta_key ) {
			register_post_meta(
				$post_type,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					// Keys starting with "_" are protected; this decides who may read and write them.
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}
}
// Late, so post types registered by plugins on init already exist.
add_action( 'init', 'wpsnip_register_yoast_rest_meta', 99 );
