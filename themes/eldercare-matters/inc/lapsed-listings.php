<?php
defined( 'ABSPATH' ) || exit;

// _expired_subscription_id is written by Listing Payments only when a subscription ends, and its package is deleted then; a live package means the marker is stale.
function ecm_listing_subscription_lapsed( int $post_id ) : bool {
	$status = get_post_status( $post_id );
	if ( 'publish' === $status ) {
		return in_array( $post_id, ecm_inactive_subscription_listing_ids(), true );
	}
	if ( 'expired' !== $status || '' === (string) get_post_meta( $post_id, '_expired_subscription_id', true ) ) {
		return false;
	}
	global $wpdb;
	$user_package_id = (int) get_post_meta( $post_id, '_user_package_id', true );
	return ! $user_package_id || ! $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}wcpl_user_packages WHERE id = %d", $user_package_id ) );
}

function ecm_get_listing_display_tier( int $post_id ) : string {
	return ecm_listing_subscription_lapsed( $post_id ) ? 'free' : ecm_get_listing_tier( $post_id );
}

function ecm_get_listing_display_plan_name( int $post_id ) : string {
	return ecm_listing_subscription_lapsed( $post_id ) ? 'Free' : ecm_get_listing_plan_name( $post_id );
}

function ecm_lapsed_listing_ids() : array {
	$cached = get_transient( ecm_cache_key( 'lapsed' ) );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$ids = array_map( 'intval', $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p
		 JOIN {$wpdb->postmeta} lm ON lm.post_id = p.ID AND lm.meta_key = '_expired_subscription_id' AND lm.meta_value <> ''
		 WHERE p.post_type = 'job_listing' AND p.post_status = 'expired'
		   AND NOT EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} um JOIN {$wpdb->prefix}wcpl_user_packages up ON up.id = um.meta_value
			WHERE um.post_id = p.ID AND um.meta_key = '_user_package_id'
		   )"
	) );
	set_transient( ecm_cache_key( 'lapsed' ), $ids, 6 * HOUR_IN_SECONDS );
	return $ids;
}

function ecm_plan_subscriptions( array $plans ) : array {
	global $wpdb;
	$in   = implode( ',', array_map( 'intval', $plans ) );
	$oi   = $wpdb->prefix . 'woocommerce_order_items';
	$oim  = $wpdb->prefix . 'woocommerce_order_itemmeta';
	$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	if ( $hpos ) {
		$rows = $wpdb->get_results(
			"SELECT s.id AS id, s.customer_id AS uid, s.status AS status, im.meta_value AS product, np.meta_value AS next_payment, en.meta_value AS end_date
			 FROM {$wpdb->prefix}wc_orders s
			 JOIN $oi i ON i.order_id = s.id AND i.order_item_type = 'line_item'
			 JOIN $oim im ON im.order_item_id = i.order_item_id AND im.meta_key = '_product_id' AND im.meta_value IN ($in)
			 LEFT JOIN {$wpdb->prefix}wc_orders_meta np ON np.order_id = s.id AND np.meta_key = '_schedule_next_payment'
			 LEFT JOIN {$wpdb->prefix}wc_orders_meta en ON en.order_id = s.id AND en.meta_key = '_schedule_end'
			 WHERE s.type = 'shop_subscription'"
		);
	} else {
		$rows = $wpdb->get_results(
			"SELECT s.ID AS id, cu.meta_value AS uid, s.post_status AS status, im.meta_value AS product, np.meta_value AS next_payment, en.meta_value AS end_date
			 FROM {$wpdb->posts} s
			 JOIN {$wpdb->postmeta} cu ON cu.post_id = s.ID AND cu.meta_key = '_customer_user'
			 JOIN $oi i ON i.order_id = s.ID AND i.order_item_type = 'line_item'
			 JOIN $oim im ON im.order_item_id = i.order_item_id AND im.meta_key = '_product_id' AND im.meta_value IN ($in)
			 LEFT JOIN {$wpdb->postmeta} np ON np.post_id = s.ID AND np.meta_key = '_schedule_next_payment'
			 LEFT JOIN {$wpdb->postmeta} en ON en.post_id = s.ID AND en.meta_key = '_schedule_end'
			 WHERE s.post_type = 'shop_subscription'"
		);
	}
	return $rows ?: [];
}

