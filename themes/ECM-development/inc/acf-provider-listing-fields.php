<?php
/**
 * Provider listing profile fields — the rich content beyond WPJM's core
 * submission form (bio, gallery, testimonials, team, credentials, FAQ,
 * articles), shown on the public single-listing page and edited via
 * acf_form() on the in-dashboard Edit Listing screen.
 *
 * Field visibility/row limits are enforced per the listing's package tier —
 * see inc/package-capabilities.php for the tier → limits map.
 *
 * IMPORTANT: enforcement here is server-side (acf/validate_value,
 * acf/update_value truncation) as well as UI-side (acf/prepare_field
 * setting `max`) — the UI-side max alone is not real enforcement, since a
 * post request that skips the browser (or a stale page) can still submit
 * more rows than the UI allowed. Don't remove the server-side half.
 */

defined( 'ABSPATH' ) || exit;

acf_add_local_field_group( [
	'key'      => 'group_ecm_listing_profile',
	'title'    => 'Provider Profile (by package)',
	'location' => [ [ [
		'param'    => 'post_type',
		'operator' => '==',
		'value'    => 'job_listing',
	] ] ],
	'menu_order'      => 0,
	'position'        => 'normal',
	'label_placement' => 'top',
	'active'          => true,
	'fields'          => [

		[ 'key' => 'field_ecm_lp_tab_about', 'label' => 'About', 'type' => 'tab' ],
		[
			'key' => 'field_ecm_lp_bio', 'label' => 'About / Bio', 'name' => 'lp_bio', 'type' => 'textarea', 'rows' => 6,
			'instructions' => 'Basic: up to 50 words. Premium: up to 250 words. Featured: up to 500 words. Over the limit will be rejected on save.',
		],
		[
			'key' => 'field_ecm_lp_mission', 'label' => 'Mission Statement', 'name' => 'lp_mission', 'type' => 'text',
			'instructions' => 'Featured only.',
		],
		[
			'key' => 'field_ecm_lp_highlights', 'label' => 'Highlights (\'What We Offer\' / \'Why Choose Us\')', 'name' => 'lp_highlights',
			'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 8,
			'instructions' => 'Premium & Featured only.',
			'sub_fields' => [
				[ 'key' => 'field_ecm_lp_highlight_text', 'label' => 'Item', 'name' => 'text', 'type' => 'text' ],
			],
		],

		[ 'key' => 'field_ecm_lp_tab_contact', 'label' => 'Contact & Coverage', 'type' => 'tab' ],
		[ 'key' => 'field_ecm_lp_phone', 'label' => 'Phone', 'name' => 'lp_phone', 'type' => 'text', 'instructions' => 'Premium & Featured.' ],
		[ 'key' => 'field_ecm_lp_website', 'label' => 'Website URL', 'name' => 'lp_website', 'type' => 'url', 'instructions' => 'Featured only.' ],
		[ 'key' => 'field_ecm_lp_facebook',  'label' => 'Facebook URL',  'name' => 'lp_facebook',  'type' => 'url', 'instructions' => 'e.g. https://facebook.com/yourpage' ],
		[ 'key' => 'field_ecm_lp_instagram', 'label' => 'Instagram URL', 'name' => 'lp_instagram', 'type' => 'url', 'instructions' => 'e.g. https://instagram.com/yourhandle' ],
		[ 'key' => 'field_ecm_lp_linkedin',  'label' => 'LinkedIn URL',  'name' => 'lp_linkedin',  'type' => 'url', 'instructions' => 'e.g. https://linkedin.com/company/yourcompany' ],
		[ 'key' => 'field_ecm_lp_twitter',   'label' => 'X / Twitter URL', 'name' => 'lp_twitter', 'type' => 'url', 'instructions' => 'e.g. https://x.com/yourhandle' ],
		[
			'key' => 'field_ecm_lp_cities', 'label' => 'Coverage Cities', 'name' => 'lp_cities',
			'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 9,
			'instructions' => 'Premium: up to 4 cities. Featured: up to 9. Not available at Free/Basic — primary location comes from the core listing field.',
			'sub_fields' => [
				[ 'key' => 'field_ecm_lp_city_name', 'label' => 'City', 'name' => 'city', 'type' => 'text' ],
			],
		],

		[ 'key' => 'field_ecm_lp_tab_testimonials', 'label' => 'Testimonials', 'type' => 'tab' ],
		[
			'key' => 'field_ecm_lp_testimonials', 'label' => 'Testimonials', 'name' => 'lp_testimonials',
			'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 4,
			'instructions' => 'Premium: up to 3. Featured: up to 4.',
			'sub_fields' => [
				[ 'key' => 'field_ecm_lp_test_name', 'label' => 'Family Name', 'name' => 'name', 'type' => 'text' ],
				[ 'key' => 'field_ecm_lp_test_relation', 'label' => 'Relation / Location', 'name' => 'relation', 'type' => 'text' ],
				[ 'key' => 'field_ecm_lp_test_rating', 'label' => 'Rating (1-5)', 'name' => 'rating', 'type' => 'number', 'min' => 1, 'max' => 5 ],
				[ 'key' => 'field_ecm_lp_test_quote', 'label' => 'Quote', 'name' => 'quote', 'type' => 'textarea', 'rows' => 3 ],
			],
		],

		[ 'key' => 'field_ecm_lp_tab_team', 'label' => 'Team', 'type' => 'tab' ],
		[
			'key' => 'field_ecm_lp_team', 'label' => 'Team Members', 'name' => 'lp_team',
			'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 4,
			'instructions' => 'Premium: up to 2. Featured: up to 4 (may include one "spotlight" member).',
			'sub_fields' => [
				[ 'key' => 'field_ecm_lp_team_name', 'label' => 'Name', 'name' => 'name', 'type' => 'text' ],
				[ 'key' => 'field_ecm_lp_team_role', 'label' => 'Role', 'name' => 'role', 'type' => 'text' ],
				[ 'key' => 'field_ecm_lp_team_bio', 'label' => 'Bio', 'name' => 'bio', 'type' => 'textarea', 'rows' => 3 ],
				[ 'key' => 'field_ecm_lp_team_credentials', 'label' => 'Credentials Line', 'name' => 'credentials_line', 'type' => 'text' ],
				[
					'key' => 'field_ecm_lp_team_spotlight', 'label' => 'Spotlight Member?', 'name' => 'spotlight', 'type' => 'true_false', 'ui' => 1,
					'instructions' => 'Featured only — highlights this member differently (e.g. "Caregiver of the Quarter").',
				],
			],
		],

		[ 'key' => 'field_ecm_lp_tab_credentials', 'label' => 'Credentials', 'type' => 'tab' ],
		[
			'key' => 'field_ecm_lp_credentials', 'label' => 'Credentials & Certifications', 'name' => 'lp_credentials',
			'type' => 'repeater', 'layout' => 'table', 'min' => 0, 'max' => 6,
			'instructions' => 'Premium: up to 4. Featured: up to 6.',
			'sub_fields' => [
				[ 'key' => 'field_ecm_lp_credential_text', 'label' => 'Credential', 'name' => 'text', 'type' => 'text' ],
			],
		],

		[ 'key' => 'field_ecm_lp_tab_gallery', 'label' => 'Gallery', 'type' => 'tab' ],
		[
			'key' => 'field_ecm_lp_gallery', 'label' => 'Photo Gallery', 'name' => 'lp_gallery', 'type' => 'gallery',
			'instructions' => 'Basic: up to 2 photos. Premium: up to 5. Featured: up to 10 (also shown as a hero carousel). Extra uploads beyond your limit are removed on save.',
		],

		[ 'key' => 'field_ecm_lp_tab_faq', 'label' => 'FAQ', 'type' => 'tab' ],
		[
			'key' => 'field_ecm_lp_faq', 'label' => 'FAQ Items', 'name' => 'lp_faq',
			'type' => 'repeater', 'layout' => 'block', 'min' => 0, 'max' => 5,
			'instructions' => 'Basic: up to 3. Premium: up to 4. Featured: up to 5.',
			'sub_fields' => [
				[ 'key' => 'field_ecm_lp_faq_q', 'label' => 'Question', 'name' => 'question', 'type' => 'text' ],
				[ 'key' => 'field_ecm_lp_faq_a', 'label' => 'Answer', 'name' => 'answer', 'type' => 'textarea', 'rows' => 3 ],
			],
		],

	],
] );

