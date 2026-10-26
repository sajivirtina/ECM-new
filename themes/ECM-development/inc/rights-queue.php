<?php
/**
 * Rights@ElderCareMatters.com intake queue — Privacy Compliance: Developer
 * Requirements, items 47-50 (doc 6.2, 6.3, 6.4, 6.5).
 *
 * Rights@ is an email inbox, not a web form (doc 6.4: "Rights@ is the only
 * intake channel") — there is no inbound-email webhook in this stack, so a
 * staff member reads the email and logs it here as a new "Rights Request"
 * (custom post type, admin-only). Everything downstream — the 45-day
 * clock, identity verification, the confirmation code, the deletion flow,
 * and the WA/NV list-of-recipients response — is automated from that point.
 *
 * Covers:
 *  47. The queue itself, with a 45-day due date per request.
 *  48. Identity verification (email+phone match) before anything beyond a
 *      simple opt-out proceeds; a confirmation code gates the rest.
 *  49. The deletion flow, all four 6.3 steps, in order.
 *  50. WA/NV list-of-recipients response.
 *
 * NOT built here: 6.2's broader "block every provider on ALL of a family's
 * requests" (this covers a single request at a time, same as stop-contact)
 * — ask if you want the family-wide version.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_RIGHTS_DUE_DAYS = 45;
const ECM_RIGHTS_CODE_TTL = 10 * MINUTE_IN_SECONDS;

// ── Post type ────────────────────────────────────────────────────────────────

add_action( 'init', function () {
	register_post_type( 'ecm_rights_request', [
		'label'           => 'Rights Requests',
		'labels'          => [
			'name'          => 'Rights Requests',
			'singular_name' => 'Rights Request',
			'menu_name'     => 'Rights Requests',
			'add_new_item'  => 'Log a Rights@ Request',
		],
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-privacy',
		'supports'        => [ 'title' ],
		// Admin-only — this screen handles deletion of family PII and must not
		// be reachable by a provider or editor role.
		//
		// IMPORTANT: never map the singular meta caps (edit_post/read_post/
		// delete_post) to 'manage_options' here. WordPress registers whatever
		// string you assign them in a GLOBAL $post_type_meta_caps lookup
		// (see _post_type_meta_capabilities() in wp-includes/post.php) — so
		// doing that turns 'manage_options' itself into a meta capability
		// requiring a post ID site-wide, and current_user_can('manage_options')
		// starts returning false for EVERY user, everywhere, with no object ID
		// to check against. (Confirmed live: this broke wp-admin access
		// entirely until these three keys were removed.) Leaving them unset
		// lets WordPress derive safe, unique defaults automatically — the
		// actual admin-only enforcement comes from the plural/primitive caps
		// below (edit_posts, publish_posts, etc.), which don't have this
		// collision risk.
		'capability_type' => 'ecm_rights_request',
		'capabilities'    => [
			'edit_posts'            => 'manage_options',
			'edit_others_posts'     => 'manage_options',
			'publish_posts'         => 'manage_options',
			'read_private_posts'    => 'manage_options',
			'delete_posts'          => 'manage_options',
			'delete_private_posts'  => 'manage_options',
			'delete_published_posts' => 'manage_options',
			'delete_others_posts'   => 'manage_options',
			'edit_private_posts'    => 'manage_options',
			'edit_published_posts'  => 'manage_options',
			'create_posts'          => 'manage_options',
		],
		'map_meta_cap'    => true,
	] );
} );

/**
 * Left unset above, edit_post/read_post/delete_post auto-derive to
 * edit_ecm_rights_request / read_ecm_rights_request /
 * delete_ecm_rights_request — unique strings nobody actually holds, which
 * silently breaks anything that checks them per-post (get_edit_post_link(),
 * the list table's Edit link, the redirect-back-to-this-post logic in the
 * status box below — confirmed live: without this, every action handler
 * fell back to the generic post list instead of returning to the request
 * being worked on). This grants exactly those three derived strings to
 * manage_options, and nothing else — unlike mapping 'manage_options'
 * itself into the capabilities array (see the comment above), this is
 * safe: the filter only fires for these specific, unique capability
 * names, never for a check of 'manage_options' itself.
 */
