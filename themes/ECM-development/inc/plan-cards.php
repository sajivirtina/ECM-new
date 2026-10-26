<?php
/**
 * Shared accessor for the Plans & Pricing page's `pl_cards` ACF repeater
 * (see acf-plans-pricing.php), so other templates — e.g. the Featured
 * Directory Listings page's plan popup — read the exact same
 * admin-controlled price/features instead of a second, driftable copy.
 */

defined( 'ABSPATH' ) || exit;

function ecm_get_plans_pricing_page_id() : int {
	static $page_id = null;
	if ( null !== $page_id ) {
		return $page_id;
	}

	// The Plans & Pricing template is forced onto the WooCommerce Shop page
	// by the is_shop() check in the template_include filter (functions.php),
	// not by this page's own _wp_page_template meta — that meta is "default"
	// and WordPress resets it back to "default" on every save, because
	// template-plans-pricing.php isn't a template the block editor's own
	// Template dropdown knows about (it's not declared through the normal
	// "Template Name:" discovery WP's dropdown reads, only wired in via the
	// filter). So the Shop page ID — a stable WooCommerce option, untouched
	// by saving this post — is the real source of truth, not that meta.
	if ( function_exists( 'wc_get_page_id' ) ) {
		$shop_id = wc_get_page_id( 'shop' );
		if ( $shop_id > 0 ) {
			return $page_id = $shop_id;
		}
	}

	// Fallback for a setup with no WooCommerce shop page: the old lookup.
	$cached = get_transient( 'ecm_plans_pricing_page_id' );
	if ( false !== $cached ) {
		return $page_id = (int) $cached;
	}
	global $wpdb;
	$id = (int) $wpdb->get_var(
		"SELECT post_id FROM {$wpdb->postmeta}
		 WHERE meta_key = '_wp_page_template' AND meta_value = 'template-plans-pricing.php'
		 LIMIT 1"
	);
	set_transient( 'ecm_plans_pricing_page_id', $id, HOUR_IN_SECONDS );
	return $page_id = $id;
}

/**
 * WooCommerce product IDs for each plan tier.
 * Prices on the Plans & Pricing page are pulled from these products so that
 * updating the product price in WooCommerce is the single source of truth.
 */
function ecm_plan_product_ids() : array {
	return [
		'free'     => 283094,
		'basic'    => 68641,
		'popular'  => 92,
		'featured' => 91,
	];
}

/**
 * Fetch the numeric price for a plan from its WooCommerce product.
 * Returns an empty string if the product doesn't exist or WC isn't active.
 */
function ecm_plan_price( string $style ) : string {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$ids     = ecm_plan_product_ids();
	$prod_id = $ids[ $style ] ?? 0;
	if ( ! $prod_id ) {
		return '';
	}
	$product = wc_get_product( $prod_id );
	if ( ! $product ) {
		return '';
	}
	$price = $product->get_price();
	return ( '' !== $price && null !== $price ) ? (string) $price : '';
}

