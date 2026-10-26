<?php
/**
 * Registers the 5 repeating profile fields — FAQ, testimonials, team,
 * credentials, coverage cities — as WPJM native fields, using the custom
 * "repeater" field type (job_manager/form-fields/repeater-field.php).
 *
 * These render in the SAME single submit/edit form as every other field
 * (core WPJM's + Field Editor's), via the same submit_job_form_fields
 * filter everything else uses. This is what makes questions 4/5 (from the
 * "use the same hooks" conversation) possible for repeating data, which
 * WPJM otherwise has no native field type for at all.
 *
 * Saved values round-trip through WPJM's own generic field machinery —
 * see the docblock in repeater-field.php for exactly how/why no custom
 * save/load code is needed. Meta key on the job_listing post is
 * "_lp_{fieldname}" (WPJM's automatic underscore-prefix convention) —
 * e.g. the 'lp_faq' field saves to post meta key '_lp_faq'. The frontend
 * template-parts (template-parts/listing/*.php) read from these same
 * underscore-prefixed keys.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central definition of the 5 repeater fields: which tier-limit key
 * governs each one's row count (see inc/package-capabilities.php →
 * ecm_tier_limits()), and each field's sub_fields config for the
 * repeater-field.php template.
 */
function ecm_native_repeater_field_defs() : array {
	return [
		'lp_faq' => [
			'limit_key'  => 'faq',
			'label'      => __( 'FAQ Items', 'eldercare-matters' ),
			'sub_fields' => [
				'question' => [ 'type' => 'text', 'label' => __( 'Question', 'eldercare-matters' ) ],
				'answer'   => [ 'type' => 'textarea', 'label' => __( 'Answer', 'eldercare-matters' ) ],
			],
		],
		'lp_testimonials' => [
			'limit_key'  => 'testimonials',
			'label'      => __( 'Testimonials', 'eldercare-matters' ),
			'sub_fields' => [
				'name'     => [ 'type' => 'text', 'label' => __( 'Family Name', 'eldercare-matters' ) ],
				'relation' => [ 'type' => 'text', 'label' => __( 'Relation / Location', 'eldercare-matters' ) ],
				'rating'   => [ 'type' => 'star_rating', 'label' => __( 'Rating', 'eldercare-matters' ), 'min' => 1, 'max' => 5 ],
				'quote'    => [ 'type' => 'textarea', 'label' => __( 'Quote', 'eldercare-matters' ) ],
			],
		],
		'lp_team' => [
			'limit_key'  => 'team_members',
			'label'      => __( 'Team Members', 'eldercare-matters' ),
			'sub_fields' => [
				'name'              => [ 'type' => 'text', 'label' => __( 'Name', 'eldercare-matters' ) ],
				'role'              => [ 'type' => 'text', 'label' => __( 'Role', 'eldercare-matters' ) ],
				'bio'               => [ 'type' => 'textarea', 'label' => __( 'Bio', 'eldercare-matters' ) ],
				'credentials_line'  => [ 'type' => 'text', 'label' => __( 'Credentials Line', 'eldercare-matters' ) ],
				'spotlight'         => [ 'type' => 'checkbox', 'label' => __( 'Spotlight Member?', 'eldercare-matters' ), 'checkbox_label' => __( 'Highlight this member (e.g. "Caregiver of the Quarter")', 'eldercare-matters' ) ],
			],
		],
		'lp_credentials' => [
			'limit_key'  => 'credentials',
			'label'      => __( 'Credentials & Certifications', 'eldercare-matters' ),
			'sub_fields' => [
				'text' => [ 'type' => 'text', 'label' => __( 'Credential', 'eldercare-matters' ) ],
			],
		],
		'lp_cities' => [
			'limit_key'  => 'cities',
			'label'      => __( 'Coverage Cities', 'eldercare-matters' ),
			'sub_fields' => [
				'city' => [ 'type' => 'text', 'label' => __( 'City', 'eldercare-matters' ) ],
			],
		],
	];
}

/**
 * Register the 5 fields into the 'job' group (the only groups WPJM's
 * front-end template actually renders are 'job' and 'company' — confirmed
 * from templates/job-submit.php). Runs at the same priority (200) as the
 * single-value field limits in wpjm-native-fields.php.
 */
