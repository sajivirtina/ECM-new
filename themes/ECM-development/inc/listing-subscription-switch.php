<?php
/**
 * Listing → WooCommerce Subscription resolution + "Upgrade / Downgrade" URL.
 *
 * Chain:
 *   job_listing._user_package_id  →  wp_wcpl_user_packages.id
 *   wp_wcpl_user_packages.order_id →  WC_Subscription ID (for subscription
 *                                      packages, Listing Payments stores the
 *                                      subscription ID here, not the parent
 *                                      order ID — parent order is handled as
 *                                      a fallback just in case)
 *
 * Then builds the same switch URL WooCommerce Subscriptions prints on
 * My Account → Subscriptions ("Upgrade or Downgrade" button):
 *   /product/subscription-plans/?switch-subscription=X&item=Y&_wcsnonce=Z
 * via WC_Subscriptions_Switcher::get_switch_url(), so the nonce, grouped
 * product and proration all behave exactly like the native button.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns a wcpl_user_packages row ID into the WC_Subscription it belongs to.
 * Returns null if the row isn't the given user's, or isn't a subscription.
 */
function ecm_get_subscription_for_user_package( int $user_package_id, int $user_id = 0 ) {
	if ( ! $user_package_id || ! function_exists( 'wcs_get_subscription' ) ) {
		return null;
	}
	$user_id = $user_id ?: get_current_user_id();

	global $wpdb;
	$order_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT order_id FROM {$wpdb->prefix}wcpl_user_packages WHERE id = %d AND user_id = %d",
		$user_package_id,
		$user_id
	) );
	if ( ! $order_id ) {
		return null;
	}

	// Normal case: order_id already is the subscription.
	if ( wcs_is_subscription( $order_id ) ) {
		$sub = wcs_get_subscription( $order_id );
		return ( $sub && (int) $sub->get_user_id() === $user_id ) ? $sub : null;
	}

	// Fallback: order_id is the parent order — find the subscription created from it.
	$subs = wcs_get_subscriptions_for_order( $order_id, [ 'order_type' => 'any' ] );
	foreach ( $subs as $sub ) {
		if ( (int) $sub->get_user_id() === $user_id ) {
			return $sub;
		}
	}
	return null;
}

/** Debug trace — add ?ecm_sub_debug=1 to the Add/Edit Listing URL to see it. */
function ecm_sub_trace( string $msg ) {
	$GLOBALS['ecm_sub_trace'][] = $msg;
}

/**
 * Owner's subscriptions (newest first) that contain one of our listing
 * package products. If $product_id is given, only subscriptions with that
 * exact product. Active ones are preferred over other statuses.
 */
function ecm_find_package_subscription( int $user_id, int $product_id = 0 ) {
	if ( ! $user_id || ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return null;
	}
	$package_ids = function_exists( 'ecm_package_product_ids' ) ? array_map( 'intval', array_values( ecm_package_product_ids() ) ) : [];
	$fallback    = null;

	$subs = wcs_get_users_subscriptions( $user_id );
	krsort( $subs ); // newest first
	foreach ( $subs as $sub ) {
		if ( $sub->has_status( [ 'cancelled', 'expired', 'trash', 'switched' ] ) ) {
			continue;
		}
		foreach ( $sub->get_items() as $item ) {
			$pid = (int) $item->get_product_id();
			$vid = (int) $item->get_variation_id();
			$match = $product_id
				? in_array( $product_id, [ $pid, $vid ], true )
				: ( array_intersect( [ $pid, $vid ], $package_ids ) ? true : false );
			if ( ! $match ) {
				continue;
			}
			if ( $sub->has_status( 'active' ) ) {
				return $sub;
			}
			$fallback = $fallback ?: $sub;
		}
	}
	return $fallback;
}

/**
 * Finds the subscription that pays for a specific listing.
 */
