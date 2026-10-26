<?php
/**
 * Distribution log + immediate masked-lead distribution — Privacy
 * Compliance: Developer Requirements, items 41-42 and 62.
 *
 * Every distribution and unmask attempt gets a permanent log row. This
 * file covers the "distributed" side (sending the masked payload to every
 * picked provider the moment a request is submitted); "unmask_granted" /
 * "unmask_denied" rows belong to the unmask-window endpoint (items 30-33),
 * not built yet — this table is shaped to take them too.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_DISTRIBUTION_LOG_DB_VERSION = '1.0';

function ecm_distribution_log_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ecm_distribution_log';
}

function ecm_distribution_log_maybe_create_table() : void {
	if ( get_option( 'ecm_distribution_log_db_version' ) === ECM_DISTRIBUTION_LOG_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table           = ecm_distribution_log_table();
	$charset_collate = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		request_id bigint(20) unsigned NOT NULL,
		provider_id bigint(20) unsigned NOT NULL,
		event_type varchar(20) NOT NULL,
		plan_tier varchar(20) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY request_id (request_id),
		KEY provider_id (provider_id)
	) ENGINE=InnoDB {$charset_collate};" );
	update_option( 'ecm_distribution_log_db_version', ECM_DISTRIBUTION_LOG_DB_VERSION );
}
add_action( 'init', 'ecm_distribution_log_maybe_create_table' );

/** Writes one log row. $event_type: 'distributed' | 'unmask_granted' | 'unmask_denied'. */
function ecm_log_distribution_event( int $request_id, int $provider_id, string $event_type, string $plan_tier = '' ) : void {
	global $wpdb;
	$wpdb->insert( ecm_distribution_log_table(), [
		'request_id'  => $request_id,
		'provider_id' => $provider_id,
		'event_type'  => $event_type,
		'plan_tier'   => $plan_tier ?: null,
	] );
}

/** Every logged event for a request, newest first — item 42's "query by request_id". */
function ecm_distribution_log_for_request( int $request_id ) : array {
	global $wpdb;
	$table = ecm_distribution_log_table();
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE request_id = %d ORDER BY id DESC",
		$request_id
	), ARRAY_A ) ?: [];
}

/** A provider listing's contact email — WP Job Manager's own "apply" field, falling back to the listing author's account email. */
function ecm_provider_contact_email( int $provider_id ) : string {
	$email = (string) get_post_meta( $provider_id, '_application', true );
	if ( $email && is_email( $email ) ) {
		return $email;
	}
	$author_id = (int) get_post_field( 'post_author', $provider_id );
	return $author_id ? (string) get_the_author_meta( 'user_email', $author_id ) : '';
}

/**
 * Sends the masked payload to every picked provider and logs a
 * 'distributed' row for each — immediately on submission (item 62).
 *
 * @param int   $request_id  The ecm_lead post ID.
 * @param array $provider_ids Picked provider post IDs.
 */
function ecm_distribute_lead_to_providers( int $request_id, array $provider_ids ) : void {
	if ( ! $provider_ids ) {
		return;
	}
	$lead = get_post( $request_id );
	if ( ! $lead ) {
		return;
	}

	$payload = ecm_build_masked_payload( $lead );

	foreach ( $provider_ids as $provider_id ) {
		$provider_id = (int) $provider_id;
		$tier        = function_exists( 'ecm_get_listing_tier' ) ? ecm_get_listing_tier( $provider_id ) : '';

		ecm_log_distribution_event( $request_id, $provider_id, 'distributed', $tier );

		$to = ecm_provider_contact_email( $provider_id );
		if ( ! $to || ! is_email( $to ) ) {
			continue;
		}

		$subject = 'New family request on ElderCareMatters';
		$body  = "You have a new family request:\n\n";
		$body .= 'Category: ' . ( $payload['category'] ?: '—' ) . "\n";
		$body .= 'Location: ' . ( $payload['location'] ?: '—' ) . "\n";
		$body .= 'Timing: '   . ( $payload['timing'] ?: '—' ) . "\n";
		if ( $payload['care_summary'] ) {
			$body .= 'Notes: ' . $payload['care_summary'] . "\n";
		}
		$body .= "\nContact details are hidden until your plan's window opens. Log in to your provider dashboard to respond.\n";
		$body .= get_bloginfo( 'url' ) . "\n";

		wp_mail( $to, $subject, $body );
	}
}
