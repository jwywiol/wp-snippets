<?php
/**
 * Elementor Pro forms: reject submissions that bypassed the page's JavaScript.
 *
 * Elementor's front-end script always sends the page address as `referrer`
 * with every form submission. Spam bots that read the HTML and POST straight
 * to admin-ajax.php never run that script, so the field is missing.
 *
 * Such a submission gets Elementor's normal success response before any
 * action runs: nothing is saved, mailed or sent to a webhook, and the bot gets
 * no hint that it was detected.
 *
 * Requires: Elementor Pro (Forms module).
 *
 * @package WP_Snippets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stops submissions without the `referrer` field.
 *
 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record       Submitted form.
 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Request handler.
 */
function wpsnip_elementor_require_referrer( $record, $ajax_handler ) {
	// Elementor verifies the request before this hook; this only reads one field.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$referrer = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '';

	// Presence only, no domain check: the value is the page the form is on,
	// and it can legitimately differ (www vs. bare domain, translation proxies).
	if ( '' !== $referrer ) {
		return;
	}

	$settings = (array) $record->get( 'form_settings' );

	/**
	 * Fires when a submission is dropped, e.g. to log it.
	 *
	 * @param array $settings Form settings (id, form_name, form_post_id, ...).
	 */
	do_action( 'wpsnip_elementor_submission_blocked', $settings );

	// The handler starts in the "success" state, so send() answers with the
	// form's own success message and ends the request before any action runs.
	$ajax_handler->send();
}
add_action( 'elementor_pro/forms/validation', 'wpsnip_elementor_require_referrer', 5, 2 );
