<?php
/**
 * ECM Verified — check-cycle data model + admin management screen.
 *
 * Badge rendering/placement on the public site is intentionally NOT wired
 * up from this file yet — that depends on the placement map + exact copy
 * in section 11 of Privacy Compliance: Developer Requirements, which isn't
 * available yet. This file only covers: recording a verification check
 * cycle, computing the current state from it, and an admin screen to see
 * every paid-tier listing's status and log a new check.
 *
 * Per the ECM Verified spec: a boolean "is verified" flag is explicitly
 * disallowed (it goes stale silently). Every check cycle — pass or fail —
 * is stored as its own permanent row; the current badge state is always
 * derived from the latest cycle where all three checks passed, never from
 * a flag that has to be remembered to unset.
 */

defined( 'ABSPATH' ) || exit;

const ECM_VERIFICATION_DB_VERSION = '1.2';
const ECM_VERIFICATION_CAP        = 'manage_options';

// ── Table setup ─────────────────────────────────────────────────────────────

function ecm_verification_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ecm_verification_checks';
}

function ecm_verification_maybe_create_table() : void {
	if ( get_option( 'ecm_verification_db_version' ) === ECM_VERIFICATION_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table           = ecm_verification_table();
	$charset_collate = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		post_id bigint(20) unsigned NOT NULL,
		verified_on date DEFAULT NULL,
		verified_until date DEFAULT NULL,
		verify_entity_result tinyint(1) NOT NULL DEFAULT 0,
		verify_license_result tinyint(1) NOT NULL DEFAULT 0,
		verify_contact_result tinyint(1) NOT NULL DEFAULT 0,
		license_required tinyint(1) NOT NULL DEFAULT 0,
		verify_performed_by bigint(20) unsigned NOT NULL,
		notes text DEFAULT NULL,
		attachment_ids text DEFAULT NULL,
		related_check_id bigint(20) unsigned DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY post_id (post_id)
	) ENGINE=InnoDB {$charset_collate};" );
	update_option( 'ecm_verification_db_version', ECM_VERIFICATION_DB_VERSION );
}
add_action( 'admin_init', 'ecm_verification_maybe_create_table' );

// ── License-required per category (term meta) ───────────────────────────────

foreach ( [ 'job_listing_category_add_form_fields', 'job_listing_category_edit_form_fields' ] as $hook ) {
	add_action( $hook, function ( $term = null ) {
		$checked = $term ? (bool) get_term_meta( $term->term_id, 'ecm_license_required', true ) : false;
		?>
		<div class="form-field term-ecm-license-required-wrap">
			<label for="ecm_license_required">
				<input type="checkbox" name="ecm_license_required" id="ecm_license_required" value="1" <?php checked( $checked ); ?>>
				Requires a state license
			</label>
			<p>Check this if a provider listed under this category needs an active state license/certification — drives the ECM Verified license check and the "no state license required" badge text.</p>
		</div>
		<?php
	} );
}
foreach ( [ 'created_job_listing_category', 'edited_job_listing_category' ] as $hook ) {
	add_action( $hook, function ( $term_id ) {
		update_term_meta( $term_id, 'ecm_license_required', isset( $_POST['ecm_license_required'] ) ? 1 : 0 );
	} );
}

/** Whether ANY category assigned to a listing requires a state license. */
function ecm_listing_license_required( int $post_id ) : bool {
	$term_ids = wp_get_post_terms( $post_id, 'job_listing_category', [ 'fields' => 'ids' ] );
	if ( is_wp_error( $term_ids ) || ! $term_ids ) {
		return false;
	}
	foreach ( $term_ids as $term_id ) {
		if ( get_term_meta( $term_id, 'ecm_license_required', true ) ) {
			return true;
		}
	}
	return false;
}

// ── Check-cycle read/write ───────────────────────────────────────────────────

/**
 * Records one verification attempt as its own permanent row. Only a cycle
 * where all three checks pass sets verified_on/verified_until — a failed
 * attempt is still logged (for the audit trail) but does not extend or
 * grant a badge state.
 *
 * $verified_until_override lets the resulting expiry be moved off the
 * default 12/24-month computation (e.g. a license the provider showed
 * documentation for that's valid longer, or a shorter window because of
 * something noted at check time). Only applied when the cycle actually
 * passes and parses as a real date — a bad or empty value silently falls
 * back to the computed default rather than failing the whole submission.
 */
function ecm_verification_log_check( int $post_id, bool $entity_ok, bool $license_ok, bool $contact_ok, int $performed_by, ?string $verified_until_override = null, string $notes = '', array $attachment_ids = [], int $related_check_id = 0 ) : int {
	global $wpdb;
	$license_required = ecm_listing_license_required( $post_id );
	$all_pass          = $entity_ok && ( ! $license_required || $license_ok ) && $contact_ok;

	$verified_on    = $all_pass ? current_time( 'Y-m-d' ) : null;
	$verified_until = null;
	if ( $all_pass ) {
		$months         = $license_required ? 12 : 24;
		$verified_until = gmdate( 'Y-m-d', strtotime( "{$verified_on} +{$months} months" ) );
		if ( $verified_until_override && false !== strtotime( $verified_until_override ) ) {
			$verified_until = gmdate( 'Y-m-d', strtotime( $verified_until_override ) );
		}
	}

	// Only trust related_check_id if it's a real row belonging to this SAME
	// listing — never take the client-submitted value on faith, since it
	// would otherwise let a mis-posted or tampered request link across
	// unrelated listings' history.
	$related_check_id = $related_check_id && $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . ecm_verification_table() . " WHERE id = %d AND post_id = %d",
		$related_check_id, $post_id
	) ) ? $related_check_id : null;

	$wpdb->insert( ecm_verification_table(), [
		'post_id'               => $post_id,
		'verified_on'           => $verified_on,
		'verified_until'        => $verified_until,
		'verify_entity_result'  => $entity_ok ? 1 : 0,
		'verify_license_result' => $license_ok ? 1 : 0,
		'verify_contact_result' => $contact_ok ? 1 : 0,
		'license_required'      => $license_required ? 1 : 0,
		'verify_performed_by'   => $performed_by,
		'notes'                 => $notes ?: null,
		'attachment_ids'        => $attachment_ids ? wp_json_encode( array_values( array_map( 'intval', $attachment_ids ) ) ) : null,
		'related_check_id'      => $related_check_id,
		'created_at'            => current_time( 'mysql' ),
	] );

	// Order matters: ecm_verification_clear_badge_cache() first, THEN
	// ecm_clear_search_caches(). The latter also wipes ecm_badge_* rows,
	// but via a raw SQL DELETE that WordPress's object cache has no idea
	// happened — delete_option() (which delete_transient() calls) does its
	// own SELECT first and returns early, skipping wp_cache_delete(), when
	// it finds the row already gone. Called in this order, the proper
	// delete_transient() runs while the row still exists and clears the
	// cache correctly; the raw SQL sweep afterward is then a harmless
	// no-op instead of the thing that silently defeats the cache clear.
	ecm_verification_clear_badge_cache( $post_id );
	ecm_clear_search_caches();

	return (int) $wpdb->insert_id;
}

