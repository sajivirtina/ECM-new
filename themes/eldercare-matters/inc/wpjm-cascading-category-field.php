<?php
/**
 * Two-step "Category → Subcategory" picker for the job_listing_category
 * taxonomy field WP Job Manager Field Editor already builds as job_category
 * (currently configured as a flat "Taxonomy Multi-Select Dropdown" — every
 * parent AND child term in one list — see the Field Editor admin screen).
 *
 * This file does NOT change how the field is configured, saved, or
 * validated — Field Editor's own 'taxonomy' => 'job_listing_category'
 * config keeps doing that exactly as before, via the same native
 * <select name="job_category[]" multiple> WPJM/Field Editor already
 * expects. It only swaps which TEMPLATE renders that field (job_manager/
 * form-fields/category_cascade-field.php), which draws two friendlier
 * selects — Category, then Subcategory filtered to that category's
 * children — and keeps them in sync with the real (hidden) select.
 *
 * Hooked at priority 200 — same convention as the other inc/wpjm-*.php
 * files in this theme — so it runs AFTER Field Editor (100) has already
 * built the field config.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

/** Taxonomy this applies to — change here if the category taxonomy is ever renamed. */
function ecm_cascade_taxonomy() : string {
	return 'job_listing_category';
}

/**
 * Finds the [group_key, field_key] of the category field within a WPJM
 * front-end $fields array (shape: ['job' => [...], 'company' => [...]]),
 * matching on the field's 'taxonomy' config rather than assuming which
 * group/key Field Editor put it under.
 */
function ecm_find_cascade_field( array $fields ) : ?array {
	foreach ( $fields as $group_key => $group_fields ) {
		if ( ! is_array( $group_fields ) ) {
			continue;
		}
		foreach ( $group_fields as $field_key => $field ) {
			$taxonomy = $field['taxonomy'] ?? '';
			if ( ecm_cascade_taxonomy() === $taxonomy || 'job_category' === $field_key ) {
				return [ $group_key, $field_key ];
			}
		}
	}
	return null;
}

/**
 * Max SUBcategory (child-term) picks for the given job, per package tier —
 * Free: 1, Basic: 3, Premium (internal key 'standard'): 5, Featured
 * (internal key 'premium'): unlimited. Single source of truth is
 * ecm_tier_limits()['subcategories'] in inc/package-capabilities.php; -1
 * there means unlimited, returned as-is here.
 */
function ecm_cascade_subcategory_limit( int $job_id = 0 ) : int {
	$tier = function_exists( 'ecm_resolve_current_submission_tier' )
		? ecm_resolve_current_submission_tier( $job_id )
		: 'free';
	$limits = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];
	return $limits['subcategories'] ?? 1;
}

// ── Front-end submit/edit form ──────────────────────────────────────────────

add_filter( 'submit_job_form_fields', function ( $fields ) {
	$loc = ecm_find_cascade_field( $fields );
	if ( ! $loc ) {
		return $fields;
	}
	[ $group_key, $field_key ] = $loc;
	// Keep everything else (taxonomy, label, required, description, value…)
	// exactly as Field Editor built it — only the render template changes,
	// plus the tier-based subcategory cap passed through for the template/JS.
	$fields[ $group_key ][ $field_key ]['type']              = 'category_cascade';
	$fields[ $group_key ][ $field_key ]['subcategory_limit'] = ecm_cascade_subcategory_limit( ecm_get_current_job_id_from_request() );

	return $fields;
}, 200 );

// ── wp-admin job_listing edit screen ────────────────────────────────────────

/**
 * Admin side stays informational rather than hard-limited — same philosophy
 * as the gallery/description tier notes in inc/wpjm-admin-native-fields.php
 * ("admins are trusted to override these when they have a good reason to").
 * subcategory_limit is passed as -1 (unlimited) so the JS never blocks a
 * selection here; the provider's tier is shown as a plain note instead.
 */
