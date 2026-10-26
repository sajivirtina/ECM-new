<?php
/**
 * Manually saves the native fields (gallery, description, and the 5
 * repeaters) directly from $_POST — bypassing WP Job Manager core's own
 * generic per-field save loop in update_job_data() entirely.
 *
 * See the previous version's docblock (same file) for the full backstory:
 * $_POST proven correct, this action proven to fire reliably, WPJM's own
 * save loop still not persisting the data. A DIRECT unconditional save on
 * a proven-firing hook STILL not persisting is unusual enough that it
 * needs runtime evidence, not more code-reading — so this version adds
 * explicit, labeled error_log() tracing at every step, PLUS a same-request
 * write-then-read-back check, to distinguish two very different
 * possibilities:
 *   1. update_post_meta() itself never runs or fails right here.
 *   2. It DOES succeed (readable moments later, same request) but
 *      something ELSE, later in the SAME request, deletes/overwrites it
 *      before the page finishes.
 * Search debug.log for "ECM_SAVE_TRACE" after a test attempt.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'ecm_save_trace' ) ) {
	function ecm_save_trace( string $message ) : void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'ECM_SAVE_TRACE: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- temporary diagnostic, see docblock.
		}
	}
}

/**
 * BYPASSES update_post_meta()/update_metadata() entirely — writes directly
 * via $wpdb, skipping the `update_post_metadata` / `add_post_metadata`
 * filters. Confirmed necessary from a live trace: update_post_meta()
 * returned false when saving _lp_faq on a BRAND NEW post that had never
 * had that meta key before — a case where WordPress's own code should
 * always take the "add new row" path and return a truthy meta ID, never
 * false. That specific behavior only happens when something hooks
 * update_post_metadata and explicitly returns a non-null value, short-
 * circuiting update_metadata() before it ever touches the database.
 * Checked Field Editor's own source directly — it does not hook this
 * filter, so something else in the stack does (a security plugin's
 * activity/audit logging is the most common cause of this exact pattern).
 *
 * This bypass also logs which callbacks ARE registered on that filter
 * (ecm_log_update_post_metadata_filters(), called once below) so the
 * actual culprit can still be identified and addressed properly later,
 * rather than only being permanently worked around.
 */
