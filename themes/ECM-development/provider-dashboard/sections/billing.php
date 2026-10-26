<?php
/**
 * M6 — Billing. Real WooCommerce order history, subscription, and saved
 * payment method for the current provider. Uses wc_get_orders(),
 * wcs_get_users_subscriptions() (WooCommerce Subscriptions), and
 * WC_Payment_Tokens (WooCommerce core, populated by the Stripe gateway) —
 * no dummy data. "Lead Credits" from the prototype is not implemented:
 * no credits system exists anywhere in this install.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_get_orders' ) ) : ?>
<div class="dash-main__body">
	<div class="pd-section-header">
		<h1 class="pd-section-title">Billing</h1>
	</div>
	<div class="pd-empty-state">
		<div class="pd-empty-icon">💳</div>
		<h3>WooCommerce is not active</h3>
		<p>Billing information requires WooCommerce to be installed and active.</p>
	</div>
</div><!-- /.dash-main__body -->
<?php return; endif;

/**
 * Order / Subscription / Payment-Methods detail views — rendered in-place
 * here rather than linking out to WooCommerce's separate My Account pages.
 * Reuses WC's / WC Subscriptions' own template-rendering functions directly,
 * so ownership checks (current_user_can('view_order', $id) — true only for
 * the order/subscription's own customer or a manage_woocommerce admin),
 * nonces on any action forms (cancel, set-default card, etc.), and PCI-safe
 * card entry (Stripe's own iframe) are exactly what WC itself uses on its
 * native account pages — nothing about that security model is reimplemented
 * or bypassed, only the surrounding chrome changes.
 */
if ( pd_is_billing_detail_request() ) {
	$uid  = get_current_user_id();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view; WC's own forms inside are separately nonce-protected.
	$view = sanitize_key( $_GET['view'] );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$item_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	$back = '<p class="pd-section-sub"><a href="' . esc_url( home_url( '/provider-dashboard/billing/' ) ) . '">← Back to Billing</a></p>';

	if ( 'payment-methods' === $view ) : ?>
		<div class="dash-main__body">
		<div class="pd-section-header">
			<h1 class="pd-section-title">Payment Methods</h1>
			<?php echo $back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url() above. ?>
		</div>
		<div class="pd-card" style="padding:24px;">
			<?php wc_print_notices(); ?>
			<?php woocommerce_account_payment_methods(); ?>
		</div>
		</div>
		<?php
		return;
	endif;

	if ( 'order' === $view && $item_id && current_user_can( 'view_order', $item_id ) ) :
		$order_due = function_exists( 'ecm_order_prorated_amount' ) ? ecm_order_prorated_amount( wc_get_order( $item_id ) ) : null;
		?>
		<div class="dash-main__body">
		<div class="pd-section-header">
			<h1 class="pd-section-title">Order #<?php echo esc_html( $item_id ); ?></h1>
			<?php echo $back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php if ( $order_due ) : ?>
		<div class="pd-notice pd-notice--warning"><?php
			echo esc_html( sprintf(
				'This renewal is listed at %1$s, but because the plan is on hold, paying it now charges only %2$s for the rest of the month (through %3$s). The regular %1$s/month resumes on %4$s.',
				wp_strip_all_tags( wc_price( $order_due['full'] ) ),
				wp_strip_all_tags( wc_price( $order_due['amount'] ) ),
				wp_date( 'F j', $order_due['next'] - DAY_IN_SECONDS ),
				wp_date( 'F j', $order_due['next'] )
			) );
		?></div>
		<?php endif; ?>
		<div class="pd-card" style="padding:24px;">
			<?php WC_Shortcode_My_Account::view_order( $item_id ); ?>
		</div>
		</div>
		<?php
		return;
	endif;

	if ( 'subscription' === $view && $item_id && class_exists( 'WCS_Template_Loader' ) && current_user_can( 'view_order', $item_id ) ) :
		$hold_sub = wcs_get_subscription( $item_id );
		$on_hold  = $hold_sub && $hold_sub->has_status( 'on-hold' );
		if ( $on_hold ) {
			$hold_msg = pd_on_hold_notice( $hold_sub );
		}
		?>
		<div class="dash-main__body">
		<div class="pd-section-header">
			<h1 class="pd-section-title">Subscription #<?php echo esc_html( $item_id ); ?></h1>
			<?php echo $back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php if ( $on_hold ) : ?>
		<div class="pd-notice pd-notice--warning"><?php echo esc_html( $hold_msg ); ?></div>
		<?php endif; ?>
		<div class="pd-card" style="padding:24px;">
			<?php WCS_Template_Loader::get_view_subscription_template( $item_id ); ?>
		</div>
		</div>
		<?php
		return;
	endif;
	?>
	<div class="dash-main__body">
		<div class="pd-empty-state">
			<div class="pd-empty-icon">🚫</div>
			<h3>Not found</h3>
			<p>That order or subscription doesn't exist, or isn't yours.</p>
			<a href="<?php echo esc_url( home_url( '/provider-dashboard/billing/' ) ); ?>" class="pd-btn pd-btn--primary">← Back to Billing</a>
		</div>
	</div>
	<?php
	return;
}

