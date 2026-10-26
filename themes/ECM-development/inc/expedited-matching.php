<?php
/**
 * Expedited matching + state restriction — Privacy Compliance: Developer
 * Requirements, items 34-40 (doc 2.1, 4.5, 4.6, 8.3; Amendment 1 §1 pulls
 * this into version one, not version two).
 *
 * 34-35: care_state (from the city/ZIP the family already enters via the
 * sitewide location picker) and submitter_state (from IP geolocation — see
 * ecm_lookup_submitter_state_from_ip() below, currently a stub pending a
 * vendor decision) are computed and stored separately in intake-api.php.
 * No separate state field was added to the form, per item 35.
 *
 * 36: the MD/WA/NV "masked-only" restriction on UNMASKING itself lives in
 * inc/unmask-api.php, since that's where the actual gate has to run.
 *
 * 37-39: this file is the trigger — a provider who was NOT picked by the
 * family can still receive the masked payload if the family's picks go
 * quiet, but can only ever unmask it outside MD/WA/NV (doc 4.5/4.6; the
 * Consumer Health Data Notice §4 confirms distribution still happens in
 * those states, only unmasking is blocked).
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_EXPEDITED_DEFAULT_HOURS = 72;
const ECM_EXPEDITED_MAX_PROVIDERS = 10; // Not specified in the spec; capped to the same number a family can pick themselves [10.2], and filterable.

/** Hours after submission an un-responded request becomes eligible for expedited matching — admin-configurable, default 72 [38]. */
function ecm_expedited_matching_hours() : int {
	$hours = (int) get_option( 'ecm_expedited_matching_hours', ECM_EXPEDITED_DEFAULT_HOURS );
	return $hours > 0 ? $hours : ECM_EXPEDITED_DEFAULT_HOURS;
}

/**
 * Submitter state from IP geolocation [8.3] — STUBBED. Returns '' until a
 * lookup method is chosen (a live third-party API, which would need to be
 * added to the Consumer Health Data Notice's vendor list, vs. a local
 * MaxMind GeoLite2-City database, which needs a free account + license key
 * this assistant can't create). Until this returns real data, MD/WA/NV
 * detection runs on care_state alone — still correct, just not using both
 * signals doc 8.3 asks for.
 */
function ecm_lookup_submitter_state_from_ip( string $ip ) : string {
	return '';
}

/** True when either stored state on a lead is MD, WA or NV [4.5, 8.3]. */
function ecm_lead_is_masked_only_state( int $lead_id ) : bool {
	$care_state      = strtoupper( (string) get_post_meta( $lead_id, '_ecm_care_state', true ) );
	$submitter_state = strtoupper( (string) get_post_meta( $lead_id, '_ecm_submitter_state', true ) );
	return in_array( $care_state, [ 'MD', 'WA', 'NV' ], true ) || in_array( $submitter_state, [ 'MD', 'WA', 'NV' ], true );
}

// ── Settings — Settings → General, one field [38] ───────────────────────────

add_action( 'admin_init', function () {
	register_setting( 'general', 'ecm_expedited_matching_hours', [
		'type'              => 'integer',
		'sanitize_callback' => function ( $value ) {
			$value = absint( $value );
			return $value > 0 ? $value : ECM_EXPEDITED_DEFAULT_HOURS;
		},
		'default'           => ECM_EXPEDITED_DEFAULT_HOURS,
	] );
	add_settings_field(
		'ecm_expedited_matching_hours',
		'Expedited matching window (hours)',
		function () {
			$value = ecm_expedited_matching_hours();
			echo '<input type="number" min="1" name="ecm_expedited_matching_hours" value="' . esc_attr( $value ) . '" class="small-text"> ';
			echo '<p class="description">If no picked provider has unmasked a request within this many hours, it becomes eligible for expedited matching [4.6]. Default 72.</p>';
		},
		'general'
	);
} );

// ── Cron: find due requests, trigger expedited matching ─────────────────────

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ecm_expedited_matching_check' ) ) {
		wp_schedule_event( time(), 'hourly', 'ecm_expedited_matching_check' );
	}
} );

add_action( 'ecm_expedited_matching_check', 'ecm_run_expedited_matching_check' );

/**
 * Scans opted-in, not-yet-triggered requests and fires expedited matching
 * for any that are past the window with no picked-provider response [37].
 */
function ecm_run_expedited_matching_check() : void {
	$candidates = get_posts( [
		'post_type'      => 'ecm_lead',
		'post_status'    => 'any',
		'numberposts'    => 100,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- hourly cron, not a request-path query.
			[ 'key' => '_ecm_expedited_opt_in', 'value' => '1' ],
			[ 'key' => '_ecm_expedited_triggered', 'compare' => 'NOT EXISTS' ],
		],
	] );

	foreach ( $candidates as $lead_id ) {
		ecm_maybe_trigger_expedited_matching( (int) $lead_id );
	}
}

/**
 * Evaluates and, if due, runs expedited matching for one request. Public
 * (not just cron-only) so it can be triggered manually/tested directly.
 *
 * @param bool $force Skip the time-elapsed check (admin test tool only —
 *                     the idempotency guard above still applies, so this
 *                     never double-triggers a request that's already been
 *                     handled).
 */