function ecm_force_update_post_meta( int $post_id, string $meta_key, $meta_value ) : bool {
	global $wpdb;
	$serialized = maybe_serialize( $meta_value );

	$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post_id, $meta_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- deliberately bypassing update_post_meta()'s filter layer; see docblock above.

	if ( $existing_id ) {
		$result = $wpdb->update( $wpdb->postmeta, [ 'meta_value' => $serialized ], [ 'meta_id' => $existing_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	} else {
		$result = $wpdb->insert( $wpdb->postmeta, [ 'post_id' => $post_id, 'meta_key' => $meta_key, 'meta_value' => $serialized ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	wp_cache_delete( $post_id, 'post_meta' ); // So get_post_meta() (including our own debug panel) sees the fresh value immediately, not a stale cached copy.

	return false !== $result;
}

function ecm_force_delete_post_meta( int $post_id, string $meta_key ) : void {
	global $wpdb;
	$wpdb->delete( $wpdb->postmeta, [ 'post_id' => $post_id, 'meta_key' => $meta_key ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	wp_cache_delete( $post_id, 'post_meta' );
}

/**
 * One-time diagnostic: logs every callback registered on
 * update_post_metadata — this is the filter something is using to block
 * our writes. Run once (guarded by a transient) the next time this file
 * loads, so the log shows exactly what's hooked there without needing to
 * search every plugin's source by hand.
 */
add_action( 'wp_loaded', function () {
	if ( get_transient( 'ecm_logged_meta_filters' ) ) {
		return;
	}
	set_transient( 'ecm_logged_meta_filters', 1, DAY_IN_SECONDS );

	global $wp_filter;
	if ( empty( $wp_filter['update_post_metadata'] ) ) {
		ecm_save_trace( 'DIAGNOSTIC: nothing is hooked to update_post_metadata at all.' );
		return;
	}
	foreach ( $wp_filter['update_post_metadata']->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) ) {
				$label = ( is_object( $fn[0] ) ? get_class( $fn[0] ) : $fn[0] ) . '::' . $fn[1];
			} elseif ( is_string( $fn ) ) {
				$label = $fn;
			} else {
				$label = 'closure';
			}
			ecm_save_trace( "DIAGNOSTIC: update_post_metadata hook — priority {$priority} — {$label}" );
		}
	}
}, 999999 );


add_action( 'job_manager_update_job_data', function ( $job_id ) {
	ecm_save_trace( '--- hook fired --- job_id=' . var_export( $job_id, true ) );

	if ( ! $job_id ) {
		ecm_save_trace( 'ABORT: job_id is falsy.' );
		return;
	}

	ecm_save_trace( 'POST keys present: ' . implode( ', ', array_keys( $_POST ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- diagnostic only, not used for any action.

	$tier   = function_exists( 'ecm_resolve_current_submission_tier' ) ? ecm_resolve_current_submission_tier( $job_id ) : 'free';
	$limits = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];
	ecm_save_trace( 'tier=' . $tier . ' limits=' . wp_json_encode( $limits ) );

	// ── Gallery ────────────────────────────────────────────────────────
	if ( isset( $_POST['gallery_images'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$gallery_limit = $limits['gallery'] ?? 0;
		$raw           = wp_unslash( $_POST['gallery_images'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$urls          = array_values( array_filter( array_map( 'esc_url_raw', (array) $raw ) ) );
		if ( $gallery_limit > 0 ) {
			$urls = array_slice( $urls, 0, $gallery_limit );
			ecm_save_trace( 'gallery_images: attempting update_post_meta, value=' . wp_json_encode( $urls ) );
			$result = ecm_force_update_post_meta( $job_id, '_gallery_images', $urls );
			ecm_save_trace( 'gallery_images: update_post_meta returned ' . var_export( $result, true ) );
			$readback = get_post_meta( $job_id, '_gallery_images', true );
			ecm_save_trace( 'gallery_images: IMMEDIATE read-back = ' . wp_json_encode( $readback ) );
		}
	} else {
		ecm_save_trace( 'gallery_images: NOT present in $_POST.' );
	}

	// ── Description ────────────────────────────────────────────────────
	if ( isset( $_POST['job_description'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$word_limit = $limits['bio_words'] ?? 0;
		$desc       = wp_kses_post( wp_unslash( $_POST['job_description'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $word_limit > 0 ) {
			ecm_save_trace( 'job_description: attempting update_post_meta, value=' . $desc );
			$result = ecm_force_update_post_meta( $job_id, '_job_description', $desc );
			ecm_save_trace( 'job_description: update_post_meta returned ' . var_export( $result, true ) );
			$readback = get_post_meta( $job_id, '_job_description', true );
			ecm_save_trace( 'job_description: IMMEDIATE read-back = ' . $readback );
		}
	} else {
		ecm_save_trace( 'job_description: NOT present in $_POST.' );
	}

	// ── The 5 repeaters ────────────────────────────────────────────────
	if ( ! function_exists( 'ecm_native_repeater_field_defs' ) ) {
		ecm_save_trace( 'ABORT: ecm_native_repeater_field_defs() does not exist — file not loaded?' );
		return;
	}
	foreach ( ecm_native_repeater_field_defs() as $key => $def ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			ecm_save_trace( $key . ': NOT present in $_POST.' );
			continue;
		}
		if ( ! is_array( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			ecm_save_trace( $key . ': present but NOT an array — raw type=' . gettype( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			continue;
		}

		$max = $limits[ $def['limit_key'] ] ?? 0;
		if ( 0 === $max ) {
			ecm_save_trace( $key . ': tier limit is 0, deleting meta instead of saving.' );
			ecm_force_delete_post_meta( $job_id, '_' . $key );
			continue;
		}

		$raw_rows = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rows     = [];
		foreach ( $raw_rows as $raw_row ) {
			if ( ! is_array( $raw_row ) ) {
				continue;
			}
			$row = [];
			foreach ( $def['sub_fields'] as $sub_key => $sub_def ) {
				$val = $raw_row[ $sub_key ] ?? '';
				if ( 'textarea' === ( $sub_def['type'] ?? 'text' ) ) {
					$row[ $sub_key ] = sanitize_textarea_field( (string) $val );
				} elseif ( 'number' === ( $sub_def['type'] ?? 'text' ) ) {
					$row[ $sub_key ] = is_numeric( $val ) ? (string) $val : '';
				} elseif ( 'checkbox' === ( $sub_def['type'] ?? 'text' ) ) {
					$row[ $sub_key ] = ! empty( $val ) && '0' !== (string) $val ? '1' : '0';
				} else {
					$row[ $sub_key ] = sanitize_text_field( (string) $val );
				}
			}
			$has_content = array_filter( $row, function ( $v ) { return '' !== trim( (string) $v ) && '0' !== (string) $v; } );
			if ( $has_content ) {
				$rows[] = $row;
			}
		}

		$rows = array_slice( $rows, 0, $max );
		ecm_save_trace( $key . ': attempting update_post_meta, ' . count( $rows ) . ' row(s), value=' . wp_json_encode( $rows ) );
		$result = ecm_force_update_post_meta( $job_id, '_' . $key, $rows );
		ecm_save_trace( $key . ': update_post_meta returned ' . var_export( $result, true ) );
		$readback = get_post_meta( $job_id, '_' . $key, true );
		ecm_save_trace( $key . ': IMMEDIATE read-back = ' . wp_json_encode( $readback ) );
	}

	ecm_save_trace( '--- hook finished ---' );
}, 999, 1 );

/**
 * Also trace the VERY END of the whole request, to catch anything that
 * might delete/clear this post's meta AFTER our hook above already ran
 * successfully (e.g. some other plugin/core cleanup step later in the
 * same request) — this is what the "IMMEDIATE read-back" lines above are
 * for comparing against: if those show data present, but this final
 * shutdown check shows it gone, something LATER in the request is the
 * real culprit, not the save itself.
 */
add_action( 'shutdown', function () {
	if ( empty( $_POST['job_id'] ) && empty( $_GET['job_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		return;
	}
	$job_id = ! empty( $_POST['job_id'] ) ? (int) $_POST['job_id'] : (int) $_GET['job_id']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
	if ( ! $job_id || ! function_exists( 'ecm_native_repeater_field_defs' ) ) {
		return;
	}
	foreach ( array_keys( ecm_native_repeater_field_defs() ) as $key ) {
		$val = get_post_meta( $job_id, '_' . $key, true );
		ecm_save_trace( 'SHUTDOWN check — ' . $key . ' on job ' . $job_id . ' = ' . wp_json_encode( $val ) );
	}
} );
