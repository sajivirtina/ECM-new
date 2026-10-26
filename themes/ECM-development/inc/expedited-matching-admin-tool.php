<?php
/**
 * Admin test tool for items 34-40 (state restriction + expedited matching) —
 * NOT part of the feature itself. Lets an admin inspect a lead's stored
 * state, force-trigger expedited matching on one specific lead without
 * waiting for the real window to elapse, and simulate the unmask gate's
 * outcome for a given lead/listing pair. Admin-only (manage_options).
 *
 * Scoped to one lead ID at a time, entered by hand — deliberately does NOT
 * expose a "run against every eligible lead" button, since real leads on
 * this install have real picked providers with real email addresses; a
 * sitewide force-run here could send unwanted live emails.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page(
		'Expedited Matching Test',
		'Expedited Matching Test',
		'manage_options',
		'ecm-expedited-test',
		'ecm_render_expedited_test_page'
	);
} );

function ecm_render_expedited_test_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'eldercare-matters' ) );
	}

	$lead_id = absint( $_GET['lead_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, just selects which lead to inspect.
	$notice  = isset( $_GET['ecm_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ecm_notice'] ) ) : '';

	echo '<div class="wrap"><h1>Expedited Matching Test Tool</h1>';
	echo '<p>Admin-only diagnostic for checklist items 34-40. Enter a real <code>ecm_lead</code> post ID (the number in its edit-screen URL, or the "Lead ID" column on Care Leads).</p>';

	if ( $notice ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
	}

	// ── Lead ID selector ──────────────────────────────────────────────────
	echo '<form method="get" style="margin-bottom:20px;">';
	echo '<input type="hidden" name="page" value="ecm-expedited-test">';
	echo '<label><strong>Lead ID:</strong> <input type="number" name="lead_id" value="' . esc_attr( $lead_id ) . '"></label> ';
	echo '<button type="submit" class="button">Inspect</button>';
	echo '</form>';

	if ( ! $lead_id ) {
		echo '</div>';
		return;
	}

	$lead = get_post( $lead_id );
	if ( ! $lead || 'ecm_lead' !== $lead->post_type ) {
		echo '<div class="notice notice-error"><p>No ecm_lead found with that ID.</p></div></div>';
		return;
	}

	$care_state      = get_post_meta( $lead_id, '_ecm_care_state', true ) ?: '(empty)';
	$submitter_state = get_post_meta( $lead_id, '_ecm_submitter_state', true ) ?: '(empty)';
	$masked_only     = function_exists( 'ecm_lead_is_masked_only_state' ) && ecm_lead_is_masked_only_state( $lead_id );
	$picked_ids      = array_map( 'intval', (array) get_post_meta( $lead_id, '_ecm_picked_provider_ids', true ) );
	$expedited_ids   = array_map( 'intval', (array) get_post_meta( $lead_id, '_ecm_expedited_provider_ids', true ) );
	$triggered       = get_post_meta( $lead_id, '_ecm_expedited_triggered', true );
	$triggered_at    = get_post_meta( $lead_id, '_ecm_expedited_triggered_at', true );
	$triggered_why   = get_post_meta( $lead_id, '_ecm_expedited_triggered_reason', true );
	$submitted_at    = get_post_meta( $lead_id, '_ecm_submitted_at', true );
	$hours_elapsed   = $submitted_at ? round( ( time() - strtotime( $submitted_at . ' UTC' ) ) / HOUR_IN_SECONDS, 1 ) : null;

	echo '<h2>Lead ' . esc_html( $lead_id ) . ' — ' . esc_html( get_the_title( $lead_id ) ) . '</h2>';
	echo '<table class="widefat striped" style="max-width:700px;"><tbody>';
	echo '<tr><th>care_state</th><td>' . esc_html( $care_state ) . '</td></tr>';
	echo '<tr><th>submitter_state</th><td>' . esc_html( $submitter_state ) . '</td></tr>';
	echo '<tr><th>Masked-only state (MD/WA/NV)?</th><td>' . ( $masked_only ? '<strong style="color:#b91c1c;">YES</strong> — expedited providers can never unmask this one' : 'No' ) . '</td></tr>';
	echo '<tr><th>Submitted</th><td>' . esc_html( $submitted_at ?: '—' ) . ( null !== $hours_elapsed ? ' (' . $hours_elapsed . 'h ago)' : '' ) . '</td></tr>';
	echo '<tr><th>Expedited opt-in</th><td>' . ( get_post_meta( $lead_id, '_ecm_expedited_opt_in', true ) ? 'yes' : 'no' ) . '</td></tr>';
	echo '<tr><th>Picked provider IDs</th><td>' . ( $picked_ids ? esc_html( implode( ', ', $picked_ids ) ) : '(none)' ) . '</td></tr>';
	echo '<tr><th>Expedited provider IDs</th><td>' . ( $expedited_ids ? esc_html( implode( ', ', $expedited_ids ) ) : '(none yet)' ) . '</td></tr>';
	echo '<tr><th>Expedited matching already triggered?</th><td>' . ( $triggered ? 'yes, at ' . esc_html( $triggered_at ) . ' (reason: ' . esc_html( $triggered_why ) . ')' : 'no' ) . '</td></tr>';
	echo '</tbody></table>';

	$redirect = add_query_arg( [ 'page' => 'ecm-expedited-test', 'lead_id' => $lead_id ], admin_url( 'tools.php' ) );

	// ── Force-trigger ────────────────────────────────────────────────────
	echo '<h3>Force-trigger expedited matching</h3>';
	if ( $triggered ) {
		echo '<p class="description">Already triggered — won\'t re-run (idempotent). Use "Reset" below to test again.</p>';
		echo '<p><a href="' . esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'ecm_test_reset_expedited', 'lead_id' => $lead_id, '_redirect' => rawurlencode( $redirect ) ], admin_url( 'admin-post.php' ) ), 'ecm_test_action_' . $lead_id ) ) . '" class="button">Reset trigger flag (test only — does not undo emails already sent)</a></p>';
	} else {
		echo '<p>Runs the real distribution logic now, ignoring the ' . esc_html( ecm_expedited_matching_hours() ) . 'h window — selects providers and writes real distribution-log rows either way. <strong>Dry run</strong> suppresses the actual email send (nothing goes out); the plain version sends real emails to real matching providers.</p>';
		$dry_link  = wp_nonce_url( add_query_arg( [ 'action' => 'ecm_test_force_trigger', 'lead_id' => $lead_id, 'dry_run' => '1', '_redirect' => rawurlencode( $redirect ) ], admin_url( 'admin-post.php' ) ), 'ecm_test_action_' . $lead_id );
		$real_link = wp_nonce_url( add_query_arg( [ 'action' => 'ecm_test_force_trigger', 'lead_id' => $lead_id, '_redirect' => rawurlencode( $redirect ) ], admin_url( 'admin-post.php' ) ), 'ecm_test_action_' . $lead_id );
		echo '<p>';
		echo '<a href="' . esc_url( $dry_link ) . '" class="button button-primary">Force-Trigger Now (dry run — no emails sent)</a> ';
		echo '<a href="' . esc_url( $real_link ) . '" class="button" onclick="return confirm(\'This sends real emails to real providers matching this lead. Continue?\');">Force-Trigger Now (send real emails)</a>';
		echo '</p>';
	}

	// ── Unmask simulation ────────────────────────────────────────────────
	echo '<h3>Simulate the unmask gate</h3>';
	echo '<p>Enter a job_listing ID to see what the unmask endpoint would decide for it right now, without needing to log in as that provider.</p>';
	echo '<form method="get" style="margin-bottom:10px;">';
	echo '<input type="hidden" name="page" value="ecm-expedited-test">';
	echo '<input type="hidden" name="lead_id" value="' . esc_attr( $lead_id ) . '">';
	echo '<label>Listing ID: <input type="number" name="listing_id" value="' . esc_attr( absint( $_GET['listing_id'] ?? 0 ) ) . '"></label> '; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only simulation input.
	echo '<button type="submit" class="button">Simulate</button>';
	echo '</form>';

	$listing_id = absint( $_GET['listing_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $listing_id ) {
		echo '<div class="notice notice-info" style="padding:10px;"><p><strong>Result:</strong> ' . esc_html( ecm_simulate_unmask_outcome( $lead_id, $listing_id ) ) . '</p></div>';
	}

	echo '</div>';
}

/**
 * Same decision tree as ecm_handle_unmask_request(), but checked directly
 * against a given listing ID instead of the currently logged-in user's
 * owned listings — so an admin can test any provider without impersonating
 * them. Read-only: does not write to the distribution log.
 */