add_filter( 'job_manager_job_listing_data_fields', function ( $fields ) {
	global $post_id;
	foreach ( $fields as $field_key => $field ) {
		$taxonomy = $field['taxonomy'] ?? '';
		if ( ecm_cascade_taxonomy() === $taxonomy || '_job_category' === $field_key ) {
			$fields[ $field_key ]['type']              = 'category_cascade';
			$fields[ $field_key ]['subcategory_limit']  = -1;
			$limit = ecm_cascade_subcategory_limit( (int) $post_id );
			$fields[ $field_key ]['description'] = ( -1 === $limit )
				? esc_html__( "Provider's package allows unlimited subcategories.", 'eldercare-matters' )
				: sprintf(
					/* translators: %d: number of subcategories allowed */
					esc_html__( "Provider's package allows up to %d subcategories — shown here without that limit enforced.", 'eldercare-matters' ),
					$limit
				);
		}
	}
	return $fields;
}, 200 );

/**
 * Admin-side renderer for the 'category_cascade' field type — same
 * action-based dispatch WPJM admin uses for any type without a built-in
 * method (see inc/wpjm-admin-native-fields.php's 'repeater' renderer for
 * the same pattern). Reuses the exact same template as the front-end.
 */
add_action( 'job_manager_input_category_cascade', function ( $key, $field ) {
	echo '<p class="form-field form-field-category-cascade"><label>' . esc_html( wp_strip_all_tags( $field['label'] ?? '' ) ) . '</label>';
	include get_theme_file_path( 'job_manager/form-fields/category_cascade-field.php' );
	echo '</p>';
}, 10, 2 );

// ── Server-side subcategory-limit validation ────────────────────────────────

/**
 * Blocks submission (with a clear error, same pattern as the other
 * tier-limit validators in inc/wpjm-native-fields.php) when more child
 * (subcategory) terms are posted than the provider's tier allows. Only
 * CHILD terms count against the limit — the single top-level Category pick
 * doesn't. Client-side JS in the field template already stops a user from
 * over-selecting in the normal case; this is the actual enforcement.
 *
 * Registered on BOTH submit_job_form_validate_fields AND
 * submit_draft_job_form_validate_fields — these are two entirely separate
 * filters (see inc/wpjm-native-fields.php's docblock for why both are
 * needed: Save Draft never runs the non-draft validator).
 */
function ecm_validate_subcategory_limit( $no_errors, $fields, $values ) {
	if ( is_wp_error( $no_errors ) ) {
		return $no_errors; // An earlier validator already failed — don't mask it.
	}

	$loc = ecm_find_cascade_field( $fields );
	if ( ! $loc ) {
		return $no_errors;
	}
	[ $group_key, $field_key ] = $loc;

	$posted = $values[ $group_key ][ $field_key ] ?? [];
	$posted = is_array( $posted ) ? array_map( 'absint', $posted ) : array_filter( [ absint( $posted ) ] );

	$limit = ecm_cascade_subcategory_limit( ecm_get_current_job_id_from_request() );
	if ( -1 === $limit ) {
		return $no_errors; // Unlimited tier.
	}

	$child_count = 0;
	foreach ( $posted as $term_id ) {
		$term = get_term( $term_id, ecm_cascade_taxonomy() );
		if ( $term && ! is_wp_error( $term ) && (int) $term->parent > 0 ) {
			++$child_count;
		}
	}

	if ( $child_count > $limit ) {
		return new WP_Error(
			'ecm_subcategory_limit',
			sprintf(
				/* translators: %d: number of subcategories allowed */
				esc_html__( 'Your package allows up to %d subcategories — please remove some before continuing.', 'eldercare-matters' ),
				$limit
			)
		);
	}

	return $no_errors;
}
add_filter( 'submit_job_form_validate_fields', 'ecm_validate_subcategory_limit', 10002, 3 ); // After the other native-field validators (10000/10001).
add_filter( 'submit_draft_job_form_validate_fields', 'ecm_validate_subcategory_limit', 10002, 3 );

