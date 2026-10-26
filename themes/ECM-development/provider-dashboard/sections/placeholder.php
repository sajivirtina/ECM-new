<?php
/**
 * Generic "coming soon" panel for dashboard sections not yet built (M1+).
 */

defined( 'ABSPATH' ) || exit;

$pd_section = pd_current_section();
$pd_titles  = [
	'leads'    => 'Lead Inbox',
	'listings' => 'My Listings',
	'profile'  => 'My Profile',
	'stats'    => 'Stats',
	'reviews'  => 'Reviews',
	'billing'  => 'Billing',
	'settings' => 'Settings',
];
$pd_title = $pd_titles[ $pd_section ] ?? ucfirst( $pd_section );
?>
<div class="greeting-bar">
	<div class="greeting-text">
		<h2><?php echo esc_html( $pd_title ); ?></h2>
		<p>This module is coming soon.</p>
	</div>
</div>
<div class="dash-main__body">
	<div class="dash-card">
		<div class="dash-card__body" style="text-align:center;padding:56px 24px;">
			<div style="font-size:2.2rem;margin-bottom:10px;">🛠️</div>
			<h3 style="margin:0 0 6px;">“<?php echo esc_html( $pd_title ); ?>” is on the way</h3>
			<p style="color:var(--gray-500,#6b7280);margin:0 0 18px;">We're building this module next.</p>
			<a href="<?php echo esc_url( home_url( '/provider-dashboard/' ) ); ?>" class="btn--secondary btn--sm">← Back to Dashboard</a>
		</div>
	</div>
</div>
