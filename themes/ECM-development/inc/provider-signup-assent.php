<?php
/**
 * Terms/Privacy assent on the provider signup form — Privacy Compliance:
 * Developer Requirements, item 4 (same sentence/placement as item 3, on
 * the provider signup form). That form is a Contact Form 7 shortcode
 * (f16beae on the For Providers page), so the sentence is injected into
 * CF7's own rendered markup, immediately before its submit button —
 * editing the form content via CF7's admin UI couldn't guarantee it stays
 * pinned to the button, and must never become a checkbox (item 5).
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wpcf7_form_elements', function ( string $html ) : string {
	if ( ! function_exists( 'ecm_assent_sentence_html' ) ) {
		return $html;
	}
	$assent = '<p class="ecm-form-assent">' . ecm_assent_sentence_html() . '</p>';

	// CF7's submit control has varied by version: an <input type="submit">
	// in older markup, a <button type="submit"> in current releases. Match
	// either rather than assuming one. If neither is found (a future markup
	// change), fall back to appending at the end rather than silently
	// dropping the assent sentence.
	if ( preg_match( '/<(input|button)[^>]*type="submit"/', $html ) ) {
		return preg_replace( '/<(input|button)([^>]*)type="submit"/', $assent . '<$1$2type="submit"', $html, 1 );
	}
	return $html . $assent;
} );
