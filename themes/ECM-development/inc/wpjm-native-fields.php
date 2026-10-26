<?php
/**
 * Applies package-tier limits directly to WP Job Manager's own native
 * fields (registered via Field Editor, e.g. gallery_images, job_description)
 * so they render in the SAME single submit/edit form Field Editor already
 * builds — instead of a separate ACF "Profile Details" card below it.
 *
 * Uses the exact same extension points Field Editor itself uses
 * (confirmed from its source, classes/job/fields.php):
 *   - submit_job_form_fields          (WPJM core filter — modifies field config)
 *   - submit_job_form_validate_fields (WPJM core filter — server-side validation)
 *   - job_manager_update_job_data     (WPJM core action — fires on every save)
 *
 * Field Editor hooks submit_job_form_fields at priority 100 — this file
 * hooks the SAME filter at priority 200, so it runs AFTER Field Editor has
 * already built the field, and can then layer tier-based limits on top.
 *
 * IMPORTANT — architectural note: WPJM's native field types (see
 * classes/field-types.php in Field Editor) are all single-value — text,
 * textarea, wp-editor, select, checkbox, file, etc. There is NO native
 * repeater type. This file covers the single-value fields (gallery,
 * description). The repeating fields — testimonials, team, credentials,
 * FAQ, coverage cities — get a custom-built "repeater" field TYPE instead;
 * see inc/wpjm-native-repeater-fields.php + job_manager/form-fields/
 * repeater-field.php for that.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the tier that should govern the CURRENT submission — for an
 * existing listing (Edit), reads its real package. For a brand-new
 * listing still mid-flow (Add, before the job even exists or before its
 * package meta has been written), falls back to the same
 * chosen_package_id / chosen_package_is_user_package cookies the payments
 * plugin sets the instant a package is chosen (see
 * inc/provider-dashboard.php for the fuller explanation of this cookie).
 *
 * UPDATED: now prefers WP Job Manager Field Editor's OWN package-resolution
 * utility (WP_Job_Manager_Field_Editor_Package_WC), confirmed from its
 * source (classes/package/wc.php) to be more robust than our original,
 * hand-rolled version — it checks more meta-key fallbacks (_package_id,
 * _wcpl_jmfe_product_id, _user_package_id), and its get_product_id()
 * helper transparently supports multiple payment plugins (Listing
 * Payments, WC Paid Listings, WC Advanced Paid Listings), not just
 * Listing Payments specifically. This is the SAME resolution mechanism
 * that makes Field Editor's own admin-configured "show for these
 * packages" fields work reliably — reusing it here closes the gap where
 * our fields weren't appearing on Add while a manually-configured Field
 * Editor field, using its own resolution, worked correctly.
 */
function ecm_resolve_current_submission_tier( int $job_id = 0 ) : string {
	if ( class_exists( 'WP_Job_Manager_Field_Editor_Package_WC' ) && function_exists( 'ecm_package_product_ids' ) ) {
		$resolved = WP_Job_Manager_Field_Editor_Package_WC::get_post_package_id( $job_id ?: 0 );
		if ( $resolved ) {
			// $resolved may be a raw numeric product ID OR a "user-{id}"
			// formatted string (Field Editor's own convention for
			// already-owned packages) — get_product_id() normalizes either.
			$product_id = WP_Job_Manager_Field_Editor_Package_WC::get_product_id( $resolved );
			if ( $product_id ) {
				$tier = array_search( (int) $product_id, ecm_package_product_ids(), true );
				if ( $tier ) {
					return $tier;
				}
			}
		}
		// Their resolver came up empty too (most likely $job_id is 0 — a
		// brand-new listing mid-flow, before any post/meta exists at all,
		// which get_post_package_id() can't help with since it only reads
		// post meta). Fall through to our own cookie-based fallback below,
		// which is the one case their resolver doesn't cover.
	}

	if ( ! empty( $_POST['job_package'] ) ) {
		// We may be on the exact request that's submitting the package
		// choice itself — this is more current than any cookie.
		return ecm_tier_from_job_package_value( wp_unslash( $_POST['job_package'] ) );
	}

	if ( $job_id && function_exists( 'ecm_get_listing_tier' ) ) {
		$tier = ecm_get_listing_tier( $job_id );
		if ( 'free' !== $tier ) {
			return $tier;
		}
		// The listing's stored tier IS free — trust the post meta and return
		// 'free' rather than falling through to a stale cookie. The cookie
		// fallback is for brand-new listings (job_id = 0) where no package meta
		// exists yet. On an upgrade POST, $_POST['job_package'] fires first.
		return 'free';
	}

	if ( empty( $_COOKIE['chosen_package_id'] ) || ! function_exists( 'ecm_package_product_ids' ) ) {
		return 'free';
	}

	$cookie_package_id = absint( wp_unslash( $_COOKIE['chosen_package_id'] ) );
	$is_user_package    = ! empty( $_COOKIE['chosen_package_is_user_package'] ) && '1' === (string) wp_unslash( $_COOKIE['chosen_package_is_user_package'] );

	return ecm_tier_from_job_package_value( $is_user_package ? 'user-' . $cookie_package_id : (string) $cookie_package_id );
}