$uid = get_current_user_id();

// Order History pagination — some providers have thousands of orders (e.g.
// monthly listing-renewal orders going back years), so this must be a real
// paginated query, not a fetch-50-and-slice.
$per_page   = 15;
$order_page = max( 1, (int) ( $_GET['opg'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination, read-only.
$orders_result = wc_get_orders( [
	'customer_id' => $uid,
	'limit'       => $per_page,
	'page'        => $order_page,
	'orderby'     => 'date',
	'order'       => 'DESC',
	'status'      => [ 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'wc-refunded' ],
	'paginate'    => true,
] );
$orders            = $orders_result->orders;
$total_orders       = (int) $orders_result->total;
$total_order_pages = max( 1, (int) $orders_result->max_num_pages );

// Real accumulated total spend (WC's own cached figure, not a sum of one page's orders).
$total_spend = (float) wc_get_customer_total_spent( $uid );

// Subscriptions (WC Subscriptions add-on) — current/active plan(s).
$subscriptions   = [];
$active_subs_list = [];
if ( function_exists( 'wcs_get_users_subscriptions' ) ) {
	$subscriptions = wcs_get_users_subscriptions( $uid );
	foreach ( $subscriptions as $sub ) {
		if ( $sub->has_status( [ 'active', 'pending-cancel', 'on-hold' ] ) ) {
			$active_subs_list[] = $sub;
		}
	}
}
$active_subs = count( $active_subs_list );

// Saved payment method (WooCommerce core, populated by whichever gateway is active — e.g. Stripe).
$default_token = null;
if ( class_exists( 'WC_Payment_Tokens' ) ) {
	$default_token = WC_Payment_Tokens::get_customer_default_token( $uid );
	if ( ! $default_token ) {
		$customer_tokens = WC_Payment_Tokens::get_customer_tokens( $uid );
		$default_token   = $customer_tokens ? reset( $customer_tokens ) : null;
	}
}
?>

<div class="dash-main__body">
<div class="pd-section-header">
	<h1 class="pd-section-title">Billing</h1>
	<p class="pd-section-sub">Your order history and spend with ElderCareMatters.</p>
</div>

<?php if ( function_exists( 'wcs_get_users_subscriptions' ) ) : ?>
<!-- Current Plan (real WooCommerce Subscriptions data) -->
<div class="pd-card" style="padding:20px 24px;margin-bottom:20px;">
	<h3 style="margin:0 0 14px;font-size:1rem;font-weight:600">Current Plan</h3>
	<?php if ( ! $active_subs_list ) : ?>
		<p style="color:var(--gray-500,#6b7280);margin:0 0 12px;">No active subscription.</p>
		<a href="<?php echo esc_url( home_url( '/for-providers/' ) ); ?>" class="pd-btn pd-btn--primary">View Plans →</a>
	<?php else :
		$sub_per_page    = 10;
		$sub_total_pages = max( 1, (int) ceil( count( $active_subs_list ) / $sub_per_page ) );
		$sub_page        = max( 1, min( $sub_total_pages, (int) ( $_GET['spg'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination, read-only.
		$sub_page_items  = array_slice( $active_subs_list, ( $sub_page - 1 ) * $sub_per_page, $sub_per_page );
		$active_subs_count = count( $sub_page_items );
		foreach ( array_values( $sub_page_items ) as $i => $sub ) :
		$sub_items = $sub->get_items();
		$sub_names = array_map( fn( $item ) => $item->get_name(), array_values( $sub_items ) );
		$interval  = (int) $sub->get_billing_interval();
		$period    = $sub->get_billing_period();
		$period_str = 1 === $interval ? $period : "$interval {$period}s";
		$sub_status = wcs_get_subscription_status_name( $sub->get_status() );
		$pay_url    = pd_on_hold_pay_url( $sub );
		$is_last    = $i === $active_subs_count - 1;
	?>
	<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;<?php echo $is_last ? '' : 'margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--gray-100,#f3f4f6);'; ?>">
		<div>
			<div style="font-weight:600;font-size:1.05rem;"><?php echo esc_html( $sub_names ? implode( ', ', $sub_names ) : 'Subscription #' . $sub->get_id() ); ?></div>
			<div style="font-size:0.85rem;color:var(--gray-500,#6b7280);margin-top:2px;">
				<?php echo esc_html( strip_tags( wc_price( $sub->get_total() ) ) . ' / ' . $period_str ); ?>
				· <?php echo esc_html( $sub_status ); ?>
				<?php if ( $sub->has_status( 'active' ) ) : ?>
					· Next payment <?php echo esc_html( $sub->get_date_to_display( 'next_payment' ) ); ?>
				<?php elseif ( $pay_url ) : ?>
					· Payment needed
				<?php endif; ?>
			</div>
		</div>
		<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
			<?php if ( $pay_url ) : ?>
				<?php $pay_amount = pd_on_hold_pay_amount( $sub ); ?><a href="<?php echo esc_url( $pay_url ); ?>" class="pd-btn pd-btn--primary"><?php echo esc_html( $pay_amount ? "Pay $pay_amount now →" : 'Pay now →' ); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'view' => 'subscription', 'id' => $sub->get_id() ], home_url( '/provider-dashboard/billing/' ) ) ); ?>" class="btn--secondary">Manage Subscription →</a>
		</div>
		<?php if ( $sub->has_status( 'on-hold' ) ) : ?>
			<div class="pd-notice pd-notice--warning" style="flex:0 0 100%;margin:0;"><?php echo esc_html( pd_on_hold_notice( $sub, true ) ); ?></div>
		<?php endif; ?>
	</div>
	<?php endforeach; ?>
	<?php if ( $sub_total_pages > 1 ) : ?>
	<div class="pd-pagination" style="justify-content:space-between;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-100,#f3f4f6);">
		<?php if ( $sub_page > 1 ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'spg', $sub_page - 1 ) ); ?>" class="pd-page-link pd-page-link--nav">← Previous</a>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
		<span style="font-size:0.85rem;color:var(--gray-500,#6b7280);">Page <?php echo esc_html( $sub_page ); ?> of <?php echo esc_html( $sub_total_pages ); ?> (<?php echo esc_html( count( $active_subs_list ) ); ?> subscriptions)</span>
		<?php if ( $sub_page < $sub_total_pages ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'spg', $sub_page + 1 ) ); ?>" class="pd-page-link pd-page-link--nav">Next →</a>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	<?php endif; ?>
</div>
<?php endif; ?>

<!-- Payment Method (real WooCommerce payment token — populated by the active gateway, e.g. Stripe) -->
<div class="pd-card" style="padding:20px 24px;margin-bottom:20px;">
	<h3 style="margin:0 0 14px;font-size:1rem;font-weight:600">Payment Method</h3>
	<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
		<?php if ( $default_token ) : ?>
			<div style="display:flex;align-items:center;gap:10px;">
				<span style="font-size:1.3rem;">💳</span>
				<span><?php echo esc_html( $default_token->get_display_name() ); ?></span>
			</div>
		<?php else : ?>
			<span style="color:var(--gray-500,#6b7280);">No payment method on file.</span>
		<?php endif; ?>
		<?php if ( function_exists( 'wc_get_account_endpoint_url' ) ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'view' => 'payment-methods' ], home_url( '/provider-dashboard/billing/' ) ) ); ?>" class="btn--secondary"><?php echo $default_token ? 'Update Card →' : 'Add Payment Method →'; ?></a>
		<?php endif; ?>
	</div>
