<?php
/**
 * Common ECM settings page (Settings > ECM Settings).
 *
 * One place for the ECM Verified badge option and the email verification
 * limits. Storage stays where each feature already reads it:
 *  - ecm_verification_show_on_cards (inc/ecm-verification.php)
 *  - ECM_LEAD_SETTINGS_OPTION      (inc/email-verification.php)
 *
 * Layout follows the plumlabs-2fa settings screen: icon header, cards with a
 * titled header bar, rows with text on the left and the control on the right.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['ecm_settings_hook_suffix'] = null;

add_action( 'admin_menu', function () {
	$GLOBALS['ecm_settings_hook_suffix'] = add_options_page( 'ECM Settings', 'ECM Settings', 'manage_options', 'ecm-settings', 'ecm_render_settings_page' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$on_page = ( $GLOBALS['ecm_settings_hook_suffix'] && $hook === $GLOBALS['ecm_settings_hook_suffix'] )
		|| ( 'options-general.php' === ( $GLOBALS['pagenow'] ?? '' ) && 'ecm-settings' === ( $_GET['page'] ?? '' ) );
	if ( ! $on_page ) {
		return;
	}
	$css_path = get_template_directory() . '/assets/css/ecm-settings-admin.css';
	wp_enqueue_style( 'ecm-settings-admin', get_template_directory_uri() . '/assets/css/ecm-settings-admin.css', [], file_exists( $css_path ) ? filemtime( $css_path ) : '1.0' );
} );

function ecm_render_settings_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}

	$saved = false;
	$mail_result = '';
	if ( isset( $_POST['ecm_test_mail_nonce'] ) && wp_verify_nonce( $_POST['ecm_test_mail_nonce'], 'ecm_test_mail' ) ) {
		$to = sanitize_email( wp_unslash( $_POST['test_mail_to'] ?? '' ) );
		if ( ! is_email( $to ) ) {
			$mail_result = 'Enter a valid email address.';
		} else {
			$mail_error = '';
			$catch      = static function ( $error ) use ( &$mail_error ) {
				$mail_error = is_wp_error( $error ) ? $error->get_error_message() : 'unknown error';
			};
			add_action( 'wp_mail_failed', $catch );
			$sent = wp_mail( $to, 'ElderCareMatters test email', "This is a test email from Settings > ECM Settings.\n\nIf you can read this, the site can send mail." );
			remove_action( 'wp_mail_failed', $catch );
			$mail_result = $sent
				? 'wp_mail() accepted the message for ' . $to . '. If it does not arrive, check the spam folder and the mail provider log: the message left WordPress.'
				: 'Sending failed: ' . ( $mail_error ?: 'wp_mail() returned false' );
		}
	}
	if ( isset( $_POST['ecm_settings_nonce'] ) && wp_verify_nonce( $_POST['ecm_settings_nonce'], 'ecm_settings_save' ) ) {
		update_option( 'ecm_verification_show_on_cards', ! empty( $_POST['show_on_cards'] ) ? '1' : '0' );

		$clean = [];
		foreach ( ecm_lead_setting_bounds() as $key => [ $min, $max ] ) {
			$clean[ $key ] = min( $max, max( $min, (int) ( $_POST[ $key ] ?? ecm_lead_setting_defaults()[ $key ] ) ) );
		}
		$clean['require_verified'] = ! empty( $_POST['require_verified'] ) ? 1 : 0;
		update_option( ECM_LEAD_SETTINGS_OPTION, $clean );
		$saved = true;
	}

	$values = array_merge( ecm_lead_setting_defaults(), array_intersect_key( (array) get_option( ECM_LEAD_SETTINGS_OPTION, [] ), ecm_lead_setting_defaults() ) );
	$fields = [
		'code_minutes'   => [ 'Code expiry', 'How long a sent code stays valid before the family has to request a new one (checklist item 15: 10 minutes).', 'minutes' ],
		'resend_seconds' => [ 'Resend cooldown', 'How long the family waits before "Send a new code" becomes available again.', 'seconds' ],
		'max_sends'      => [ 'Codes per email before a lock', 'Codes allowed per email, including the automatic new code after 5 wrong attempts, before that email is locked.', 'codes' ],
		'lock_minutes'   => [ 'Automatic unlock after', 'How long a locked email must wait. The form shows a live countdown, then unlocks by itself.', 'minutes' ],
		'remember_days'  => [ 'Remember a verified email', 'The same email on the same browser skips the code for this long. Enter 0 to turn this off.', 'days' ],
	];
	?>
	<div class="wrap ecm-settings-wrap">
		<div class="ecm-settings-header">
			<span class="dashicons dashicons-admin-settings"></span>
			<div>
				<h1>ECM Settings</h1>
				<p>Options for the ECM Verified badge and email verification on the request form.</p>
			</div>
		</div>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'ecm_settings_save', 'ecm_settings_nonce' ); ?>

			<div class="ecm-settings-card">
				<div class="ecm-settings-card-header">
					<span class="dashicons dashicons-awards"></span>
					<h2>ECM Verified badge</h2>
				</div>
				<div class="ecm-settings-card-body">
					<div class="ecm-settings-row">
						<div class="ecm-settings-row-text">
							<span class="ecm-settings-row-title">Show on Find Care result cards</span>
							<p>Off by default. The badge always shows on the listing page itself, for Featured listings only. Manage checks under <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=ecm-verification' ) ); ?>">Listings &rarr; ECM Verification</a>.</p>
						</div>
						<div class="ecm-settings-row-control">
							<label class="ecm-settings-switch">
								<input type="checkbox" name="show_on_cards" value="1" <?php checked( ecm_verification_cards_enabled() ); ?> />
								<span class="ecm-settings-switch-track"></span>
							</label>
						</div>
					</div>
				</div>
			</div>

			<div class="ecm-settings-card">
				<div class="ecm-settings-card-header">
					<span class="dashicons dashicons-email-alt"></span>
					<h2>Email verification</h2>
				</div>
				<div class="ecm-settings-card-body">
						<div class="ecm-settings-row">
							<div class="ecm-settings-row-text">
								<span class="ecm-settings-row-title">Require a verified email</span>
								<p>Off by default: the email is optional and families can skip verification (verified emails get priority response). Turn on to refuse any request without a verified email.</p>
							</div>
							<div class="ecm-settings-row-control">
								<label class="ecm-settings-switch">
									<input type="checkbox" name="require_verified" value="1" <?php checked( ! empty( $values['require_verified'] ) ); ?> />
									<span class="ecm-settings-switch-track"></span>
								</label>
							</div>
						</div>
					<?php foreach ( $fields as $key => [ $title, $help, $unit ] ) : [ $min, $max ] = ecm_lead_setting_bounds()[ $key ]; ?>
					<div class="ecm-settings-row">
						<div class="ecm-settings-row-text">
							<span class="ecm-settings-row-title"><?php echo esc_html( $title ); ?></span>
							<p><?php echo esc_html( $help ); ?> Between <?php echo (int) $min; ?> and <?php echo (int) $max; ?>.</p>
						</div>
						<div class="ecm-settings-row-control">
							<div class="ecm-settings-number-control">
								<input type="number" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $values[ $key ] ); ?>" min="<?php echo (int) $min; ?>" max="<?php echo (int) $max; ?>" step="1" />
								<span class="ecm-settings-unit"><?php echo esc_html( $unit ); ?></span>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
					<div class="ecm-settings-row">
						<div class="ecm-settings-row-text">
							<span class="ecm-settings-row-title">Wrong attempts per code</span>
							<p>Fixed by the checklist (item 16): after 5 wrong attempts the code is replaced. The code email and the code-entry, wrong-code and expired-code messages use the exact wording from section 10.3; the lock and rate-limit messages are not in the spec and are placeholders.</p>
						</div>
						<div class="ecm-settings-row-control">
							<div class="ecm-settings-number-control">
								<input type="number" value="5" disabled />
								<span class="ecm-settings-unit">attempts</span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="ecm-settings-save-row">
				<?php submit_button( null, 'primary', 'submit', false ); ?>
			</div>
		</form>
		<form method="post" class="ecm-settings-test-mail">
			<?php wp_nonce_field( 'ecm_test_mail', 'ecm_test_mail_nonce' ); ?>
			<div class="ecm-settings-card">
				<div class="ecm-settings-card-header">
					<span class="dashicons dashicons-email"></span>
					<h2>Test email delivery</h2>
				</div>
				<div class="ecm-settings-card-body">
					<div class="ecm-settings-row">
						<div class="ecm-settings-row-text">
							<span class="ecm-settings-row-title">Send a test email</span>
							<p>Use this when verification codes are not arriving. It sends through the same mail setup as the codes and shows any error.</p>
							<?php if ( $mail_result ) : ?><p><strong><?php echo esc_html( $mail_result ); ?></strong></p><?php endif; ?>
						</div>
						<div class="ecm-settings-row-control">
							<input type="email" name="test_mail_to" placeholder="you@example.com" />
							<?php submit_button( 'Send test', 'secondary', 'send_test', false ); ?>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
	<?php
}
