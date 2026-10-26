<?php
/**
 * Checkout renewal consent — Privacy Compliance: Developer Requirements,
 * section 10.7 ("Provider checkout"). Exact wording comes from that
 * document; do not edit the strings below without checking it first.
 *
 * Adds, for any checkout that contains a subscription (plan) product:
 *  - a required, standalone renewal-consent checkbox next to the pay button
 *  - a charge-explanation line under the order total (today's prorated
 *    charge vs. the full recurring amount)
 *  - plan name, monthly price, next charge date and a cancel link on the
 *    order confirmation email, plus that email's subject line
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

/**
 * The first subscription cart item at checkout, or null if the cart has
 * no subscription product (e.g. a one-off purchase) or WC Subscriptions
 * isn't active.
 */
function ecm_checkout_subscription_cart_item() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! class_exists( 'WC_Subscriptions_Product' ) ) {
		return null;
	}
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = $cart_item['data'] ?? null;
		if ( $product && WC_Subscriptions_Product::is_subscription( $product ) ) {
			return $cart_item;
		}
	}
	return null;
}

/**
 * Plain-text renewal consent sentence for a given plan name + price,
 * exact wording per 10.7. Shared by the checkout checkbox and the order
 * meta snapshot so the stored text always matches what was shown.
 */
function ecm_renewal_consent_text( string $plan, string $price_html ) : string {
	return sprintf(
		/* translators: 1: plan name, 2: monthly price */
		__( 'I agree to the Terms of Service. My %1$s plan renews automatically at %2$s on the 1st of each month until I cancel. I can cancel anytime from my dashboard.', 'eldercare-matters' ),
		$plan,
		wp_strip_all_tags( $price_html )
	);
}

/**
 * Renewal consent checkbox, required, 10.7.
 */