function ecm_simulate_unmask_outcome( int $lead_id, int $listing_id ) : string {
	$picked    = array_map( 'intval', (array) get_post_meta( $lead_id, '_ecm_picked_provider_ids', true ) );
	$expedited = array_map( 'intval', (array) get_post_meta( $lead_id, '_ecm_expedited_provider_ids', true ) );

	$via = null;
	if ( in_array( $listing_id, $picked, true ) ) {
		$via = 'picked';
	} elseif ( in_array( $listing_id, $expedited, true ) ) {
		$via = 'expedited';
	}

	if ( ! $via ) {
		return '403 not_picked — this listing is neither in the picked list nor the expedited list for this lead.';
	}

	if ( 'expedited' === $via && function_exists( 'ecm_lead_is_masked_only_state' ) && ecm_lead_is_masked_only_state( $lead_id ) ) {
		return "403 state_restricted — this listing reached the lead via EXPEDITED matching, and the lead's state is MD/WA/NV, so it can never unmask.";
	}

	$tier         = function_exists( 'ecm_get_listing_tier' ) ? ecm_get_listing_tier( $listing_id ) : 'free';
	$submitted_at = (string) get_post_meta( $lead_id, '_ecm_submitted_at', true );
	$submitted_ts = $submitted_at ? strtotime( $submitted_at . ' UTC' ) : 0;
	$window_start = function_exists( 'ecm_unmask_window_start_hour' ) ? ecm_unmask_window_start_hour( $tier ) : 72;
	$hours        = $submitted_ts ? ( time() - $submitted_ts ) / HOUR_IN_SECONDS : 0;

	$via_label = 'picked' === $via ? 'picked (never state-restricted)' : 'expedited (state check passed — not MD/WA/NV)';

	if ( $hours < $window_start ) {
		return sprintf(
			'403 window_not_open — via %s, tier "%s", window opens at hour %d, only %.1fh elapsed.',
			$via_label, $tier, $window_start, $hours
		);
	}

	return sprintf(
		'200 OK — via %s, tier "%s", window opened at hour %d (%.1fh elapsed). Would return real contact details.',
		$via_label, $tier, $window_start, $hours
	);
}