add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id, $args ) {
	$pto = get_post_type_object( 'ecm_rights_request' );
	if ( $pto && in_array( $cap, [ $pto->cap->edit_post, $pto->cap->read_post, $pto->cap->delete_post ], true ) ) {
		return [ 'manage_options' ];
	}
	return $caps;
}, 10, 4 );

// ── "Find Matching Requests" — email/phone lookup against Care Leads ───────

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || 'ecm_rights_request' !== get_post_type() ) {
		return;
	}
	$js_path = get_template_directory() . '/assets/js/ecm-rights-admin.js';
	$js_v    = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0';
	wp_enqueue_script( 'ecm-rights-admin', get_template_directory_uri() . '/assets/js/ecm-rights-admin.js', [], $js_v, true );
	wp_localize_script( 'ecm-rights-admin', 'ECM_RIGHTS_ADMIN', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'ecm_rights_find_leads' ),
	] );
} );

/**
 * Searches Care Leads by the requester's email and/or phone — the lookup
 * behind the "Find Matching Requests" button, so staff don't have to hunt
 * for an ecm_lead's ID by hand (previously the only option). Matches
 * EITHER field (not both) deliberately: a typo or formatting difference in
 * one shouldn't hide a real match — "Check Identity Match" still requires
 * an exact match on both before anything can be actioned, this just helps
 * find candidates.
 */
add_action( 'wp_ajax_ecm_rights_find_leads', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied.', 403 );
	}
	check_ajax_referer( 'ecm_rights_find_leads', 'nonce' );

	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$phone = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['phone'] ?? '' ) );

	if ( ! $email && ! $phone ) {
		wp_send_json_error( 'Enter an email or phone number to search.' );
	}

	$meta_query = [ 'relation' => 'OR' ];
	if ( $email ) {
		$meta_query[] = [ 'key' => '_ecm_email', 'value' => $email, 'compare' => '=' ];
	}
	if ( $phone ) {
		// Stored phone values aren't normalized, so compare loosely against
		// the digits-only version of whatever's on file.
		$meta_query[] = [ 'key' => '_ecm_phone', 'value' => $phone, 'compare' => 'LIKE' ];
	}

	$leads = get_posts( [
		'post_type'      => 'ecm_lead',
		'post_status'    => 'any',
		'numberposts'    => 20,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-only, low-volume lookup tool, not a frontend query.
	] );

	$results = [];
	foreach ( $leads as $lead ) {
		$picked = array_filter( array_map( 'get_the_title', (array) get_post_meta( $lead->ID, '_ecm_picked_provider_ids', true ) ) );
		$results[] = [
			'id'       => $lead->ID,
			'title'    => get_the_title( $lead ) ?: '(no title)',
			'email'    => get_post_meta( $lead->ID, '_ecm_email', true ),
			'phone'    => get_post_meta( $lead->ID, '_ecm_phone', true ),
			'date'     => get_the_date( 'M j, Y', $lead ),
			'picked'   => $picked ? implode( ', ', $picked ) : '—',
		];
	}

	wp_send_json_success( $results );
} );

function ecm_rights_right_types() : array {
	return [
		'access'              => 'Confirm & access',
		'list_of_recipients'  => 'List of recipients',
		'withdraw'            => 'Withdraw consent (simple opt-out)',
		'delete'              => 'Delete',
		'appeal'              => 'Appeal',
	];
}

/** "Anything beyond a simple opt-out" [6.4] — these require the confirmation code; withdraw/appeal don't. */
function ecm_rights_requires_code( string $right_type ) : bool {
	return in_array( $right_type, [ 'access', 'list_of_recipients', 'delete' ], true );
}

