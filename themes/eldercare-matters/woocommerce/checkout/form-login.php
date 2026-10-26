<?php
/**
 * Checkout login form — ECM theme override.
 *
 * Collapsed by default: shows a single "Sign In" trigger link.
 * Expands inline when the user clicks it. Two-column layout inside:
 * hCaptcha on the left, form fields on the right.
 *
 * Overrides: woocommerce/templates/checkout/form-login.php (v10.0.0)
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) {
	return;
}

$registration_at_checkout   = WC_Checkout::instance()->is_registration_enabled();
$login_reminder_at_checkout = 'yes' === get_option( 'woocommerce_enable_checkout_login_reminder' );

// Always show the section on ECM checkout — subscription products require a real account.
if ( ! $login_reminder_at_checkout && ! $registration_at_checkout ) {
	// Force it on even if the WooCommerce option is off.
}

// Show expanded immediately after a failed login attempt.
$expanded = isset( $_POST['login'] );
?>

<div class="ecm-checkout-login-wrap">

	<div class="ecm-checkout-login-bar">
		<span class="ecm-checkout-login-bar-text">
			<?php esc_html_e( 'Already have an account?', 'eldercare-matters' ); ?>
		</span>
		<button
			type="button"
			class="ecm-checkout-login-trigger"
			aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
			aria-controls="ecm-checkout-login-panel"
		>
			<?php esc_html_e( 'Sign In', 'eldercare-matters' ); ?>
			<svg class="ecm-checkout-login-chevron" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
				<path d="M3 5l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>
	</div>

	<div
		id="ecm-checkout-login-panel"
		class="ecm-checkout-login-panel"
		<?php if ( ! $expanded ) echo 'hidden'; ?>
	>
		<div class="ecm-checkout-login-panel-inner">
			<div class="ecm-checkout-login-header">
				<h2 class="ecm-checkout-login-title">
					<?php esc_html_e( 'Sign in to your account', 'eldercare-matters' ); ?>
				</h2>
				<p class="ecm-checkout-login-sub">
					<?php esc_html_e( 'Your billing details will be pre-filled and checkout will be faster.', 'eldercare-matters' ); ?>
				</p>
			</div>

			<?php
			woocommerce_login_form(
				array(
					'message'  => '',
					'redirect' => wc_get_checkout_url(),
					'hidden'   => false,
				)
			);
			?>
		</div>
	</div>

</div>

<script>
(function () {
	var trigger = document.querySelector('.ecm-checkout-login-trigger');
	var panel   = document.getElementById('ecm-checkout-login-panel');
	if (!trigger || !panel) return;

	trigger.addEventListener('click', function () {
		var open = this.getAttribute('aria-expanded') === 'true';
		this.setAttribute('aria-expanded', open ? 'false' : 'true');
		if (open) {
			panel.hidden = true;
		} else {
			panel.hidden = false;
			var first = panel.querySelector('input:not([type="hidden"])');
			if (first) setTimeout(function () { first.focus(); }, 50);
		}
	});
})();
</script>