function ecm_get_plan_cards() : array {
	$page_id = ecm_get_plans_pricing_page_id();
	$cards   = $page_id ? get_field( 'pl_cards', $page_id ) : false;

	// Always pull prices from WooCommerce products so the backend price field
	// is never the source of truth — changing a product price in WC is enough.
	$wc_prices = [
		'free'     => ecm_plan_price( 'free' ),
		'basic'    => ecm_plan_price( 'basic' )    ?: '79',
		'popular'  => ecm_plan_price( 'popular' )  ?: '149',
		'featured' => ecm_plan_price( 'featured' ) ?: '299',
	];

	if ( $cards ) {
		// ACF controls everything except price — override with live WC price.
		foreach ( $cards as &$card ) {
			$style = $card['style'] ?? '';
			if ( isset( $wc_prices[ $style ] ) && '' !== $wc_prices[ $style ] ) {
				$card['price'] = $wc_prices[ $style ];
			}
		}
		unset( $card );
		return $cards;
	}

	// No ACF data — use fully dynamic fallback.
	$price_free     = $wc_prices['free'];
	$price_basic    = $wc_prices['basic'];
	$price_premium  = $wc_prices['popular'];
	$price_featured = $wc_prices['featured'];

	$plans_base_url = home_url( '/add-your-listing/' );
	return [
		[
			'name' => 'Free', 'tagline' => 'A simple, free listing so families can find you.',
			'style' => 'free', 'ribbon' => '', 'price' => $price_free ?: '0', 'price_unit' => '/ forever',
			'features' => [
				[ 'text' => 'Standard directory listing', 'strong' => false ],
				[ 'text' => 'Business name, address & phone', 'strong' => false ],
				[ 'text' => 'One service category', 'strong' => false ],
				[ 'text' => 'Family inquiries released after 5 days', 'strong' => false ],
				[ 'text' => '1 article slot/yr — write it yourself or $350 we write it', 'strong' => false ],
			],
			'btn_text' => 'Start Free →', 'btn_url' => '#',
		],
		[
			'name' => 'Basic', 'tagline' => 'Stronger visibility across your full service area.',
			'style' => 'basic', 'ribbon' => '', 'price' => $price_basic, 'price_unit' => '/ month',
			'features' => [
				[ 'text' => 'Everything in Free', 'strong' => false ],
				[ 'text' => 'Up to 3 service categories', 'strong' => true ],
				[ 'text' => 'Photo gallery & extended bio', 'strong' => false ],
				[ 'text' => 'Priority placement in search', 'strong' => false ],
				[ 'text' => 'Family inquiries released after 48 hours', 'strong' => true ],
				[ 'text' => '1 article slot/yr — $250 if we write it', 'strong' => false ],
			],
			'btn_text' => 'Choose Basic →', 'btn_url' => $plans_base_url . '?choose_package=68641',
		],
		[
			'name' => 'Premium', 'tagline' => 'Maximum reach with faster access to family inquiries.',
			'style' => 'popular', 'ribbon' => 'Most Popular', 'price' => $price_premium, 'price_unit' => '/ month',
			'features' => [
				[ 'text' => 'Everything in Basic', 'strong' => false ],
				[ 'text' => 'Unlimited service categories', 'strong' => true ],
				[ 'text' => 'Featured badge on listing', 'strong' => false ],
				[ 'text' => 'Homepage & category spotlight', 'strong' => false ],
				[ 'text' => 'Family inquiries released after 24 hours', 'strong' => true ],
				[ 'text' => '2 article slots/yr — $200 if we write it', 'strong' => false ],
			],
			'btn_text' => 'Choose Premium →', 'btn_url' => $plans_base_url . '?choose_package=92',
		],
		[
			'name' => 'Featured', 'tagline' => 'Top-tier placement and same-day family inquiry access.',
			'style' => 'featured', 'ribbon' => 'Best Value', 'price' => $price_featured, 'price_unit' => '/ month',
			'features' => [
				[ 'text' => 'Everything in Premium', 'strong' => false ],
				[ 'text' => 'Family inquiries same day', 'strong' => true ],
				[ 'text' => 'Top placement across the directory', 'strong' => true ],
				[ 'text' => '4 article slots/yr — $150 if we write it', 'strong' => true ],
				[ 'text' => 'Dedicated account manager', 'strong' => false ],
				[ 'text' => 'Custom onboarding & training', 'strong' => false ],
			],
			'btn_text' => 'Choose Featured →', 'btn_url' => $plans_base_url . '?choose_package=91',
		],
	];
}

function ecm_get_plan_card( string $style ) : ?array {
	foreach ( ecm_get_plan_cards() as $card ) {
		if ( ( $card['style'] ?? '' ) === $style ) {
			return $card;
		}
	}
	return null;
}