/** The most recent fully-passed check cycle for a listing, or null. */
function ecm_verification_latest_pass( int $post_id ) : ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . ecm_verification_table() . "
		 WHERE post_id = %d AND verified_until IS NOT NULL
		 ORDER BY id DESC LIMIT 1",
		$post_id
	), ARRAY_A );
	return $row ?: null;
}

/** The most recent attempt of any kind (pass or fail), or null. */
function ecm_verification_latest_attempt( int $post_id ) : ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . ecm_verification_table() . "
		 WHERE post_id = %d ORDER BY id DESC LIMIT 1",
		$post_id
	), ARRAY_A );
	return $row ?: null;
}

/**
 * Bulk version of ecm_verification_latest_attempt() — one flat query (a
 * MAX(id)-per-post_id derived table, joined back to the full row) instead
 * of a query per listing. post_id => row map; a listing with no rows is
 * simply absent from the result.
 */
function ecm_verification_bulk_latest_attempts( array $post_ids ) : array {
	if ( ! $post_ids ) {
		return [];
	}
	global $wpdb;
	$table        = ecm_verification_table();
	$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.* FROM {$table} t
		 INNER JOIN (
		     SELECT post_id, MAX(id) AS max_id FROM {$table} WHERE post_id IN ({$placeholders}) GROUP BY post_id
		 ) latest ON latest.post_id = t.post_id AND latest.max_id = t.id",
		...$post_ids
	), ARRAY_A );
	$map = [];
	foreach ( $rows as $r ) {
		$map[ (int) $r['post_id'] ] = $r;
	}
	return $map;
}

/** Every check cycle for a listing, most recent first — the audit history. */
function ecm_verification_history( int $post_id ) : array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . ecm_verification_table() . " WHERE post_id = %d ORDER BY id DESC",
		$post_id
	), ARRAY_A );
}

/**
 * Current state: 'never_checked' | 'active' | 'expiring' | 'expired'.
 * Computed entirely from the LATEST attempt of any kind, pass or fail —
 * never from a stored flag. This deliberately means a failed re-check
 * revokes an existing badge immediately rather than leaving it live until
 * its old verified_until date: per the ECM Verified doc, "when a check
 * comes due and we cannot complete it, the badge comes off" — waiting out
 * the old expiry after a known-failed re-check would be exactly the
 * "checked once and never looked again" problem re-verification exists to
 * prevent.
 */
function ecm_verification_state( int $post_id ) : string {
	return ecm_verification_state_from_row( ecm_verification_latest_attempt( $post_id ) );
}

/** Same state computation as ecm_verification_state(), but from an already-fetched row (bulk-list use). */
function ecm_verification_state_from_row( ?array $latest ) : string {
	if ( ! $latest ) {
		return 'never_checked';
	}
	if ( null === $latest['verified_until'] ) {
		return 'expired';
	}
	$days_left = ( strtotime( $latest['verified_until'] ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS;
	if ( $days_left < 0 ) {
		return 'expired';
	}
	return $days_left <= 30 ? 'expiring' : 'active';
}

/** Paid-tier (non-Free, non-lapsed) job_listing IDs — the pool this screen manages. */
function ecm_verification_eligible_listing_ids() : array {
	global $wpdb;
	$ids = array_map( 'intval', $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'job_listing' AND post_status IN ('publish','expired')"
	) );
	if ( ! $ids ) {
		return [];
	}
	$tiers = ecm_bulk_listing_tier( $ids );
	$lapsed = array_flip( ecm_lapsed_listing_ids() );
	return array_values( array_filter( $ids, function ( $id ) use ( $tiers, $lapsed ) {
		return ( $tiers[ $id ] ?? 'free' ) !== 'free' && ! isset( $lapsed[ $id ] );
	} ) );
}

/**
 * Listings that have verification history but are NOT currently in the
 * paid-eligible pool — i.e. downgraded to Free or lapsed after being
 * checked at some point. Deliberately not "every Free listing": most never
 * had a check at all and don't belong in this screen. This only surfaces
 * the ones with a real record worth not losing track of.
 */
function ecm_verification_changed_plan_ids() : array {
	global $wpdb;
	$checked = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT post_id FROM " . ecm_verification_table() ) );
	if ( ! $checked ) {
		return [];
	}
	return array_values( array_diff( $checked, ecm_verification_eligible_listing_ids() ) );
}

/**
 * Bulk subscription status/dates for a set of listings — one small set of
 * queries regardless of how many IDs, same shape as ecm_bulk_listing_tier().
 * Not cached: this admin screen should always show live billing state, and
 * it's low-traffic (admin-only), unlike the public search paths that do
 * need caching to stay fast.
 */
function ecm_verification_bulk_subscription_info( array $post_ids ) : array {
	if ( ! $post_ids ) {
		return [];
	}
	global $wpdb;
	$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

	$post_to_package = [];
	foreach ( $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_value FROM {$wpdb->postmeta}
		 WHERE meta_key = '_user_package_id' AND post_id IN ({$placeholders}) AND meta_value <> ''",
		...$post_ids
	) ) as $r ) {
		$post_to_package[ (int) $r->post_id ] = (int) $r->meta_value;
	}

	$order_by_package = [];
	$package_ids = array_values( array_unique( array_filter( $post_to_package ) ) );
	if ( $package_ids ) {
		$ph = implode( ',', array_fill( 0, count( $package_ids ), '%d' ) );
		foreach ( $wpdb->get_results( $wpdb->prepare(
			"SELECT id, order_id FROM {$wpdb->prefix}wcpl_user_packages WHERE id IN ({$ph})",
			...$package_ids
		) ) as $r ) {
			$order_by_package[ (int) $r->id ] = (int) $r->order_id;
		}
	}

	$sub_by_order = [];
	$plans = array_values( array_intersect_key( ecm_package_product_ids(), array_flip( [ 'basic', 'standard', 'premium' ] ) ) );
	if ( $plans && function_exists( 'ecm_plan_subscriptions' ) ) {
		foreach ( ecm_plan_subscriptions( $plans ) as $s ) {
			// A customer can have more than one subscription row match the
			// order lookup only in edge cases; last one wins, which is fine
			// for display purposes here (not used for any gating decision).
			$sub_by_order[ (int) $s->id ] = $s;
		}
	}

	$info = [];
	foreach ( $post_ids as $id ) {
		$order_id = $order_by_package[ $post_to_package[ $id ] ?? 0 ] ?? 0;
		$sub      = $sub_by_order[ $order_id ] ?? null;
		$info[ $id ] = $sub ? [
			'status'       => str_replace( 'wc-', '', $sub->status ),
			'next_payment' => $sub->next_payment && '0000-00-00 00:00:00' !== $sub->next_payment ? $sub->next_payment : null,
			'end_date'     => $sub->end_date && '0000-00-00 00:00:00' !== $sub->end_date ? $sub->end_date : null,
		] : null;
	}
	return $info;
}

