<?php
/**
 * Settings — account details + notification preferences (real, secure save).
 * Persists via admin_post_pd_save_settings (nonce + capability).
 *
 * "Your Plan" shows real entitlements from the user's active WooCommerce
 * Subscription product (WP Job Manager Listing Payments meta) — replaces the
 * old "Lead Preferences" UI, which referenced an LQS/LIS scoring system that
 * doesn't exist anywhere in this install.
 */

defined( 'ABSPATH' ) || exit;

$uid   = get_current_user_id();
$user  = wp_get_current_user();
$prefs = wp_parse_args( (array) get_user_meta( $uid, 'pd_prefs', true ), [
	'notify_lead' => 1, 'notify_purchase' => 1, 'notify_message' => 1, 'notify_billing' => 0, 'notify_updates' => 1,
] );
$plan_products = pd_get_active_plan_products( $uid );

$notice = isset( $_GET['pd_notice'] ) ? sanitize_key( $_GET['pd_notice'] ) : '';

$pd_toggle = function ( $name, $label, $desc, $on ) {
	printf(
		'<div class="setting-row"><div class="setting-info"><div class="setting-label">%s</div><div class="setting-desc">%s</div></div>'
		. '<label class="toggle"><input type="checkbox" name="%s" value="1" %s><span class="toggle-slider"></span></label></div>',
		esc_html( $label ), esc_html( $desc ), esc_attr( $name ), checked( $on, 1, false )
	);
};
?>
<div class="dash-main__header">
	<div>
		<div class="dash-main__title">Settings</div>
		<div class="dash-main__sub">Manage notifications, your plan, and account details</div>
	</div>
</div>

<div class="dash-main__body">

	<?php if ( 'settings_saved' === $notice ) : ?>
		<div class="pd-notice pd-notice--success">✓ Settings saved.</div>
	<?php elseif ( 'password_changed' === $notice ) : ?>
		<div class="pd-notice pd-notice--success">✓ Settings saved. Your password was changed — please sign in again.</div>
	<?php elseif ( 'password_error' === $notice ) : ?>
		<div class="pd-notice pd-notice--error">New passwords didn't match or were under 8 characters. Other settings were saved.</div>
	<?php elseif ( 'current_password_wrong' === $notice ) : ?>
		<div class="pd-notice pd-notice--error">Current password was incorrect — password was not changed. Other settings were saved.</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="pd_save_settings">
		<?php wp_nonce_field( 'pd_save_settings' ); ?>

		<!-- Notifications -->
		<div class="settings-section">
			<div class="settings-section__title">Notifications</div>
			<?php
			$pd_toggle( 'notify_lead', 'New lead available', 'Get notified when a new geo-matched lead appears in your inbox.', $prefs['notify_lead'] );
			$pd_toggle( 'notify_purchase', 'Lead purchased confirmation', 'Receive a confirmation email when you successfully purchase a lead.', $prefs['notify_purchase'] );
			$pd_toggle( 'notify_message', 'Consumer message received', "Alert when a family you've contacted sends a message through ECM.", $prefs['notify_message'] );
			$pd_toggle( 'notify_billing', 'Monthly billing summary', 'Receive a monthly email summarising your spend and ROI.', $prefs['notify_billing'] );
			$pd_toggle( 'notify_updates', 'ECM platform updates', 'News about new features, improvements, and best practices.', $prefs['notify_updates'] );
			?>
		</div>

		<!-- Your Plan (real WooCommerce Subscription + Job Manager Listing Payments data) -->
		<div class="settings-section">
			<div class="settings-section__title">Your Plan</div>
			<?php if ( ! $plan_products ) : ?>
				<p style="font-size:0.85rem;color:var(--gray-500);margin:0 0 12px;">No active plan.</p>
				<a href="<?php echo esc_url( home_url( '/for-providers/' ) ); ?>" class="btn--secondary btn--sm">View Plans →</a>
			<?php else : foreach ( $plan_products as $product ) :
				$featured = method_exists( $product, 'get_job_listing_featured' ) && 'yes' === $product->get_job_listing_featured();
				$limit    = method_exists( $product, 'get_job_listing_limit' ) ? $product->get_job_listing_limit() : '';
				$duration = method_exists( $product, 'get_job_listing_duration' ) ? $product->get_job_listing_duration() : '';
			?>
				<div class="setting-row">
					<div class="setting-info">
						<div class="setting-label"><?php echo esc_html( $product->get_name() ); ?></div>
						<div class="setting-desc">
							<?php echo esc_html( strip_tags( wc_price( $product->get_price() ) ) ); ?>
							· <?php echo $featured ? 'Featured listing' : 'Premium listing'; ?>
							<?php if ( $limit ) : ?> · <?php echo esc_html( $limit ); ?> listing<?php echo (int) $limit !== 1 ? 's' : ''; ?><?php endif; ?>
							<?php echo $duration ? ' · ' . esc_html( $duration ) . '-day duration' : ' · Active while subscribed'; ?>
						</div>
					</div>
				</div>
			<?php endforeach; endif; ?>
		</div>

		<!-- Account -->
		<div class="settings-section">
			<div class="settings-section__title">Account</div>
			<div class="form-grid" style="max-width:480px;">
				<div class="form-group form-full">
					<label for="display_name">Display Name</label>
					<input id="display_name" name="display_name" type="text" value="<?php echo esc_attr( $user->display_name ); ?>">
				</div>
				<div class="form-group form-full">
					<label for="acc-email">Login Email</label>
					<input id="acc-email" type="email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly style="background:var(--gray-50);color:var(--gray-500);">
				</div>
				<div class="form-group form-full">
					<label for="current_password">Current Password</label>
					<input id="current_password" name="current_password" type="password" placeholder="Required to change your password" autocomplete="current-password">
				</div>
				<div class="form-group form-full">
					<label for="new_password">New Password</label>
					<input id="new_password" name="new_password" type="password" placeholder="Leave blank to keep current" autocomplete="new-password">
				</div>
				<div class="form-group form-full">
					<label for="confirm_password">Confirm Password</label>
					<input id="confirm_password" name="confirm_password" type="password" placeholder="Confirm new password" autocomplete="new-password">
				</div>
			</div>
		</div>

		<div style="margin-top:4px;">
			<button type="submit" class="btn--primary">Save Settings</button>
		</div>
	</form>
</div>