/** List-of-recipients is a WA/NV-only right [6.5] — either the requester's own state or the care location's state qualifies. */
function ecm_rights_is_wa_nv( int $post_id ) : bool {
	$requester_state = strtoupper( (string) get_post_meta( $post_id, '_ecm_requester_state', true ) );
	$care_state      = strtoupper( (string) get_post_meta( $post_id, '_ecm_rq_care_state', true ) );
	return in_array( $requester_state, [ 'WA', 'NV' ], true ) || in_array( $care_state, [ 'WA', 'NV' ], true );
}

// ── Meta box: intake fields ─────────────────────────────────────────────────

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'ecm_rights_intake', 'Request Details', 'ecm_rights_render_intake_box', 'ecm_rights_request', 'normal', 'high' );
	add_meta_box( 'ecm_rights_status', 'Status & Actions', 'ecm_rights_render_status_box', 'ecm_rights_request', 'side', 'high' );
} );

function ecm_rights_render_intake_box( WP_Post $post ) : void {
	wp_nonce_field( 'ecm_rights_save_intake', 'ecm_rights_intake_nonce' );
	$f = fn( $key ) => esc_attr( get_post_meta( $post->ID, $key, true ) );
	$right_type = get_post_meta( $post->ID, '_ecm_right_type', true );
	$locked     = (bool) get_post_meta( $post->ID, '_ecm_rights_locked', true ); // locked once a code has been sent, to stop the match target shifting mid-verification
	?>
	<table class="form-table">
		<tr><th><label>Requester name</label></th><td><input type="text" name="requester_name" value="<?php echo $f( '_ecm_requester_name' ); ?>" class="regular-text" <?php disabled( $locked ); ?>></td></tr>
		<tr><th><label>Requester email</label></th><td><input type="email" id="ecm_rights_requester_email" name="requester_email" value="<?php echo $f( '_ecm_requester_email' ); ?>" class="regular-text" <?php disabled( $locked ); ?>></td></tr>
		<tr><th><label>Requester phone</label></th><td><input type="text" id="ecm_rights_requester_phone" name="requester_phone" value="<?php echo $f( '_ecm_requester_phone' ); ?>" class="regular-text" <?php disabled( $locked ); ?>></td></tr>
		<tr><th><label>Requester state</label></th><td><input type="text" name="requester_state" value="<?php echo $f( '_ecm_requester_state' ); ?>" class="small-text" maxlength="2" placeholder="e.g. WA" <?php disabled( $locked ); ?>></td></tr>
		<tr><th><label>Care state (if different)</label></th><td><input type="text" name="care_state" value="<?php echo $f( '_ecm_rq_care_state' ); ?>" class="small-text" maxlength="2" <?php disabled( $locked ); ?>></td></tr>
		<tr><th><label>Which right</label></th><td>
			<select name="right_type" <?php disabled( $locked ); ?>>
				<option value="">— Select —</option>
				<?php foreach ( ecm_rights_right_types() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $right_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td></tr>
		<tr><th><label>Related request (ecm_lead ID)</label></th><td>
			<input type="number" id="ecm_rights_related_lead_id" name="related_lead_id" value="<?php echo $f( '_ecm_related_lead_id' ); ?>" class="small-text" <?php disabled( $locked ); ?>>
			<?php if ( ! $locked ) : ?>
				<button type="button" class="button" id="ecm_rights_find_leads_btn">Find Matching Requests</button>
			<?php endif; ?>
			<p class="description">Click "Find Matching Requests" to search Care Leads by the email/phone entered above, or enter the ID directly if you already know it.</p>
			<div id="ecm_rights_find_leads_results"></div>
		</td></tr>
	</table>
	<?php if ( $locked ) : ?>
		<p class="description">Fields are locked because a confirmation code has already been sent for this request — the identity being verified can't change mid-flow. Use a new Rights Request if these details were entered wrong.</p>
	<?php endif; ?>
	<?php
}

add_action( 'save_post_ecm_rights_request', function ( int $post_id ) {
	if ( ! isset( $_POST['ecm_rights_intake_nonce'] ) || ! wp_verify_nonce( $_POST['ecm_rights_intake_nonce'], 'ecm_rights_save_intake' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( get_post_meta( $post_id, '_ecm_rights_locked', true ) ) {
		return; // Intake fields are frozen once a code has gone out.
	}

	update_post_meta( $post_id, '_ecm_requester_name', sanitize_text_field( wp_unslash( $_POST['requester_name'] ?? '' ) ) );
	update_post_meta( $post_id, '_ecm_requester_email', sanitize_email( wp_unslash( $_POST['requester_email'] ?? '' ) ) );
	update_post_meta( $post_id, '_ecm_requester_phone', sanitize_text_field( wp_unslash( $_POST['requester_phone'] ?? '' ) ) );
	update_post_meta( $post_id, '_ecm_requester_state', strtoupper( sanitize_text_field( wp_unslash( $_POST['requester_state'] ?? '' ) ) ) );
	update_post_meta( $post_id, '_ecm_rq_care_state', strtoupper( sanitize_text_field( wp_unslash( $_POST['care_state'] ?? '' ) ) ) );
	update_post_meta( $post_id, '_ecm_right_type', sanitize_key( wp_unslash( $_POST['right_type'] ?? '' ) ) );
	update_post_meta( $post_id, '_ecm_related_lead_id', absint( $_POST['related_lead_id'] ?? 0 ) );

	// 45-day clock [47] — starts once, from the first save, never reset.
	if ( ! get_post_meta( $post_id, '_ecm_due_at', true ) ) {
		update_post_meta( $post_id, '_ecm_due_at', gmdate( 'Y-m-d H:i:s', time() + ECM_RIGHTS_DUE_DAYS * DAY_IN_SECONDS ) );
		update_post_meta( $post_id, '_ecm_status', 'pending' );
	}
}, 10, 1 );

// ── Status box + actions ─────────────────────────────────────────────────────

function ecm_rights_render_status_box( WP_Post $post ) : void {
	$status      = get_post_meta( $post->ID, '_ecm_status', true ) ?: 'pending';
	$due_at      = get_post_meta( $post->ID, '_ecm_due_at', true );
	$match       = get_post_meta( $post->ID, '_ecm_identity_match', true );
	$verified    = (bool) get_post_meta( $post->ID, '_ecm_identity_verified', true );
	$right_type  = get_post_meta( $post->ID, '_ecm_right_type', true );
	$lead_id     = (int) get_post_meta( $post->ID, '_ecm_related_lead_id', true );
	$needs_code  = ecm_rights_requires_code( $right_type );
	$completed_at = get_post_meta( $post->ID, '_ecm_completed_at', true );
	$recipients  = get_post_meta( $post->ID, '_ecm_recipients_result', true );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a message just set by our own redirect; no state change happens from reading it.
	if ( ! empty( $_GET['ecm_notice'] ) ) {
		echo '<p style="color:#b91c1c;background:#fee2e2;padding:8px 10px;border-radius:4px;">' . esc_html( rawurldecode( wp_unslash( $_GET['ecm_notice'] ) ) ) . '</p>';
	}

	echo '<p><strong>Status:</strong> ' . esc_html( ucwords( str_replace( '_', ' ', $status ) ) ) . '</p>';
	if ( $due_at ) {
		echo '<p><strong>Due:</strong> ' . esc_html( wp_date( 'M j, Y', strtotime( $due_at ) ) ) . ' (45-day clock)</p>';
	}
	if ( $completed_at ) {
		echo '<p><strong>Completed:</strong> ' . esc_html( wp_date( 'M j, Y g:i a', strtotime( $completed_at ) ) ) . '</p>';
	}

	echo '<hr>';

	if ( ! $lead_id ) {
		echo '<p class="description">Enter a Related request ID above and save before checking identity.</p>';
		return;
	}

	// Plain GET links, not nested <form>s: the entire post-edit screen is
	// already one big <form id="post" ...> wrapping every meta box, and
	// HTML forbids nesting <form> elements — a <form> echoed here gets
	// silently dropped by the browser's parser, leaving the button
	// orphaned inside WordPress's OWN form, which just re-submits the post
	// edit screen instead of hitting admin-post.php at all. (Confirmed
	// live: every action button was a no-op precisely because of this —
	// the click just saved the post and bounced to the generic post list.)
	// A one-click GET link with a nonce is the standard safe pattern for
	// admin-post.php actions that need no extra input.
	$redirect = get_edit_post_link( $post->ID, '' );
	$link = function ( string $action ) use ( $post, $redirect ) {
		return wp_nonce_url(
			add_query_arg( [ 'action' => $action, 'request_post_id' => $post->ID, '_redirect' => rawurlencode( $redirect ) ], admin_url( 'admin-post.php' ) ),
			'ecm_rights_action_' . $post->ID
		);
	};

	echo '<p><a href="' . esc_url( $link( 'ecm_rights_check_identity' ) ) . '" class="button">Check Identity Match</a></p>';

	if ( 'yes' === $match ) {
		echo '<p style="color:#2d6a4f;">✓ Identity matches the original request.</p>';
	} elseif ( 'no' === $match ) {
		echo '<p style="color:#b91c1c;">✗ Mismatch — email/phone do not match the related request. Do not action this request.</p>';
	}

	if ( 'yes' === $match && $needs_code && ! $verified ) {
		echo '<p><a href="' . esc_url( $link( 'ecm_rights_send_code' ) ) . '" class="button">Send Confirmation Code</a></p>';

		if ( get_post_meta( $post->ID, '_ecm_confirmation_code', true ) ) {
			// The one case needing real user input (the code) — a small
			// inline script builds the GET link client-side rather than a
			// <form>, for the same nested-form reason as above.
			$verify_base = $link( 'ecm_rights_verify_code' );
			// A <script> block, not an inline onclick="" attribute — wp_json_encode()'s
			// own double quotes would otherwise break out of an HTML attribute value.
			echo '<p><input type="text" id="ecm_rights_code_input" placeholder="6-digit code" maxlength="6" style="width:90px;"> '
				. '<button type="button" class="button" id="ecm_rights_verify_code_btn">Verify Code</button></p>';
			// wp_nonce_url() always HTML-escapes its return value (& becomes &amp;) —
			// correct for an href="" attribute, but <script> content is RAWTEXT and
			// never gets HTML-entity-decoded by the browser, so that &amp; would
			// stay literal in the built URL and the real & (and everything after,
			// including the nonce) would never reach the server. Decode it back
			// before handing it to JS. (Confirmed live: without this, the nonce
			// check failed with "The link you followed has expired.")
			echo '<script>document.getElementById("ecm_rights_verify_code_btn").addEventListener("click", function () {'
				. 'var code = document.getElementById("ecm_rights_code_input").value;'
				. 'window.location.href = ' . wp_json_encode( html_entity_decode( $verify_base ) ) . ' + "&code=" + encodeURIComponent(code);'
				. '});</script>';
		}
	} elseif ( 'yes' === $match && ! $needs_code ) {
		echo '<p class="description">This right type does not require a confirmation code.</p>';
	}

	if ( $verified ) {
		echo '<p style="color:#2d6a4f;">✓ Identity verified.</p>';
	}

	$can_act = 'yes' === $match && ( ! $needs_code || $verified ) && 'completed' !== $status;

	if ( $can_act && 'delete' === $right_type ) {
		echo '<p><a href="' . esc_url( $link( 'ecm_rights_process_deletion' ) ) . '" class="button button-primary" onclick="return confirm(\'Permanently delete this request and all contact details? This cannot be undone.\');">Process Deletion</a></p>';
	}

	if ( $can_act && 'list_of_recipients' === $right_type ) {
		if ( ecm_rights_is_wa_nv( $post->ID ) ) {
			echo '<p><a href="' . esc_url( $link( 'ecm_rights_generate_recipients' ) ) . '" class="button button-primary">Generate List of Recipients</a></p>';
		} else {
			echo '<p style="color:#b91c1c;">List-of-recipients is a Washington/Nevada right (doc 6.5) — set Requester state or Care state to WA or NV above and save before this can be generated.</p>';
		}
	}

	if ( $recipients ) {
		echo '<p><strong>Recipients list (copy into reply):</strong></p>';
		echo '<textarea readonly rows="6" style="width:100%;">' . esc_textarea( $recipients ) . '</textarea>';
	}

	if ( 'completed' !== $status ) {
		echo '<hr><p><a href="' . esc_url( $link( 'ecm_rights_decline' ) ) . '" class="button-link-delete" onclick="return confirm(\'Mark this request declined?\');">Mark Declined</a></p>';
	}
}

/** Shared guard for every admin-post handler below. GET links, so $_REQUEST (not $_POST). */
function ecm_rights_action_guard() : array {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'eldercare-matters' ), '', [ 'response' => 403 ] );
	}
	$post_id = absint( $_REQUEST['request_post_id'] ?? 0 );
	check_admin_referer( 'ecm_rights_action_' . $post_id );
	$post = get_post( $post_id );
	if ( ! $post || 'ecm_rights_request' !== $post->post_type ) {
		wp_die( esc_html__( 'Invalid request.', 'eldercare-matters' ), '', [ 'response' => 404 ] );
	}
	$redirect = esc_url_raw( wp_unslash( $_REQUEST['_redirect'] ?? admin_url() ) );
	return [ $post, $redirect ];
}

