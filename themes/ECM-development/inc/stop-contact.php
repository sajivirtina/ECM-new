<?php
/**
 * Stop contact — Privacy Compliance: Developer Requirements, items 43-45
 * (doc 6.1, 5.3, text in 10.4).
 *
 * Every email ElderCareMatters sends a family carries a one-click,
 * no-login link (ecm_stop_contact_url()) tied to a random per-lead token.
 * Visiting it suppresses further ECM email about that one request, marks
 * it closed, tells every provider that unmasked it to stop contacting the
 * family, and writes a permanent suppression record — scoped to that
 * single request_id only (test case 9.9: "that sender blocked, others
 * unaffected").
 *
 * NOT built here: 6.2 (Help@ family-level stop across ALL of a family's
 * requests) and 6.3/6.4 (deletion, the Rights@ queue) — separate pieces.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_SUPPRESSION_LOG_DB_VERSION = '1.0';
const ECM_STOP_CONTACT_REWRITE_VERSION = '1';

function ecm_suppression_log_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ecm_suppression_log';
}

function ecm_suppression_log_maybe_create_table() : void {
	if ( get_option( 'ecm_suppression_log_db_version' ) === ECM_SUPPRESSION_LOG_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table           = ecm_suppression_log_table();
	$charset_collate = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(255) NOT NULL,
		request_id bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY email (email),
		KEY request_id (request_id)
	) ENGINE=InnoDB {$charset_collate};" );
	update_option( 'ecm_suppression_log_db_version', ECM_SUPPRESSION_LOG_DB_VERSION );
}
add_action( 'init', 'ecm_suppression_log_maybe_create_table' );

// ── Token + link ─────────────────────────────────────────────────────────────

/** The lead's stop-contact token, generating and storing one on first use. */
function ecm_get_or_create_stop_token( int $lead_id ) : string {
	$token = (string) get_post_meta( $lead_id, '_ecm_stop_token', true );
	if ( $token ) {
		return $token;
	}
	$token = bin2hex( random_bytes( 20 ) );
	update_post_meta( $lead_id, '_ecm_stop_token', $token );
	return $token;
}

/** The one-click, no-login stop-contact URL for a lead — goes in every family email [6.1]. */
function ecm_stop_contact_url( int $lead_id ) : string {
	$token = ecm_get_or_create_stop_token( $lead_id );
	return home_url( '/stop-contact/' . $token . '/' );
}

/**
 * The exact 10.4 footer, appended to every ElderCareMatters email sent to a
 * family about a specific request. Plain text — the intake confirmation
 * email this currently applies to is plain text, not HTML.
 */
function ecm_email_family_footer( int $lead_id ) : string {
	$link = ecm_stop_contact_url( $lead_id );
	return "\n\nYou are getting this because you asked ElderCareMatters to connect you with providers. "
		. 'Stop all contact about this request: ' . $link . '. '
		. "Questions: Help@ElderCareMatters.com";
}

// ── Routing ───────────────────────────────────────────────────────────────────

add_action( 'init', function () {
	add_rewrite_rule( '^stop-contact/([^/]+)/?$', 'index.php?ecm_stop_token=$matches[1]', 'top' );
	if ( ECM_STOP_CONTACT_REWRITE_VERSION !== get_option( 'ecm_stop_contact_rewrite_version' ) ) {
		flush_rewrite_rules( false );
		update_option( 'ecm_stop_contact_rewrite_version', ECM_STOP_CONTACT_REWRITE_VERSION );
	}
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'ecm_stop_token';
	return $vars;
} );

add_action( 'template_redirect', function () {
	$token = sanitize_text_field( (string) get_query_var( 'ecm_stop_token' ) );
	if ( '' === $token ) {
		return;
	}
	nocache_headers();
	ecm_render_stop_contact_page( $token );
	exit;
} );

// ── The four actions [6.1] ──────────────────────────────────────────────────

