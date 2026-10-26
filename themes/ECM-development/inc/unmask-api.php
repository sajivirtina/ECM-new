<?php
/**
 * Unmask endpoint — Privacy Compliance: Developer Requirements, items 30-33.
 *
 * Returns full contact details and raw notes for a request, but only to a
 * provider that was actually picked for it, and only once their plan
 * tier's window has opened. Windows run absolutely from submitted_at and
 * never open early just because no higher tier was picked (item 31) — a
 * Basic provider always waits until hour 48, Featured-or-not.
 *
 * Also provides ecm_leads_for_provider() — the read model behind the
 * dashboard's Lead Inbox (provider-dashboard/sections/leads.php), which
 * drives the actual "Request Contact Details" button from doc section 5 /
 * 10.5.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'ecm/v1', '/unmask', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'ecm_handle_unmask_request',
		'permission_callback' => 'is_user_logged_in',
		'args'                => [
			'request_id' => [ 'type' => 'integer', 'required' => true ],
		],
	] );
} );

/**
 * Hour at which a tier's unmask window opens, counted from submitted_at.
 * Matches the search/package tier keys used elsewhere in this theme:
 * 'premium' = the "Featured" public tier, 'standard' = "Premium",
 * 'basic' = "Basic", everything else = "Free". (See package-capabilities.php.)
 */
function ecm_unmask_window_start_hour( string $tier ) : int {
	switch ( $tier ) {
		case 'premium':  return 0;  // Featured
		case 'standard': return 24; // Premium
		case 'basic':    return 48; // Basic
		default:         return 72; // Free
	}
}

/** Max unmask REQUESTS (granted or denied) a single account may make per rolling hour [5.2]. */
function ecm_unmask_rate_limit() : int {
	return (int) apply_filters( 'ecm_unmask_rate_limit', 20 );
}

/**
 * Per-account rate limit on unmask attempts [5.2]. Counts every attempt
 * (granted or denied) against the limit — denials are the case most worth
 * throttling (repeated probing), and the log already records every attempt
 * regardless of this gate.
 */
