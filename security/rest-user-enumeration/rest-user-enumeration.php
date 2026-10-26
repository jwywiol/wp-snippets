<?php
/**
 * Block anonymous user enumeration through the REST API.
 *
 * Unauthenticated GET /wp-json/wp/v2/users lists every author with their
 * login-derived slug, half of a login. This blocks the users routes for
 * anonymous requests only. Logged-in users and application-password clients
 * keep full access, so the block editor's author picker and application
 * password management keep working.
 *
 * Works for /wp-json/... and ?rest_route=... alike, because it runs inside
 * the REST server, after authentication.
 *
 * @package WP_Snippets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rejects anonymous requests to /wp/v2/users and /wp/v2/users/{id}.
 *
 * @param mixed            $result  Response to short-circuit with, or null.
 * @param \WP_REST_Server  $server  REST server.
 * @param \WP_REST_Request $request Current request.
 * @return mixed
 */
function wpsnip_block_anonymous_user_enumeration( $result, $server, $request ) {
	if ( null !== $result || is_user_logged_in() ) {
		return $result;
	}

	$route = untrailingslashit( $request->get_route() );

	if ( '/wp/v2/users' === $route || str_starts_with( $route, '/wp/v2/users/' ) ) {
		return new WP_Error(
			'rest_user_cannot_view',
			__( 'Sorry, you are not allowed to list users.', 'wp-snippets' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', 'wpsnip_block_anonymous_user_enumeration', 10, 3 );