</div>

<!-- KPI row -->
<div class="kpi-row" style="margin-bottom:28px">
	<div class="kpi-card">
		<div class="kpi-label">Total Orders</div>
		<div class="kpi-value"><?php echo esc_html( number_format_i18n( $total_orders ) ); ?></div>
		<div class="kpi-sub">All time</div>
	</div>
	<div class="kpi-card">
		<div class="kpi-label">Total Spend</div>
		<div class="kpi-value"><?php echo $total_spend > 0 ? esc_html( strip_tags( wc_price( $total_spend ) ) ) : '$0'; ?></div>
		<div class="kpi-sub">Completed &amp; processing orders</div>
	</div>
	<?php if ( function_exists( 'wcs_get_subscriptions' ) ) : ?>
	<div class="kpi-card">
		<div class="kpi-label">Active Subscriptions</div>
		<div class="kpi-value"><?php echo esc_html( number_format_i18n( $active_subs ) ); ?></div>
		<div class="kpi-sub">WooCommerce Subscriptions</div>
	</div>
	<?php endif; ?>
</div>

<?php if ( ! $orders ) : ?>
	<div class="pd-empty-state">
		<div class="pd-empty-icon">💳</div>
		<h3>No orders yet</h3>
		<p>Your purchase history will appear here once you have placed an order.</p>
		<a href="<?php echo esc_url( home_url( '/for-providers/' ) ); ?>" class="pd-btn pd-btn--primary">View Plans →</a>
	</div>