/** Turns one ecm_verification_bulk_subscription_info() entry into display-ready text. */
function ecm_verification_subscription_display( ?array $sub ) : array {
	if ( ! $sub ) {
		return [ 'status' => 'none', 'label' => 'No subscription', 'date' => '—' ];
	}
	$date = fn( $d ) => $d ? mysql2date( 'M j, Y', $d ) : null;
	switch ( $sub['status'] ) {
		case 'active':
			return [ 'status' => 'active', 'label' => 'Active', 'date' => $date( $sub['next_payment'] ) ? 'Renews ' . $date( $sub['next_payment'] ) : '—' ];
		case 'on-hold':
			return [ 'status' => 'on-hold', 'label' => 'On hold', 'date' => $date( $sub['next_payment'] ) ? 'Payment due ' . $date( $sub['next_payment'] ) : '—' ];
		case 'pending-cancel':
			return [ 'status' => 'pending-cancel', 'label' => 'Pending cancellation', 'date' => $date( $sub['end_date'] ) ? 'Ends ' . $date( $sub['end_date'] ) : '—' ];
		case 'cancelled':
			return [ 'status' => 'cancelled', 'label' => 'Cancelled', 'date' => $date( $sub['end_date'] ) ? 'Ended ' . $date( $sub['end_date'] ) : '—' ];
		case 'expired':
			return [ 'status' => 'expired', 'label' => 'Expired', 'date' => $date( $sub['end_date'] ) ? 'Ended ' . $date( $sub['end_date'] ) : '—' ];
		case 'pending':
			return [ 'status' => 'pending', 'label' => 'Pending', 'date' => '—' ];
		default:
			return [ 'status' => 'none', 'label' => ucfirst( str_replace( '-', ' ', $sub['status'] ) ), 'date' => '—' ];
	}
}

// ── Public badge rendering ───────────────────────────────────────────────────
//
// Placement: only wired into the single listing page hero for now (the one
// placement we can be confident about — it's where the old boolean-based
// badge already lived). The full positive placement map (section 11.1.2
// through 11.1.5 of Privacy Compliance: Developer Requirements) and the
// exact aria-label/hover copy (11.5.4) aren't available yet, so this is
// deliberately not wired into search results, category cards, or anywhere
// else until that's in hand — guessing at additional placements risks
// putting the badge somewhere item 96 actually forbids.
//
// Text and the 3-state behavior below come directly from "Page copy for
// /verified/" and its display-rules table in the ECM Verified doc:
//   verified_until in the future                → "ECM Verified, {Month Year}"
//   verified_until passed, within the last 30 days → same text + "Re-check in progress."
//   verified_until passed more than 30 days ago  → nothing renders at all
// verified_on's month/year is what's shown, never verified_until's.

// TEMPORARILY DISABLED: /verified/ page gate (spec items 52 and 101 — badge must fail closed
// if the page is offline). Re-enable before launch by uncommenting this block and the check
// at the top of ecm_verification_compute_public_badge().
// function ecm_verification_page_is_live() : bool {
// 	$cached = get_transient( ecm_cache_key( 'verified_page_live' ) );
// 	if ( false !== $cached ) {
// 		return (bool) $cached;
// 	}
// 	$page = get_page_by_path( 'verified' );
// 	$live = (bool) ( $page && 'publish' === $page->post_status );
// 	set_transient( ecm_cache_key( 'verified_page_live' ), $live ? 1 : 0, HOUR_IN_SECONDS );
// 	return $live;
// }
//
// add_action( 'transition_post_status', function ( $new, $old, $post ) {
// 	if ( 'page' === $post->post_type && 'verified' === $post->post_name ) {
// 		delete_transient( ecm_cache_key( 'verified_page_live' ) );
// 	}
// }, 10, 3 );

/**
 * Computes what the public badge should show for a listing, or null for no
 * badge. Not cached itself — ecm_verification_public_badge() below is the
 * cached entry point; keeping the raw computation separate makes it easy to
 * unit-test without touching the transient.
 */
function ecm_verification_compute_public_badge( int $post_id ) : ?array {
	// if ( ! ecm_verification_page_is_live() ) {
	// 	return null; // fail closed
	// }
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return null;
	}
	// Badge is for Featured (premium) listings only; lapsed ones display as Free and are excluded.
	if ( 'premium' !== ecm_get_listing_display_tier( $post_id ) ) {
		return null;
	}
	// Must be the latest ATTEMPT that passed, not just the latest pass —
	// otherwise a newer failed re-check wouldn't revoke the badge until its
	// old verified_until date, which is the same bug already fixed once for
	// the admin state computation (see ecm_verification_state()) and has to
	// hold here too: a family looking at the public badge deserves the same
	// "we looked again and it failed" behavior as the internal dashboard.
	$latest = ecm_verification_latest_attempt( $post_id );
	if ( ! $latest || null === $latest['verified_until'] ) {
		return null;
	}
	$pass = $latest;
	$days_since_expiry = ( current_time( 'timestamp' ) - strtotime( $pass['verified_until'] ) ) / DAY_IN_SECONDS;
	if ( $days_since_expiry > 30 ) {
		return null;
	}
	return [
		'month_year'       => date_i18n( 'F Y', strtotime( $pass['verified_on'] ) ),
		'grace'            => $days_since_expiry > 0,
		'license_required' => (bool) $pass['license_required'],
	];
}

/**
 * Cached wrapper around ecm_verification_compute_public_badge(). Short TTL
 * (1 hour) rather than an indefinite cache, because badge state changes on
 * its own as verified_until's date boundaries pass — there's no write event
 * to hook for that, so a bounded TTL is what actually keeps it correct.
 * Explicitly invalidated on every new check cycle (see
 * ecm_verification_log_check()) and on the same tier-source meta changes
 * that invalidate the tier cache, so real edits reflect immediately and the
 * TTL is only a backstop for the passive date-crossing case.
 */
function ecm_verification_public_badge( int $post_id ) : ?array {
	$key    = 'ecm_badge_' . $post_id;
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached ?: null; // an empty array is the cached "no badge" result
	}
	$badge = ecm_verification_compute_public_badge( $post_id );
	set_transient( $key, $badge ?: [], HOUR_IN_SECONDS );
	return $badge;
}

function ecm_verification_clear_badge_cache( int $post_id ) : void {
	delete_transient( 'ecm_badge_' . $post_id );
}

// A tier change affects badge eligibility (only paid tiers qualify), so the
// badge cache needs clearing on the same events that invalidate the tier
// cache. Kept as our own hook here rather than editing
// ecm_tier_source_meta_changed() in package-capabilities.php — WP fires
// every registered callback on these hooks, so this just adds a second,
// independent listener rather than coupling the two concerns together.
foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
	add_action( $hook, function ( $meta_id, $post_id, $meta_key ) {
		if ( in_array( $meta_key, [ '_user_package_id', '_package_id', '_wcpl_jmfe_product_id', 'package_id', '_job_package_id' ], true ) ) {
			ecm_verification_clear_badge_cache( $post_id );
		}
	}, 10, 3 );
}

/** Whether the badge also shows on Find Care result cards. Off by default; toggled on the ECM Verification screen. */
function ecm_verification_cards_enabled() : bool {
	return '1' === (string) get_option( 'ecm_verification_show_on_cards', '0' );
}

foreach ( [ 'add_option_ecm_verification_show_on_cards', 'update_option_ecm_verification_show_on_cards' ] as $hook ) {
	add_action( $hook, 'ecm_clear_search_caches' );
}

/** Visible badge text: month/year always (item 93), grace note, and the no-license note (item 94). */
function ecm_verification_badge_text( array $badge ) : string {
	$text = 'ECM Verified, ' . $badge['month_year'];
	if ( $badge['grace'] ) {
		$text .= '. Re-check in progress.';
	}
	if ( ! $badge['license_required'] ) {
		$text .= ' · No state license required for this service';
	}
	return $text;
}

