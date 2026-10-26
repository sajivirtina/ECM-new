<?php
/**
 * Consent record — Privacy Compliance: Developer Requirements, items 8-13.
 *
 * One write-once, never-updated row per request submission. Stores the
 * EXACT assent wording shown at submission time (not a pointer to it — a
 * later copy change must never alter what an earlier family is shown to
 * have agreed to) plus the legal-document versions live at that moment.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_CONSENT_DB_VERSION = '1.0';
const ECM_CONSENT_RETENTION_YEARS = 4;

function ecm_consent_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ecm_consent_records';
}

function ecm_consent_maybe_create_table() : void {
	if ( get_option( 'ecm_consent_db_version' ) === ECM_CONSENT_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table           = ecm_consent_table();
	$charset_collate = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		request_id bigint(20) unsigned NOT NULL,
		consent_text longtext NOT NULL,
		submitted_at datetime NOT NULL,
		ip_address varchar(45) NOT NULL,
		picked_provider_ids text DEFAULT NULL,
		channels varchar(50) NOT NULL,
		expedited_opt_in tinyint(1) NOT NULL DEFAULT 0,
		care_state varchar(2) DEFAULT NULL,
		submitter_state varchar(2) DEFAULT NULL,
		terms_version varchar(20) DEFAULT NULL,
		privacy_version varchar(20) DEFAULT NULL,
		terms_accepted_at datetime NOT NULL,
		terms_text_hash char(64) NOT NULL,
		subject_deleted tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY request_id (request_id)
	) ENGINE=InnoDB {$charset_collate};" );
	update_option( 'ecm_consent_db_version', ECM_CONSENT_DB_VERSION );
}
add_action( 'init', 'ecm_consent_maybe_create_table' );

/**
 * The exact assent sentence — same string used on-screen (item 3/4) and
 * hashed into consent_text_hash (item 10). Plain text only: callers that
 * need the linked-titles HTML build it separately around this string.
 */
function ecm_assent_sentence_text() : string {
	return 'By clicking Submit you agree to the Terms of Service and the Privacy Policy.';
}

/**
 * The assent sentence as HTML, with both document titles linked — for
 * display immediately above a submit button. Never wrapped in a checkbox
 * or other input (item 5).
 */
function ecm_assent_sentence_html() : string {
	$terms_url   = get_permalink( 34 );
	$privacy_url = get_permalink( 9141 );
	return sprintf(
		'By clicking Submit you agree to the <a href="%s" target="_blank" rel="noopener noreferrer">Terms of Service</a> and the <a href="%s" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.',
		esc_url( $terms_url ),
		esc_url( $privacy_url )
	);
}

/**
 * Restricts a requested channel list to the only two allowed values.
 * Anything else (e.g. "sms") is silently dropped, never stored.
 */
function ecm_sanitize_consent_channels( array $requested ) : array {
	return array_values( array_intersect( $requested, [ 'email', 'phone' ] ) );
}

/**
 * Creates the write-once consent record for a request submission.
 *
 * @param int    $request_id        The ecm_lead post this consent belongs to.
 * @param array  $picked_ids        Picked provider post IDs.
 * @param bool   $expedited_opt_in  Whether expedited matching was opted into.
 * @param string $care_state        2-letter state computed from the care location.
 * @param string $submitter_state   2-letter state from IP geolocation.
 * @param array  $channels          Requested contact channels (filtered to email/phone).
 * @return int Inserted row ID, or 0 on failure.
 */
function ecm_create_consent_record(
	int $request_id,
	array $picked_ids,
	bool $expedited_opt_in,
	string $care_state,
	string $submitter_state,
	array $channels
) : int {
	global $wpdb;

	$now        = current_time( 'mysql' );
	$consent    = ecm_assent_sentence_text();
	$terms_ver  = ecm_legal_latest_version( 'terms' );
	$privacy_ver = ecm_legal_latest_version( 'privacy' );

	$wpdb->insert( ecm_consent_table(), [
		'request_id'          => $request_id,
		'consent_text'        => $consent,
		'submitted_at'        => $now,
		'ip_address'          => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
		'picked_provider_ids' => wp_json_encode( array_values( $picked_ids ) ),
		'channels'            => implode( ',', ecm_sanitize_consent_channels( $channels ) ),
		'expedited_opt_in'    => $expedited_opt_in ? 1 : 0,
		'care_state'          => $care_state ?: null,
		'submitter_state'     => $submitter_state ?: null,
		'terms_version'       => $terms_ver ?: null,
		'privacy_version'     => $privacy_ver ?: null,
		'terms_accepted_at'   => $now,
		'terms_text_hash'     => hash( 'sha256', $consent ),
	] );

	return (int) $wpdb->insert_id;
}

/**
 * Marks a consent record subject_deleted instead of removing it (item 13)
 * — the record itself must survive a family's deletion request.
 */
function ecm_mark_consent_subject_deleted( int $request_id ) : void {
	global $wpdb;
	$wpdb->update(
		ecm_consent_table(),
		[ 'subject_deleted' => 1 ],
		[ 'request_id' => $request_id ]
	);
}
