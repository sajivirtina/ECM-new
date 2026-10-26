<?php
/**
 * Dashboard home. All KPI cards use real data from WP Job Manager and WooCommerce.
 * No hardcoded or sample data is displayed.
 */

defined( 'ABSPATH' ) || exit;

$uid  = get_current_user_id();
$user = wp_get_current_user();
$kpis = pd_get_kpis( $uid );

// Location from the provider's first listing (best-effort, real).
$pd_location = '';
$ids = pd_get_my_listing_ids( $uid );
if ( $ids ) {
	$city  = get_post_meta( $ids[0], 'geolocation_city', true );
	$state = get_post_meta( $ids[0], 'geolocation_state_short', true );
	$pd_location = $city ? trim( $city . ( $state ? ', ' . $state : '' ) ) : '';
}

$pd_hour     = (int) current_time( 'G' );
$pd_greeting = $pd_hour < 12 ? 'Good morning' : ( $pd_hour < 18 ? 'Good afternoon' : 'Good evening' );
?>

<div class="greeting-bar">
	<div class="greeting-text">
		<h2><?php echo esc_html( $pd_greeting . ', ' . $user->display_name ); ?></h2>
		<p>
			<?php echo $pd_location ? esc_html( $pd_location ) . ' · ' : ''; ?>
			<?php echo esc_html( sprintf( _n( '%s active listing', '%s active listings', $kpis['active'], 'eldercare-matters' ), number_format_i18n( $kpis['active'] ) ) ); ?>
		</p>
	</div>
	<div class="greeting-actions">
		<a href="<?php echo esc_url( home_url( '/provider-dashboard/listings/' ) ); ?>" class="btn--primary">My Listings →</a>
	</div>
</div>

