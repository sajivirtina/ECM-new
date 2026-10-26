<?php
/**
 * ECM Intake Form — REST API endpoint.
 *
 * Receives multi-step popup form submissions (POST /wp-json/ecm/v1/lead),
 * saves the lead as a custom post, and emails the site admin.
 *
 * Route: POST /wp-json/ecm/v1/lead
 * Auth:  public (no nonce required — the endpoint sanitises all input).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'ecm/v1', '/lead', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'ecm_handle_lead_submission',
		'permission_callback' => '__return_true',
		'args'                => [
			'careType'    => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'firstName'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'lastName'    => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'city'        => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'state'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'urgency'     => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'email'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
			'phone'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'description' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
			'pickedProviderIds' => [
				'type'  => 'array',
				'items' => [ 'type' => 'integer' ],
				'default' => [],
			],
			'expeditedOptIn' => [ 'type' => 'boolean', 'default' => false ],
		],
	] );
} );

/**
 * WordPress/browser email checks allow local-network style domains like user@host.
 * Public intake leads should include a domain extension, e.g. user@example.com.
 */
function ecm_is_valid_public_email( $email ) {
	if ( ! is_email( $email ) ) {
		return false;
	}

	$domain = substr( strrchr( $email, '@' ), 1 );
	if ( false === $domain || false === strpos( $domain, '.' ) ) {
		return false;
	}

	$tld = substr( strrchr( $domain, '.' ), 1 );
	return (bool) preg_match( '/^[A-Za-z]{2,63}$/', $tld );
}

/**
 * Handle lead submission: save post + send admin email.
 */