function ecm_unmask_rate_limited( int $uid ) : bool {
	$key   = 'ecm_unmask_rl_' . $uid;
	$count = (int) get_transient( $key );
	if ( $count >= ecm_unmask_rate_limit() ) {
		return true;
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return false;
}

/** Unix timestamp a request's unmask window opens for the given tier. */
function ecm_unmask_window_opens_at( int $submitted_ts, string $tier ) : int {
	return $submitted_ts + ecm_unmask_window_start_hour( $tier ) * HOUR_IN_SECONDS;
}

function ecm_handle_unmask_request( WP_REST_Request $request ) {
	$request_id  = (int) $request->get_param( 'request_id' );
	$uid         = get_current_user_id();

	if ( ecm_unmask_rate_limited( $uid ) ) {
		return new WP_Error(
			'rate_limited',
			'Too many requests. Please try again later.',
			[ 'status' => 429 ]
		);
	}

	// Picked providers (the family's own choice) are never state-restricted —
	// try that list first. Only a provider who reached this lead through
	// expedited matching (items 37-40), not the family's own picks, is
	// subject to the MD/WA/NV masked-only restriction [36].
	$provider_id       = ecm_resolve_unmask_provider_id( $request_id );
	$via_expedited     = false;
	if ( ! $provider_id ) {
		$provider_id   = function_exists( 'ecm_resolve_expedited_unmask_provider_id' )
			? ecm_resolve_expedited_unmask_provider_id( $request_id )
			: 0;
		$via_expedited = (bool) $provider_id;
	}

	if ( ! $provider_id ) {
		// Not logged in as (any listing belonging to) a picked OR expedited
		// provider for this request at all — not a timing rejection, but
		// still logged and still a 403, so no information about the
		// request leaks.
		ecm_log_distribution_event( $request_id, 0, 'unmask_denied' );
		return new WP_Error( 'not_picked', 'You were not picked for this request.', [ 'status' => 403 ] );
	}

	// [36] Expedited (non-picked) providers can never unmask a request whose
	// care_state or submitter_state is MD, WA or NV — regardless of window
	// timing. They still received the masked payload; only unmasking is
	// blocked, per the Consumer Health Data Notice §4.
	if ( $via_expedited && function_exists( 'ecm_lead_is_masked_only_state' ) && ecm_lead_is_masked_only_state( $request_id ) ) {
		ecm_log_distribution_event( $request_id, $provider_id, 'unmask_denied', ecm_get_listing_tier( $provider_id ) );
		return new WP_Error(
			'state_restricted',
			"This family's request is limited to the providers they selected.",
			[ 'status' => 403 ]
		);
	}

	$lead = get_post( $request_id );
	if ( ! $lead || 'ecm_lead' !== $lead->post_type ) {
		return new WP_Error( 'not_found', 'Request not found.', [ 'status' => 404 ] );
	}

	$tier          = function_exists( 'ecm_get_listing_tier' ) ? ecm_get_listing_tier( $provider_id ) : 'free';
	$submitted_at  = (string) get_post_meta( $request_id, '_ecm_submitted_at', true );
	$submitted_ts  = $submitted_at ? strtotime( $submitted_at . ' UTC' ) : (int) strtotime( $lead->post_date_gmt . ' UTC' );
	$hours_elapsed = $submitted_ts ? ( time() - $submitted_ts ) / HOUR_IN_SECONDS : 0;
	$window_start  = ecm_unmask_window_start_hour( $tier );

	if ( $hours_elapsed < $window_start ) {
		ecm_log_distribution_event( $request_id, $provider_id, 'unmask_denied', $tier );
		$opens_at = $submitted_ts ? ecm_unmask_window_opens_at( $submitted_ts, $tier ) : 0;
		return new WP_Error(
			'window_not_open',
			'Your plan can unmask this request starting at hour ' . $window_start . '.',
			[
				'status'        => 403,
				'window_start'  => $window_start,
				'opens_at'      => $opens_at,
				'opens_at_text' => $opens_at ? wp_date( 'M j, g:i a', $opens_at ) : '',
			]
		);
	}

	ecm_log_distribution_event( $request_id, $provider_id, 'unmask_granted', $tier );

	return rest_ensure_response( [
		'first_name'    => get_post_meta( $request_id, '_ecm_first_name', true ),
		'last_name'     => get_post_meta( $request_id, '_ecm_last_name', true ),
		'email'         => get_post_meta( $request_id, '_ecm_email', true ),
		'phone'         => get_post_meta( $request_id, '_ecm_phone', true ),
		'description'   => get_post_meta( $request_id, '_ecm_description', true ),
		'consent_record' => ecm_consent_record_for_request( $request_id ), // item 33
	] );
}

/**
 * The picked listing ID (for this request) that the current user owns, or
 * 0 if none of their listings were picked for it.
 */
function ecm_resolve_unmask_provider_id( int $request_id ) : int {
	$uid = get_current_user_id();
	if ( ! $uid ) {
		return 0;
	}
	$picked = (array) get_post_meta( $request_id, '_ecm_picked_provider_ids', true );
	$picked = array_map( 'intval', $picked );

	$my_listings = function_exists( 'pd_get_my_listing_ids' ) ? pd_get_my_listing_ids( $uid ) : [];
	foreach ( $my_listings as $listing_id ) {
		if ( in_array( (int) $listing_id, $picked, true ) ) {
			return (int) $listing_id;
		}
	}
	return 0;
}

/**
 * The expedited-matched listing ID (for this request) that the current
 * user owns, or 0 if none of their listings received it via expedited
 * matching [37-40]. Deliberately separate from ecm_resolve_unmask_provider_id()
 * — the caller needs to know WHICH list matched, since only this one is
 * subject to the MD/WA/NV restriction [36].
 */
function ecm_resolve_expedited_unmask_provider_id( int $request_id ) : int {
	$uid = get_current_user_id();
	if ( ! $uid ) {
		return 0;
	}
	$expedited = (array) get_post_meta( $request_id, '_ecm_expedited_provider_ids', true );
	$expedited = array_map( 'intval', $expedited );

	$my_listings = function_exists( 'pd_get_my_listing_ids' ) ? pd_get_my_listing_ids( $uid ) : [];
	foreach ( $my_listings as $listing_id ) {
		if ( in_array( (int) $listing_id, $expedited, true ) ) {
			return (int) $listing_id;
		}
	}
	return 0;
}

/** The consent record row for a request, as a plain array, or null. */
function ecm_consent_record_for_request( int $request_id ) : ?array {
	global $wpdb;
	if ( ! function_exists( 'ecm_consent_table' ) ) {
		return null;
	}
	$table = ecm_consent_table();
	$row   = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE request_id = %d", $request_id
	), ARRAY_A );
	return $row ?: null;
}

