<?php
/**
 * Email verification for the request form — checklist items 14-16.
 *
 * The family verifies their email on the contact step: POST /email/start emails
 * a 6-digit code, POST /email/verify checks it. The final submit
 * (POST /lead/submit) only creates a lead for an email that has been verified,
 * so no request row, consent record or provider distribution exists before the
 * correct code is entered. The old direct POST /lead route is removed so it
 * can't bypass verification.
 *
 * All user-facing wording lives in ecm_lead_verification_messages() and is
 * PLACEHOLDER copy until section 10.3 of the Privacy Compliance spec is in hand.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_LEAD_VERIFIED_TTL      = 30 * MINUTE_IN_SECONDS; // how long a verified email may be used to submit
const ECM_LEAD_MAX_ATTEMPTS      = 5;                      // wrong codes before the code is replaced (item 16)
const ECM_LEAD_MAX_SENDS_IP      = 15;                     // per hour, backstop per connection

/** Admin-editable limits (Care Leads > Email Verification). Defaults match the checklist (items 15-16) and the plumlabs-2fa plugin. */
const ECM_LEAD_SETTINGS_OPTION = 'ecm_lead_ev_settings';

function ecm_lead_setting_defaults() : array {
	return [ 'code_minutes' => 10, 'resend_seconds' => 60, 'max_sends' => 3, 'lock_minutes' => 15, 'remember_days' => 30, 'require_verified' => 0 ];
}

function ecm_lead_setting_bounds() : array {
	return [ 'code_minutes' => [ 1, 60 ], 'resend_seconds' => [ 10, 600 ], 'max_sends' => [ 1, 10 ], 'lock_minutes' => [ 1, 1440 ], 'remember_days' => [ 0, 365 ], 'require_verified' => [ 0, 1 ] ];
}

/** Tunable limits in seconds. Stored values come from the admin page; the ecm_lead_verification_setting filter can still override. */
function ecm_lead_setting( string $name ) : int {
	$saved  = (array) get_option( ECM_LEAD_SETTINGS_OPTION, [] );
	$values = array_merge( ecm_lead_setting_defaults(), array_intersect_key( $saved, ecm_lead_setting_defaults() ) );
	$map    = [
		'code_ttl'         => (int) $values['code_minutes'] * MINUTE_IN_SECONDS, // item 15: code expires after 10 minutes by default
		'resend_cooldown'  => (int) $values['resend_seconds'],
		'max_sends'        => (int) $values['max_sends'],
		'lock_seconds'     => (int) $values['lock_minutes'] * MINUTE_IN_SECONDS,
		'remember_seconds' => (int) $values['remember_days'] * DAY_IN_SECONDS,
		'require_verified' => (int) $values['require_verified'], // 0: email is optional and verification can be skipped
	];
	return (int) apply_filters( 'ecm_lead_verification_setting', $map[ $name ] ?? 0, $name );
}

/** How long a pending record is kept: the code lifetime plus 20 minutes, so "expired" can be told apart from "unknown". */
function ecm_lead_pending_ttl() : int {
	return ecm_lead_setting( 'code_ttl' ) + 20 * MINUTE_IN_SECONDS;
}

/**
 * User-facing strings. Entries marked [10.3] are the exact wording from section
 * 10.3 of Privacy Compliance - Developer Requirements; the rest are not in the
 * spec and are placeholders (flagged as such).
 */
function ecm_lead_verification_messages() : array {
	return apply_filters( 'ecm_lead_verification_messages', [
		// [10.3] Verification code, email subject / body.
		'email_subject' => 'Your ElderCareMatters code is {code}',
		'email_body'    => 'Your ElderCareMatters verification code is {code}. It expires in 10 minutes. We are confirming the request you just sent for {category} in {city}. If you did not make this request, ignore this email and nothing happens.',
		// [10.3] Code entry screen label / help text (used by the form).
		'entry_label'   => 'Enter the 6-digit code we emailed to {email}.',
		'entry_help'    => "Didn't get it? Check your spam folder, or send a new code.",
		// [10.3] Wrong code / expired code.
		'wrong'         => "That code doesn't match. Check the email and try again.",
		'expired'       => 'That code expired. We have sent you a new one.',
		// Not in 10.3 — placeholders.
		'locked'        => "Too many incorrect attempts. We've sent a new code to your email.",
		'locked_timer'  => 'This email address has tried too many times. Please try again in %1$s (at %2$s).',
		'cooldown'      => 'Please wait a moment before requesting another code.',
		'rate_limited'  => 'Too many requests. Please wait a while and try again.',
		'send_failed'   => "We couldn't send the email. Please check the address and try again.",
		'invalid_email' => 'Please enter a valid email address.',
		'missing'       => 'Required fields missing.',
		'unknown'       => 'This verification request was not found. Please start again.',
		'unverified'    => 'Please verify your email address to continue.',
	] );
}