/**
 * For a precondition that can legitimately be stale by the time a click
 * lands (e.g. the page was open in another tab, or state changed between
 * page load and click) — redirects back with a friendly inline notice
 * instead of a dead-end wp_die() page. Reserved for recoverable, expected
 * failures; a genuine permission/tampering case still uses wp_die().
 */
function ecm_rights_redirect_with_notice( string $redirect, string $message ) : void {
	wp_safe_redirect( add_query_arg( 'ecm_notice', rawurlencode( $message ), $redirect ) );
	exit;
}

/** Item 48: match the requester's email+phone against the related ecm_lead's own submitted email+phone. */
add_action( 'admin_post_ecm_rights_check_identity', function () {
	[ $post, $redirect ] = ecm_rights_action_guard();

	$lead_id = (int) get_post_meta( $post->ID, '_ecm_related_lead_id', true );
	$lead    = $lead_id ? get_post( $lead_id ) : null;

	$match = 'no';
	if ( $lead && 'ecm_lead' === $lead->post_type ) {
		$req_email = strtolower( trim( (string) get_post_meta( $post->ID, '_ecm_requester_email', true ) ) );
		$req_phone = preg_replace( '/\D/', '', (string) get_post_meta( $post->ID, '_ecm_requester_phone', true ) );
		$lead_email = strtolower( trim( (string) get_post_meta( $lead_id, '_ecm_email', true ) ) );
		$lead_phone = preg_replace( '/\D/', '', (string) get_post_meta( $lead_id, '_ecm_phone', true ) );

		if ( $req_email && $req_email === $lead_email && $req_phone && $req_phone === $lead_phone ) {
			$match = 'yes';
		}
	}

	update_post_meta( $post->ID, '_ecm_identity_match', $match );
	wp_safe_redirect( $redirect );
	exit;
} );