<div class="dash-main__body">

	<!-- KPI row — real data from WP Job Manager + WooCommerce -->
	<div class="kpi-row">
		<div class="kpi-card kpi-card--green">
			<div class="kpi-label">Active Listings</div>
			<div class="kpi-value"><?php echo esc_html( number_format_i18n( $kpis['active'] ) ); ?></div>
			<div class="kpi-delta kpi-delta--flat"><?php echo esc_html( number_format_i18n( $kpis['total_listings'] ) ); ?> total</div>
		</div>
		<div class="kpi-card kpi-card--amber">
			<div class="kpi-label">Total Listing Views</div>
			<div class="kpi-value"><?php echo esc_html( number_format_i18n( $kpis['views'] ) ); ?></div>
			<div class="kpi-delta kpi-delta--flat">All-time</div>
		</div>
		<div class="kpi-card kpi-card--teal">
			<div class="kpi-label">Spend (30 days)</div>
			<div class="kpi-value"><?php echo $kpis['spend'] > 0 ? esc_html( strip_tags( wc_price( $kpis['spend'] ) ) ) : '$0'; ?></div>
			<div class="kpi-delta kpi-delta--flat">WooCommerce orders</div>
		</div>
		<div class="kpi-card kpi-card--blue">
			<div class="kpi-label">Total Reviews</div>
			<?php
			// Real review count from WP comments on this provider's listings
			$review_count = $ids ? get_comments( [
				'post__in' => $ids,
				'status'   => 'approve',
				'type'     => 'job_listing_review',
				'count'    => true,
			] ) : 0;
			if ( ! $review_count && $ids ) {
				// Fallback for older Reviews add-on versions
				$review_count = get_comments( [
					'post__in' => $ids,
					'status'   => 'approve',
					'count'    => true,
					'meta_key' => 'rating',
				] );
			}
			?>
			<div class="kpi-value"><?php echo esc_html( number_format_i18n( (int) $review_count ) ); ?></div>
			<div class="kpi-delta kpi-delta--flat"><a href="<?php echo esc_url( home_url( '/provider-dashboard/reviews/' ) ); ?>" style="color:inherit">View reviews →</a></div>
		</div>
	</div>

	<div class="pd-grid-2col" style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

		<!-- Left column: Quick links to real sections -->
		<div>
			<div class="dash-card" style="margin-bottom:20px;">
				<div class="dash-card__header">
					<div class="dash-card__title">Quick Actions</div>
				</div>
				<div class="dash-card__body">
					<div style="display:flex;flex-direction:column;gap:12px;">
						<a href="<?php echo esc_url( home_url( '/provider-dashboard/listings/' ) ); ?>" class="pd-quick-link">
							<span class="pd-quick-link__icon">🏢</span>
							<div><strong>My Listings</strong><p>View and manage your <?php echo esc_html( number_format_i18n( $kpis['total_listings'] ) ); ?> listing<?php echo $kpis['total_listings'] !== 1 ? 's' : ''; ?></p></div>
							<span class="pd-quick-link__arrow">→</span>
						</a>
						<a href="<?php echo esc_url( home_url( '/provider-dashboard/stats/' ) ); ?>" class="pd-quick-link">
							<span class="pd-quick-link__icon">📈</span>
							<div><strong>Stats &amp; Analytics</strong><p><?php echo esc_html( number_format_i18n( $kpis['views'] ) ); ?> total views across your listings</p></div>
							<span class="pd-quick-link__arrow">→</span>
						</a>
						<a href="<?php echo esc_url( home_url( '/provider-dashboard/reviews/' ) ); ?>" class="pd-quick-link">
							<span class="pd-quick-link__icon">⭐</span>
							<div><strong>Reviews</strong><p><?php echo esc_html( number_format_i18n( (int) $review_count ) ); ?> review<?php echo (int) $review_count !== 1 ? 's' : ''; ?> from families</p></div>
							<span class="pd-quick-link__arrow">→</span>
						</a>
						<a href="<?php echo esc_url( home_url( '/provider-dashboard/billing/' ) ); ?>" class="pd-quick-link">
							<span class="pd-quick-link__icon">💳</span>
							<div><strong>Billing</strong><p>View your order history and spend</p></div>
							<span class="pd-quick-link__arrow">→</span>
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- Right column: Recent listings summary -->
		<div>
			<div class="dash-card">
				<div class="dash-card__header">
					<div class="dash-card__title">Recent Listings</div>
					<a href="<?php echo esc_url( home_url( '/provider-dashboard/listings/' ) ); ?>" style="font-size:0.8rem;color:var(--primary-600,#2563eb)">View all</a>
				</div>
				<?php
				$recent_ids = array_slice( $ids ?: [], 0, 5 );
				if ( $recent_ids ) : ?>
				<div class="notif-list">
					<?php foreach ( $recent_ids as $pid ) :
						$status   = get_post_status( $pid );
						$expires  = get_post_meta( $pid, '_job_expires', true );
						$is_exp   = $expires && strtotime( $expires ) < time();
						$badge_cls = 'publish' === $status && ! $is_exp ? 'pd-badge--green' : 'pd-badge--amber';
						$badge_lbl = 'publish' === $status && ! $is_exp ? 'Active' : ucfirst( $status );
					?>
					<div class="notif-item" style="padding:10px 16px;">
						<div class="notif-body" style="min-width:0;">
							<strong style="font-size:0.85rem;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo esc_html( get_the_title( $pid ) ); ?></strong>
							<span class="pd-badge <?php echo esc_attr( $badge_cls ); ?>" style="margin-top:4px;font-size:0.72rem"><?php echo esc_html( $badge_lbl ); ?></span>
						</div>
						<a href="<?php echo esc_url( pd_get_listing_edit_url( $pid ) ); ?>" style="font-size:0.78rem;color:var(--primary-600,#2563eb);white-space:nowrap">Edit →</a>
					</div>
					<?php endforeach; ?>
				</div>
				<?php else : ?>
				<div class="dash-card__body">
					<div class="pd-empty-state pd-empty-state--sm">
						<p>No listings yet. <a href="<?php echo esc_url( pd_get_add_listing_url() ); ?>">Add your first listing →</a></p>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>

	</div><!-- /.pd-grid-2col -->

</div><!-- /.dash-main__body -->