function ecm_lead_msg( string $key ) : string {
	$m = ecm_lead_verification_messages();
	return $m[ $key ] ?? '';
}

// The old route creates a lead with no verification, so take it out of the REST index entirely.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	unset( $endpoints['/ecm/v1/lead'] );
	return $endpoints;
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'ecm/v1', '/email/start', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'ecm_email_start',
		'permission_callback' => '__return_true',
		'args'                => [
			'email'    => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
			'category' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'city'     => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );
	register_rest_route( 'ecm/v1', '/email/verify', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'ecm_email_verify',
		'permission_callback' => '__return_true',
		'args'                => [
			'token' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'code'  => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );
	register_rest_route( 'ecm/v1', '/email/resend', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'ecm_email_resend',
		'permission_callback' => '__return_true',
		'args'                => [
			'token' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );
	register_rest_route( 'ecm/v1', '/email/restore', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'ecm_email_restore',
		'permission_callback' => '__return_true',
		'args'                => [
			'email'    => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
			'remember' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );
	register_rest_route( 'ecm/v1', '/lead/submit', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'ecm_lead_submit_verified',
		'permission_callback' => '__return_true',
		'args'                => [
			'token'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'careType'    => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'relationship' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'firstName'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'lastName'    => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'city'        => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'state'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'urgency'     => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'email'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
			'phone'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'description' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
			'pickedProviderIds' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'default' => [] ],
			'expeditedOptIn'    => [ 'type' => 'boolean', 'default' => false ],
		],
	] );
} );

function ecm_lead_error( string $reason, int $status, array $extra = [] ) : WP_Error {
	return new WP_Error( 'ecm_lead_' . $reason, ecm_lead_msg( $reason ), array_merge( [ 'status' => $status, 'reason' => $reason ], $extra ) );
}

function ecm_lead_pending_key( string $token ) : string {
	return 'ecm_lead_pending_' . preg_replace( '/[^a-f0-9]/', '', strtolower( $token ) );
}

function ecm_lead_hash_code( string $token, string $code ) : string {
	return hash_hmac( 'sha256', $code, wp_salt( 'auth' ) . $token );
}

/** Counts a send against a rolling-hour limit. Returns false when the limit is already reached. */
function ecm_lead_rate_hit( string $bucket, int $max ) : bool {
	$key   = 'ecm_lead_rl_' . md5( $bucket );
	$state = get_transient( $key );
	if ( ! is_array( $state ) ) {
		$state = [ 'n' => 0, 'until' => time() + HOUR_IN_SECONDS ];
	}
	if ( $state['n'] >= $max ) {
		return false;
	}
	$state['n']++;
	set_transient( $key, $state, max( 1, $state['until'] - time() ) );
	return true;
}

function ecm_lead_email_state_key( string $email ) : string {
	return 'ecm_lead_es_' . md5( strtolower( $email ) );
}

function ecm_lead_human_duration( int $seconds ) : string {
	$minutes = max( 1, (int) ceil( $seconds / 60 ) );
	if ( $minutes > 90 ) {
		return sprintf( '%d hours', (int) ceil( $minutes / 60 ) );
	}
	return 1 === $minutes ? '1 minute' : sprintf( '%d minutes', $minutes );
}

/** Error carrying the lock countdown so the form can show a live timer. */
function ecm_lead_lock_error( int $locked_until ) : WP_Error {
	$seconds = max( 1, $locked_until - time() );
	$at      = wp_date( (string) get_option( 'time_format', 'g:i a' ), $locked_until );
	return new WP_Error( 'ecm_lead_locked', sprintf( ecm_lead_msg( 'locked_timer' ), ecm_lead_human_duration( $seconds ), $at ), [
		'status'    => 429,
		'reason'    => 'locked_timer',
		'locked'    => true,
		'unlock_in' => $seconds,
		'unlock_at' => $at,
	] );
}

