<?php
/**
 * Scopes the Google Ads conversion/remarketing pixel to provider-facing pages
 * only (For Providers, /plans/, checkout, the provider dashboard) — Section 7
 * of Privacy Compliance: Developer Requirements, also flagged in the
 * Resources Changes doc: health-topic articles (dementia, hospice, memory
 * care) were sending a retargeting ping to Google Ads on every view, which
 * risks the Washington My Health My Data Act and the Nevada consumer health
 * data law.
 *
 * The pixel itself comes from the Pixel Manager for WooCommerce plugin
 * (disguised under the "WooCommerce Google AdWords Conversion Tracking Tag"
 * slug), which has no built-in per-template setting to scope its Google Ads
 * output — it's wired sitewide via several wp_head/wp_footer hooks deep in
 * the plugin. Rather than patching a third-party plugin (lost on update),
 * the whole response is buffered on disallowed pages and the Ads script is
 * stripped before it reaches the browser. Google Analytics is untouched
 * (not configured in this install today, but nothing here would strip it).
 */

defined( 'ABSPATH' ) || exit;

function ecm_is_ads_pixel_allowed_page() : bool {
	if ( is_admin() ) {
		return true;
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return true;
	}
	if ( is_page( 'for-providers' ) || is_page( 'plans' ) ) {
		return true;
	}
	if ( 0 === strpos( $_SERVER['REQUEST_URI'] ?? '', '/provider-dashboard' ) ) {
		return true;
	}
	return false;
}

add_action( 'template_redirect', function () {
	if ( ecm_is_ads_pixel_allowed_page() ) {
		return;
	}
	ob_start( 'ecm_strip_ads_pixel_output' );
}, 0 );

function ecm_strip_ads_pixel_output( string $html ) : string {
	// The external gtag.js loader for the Google Ads conversion ID. Without
	// this, no later gtag() call can do anything.
	$html = preg_replace(
		'#<script\b[^>]*\ssrc=["\'][^"\']*googletagmanager\.com/gtag/js\?id=AW-[^"\']*["\'][^>]*></script>#i',
		'',
		$html
	);
	// Google Ads' own conversion-tracking tag loader uses this host too, on
	// some setups, instead of (or alongside) googletagmanager.com.
	$html = preg_replace(
		'#<script\b[^>]*\ssrc=["\'][^"\']*googleadservices\.com[^"\']*["\'][^>]*></script>#i',
		'',
		$html
	);
	// Inline <script> blocks that configure or fire the AW- tag
	// (gtag('config','AW-…') / gtag('event','conversion', …)) — dead weight
	// once the loader above is gone, and would otherwise throw a JS error
	// calling an undefined gtag().
	$html = preg_replace_callback(
		'#<script\b[^>]*>(.*?)</script>#is',
		static function ( array $m ) : string {
			return ( false !== stripos( $m[1], "'AW-" ) || false !== stripos( $m[1], '"AW-' ) )
				? ''
				: $m[0];
		},
		$html
	);
	return $html;
}