/** Sends the confirmation code to the email ON FILE on the original request — never to whatever the requester typed, so a spoofed request can't redirect its own verification. */
add_action( 'admin_post_ecm_rights_send_code', function () {
	[ $post, $redirect ] = ecm_rights_action_guard();

	if ( 'yes' !== get_post_meta( $post->ID, '_ecm_identity_match', true ) ) {
		ecm_rights_redirect_with_notice( $redirect, 'Identity has not been matched yet.' );
	}

	$lead_id = (int) get_post_meta( $post->ID, '_ecm_related_lead_id', true );
	$to      = (string) get_post_meta( $lead_id, '_ecm_email', true );
	if ( ! $to || ! is_email( $to ) ) {
		ecm_rights_redirect_with_notice( $redirect, 'No email on file for the related request.' );
	}

	$code = (string) wp_rand( 100000, 999999 );
	update_post_meta( $post->ID, '_ecm_confirmation_code', wp_hash_password( $code ) );
	update_post_meta( $post->ID, '_ecm_confirmation_code_expires', time() + ECM_RIGHTS_CODE_TTL );
	update_post_meta( $post->ID, '_ecm_rights_locked', 1 ); // freeze intake fields now that a code has gone out

	wp_mail(
		$to,
		'Your ElderCareMatters privacy rights confirmation code',
		"Your confirmation code is {$code}. It expires in 10 minutes.\n\nWe are confirming a privacy rights request (Rights@ElderCareMatters.com) on your ElderCareMatters submission. If you did not make this request, ignore this email and nothing happens."
	);

	wp_safe_redirect( $redirect );
	exit;
} );