// ── Save-time safety net ────────────────────────────────────────────────────

/**
 * Explicitly re-applies the posted job_category term IDs via
 * wp_set_object_terms(), truncating child (subcategory) terms to the
 * provider's tier limit as a final defense-in-depth pass. Belt-and-braces
 * alongside Field Editor's own taxonomy save path: this codebase has
 * already hit one confirmed case (see inc/wpjm-manual-field-save.php's
 * docblock) of WPJM's generic per-field save loop silently not persisting
 * a field despite correct $_POST data, so the same direct-save pattern is
 * applied here too — this is idempotent alongside Field Editor's own save
 * (same $_POST, same taxonomy, last write wins with no conflict either way).
 */
add_action( 'job_manager_update_job_data', function ( $job_id ) {
	if ( ! $job_id || ! isset( $_POST['job_category'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WPJM's own submit form is nonce-protected upstream; this hook only runs after that check passes.
		return;
	}

	$raw      = wp_unslash( $_POST['job_category'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately below via absint.
	$term_ids = array_values( array_filter( array_map( 'absint', (array) $raw ) ) );

	$limit = ecm_cascade_subcategory_limit( $job_id );
	if ( -1 !== $limit ) {
		$taxonomy = ecm_cascade_taxonomy();
		$parents  = [];
		$children = [];
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) && (int) $term->parent > 0 ) {
				$children[] = $term_id;
			} else {
				$parents[] = $term_id;
			}
		}
		$children = array_slice( $children, 0, max( 0, $limit ) );
		$term_ids = array_merge( $parents, $children );
	}

	wp_set_object_terms( $job_id, $term_ids, ecm_cascade_taxonomy(), false );
}, 200, 1 );

// ── Guard: never let the submit form silently create NEW categories ────────

/**
 * Blocks new job_listing_category terms from being created outside
 * wp-admin — the actual fix for the "a new category gets created on every
 * submit, named like a post/number ID" bug.
 *
 * Root cause: this field's 'type' is now 'category_cascade', which is
 * unrecognized by Field Editor's/WPJM's own type-driven POST sanitizer.
 * Their save path still fires (it keys off the field's 'taxonomy' config,
 * not its 'type' — see this file's other docblocks), but for an unknown
 * type it appears to pass the raw POSTed values straight into
 * wp_set_object_terms() as PHP STRINGS rather than casting them to real
 * integers first. WordPress's own term_exists() treats a non-integer value
 * as a SLUG/NAME lookup, not an ID lookup — so a numeric string like
 * "1074" that doesn't match any existing term's slug/name (even though
 * term ID 1074 itself exists) falls through to wp_insert_term(), silently
 * creating a brand-new junk term literally named "1074" on every submit.
 *
 * The save-time safety net above (job_manager_update_job_data) already
 * uses absint() everywhere, so it only ever passes true PHP integers —
 * term_exists()/wp_set_object_terms() correctly treats those as ID lookups
 * and never creates anything from them. This guard stops the OTHER
 * (unknown) save path from creating junk terms in the first place,
 * regardless of what's calling it — providers should only ever be
 * attaching EXISTING categories from the picker, never minting new ones.
 *
 * Scoped to non-admin requests only, so managing categories normally via
 * Job Listings → Categories in wp-admin (or WP-CLI/import scripts) is
 * unaffected.
 */
add_filter( 'pre_insert_term', function ( $term, $taxonomy ) {
	if ( ecm_cascade_taxonomy() !== $taxonomy ) {
		return $term;
	}
	if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return $term; // Legitimate admin/CLI category management — allow.
	}
	return new WP_Error(
		'ecm_no_new_category',
		__( 'Categories can only be chosen from the existing list, not created from the listing form.', 'eldercare-matters' )
	);
}, 10, 2 );