/** Hover / tap text, exact wording from spec 11.5.4. */
function ecm_verification_badge_hover( array $badge ) : string {
	return 'We confirmed this business is registered, its license is active, and someone there confirmed the listing. ' . $badge['month_year'] . '. See what we checked.';
}

/** aria-label, exact wording from spec 11.5.4. */
function ecm_verification_badge_aria( array $badge ) : string {
	return 'ECM Verified in ' . $badge['month_year'] . '. See what we checked.';
}

/** Ready-to-echo badge markup, or '' when nothing should render. */
function ecm_verification_badge_html( int $post_id ) : string {
	$badge = ecm_verification_public_badge( $post_id );
	if ( ! $badge ) {
		return '';
	}
	$text   = ecm_verification_badge_text( $badge );
	$tip_id = 'ecm-vtip-' . $post_id;
	// The hover/tap text (spec 11.5.4) is a real, focusable-with-the-link popover rather than a title
	// tooltip: it shows on hover and keyboard focus, can be hovered, and closes with Escape (WCAG 1.4.13).
	return sprintf(
		'<span class="ecm-verified-wrap"><a href="%1$s" class="ecm-verified-badge" aria-label="%2$s" aria-describedby="%3$s">%4$s</a><span class="ecm-verified-tip" id="%3$s">%5$s</span></span>',
		esc_url( home_url( '/verified/' ) ),
		esc_attr( ecm_verification_badge_aria( $badge ) ),
		esc_attr( $tip_id ),
		esc_html( $text ),
		esc_html( ecm_verification_badge_hover( $badge ) )
	);
}

// ── Admin screen ──────────────────────────────────────────────────────────────

$GLOBALS['ecm_verification_hook_suffix'] = null;

add_action( 'admin_menu', function () {
	$GLOBALS['ecm_verification_hook_suffix'] = add_submenu_page(
		'edit.php?post_type=job_listing',
		'ECM Verification',
		'ECM Verification',
		ECM_VERIFICATION_CAP,
		'ecm-verification',
		'ecm_verification_render_page'
	);
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$on_own_page   = $hook === $GLOBALS['ecm_verification_hook_suffix'];
	$on_listing_edit = in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && 'job_listing' === get_post_type();
	if ( ! $on_own_page && ! $on_listing_edit ) {
		return;
	}
	// Versioned by each file's own mtime, not the static theme version — a
	// deploy that changes one of these files always gets a new ?ver= query
	// string, so browsers and any intermediate cache fetch it fresh instead
	// of serving a stale copy under an unchanged version number.
	$css_path = get_template_directory() . '/assets/css/ecm-verification-admin.css';
	$css_v    = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
	wp_enqueue_style( 'ecm-verification-admin', get_template_directory_uri() . '/assets/css/ecm-verification-admin.css', [], $css_v );

	if ( ! $on_own_page ) {
		return; // the listing edit screen's meta box only needs the pill styles above, not the JS/media picker
	}
	$js_path = get_template_directory() . '/assets/js/ecm-verification-admin.js';
	$js_v    = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0';
	wp_enqueue_media(); // only needed on the detail screen's "Add files" picker, harmless to load on the list screen too
	wp_enqueue_script( 'ecm-verification-admin', get_template_directory_uri() . '/assets/js/ecm-verification-admin.js', [ 'jquery' ], $js_v, true );
} );

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'ecm_verification_metabox',
		'ECM Verification',
		'ecm_verification_render_metabox',
		'job_listing',
		'side',
		'default'
	);
} );

/** Quick-glance status + a link to the full Manage screen, right on the listing's own edit screen — in case that's a faster path than the ECM Verification list for whoever's editing a specific listing. */
function ecm_verification_render_metabox( WP_Post $post ) : void {
	$tier = ecm_get_listing_display_tier( $post->ID );
	if ( 'free' === $tier ) {
		echo '<p>' . esc_html__( "Free-tier listings aren't eligible for the ECM Verified badge.", 'ecm' ) . '</p>';
		return;
	}

	$state        = ecm_verification_state( $post->ID );
	$state_labels = ecm_verification_status_labels();
	$latest       = ecm_verification_latest_attempt( $post->ID );
	$manage_url   = add_query_arg(
		[ 'action' => 'detail', 'post_id' => $post->ID ],
		admin_url( 'edit.php?post_type=job_listing&page=ecm-verification' )
	);
	?>
	<p>
		<span class="ecm-pill ecm-pill--status-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $state_labels[ $state ] ); ?></span>
	</p>
	<?php if ( $latest ) : ?>
		<p style="color:#646970;font-size:13px;">
			Last logged: <?php echo esc_html( mysql2date( 'M j, Y', $latest['created_at'] ) ); ?><br>
			Valid until: <?php echo esc_html( $latest['verified_until'] ?? '—' ); ?>
		</p>
	<?php else : ?>
		<p style="color:#646970;font-size:13px;">Never checked.</p>
	<?php endif; ?>
	<p>
		<a href="<?php echo esc_url( $manage_url ); ?>" class="button button-primary" style="width:100%;text-align:center;">Manage verification &rarr;</a>
	</p>
	<?php
}

function ecm_verification_render_page() : void {
	if ( ! current_user_can( ECM_VERIFICATION_CAP ) ) {
		wp_die( 'Not allowed.' );
	}

	if ( 'detail' === ( $_GET['action'] ?? '' ) && ! empty( $_GET['post_id'] ) ) {
		ecm_verification_render_detail( (int) $_GET['post_id'] );
		return;
	}

	ecm_verification_render_list();
}

/** Admins/editors — the pool a check cycle can be attributed to. */
function ecm_verification_eligible_performers() : array {
	return get_users( [
		'role__in' => [ 'administrator', 'editor' ],
		'orderby'  => 'display_name',
		'order'    => 'ASC',
	] );
}