add_action( 'admin_post_ecm_rights_verify_code', function () {
	[ $post, $redirect ] = ecm_rights_action_guard();

	$submitted = preg_replace( '/\D/', '', (string) ( $_REQUEST['code'] ?? '' ) );
	$stored    = (string) get_post_meta( $post->ID, '_ecm_confirmation_code', true );
	$expires   = (int) get_post_meta( $post->ID, '_ecm_confirmation_code_expires', true );

	if ( $stored && $expires > time() && $submitted && wp_check_password( $submitted, $stored ) ) {
		update_post_meta( $post->ID, '_ecm_identity_verified', 1 );
		delete_post_meta( $post->ID, '_ecm_confirmation_code' );
		delete_post_meta( $post->ID, '_ecm_confirmation_code_expires' );
	} else {
		update_post_meta( $post->ID, '_ecm_code_error', 1 );
	}

	wp_safe_redirect( $redirect );
	exit;
} );

/** Item 49 — deletion flow, doc 6.3's four steps, in order. */
add_action( 'admin_post_ecm_rights_process_deletion', function () {
	[ $post, $redirect ] = ecm_rights_action_guard();

	if ( 'yes' !== get_post_meta( $post->ID, '_ecm_identity_match', true ) || ! get_post_meta( $post->ID, '_ecm_identity_verified', true ) ) {
		ecm_rights_redirect_with_notice( $redirect, 'Identity must be matched and verified before a deletion can be processed.' );
	}

	$lead_id = (int) get_post_meta( $post->ID, '_ecm_related_lead_id', true );
	$lead    = get_post( $lead_id );
	if ( ! $lead || 'ecm_lead' !== $lead->post_type ) {
		wp_die( esc_html__( 'Related request no longer exists.', 'eldercare-matters' ), '', [ 'response' => 404 ] );
	}

	// 1. Delete the request record and all contact details.
	wp_delete_post( $lead_id, true );

	// 2. Notify every provider in the unmask log for that request.
	if ( function_exists( 'ecm_distribution_log_for_request' ) ) {
		$notified = [];
		foreach ( ecm_distribution_log_for_request( $lead_id ) as $event ) {
			if ( 'unmask_granted' !== $event['event_type'] ) {
				continue;
			}
			$provider_id = (int) $event['provider_id'];
			if ( in_array( $provider_id, $notified, true ) ) {
				continue;
			}
			$notified[] = $provider_id;
			$to = function_exists( 'ecm_provider_contact_email' ) ? ecm_provider_contact_email( $provider_id ) : '';
			if ( $to && is_email( $to ) ) {
				wp_mail(
					$to,
					'Please delete contact details — ElderCareMatters request',
					"The family on this request has exercised their right to delete their data. Please delete any contact details or notes you retained from this request and do not contact them further about it.\n\nQuestions: Help@ElderCareMatters.com"
				);
			}
		}
	}

	// 3. Keep the consent_record and mark it subject_deleted.
	if ( function_exists( 'ecm_mark_consent_subject_deleted' ) ) {
		ecm_mark_consent_subject_deleted( $lead_id );
	}

	// 4. Write a completion row with timestamp.
	update_post_meta( $post->ID, '_ecm_status', 'completed' );
	update_post_meta( $post->ID, '_ecm_completed_at', current_time( 'mysql' ) );

	wp_safe_redirect( $redirect );
	exit;
} );