// ── Action handlers ──────────────────────────────────────────────────────

add_action( 'admin_post_ecm_test_force_trigger', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'eldercare-matters' ), '', [ 'response' => 403 ] );
	}
	$lead_id = absint( $_REQUEST['lead_id'] ?? 0 );
	check_admin_referer( 'ecm_test_action_' . $lead_id );

	$dry_run = ! empty( $_REQUEST['dry_run'] );
	if ( $dry_run ) {
		// Suppresses the actual send only — distribution-log rows and
		// _ecm_expedited_provider_ids are still written for real, so the
		// inspection table below reflects the real outcome either way.
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	ecm_maybe_trigger_expedited_matching( $lead_id, true );

	$redirect = esc_url_raw( wp_unslash( $_REQUEST['_redirect'] ?? admin_url() ) );
	$notice   = $dry_run
		? 'Force-trigger ran (dry run — no emails were sent). Check the table below for results.'
		: 'Force-trigger ran and real emails were sent to the providers listed below.';
	wp_safe_redirect( add_query_arg( 'ecm_notice', rawurlencode( $notice ), $redirect ) );
	exit;
} );

add_action( 'admin_post_ecm_test_reset_expedited', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'eldercare-matters' ), '', [ 'response' => 403 ] );
	}
	$lead_id = absint( $_REQUEST['lead_id'] ?? 0 );
	check_admin_referer( 'ecm_test_action_' . $lead_id );

	delete_post_meta( $lead_id, '_ecm_expedited_triggered' );
	delete_post_meta( $lead_id, '_ecm_expedited_triggered_at' );
	delete_post_meta( $lead_id, '_ecm_expedited_triggered_reason' );
	delete_post_meta( $lead_id, '_ecm_expedited_provider_ids' );

	$redirect = esc_url_raw( wp_unslash( $_REQUEST['_redirect'] ?? admin_url() ) );
	wp_safe_redirect( add_query_arg( 'ecm_notice', rawurlencode( 'Trigger flag reset — you can Force-Trigger again now.' ), $redirect ) );
	exit;
} );