<?php else : ?>

<div class="pd-card" style="padding:0;overflow:hidden">
	<div style="padding:20px 24px 12px;border-bottom:1px solid var(--gray-100,#f3f4f6)">
		<h3 style="margin:0;font-size:1rem;font-weight:600">Order History</h3>
	</div>
	<table class="pd-listings-table">
		<thead>
			<tr>
				<th>Order</th>
				<th>Date</th>
				<th>Items</th>
				<th>Total</th>
				<th>Status</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $orders as $order ) :
			$status     = $order->get_status();
			$badge_cls  = match( $status ) {
				'completed'  => 'pd-badge--green',
				'processing' => 'pd-badge--teal',
				'on-hold'    => 'pd-badge--amber',
				'cancelled', 'refunded' => 'pd-badge--red',
				default      => 'pd-badge--gray',
			};
			$items = $order->get_items();
			$item_names = array_map( fn( $i ) => $i->get_name(), array_values( $items ) );
		?>
			<tr>
				<td><a href="<?php echo esc_url( add_query_arg( [ 'view' => 'order', 'id' => $order->get_id() ], home_url( '/provider-dashboard/billing/' ) ) ); ?>" class="pd-table-link">#<?php echo esc_html( $order->get_id() ); ?></a></td>
				<td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'M j, Y' ) : '—' ); ?></td>
				<td style="max-width:240px">
					<?php if ( $item_names ) : ?>
						<span title="<?php echo esc_attr( implode( ', ', $item_names ) ); ?>">
							<?php echo esc_html( $item_names[0] ); ?>
							<?php if ( count( $item_names ) > 1 ) : ?>
								<span style="color:var(--gray-400);font-size:0.8rem"> +<?php echo count( $item_names ) - 1; ?> more</span>
							<?php endif; ?>
						</span>
					<?php else : ?>
						<span style="color:var(--gray-400)">—</span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( strip_tags( wc_price( $order->get_total() ) ) ); ?></td>
				<td><span class="pd-badge <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span></td>
				<td><a href="<?php echo esc_url( add_query_arg( [ 'view' => 'order', 'id' => $order->get_id() ], home_url( '/provider-dashboard/billing/' ) ) ); ?>" class="pd-table-link" style="white-space:nowrap">View →</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php if ( $total_order_pages > 1 ) : ?>
<div class="pd-pagination" style="justify-content:space-between;">
	<?php if ( $order_page > 1 ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'opg', $order_page - 1 ) ); ?>" class="pd-page-link pd-page-link--nav">← Previous</a>
	<?php else : ?>
		<span></span>
	<?php endif; ?>
	<span style="font-size:0.85rem;color:var(--gray-500,#6b7280);">Page <?php echo esc_html( $order_page ); ?> of <?php echo esc_html( $total_order_pages ); ?> (<?php echo esc_html( number_format_i18n( $total_orders ) ); ?> orders)</span>
	<?php if ( $order_page < $total_order_pages ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'opg', $order_page + 1 ) ); ?>" class="pd-page-link pd-page-link--nav">Next →</a>
	<?php else : ?>
		<span></span>
	<?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>
</div><!-- /.dash-main__body -->