/**
 * Item 50 — WA/NV list of recipients [6.5]: provider business names from
 * the distribution log, plus the fixed service-provider list named in the
 * Consumer Health Data Notice (hosting, email delivery, payments, analytics
 * — update this if that document's vendor list ever changes).
 */
function ecm_chd_notice_service_providers() : array {
	return [
		'Hosting provider',
		'Email delivery (Brevo or Zoho)',
		'Payments (Stripe)',
		'Analytics (Google)',
	];
}

/**
 * Every distinct provider the distribution log shows received ANYTHING
 * about this request — a 'distributed' row alone counts, not just
 * 'unmask_granted', because the masked payload itself already carries
 * consumer health data under the Consumer Health Data Notice's own
 * definition (care category, timing, and notes can all reveal a
 * condition) — "received it" isn't limited to the unmasked/full-contact
 * case. Plus the fixed vendor list from that notice.
 */
function ecm_build_recipients_list( int $lead_id ) : string {
	$events       = function_exists( 'ecm_distribution_log_for_request' ) ? ecm_distribution_log_for_request( $lead_id ) : [];
	$provider_ids = array_unique( array_map( fn( $e ) => (int) $e['provider_id'], array_filter( $events, fn( $e ) => 0 !== (int) $e['provider_id'] ) ) );

	$names = [];
	foreach ( $provider_ids as $pid ) {
		$title = get_the_title( $pid );
		if ( $title ) {
			$names[] = $title;
		}
	}

	$lines   = [ 'Providers who received this request:' ];
	$lines[] = $names ? implode( ', ', $names ) : '(none)';
	$lines[] = '';
	$lines[] = 'Our service providers (process data under contract):';
	$lines[] = implode( ', ', ecm_chd_notice_service_providers() );

	return implode( "\n", $lines );
}