/**
 * Resolves a raw "job_package" style value — either a plain numeric
 * product ID (a fresh purchase) or a "user-{id}" formatted string (an
 * already-owned package, Field Editor's own convention) — into our tier
 * key. Prefers Field Editor's own get_product_id() helper when available
 * (handles multiple payment plugins); falls back to calling
 * astoundify_wpjmlp_get_user_package() directly otherwise.
 */
function ecm_tier_from_job_package_value( string $value ) : string {
	if ( ! function_exists( 'ecm_package_product_ids' ) ) {
		return 'free';
	}

	$product_id = null;

	if ( class_exists( 'WP_Job_Manager_Field_Editor_Package_WC' ) ) {
		$product_id = (int) WP_Job_Manager_Field_Editor_Package_WC::get_product_id( $value );
	} elseif ( 0 === strpos( $value, 'user-' ) && function_exists( 'astoundify_wpjmlp_get_user_package' ) ) {
		$user_package = astoundify_wpjmlp_get_user_package( (int) substr( $value, 5 ) );
		if ( $user_package && method_exists( $user_package, 'get_product_id' ) ) {
			$product_id = (int) $user_package->get_product_id();
		}
	} elseif ( is_numeric( $value ) ) {
		$product_id = (int) $value;
	}

	if ( ! $product_id ) {
		return 'free';
	}

	$tier = array_search( $product_id, ecm_package_product_ids(), true );
	return $tier ?: 'free';
}

/**
 * Layer tier-based limits onto Field Editor's own field config. Runs at
 * priority 200 — after Field Editor (100) has already built the fields.
 */
add_filter( 'submit_job_form_fields', function ( $fields ) {
	$job_id = ecm_get_current_job_id_from_request(); // Checks $_GET (Edit) AND $_POST (Add, mid-flow).
	$tier   = ecm_resolve_current_submission_tier( $job_id );
	$limits = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];

	foreach ( [ 'job', 'company' ] as $group ) {
		if ( empty( $fields[ $group ] ) ) {
			continue;
		}

		if ( isset( $fields[ $group ]['gallery_images'] ) ) {
			$gallery_limit = $limits['gallery'] ?? 0;
			if ( 0 === $gallery_limit ) {
				unset( $fields[ $group ]['gallery_images'] ); // Not available at this tier — hide entirely.
			} else {
				$fields[ $group ]['gallery_images']['multiple']   = true;
				$fields[ $group ]['gallery_images']['ajax']       = true; // Enables WPJM's own client-side file_limit enforcement.
				$fields[ $group ]['gallery_images']['file_limit'] = $gallery_limit;
				$fields[ $group ]['gallery_images']['description'] = sprintf(
					/* translators: %d: number of photos allowed */
					esc_html__( 'Up to %d photos on your current package.', 'eldercare-matters' ),
					$gallery_limit
				);
			}
		}

		if ( isset( $fields[ $group ]['job_description'] ) ) {
			$word_limit = $limits['bio_words'] ?? 0;
			if ( 0 === $word_limit ) {
				$fields[ $group ]['job_description']['description'] = esc_html__( 'A short auto-generated description is used on the Free plan. Upgrade to write your own.', 'eldercare-matters' );
			} else {
				$fields[ $group ]['job_description']['description'] = sprintf(
					/* translators: %d: word count allowed */
					esc_html__( 'Up to %d words on your current package.', 'eldercare-matters' ),
					$word_limit
				);
			}
		}
	}

	// Gate non-repeater custom fields that Field Editor may register.
	// Repeaters (faq, testimonials, team, credentials, cities) are handled in wpjm-native-repeater-fields.php.
	$is_standard_or_above = in_array( $tier, [ 'standard', 'premium' ], true );

	// Phone — Standard and Premium only.
	// Field Editor registers this field with slug 'phone' (not 'lp_phone'); unset both to be safe.
	if ( ! $is_standard_or_above ) {
		unset( $fields['job']['lp_phone'], $fields['company']['lp_phone'] );
		unset( $fields['job']['phone'],    $fields['company']['phone'] );
	}

	// Website — Premium (Featured) only.
	if ( empty( $limits['has_website'] ) ) {
		unset( $fields['job']['lp_website'], $fields['company']['lp_website'] );
	}

	// Mission — Premium (Featured) only.
	if ( empty( $limits['has_mission'] ) ) {
		unset( $fields['job']['lp_mission'], $fields['company']['lp_mission'] );
	}

	// Highlights — Standard and Premium only.
	if ( ! $is_standard_or_above ) {
		unset( $fields['job']['lp_highlights'], $fields['company']['lp_highlights'] );
	}

	return $fields;
}, 200 );