/**
 * The provider dashboard's "Lead Inbox" (items 43-50 / doc section 5) —
 * every request distributed to any listing the given user owns, newest
 * first, with that listing's current unmask status computed the same way
 * ecm_handle_unmask_request() does. Reads the distribution log for the
 * 'distributed' rows (one per picked provider per request, written at
 * submission by ecm_distribute_lead_to_providers()) rather than scanning
 * every ecm_lead's serialized _ecm_picked_provider_ids meta.
 */
function ecm_leads_for_provider( int $uid ) : array {
	global $wpdb;
	$listing_ids = function_exists( 'pd_get_my_listing_ids' ) ? pd_get_my_listing_ids( $uid ) : [];
	if ( ! $listing_ids ) {
		return [];
	}

	$table        = ecm_distribution_log_table();
	$placeholders = implode( ',', array_fill( 0, count( $listing_ids ), '%d' ) );
	$rows         = $wpdb->get_results( $wpdb->prepare(
		"SELECT request_id, provider_id, plan_tier, MIN(created_at) AS distributed_at
		 FROM {$table}
		 WHERE event_type = 'distributed' AND provider_id IN ({$placeholders})
		 GROUP BY request_id, provider_id
		 ORDER BY distributed_at DESC",
		$listing_ids
	), ARRAY_A ) ?: [];

	$leads = [];
	foreach ( $rows as $row ) {
		$request_id = (int) $row['request_id'];
		$lead       = get_post( $request_id );
		if ( ! $lead || 'ecm_lead' !== $lead->post_type ) {
			continue;
		}

		$provider_id   = (int) $row['provider_id'];
		$tier          = (string) ( $row['plan_tier'] ?: ( function_exists( 'ecm_get_listing_tier' ) ? ecm_get_listing_tier( $provider_id ) : 'free' ) );
		$submitted_at  = (string) get_post_meta( $request_id, '_ecm_submitted_at', true );
		$submitted_ts  = $submitted_at ? strtotime( $submitted_at . ' UTC' ) : (int) strtotime( $lead->post_date_gmt . ' UTC' );
		$window_start  = ecm_unmask_window_start_hour( $tier );
		$opens_at      = $submitted_ts ? ecm_unmask_window_opens_at( $submitted_ts, $tier ) : 0;
		$window_open   = $submitted_ts && time() >= $opens_at;

		$already_granted = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE request_id = %d AND provider_id = %d AND event_type = 'unmask_granted' LIMIT 1",
			$request_id, $provider_id
		) );

		$leads[] = [
			'request_id'    => $request_id,
			'provider_id'   => $provider_id,
			'tier'          => $tier,
			'payload'       => ecm_build_masked_payload( $lead ),
			'window_start'  => $window_start,
			'opens_at'      => $opens_at,
			'window_open'   => $window_open,
			'unmasked'      => $already_granted,
			'closed'        => (bool) get_post_meta( $request_id, '_ecm_closed', true ), // family stop-contact [5.3]
			'submitted_at'  => $submitted_at ?: $lead->post_date_gmt,
			// Already granted previously (a real past unmask_granted row) — safe to
			// show directly from postmeta without re-running the gate or writing
			// another log row; the entitlement was already earned and logged once.
			'contact'       => $already_granted ? [
				'first_name'  => get_post_meta( $request_id, '_ecm_first_name', true ),
				'last_name'   => get_post_meta( $request_id, '_ecm_last_name', true ),
				'email'       => get_post_meta( $request_id, '_ecm_email', true ),
				'phone'       => get_post_meta( $request_id, '_ecm_phone', true ),
				'description' => get_post_meta( $request_id, '_ecm_description', true ),
			] : null,
		];
	}
	return $leads;
}