function ecm_inactive_subscription_listing_ids() : array {
	$cached = get_transient( ecm_cache_key( 'inactive' ) );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$plans = array_values( array_intersect_key( ecm_package_product_ids(), array_flip( [ 'basic', 'standard', 'premium' ] ) ) );
	$ids   = [];
	if ( $plans ) {
		$now  = gmdate( 'Y-m-d H:i:s' );
		$set  = fn( $d ) => '' !== (string) $d && '0' !== (string) $d;
		$live = [];
		$dead = [];
		$live_subs = [];
		$dead_subs = [];
		foreach ( ecm_plan_subscriptions( $plans ) as $s ) {
			switch ( $s->status ) {
				case 'wc-on-hold':
					$ended = $set( $s->next_payment ) && $s->next_payment <= $now;
					break;
				case 'wc-cancelled':
				case 'wc-expired':
					$ended = ! $set( $s->end_date ) || $s->end_date <= $now;
					break;
				case 'wc-pending-cancel':
					$ended = $set( $s->end_date ) && $s->end_date <= $now;
					break;
				case 'wc-switched':
					continue 2;
				default:
					$ended = false;
			}
			$uid  = (int) $s->uid;
			$prod = (int) $s->product;
			if ( $ended ) {
				$dead[ $uid ][ $prod ]       = true;
				$dead_subs[ (int) $s->id ] = true;
			} else {
				$live[ $uid ][ $prod ]       = true;
				$live_subs[ (int) $s->id ] = true;
			}
		}
		if ( $dead_subs ) {
			$ids = $wpdb->get_col(
				"SELECT DISTINCT l.ID FROM {$wpdb->posts} l
				 JOIN {$wpdb->postmeta} um ON um.post_id = l.ID AND um.meta_key = '_user_package_id'
				 JOIN {$wpdb->prefix}wcpl_user_packages up ON up.id = um.meta_value
				 WHERE l.post_type = 'job_listing' AND l.post_status = 'publish' AND up.order_id IN (" . implode( ',', array_keys( $dead_subs ) ) . ')'
			);
		}
		if ( $dead ) {
			$candidates = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'job_listing' AND post_status = 'publish' AND post_author IN (" . implode( ',', array_keys( $dead ) ) . ')' ) );
			if ( $candidates ) {
				update_meta_cache( 'post', $candidates );
				$pkg_order = [];
				$pkg_prod  = [];
				foreach ( $wpdb->get_results(
					"SELECT um.post_id AS pid, up.order_id AS oid, up.product_id AS prod FROM {$wpdb->postmeta} um
					 JOIN {$wpdb->prefix}wcpl_user_packages up ON up.id = um.meta_value
					 WHERE um.meta_key = '_user_package_id' AND um.post_id IN (" . implode( ',', $candidates ) . ')'
				) as $r ) {
					$pkg_order[ (int) $r->pid ] = (int) $r->oid;
					$pkg_prod[ (int) $r->pid ]  = (int) $r->prod;
				}
				$known = array_flip( array_map( 'intval', $ids ) );
				foreach ( $candidates as $id ) {
					if ( isset( $known[ $id ] ) || isset( $live_subs[ $pkg_order[ $id ] ?? 0 ] ) ) {
						continue;
					}
					$prod = $pkg_prod[ $id ] ?? 0;
					foreach ( $prod ? [] : [ '_package_id', '_wcpl_jmfe_product_id', 'package_id', '_job_package_id' ] as $key ) {
						$value = get_post_meta( $id, $key, true );
						if ( '' !== $value && null !== $value ) {
							$prod = (int) $value;
							break;
						}
					}
					$uid = (int) get_post_field( 'post_author', $id );
					if ( in_array( $prod, $plans, true ) && ! isset( $live[ $uid ][ $prod ] ) && isset( $dead[ $uid ][ $prod ] ) ) {
						$ids[] = $id;
					}
				}
			}
		}
	}
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	set_transient( ecm_cache_key( 'inactive' ), $ids, HOUR_IN_SECONDS );
	return $ids;
}

function ecm_public_listing_status_sql( string $alias = 'p' ) : string {
	$ids = ecm_lapsed_listing_ids();
	return $ids
		? "( {$alias}.post_status = 'publish' OR {$alias}.ID IN (" . implode( ',', $ids ) . ') )'
		: "{$alias}.post_status = 'publish'";
}

function ecm_lapsed_marker_changed( $meta_id, $post_id, $meta_key ) {
	if ( '_expired_subscription_id' === $meta_key && function_exists( 'ecm_clear_search_caches' ) ) {
		ecm_clear_search_caches();
	}
}
add_action( 'added_post_meta', 'ecm_lapsed_marker_changed', 10, 3 );
add_action( 'updated_post_meta', 'ecm_lapsed_marker_changed', 10, 3 );
add_action( 'deleted_post_meta', 'ecm_lapsed_marker_changed', 10, 3 );

function ecm_lapsed_reset() {
	static $queued = false;
	delete_transient( ecm_cache_key( 'lapsed' ) );
	delete_transient( ecm_cache_key( 'inactive' ) );
	if ( $queued ) {
		return;
	}
	$queued = true;
	add_action( 'shutdown', function () {
		if ( function_exists( 'ecm_clear_search_caches' ) ) {
			ecm_clear_search_caches();
		}
	} );
}
foreach ( [
	'woocommerce_subscription_status_updated',
	'woocommerce_subscription_renewal_payment_complete',
	'woocommerce_subscription_payment_failed',
	'woocommerce_new_order',
	'woocommerce_order_status_changed',
	'woocommerce_payment_complete',
	'astoundify_wpjmlp_switched_subscription',
] as $hook ) {
	add_action( $hook, 'ecm_lapsed_reset' );
}

add_filter( 'posts_where', function ( $where, $query ) {
	if ( ! $query->get( 'ecm_include_lapsed' ) ) {
		return $where;
	}
	global $wpdb;
	return $where . ' AND ' . ecm_public_listing_status_sql( $wpdb->posts );
}, 10, 2 );

add_filter( 'posts_orderby', function ( $orderby, $query ) {
	if ( ! $query->get( 'ecm_include_lapsed' ) ) {
		return $orderby;
	}
	global $wpdb;
	$inactive = ecm_inactive_subscription_listing_ids();
	$or       = $inactive ? " OR {$wpdb->posts}.ID IN (" . implode( ',', $inactive ) . ')' : '';
	return str_replace(
		"{$wpdb->posts}.menu_order",
		"( CASE WHEN {$wpdb->posts}.post_status = 'expired'{$or} THEN 0 ELSE {$wpdb->posts}.menu_order END )",
		$orderby
	);
}, 10, 2 );

add_filter( 'post_type_link', function ( $link, $post ) {
	if ( 'job_listing' !== $post->post_type || false === strpos( $link, '?' ) || ! get_option( 'permalink_structure' ) ) {
		return $link;
	}
	if ( ! ecm_listing_subscription_lapsed( $post->ID ) ) {
		return $link;
	}
	$type = get_post_type_object( 'job_listing' );
	$base = ( $type && ! empty( $type->rewrite['slug'] ) ) ? $type->rewrite['slug'] : 'job';
	return home_url( user_trailingslashit( $base . '/' . $post->post_name ) );
}, 10, 2 );
