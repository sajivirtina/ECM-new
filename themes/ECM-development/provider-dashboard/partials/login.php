<?php
/**
 * Logged-out / non-provider state: provider login + register prompt.
 * Uses the existing custom-ajax-login plugin shortcode; no custom auth.
 */

defined( 'ABSPATH' ) || exit;

$pd_redirect  = home_url( '/provider-dashboard/' );
// Both "Register" (logged-out) and the no-listing-yet notice (logged-in)
// send everyone through Plans & Pricing to choose a package first, rather
// than the old shortcut straight into WPJM's own multi-step add-listing
// flow (choose_package= URL param) — that flow turned out to route through
// a confusing "Save Draft" step with no clear "Continue" action, so buying
// a package up front via the normal cart/checkout flow is the more
// standard, less confusing path in.
$pd_plans_url = home_url( '/plans/' ); // Plans & Pricing page
$pd_logged_in = is_user_logged_in();
?>
<div class="pd-auth">
	<div class="pd-auth-card">
		<h1 class="pd-auth-title">Provider Login</h1>
		<p class="pd-auth-sub">Sign in to manage your listings, profile, and billing.</p>

		<?php if ( $pd_logged_in ) :
			$pd_redirect_secs = 5;
			?>
			<div class="pd-auth-notice" style="background:#eef4fb;border:1px solid #cfe0f3;border-radius:12px;padding:20px;color:#374151;line-height:1.6;">
				<p style="margin:0 0 14px;font-size:0.95rem;">
					You're signed in, but this account isn't linked to any provider listings yet.
				</p>
				<p style="margin:0 0 14px;">
					<a href="<?php echo esc_url( $pd_plans_url ); ?>" style="color:#2563eb;font-weight:600;text-decoration:none;">Select a plan →</a>
				</p>
				<p style="margin:0;font-size:0.82rem;color:#6b7280;">
					Redirecting to Plans &amp; Pricing in <span id="pd-redirect-countdown"><?php echo (int) $pd_redirect_secs; ?></span> seconds…
				</p>
			</div>
			<script>
			(function () {
				var seconds = <?php echo (int) $pd_redirect_secs; ?>;
				var el = document.getElementById( 'pd-redirect-countdown' );
				var timer = setInterval( function () {
					seconds--;
					if ( el ) { el.textContent = seconds; }
					if ( seconds <= 0 ) {
						clearInterval( timer );
						window.location.href = <?php echo wp_json_encode( $pd_plans_url ); ?>;
					}
				}, 1000 );
			})();
			</script>
		<?php else : ?>
			<?php echo do_shortcode( '[custom_login_form redirect="' . esc_url( $pd_redirect ) . '"]' ); ?>

			<div class="pd-auth-links">
				<a href="<?php echo esc_url( home_url( '/provider-dashboard/lost-password/' ) ); ?>">Lost your password?</a>
				<span>Don't have an account? <a href="<?php echo esc_url( $pd_plans_url ); ?>">Register</a></span>
			</div>
		<?php endif; ?>
	</div>
</div>
