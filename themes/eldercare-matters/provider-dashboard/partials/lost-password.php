<?php
/**
 * Lost password — styled to match the login card. Posts to WordPress core's
 * lostpassword handler (secure, rate-limited, doesn't disclose account existence)
 * and returns to a branded confirmation.
 */

defined( 'ABSPATH' ) || exit;

$sent = ! empty( $_GET['sent'] );
?>
<div class="pd-auth">
	<div class="pd-auth-card">
		<h1 class="pd-auth-title">Reset your password</h1>
		<p class="pd-auth-sub">Enter your account email and we'll send you a reset link.</p>

		<?php if ( $sent ) : ?>
			<div class="pd-notice pd-notice--success">✓ If an account exists for that email, a password reset link is on its way. Check your inbox.</div>
			<p class="pd-auth-foot"><a href="<?php echo esc_url( home_url( '/provider-dashboard/' ) ); ?>">← Back to sign in</a></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( site_url( 'wp-login.php?action=lostpassword', 'login_post' ) ); ?>" class="custom-login-form">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/provider-dashboard/lost-password/?sent=1' ) ); ?>">
				<div class="form-group">
					<label for="lp-login">Username or email <span class="required">*</span></label>
					<input id="lp-login" class="form-control" type="text" name="user_login" required autocomplete="username">
				</div>
				<button type="submit" class="login-button">Send reset link</button>
			</form>
			<p class="pd-auth-foot"><a href="<?php echo esc_url( home_url( '/provider-dashboard/' ) ); ?>">← Back to sign in</a></p>
		<?php endif; ?>
	</div>
</div>