/** Returns an active lock error for the email, or null. */
function ecm_lead_active_lock( string $email ) : ?WP_Error {
	$state = get_transient( ecm_lead_email_state_key( $email ) );
	if ( is_array( $state ) && ! empty( $state['locked_until'] ) && $state['locked_until'] > time() ) {
		return ecm_lead_lock_error( (int) $state['locked_until'] );
	}
	return null;
}

/**
 * Counts a code send against the email: after max_sends in a window the email
 * is locked for lock_seconds. Returns true when allowed, or a WP_Error (lock / rate limit).
 */
function ecm_lead_send_allowed( string $email ) {
	$lock = ecm_lead_active_lock( $email );
	if ( $lock ) {
		return $lock;
	}
	$key   = ecm_lead_email_state_key( $email );
	$state = get_transient( $key );
	if ( ! is_array( $state ) || ( ! empty( $state['locked_until'] ) && $state['locked_until'] <= time() ) ) {
		$state = [ 'sends' => 0, 'locked_until' => 0 ]; // fresh window, or a lock that has run out
	}
	if ( $state['sends'] >= ecm_lead_setting( 'max_sends' ) ) {
		$state['locked_until'] = time() + ecm_lead_setting( 'lock_seconds' );
		set_transient( $key, $state, ecm_lead_setting( 'lock_seconds' ) + MINUTE_IN_SECONDS );
		return ecm_lead_lock_error( $state['locked_until'] );
	}
	$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
	if ( ! ecm_lead_rate_hit( 'ip:' . $ip, ECM_LEAD_MAX_SENDS_IP ) ) {
		return ecm_lead_error( 'rate_limited', 429 );
	}
	$state['sends']++;
	set_transient( $key, $state, HOUR_IN_SECONDS );
	return true;
}

function ecm_lead_send_code( string $email, string $code, array $vars = [] ) : bool {
	$replace = [
		'{code}'     => $code,
		'{category}' => $vars['category'] ?? '' ?: 'care',
		'{city}'     => $vars['city'] ?? '' ?: 'your area',
	];
	return (bool) wp_mail(
		$email,
		strtr( ecm_lead_msg( 'email_subject' ), $replace ),
		strtr( ecm_lead_msg( 'email_body' ), $replace )
	);
}

function ecm_lead_new_code() : string {
	return (string) random_int( 100000, 999999 );
}

/** Signed "this browser verified this email" token (stored in localStorage by the form). Not trusted without the signature. */
function ecm_lead_remember_token( string $email ) : string {
	$payload = rtrim( strtr( base64_encode( wp_json_encode( [ 'e' => strtolower( $email ), 'x' => time() + ecm_lead_setting( 'remember_seconds' ) ] ) ), '+/', '-_' ), '=' );
	return $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) . 'ecm_remember' );
}

function ecm_lead_remember_valid( string $email, string $token ) : bool {
	$parts = explode( '.', $token );
	if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) . 'ecm_remember' ), $parts[1] ) ) {
		return false;
	}
	$data = json_decode( base64_decode( strtr( $parts[0], '-_', '+/' ) ), true );
	return is_array( $data ) && ( $data['e'] ?? '' ) === strtolower( $email ) && (int) ( $data['x'] ?? 0 ) > time();
}

/** Restores a verified state for an email this browser already verified, so it isn't asked again. */
function ecm_email_restore( WP_REST_Request $request ) {
	$email    = strtolower( (string) ( $request->get_param( 'email' ) ?: '' ) );
	$remember = (string) $request->get_param( 'remember' );
	if ( ! $email || ! ecm_lead_remember_valid( $email, $remember ) ) {
		return ecm_lead_error( 'unverified', 400 );
	}
	$token = bin2hex( random_bytes( 16 ) );
	set_transient( ecm_lead_pending_key( $token ), [
		'email'       => $email,
		'verified'    => true,
		'verified_at' => time(),
		'expires'     => time(),
		'attempts'    => 0,
		'last_sent'   => 0,
	], ECM_LEAD_VERIFIED_TTL );
	return rest_ensure_response( [ 'success' => true, 'verified' => true, 'token' => $token ] );
}