/**
 * Resolves the job_listing post ID currently being edited via acf_form().
 * Deliberately does NOT rely on acf_get_form_data('post_id') — that proved
 * unreliable inside this theme's embedded dashboard context (fields weren't
 * being tier-limited at all as a result). listings.php sets this global
 * explicitly right before calling acf_form(); $_GET['job_id'] and ACF's own
 * lookup are kept only as last-resort fallbacks.
 */
function ecm_current_profile_form_post_id() : int {
	if ( ! empty( $GLOBALS['ecm_profile_form_post_id'] ) ) {
		return (int) $GLOBALS['ecm_profile_form_post_id'];
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup, not a state change.
	if ( ! empty( $_GET['job_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return (int) $_GET['job_id'];
	}
	if ( function_exists( 'acf_get_form_data' ) ) {
		$fallback = (int) acf_get_form_data( 'post_id' );
		if ( $fallback ) {
			return $fallback;
		}
	}
	return 0;
}

/**
 * UI-side enforcement: hides fields the current tier doesn't unlock, and
 * sets `max` on repeaters/gallery so the browser UI won't let a provider
 * add more rows/photos than their tier allows. This alone is NOT sufficient
 * enforcement — see acf/validate_value and acf/update_value below for the
 * server-side half that actually blocks over-limit data on save.
 *
 * Registered on BOTH acf/load_field and acf/prepare_field (see calls below)
 * rather than just one: acf/prepare_field alone proved unreliable for this
 * in the dashboard's embedded acf_form() context — a debug session showed
 * `max` computed correctly (max: 1) but the repeater's "Add row" button in
 * the actual rendered UI still let more rows through. acf/load_field fires
 * earlier, at the point ACF actually loads the field's stored settings
 * (regardless of whether rendering used 'field_groups' or an explicit
 * 'fields' key list), and is the more standard hook for this exact
 * per-request settings override — so it's the primary mechanism now, with
 * prepare_field kept as a harmless second attempt in case some render path
 * only consults one or the other.
 */
function ecm_apply_tier_field_rules( $field ) {
	if ( empty( $field['name'] ) || 0 !== strpos( $field['name'], 'lp_' ) ) {
		return $field; // Not one of ours.
	}

	$post_id = ecm_current_profile_form_post_id();
	if ( ! $post_id ) {
		return $field;
	}

	$limits          = ecm_get_listing_limits( $post_id );
	$tier_for_field  = ecm_get_listing_tier( $post_id );

	$field_limit_map = [
		'lp_gallery'        => 'gallery',
		'lp_testimonials'   => 'testimonials',
		'lp_team'           => 'team_members',
		'lp_credentials'    => 'credentials',
		'lp_cities'         => 'cities',
		'lp_faq'            => 'faq',
	];

	if ( ! isset( $field_limit_map[ $field['name'] ] ) ) {
		// Non-repeater fields gated by a simple flag rather than a count.
		if ( 'lp_website' === $field['name'] && empty( $limits['has_website'] ) ) {
			return false;
		}
		if ( 'lp_mission' === $field['name'] && empty( $limits['has_mission'] ) ) {
			return false;
		}
		if ( 'lp_phone' === $field['name'] && ! in_array( $tier_for_field, [ 'standard', 'premium' ], true ) ) {
			return false;
		}
		if ( 'lp_bio' === $field['name'] && 0 === ( $limits['bio_words'] ?? 0 ) ) {
			return false; // Free tier — bio is auto-generated, not editable.
		}
		if ( 'lp_highlights' === $field['name'] && ! in_array( $tier_for_field, [ 'standard', 'premium' ], true ) ) {
			return false; // Standard and Premium only.
		}
		return $field;
	}

	$max = $limits[ $field_limit_map[ $field['name'] ] ] ?? 0;

	if ( 0 === $max ) {
		return false; // Hide the field entirely — not available at this tier.
	}

	$field['max'] = $max; // Works for both 'gallery' and repeater field types.

	return $field;
}
add_filter( 'acf/load_field', 'ecm_apply_tier_field_rules' );
add_filter( 'acf/prepare_field', 'ecm_apply_tier_field_rules' );

/** Server-side: reject a bio over the tier's word limit, with a clear message (rather than silently truncating mid-sentence). */
add_filter( 'acf/validate_value/name=lp_bio', function ( $valid, $value, $field, $input ) {
	if ( true !== $valid || empty( $value ) ) {
		return $valid;
	}
	$post_id = ecm_current_profile_form_post_id();
	if ( ! $post_id ) {
		return $valid;
	}
	$limits    = ecm_get_listing_limits( $post_id );
	$max_words = $limits['bio_words'] ?? 0;
	if ( 0 === $max_words ) {
		return 'Your current package doesn\'t include an editable bio.';
	}
	$word_count = str_word_count( wp_strip_all_tags( $value ) );
	if ( $word_count > $max_words ) {
		return sprintf( 'Your bio is %1$d words — your package allows up to %2$d.', $word_count, $max_words );
	}
	return $valid;
}, 10, 4 );

/**
 * Server-side safety net: even if a request bypasses the UI's `max` (a
 * direct POST, a stale cached page, etc.), truncate repeaters/gallery down
 * to the tier's actual limit before the value is saved. Runs after ACF's
 * own value normalization, right before it hits the database.
 */
add_filter( 'acf/update_value', function ( $value, $post_id, $field ) {
	if ( empty( $field['name'] ) || 0 !== strpos( $field['name'], 'lp_' ) || ! is_array( $value ) ) {
		return $value;
	}

	$field_limit_map = [
		'lp_gallery'        => 'gallery',
		'lp_testimonials'   => 'testimonials',
		'lp_team'           => 'team_members',
		'lp_credentials'    => 'credentials',
		'lp_cities'         => 'cities',
		'lp_faq'            => 'faq',
	];

	if ( ! isset( $field_limit_map[ $field['name'] ] ) ) {
		return $value;
	}

	$limits = ecm_get_listing_limits( (int) $post_id );
	$max    = $limits[ $field_limit_map[ $field['name'] ] ] ?? 0;

	if ( count( $value ) > $max ) {
		$value = array_slice( $value, 0, max( 0, $max ) );
	}

	return $value;
}, 10, 3 );