add_action( 'woocommerce_review_order_before_submit', 'ecm_render_renewal_consent_checkbox', 20 );
function ecm_render_renewal_consent_checkbox() {
	$cart_item = ecm_checkout_subscription_cart_item();
	if ( ! $cart_item ) {
		return;
	}

	$product = $cart_item['data'];
	$plan    = $product->get_name();
	$price   = wc_price( WC_Subscriptions_Product::get_price( $product ) );

	$terms_page_id = function_exists( 'wc_terms_and_conditions_page_id' ) ? wc_terms_and_conditions_page_id() : 0;
	$terms_link    = $terms_page_id
		? '<a href="' . esc_url( get_permalink( $terms_page_id ) ) . '" target="_blank">' . esc_html__( 'Terms of Service', 'eldercare-matters' ) . '</a>'
		: esc_html__( 'Terms of Service', 'eldercare-matters' );

	// Same sentence as ecm_renewal_consent_text(), with "Terms of Service" swapped for a link.
	$label = sprintf(
		/* translators: 1: Terms of Service link, 2: plan name, 3: monthly price */
		__( 'I agree to the %1$s. My %2$s plan renews automatically at %3$s on the 1st of each month until I cancel. I can cancel anytime from my dashboard.', 'eldercare-matters' ),
		$terms_link,
		esc_html( $plan ),
		$price
	);

	printf(
		'<p class="form-row validate-required ecm-renewal-consent">
			<label for="ecm_renewal_consent">
				<input type="checkbox" name="ecm_renewal_consent" id="ecm_renewal_consent" value="1" %1$s />
				%2$s <abbr class="required" title="%3$s">*</abbr>
			</label>
		</p>',
		checked( ! empty( $_POST['ecm_renewal_consent'] ), true, false ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$label, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html()'d/esc_url()'d parts above.
		esc_attr__( 'required', 'eldercare-matters' )
	);
}

/**
 * Required: block checkout if the box isn't checked.
 */
add_action( 'woocommerce_checkout_process', 'ecm_require_renewal_consent' );
function ecm_require_renewal_consent() {
	if ( ecm_checkout_subscription_cart_item() && empty( $_POST['ecm_renewal_consent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		wc_add_notice( __( 'Please check the box agreeing to automatic plan renewal before placing your order.', 'eldercare-matters' ), 'error' );
	}
}

/**
 * Snapshot the exact consent text shown, plus when, onto the order — not a
 * pointer to it, so later copy changes don't rewrite what this customer
 * actually agreed to.
 */
add_action( 'woocommerce_checkout_create_order', 'ecm_save_renewal_consent', 10, 2 );
function ecm_save_renewal_consent( $order, $data ) {
	$cart_item = ecm_checkout_subscription_cart_item();
	if ( ! $cart_item || empty( $_POST['ecm_renewal_consent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$product = $cart_item['data'];
	$order->update_meta_data(
		'_ecm_renewal_consent_text',
		ecm_renewal_consent_text( $product->get_name(), wc_price( WC_Subscriptions_Product::get_price( $product ) ) )
	);
	$order->update_meta_data( '_ecm_renewal_consent_at', current_time( 'mysql' ) );
}

/**
 * Charge-explanation line under the checkout total, 10.7: today's prorated
 * charge vs. the full amount billed going forward. The cart item's price is
 * already prorated by WC Subscriptions' sync feature when applicable, so
 * "today" just reads the line subtotal WooCommerce already computed.
 */
add_action( 'woocommerce_review_order_after_order_total', 'ecm_render_checkout_charge_explanation' );
function ecm_render_checkout_charge_explanation() {
	$cart_item = ecm_checkout_subscription_cart_item();
	if ( ! $cart_item ) {
		return;
	}

	$product      = $cart_item['data'];
	$today_amount = (float) $cart_item['line_subtotal'];
	$full_price   = (float) WC_Subscriptions_Product::get_price( $product );

	printf(
		'<tr class="ecm-charge-explanation"><td colspan="2">%s</td></tr>',
		wp_kses_post( sprintf(
			/* translators: 1: amount charged today, 2: current month name, 3: full monthly price charged going forward */
			__( 'Today: %1$s for the rest of %2$s. Then %3$s on the 1st of each month.', 'eldercare-matters' ),
			wc_price( $today_amount ),
			esc_html( date_i18n( 'F' ) ),
			wc_price( $full_price )
		) )
	);
}

/**
 * The subscription (if any) created from this order's checkout, or null.
 */
function ecm_order_new_subscription( $order ) {
	if ( ! $order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
		return null;
	}
	$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'parent' ] );
	return $subscriptions ? reset( $subscriptions ) : null;
}

/**
 * Confirmation email subject, 10.7: "Your ElderCareMatters {plan} plan is active".
 */
add_filter( 'woocommerce_email_subject_customer_processing_order', 'ecm_filter_confirmation_email_subject', 10, 2 );
add_filter( 'woocommerce_email_subject_customer_completed_order', 'ecm_filter_confirmation_email_subject', 10, 2 );
function ecm_filter_confirmation_email_subject( $subject, $order ) {
	$subscription = ecm_order_new_subscription( $order );
	if ( ! $subscription ) {
		return $subject;
	}
	$item = current( $subscription->get_items() );
	if ( ! $item ) {
		return $subject;
	}
	return sprintf(
		/* translators: %s: plan name */
		__( 'Your ElderCareMatters %s plan is active', 'eldercare-matters' ),
		$item->get_name()
	);
}

/**
 * Confirmation email body, 10.7: plan name, monthly price, next charge
 * date, and how to cancel.
 */
add_action( 'woocommerce_email_before_order_table', 'ecm_render_confirmation_email_plan_block', 10, 4 );
function ecm_render_confirmation_email_plan_block( $order, $sent_to_admin, $plain_text, $email ) {
	if ( $sent_to_admin || empty( $email->id ) || ! in_array( $email->id, [ 'customer_processing_order', 'customer_completed_order' ], true ) ) {
		return;
	}

	$subscription = ecm_order_new_subscription( $order );
	if ( ! $subscription ) {
		return;
	}

	$item = current( $subscription->get_items() );
	if ( ! $item ) {
		return;
	}

	$plan        = $item->get_name();
	$price       = wc_price( $subscription->get_total() );
	$next_charge = $subscription->get_date( 'next_payment' );
	$next_charge = $next_charge ? wc_format_datetime( wc_string_to_datetime( $next_charge ) ) : '';
	$cancel_url  = $subscription->get_view_order_url();

	if ( $plain_text ) {
		echo "\n" . sprintf(
			/* translators: 1: plan name, 2: monthly price, 3: next charge date, 4: cancel link */
			esc_html__( 'Plan: %1$s — %2$s per month. Next charge: %3$s. You can cancel anytime from your dashboard: %4$s', 'eldercare-matters' ),
			wp_strip_all_tags( $plan ),
			wp_strip_all_tags( $price ),
			$next_charge,
			$cancel_url
		) . "\n\n";
		return;
	}

	printf(
		'<p>%1$s: <strong>%2$s</strong> — <strong>%3$s</strong> %4$s</p><p>%5$s <a href="%6$s">%7$s</a></p>',
		esc_html__( 'Plan', 'eldercare-matters' ),
		esc_html( $plan ),
		$price,
		esc_html__( 'per month', 'eldercare-matters' ),
		sprintf(
			/* translators: %s: next charge date */
			esc_html__( 'Next charge: %s.', 'eldercare-matters' ),
			esc_html( $next_charge )
		),
		esc_url( $cancel_url ),
		esc_html__( 'Cancel anytime from your dashboard', 'eldercare-matters' )
	);
}
