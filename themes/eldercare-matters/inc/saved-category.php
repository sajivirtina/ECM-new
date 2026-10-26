<?php
/**
 * Saved care category.
 *
 * Find Care asks for a category before showing providers, and the request form
 * opens with that category already selected. The choice is kept in the
 * visitor's browser (localStorage, handled in assets/js/intake.js) and, for
 * logged-in users, as user meta so it follows them across devices.
 *
 *   POST /wp-json/ecm/v1/category   { "category": "<job_listing_category slug>" }
 *
 * The slug must be an existing top-level job_listing_category term.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_SAVED_CATEGORY_META = 'ecm_selected_category';

/** True when $slug is an existing top-level job_listing_category term. */
function ecm_is_valid_category_slug( $slug ) : bool {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) {
		return false;
	}
	$term = get_term_by( 'slug', $slug, 'job_listing_category' );
	return $term && ! is_wp_error( $term ) && 0 === (int) $term->parent;
}

/** The logged-in user's saved category slug, or '' when none or no longer valid. */
function ecm_get_saved_category() : string {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$slug = (string) get_user_meta( get_current_user_id(), ECM_SAVED_CATEGORY_META, true );
	return ecm_is_valid_category_slug( $slug ) ? $slug : '';
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'ecm/v1', '/category', [
		'methods'             => 'POST',
		'permission_callback' => static function () {
			return is_user_logged_in();
		},
		'args'                => [
			'category' => [ 'required' => true, 'sanitize_callback' => 'sanitize_title' ],
		],
		'callback'            => static function ( WP_REST_Request $request ) {
			$slug = (string) $request->get_param( 'category' );
			if ( ! ecm_is_valid_category_slug( $slug ) ) {
				return new WP_REST_Response( [ 'ok' => false, 'message' => 'Unknown category.' ], 400 );
			}
			update_user_meta( get_current_user_id(), ECM_SAVED_CATEGORY_META, $slug );
			return new WP_REST_Response( [ 'ok' => true, 'category' => $slug ], 200 );
		},
	] );
} );

/**
 * Page-load configuration for assets/js/intake.js and search.js. Printed before
 * the intake script so every later script can read window.ECM_CATEGORY_PREF.
 * `required` is true only on the Find Care page, where a category must be
 * chosen before results show.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! wp_script_is( 'ecm-intake', 'enqueued' ) ) {
		return;
	}
	$names = [];
	$terms = get_terms( [ 'taxonomy' => 'job_listing_category', 'parent' => 0, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ] );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$names[ $t->slug ] = html_entity_decode( $t->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
	}
	$config = [
		'saved'    => ecm_get_saved_category(),
		'names'    => $names,
		'loggedIn' => is_user_logged_in(),
		'nonce'    => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'root'     => esc_url_raw( rest_url( 'ecm/v1' ) ),
		'required' => is_page_template( 'template-search.php' ),
	];
	wp_add_inline_script( 'ecm-intake', 'window.ECM_CATEGORY_PREF = ' . wp_json_encode( $config ) . ';', 'before' );
}, 30 );