function ecm_email_start( WP_REST_Request $request ) {
	$email = (string) ( $request->get_param( 'email' ) ?: '' );
	if ( ! $email ) {
		return ecm_lead_error( 'invalid_email', 400 );
	}
	if ( ! function_exists( 'ecm_is_valid_public_email' ) || ! ecm_is_valid_public_email( $email ) ) {
		return ecm_lead_error( 'invalid_email', 400 );
	}
	$allowed = ecm_lead_send_allowed( $email );
	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}

	$token = bin2hex( random_bytes( 16 ) );
	$code  = ecm_lead_new_code();
	$now   = time();
	set_transient( ecm_lead_pending_key( $token ), [
		'email'     => strtolower( $email ),
		'code_hash' => ecm_lead_hash_code( $token, $code ),
		'expires'   => $now + ecm_lead_setting( 'code_ttl' ),
		'attempts'  => 0,
		'last_sent' => $now,
		'verified'  => false,
		'category'  => (string) $request->get_param( 'category' ),
		'city'      => (string) $request->get_param( 'city' ),
	], ecm_lead_pending_ttl() );

	if ( ! ecm_lead_send_code( $email, $code, [ 'category' => (string) $request->get_param( 'category' ), 'city' => (string) $request->get_param( 'city' ) ] ) ) {
		delete_transient( ecm_lead_pending_key( $token ) );
		return ecm_lead_error( 'send_failed', 500 );
	}

	return rest_ensure_response( [
		'success'    => true,
		'token'      => $token,
		'expires_in' => ecm_lead_setting( 'code_ttl' ),
		'resend_in'  => ecm_lead_setting( 'resend_cooldown' ),
	] );
}

/** Issues a fresh code on an existing record: resets attempts and the 10-minute clock. */
function ecm_lead_reissue( string $token, array $rec ) {
	$allowed = ecm_lead_send_allowed( $rec['email'] );
	if ( is_wp_error( $allowed ) ) {
		return $allowed; // keep the record so the lock countdown still shows on later attempts
	}
	$code             = ecm_lead_new_code();
	$rec['code_hash'] = ecm_lead_hash_code( $token, $code );
	$rec['expires']   = time() + ecm_lead_setting( 'code_ttl' );
	$rec['attempts']  = 0;
	$rec['last_sent'] = time();
	set_transient( ecm_lead_pending_key( $token ), $rec, ecm_lead_pending_ttl() );
	if ( ! ecm_lead_send_code( $rec['email'], $code, [ 'category' => $rec['category'] ?? '', 'city' => $rec['city'] ?? '' ] ) ) {
		return ecm_lead_error( 'send_failed', 500 );
	}
	return true;
}

function ecm_email_verify( WP_REST_Request $request ) {
	$token = (string) $request->get_param( 'token' );
	$rec   = get_transient( ecm_lead_pending_key( $token ) );
	if ( ! is_array( $rec ) ) {
		return ecm_lead_error( 'unknown', 400 );
	}
	if ( ! empty( $rec['verified'] ) ) {
		return rest_ensure_response( [ 'success' => true, 'verified' => true ] );
	}
	$lock = ecm_lead_active_lock( $rec['email'] );
	if ( $lock ) {
		return $lock;
	}
	if ( time() > $rec['expires'] ) {
		// [10.3] "That code expired. We have sent you a new one." — so send one.
		$reissued = ecm_lead_reissue( $token, $rec );
		if ( is_wp_error( $reissued ) ) {
			return $reissued;
		}
		return ecm_lead_error( 'expired', 400, [ 'reissued' => true, 'expires_in' => ecm_lead_setting( 'code_ttl' ), 'resend_in' => ecm_lead_setting( 'resend_cooldown' ) ] );
	}

	$submitted = preg_replace( '/\D/', '', (string) $request->get_param( 'code' ) );
	if ( 6 === strlen( $submitted ) && hash_equals( $rec['code_hash'], ecm_lead_hash_code( $token, $submitted ) ) ) {
		$rec['verified']    = true;
		$rec['verified_at'] = time();
		unset( $rec['code_hash'] );
		set_transient( ecm_lead_pending_key( $token ), $rec, ECM_LEAD_VERIFIED_TTL );
		return rest_ensure_response( [ 'success' => true, 'verified' => true, 'remember' => ecm_lead_remember_token( $rec['email'] ) ] );
	}

	$rec['attempts']++;
	if ( $rec['attempts'] >= ECM_LEAD_MAX_ATTEMPTS ) {
		$reissued = ecm_lead_reissue( $token, $rec ); // item 16: five wrong attempts, then a new code
		if ( is_wp_error( $reissued ) ) {
			return $reissued;
		}
		return ecm_lead_error( 'locked', 400, [ 'reissued' => true, 'attempts_left' => ECM_LEAD_MAX_ATTEMPTS, 'expires_in' => ecm_lead_setting( 'code_ttl' ), 'resend_in' => ecm_lead_setting( 'resend_cooldown' ) ] );
	}
	set_transient( ecm_lead_pending_key( $token ), $rec, ecm_lead_pending_ttl() ); // code expiry is governed by $rec['expires'], not the storage TTL
	return ecm_lead_error( 'wrong', 400, [ 'attempts_left' => ECM_LEAD_MAX_ATTEMPTS - $rec['attempts'], 'expires_in' => max( 0, $rec['expires'] - time() ) ] );
}