add_action( 'admin_post_ecm_rights_generate_recipients', function () {
	[ $post, $redirect ] = ecm_rights_action_guard();

	if ( 'yes' !== get_post_meta( $post->ID, '_ecm_identity_match', true ) || ! get_post_meta( $post->ID, '_ecm_identity_verified', true ) ) {
		ecm_rights_redirect_with_notice( $redirect, 'Identity must be matched and verified first.' );
	}

	if ( ! ecm_rights_is_wa_nv( $post->ID ) ) {
		ecm_rights_redirect_with_notice( $redirect, 'List-of-recipients is a Washington/Nevada right — neither state is set on this request.' );
	}

	$lead_id = (int) get_post_meta( $post->ID, '_ecm_related_lead_id', true );
	update_post_meta( $post->ID, '_ecm_recipients_result', ecm_build_recipients_list( $lead_id ) );
	update_post_meta( $post->ID, '_ecm_status', 'completed' );
	update_post_meta( $post->ID, '_ecm_completed_at', current_time( 'mysql' ) );

	wp_safe_redirect( $redirect );
	exit;
} );

add_action( 'admin_post_ecm_rights_decline', function () {
	[ $post, $redirect ] = ecm_rights_action_guard();
	update_post_meta( $post->ID, '_ecm_status', 'declined' );
	update_post_meta( $post->ID, '_ecm_completed_at', current_time( 'mysql' ) );
	wp_safe_redirect( $redirect );
	exit;
} );

// ── Admin list table: surface status + due date at a glance ─────────────────

add_filter( 'manage_ecm_rights_request_posts_columns', function ( $columns ) {
	$columns['right_type'] = 'Right';
	$columns['status']     = 'Status';
	$columns['due_at']     = 'Due';
	return $columns;
} );

add_action( 'manage_ecm_rights_request_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'right_type':
			$type = get_post_meta( $post_id, '_ecm_right_type', true );
			echo esc_html( ecm_rights_right_types()[ $type ] ?? '—' );
			break;
		case 'status':
			echo esc_html( ucwords( str_replace( '_', ' ', get_post_meta( $post_id, '_ecm_status', true ) ?: 'pending' ) ) );
			break;
		case 'due_at':
			$due = get_post_meta( $post_id, '_ecm_due_at', true );
			echo $due ? esc_html( wp_date( 'M j, Y', strtotime( $due ) ) ) : '—';
			break;
	}
}, 10, 2 );
