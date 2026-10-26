<?php
/**
 * Legal document versioning — Privacy Compliance: Developer Requirements,
 * items 6-7.
 *
 * Every time the Terms of Service or Privacy Policy page is saved, this
 * archives the published content as a new, immutable version row keyed by
 * date (e.g. "2026-10-01"). Old versions are never edited or deleted — a
 * consent record references the version live at the moment of consent, so
 * that row must keep returning the exact text it pointed to even after the
 * document changes again later.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_LEGAL_VERSIONS_DB_VERSION = '1.0';

/** post ID => doc type key, for the two documents this tracks. */
function ecm_legal_doc_post_ids() : array {
	return [
		34   => 'terms',
		9141 => 'privacy',
	];
}

function ecm_legal_versions_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ecm_legal_versions';
}

function ecm_legal_versions_maybe_create_table() : void {
	if ( get_option( 'ecm_legal_versions_db_version' ) === ECM_LEGAL_VERSIONS_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table           = ecm_legal_versions_table();
	$charset_collate = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		doc_type varchar(20) NOT NULL,
		version_id varchar(20) NOT NULL,
		post_id bigint(20) unsigned NOT NULL,
		content longtext NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY doc_version (doc_type, version_id)
	) ENGINE=InnoDB {$charset_collate};" );
	update_option( 'ecm_legal_versions_db_version', ECM_LEGAL_VERSIONS_DB_VERSION );
}
add_action( 'init', 'ecm_legal_versions_maybe_create_table' );

/**
 * Backfill: the Terms/Privacy pages were published long before this
 * versioning system existed. Archive their current content as each
 * document's first version (dated at the page's own last-modified date)
 * the first time this runs, so there's always at least one version to
 * reference — never touches a doc_type that already has a version.
 */
function ecm_legal_versions_backfill() : void {
	global $wpdb;
	$table = ecm_legal_versions_table();
	foreach ( ecm_legal_doc_post_ids() as $post_id => $doc_type ) {
		$has_version = $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE doc_type = %s LIMIT 1", $doc_type
		) );
		if ( $has_version ) {
			continue;
		}
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			continue;
		}
		$wpdb->insert( $table, [
			'doc_type'   => $doc_type,
			'version_id' => substr( $post->post_modified, 0, 10 ), // Y-m-d
			'post_id'    => $post_id,
			'content'    => $post->post_content,
			'created_at' => $post->post_modified,
		] );
	}
}
add_action( 'init', 'ecm_legal_versions_backfill', 21 );

/**
 * Archives the current content of a Terms/Privacy page as a new version,
 * unless today's version already holds this exact content (repeat saves
 * with no real change don't spawn pointless versions).
 */
function ecm_legal_archive_on_save( int $post_id, WP_Post $post ) : void {
	$doc_types = ecm_legal_doc_post_ids();
	if ( ! isset( $doc_types[ $post_id ] ) || 'publish' !== $post->post_status ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	global $wpdb;
	$doc_type = $doc_types[ $post_id ];
	$content  = $post->post_content;
	$table    = ecm_legal_versions_table();

	$today = current_time( 'Y-m-d' );
	$suffix = 0;
	do {
		$version_id = $today . ( $suffix ? '-' . ( $suffix + 1 ) : '' );
		$existing   = $wpdb->get_var( $wpdb->prepare(
			"SELECT content FROM {$table} WHERE doc_type = %s AND version_id = %s",
			$doc_type, $version_id
		) );
		if ( null === $existing ) {
			// No row for this version_id yet — write it.
			$wpdb->insert( $table, [
				'doc_type'   => $doc_type,
				'version_id' => $version_id,
				'post_id'    => $post_id,
				'content'    => $content,
			] );
			return;
		}
		if ( $existing === $content ) {
			// Already archived verbatim under this version_id — nothing to do.
			return;
		}
		$suffix++;
	} while ( $suffix < 50 ); // sanity cap against a runaway loop
}
add_action( 'save_post', 'ecm_legal_archive_on_save', 20, 2 );

/** Latest version identifier for a doc type ('terms' or 'privacy'), or ''. */
function ecm_legal_latest_version( string $doc_type ) : string {
	global $wpdb;
	$table = ecm_legal_versions_table();
	return (string) ( $wpdb->get_var( $wpdb->prepare(
		"SELECT version_id FROM {$table} WHERE doc_type = %s ORDER BY id DESC LIMIT 1",
		$doc_type
	) ) ?: '' );
}

/** Exact archived text for a specific version, or null if it doesn't exist. */
function ecm_legal_version_content( string $doc_type, string $version_id ) : ?string {
	global $wpdb;
	$table = ecm_legal_versions_table();
	$row   = $wpdb->get_var( $wpdb->prepare(
		"SELECT content FROM {$table} WHERE doc_type = %s AND version_id = %s",
		$doc_type, $version_id
	) );
	return null === $row ? null : (string) $row;
}
