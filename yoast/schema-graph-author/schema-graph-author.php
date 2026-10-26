<?php
/**
 * Yoast SEO: add author credentials to the Person node of the schema graph.
 *
 * Adds a "Job title" and "Areas of expertise" field to user profiles and
 * merges them into Yoast's existing Person node (jobTitle, knowsAbout,
 * worksFor). Editors keep the data up to date in wp-admin; no copy lives in
 * code.
 *
 * Gotcha this is built around: `wpseo_schema_graph` receives a flat list of
 * graph pieces. The {"@context": ..., "@graph": [...]} wrapper is added after
 * the filter runs, so code that looks for $data['@graph'] silently does
 * nothing.
 *
 * Requires: Yoast SEO.
 *
 * @package WP_Snippets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Profile fields and the schema.org property each one fills.
 *
 * @return array<string, array{label: string, property: string, list?: bool}>
 */
function wpsnip_author_schema_fields() {
	return apply_filters(
		'wpsnip_author_schema_fields',
		array(
			'wpsnip_job_title'   => array(
				'label'    => __( 'Job title', 'wp-snippets' ),
				'property' => 'jobTitle',
			),
			'wpsnip_knows_about' => array(
				'label'    => __( 'Areas of expertise (comma-separated)', 'wp-snippets' ),
				'property' => 'knowsAbout',
				'list'     => true,
			),
		)
	);
}

/**
 * Enriches the author's Person node.
 *
 * @param mixed $graph   Flat list of graph pieces.
 * @param mixed $context Yoast Meta_Tags_Context.
 * @return mixed
 */
function wpsnip_author_schema_graph( $graph, $context ) {
	if ( ! is_array( $graph ) || ! is_object( $context ) ) {
		return $graph;
	}

	// Author of a single post, or the user of an author archive.
	$user_id = 0;
	if ( ! empty( $context->post ) && is_singular() ) {
		$user_id = (int) $context->post->post_author;
	} elseif ( is_author() ) {
		$user_id = (int) get_queried_object_id();
	}

	if ( ! $user_id || ! function_exists( 'YoastSEO' ) ) {
		return $graph;
	}

	$person_id = YoastSEO()->helpers->schema->id->get_user_schema_id( $user_id, $context );
	$org_id    = '';

	foreach ( $graph as $piece ) {
		if ( is_array( $piece ) && in_array( 'Organization', (array) ( $piece['@type'] ?? array() ), true ) ) {
			$org_id = (string) ( $piece['@id'] ?? '' );
		}
	}

	foreach ( $graph as &$piece ) {
		if ( ! is_array( $piece ) || ( $piece['@id'] ?? '' ) !== $person_id ) {
			continue;
		}

		foreach ( wpsnip_author_schema_fields() as $meta_key => $field ) {
			$value = trim( (string) get_user_meta( $user_id, $meta_key, true ) );

			if ( '' === $value ) {
				continue;
			}

			$piece[ $field['property'] ] = empty( $field['list'] )
				? $value
				: array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
		}

		// Link the author to the site's organisation, if Yoast outputs one.
		if ( $org_id && ! empty( $piece['jobTitle'] ) && empty( $piece['worksFor'] ) ) {
			$piece['worksFor'] = array( '@id' => $org_id );
		}
	}
	unset( $piece );

	return $graph;
}
add_filter( 'wpseo_schema_graph', 'wpsnip_author_schema_graph', 10, 2 );

/**
 * Profile fields under "Users > Profile".
 *
 * @param \WP_User $user User being edited.
 */
function wpsnip_author_schema_profile_fields( $user ) {
	wp_nonce_field( 'wpsnip_author_schema', 'wpsnip_author_schema_nonce' );
	echo '<h2>' . esc_html__( 'Author details (structured data)', 'wp-snippets' ) . '</h2><table class="form-table" role="presentation">';

	foreach ( wpsnip_author_schema_fields() as $meta_key => $field ) {
		printf(
			'<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="%1$s" name="%1$s" value="%3$s"></td></tr>',
			esc_attr( $meta_key ),
			esc_html( $field['label'] ),
			esc_attr( (string) get_user_meta( $user->ID, $meta_key, true ) )
		);
	}

	echo '</table>';
}
add_action( 'show_user_profile', 'wpsnip_author_schema_profile_fields' );
add_action( 'edit_user_profile', 'wpsnip_author_schema_profile_fields' );

/**
 * Saves the profile fields.
 *
 * @param int $user_id User being saved.
 */
function wpsnip_author_schema_save_profile( $user_id ) {
	if (
		! current_user_can( 'edit_user', $user_id )
		|| ! isset( $_POST['wpsnip_author_schema_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsnip_author_schema_nonce'] ) ), 'wpsnip_author_schema' )
	) {
		return;
	}

	foreach ( array_keys( wpsnip_author_schema_fields() ) as $meta_key ) {
		if ( isset( $_POST[ $meta_key ] ) ) {
			update_user_meta( $user_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) );
		}
	}
}
add_action( 'personal_options_update', 'wpsnip_author_schema_save_profile' );
add_action( 'edit_user_profile_update', 'wpsnip_author_schema_save_profile' );
