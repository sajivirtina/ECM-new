<?php
defined( 'ABSPATH' ) || exit;

function ecm_subscription_overdue_on_hold( $subscription ) : bool {
	if ( ! $subscription || ! $subscription->has_status( 'on-hold' ) ) {
		return false;
	}
	$next = $subscription->get_time( 'next_payment' );
	return ! $next || $next <= time();
}

function ecm_prorated_unit_price( $product, float $price ) : ?float {
	if ( ! class_exists( 'WC_Subscriptions_Synchroniser' ) || ! WC_Subscriptions_Product::is_subscription( $product ) || ! WC_Subscriptions_Synchroniser::is_product_prorated( $product ) ) {
		return null;
	}
	$next = WC_Subscriptions_Synchroniser::calculate_first_payment_date( $product, 'timestamp' );
	if ( ! $next || WC_Subscriptions_Synchroniser::is_today( $next ) ) {
		return null;
	}
	$interval = (int) WC_Subscriptions_Product::get_interval( $product );
	switch ( WC_Subscriptions_Product::get_period( $product ) ) {
		case 'week':
			$cycle = 7 * $interval;
			break;
		case 'month':
			$cycle = (int) gmdate( 't' ) * $interval;
			break;
		case 'year':
			$cycle = ( 365 + (int) gmdate( 'L' ) ) * $interval;
			break;
		default:
			return null;
	}
	$days = ceil( ( $next - time() ) / DAY_IN_SECONDS );
	return round( $days * ( $price / $cycle ), wc_get_price_decimals() );
}

function ecm_overdue_renewal_amount( $subscription ) : ?array {
	if ( ! ecm_subscription_overdue_on_hold( $subscription ) ) {
		return null;
	}
	$due  = 0.0;
	$full = 0.0;
	$next = 0;
	foreach ( $subscription->get_items() as $item ) {
		$product = $item->get_product();
		$qty     = max( 1, (int) $item->get_quantity() );
		$unit    = (float) $item->get_subtotal() / $qty;
		$prorate = $product ? ecm_prorated_unit_price( $product, $unit ) : null;
		if ( null === $prorate ) {
			return null;
		}
		$due  += $prorate * $qty;
		$full += $unit * $qty;
		$next  = WC_Subscriptions_Synchroniser::calculate_first_payment_date( $product, 'timestamp' );
	}
	return $next ? [ 'amount' => $due, 'full' => $full, 'next' => $next ] : null;
}

function ecm_order_prorated_amount( $order ) : ?array {
	static $cache = [];
	if ( ! $order || ! function_exists( 'wcs_get_subscriptions_for_renewal_order' ) || ! $order->needs_payment() ) {
		return null;
	}
	$id = $order->get_id();
	if ( ! array_key_exists( $id, $cache ) ) {
		$cache[ $id ] = null;
		foreach ( wcs_get_subscriptions_for_renewal_order( $order ) as $subscription ) {
			if ( (int) $subscription->get_last_order( 'ids', 'any' ) === $id ) {
				$cache[ $id ] = ecm_overdue_renewal_amount( $subscription );
				break;
			}
		}
	}
	return $cache[ $id ];
}

function ecm_prorated_line_total( $item, $order ) : ?float {
	if ( ! ecm_order_prorated_amount( $order ) ) {
		return null;
	}
	$product = $item->get_product();
	$qty     = max( 1, (int) $item->get_quantity() );
	$unit    = $product ? ecm_prorated_unit_price( $product, (float) $item->get_subtotal() / $qty ) : null;
	return null === $unit ? null : $unit * $qty;
}

add_filter( 'woocommerce_get_cart_item_from_session', function ( $data, $values, $key ) {
	$renewal = $data['subscription_renewal'] ?? null;
	if ( empty( $renewal['subscription_id'] ) || empty( $data['data'] ) ) {
		return $data;
	}
	if ( ! ecm_subscription_overdue_on_hold( wcs_get_subscription( (int) $renewal['subscription_id'] ) ) ) {
		return $data;
	}
	$prorated = ecm_prorated_unit_price( $data['data'], (float) $data['data']->get_price() );
	if ( null !== $prorated ) {
		$data['data']->set_price( $prorated );
	}
	return $data;
}, 20, 3 );
