<?php
/**
 * Load a plugin's CSS and JS only on the posts that need it.
 *
 * Many plugins enqueue their assets on every page, even when only three
 * posts use them. This adds a private "Page assets" taxonomy: editors tick a
 * checkbox on the posts that need a feature, and everywhere else the plugin's
 * handles are dequeued.
 *
 * The taxonomy is private: no archive, no URL, no sitemap entry.
 *
 * @package WP_Snippets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Features that can be switched on per post.
 *
 * Key: term slug. `label`: checkbox text in the editor. `prefixes`: start of the
 * style/script handles the feature needs. Find them in the page source
 * (id="handle-css") or with Query Monitor.
 *
 * @return array<string, array{label: string, prefixes: string[]}>
 */
function wpsnip_page_asset_features() {
	return apply_filters(
		'wpsnip_page_asset_features',
		array(
			'copy-to-clipboard' => array(
				'label'    => 'Copy to clipboard buttons',
				'prefixes' => array( 'ctc-' ),
			),
		)
	);
}

/**
 * Registers the taxonomy and creates one term per feature.
 */
function wpsnip_register_page_assets_taxonomy() {
	register_taxonomy(
		'wpsnip_page_assets',
		apply_filters( 'wpsnip_page_assets_post_types', array( 'post', 'page' ) ),
		array(
			'labels'             => array(
				'name'          => __( 'Page assets', 'wp-snippets' ),
				'singular_name' => __( 'Page asset', 'wp-snippets' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_rest'       => true, // Panel in the block editor.
			'show_admin_column'  => true,
			'hierarchical'       => true, // Checkboxes instead of free text, so no typos.
			'rewrite'            => false,
			'query_var'          => false,
		)
	);

	if ( ! is_admin() ) {
		return;
	}

	foreach ( wpsnip_page_asset_features() as $slug => $feature ) {
		if ( ! term_exists( $slug, 'wpsnip_page_assets' ) ) {
			wp_insert_term( $feature['label'], 'wpsnip_page_assets', array( 'slug' => $slug ) );
		}
	}
}
// Late priority, so custom post types registered on init already exist.
add_action( 'init', 'wpsnip_register_page_assets_taxonomy', 20 );

/**
 * Dequeues the handles of every feature the current post does not use.
 */
function wpsnip_dequeue_unused_page_assets() {
	if ( is_admin() ) {
		return;
	}

	// Page builder previews render widgets without the post's terms in context.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$builder_preview = isset( $_GET['elementor-preview'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['ct_builder'] );

	if ( $builder_preview ) {
		return;
	}

	$post_id = is_singular() ? get_queried_object_id() : 0;

	foreach ( wpsnip_page_asset_features() as $slug => $feature ) {
		if ( $post_id && has_term( $slug, 'wpsnip_page_assets', $post_id ) ) {
			continue;
		}

		foreach ( array( wp_styles(), wp_scripts() ) as $registry ) {
			foreach ( $registry->queue as $handle ) {
				foreach ( $feature['prefixes'] as $prefix ) {
					if ( str_starts_with( $handle, $prefix ) ) {
						$registry->dequeue( $handle );
					}
				}
			}
		}
	}
}
// Last moment before printing: wp_print_styles()/wp_print_scripts() fire their
// action and then print, and core prints footer scripts at priority 10. Some
// plugins (and Elementor widgets) enqueue late, so earlier would miss them.
add_action( 'wp_print_styles', 'wpsnip_dequeue_unused_page_assets', PHP_INT_MAX );
add_action( 'wp_print_scripts', 'wpsnip_dequeue_unused_page_assets', PHP_INT_MAX );
add_action( 'wp_print_footer_scripts', 'wpsnip_dequeue_unused_page_assets', 9 );
