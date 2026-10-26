<?php
/**
 * Elementor Pro form webhooks: same payload keys in every language (Polylang).
 *
 * Without "Advanced Data", the webhook body uses field labels and localised
 * meta titles ("Date", "Time", "Page URL") as keys. On a translated page the
 * labels are translated, so the receiving automation (Make, Zapier, n8n) gets
 * different keys and its mapping silently breaks.
 *
 * For a form on a translated page, this rebuilds the body the way the
 * default-language original of the same form would: keys from the original's
 * labels (matched by field ID) and meta titles in the default language. The
 * values and the real page URL are kept, so the language can still be told
 * from `page_url`.
 *
 * Requires: Elementor Pro (Forms), Polylang.
 *
 * @package WP_Snippets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites the webhook body of translated forms.
 *
 * @param array                                           $args   wp_remote_post() arguments.
 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record Submitted form.
 * @return array
 */
function wpsnip_elementor_webhook_default_language_keys( $args, $record ) {
	if ( ! function_exists( 'pll_get_post' ) || ! function_exists( 'pll_default_language' ) ) {
		return $args;
	}

	$settings = (array) $record->get( 'form_settings' );

	// With "Advanced Data" the keys are field IDs, which do not change between languages.
	if ( 'yes' === ( $settings['webhooks_advanced_data'] ?? '' ) ) {
		return $args;
	}

	$post_id = (int) ( $settings['form_post_id'] ?? 0 );
	$default = pll_default_language();

	if ( ! $post_id || ! $default || pll_get_post_language( $post_id ) === $default ) {
		return $args;
	}

	// Same form widget (same element ID) in the default-language original.
	$source_id = pll_get_post( $post_id, $default );
	$document  = $source_id ? \Elementor\Plugin::$instance->documents->get( $source_id ) : null;
	$source    = $document
		? \ElementorPro\Modules\Forms\Module::find_element_recursive( $document->get_elements_data(), $settings['id'] )
		: null;

	if ( empty( $source['settings']['form_fields'] ) ) {
		return $args;
	}

	$labels = array();
	foreach ( $source['settings']['form_fields'] as $field ) {
		$labels[ $field['custom_id'] ] = $field['field_label'] ?? '';
	}

	// Meta titles ("Date", "Page URL") and the date format in the default language.
	$meta         = (array) $record->get( 'meta' );
	$switched     = switch_to_locale( pll_default_language( 'locale' ) );
	$no_label     = esc_html__( 'No Label', 'elementor-pro' );
	$default_meta = $record->get_form_meta( array_keys( $meta ) );
	if ( $switched ) {
		restore_previous_locale();
	}

	$body = array();

	foreach ( (array) $record->get( 'fields' ) as $id => $field ) {
		$title = array_key_exists( $id, $labels ) ? $labels[ $id ] : ( $field['title'] ?? '' );
		$key   = '' === (string) $title ? $no_label . ' ' . $id : $title;

		$body[ $key ] = $field['value'];
	}

	foreach ( $meta as $key => $item ) {
		$title = $default_meta[ $key ]['title'] ?? $item['title'];
		// Keep the real page URL, so the submission's language stays visible.
		$value = 'page_url' === $key ? $item['value'] : ( $default_meta[ $key ]['value'] ?? $item['value'] );

		$body[ $title ] = $value;
	}

	$body['form_id']   = $settings['id'];
	$body['form_name'] = $settings['form_name'];

	$args['body'] = $body;

	return $args;
}
add_filter( 'elementor_pro/forms/webhooks/request_args', 'wpsnip_elementor_webhook_default_language_keys', 10, 2 );