add_filter( 'submit_job_form_fields', function ( $fields ) {
	$job_id = ecm_get_current_job_id_from_request(); // Checks $_GET (Edit) AND $_POST (Add, mid-flow).
	$tier   = function_exists( 'ecm_resolve_current_submission_tier' ) ? ecm_resolve_current_submission_tier( $job_id ) : 'free';
	$limits = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];

	foreach ( ecm_native_repeater_field_defs() as $key => $def ) {
		$max = $limits[ $def['limit_key'] ] ?? 0;
		if ( 0 === $max ) {
			// Not available at this tier — unset any registration Field Editor
			// may have added at priority 100 before this callback ran.
			unset( $fields['job'][ $key ], $fields['company'][ $key ] );
			continue;
		}

		$fields['job'][ $key ] = [
			'label'       => $def['label'],
			'type'        => 'repeater',
			'required'    => false,
			'placeholder' => '',
			'priority'    => 25,
			'sub_fields'  => $def['sub_fields'],
			'max'         => $max,
			// WPJM's default get_posted_field()/get_job_data() loaders need
			// this NOT to collide with a taxonomy or special-cased key
			// (job_title, job_description, company_logo) — 'lp_' prefix
			// already guarantees that.
		];
	}

	return $fields;
}, 200 );

/**
 * Server-side row-count enforcement — same reasoning as the gallery/
 * description validation in wpjm-native-fields.php: the client-side max
 * (repeater-field.php's "Add" button hiding) is a convenience only.
 *
 * Registered on BOTH submit_job_form_validate_fields AND
 * submit_draft_job_form_validate_fields — see the fuller explanation in
 * wpjm-native-fields.php's ecm_validate_native_field_limits(): these are
 * two separate filters, and "Save Draft" only fires the draft one.
 */
function ecm_validate_native_repeater_limits( $no_errors, $fields, $values ) {
	if ( is_wp_error( $no_errors ) ) {
		return $no_errors;
	}

	$job_id = ecm_get_current_job_id_from_request(); // Checks $_GET (Edit) AND $_POST (Add, mid-flow).
	$tier   = function_exists( 'ecm_resolve_current_submission_tier' ) ? ecm_resolve_current_submission_tier( $job_id ) : 'free';
	$limits = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];

	foreach ( ecm_native_repeater_field_defs() as $key => $def ) {
		if ( empty( $values['job'][ $key ] ) || ! is_array( $values['job'][ $key ] ) ) {
			continue;
		}
		$max = $limits[ $def['limit_key'] ] ?? 0;
		// Rows with every sub-field empty (an added-then-emptied row) don't count.
		$non_empty_rows = array_filter( $values['job'][ $key ], function ( $row ) {
			return is_array( $row ) && array_filter( $row, function ( $v ) { return '' !== trim( (string) $v ) && '0' !== (string) $v; } );
		} );
		if ( count( $non_empty_rows ) > $max ) {
			return new WP_Error(
				'ecm_repeater_limit_' . $key,
				sprintf(
					/* translators: 1: field label, 2: row limit */
					esc_html__( '%1$s: your package allows up to %2$d — please remove some before continuing.', 'eldercare-matters' ),
					$def['label'],
					$max
				)
			);
		}
	}

	return $no_errors;
}
add_filter( 'submit_job_form_validate_fields', 'ecm_validate_native_repeater_limits', 10001, 3 ); // After the single-value validator (10000).
add_filter( 'submit_draft_job_form_validate_fields', 'ecm_validate_native_repeater_limits', 10001, 3 );

/**
 * Save-time safety net — truncates each repeater to its tier's row limit,
 * and strips fully-empty rows, even if validation above were bypassed.
 * Mirrors the ACF acf/update_value pattern used elsewhere in this theme.
 */
add_action( 'job_manager_update_job_data', function ( $job_id ) {
	if ( ! $job_id ) {
		return;
	}
	$tier   = function_exists( 'ecm_resolve_current_submission_tier' ) ? ecm_resolve_current_submission_tier( (int) $job_id ) : 'free';
	$limits = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];

	foreach ( ecm_native_repeater_field_defs() as $key => $def ) {
		$max  = $limits[ $def['limit_key'] ] ?? 0;
		$meta_key = '_' . $key;
		$rows = get_post_meta( $job_id, $meta_key, true );

		if ( 0 === $max ) {
			delete_post_meta( $job_id, $meta_key ); // Not available at this tier (e.g. after a downgrade) — don't leave stale data queryable.
			continue;
		}
		if ( ! is_array( $rows ) ) {
			continue;
		}

		$rows = array_values( array_filter( $rows, function ( $row ) {
			return is_array( $row ) && array_filter( $row, function ( $v ) { return '' !== trim( (string) $v ) && '0' !== (string) $v; } );
		} ) );

		if ( count( $rows ) > $max ) {
			$rows = array_slice( $rows, 0, $max );
		}

		update_post_meta( $job_id, $meta_key, $rows );
	}
}, 200, 1 );
