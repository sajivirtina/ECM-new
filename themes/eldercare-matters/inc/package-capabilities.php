<?php
/**
 * Package → capability tier mapping, and the field limits each tier gets.
 * Single source of truth for "what does this provider's package unlock" —
 * used by both the Add/Edit listing form (inc/acf-provider-listing-fields.php)
 * and the public single-listing template.
 *
 * Confirmed: the live "Choose a package" screen uses 4 real WC products
 * named Free / Basic Listing / Premium Listing / Featured Listing — note
 * this is "Premium/Standard" naming used on the marketing Plans & Pricing
 * page. The tier keys below ('basic'/'standard'/'premium') are just this
 * codebase's internal labels chosen to match the marketing copy — they
 * don't need to match WooCommerce's product titles, only the product IDs
 * below need to be correct.
 *
 * ⚠ STILL UNCONFIRMED: whether product 92 is really "Premium Listing" and
 * 91 is really "Featured Listing" (as currently assumed, carried over from
 * the original Plans & Pricing build), or the reverse. Confirm via the
 * debug panel (functions.php → ECM_PACKAGE_DEBUG) on a listing created
 * under a known package.
 *
 * Package-ID resolution (confirmed against wp-job-manager-listing-payments
 * plugin source, not guessed):
 *   - `_package_id` on the job_listing post is the real WC product ID,
 *     set directly by that plugin's Job_Manager_Submit_Form::process_package().
 *   - When a listing's package was assigned from an ALREADY-OWNED package
 *     (the "Your Packages" section of the package chooser) rather than a
 *     fresh purchase, `_package_id` can end up missing, and only
 *     `_user_package_id` is set — but that value is a row ID from the
 *     `wp_wcpl_user_packages` table, NOT a WC product ID. Resolving it
 *     requires the plugin's own `astoundify_wpjmlp_get_user_package()`
 *     helper → `->get_product_id()`. Treating that row ID as if it were a
 *     product ID (the original bug) silently failed to match anything in
 *     ecm_package_product_ids() and defaulted everything to 'free' — this
 *     was confirmed live: a listing on "Standard Listing (Current)" was
 *     resolving to Free because only _user_package_id was set.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The job's ID for the CURRENT request — checks $_GET first (the Edit
 * screen's URL, ?action=edit&job_id=X), then $_POST (the Add flow's
 * multi-step form, which passes job_id via a hidden field once the
 * listing exists — NEVER as a URL query param on Add). Several places
 * were checking $_GET only, meaning job_id always resolved to 0 during
 * Add's core-fields step submission, forcing tier resolution to rely
 * purely on the chosen_package_id cookie fallback with no ability to
 * cross-check against the real, by-then-existing post meta.
 */