function ecm_get_listing_subscription( int $job_id ) {
	if ( ! $job_id || ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		ecm_sub_trace( 'No job ID or WooCommerce Subscriptions not active.' );
		return null;
	}
	$owner_id = (int) get_post_field( 'post_author', $job_id );
	ecm_sub_trace( "Listing #$job_id, owner user #$owner_id" );

	// 1) Via the user package the listing is attached to.
	$user_package_id = (int) get_post_meta( $job_id, '_user_package_id', true );
	ecm_sub_trace( "_user_package_id = " . ( $user_package_id ?: '(empty)' ) );
	if ( $user_package_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wcpl_user_packages WHERE id = %d", $user_package_id ), ARRAY_A );
		ecm_sub_trace( 'wcpl_user_packages row: ' . ( $row ? wp_json_encode( $row ) : '(not found)' ) );
		if ( $row && function_exists( 'wcs_is_subscription' ) ) {
			ecm_sub_trace( "row order_id {$row['order_id']} is subscription? " . ( wcs_is_subscription( (int) $row['order_id'] ) ? 'yes' : 'no' ) );
		}
		$sub = ecm_get_subscription_for_user_package( $user_package_id, $owner_id );
		if ( $sub ) {
			ecm_sub_trace( 'Step 1 matched subscription #' . $sub->get_id() );
			return $sub;
		}
	}

	// 2) "Link to listing" subscriptions: _job_id on the subscription line item.
	foreach ( wcs_get_users_subscriptions( $owner_id ) as $sub ) {
		foreach ( $sub->get_items() as $item ) {
			if ( (int) $item->get_meta( '_job_id' ) === $job_id ) {
				ecm_sub_trace( 'Step 2 (_job_id on line item) matched subscription #' . $sub->get_id() );
				return $sub;
			}
		}
	}

	// 3) Owner's subscription for the same package product as this listing.
	$product_id = function_exists( 'ecm_get_listing_package_product_id' ) ? (int) ecm_get_listing_package_product_id( $job_id ) : 0;
	ecm_sub_trace( 'Listing package product ID = ' . ( $product_id ?: '(none)' ) );
	if ( $product_id ) {
		$sub = ecm_find_package_subscription( $owner_id, $product_id );
		if ( $sub ) {
			ecm_sub_trace( 'Step 3 (same product) matched subscription #' . $sub->get_id() );
			return $sub;
		}
	}

	// 4) Any live package subscription the owner has.
	$sub = ecm_find_package_subscription( $owner_id );
	ecm_sub_trace( $sub ? 'Step 4 (any package subscription) matched #' . $sub->get_id() : 'No subscription found — falling back to plans page.' );
	return $sub;
}

/**
 * Native WCS switch URL for a subscription's first switchable line item.
 * Returns '' if switching isn't possible (inactive, not in a grouped product,
 * switching disabled in WC → Settings → Subscriptions, etc.).
 */
function ecm_get_subscription_switch_url( $subscription ) : string {
	if ( ! $subscription || ! class_exists( 'WC_Subscriptions_Switcher' ) ) {
		return '';
	}
	foreach ( $subscription->get_items() as $item_id => $item ) {
		if ( WC_Subscriptions_Switcher::can_item_be_switched_by_user( $item, $subscription ) ) {
			return (string) WC_Subscriptions_Switcher::get_switch_url( $item_id, $item, $subscription );
		}
	}
	return '';
}

/**
 * The URL to use for the "Upgrade your package →" link.
 *
 * @param int $job_id Listing being edited, or 0 on the Add Listing screen
 *                    (then the package chosen on the "Choose a package" step
 *                    is read from the Listing Payments cookies).
 */
function ecm_get_upgrade_package_url( int $job_id = 0 ) : string {
	$sub = null;

	if ( $job_id ) {
		$sub = ecm_get_listing_subscription( $job_id );
	} elseif (
		! empty( $_COOKIE['chosen_package_id'] ) &&
		! empty( $_COOKIE['chosen_package_is_user_package'] ) &&
		'1' === (string) wp_unslash( $_COOKIE['chosen_package_is_user_package'] )
	) {
		// Add Listing, provider picked a package they already own.
		$sub = ecm_get_subscription_for_user_package( absint( wp_unslash( $_COOKIE['chosen_package_id'] ) ) );
		ecm_sub_trace( 'Add Listing: chosen user package #' . absint( $_COOKIE['chosen_package_id'] ) . ' → ' . ( $sub ? 'subscription #' . $sub->get_id() : 'no subscription' ) );
	}

	if ( ! $sub && ! $job_id ) {
		// Add Listing with a new product chosen (or nothing matched) —
		// use the provider's current package subscription, if they have one.
		$sub = ecm_find_package_subscription( get_current_user_id() );
		ecm_sub_trace( 'Add Listing fallback: ' . ( $sub ? 'current package subscription #' . $sub->get_id() : 'user has no package subscription' ) );
	}

	if ( $sub ) {
		$switch_url = ecm_get_subscription_switch_url( $sub );
		ecm_sub_trace( 'Subscription #' . $sub->get_id() . ' status ' . $sub->get_status() . ', switch URL: ' . ( $switch_url ?: '(not switchable — check WC → Settings → Subscriptions → Switching)' ) );
		if ( $switch_url ) {
			return $switch_url;
		}
		// Subscription exists but can't be switched right now (e.g. on-hold) —
		// send them to its management screen instead.
		return add_query_arg( [ 'view' => 'subscription', 'id' => $sub->get_id() ], home_url( '/provider-dashboard/billing/' ) );
	}

	// No subscription yet (free listing, or a brand-new package being bought
	// in this submission) → plans page ('/product/subscription-plans/' does
	// not exist; the real Plans & Pricing page is published at this slug).
	return home_url( '/plans/' );
}

/** Prints the trace as a small panel when ?ecm_sub_debug=1 is on the URL. */
function ecm_print_sub_trace() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only debug flag.
	if ( empty( $_GET['ecm_sub_debug'] ) || ! is_user_logged_in() || empty( $GLOBALS['ecm_sub_trace'] ) ) {
		return;
	}
	echo '<pre style="background:#111;color:#9f9;padding:12px;font-size:12px;white-space:pre-wrap;border-radius:8px;">';
	echo esc_html( "Upgrade link debug:\n- " . implode( "\n- ", $GLOBALS['ecm_sub_trace'] ) );
	echo '</pre>';
}