function ecm_verification_render_detail( int $post_id ) : void {
	$post = get_post( $post_id );
	if ( ! $post || 'job_listing' !== $post->post_type ) {
		wp_die( 'Listing not found.' );
	}

	$license_required = ecm_listing_license_required( $post_id );
	$notice = '';
	if ( isset( $_POST['ecm_verification_nonce'] ) && wp_verify_nonce( $_POST['ecm_verification_nonce'], 'ecm_verification_log_' . $post_id ) ) {
		$entity_ok  = ! empty( $_POST['entity_ok'] );
		$license_ok = ! empty( $_POST['license_ok'] );
		$contact_ok = ! empty( $_POST['contact_ok'] );
		$until      = sanitize_text_field( $_POST['verified_until'] ?? '' );
		$notes      = sanitize_textarea_field( $_POST['notes'] ?? '' );
		$attach_raw = sanitize_text_field( $_POST['attachment_ids'] ?? '' );
		$attach_ids = $attach_raw ? array_filter( array_map( 'intval', explode( ',', $attach_raw ) ) ) : [];
		$related_id = (int) ( $_POST['related_check_id'] ?? 0 );

		// Trust the submitted performer only if they're a real admin/editor —
		// otherwise fall back to whoever is actually submitting the form,
		// same as before this was made selectable.
		$submitted_by    = (int) ( $_POST['verify_performed_by'] ?? 0 );
		$eligible_ids    = wp_list_pluck( ecm_verification_eligible_performers(), 'ID' );
		$performed_by    = in_array( $submitted_by, $eligible_ids, true ) ? $submitted_by : get_current_user_id();

		ecm_verification_log_check( $post_id, $entity_ok, $license_ok, $contact_ok, $performed_by, $until, $notes, $attach_ids, $related_id );
		$passed = $entity_ok && ( ! $license_required || $license_ok ) && $contact_ok;
		$notice = $passed
			? '<div class="notice notice-success"><p><strong>Passed.</strong> Badge is now active for "' . esc_html( $post->post_title ) . '".</p></div>'
			: '<div class="notice notice-warning"><p><strong>Recorded, not a pass.</strong> At least one check failed — no badge was granted. The attempt is logged for the audit trail.</p></div>';
	}

	if ( isset( $_POST['ecm_verification_remove_nonce'] ) && wp_verify_nonce( $_POST['ecm_verification_remove_nonce'], 'ecm_verification_remove_' . $post_id ) ) {
		$to_remove = ecm_verification_latest_attempt( $post_id );
		if ( $to_remove && null !== $to_remove['verified_until'] ) {
			$remover = wp_get_current_user();
			// Logged as a new failed cycle linked to the one it revokes: cycles are write-once, so the original stays in History.
			ecm_verification_log_check( $post_id, false, false, false, get_current_user_id(), null, 'Verification removed by ' . $remover->display_name . '.', [], (int) $to_remove['id'] );
			$notice = '<div class="notice notice-success"><p><strong>Verification removed.</strong> The badge for "' . esc_html( $post->post_title ) . '" is no longer shown. The removal is logged in History and the previous cycle is kept.</p></div>';
		}
	}

	$tier         = ecm_get_listing_plan_name( $post_id );
	$state        = ecm_verification_state( $post_id );
	$state_labels = ecm_verification_status_labels();
	$sub          = ecm_verification_subscription_display( ecm_verification_bulk_subscription_info( [ $post_id ] )[ $post_id ] ?? null );
	$history      = ecm_verification_history( $post_id );
	$back_url     = admin_url( 'edit.php?post_type=job_listing&page=ecm-verification' );
	$default_months = $license_required ? 12 : 24;
	$default_until   = gmdate( 'Y-m-d', strtotime( "+{$default_months} months" ) );
	?>
	<div class="wrap ecm-verify-wrap">
		<a class="ecm-verify-back-link" href="<?php echo esc_url( $back_url ); ?>">&larr; Back to ECM Verification</a>
		<?php echo $notice; // phpcs:ignore -- built from esc_html() above ?>

		<div class="ecm-verify-log-listing">
			<div>
				<h2><?php echo esc_html( $post->post_title ); ?> <a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>" style="font-size:13px;font-weight:normal;">(edit listing)</a></h2>
				<div class="ecm-verify-meta-row">
					<span class="ecm-verify-meta-group">
						<span class="ecm-verify-meta-label">Plan</span>
						<span class="ecm-pill ecm-pill--plan-<?php echo esc_attr( strtolower( $tier ) ); ?>"><?php echo esc_html( $tier ); ?></span>
					</span>
					<span class="ecm-verify-meta-group">
						<span class="ecm-verify-meta-label">Verification</span>
						<span class="ecm-pill ecm-pill--status-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $state_labels[ $state ] ); ?></span>
					</span>
					<span class="ecm-verify-meta-group">
						<span class="ecm-verify-meta-label">Subscription</span>
						<span class="ecm-pill ecm-pill--sub-<?php echo esc_attr( $sub['status'] ); ?>"><?php echo esc_html( $sub['label'] ); ?></span>
						<span style="color:#646970;font-size:13px;"><?php echo esc_html( $sub['date'] ); ?></span>
					</span>
				</div>
			</div>
		</div>

		<div class="ecm-verify-tabs" role="tablist">
			<button type="button" class="ecm-verify-tab is-active" data-tab="log">Log check</button>
			<button type="button" class="ecm-verify-tab" data-tab="history">History (<?php echo count( $history ); ?>)</button>
		</div>

		<div class="ecm-verify-tab-panel" data-tab-panel="log">
		<?php
		$current = ecm_verification_latest_attempt( $post_id );
		if ( $current ) :
			$cur_passed = null !== $current['verified_until'];
			$cur_user   = get_userdata( (int) $current['verify_performed_by'] );
			?>
		<div class="ecm-verify-log-card ecm-verify-current-card">
			<h3 style="margin-top:0;">Current verification
				<?php $cur_removed = ! $cur_passed && 0 === strpos( (string) $current['notes'], 'Verification removed by' ); ?>
				<span class="ecm-pill ecm-pill--status-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $cur_removed ? 'Removed' : $state_labels[ $state ] ); ?></span>
			</h3>
			<p class="description" style="margin-bottom:12px;">Read-only — this is the latest recorded cycle, which is what the public badge is based on. To change it, record a new cycle below.</p>
			<div class="ecm-verify-check-row ecm-verify-current-row">
				<input type="checkbox" disabled <?php checked( (bool) $current['verify_entity_result'] ); ?>>
				<div><label>Business exists</label></div>
			</div>
			<?php if ( $current['license_required'] ) : ?>
			<div class="ecm-verify-check-row ecm-verify-current-row">
				<input type="checkbox" disabled <?php checked( (bool) $current['verify_license_result'] ); ?>>
				<div><label>License current</label></div>
			</div>
			<?php endif; ?>
			<div class="ecm-verify-check-row ecm-verify-current-row">
				<input type="checkbox" disabled <?php checked( (bool) $current['verify_contact_result'] ); ?>>
				<div><label>Phone confirmation</label></div>
			</div>
			<table class="form-table" style="margin-top:4px">
				<tr><th scope="row">Result</th><td><span class="ecm-pill ecm-pill--status-<?php echo $cur_passed ? 'active' : 'expired'; ?>"><?php echo $cur_passed ? 'Pass' : 'Fail'; ?></span></td></tr>
				<tr><th scope="row">Verified on</th><td><?php echo esc_html( mysql2date( 'M j, Y', $current['verified_on'] ) ); ?></td></tr>
				<tr><th scope="row">Valid until</th><td><?php echo esc_html( $cur_passed ? mysql2date( 'M j, Y', $current['verified_until'] ) : '—' ); ?></td></tr>
				<tr><th scope="row">Verified by</th><td><?php echo esc_html( $cur_user ? $cur_user->display_name : '—' ); ?></td></tr>
				<?php if ( ! empty( $current['notes'] ) ) : ?>
				<tr><th scope="row">Notes</th><td><?php echo esc_html( $current['notes'] ); ?></td></tr>
				<?php endif; ?>
			</table>
			<?php if ( $cur_passed ) : ?>
			<form method="post" class="ecm-verify-remove-form" data-confirm="<?php echo esc_attr( 'Remove the verification for "' . $post->post_title . '"? The badge will disappear from the listing immediately. The removal is logged and the cycle stays in History.' ); ?>">
				<?php wp_nonce_field( 'ecm_verification_remove_' . $post_id, 'ecm_verification_remove_nonce' ); ?>
				<button type="submit" class="button ecm-verify-remove-btn">Remove verification</button>
			</form>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<div class="ecm-verify-log-card">
			<h3 style="margin-top:0;"><?php echo $current ? 'Record a new check cycle' : 'Record the first check cycle'; ?></h3>
			<?php if ( $current ) : ?>
			<p class="description" style="margin-bottom:12px;">Submitting adds a new cycle — it doesn't edit the current one above. Tick everything that passed this time.</p>
			<?php endif; ?>
			<form method="post" id="ecm-verify-log-form" data-current-user-id="<?php echo (int) get_current_user_id(); ?>">
				<?php wp_nonce_field( 'ecm_verification_log_' . $post_id, 'ecm_verification_nonce' ); ?>

				<p class="description" style="margin-bottom:16px;">There's no separate "Verified" checkbox — ticking all applicable checks below and submitting <em>is</em> what grants the badge (or doesn't, if one fails). That's by design: a manual verified toggle is exactly what this system replaced.</p>

				<div class="ecm-verify-check-row">
					<input type="checkbox" name="entity_ok" id="entity_ok" value="1">
					<div>
						<label for="entity_ok">Business exists</label>
						<p>Registered and active with the state's Secretary of State (or county/DBA); the registered name matches the listing.</p>
					</div>
				</div>

				<div class="ecm-verify-check-row<?php echo $license_required ? '' : ' is-disabled'; ?>">
					<input type="checkbox" name="license_ok" id="license_ok" value="1" <?php disabled( ! $license_required ); ?>>
					<div>
						<label for="license_ok">License current</label>
						<p>
							<?php if ( $license_required ) : ?>
								Looked up in the state board/agency database — active, held in the provider's name.
							<?php else : ?>
								Not required — no category on this listing is marked as needing a state license. Wrong?
								<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=job_listing_category&post_type=job_listing' ) ); ?>" target="_blank" rel="noopener">Edit categories</a>
								and tick "Requires a state license" on the ones that need it.
							<?php endif; ?>
						</p>
						<p class="description" style="margin-top:6px;">
							<?php
							$listing_terms = wp_get_post_terms( $post_id, 'job_listing_category' );
							if ( is_wp_error( $listing_terms ) || ! $listing_terms ) {
								echo 'This listing has no category set.';
							} else {
								echo 'This listing\'s categories: ';
								$parts = array_map( function ( $term ) {
									$requires = get_term_meta( $term->term_id, 'ecm_license_required', true );
									return esc_html( $term->name ) . ( $requires ? ' <strong>(requires license)</strong>' : '' );
								}, $listing_terms );
								echo implode( ', ', $parts ); // phpcs:ignore -- esc_html() applied per term above, <strong> is literal markup
							}
							?>
						</p>
					</div>
				</div>

				<div class="ecm-verify-check-row">
					<input type="checkbox" name="contact_ok" id="contact_ok" value="1">
					<div>
						<label for="contact_ok">Phone confirmation</label>
						<p>A real person at the business confirmed the address and the areas served — no voicemail, no form reply.</p>
					</div>
				</div>

				<div class="ecm-verify-result-preview" data-license-required="<?php echo $license_required ? '1' : '0'; ?>">
					<span class="ecm-verify-result-toggle" aria-hidden="true"><span class="ecm-verify-result-knob"></span></span>
					<span class="ecm-verify-result-text">Not all checks are ticked yet — submitting now would <strong>not</strong> grant the badge.</span>
				</div>
				<p class="description" style="margin-top:4px;">This updates live as you tick the checks above — it's a preview, not a control. It's read-only for the same reason there's no separate "Verified" checkbox: the outcome is always computed from the real checks, never set directly.</p>

				<table class="form-table" style="margin-top:8px">
					<tr>
						<th scope="row"><label for="verify_performed_by">Verified by</label></th>
						<td>
							<select name="verify_performed_by" id="verify_performed_by">
								<?php foreach ( ecm_verification_eligible_performers() as $performer ) : ?>
									<option value="<?php echo (int) $performer->ID; ?>" <?php selected( $performer->ID, get_current_user_id() ); ?>><?php echo esc_html( $performer->display_name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Defaults to you — change it only if someone else actually performed this check.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="verified_until">Verification valid until</label></th>
						<td>
							<input type="date" name="verified_until" id="verified_until" value="<?php echo esc_attr( $default_until ); ?>">
							<p class="description">Defaults to <?php echo (int) $default_months; ?> months from today (<?php echo $license_required ? 'licensed' : 'unlicensed'; ?> category). Only takes effect if this cycle passes — adjust it here if there's a reason to grant a different window.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="related_check_id">Relates to / corrects</label></th>
						<td>
							<select name="related_check_id" id="related_check_id">
								<option value="0">— None —</option>
								<?php foreach ( $history as $h ) : ?>
									<option value="<?php echo (int) $h['id']; ?>">
										<?php echo esc_html( mysql2date( 'M j, Y g:ia', $h['created_at'] ) . ' — ' . ( $h['verified_until'] ? 'Pass' : 'Fail' ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">Optional — link this entry to the specific prior one it corrects or follows up on, instead of just describing it in the notes.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="notes">Notes</label></th>
						<td>
							<textarea name="notes" id="notes" rows="3" class="large-text" placeholder="Optional — e.g. reason for an early re-check, or a correction to a prior entry"></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">Supporting files</th>
						<td>
							<input type="hidden" name="attachment_ids" id="ecm-verify-attachment-ids" value="">
							<button type="button" class="button" id="ecm-verify-add-files">Add files&hellip;</button>
							<ul id="ecm-verify-attachment-list" style="margin-top:8px"></ul>
							<p class="description">Optional — e.g. a screenshot of the license lookup, or the confirmation email sent after the phone call. Not required by the standard, kept here for your own record only.</p>
						</td>
					</tr>
				</table>

				<p class="description">All applicable checks must be ticked for this to count as a pass. A failed attempt is still recorded and immediately revokes any current badge.</p>
				<?php submit_button( 'Record check cycle' ); ?>
			</form>
		</div>
		</div>

		<div class="ecm-verify-tab-panel" data-tab-panel="history" style="display:none">
		<div class="ecm-verify-table-card">
			<table class="ecm-verify-table">
				<thead>
					<tr>
						<th>Logged</th>
						<th>Entity</th>
						<th>License</th>
						<th>Contact</th>
						<th>Result</th>
						<th>Valid until</th>
						<th>By</th>
						<th>Notes</th>
						<th>Relates to</th>
						<th>Files</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $history ) : ?>
						<tr><td colspan="10">No check cycles logged yet.</td></tr>
					<?php endif; ?>
					<?php
					$by_id = [];
					foreach ( $history as $h ) { $by_id[ (int) $h['id'] ] = $h; }
					foreach ( $history as $h ) :
						$passed_row = null !== $h['verified_until'];
						$user = get_userdata( (int) $h['verify_performed_by'] );
						$att_ids = $h['attachment_ids'] ? json_decode( $h['attachment_ids'], true ) : [];
						$related = ! empty( $h['related_check_id'] ) ? ( $by_id[ (int) $h['related_check_id'] ] ?? null ) : null;
					?>
					<tr id="check-<?php echo (int) $h['id']; ?>">
						<td><?php echo esc_html( mysql2date( 'M j, Y g:ia', $h['created_at'] ) ); ?></td>
						<td><?php echo $h['verify_entity_result'] ? '✓' : '✗'; ?></td>
						<td><?php echo $h['license_required'] ? ( $h['verify_license_result'] ? '✓' : '✗' ) : '—'; ?></td>
						<td><?php echo $h['verify_contact_result'] ? '✓' : '✗'; ?></td>
						<?php $is_removal = ! $passed_row && 0 === strpos( (string) $h['notes'], 'Verification removed by' ); ?>
						<td><span class="ecm-pill ecm-pill--status-<?php echo $passed_row ? 'active' : 'expired'; ?>"><?php echo $passed_row ? 'Pass' : ( $is_removal ? 'Removed' : 'Fail' ); ?></span></td>
						<td><?php echo esc_html( $h['verified_until'] ?? '—' ); ?></td>
						<td><?php echo esc_html( $user ? $user->display_name : '—' ); ?></td>
						<td><?php echo esc_html( $h['notes'] ?: '—' ); ?></td>
						<td>
							<?php if ( $related ) : ?>
								<a href="#check-<?php echo (int) $related['id']; ?>">&#8617; <?php echo esc_html( mysql2date( 'M j, Y', $related['created_at'] ) ); ?></a>
							<?php else : echo '—'; endif; ?>
						</td>
						<td>
							<?php if ( $att_ids ) : foreach ( $att_ids as $aid ) : ?>
								<a href="<?php echo esc_url( wp_get_attachment_url( $aid ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $aid ) ?: ( '#' . $aid ) ); ?></a><br>
							<?php endforeach; else : echo '—'; endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		</div>
	</div>
	<?php
}

function ecm_verification_status_labels() : array {
	return [
		'all'           => 'All',
		'never_checked' => 'Pending approval',
		'active'        => 'Verified',
		'expiring'      => 'Expiring soon',
		'expired'       => 'Expired',
	];
}

/** Internal tier key ('premium' = Featured) → this screen's tier-scope bucket. */
function ecm_verification_tier_scope( string $internal_tier ) : string {
	return 'premium' === $internal_tier ? 'featured' : 'other';
}

/** post_id => post_title for a set of IDs, one flat query — used for search/sort without a get_the_title() call per row. */
function ecm_verification_bulk_titles( array $ids ) : array {
	if ( ! $ids ) {
		return [];
	}
	global $wpdb;
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ({$placeholders})",
		...$ids
	) );
	$map = [];
	foreach ( $rows as $r ) {
		$map[ (int) $r->ID ] = $r->post_title;
	}
	return $map;
}