function ecm_email_resend( WP_REST_Request $request ) {
	$token = (string) $request->get_param( 'token' );
	$rec   = get_transient( ecm_lead_pending_key( $token ) );
	if ( ! is_array( $rec ) ) {
		return ecm_lead_error( 'unknown', 400 );
	}
	if ( ! empty( $rec['verified'] ) ) {
		return rest_ensure_response( [ 'success' => true, 'verified' => true ] );
	}
	$lock = ecm_lead_active_lock( $rec['email'] );
	if ( $lock ) {
		return $lock;
	}
	$wait = ecm_lead_setting( 'resend_cooldown' ) - ( time() - $rec['last_sent'] );
	if ( $wait > 0 ) {
		return ecm_lead_error( 'cooldown', 429, [ 'retry_after' => $wait ] );
	}
	$reissued = ecm_lead_reissue( $token, $rec );
	if ( is_wp_error( $reissued ) ) {
		return $reissued;
	}
	return rest_ensure_response( [ 'success' => true, 'expires_in' => ecm_lead_setting( 'code_ttl' ), 'resend_in' => ecm_lead_setting( 'resend_cooldown' ) ] );
}

/**
 * Final submit. The email is required; verifying it is optional. By
 * default a request whose email was not verified is accepted and flagged as
 * unverified (verified emails get priority response). When the "require a
 * verified email" setting is on, only a verified email creates a request.
 * A verified token is single use.
 */
function ecm_lead_submit_verified( WP_REST_Request $request ) {
	$token = (string) $request->get_param( 'token' );
	$email = strtolower( trim( (string) ( $request->get_param( 'email' ) ?: '' ) ) );
	$rec   = $token ? get_transient( ecm_lead_pending_key( $token ) ) : null;

	$verified = is_array( $rec ) && ! empty( $rec['verified'] ) && '' !== $email && $rec['email'] === $email
		&& ( time() - (int) ( $rec['verified_at'] ?? 0 ) ) <= ECM_LEAD_VERIFIED_TTL;

	if ( ! $request->get_param( 'firstName' ) || ! $request->get_param( 'careType' ) ) {
		return ecm_lead_error( 'missing', 400 );
	}

	if ( ! $verified ) {
		if ( ecm_lead_setting( 'require_verified' ) ) {
			return ecm_lead_error( 'unverified', 400 );
		}
		// The email is required even when it is not verified.
		if ( '' === $email ) {
			return ecm_lead_error( 'missing', 400 );
		}
		$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
		if ( ! ecm_lead_rate_hit( 'unverified_submit_' . $ip, (int) apply_filters( 'ecm_lead_unverified_hourly_limit', 10 ) ) ) {
			return ecm_lead_error( 'rate_limited', 429 );
		}
		$request->set_param( 'emailVerified', 0 );
		return ecm_handle_lead_submission( $request );
	}

	// Single use: only the request that actually deletes the record may create the lead.
	if ( ! delete_transient( ecm_lead_pending_key( $token ) ) ) {
		return ecm_lead_error( 'unverified', 400 );
	}
	$request->set_param( 'emailVerified', 1 );
	$result = ecm_handle_lead_submission( $request );
	if ( is_wp_error( $result ) ) {
		set_transient( ecm_lead_pending_key( $token ), $rec, ECM_LEAD_VERIFIED_TTL );
	}
	return $result;
}

/** Record mail failures (SMTP errors) so a missing code can be traced. */
add_action( 'wp_mail_failed', static function ( $error ) {
	error_log( '[ECM mail] ' . ( is_wp_error( $error ) ? $error->get_error_message() : 'unknown mail error' ) );
} );