function ecm_get_current_job_id_from_request() : int {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup, not a state change.
	if ( ! empty( $_GET['job_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return (int) $_GET['job_id'];
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only lookup; the form itself is nonce-protected on actual save.
	if ( ! empty( $_POST['job_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return (int) $_POST['job_id'];
	}
	return 0;
}

/** WC product ID → tier key. */
function ecm_package_product_ids() : array {
	return [
		'free'     => 283094,
		'basic'    => 68641,
		'standard' => 92, // Live product name: "Premium Listing" — see docblock above.
		'premium'  => 91, // Live product name: "Featured Listing" — see docblock above.
	];
}

/**
 * Field limits per tier. `0` means "not available at this tier" (field
 * hidden entirely); a positive number is the max repeater rows / gallery
 * images allowed; `-1` means "unlimited" (used by `subcategories` on the
 * Featured/premium tier only — no other limit here currently uses -1, so
 * any new code reading a limit that could legitimately be unlimited must
 * check for -1 explicitly rather than just treating "not 0" as a hard cap).
 *
 * `subcategories` = max number of job_listing_category SUBcategory terms
 * (children) a listing may carry at once, via the Category → Subcategory
 * cascading picker (inc/wpjm-cascading-category-field.php). The single
 * top-level Category pick itself does not count against this limit.
 */
function ecm_tier_limits( string $tier ) : array {
	$limits = [
		'free' => [
			'bio_words'      => 0,   // no editable bio at Free — auto-generated
			'gallery'        => 0,
			'testimonials'   => 0,
			'team_members'   => 0,
			'credentials'    => 0,
			'cities'         => 0,   // no coverage-cities section at Free — primary location comes from the core listing field, shown in the header
			'faq'            => 1,
			'featured_posts' => 1,
			'subcategories'  => 1,
			'has_website'    => false,
			'has_mission'    => false,
		],
		'basic' => [
			'bio_words'      => 50,
			'gallery'        => 2,
			'testimonials'   => 0,
			'team_members'   => 0,
			'credentials'    => 0,
			'cities'         => 0,   // per comparison table: no coverage-cities section at Basic either
			'faq'            => 2,
			'featured_posts' => 1,
			'subcategories'  => 3,
			'has_website'    => false,
			'has_mission'    => false,
		],
		'standard' => [
			'bio_words'      => 250,
			'gallery'        => 5,
			'testimonials'   => 3,
			'team_members'   => 2,
			'credentials'    => 4,
			'cities'         => 4,
			'faq'            => 3,
			'featured_posts' => 2,
			'subcategories'  => 5,
			'has_website'    => false,
			'has_mission'    => false,
		],
		'premium' => [
			'bio_words'      => 500,
			'gallery'        => 10,
			'testimonials'   => 4,
			'team_members'   => 4, // includes the "spotlight" member
			'credentials'    => 6,
			'cities'         => 9,
			'subcategories'  => -1, // unlimited — Featured plan
			'faq'            => 5,
			'featured_posts' => 4,
			'has_website'    => true,
			'has_mission'    => true,
		],
	];

	return $limits[ $tier ] ?? $limits['free'];
}

/**
 * Reads the WC package product ID a listing was purchased/assigned under.
 * See the docblock at the top of this file for why `_user_package_id`
 * needs a resolution step rather than being read directly.
 */
function ecm_get_listing_package_product_id( int $post_id ) : ?int {
	// A user-package assignment (_user_package_id) is the most authoritative
	// source: it is set when the provider assigns a pre-purchased slot to this
	// listing (e.g. upgrades from free → premium). When present it supersedes
	// _package_id, which may still hold the original/stale value from the
	// listing's first creation and is not updated on package reassignment.
	$user_package_id = get_post_meta( $post_id, '_user_package_id', true );
	if ( '' !== $user_package_id && null !== $user_package_id && function_exists( 'astoundify_wpjmlp_get_user_package' ) ) {
		// astoundify_wpjmlp_get_user_package() does a raw $wpdb->get_row() and,
		// if the row is gone (deleted/orphaned _user_package_id), still wraps
		// that null in a Package object rather than returning null itself —
		// so the object is always truthy and method_exists() always passes;
		// calling get_product_id() on it then triggers "Attempt to read
		// property on null" inside the plugin's own class. Confirmed live:
		// one of this install's listings has exactly this orphaned reference.
		// Check the row is real first, entirely on our side, before trusting
		// the plugin's wrapper.
		global $wpdb;
		$row_exists = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->prefix}wcpl_user_packages WHERE id = %d",
			(int) $user_package_id
		) );
		if ( $row_exists ) {
			$user_package = astoundify_wpjmlp_get_user_package( (int) $user_package_id );
			if ( $user_package && method_exists( $user_package, 'get_product_id' ) ) {
				$resolved = (int) $user_package->get_product_id();
				if ( $resolved ) {
					return $resolved;
				}
			}
		}
	}

	// Field Editor's own multi-plugin resolver as a second fallback — covers
	// payment plugins that use meta keys other than _user_package_id, and
	// normalises "user-{id}" formatted values the same way ecm_resolve_current_
	// submission_tier() already does. Only accept the result if it maps to one
	// of our known tier product IDs (prevents returning an unrelated post ID).
	if ( class_exists( 'WP_Job_Manager_Field_Editor_Package_WC' ) && function_exists( 'ecm_package_product_ids' ) ) {
		$fe_raw = WP_Job_Manager_Field_Editor_Package_WC::get_post_package_id( $post_id );
		if ( $fe_raw ) {
			$fe_pid = (int) WP_Job_Manager_Field_Editor_Package_WC::get_product_id( $fe_raw );
			if ( $fe_pid && in_array( $fe_pid, array_values( ecm_package_product_ids() ), true ) ) {
				return $fe_pid;
			}
		}
	}

	// Direct WC product ID — the package used for a fresh purchase (no
	// pre-owned slot). For listings that were upgraded via a user package, this
	// value may be stale; the _user_package_id block above takes priority.
	$direct = get_post_meta( $post_id, '_package_id', true );
	if ( '' !== $direct && null !== $direct ) {
		return (int) $direct;
	}

	// Legacy/alternate meta key names some setups use — last resort.
	foreach ( [ 'package_id', '_job_package_id' ] as $key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( '' !== $value && null !== $value ) {
			return (int) $value;
		}
	}

	return null;
}

/**
 * Resolves a job_listing post to its capability tier key. Defaults to
 * 'free' if no package is found. Read-through cached as '_ecm_tier' post
 * meta — the underlying (non-lapsed-adjusted) plan, same value
 * ecm_get_listing_display_tier() falls back to when the subscription is
 * live. The cache is invalidated below whenever the listing's package
 * assignment actually changes, so a switch/upgrade/renewal is reflected on
 * its next read, and populated automatically the first time any new or
 * existing listing is resolved — no separate "new subscription" handling
 * needed. The lapsed/on-hold "shows as Free" rule stays entirely outside
 * this cache (ecm_get_listing_display_tier() layers it on top at read
 * time), so it keeps working exactly as before.
 */
function ecm_get_listing_tier( int $post_id ) : string {
	$cached = get_post_meta( $post_id, '_ecm_tier', true );
	if ( '' !== $cached ) {
		return $cached;
	}
	$product_id = ecm_get_listing_package_product_id( $post_id );
	$tier       = $product_id ? ( array_search( $product_id, ecm_package_product_ids(), true ) ?: 'free' ) : 'free';
	update_post_meta( $post_id, '_ecm_tier', $tier );
	return $tier;
}

/**
 * Bulk version of ecm_get_listing_tier(): id => tier key, read-through the
 * same '_ecm_tier' cache for as many listings as already have it (one
 * indexed query), resolving and caching only the ones that don't yet.
 */
function ecm_bulk_listing_tier( array $ids ) : array {
	if ( ! $ids ) {
		return [];
	}
	global $wpdb;
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	$in  = implode( ',', $ids );

	$tiers = [];
	foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_ecm_tier' AND post_id IN ($in)" ) as $row ) {
		$tiers[ (int) $row->post_id ] = $row->meta_value;
	}

	$missing = array_values( array_diff( $ids, array_keys( $tiers ) ) );
	if ( $missing ) {
		$min     = implode( ',', $missing );
		$product = array_fill_keys( $missing, null );
		foreach ( $wpdb->get_results( "SELECT um.post_id pid, up.product_id prod FROM {$wpdb->postmeta} um JOIN {$wpdb->prefix}wcpl_user_packages up ON up.id = um.meta_value WHERE um.meta_key = '_user_package_id' AND um.post_id IN ($min)" ) as $row ) {
			$product[ (int) $row->pid ] = (int) $row->prod;
		}
		foreach ( [ '_wcpl_jmfe_product_id', '_package_id', 'package_id', '_job_package_id' ] as $key ) {
			$remaining = array_keys( array_filter( $product, fn( $v ) => null === $v ) );
			if ( ! $remaining ) {
				break;
			}
			$rin = implode( ',', $remaining );
			foreach ( $wpdb->get_results( "SELECT post_id pid, meta_value val FROM {$wpdb->postmeta} WHERE meta_key = '$key' AND post_id IN ($rin) AND meta_value <> ''" ) as $row ) {
				$product[ (int) $row->pid ] = (int) $row->val;
			}
		}
		$tier_by_product = array_flip( ecm_package_product_ids() );
		$rows            = [];
		$values          = [];
		foreach ( $missing as $id ) {
			$tier         = $tier_by_product[ $product[ $id ] ?? 0 ] ?? 'free';
			$tiers[ $id ] = $tier;
			$rows[]       = '(%d, %s, %s)';
			array_push( $values, $id, '_ecm_tier', $tier );
		}
		// A single bulk INSERT instead of one update_post_meta() call per
		// listing — the latter takes ~4 queries and several seconds across
		// the whole site the first time it runs cold. Safe as a plain INSERT
		// (not update_post_meta(), which would duplicate-check first) because
		// $missing is, by construction, exactly the ids with no '_ecm_tier'
		// row yet. Deliberately not calling update_meta_cache() here — for a
		// large $missing set that pulls every OTHER meta field (gallery,
		// bio, FAQ, ...) for every one of those listings too, which is far
		// slower than the occasional single indexed lookup a same-request
		// get_post_meta() re-read would otherwise cost.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ', ', $rows ), $values ) );
	}
	return $tiers;
}

/**
 * Bulk display-tier rank for many listings at once: 0 = premium (Featured),
 * 1 = standard (Premium), 2 = basic, 3 = free — same order and same "lapsed
 * subscription shows as Free" rule as ecm_get_listing_display_tier(), built
 * on the cached ecm_bulk_listing_tier() above.
 */
function ecm_listing_tier_ranks( array $ids ) : array {
	if ( ! $ids ) {
		return [];
	}
	global $wpdb;
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	$in  = implode( ',', $ids );

	$status = [];
	foreach ( $wpdb->get_results( "SELECT ID, post_status FROM {$wpdb->posts} WHERE ID IN ($in)" ) as $row ) {
		$status[ (int) $row->ID ] = $row->post_status;
	}

	$tiers        = ecm_bulk_listing_tier( $ids );
	$inactive     = array_flip( function_exists( 'ecm_inactive_subscription_listing_ids' ) ? ecm_inactive_subscription_listing_ids() : [] );
	$rank_by_tier = [ 'premium' => 0, 'standard' => 1, 'basic' => 2, 'free' => 3 ];

	$ranks = [];
	foreach ( $ids as $id ) {
		if ( 'expired' === ( $status[ $id ] ?? '' ) || isset( $inactive[ $id ] ) ) {
			$ranks[ $id ] = 3;
			continue;
		}
		$ranks[ $id ] = $rank_by_tier[ $tiers[ $id ] ?? 'free' ] ?? 3;
	}
	return $ranks;
}

/** Public plan name for the listing's linked WC package product — derived from the same cached tier as ecm_get_listing_tier(). */
function ecm_get_listing_plan_name( int $post_id ) : string {
	$tier       = ecm_get_listing_tier( $post_id );
	$plan_names = [
		'free'     => 'Free',
		'basic'    => 'Basic',
		'standard' => 'Premium',
		'premium'  => 'Featured',
	];

	return $plan_names[ $tier ] ?? '';
}

/**
 * The cached '_ecm_tier' meta reflects the listing's package assignment at
 * the time it was last resolved — invalidate it whenever any of the meta
 * keys that assignment is read from actually changes (a purchase, an
 * already-owned-package assignment, a switch/upgrade/downgrade), so the
 * next read resolves and caches the new value. Same pattern already used
 * for '_expired_subscription_id' in inc/lapsed-listings.php.
 */
function ecm_tier_source_meta_changed( $meta_id, $post_id, $meta_key ) {
	if ( ! in_array( $meta_key, [ '_user_package_id', '_package_id', '_wcpl_jmfe_product_id', 'package_id', '_job_package_id' ], true ) ) {
		return;
	}
	delete_post_meta( $post_id, '_ecm_tier' );
	if ( function_exists( 'ecm_clear_search_caches' ) ) {
		ecm_clear_search_caches();
	}
}
add_action( 'added_post_meta', 'ecm_tier_source_meta_changed', 10, 3 );
add_action( 'updated_post_meta', 'ecm_tier_source_meta_changed', 10, 3 );
add_action( 'deleted_post_meta', 'ecm_tier_source_meta_changed', 10, 3 );

/** Convenience: limits for a given post, in one call. */
function ecm_get_listing_limits( int $post_id ) : array {
	return ecm_tier_limits( ecm_get_listing_tier( $post_id ) );
}

/** Inline CSS (background/color/border) for a tier badge — shared by the Add/Edit listing headers in the provider dashboard. */
function ecm_tier_badge_style( string $tier ) : string {
	$styles = [
		'free'     => 'background:#f5f5f5; color:#999; border:1px dashed #ccc;',
		'basic'    => 'background:#EFECE6; color:#6b6b6b; border:1px solid #ddd;',
		'standard' => 'background:#e8f0ea; color:#3f6b53; border:1px solid #b8d4c2;',
		'premium'  => 'background:#fbf0d9; color:#7a5a1e; border:1px solid #e8cf94;',
	];
	return $styles[ $tier ] ?? $styles['free'];
}

/**
 * TEMPORARY DEBUG PANEL — see the ECM_PACKAGE_DEBUG toggle in functions.php.
 * Prints a small floating, collapsible panel showing exactly what the
 * package/tier system sees for a given listing: every raw post meta key,
 * which key (if any) ecm_get_listing_package_product_id() matched (and,
 * now, the fully-resolved product ID when it came via _user_package_id),
 * the resolved tier, and the resulting field limits.
 *
 * Admin-only (manage_options) by default — loosen the capability check
 * below if a provider account needs to see it while testing.
 */
function ecm_debug_panel( int $post_id, string $context ) : void {
	if ( ! defined( 'ECM_PACKAGE_DEBUG' ) || ! ECM_PACKAGE_DEBUG ) {
		return;
	}
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$known_ids   = ecm_package_product_ids();
	$found_key   = null;
	$found_value = null;
	$meta_keys_to_try = [ '_package_id', 'package_id', '_job_package_id', '_user_package_id' ];

	if ( $post_id ) {
		foreach ( $meta_keys_to_try as $key ) {
			$v = get_post_meta( $post_id, $key, true );
			if ( '' !== $v && null !== $v ) {
				$found_key   = $key;
				$found_value = $v;
				break;
			}
		}
	}

	$resolved_product_id = $post_id ? ecm_get_listing_package_product_id( $post_id ) : null;
	$tier                = $post_id ? ecm_get_listing_tier( $post_id ) : 'n/a';
	$limits              = $post_id ? ecm_get_listing_limits( $post_id ) : [];
	$all_meta            = $post_id ? get_post_meta( $post_id ) : [];

	// UPDATED for the native-fields system (ACF is no longer used at all).
	// Two different resolutions shown together, since they can legitimately
	// differ: ecm_get_listing_tier() reads ONLY real post meta (accurate
	// once a listing+package are fully saved); ecm_resolve_current_submission_tier()
	// is what the ADD/EDIT FORM ITSELF sees right now — including the
	// Field Editor package-resolution + cookie fallbacks needed mid-flow,
	// before post meta exists yet. A mismatch between the two is expected
	// while adding a brand-new listing; a mismatch on an EXISTING listing's
	// Edit screen would be the bug to chase.
	$submission_tier = function_exists( 'ecm_resolve_current_submission_tier' ) ? ecm_resolve_current_submission_tier( $post_id ) : 'n/a';

	$field_limit_map = [
		'gallery_images'  => 'gallery',
		'lp_faq'          => 'faq',
		'lp_testimonials' => 'testimonials',
		'lp_team'         => 'team_members',
		'lp_credentials'  => 'credentials',
		'lp_cities'       => 'cities',
	];
	?>
	<div id="ecm-debug-panel" style="position:fixed; bottom:16px; right:16px; z-index:999999; width:380px; max-height:75vh; overflow-y:auto; background:#1a1a1a; color:#d6ffd6; font-family:Menlo,Consolas,monospace; font-size:11px; line-height:1.5; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.4); border:1px solid #444;">
		<details open>
			<summary style="cursor:pointer; padding:8px 12px; background:#2a2a2a; border-radius:8px 8px 0 0; color:#fff; font-weight:bold;">
				🐛 ECM Package Debug — <?php echo esc_html( $context ); ?>
			</summary>
			<div style="padding:10px 12px;">
				<div style="color:#ffd479;">post_id passed to this panel:</div> <?php echo esc_html( $post_id ?: '(none — no listing yet)' ); ?>

				<div style="color:#ffd479; margin-top:8px;">tier resolved by ecm_get_listing_tier() (post meta only):</div>
				<strong style="color:#7fffd4;"><?php echo esc_html( $tier ); ?></strong>

				<div style="color:#ffd479; margin-top:8px;">tier resolved by ecm_resolve_current_submission_tier() (what the form itself sees right now — includes cookie/Field-Editor fallback):</div>
				<strong style="color:<?php echo ( $submission_tier === $tier ) ? '#7fffd4' : '#ffb454'; ?>;">
					<?php echo esc_html( $submission_tier ); ?>
				</strong>
				<?php if ( $submission_tier !== $tier ) : ?>
					<div style="color:#ffb454;">ℹ Differs from the post-meta tier above — expected mid-flow on a new listing; worth investigating if this is an existing listing's Edit screen.</div>
				<?php endif; ?>

				<div style="color:#ffd479; margin-top:8px;">known package product IDs (from ecm_package_product_ids()):</div>
				<?php foreach ( $known_ids as $t => $pid ) : ?>
					<?php echo esc_html( $t ); ?> => <?php echo esc_html( $pid ); ?><br>
				<?php endforeach; ?>

				<div style="color:#ffd479; margin-top:8px;">matched package meta key on this post (raw value):</div>
				<?php echo $found_key ? esc_html( $found_key . ' = ' . $found_value ) : '(none of ' . esc_html( implode( ' / ', $meta_keys_to_try ) ) . ' found any value)'; ?>

				<div style="color:#ffd479; margin-top:8px;">resolved product ID (after _user_package_id lookup, if needed):</div>
				<strong style="color:#7fffd4;"><?php echo esc_html( $resolved_product_id ?? '(none resolved)' ); ?></strong>

				<div style="color:#ffd479; margin-top:8px;">package usage counted for this listing? (_ecm_package_counted):</div>
				<strong style="color:<?php echo $post_id && get_post_meta( $post_id, '_ecm_package_counted', true ) ? '#7fffd4' : '#ffb454'; ?>;">
					<?php echo esc_html( $post_id && get_post_meta( $post_id, '_ecm_package_counted', true ) ? 'YES — counted' : 'NO — not yet counted' ); ?>
				</strong>

				<div style="color:#ffd479; margin-top:8px;">bio / description word limit (job_description, native field):</div>
				<strong style="color:#7fffd4;"><?php echo esc_html( $limits['bio_words'] ?? 0 ); ?> words</strong>

				<?php if ( $limits ) : ?>
					<div style="color:#ffd479; margin-top:8px;">expected field visibility at this tier (native fields):</div>
					<?php foreach ( $field_limit_map as $field_name => $limit_key ) :
						$max = $limits[ $limit_key ] ?? 0;
					?>
						<div style="color:<?php echo $max > 0 ? '#7fffd4' : '#888'; ?>;">
							<?php echo esc_html( $field_name ); ?>: <?php echo $max > 0 ? 'SHOWN, max ' . esc_html( $max ) : 'HIDDEN (limit is 0)'; ?>
						</div>
					<?php endforeach; ?>

					<div style="color:#ffd479; margin-top:8px;">full resolved limits:</div>
					<?php foreach ( $limits as $k => $v ) : ?>
						<?php echo esc_html( $k ); ?>: <?php echo esc_html( is_bool( $v ) ? ( $v ? 'true' : 'false' ) : $v ); ?><br>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php
				// TEMPORARY — raw $_POST for the native fields, on THIS
				// exact request. If this panel renders on the same
				// response as a form submission (which it should, since
				// WPJM's multi-step form doesn't redirect between steps),
				// this shows definitively whether the submitted data made
				// it into $_POST at all — settling "did the browser send
				// it" vs "WPJM/our code lost it during save" once and for
				// all, without needing another round of guessing.
				$raw_post_keys = [ 'gallery_images', 'job_description', 'lp_faq', 'lp_testimonials', 'lp_team', 'lp_credentials', 'lp_cities', 'job_package', 'job_manager_form', 'step' ];
				$has_any_post  = false;
				foreach ( $raw_post_keys as $k ) {
					if ( isset( $_POST[ $k ] ) ) { $has_any_post = true; break; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostic dump, admin-only.
				}
				if ( $has_any_post ) :
				?>
					<div style="color:#ffd479; margin-top:8px;">🔴 RAW $_POST on this request (native fields):</div>
					<div style="max-height:220px; overflow-y:auto; border-top:1px dashed #444; margin-top:4px; padding-top:4px;">
						<?php foreach ( $raw_post_keys as $k ) :
							if ( ! isset( $_POST[ $k ] ) ) { continue; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							$v = wp_unslash( $_POST[ $k ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only diagnostic dump, not used for any action.
							$preview = is_array( $v ) ? wp_json_encode( $v ) : (string) $v;
						?>
							<div style="color:#7fffd4;"><?php echo esc_html( $k ); ?> => <?php echo esc_html( mb_substr( $preview, 0, 300 ) ); ?></div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div style="color:#888; margin-top:8px;">(no $_POST data on this request — this is a fresh GET load, not a form submission)</div>
				<?php endif; ?>

				<?php if ( $post_id ) : ?>
					<div style="color:#ffd479; margin-top:8px;">ALL post meta on this listing:</div>
					<div style="max-height:200px; overflow-y:auto; border-top:1px dashed #444; margin-top:4px; padding-top:4px;">
						<?php foreach ( $all_meta as $key => $values ) :
							$val = maybe_unserialize( $values[0] ?? '' );
							$preview = is_array( $val ) ? wp_json_encode( $val ) : (string) $val;
						?>
							<div style="color:<?php echo ( false !== stripos( $key, 'package' ) ) ? '#7fffd4' : '#d6ffd6'; ?>;">
								<?php echo esc_html( $key ); ?> => <?php echo esc_html( mb_substr( $preview, 0, 100 ) ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</details>
	</div>
	<?php
}
