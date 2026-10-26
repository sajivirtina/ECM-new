<?php
/**
 * Provider registration — styled to match the login card. Creates a subscriber
 * via the secure pd_register handler (nonce + validation + throttle).
 */

defined( 'ABSPATH' ) || exit;

$done = ! empty( $_GET['pd_done'] );
$err  = isset( $_GET['pd_err'] ) ? sanitize_key( $_GET['pd_err'] ) : '';
$errs = [
	'email'  => 'Please enter a valid email address.',
	'exists' => 'An account with that email already exists. Try signing in.',
	'rate'   => 'Too many attempts. Please try again later.',
	'fail'   => 'Something went wrong creating your account. Please try again.',
];
?>
<div class="pd-auth">
	<div class="pd-auth-card">
		<h1 class="pd-auth-title">Create your account</h1>
		<p class="pd-auth-sub">Register to manage your provider listings, profile, and billing.</p>

		<?php if ( $done ) : ?>
			<div class="pd-notice pd-notice--success">✓ Account created. Check your email for a link to set your password, then sign in.</div>
			<p class="pd-auth-foot"><a href="<?php echo esc_url( home_url( '/provider-dashboard/' ) ); ?>">← Back to sign in</a></p>
		<?php else : ?>
			<?php if ( $err && isset( $errs[ $err ] ) ) : ?>
				<div class="pd-notice pd-notice--error"><?php echo esc_html( $errs[ $err ] ); ?></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="custom-login-form">
				<input type="hidden" name="action" value="pd_register">
				<?php wp_nonce_field( 'pd_register' ); ?>
				<div class="form-group">
					<label for="reg-name">Full name</label>
					<input id="reg-name" class="form-control" type="text" name="name" autocomplete="name">
				</div>
				<div class="form-group">
					<label for="reg-email">Email address <span class="required">*</span></label>
					<input id="reg-email" class="form-control" type="email" name="email" required autocomplete="email">
				</div>
				<?php if ( function_exists( 'ecm_assent_sentence_html' ) ) : ?>
				<p class="ecm-form-assent"><?php echo ecm_assent_sentence_html(); // phpcs:ignore -- escaped inside ecm_assent_sentence_html() ?></p>
				<?php endif; ?>
				<button type="submit" class="login-button">Create account</button>
			</form>

			<p class="pd-auth-foot">Already have an account? <a href="<?php echo esc_url( home_url( '/provider-dashboard/' ) ); ?>">Sign in</a></p>
		<?php endif; ?>
	</div>
</div>