/** The pool of listing IDs for one tier-scope bucket — cheap, bulk queries only, safe at any scale. */
function ecm_verification_pool_ids( string $tier_scope, array $eligible_ids, array $changed_ids ) : array {
	if ( 'changed' === $tier_scope ) {
		return $changed_ids;
	}
	if ( 'paid_all' === $tier_scope ) {
		return $eligible_ids;
	}
	$tiers = ecm_bulk_listing_tier( $eligible_ids );
	return array_values( array_filter( $eligible_ids, fn( $id ) => 'premium' === ( $tiers[ $id ] ?? '' ) ) );
}

/** Builds display rows for a set of listing IDs, given a fixed tier_scope for all of them (or null to derive it per-listing from the current tier). */
function ecm_verification_build_rows( array $ids, ?string $forced_scope = null ) : array {
	if ( ! $ids ) {
		return [];
	}
	$tiers   = ecm_bulk_listing_tier( $ids );
	$subs    = ecm_verification_bulk_subscription_info( $ids );
	$latests = ecm_verification_bulk_latest_attempts( $ids );

	$performer_ids = array_values( array_unique( array_filter( array_map( fn( $r ) => (int) $r['verify_performed_by'], $latests ) ) ) );
	if ( $performer_ids ) {
		cache_users( $performer_ids ); // primes WP's user cache so get_userdata() below hits no extra queries
	}

	$rows = [];
	foreach ( $ids as $id ) {
		$latest = $latests[ $id ] ?? null;
		$state  = ecm_verification_state_from_row( $latest );
		$sub    = ecm_verification_subscription_display( $subs[ $id ] ?? null );
		$by     = $latest ? get_userdata( (int) $latest['verify_performed_by'] ) : false;
		$rows[] = [
			'id'             => $id,
			'title'          => get_the_title( $id ),
			'tier'           => $forced_scope ? ecm_get_listing_display_plan_name( $id ) : ecm_get_listing_plan_name( $id ),
			'tier_scope'     => $forced_scope ?? ecm_verification_tier_scope( $tiers[ $id ] ?? '' ),
			'state'          => $state,
			'verified_on'    => $latest['verified_on'] ?? '—',
			'verified_until' => $latest['verified_until'] ?? '—',
			'verified_by'    => $by ? $by->display_name : '—',
			'sub'            => $sub,
		];
	}
	return $rows;
}