function ecm_maybe_trigger_expedited_matching( int $lead_id, bool $force = false ) : void {
	if ( get_post_meta( $lead_id, '_ecm_expedited_triggered', true ) ) {
		return; // Already handled — idempotent.
	}

	$lead = get_post( $lead_id );
	if ( ! $lead || 'ecm_lead' !== $lead->post_type ) {
		return;
	}

	$submitted_at = (string) get_post_meta( $lead_id, '_ecm_submitted_at', true );
	$submitted_ts = $submitted_at ? strtotime( $submitted_at . ' UTC' ) : (int) strtotime( $lead->post_date_gmt . ' UTC' );
	if ( ! $submitted_ts ) {
		return;
	}
	$hours_elapsed = ( time() - $submitted_ts ) / HOUR_IN_SECONDS;
	if ( ! $force && $hours_elapsed < ecm_expedited_matching_hours() ) {
		return; // Not due yet — leave untriggered so the next cron run re-checks.
	}

	// "No picked provider has unmasked within [window] of submitted_at" [4.6].
	$picked_ids = array_map( 'intval', (array) get_post_meta( $lead_id, '_ecm_picked_provider_ids', true ) );
	if ( $picked_ids && function_exists( 'ecm_distribution_log_for_request' ) ) {
		foreach ( ecm_distribution_log_for_request( $lead_id ) as $event ) {
			if ( 'unmask_granted' === $event['event_type'] && in_array( (int) $event['provider_id'], $picked_ids, true ) ) {
				update_post_meta( $lead_id, '_ecm_expedited_triggered', 1 );
				update_post_meta( $lead_id, '_ecm_expedited_triggered_at', current_time( 'mysql' ) );
				update_post_meta( $lead_id, '_ecm_expedited_triggered_reason', 'picked_provider_responded' );
				return; // A pick already came through — no expedited matching needed.
			}
		}
	}

	ecm_trigger_expedited_matching( $lead_id, $picked_ids );
}

/**
 * Finds additional verified providers serving the care location, ordered
 * Featured, Premium, Basic, Free, and sends them the same masked payload
 * every picked provider got [39] — distribution only; unmasking is gated
 * separately in inc/unmask-api.php, subject to 4.5 [40].
 */
function ecm_trigger_expedited_matching( int $lead_id, array $exclude_ids ) : void {
	$lead = get_post( $lead_id );
	if ( ! $lead ) {
		return;
	}

	$care_type = (string) get_post_meta( $lead_id, '_ecm_care_type', true );
	$city      = (string) get_post_meta( $lead_id, '_ecm_city', true );

	$args = function_exists( 'ecm_category_filter_args' ) ? ecm_category_filter_args( $care_type, $city, '' ) : [];
	$args['post_type']      = 'job_listing';
	$args['post_status']    = 'publish';
	$args['posts_per_page'] = -1;
	$args['no_found_rows']  = true;
	$args['fields']         = 'ids';

	// NOT post__not_in — confirmed live that WP_Query silently drops
	// post__not_in whenever post__in is also set (ecm_category_filter_args()
	// sets post__in for the city match), with no error or notice. Excluding
	// the family's own picks in PHP instead, after the query, is the
	// reliable way to do this here.
	$candidate_ids = get_posts( $args );
	if ( $exclude_ids ) {
		$candidate_ids = array_values( array_diff( $candidate_ids, $exclude_ids ) );
	}

	// Order Featured, Premium, Basic, Free [39].
	$tier_rank = [ 'premium' => 0, 'standard' => 1, 'basic' => 2, 'free' => 3 ];
	usort( $candidate_ids, function ( $a, $b ) use ( $tier_rank ) {
		$ta = $tier_rank[ ecm_get_listing_display_tier( (int) $a ) ] ?? 3;
		$tb = $tier_rank[ ecm_get_listing_display_tier( (int) $b ) ] ?? 3;
		return $ta <=> $tb;
	} );

	$max       = (int) apply_filters( 'ecm_expedited_matching_max_providers', ECM_EXPEDITED_MAX_PROVIDERS );
	$selected  = array_slice( $candidate_ids, 0, max( 0, $max ) );

	if ( $selected && function_exists( 'ecm_build_masked_payload' ) ) {
		$payload = ecm_build_masked_payload( $lead );
		foreach ( $selected as $provider_id ) {
			$provider_id = (int) $provider_id;
			$tier        = ecm_get_listing_display_tier( $provider_id );

			if ( function_exists( 'ecm_log_distribution_event' ) ) {
				ecm_log_distribution_event( $lead_id, $provider_id, 'distributed', $tier );
			}

			$to = function_exists( 'ecm_provider_contact_email' ) ? ecm_provider_contact_email( $provider_id ) : '';
			if ( $to && is_email( $to ) ) {
				$body  = "A family's request didn't hear back from their original picks, so it's being shared more widely:\n\n";
				$body .= 'Category: ' . ( $payload['category'] ?: '—' ) . "\n";
				$body .= 'Location: ' . ( $payload['location'] ?: '—' ) . "\n";
				$body .= 'Timing: '   . ( $payload['timing'] ?: '—' ) . "\n";
				if ( $payload['care_summary'] ) {
					$body .= 'Notes: ' . $payload['care_summary'] . "\n";
				}
				$body .= "\nContact details are hidden until your plan's window opens. Log in to your provider dashboard to respond.\n";
				$body .= get_bloginfo( 'url' ) . "\n";
				wp_mail( $to, 'A family request near you — ElderCareMatters', $body );
			}
		}
	}

	update_post_meta( $lead_id, '_ecm_expedited_provider_ids', $selected );
	update_post_meta( $lead_id, '_ecm_expedited_triggered', 1 );
	update_post_meta( $lead_id, '_ecm_expedited_triggered_at', current_time( 'mysql' ) );
	update_post_meta( $lead_id, '_ecm_expedited_triggered_reason', 'window_elapsed' );
}

add_action( 'switch_theme', function () {
	wp_clear_scheduled_hook( 'ecm_expedited_matching_check' );
} );
