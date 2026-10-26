<?php
/**
 * M4 — Stats. Real per-listing view/click data from WP Job Manager Stats add-on.
 * Meta keys: _wpjms_visits_total, _wpjms_apply_button_click_total (verified
 * against the plugin's actual stat_id — 'apply_button_click' — not the
 * generic '_wpjms_clicks_total' name previously assumed here, which the
 * plugin never actually writes).
 */

defined( 'ABSPATH' ) || exit;

$uid  = get_current_user_id();
$ids  = pd_get_my_listing_ids( $uid );

// Gather per-listing stats
$stats      = [];
$total_views  = 0;
$total_clicks = 0;

foreach ( $ids as $pid ) {
	if ( 'publish' !== get_post_status( $pid ) ) {
		continue; // only active listings
	}
	$views  = (int) get_post_meta( $pid, '_wpjms_visits_total', true );
	$clicks = (int) get_post_meta( $pid, '_wpjms_apply_button_click_total', true );

	// Fallback legacy meta
	if ( ! $views ) {
		$views = (int) get_post_meta( $pid, '_count-views_all', true );
	}

	$title    = get_the_title( $pid );
	$city     = get_post_meta( $pid, 'geolocation_city', true );
	$state    = get_post_meta( $pid, 'geolocation_state_short', true );
	$location = $city ? trim( $city . ( $state ? ', ' . $state : '' ) ) : '—';

	$stats[] = compact( 'pid', 'title', 'location', 'views', 'clicks' );
	$total_views  += $views;
	$total_clicks += $clicks;
}

// Sort by views desc
usort( $stats, fn( $a, $b ) => $b['views'] - $a['views'] );

$max_views = $stats ? $stats[0]['views'] : 1;
$stats_plugin_active = defined( 'WPJMS_VERSION' ); // real constant — verified against the plugin's own source, not guessed
?>

<div class="dash-main__body">
<div class="pd-section-header">
	<h1 class="pd-section-title">Stats & Analytics</h1>
	<p class="pd-section-sub">Real view and click data from your active listings<?php echo $stats_plugin_active ? '' : ' <em>(Stats add-on inactive — data may be incomplete)</em>'; ?>.</p>
</div>

<!-- Summary KPI row -->
<div class="kpi-row" style="margin-bottom:28px">
	<div class="kpi-card">
		<div class="kpi-label">Total Views</div>
		<div class="kpi-value"><?php echo esc_html( number_format( $total_views ) ); ?></div>
		<div class="kpi-sub">across <?php echo count( $stats ); ?> active listing<?php echo count( $stats ) !== 1 ? 's' : ''; ?></div>
	</div>
	<div class="kpi-card">
		<div class="kpi-label">Total Clicks</div>
		<div class="kpi-value"><?php echo esc_html( number_format( $total_clicks ) ); ?></div>
		<div class="kpi-sub">apply / contact clicks</div>
	</div>
	<div class="kpi-card">
		<div class="kpi-label">Avg. CTR</div>
		<div class="kpi-value">
			<?php echo $total_views ? esc_html( number_format( ( $total_clicks / $total_views ) * 100, 1 ) ) . '%' : '—'; ?>
		</div>
		<div class="kpi-sub">clicks ÷ views</div>
	</div>
</div>

<?php if ( ! $stats ) : ?>
	<div class="pd-empty-state">
		<div class="pd-empty-icon">📈</div>
		<h3>No active listings</h3>
		<p>Stats will appear here once you have active listings with views recorded.</p>
	</div>
<?php else : ?>

<!-- Per-listing breakdown -->
<div class="pd-card" style="padding:0;overflow:hidden">
	<div style="padding:20px 24px 12px;border-bottom:1px solid var(--gray-100,#f3f4f6)">
		<h3 style="margin:0;font-size:1rem;font-weight:600">Performance by Listing</h3>
	</div>
	<table class="pd-listings-table">
		<thead>
			<tr>
				<th>Listing</th>
				<th>Location</th>
				<th>Views</th>
				<th>Clicks</th>
				<th>CTR</th>
				<th style="width:160px">View distribution</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $stats as $row ) :
			$ctr = $row['views'] ? round( ( $row['clicks'] / $row['views'] ) * 100, 1 ) : 0;
			$bar = $max_views > 0 ? round( ( $row['views'] / $max_views ) * 100 ) : 0;
		?>
			<tr>
				<td>
					<a href="<?php echo esc_url( pd_get_listing_edit_url( $row['pid'] ) ); ?>" class="pd-table-link">
						<?php echo esc_html( $row['title'] ); ?>
					</a>
				</td>
				<td><?php echo esc_html( $row['location'] ); ?></td>
				<td><?php echo esc_html( number_format( $row['views'] ) ); ?></td>
				<td><?php echo esc_html( number_format( $row['clicks'] ) ); ?></td>
				<td><?php echo esc_html( $ctr ); ?>%</td>
				<td>
					<div class="pd-bar-track">
						<div class="pd-bar-fill" style="width:<?php echo esc_attr( $bar ); ?>%"></div>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php endif; ?>
</div><!-- /.dash-main__body -->
