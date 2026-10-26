<?php
/**
 * My Profile — edit the provider's primary listing (real data, secure save).
 * Business info fields persist via admin_post_pd_save_profile (nonce + ownership).
 * Services/coverage come from the listing's taxonomy/geo and are shown read-only.
 */

defined( 'ABSPATH' ) || exit;

$uid     = get_current_user_id();
$user    = wp_get_current_user();
$listing = pd_get_primary_listing_id( $uid );

$notice = isset( $_GET['pd_notice'] ) ? sanitize_key( $_GET['pd_notice'] ) : '';

// Real values from the primary listing.
$biz_name = $listing ? get_the_title( $listing ) : '';
$desc     = $listing ? get_post_field( 'post_content', $listing ) : '';
$email    = $listing ? get_post_meta( $listing, '_application', true ) : '';
if ( ! $email ) { $email = $user->user_email; }
$phone    = $listing ? get_post_meta( $listing, '_phone', true ) : '';
$website  = $listing ? get_post_meta( $listing, '_company_website', true ) : '';
$city     = $listing ? get_post_meta( $listing, 'geolocation_city', true ) : '';
$state    = $listing ? get_post_meta( $listing, 'geolocation_state_short', true ) : '';
$location = trim( $city . ( $state ? ', ' . $state : '' ) );
$is_feat  = $listing && function_exists( 'is_position_featured' ) && is_position_featured( get_post( $listing ) );
$cats     = ( $listing && function_exists( 'wpjm_get_the_job_categories' ) ) ? wpjm_get_the_job_categories( get_post( $listing ) ) : [];
?>
<div class="dash-main__header">
	<div>
		<div class="dash-main__title">My Profile</div>
		<div class="dash-main__sub">Your public listing shown to families on ElderCareMatters</div>
	</div>
</div>

<div class="dash-main__body">

	<?php if ( 'profile_saved' === $notice ) : ?>
		<div class="pd-notice pd-notice--success">✓ Profile saved successfully.</div>
	<?php endif; ?>

	<?php
	$pd_draft_ids = $listing ? [] : pd_get_my_listing_ids( $uid );
	$pd_draft_id  = $pd_draft_ids ? $pd_draft_ids[0] : 0;
	?>
	<?php if ( ! $listing && $pd_draft_id ) : ?>
		<div class="profile-section">
			<p>Your listing isn't published yet. <a href="<?php echo esc_url( pd_get_listing_edit_url( $pd_draft_id ) ); ?>">Continue your listing</a> to set up your public profile.</p>
		</div>
	<?php elseif ( ! $listing ) : ?>
		<div class="profile-section">
			<p>You don't have a published listing yet. <a href="<?php echo esc_url( pd_get_add_listing_url() ); ?>">Create your listing</a> to set up your public profile.</p>
		</div>
	<?php else : ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pd-form">
		<input type="hidden" name="action" value="pd_save_profile">
		<input type="hidden" name="listing_id" value="<?php echo esc_attr( $listing ); ?>">
		<?php wp_nonce_field( 'pd_save_profile' ); ?>

		<!-- Verification -->
		<div class="profile-section">
			<div class="profile-section__title">Verification Status</div>
			<div class="badge-row">
				<?php if ( $is_feat ) : ?>
					<span class="badge-ecm">✓ Featured Listing</span>
				<?php endif; ?>
				<span style="font-size:0.82rem;color:var(--gray-600);">Editing your primary listing: <strong><?php echo esc_html( $biz_name ); ?></strong></span>
			</div>
		</div>

		<!-- Business Information (editable) -->
		<div class="profile-section">
			<div class="profile-section__title">Business Information</div>
			<div class="form-grid">
				<div class="form-group">
					<label for="biz-name">Business Name</label>
					<input id="biz-name" name="biz_name" type="text" value="<?php echo esc_attr( $biz_name ); ?>" required>
				</div>
				<div class="form-group">
					<label for="contact-name">Primary Contact</label>
					<input id="contact-name" type="text" value="<?php echo esc_attr( $user->display_name ); ?>" readonly style="background:var(--gray-50);color:var(--gray-500);">
				</div>
				<div class="form-group">
					<label for="biz-email">Public Email</label>
					<input id="biz-email" name="biz_email" type="email" value="<?php echo esc_attr( $email ); ?>">
				</div>
				<div class="form-group">
					<label for="biz-phone">Phone Number</label>
					<input id="biz-phone" name="biz_phone" type="tel" value="<?php echo esc_attr( $phone ); ?>">
				</div>
				<div class="form-group">
					<label for="biz-web">Website</label>
					<input id="biz-web" name="biz_website" type="url" value="<?php echo esc_attr( $website ); ?>">
				</div>
				<div class="form-group form-full">
					<label for="biz-desc">Business Description</label>
					<textarea id="biz-desc" name="biz_desc" rows="5"><?php echo esc_textarea( $desc ); ?></textarea>
				</div>
			</div>
		</div>

		<!-- Services & Coverage (read-only — managed via your listing) -->
		<div class="profile-section">
			<div class="profile-section__title">Services &amp; Coverage</div>
			<div class="form-grid">
				<div class="form-group">
					<label>Primary Location</label>
					<input type="text" value="<?php echo esc_attr( $location ?: '—' ); ?>" readonly style="background:var(--gray-50);color:var(--gray-500);">
				</div>
				<div class="form-group form-full">
					<label>Service Categories</label>
					<div class="service-tags">
						<?php if ( $cats ) : foreach ( $cats as $cat ) : ?>
							<span class="service-tag"><?php echo esc_html( $cat->name ); ?></span>
						<?php endforeach; else : ?>
							<span style="font-size:0.82rem;color:var(--gray-500);">No categories set.</span>
						<?php endif; ?>
					</div>
					<p style="font-size:0.78rem;color:var(--gray-500);margin-top:8px;">Categories &amp; location are managed on your listing. <a href="<?php echo esc_url( pd_get_listing_edit_url( $listing ) ); ?>">Edit listing →</a></p>
				</div>
			</div>
			<div style="margin-top:16px;">
				<button type="submit" class="btn--primary">Save Profile</button>
			</div>
		</div>
	</form>

	<?php endif; ?>
</div>