/**
 * Server-side enforcement — the file_limit/description text above are
 * UI-only conveniences. This is what actually blocks over-limit
 * submissions, run at a very late priority so it sees the fully-built
 * field config (with our limits already layered on) and the submitted
 * values together.
 *
 * Registered on BOTH submit_job_form_validate_fields AND
 * submit_draft_job_form_validate_fields — confirmed from WPJM core source
 * (class-wp-job-manager-form-submit-job.php → submit_handler()) that
 * these are two ENTIRELY SEPARATE filters: the draft path
 * ($_POST['save_draft']) validates via submit_draft_job_form_validate_fields,
 * never submit_job_form_validate_fields. Registering on only one meant
 * "Save Draft" bypassed this validation completely — which is exactly why
 * a 3rd photo could get past the 2-photo Basic limit on save; only the
 * separate job_manager_update_job_data truncation further down silently
 * fixed it afterward, with no error shown to the provider.
 */
function ecm_validate_native_field_limits( $no_errors, $fields, $values ) {
	if ( is_wp_error( $no_errors ) ) {
		return $no_errors; // An earlier validator (e.g. Field Editor's own) already failed — don't mask it.
	}

	$job_id = ecm_get_current_job_id_from_request(); // Checks $_GET (Edit) AND $_POST (Add, mid-flow).
	$tier   = ecm_resolve_current_submission_tier( $job_id );
	$limits = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];

	foreach ( [ 'job', 'company' ] as $group ) {
		if ( empty( $values[ $group ] ) ) {
			continue;
		}

		if ( isset( $values[ $group ]['gallery_images'] ) && is_array( $values[ $group ]['gallery_images'] ) ) {
			$gallery_limit = $limits['gallery'] ?? 0;
			if ( count( $values[ $group ]['gallery_images'] ) > $gallery_limit ) {
				return new WP_Error(
					'ecm_gallery_limit',
					sprintf(
						/* translators: %d: number of photos allowed */
						esc_html__( 'Your package allows up to %d photos — please remove some before continuing.', 'eldercare-matters' ),
						$gallery_limit
					)
				);
			}
		}

		if ( ! empty( $values[ $group ]['job_description'] ) ) {
			$word_limit = $limits['bio_words'] ?? 0;
			$word_count = str_word_count( wp_strip_all_tags( $values[ $group ]['job_description'] ) );
			if ( $word_limit > 0 && $word_count > $word_limit ) {
				return new WP_Error(
					'ecm_description_limit',
					sprintf(
						/* translators: 1: word count submitted, 2: word count allowed */
						esc_html__( 'Your description is %1$d words — your package allows up to %2$d.', 'eldercare-matters' ),
						$word_count,
						$word_limit
					)
				);
			}
			if ( 0 === $word_limit && $word_count > 0 ) {
				return new WP_Error(
					'ecm_description_locked',
					esc_html__( 'Your current package doesn\'t include a custom description yet — upgrade to write your own.', 'eldercare-matters' )
				);
			}
		}

		// Server-side guard for tier-gated non-repeater custom fields.
		$is_standard_or_above = in_array( $tier, [ 'standard', 'premium' ], true );

		if ( ! $is_standard_or_above && ( ! empty( $values[ $group ]['lp_phone'] ) || ! empty( $values[ $group ]['phone'] ) ) ) {
			return new WP_Error( 'ecm_phone_locked', esc_html__( 'Your package does not include the phone field — upgrade to add it.', 'eldercare-matters' ) );
		}
		if ( empty( $limits['has_website'] ) && ! empty( $values[ $group ]['lp_website'] ) ) {
			return new WP_Error( 'ecm_website_locked', esc_html__( 'Your package does not include the website field — upgrade to add it.', 'eldercare-matters' ) );
		}
		if ( empty( $limits['has_mission'] ) && ! empty( $values[ $group ]['lp_mission'] ) ) {
			return new WP_Error( 'ecm_mission_locked', esc_html__( 'Your package does not include the mission field — upgrade to add it.', 'eldercare-matters' ) );
		}
		if ( ! $is_standard_or_above && ! empty( $values[ $group ]['lp_highlights'] ) ) {
			return new WP_Error( 'ecm_highlights_locked', esc_html__( 'Your package does not include the highlights field — upgrade to add it.', 'eldercare-matters' ) );
		}
	}

	return $no_errors;
}
add_filter( 'submit_job_form_validate_fields', 'ecm_validate_native_field_limits', 10000, 3 ); // After Field Editor's own check_uploads (9999).
add_filter( 'submit_draft_job_form_validate_fields', 'ecm_validate_native_field_limits', 10000, 3 );

/**
 * Save-time safety net — truncates the gallery to the tier limit even if
 * client + server validation above were both somehow bypassed. Mirrors
 * the same defense-in-depth pattern used for the ACF-based fields
 * (inc/acf-provider-listing-fields.php → acf/update_value).
 */
add_action( 'job_manager_update_job_data', function ( $job_id ) {
	if ( ! $job_id ) {
		return;
	}
	$tier   = ecm_resolve_current_submission_tier( (int) $job_id );
	$limits = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];
	$limit  = $limits['gallery'] ?? 0;

	$gallery = get_post_meta( $job_id, '_gallery_images', true );
	if ( is_array( $gallery ) && count( $gallery ) > $limit ) {
		update_post_meta( $job_id, '_gallery_images', array_slice( $gallery, 0, max( 0, $limit ) ) );
	}
}, 200, 1 );