/**
 * Real server-side pagination — only the current page's ~25 listings ever
 * get the expensive per-row enrichment (subscription lookup, tier/plan
 * label, performer name). Filtering and counts are done against bulk
 * ID/state lookups (a handful of flat queries regardless of how many
 * listings exist), so this stays fast whether there are 100 listings or
 * 10,000 — the old version built full rows for every eligible listing on
 * every page load and relied on JS to hide/page through them client-side,
 * which doesn't scale past a few hundred rows.
 */
function ecm_verification_render_list() : void {
	$status_labels = ecm_verification_status_labels();
	$per_page      = 25;

	$tier_scope = $_GET['tier_scope'] ?? 'featured';
	if ( ! in_array( $tier_scope, [ 'featured', 'paid_all', 'changed' ], true ) ) {
		$tier_scope = 'featured';
	}
	$status = $_GET['status'] ?? 'all';
	if ( ! isset( $status_labels[ $status ] ) ) {
		$status = 'all';
	}
	$search = trim( sanitize_text_field( $_GET['s'] ?? '' ) );
	$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

	$eligible_ids = ecm_verification_eligible_listing_ids();
	$changed_ids  = ecm_verification_changed_plan_ids();
	$changed_count = count( $changed_ids );
	$pool_ids     = ecm_verification_pool_ids( $tier_scope, $eligible_ids, $changed_ids );

	// Cheap per-id state for the whole pool — one bulk query, no per-row
	// enrichment — used for the tab counts and the status filter.
	$latests   = ecm_verification_bulk_latest_attempts( $pool_ids );
	$state_map = [];
	foreach ( $pool_ids as $id ) {
		$state_map[ $id ] = ecm_verification_state_from_row( $latests[ $id ] ?? null );
	}
	$counts = array_fill_keys( array_keys( $status_labels ), 0 );
	$counts['all'] = count( $pool_ids );
	foreach ( $state_map as $st ) {
		$counts[ $st ]++;
	}

	$filtered_ids = 'all' === $status
		? $pool_ids
		: array_values( array_filter( $pool_ids, fn( $id ) => $state_map[ $id ] === $status ) );

	$titles = ecm_verification_bulk_titles( $filtered_ids );
	if ( '' !== $search ) {
		$needle = mb_strtolower( $search );
		$filtered_ids = array_values( array_filter( $filtered_ids, function ( $id ) use ( $titles, $needle ) {
			return false !== mb_strpos( mb_strtolower( $titles[ $id ] ?? '' ), $needle );
		} ) );
	}
	usort( $filtered_ids, fn( $a, $b ) => strcasecmp( $titles[ $a ] ?? '', $titles[ $b ] ?? '' ) );

	$total       = count( $filtered_ids );
	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$paged       = min( $paged, $total_pages );
	$page_ids    = array_slice( $filtered_ids, ( $paged - 1 ) * $per_page, $per_page );

	// Only the current page's ~25 rows get full enrichment.
	$rows = ecm_verification_build_rows( $page_ids, 'changed' === $tier_scope ? 'changed' : null );

	$base_url = admin_url( 'edit.php?post_type=job_listing&page=ecm-verification' );

	$url_with = function ( array $overrides ) use ( $base_url, $tier_scope, $status, $search, $paged ) {
		$args = array_merge( [
			'tier_scope' => $tier_scope,
			'status'     => $status,
			's'          => $search,
			'paged'      => $paged,
		], $overrides );
		if ( '' === $args['s'] )            unset( $args['s'] );
		if ( (int) $args['paged'] <= 1 )    unset( $args['paged'] );
		if ( 'all' === $args['status'] )    unset( $args['status'] );
		if ( 'featured' === $args['tier_scope'] ) unset( $args['tier_scope'] );
		return add_query_arg( $args, $base_url );
	};

	$showing_from = $total ? ( $paged - 1 ) * $per_page + 1 : 0;
	$showing_to   = min( $paged * $per_page, $total );
	?>
	<div class="wrap ecm-verify-wrap">
		<div class="ecm-verify-header">
			<div>
				<h1>ECM Verification</h1>
				<p>Paid-tier listings only — Free-tier and lapsed-subscription listings aren't eligible for the badge and don't appear here.</p>
			</div>
		</div>

		<p class="description"><a href="<?php echo esc_url( admin_url( 'options-general.php?page=ecm-settings' ) ); ?>">Settings</a> &mdash; show the badge on Find Care cards, and email verification limits.</p>

		<div class="ecm-verify-controls">
			<div class="ecm-verify-toggle" role="group" aria-label="Plan scope">
				<a href="<?php echo esc_url( $url_with( [ 'tier_scope' => 'featured', 'paged' => 1 ] ) ); ?>" class="ecm-verify-toggle-btn<?php echo 'featured' === $tier_scope ? ' is-active' : ''; ?>">Featured</a>
				<a href="<?php echo esc_url( $url_with( [ 'tier_scope' => 'paid_all', 'paged' => 1 ] ) ); ?>" class="ecm-verify-toggle-btn<?php echo 'paid_all' === $tier_scope ? ' is-active' : ''; ?>">Include Basic &amp; Premium</a>
				<a href="<?php echo esc_url( $url_with( [ 'tier_scope' => 'changed', 'paged' => 1 ] ) ); ?>" class="ecm-verify-toggle-btn<?php echo 'changed' === $tier_scope ? ' is-active' : ''; ?>">Changed plans (<?php echo (int) $changed_count; ?>)</a>
			</div>

			<div class="ecm-verify-stats">
				<?php foreach ( $status_labels as $key => $label ) :
					$mod = 'all' === $key ? 'all' : ( 'never_checked' === $key ? 'never' : $key );
				?>
					<a href="<?php echo esc_url( $url_with( [ 'status' => $key, 'paged' => 1 ] ) ); ?>" class="ecm-verify-stat ecm-verify-stat--<?php echo esc_attr( $mod ); ?><?php echo $key === $status ? ' is-active' : ''; ?>">
						<span class="ecm-verify-stat-count"><?php echo (int) $counts[ $key ]; ?></span>
						<span class="ecm-verify-stat-label"><?php echo esc_html( $label ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="ecm-verify-toolbar">
				<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
					<input type="hidden" name="post_type" value="job_listing">
					<input type="hidden" name="page" value="ecm-verification">
					<input type="hidden" name="tier_scope" value="<?php echo esc_attr( $tier_scope ); ?>">
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by listing name&hellip;">
					<button type="submit" class="button">Search</button>
					<?php if ( '' !== $search ) : ?>
						<a href="<?php echo esc_url( $url_with( [ 's' => '', 'paged' => 1 ] ) ); ?>" class="button">Clear</a>
					<?php endif; ?>
				</form>
				<span class="ecm-verify-count-note"><?php echo $total ? esc_html( "{$showing_from}\u{2013}{$showing_to} of {$total}" ) : '0 shown'; ?></span>
			</div>
		</div>

		<div class="ecm-verify-table-card">
			<table class="ecm-verify-table">
				<thead>
					<tr>
						<th>Listing</th>
						<th>Plan</th>
						<th>Verification status</th>
						<th>Verified on</th>
						<th>Verified until</th>
						<th>Verified by</th>
						<th>Subscription</th>
						<th>Renewal / ends</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $rows ) : ?>
						<tr class="ecm-verify-empty"><td colspan="9">No listings match this filter.</td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $r ) :
						$detail_url = add_query_arg( [ 'action' => 'detail', 'post_id' => $r['id'] ], $base_url );
					?>
					<tr<?php echo 'changed' === $r['tier_scope'] ? ' data-tier-scope="changed"' : ''; ?>>
						<td><a class="ecm-verify-listing-title" href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $r['title'] ); ?></a></td>
						<td><span class="ecm-pill ecm-pill--plan-<?php echo esc_attr( strtolower( $r['tier'] ) ); ?>"><?php echo esc_html( $r['tier'] ); ?></span></td>
						<td><span class="ecm-pill ecm-pill--status-<?php echo esc_attr( $r['state'] ); ?>"><?php echo esc_html( $status_labels[ $r['state'] ] ); ?></span></td>
						<td><?php echo esc_html( $r['verified_on'] ); ?></td>
						<td><?php echo esc_html( $r['verified_until'] ); ?></td>
						<td><?php echo esc_html( $r['verified_by'] ); ?></td>
						<td><span class="ecm-pill ecm-pill--sub-<?php echo esc_attr( $r['sub']['status'] ); ?>"><?php echo esc_html( $r['sub']['label'] ); ?></span></td>
						<td><?php echo esc_html( $r['sub']['date'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small">Manage</a>
							<a href="<?php echo esc_url( get_edit_post_link( $r['id'], 'raw' ) ); ?>" class="button button-small">Edit listing</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) :
			$pagination_args = array_filter( [
				'tier_scope' => 'featured' === $tier_scope ? null : $tier_scope,
				'status'     => 'all' === $status ? null : $status,
				's'          => '' === $search ? null : $search,
			], fn( $v ) => null !== $v );
			$pagination_base = add_query_arg( array_merge( $pagination_args, [ 'paged' => '%#%' ] ), $base_url );
			$links = paginate_links( [
				'base'      => $pagination_base,
				'format'    => '',
				'current'   => $paged,
				'total'     => $total_pages,
				'prev_text' => '‹ Prev',
				'next_text' => 'Next ›',
			] );
		?>
			<div class="ecm-verify-pagination"><?php echo $links; // phpcs:ignore -- paginate_links() output is already escaped ?></div>
		<?php endif; ?>
	</div>
	<?php
}
