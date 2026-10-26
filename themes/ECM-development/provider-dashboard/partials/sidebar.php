<?php
/**
 * Dashboard sidebar nav. Active state from the current pd_section.
 */

defined( 'ABSPATH' ) || exit;

$pd_section = pd_current_section();

$pd_nav = [
	'home'     => [ '📊', 'Dashboard' ],
	'leads'    => [ '📨', 'Leads' ],
	'listings' => [ '🏢', 'My Listings' ],
	'profile'  => [ '👤', 'My Profile' ],
	'stats'    => [ '📈', 'Stats' ],
	'billing'  => [ '💳', 'Billing' ],
	'settings' => [ '⚙️', 'Settings' ],
];

$pd_base = home_url( '/provider-dashboard/' );
?>
<aside class="dash-sidebar">
	<div class="sidebar-section">
		<nav>
			<?php foreach ( $pd_nav as $slug => $item ) :
				$url    = 'home' === $slug ? $pd_base : trailingslashit( $pd_base . $slug );
				$active = $slug === $pd_section ? ' active' : '';
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="sidebar-link<?php echo $active; ?>">
				<span class="sidebar-icon"><?php echo esc_html( $item[0] ); ?></span> <?php echo esc_html( $item[1] ); ?>
			</a>
			<?php endforeach; ?>
		</nav>
	</div>
	<div class="sidebar-bottom">
		<div class="sidebar-upgrade">
			<strong>Provider Dashboard</strong>
			Manage your listings, profile, and billing.
		</div>
	</div>
</aside>