/**
 * Runs the four stop-contact actions for one lead, idempotently (a repeat
 * click — or a link crawler — must not re-notify providers or write a
 * second suppression row).
 */
function ecm_process_stop_contact( WP_Post $lead ) : void {
	$lead_id = $lead->ID;

	if ( get_post_meta( $lead_id, '_ecm_contact_stopped', true ) ) {
		return; // Already processed — idempotent.
	}

	// 1. Suppress further ECM email about this request.
	update_post_meta( $lead_id, '_ecm_contact_stopped', 1 );

	// 2. Mark the request closed.
	update_post_meta( $lead_id, '_ecm_closed', 1 );
	update_post_meta( $lead_id, '_ecm_closed_at', current_time( 'mysql' ) );
	update_post_meta( $lead_id, '_ecm_closed_reason', 'family_stop_contact' );

	// 3. Notify every provider that unmasked the lead to stop contacting the family.
	if ( function_exists( 'ecm_distribution_log_for_request' ) ) {
		$events          = ecm_distribution_log_for_request( $lead_id );
		$notified_ids    = [];
		foreach ( $events as $event ) {
			if ( 'unmask_granted' !== $event['event_type'] ) {
				continue;
			}
			$provider_id = (int) $event['provider_id'];
			if ( in_array( $provider_id, $notified_ids, true ) ) {
				continue; // A provider can unmask once per lead, but guard anyway.
			}
			$notified_ids[] = $provider_id;

			$to = function_exists( 'ecm_provider_contact_email' ) ? ecm_provider_contact_email( $provider_id ) : '';
			if ( $to && is_email( $to ) ) {
				wp_mail(
					$to,
					'Please stop contact — ElderCareMatters request',
					"The family on this request has asked ElderCareMatters to stop all contact. Please do not contact them further about this inquiry.\n\nIf you believe this was sent in error, email Help@ElderCareMatters.com."
				);
			}
		}
	}

	// 4. Write a suppression record keyed to the email address and the request_id.
	global $wpdb;
	$email = (string) get_post_meta( $lead_id, '_ecm_email', true );
	$wpdb->insert( ecm_suppression_log_table(), [
		'email'      => $email,
		'request_id' => $lead_id,
	] );

	// Confirmation email — the one exception to "no further email", since it
	// IS the acknowledgment of the stop itself (exact text, 10.4).
	if ( $email && is_email( $email ) ) {
		wp_mail(
			$email,
			'We have stopped contact on your request',
			"We have told the providers on your request to stop contacting you, and we will not email you about it again. If a provider reaches out anyway, email Help@ElderCareMatters.com and we will follow up with them directly."
		);
	}
}

/** Renders the stop-contact landing page — exact 10.4 confirmation text, or a quiet 404 for an unknown token. */
function ecm_render_stop_contact_page( string $token ) : void {
	$leads = get_posts( [
		'post_type'      => 'ecm_lead',
		'post_status'    => 'any',
		'numberposts'    => 1,
		'meta_key'       => '_ecm_stop_token',
		'meta_value'     => $token,
		'fields'         => 'ids',
	] );

	if ( ! $leads ) {
		status_header( 404 );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not found</title></head><body style="font-family:sans-serif;max-width:480px;margin:80px auto;text-align:center;">'
			. '<p>This link is not valid.</p></body></html>';
		return;
	}

	$lead = get_post( $leads[0] );
	ecm_process_stop_contact( $lead );

	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
		. '<title>ElderCareMatters</title></head>'
		. '<body style="font-family:-apple-system,Arial,sans-serif;max-width:480px;margin:80px auto;text-align:center;color:#111827;padding:0 20px;">'
		. '<p style="font-size:1.1rem;">Done. We have told every provider on your request to stop contacting you.</p>'
		. '<p><a href="' . esc_url( home_url( '/' ) ) . '" style="color:#2d6a4f;">Return to ElderCareMatters.com</a></p>'
		. '</body></html>';
}