function ecm_handle_lead_submission( WP_REST_Request $request ) {
	$care_type   = $request->get_param( 'careType' )    ?: '';
	$first_name  = $request->get_param( 'firstName' )   ?: '';
	$last_name   = $request->get_param( 'lastName' )    ?: '';
	$city        = $request->get_param( 'city' )        ?: '';
	$state       = $request->get_param( 'state' )       ?: '';
	$urgency     = $request->get_param( 'urgency' )     ?: '';
	$email       = $request->get_param( 'email' )       ?: '';
	$phone       = $request->get_param( 'phone' )       ?: '';
	$description = $request->get_param( 'description' ) ?: '';
	$picked_ids  = array_map( 'absint', (array) ( $request->get_param( 'pickedProviderIds' ) ?: [] ) );
	$expedited   = (bool) $request->get_param( 'expeditedOptIn' );

	$email_verified = (bool) $request->get_param( 'emailVerified' );

	// The email is required; verifying it is optional (see ecm_lead_submit_verified()).
	if ( ! $first_name || ! $care_type || ! $email ) {
		return new WP_Error( 'missing_fields', 'Required fields missing.', [ 'status' => 400 ] );
	}

	if ( ! ecm_is_valid_public_email( $email ) ) {
		return new WP_Error( 'invalid_email', 'Please enter a valid email address.', [ 'status' => 400 ] );
	}

	// ── Save lead as a custom post ──────────────────────────────────────────────
	$post_id = wp_insert_post( [
		'post_type'   => 'ecm_lead',
		'post_title'  => $first_name . ' ' . $last_name . ' — ' . $care_type . ' in ' . $city,
		'post_status' => 'private',
		'post_author' => 1,
	] );

	if ( $post_id && ! is_wp_error( $post_id ) ) {
		update_post_meta( $post_id, '_ecm_care_type',   $care_type );
		update_post_meta( $post_id, '_ecm_first_name',  $first_name );
		update_post_meta( $post_id, '_ecm_last_name',   $last_name );
		update_post_meta( $post_id, '_ecm_city',        $city );
		update_post_meta( $post_id, '_ecm_state',       $state );
		update_post_meta( $post_id, '_ecm_urgency',     $urgency );
		update_post_meta( $post_id, '_ecm_email',       $email );
		update_post_meta( $post_id, '_ecm_email_verified', $email_verified ? '1' : '0' );
		update_post_meta( $post_id, '_ecm_phone',       $phone );
		update_post_meta( $post_id, '_ecm_description', $description );
		update_post_meta( $post_id, '_ecm_submitted_at', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_ecm_ip', sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		update_post_meta( $post_id, '_ecm_picked_provider_ids', $picked_ids );
		update_post_meta( $post_id, '_ecm_expedited_opt_in', $expedited );

		// Items 34-36: care_state and submitter_state are deliberately
		// computed from two different signals and stored separately — a
		// family can be arranging care for someone in a different state
		// than their own (doc 8.3's Chicago-arranging-for-Spokane example),
		// and MD/WA/NV handling must trigger on EITHER one.
		$care_state_code      = strtoupper( substr( $state, 0, 2 ) );
		$submitter_ip         = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
		$submitter_state_code = function_exists( 'ecm_lookup_submitter_state_from_ip' )
			? ecm_lookup_submitter_state_from_ip( $submitter_ip )
			: '';
		update_post_meta( $post_id, '_ecm_care_state', $care_state_code );
		update_post_meta( $post_id, '_ecm_submitter_state', $submitter_state_code );

		// Write-once consent record (items 8-13).
		if ( function_exists( 'ecm_create_consent_record' ) ) {
			ecm_create_consent_record(
				$post_id,
				$picked_ids,
				$expedited,
				$care_state_code,
				$submitter_state_code,
				$phone ? [ 'email', 'phone' ] : [ 'email' ]
			);
		}

		// Send the masked lead to every picked provider immediately (item 62),
		// logging a 'distributed' row for each (items 41-42).
		if ( $picked_ids && function_exists( 'ecm_distribute_lead_to_providers' ) ) {
			ecm_distribute_lead_to_providers( $post_id, $picked_ids );
		}
	}

	// ── Email admin ─────────────────────────────────────────────────────────────
	// Add or remove addresses here to control who receives lead notifications.
	$admin_email = array_filter( [
		get_option( 'admin_email' ),
		'developmentqa@gamillc.com',
	] );
	$location    = $city . ( $state ? ', ' . $state : '' );
	$subject     = '[ECM] New care match request — ' . $care_type . ' in ' . $city;

	$admin_link = ( $post_id && ! is_wp_error( $post_id ) )
		? admin_url( 'post.php?post=' . $post_id . '&action=edit' )
		: '';

	$picked_names = array_filter( array_map( 'get_the_title', $picked_ids ) );

	$rows = [
		[ 'Name',        esc_html( $first_name . ' ' . $last_name ) ],
		[ 'Care type',   esc_html( $care_type ) ],
		[ 'Location',    esc_html( $location ) ],
		[ 'Urgency',     esc_html( $urgency ) ],
		[ 'Email',       $email ? '<a href="mailto:' . esc_attr( $email ) . '" style="color:#2d6a4f;">' . esc_html( $email ) . '</a>' : '—' ],
		[ 'Email verified', $email ? ( $email_verified ? 'Yes (priority response)' : 'No' ) : '—' ],
		[ 'Phone',       esc_html( $phone ?: '—' ) ],
		[ 'Picked providers', $picked_names ? esc_html( implode( ', ', $picked_names ) ) : '—' ],
		[ 'Expedited opt-in', $expedited ? 'Yes' : 'No' ],
		[ 'Notes',       nl2br( esc_html( $description ?: '—' ) ) ],
		[ 'Submitted',   esc_html( current_time( 'F j, Y g:i a' ) ) ],
	];

	$table_rows = '';
	foreach ( $rows as $i => [ $label, $value ] ) {
		$bg = $i % 2 === 0 ? '#f9fafb' : '#ffffff';
		$table_rows .= '<tr style="background:' . $bg . ';">'
			. '<td style="padding:10px 16px;font-weight:600;color:#374151;width:130px;border-bottom:1px solid #e5e7eb;">' . $label . '</td>'
			. '<td style="padding:10px 16px;color:#111827;border-bottom:1px solid #e5e7eb;">' . $value . '</td>'
			. '</tr>';
	}

	$body = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;">'
		. '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">'
		. '<tr><td align="center">'
		. '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);">'

		. '<tr><td style="background:#2d6a4f;padding:24px 28px;">'
		. '<h2 style="margin:0;color:#ffffff;font-size:18px;">New Care Match Request</h2>'
		. '<p style="margin:4px 0 0;color:#b7e4c7;font-size:13px;">ElderCareMatters.com &mdash; ' . esc_html( current_time( 'F j, Y' ) ) . '</p>'
		. '</td></tr>'

		. '<tr><td style="padding:20px 28px 8px;">'
		. '<p style="margin:0;color:#6b7280;font-size:14px;">A new match request has been submitted. Details below.</p>'
		. '</td></tr>'

		. '<tr><td style="padding:8px 28px 20px;">'
		. '<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">'
		. $table_rows
		. '</table>'
		. '</td></tr>';

	if ( $admin_link ) {
		$body .= '<tr><td style="padding:0 28px 28px;text-align:center;">'
			. '<a href="' . esc_url( $admin_link ) . '" style="display:inline-block;background:#2d6a4f;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">View Lead in WP Admin &rarr;</a>'
			. '</td></tr>';
	}

	$body .= '</table></td></tr></table></body></html>';

	wp_mail( $admin_email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );

	// ── Auto-reply to user ──────────────────────────────────────────────────────
	$user_subject = 'Your ElderCareMatters request has been received';
	$user_body  = "Hi {$first_name},\n\n";
	$user_body .= "Thanks for reaching out! We've received your request for {$care_type} in {$location}.\n\n";
	$user_body .= "Verified providers in your area will be in touch within 24 hours.\n\n";
	$user_body .= "— The ElderCareMatters Team\n";
	$user_body .= get_bloginfo( 'url' ) . "\n";

	// Stop-contact footer, exact text [6.1, 10.4] — required on every family email about this request.
	if ( $post_id && ! is_wp_error( $post_id ) && function_exists( 'ecm_email_family_footer' ) ) {
		$user_body .= ecm_email_family_footer( $post_id );
	}

	if ( $email ) {
		wp_mail( $email, $user_subject, $user_body );
	}

	return rest_ensure_response( [ 'success' => true, 'lead_id' => $post_id ] );
}

// ── Register ecm_lead custom post type ─────────────────────────────────────────
add_action( 'init', function () {
	register_post_type( 'ecm_lead', [
		'label'               => 'Care Leads',
		'labels'              => [
			'name'          => 'Care Leads',
			'singular_name' => 'Care Lead',
			'menu_name'     => 'Care Leads',
		],
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_icon'           => 'dashicons-groups',
		'supports'            => [ 'title', 'custom-fields' ],
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
	] );
} );

/**
 * Lead ID + email/phone columns on the Care Leads list table — staff need
 * these to find the right ecm_lead ID when logging a Rights@ request
 * (inc/rights-queue.php's "Related request" field), and there was
 * previously no way to see the ID at all without opening each lead.
 */
add_filter( 'manage_ecm_lead_posts_columns', function ( $columns ) {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['ecm_lead_id'] = 'Lead ID';
			$new['ecm_email']   = 'Email';
			$new['ecm_phone']   = 'Phone';
		}
	}
	return $new;
} );

add_action( 'manage_ecm_lead_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'ecm_lead_id':
			echo '<strong>' . esc_html( $post_id ) . '</strong>';
			break;
		case 'ecm_email':
			echo esc_html( get_post_meta( $post_id, '_ecm_email', true ) ?: '—' );
			break;
		case 'ecm_phone':
			echo esc_html( get_post_meta( $post_id, '_ecm_phone', true ) ?: '—' );
			break;
	}
}, 10, 2 );
