<?php
/**
 * Provider Dashboard — controller, routing, auth gate, and read-only data helpers.
 *
 * A standalone, custom dashboard at /provider-dashboard/{section}/ that READS data
 * from WP Job Manager (job_listing posts/meta) and WooCommerce (orders), and embeds
 * WPJM's own edit/submit-listing forms in-place. It does NOT override or modify any
 * Job Manager / WooCommerce template — those stay the data layer.
 *
 * Security: every request is gated on is_user_logged_in() + provider ownership; all
 * record access is scoped to the current user (no cross-account access / IDOR).
 */

defined( 'ABSPATH' ) || exit;

const PD_REWRITE_VERSION = '1';
const PD_CACHE_TTL       = 600; // 10 min

/** Sections the dashboard knows about. Unknown sections fall back to home. */
function pd_allowed_sections() : array {
	return [ 'home', 'listings', 'profile', 'stats', 'reviews', 'billing', 'leads', 'content', 'settings', 'register', 'lost-password' ];
}

/** The current, validated dashboard section (defaults to 'home'). */
function pd_current_section() : string {
	// Allows non-pd_section pages (e.g. the WC account wrapper) to force the
	// sidebar's active state without faking a real pd_section route.
	$override = apply_filters( 'pd_current_section_override', '' );
	if ( $override ) {
		return $override;
	}
	$section = sanitize_key( (string) get_query_var( 'pd_section' ) );
	return in_array( $section, pd_allowed_sections(), true ) ? $section : 'home';
}

/** True when the dashboard route is active for this request. */
function pd_is_dashboard_request() : bool {
	return '' !== (string) get_query_var( 'pd_section' );
}

/**
 * True when the /provider-dashboard/listings/ route is rendering WP Job
 * Manager's native edit-listing form in-place (?action=edit&job_id=X), i.e.
 * frontend editing embedded directly in the dashboard rather than linking
 * out to WPJM's own Job Dashboard page.
 */
function pd_is_listing_edit_request() : bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only mode check, form itself is nonce-protected.
	return pd_is_dashboard_request() && 'listings' === pd_current_section()
		&& isset( $_GET['action'], $_GET['job_id'] ) && 'edit' === $_GET['action'];
}

/**
 * True when /provider-dashboard/listings/ is rendering WP Job Manager's
 * native submit-listing form in-place (?action=add) — the same in-dashboard
 * embed pattern as pd_is_listing_edit_request() above, so "Add a Listing"
 * no longer sends providers away to a separate page.
 *
 * Uses WPJM's own WP_Job_Manager_Form_Submit_Job class directly (same
 * extension point 'edit-job' already uses for editing) — ownership, nonce,
 * validation, and moderation-on-submit all stay WPJM's.
 *
 * Caveat: if a package-selection add-on (e.g. WC Paid Listings) gates its
 * own step on is_page( $the_real_add_listing_page ) rather than reading
 * $_GET['choose_package'] directly, that step may not appear on this
 * embedded route — because this URL isn't a query for that literal page.
 * A "continue on our full listing page" fallback link is shown next to the
 * embedded form specifically so that case has a working escape hatch;
 * remove it once you've confirmed package selection renders correctly here.
 */
function pd_is_listing_add_request() : bool {
	return pd_is_dashboard_request() && 'listings' === pd_current_section()
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only mode check; WPJM's own form is nonce-protected on submit.
		&& isset( $_GET['action'] ) && 'add' === $_GET['action'];
}

/** In-dashboard "Add Listing" URL (replaces linking out to /add-your-listing/). Carries choose_package through if present, so a Plans & Pricing link still preselects a package. */
function pd_get_add_listing_url() : string {
	$url = home_url( '/provider-dashboard/listings/?action=add' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only passthrough of a non-destructive query var.
	if ( isset( $_GET['choose_package'] ) ) {
		$url = add_query_arg( 'choose_package', sanitize_text_field( wp_unslash( $_GET['choose_package'] ) ), $url );
	}
	return $url;
}

/** Public front-end URL of the real "Add your listing" page — kept as the pre-login entry point (login/register partials) and as the in-dashboard fallback link if the embedded form can't complete a step. */
function pd_get_public_add_listing_url() : string {
	return home_url( '/add-your-listing/' );
}

/**
 * acf_form_head() must run before any HTML output (it processes $_POST on
 * submit and enqueues its own CSS/JS) — same early-hook requirement as
 * WPJM's own form assets. Only on the Edit Listing screen: that's where the
 * job_listing post already exists, so acf_form() has a real post_id to
 * attach the package-gated profile fields to (see
 * inc/acf-provider-listing-fields.php). The Add screen only handles package
 * selection + WPJM's core fields for now — rich profile fields come after,
 * once the listing (and therefore its package/tier) exists.
 */
add_action( 'wp', function () {
	if ( pd_is_listing_edit_request() && function_exists( 'acf_form_head' ) ) {
		acf_form_head();
	}
} );

/**
 * Fixes "previous listing's gallery images (and other fields) show up on
 * a brand-new Add Listing" — root cause confirmed in WPJM core source
 * (class-wp-job-manager-form-submit-job.php): the multi-step submit form
 * tracks an IN-PROGRESS submission via two cookies,
 * wp-job-manager-submitting-job-id / -job-key, so a provider can leave
 * mid-flow and resume later. That's reasonable for genuinely resuming a
 * draft, but it also means clicking "+ Add new listing" from My Listings
 * can silently RESUME a previous abandoned attempt (with all its old
 * field data, including gallery images) instead of starting fresh —
 * WPJM has no way to distinguish "resume" from "start over" on its own.
 *
 * Fix: clear these cookies (and the chosen-package ones, for the same
 * reason) specifically on a FRESH, first load of the Add screen — a
 * plain GET request with no $_POST data. Every subsequent step of the
 * SAME add flow is a POST back to this same URL, so checking "is this a
 * GET" reliably distinguishes "just clicked Add new listing" from
 * "midway through submitting" without breaking the flow itself.
 */
add_action( 'wp', function () {
	if ( ! pd_is_listing_add_request() || ! empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checking POST is empty (i.e. this is a GET), not reading any value from it.
		return;
	}
	// BUG FIXED: the original version below cleared these cookies on ANY
	// GET to the Add page — including a page REFRESH mid-flow (also a
	// GET), not just the very first "+ Add new listing" click. Refreshing
	// while on the core-fields step (after already choosing a package)
	// wiped chosen_package_id right then — silently forcing tier
	// resolution to 'free' for the rest of that session, which then
	// rejected ALL field data on save (gallery, description, AND every
	// repeater alike, since they all depend on the same tier resolution).
	// This is very likely why "fields not storing data" affected
	// everything at once, not just one field type.
	//
	// Fix: only clear when the referring page is NOT this same Add URL —
	// i.e. a genuine fresh arrival (from My Listings, or anywhere else),
	// not a reload of a page the provider is already mid-flow on.
	$referer = wp_get_referer();
	if ( $referer && false !== strpos( $referer, 'action=add' ) ) {
		return; // Referer is this same Add page — a refresh, not a fresh start. Leave cookies alone.
	}
	$cookies_to_clear = [
		'wp-job-manager-submitting-job-id',
		'wp-job-manager-submitting-job-key',
		'chosen_package_id',
		'chosen_package_is_user_package',
	];
	foreach ( $cookies_to_clear as $cookie_name ) {
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			unset( $_COOKIE[ $cookie_name ] ); // So the rest of THIS request also doesn't see the stale value.
			setcookie( $cookie_name, '', time() - HOUR_IN_SECONDS, '/' );
		}
	}
} );

/**
 * After a new listing's package is genuinely, fully assigned, jump straight
 * into Edit (where Profile Details lives) instead of WPJM's default
 * post-submission page — closes the gap between "chose a package" and
 * "customize the profile" so it feels like one flow.
 *
 * PREVIOUSLY this was a single hook on job_manager_job_submitted at
 * priority 999. That fires as soon as WPJM's CORE submission step finishes
 * — but wp-job-manager-listing-payments appends its OWN extra step
 * ('wc-process-package', added via the submit_job_steps filter) that runs
 * AFTER the core step specifically to write _package_id / _user_package_id
 * onto the new job. The old hook's redirect+exit fired before that later
 * step ever got a chance to run — so the listing was created, but its
 * package never got attached, and it had to be re-selected from Edit.
 * Confirmed live via the debug panel: a listing on "Standard (Current)"
 * was resolving to Free because only _user_package_id was ever set — the
 * write that should have accompanied it never happened.
 *
 * Fix — two hooks instead of one, each firing only when it's actually safe:
 *
 * 1. astoundify_wpjmlp_process_package_for_job_listing — this plugin's OWN
 *    action, fired at the exact moment _package_id/_user_package_id finish
 *    being written for the "already-owned package" path (no checkout
 *    involved — confirmed in Job_Manager_Submit_Form::process_package()).
 *    This is the precise, race-free signal for that path.
 *
 * 2. job_manager_job_submitted — kept only as a fallback for jobs with NO
 *    pending package step at all (the payments plugin isn't active, or the
 *    package somehow already resolved by this point) — so plain listings
 *    still redirect correctly without reintroducing the original bug.
 *
 * Deliberately NOT handled here: a NEW package purchase. That path calls
 * WC()->cart->add_to_cart() and redirects straight to WooCommerce checkout
 * — untouched by either hook below (is_user_package is false there, so
 * hook 1 skips it; hook 2 also skips it, since the payments plugin is
 * active and no package is assigned yet). After successful payment, there
 * is currently no redirect into Edit for that path — WooCommerce's own
 * thank-you page handles it instead (see wp-job-manager-listing-payments'
 * class-job-manager-wc-orders.php → woocommerce_thankyou()). If you want
 * the same "land on Edit" behaviour after a paid purchase too, that needs
 * a separate hook on order completion — ask if you want that built.
 */
add_action( 'astoundify_wpjmlp_process_package_for_job_listing', function ( $package_id, $is_user_package, $job_id ) {
	if ( ! $job_id || ! $is_user_package || ! pd_is_listing_add_request() ) {
		return; // Only the already-owned-package path; a fresh purchase must still reach checkout untouched.
	}
	wp_safe_redirect( add_query_arg( 'welcome', '1', home_url( '/provider-dashboard/listings/?action=edit&job_id=' . (int) $job_id ) ) );
	exit;
}, 10, 3 );

add_action( 'job_manager_job_submitted', function ( $job_id ) {
	if ( ! $job_id || ! pd_is_listing_add_request() ) {
		return;
	}
	$payments_plugin_active = function_exists( 'astoundify_wpjmlp_get_job_packages' );
	$already_has_package    = function_exists( 'ecm_get_listing_package_product_id' ) && null !== ecm_get_listing_package_product_id( (int) $job_id );
	if ( $payments_plugin_active && ! $already_has_package ) {
		return; // A package step may still be pending — either hook above will fire shortly, or this is a paid purchase about to redirect to checkout. Don't preempt either.
	}
	wp_safe_redirect( add_query_arg( 'welcome', '1', home_url( '/provider-dashboard/listings/?action=edit&job_id=' . (int) $job_id ) ) );
	exit;
}, 999 );

/**
 * "Save Draft" redirect — neither hook above fires for this path at all.
 * Confirmed from WPJM core source: job_manager_job_submitted only fires
 * from done_before(), the handler for the form's FINAL "done" step —
 * reached only once $this->step has been incremented all the way through.
 * submit_handler() explicitly skips incrementing $this->step when
 * $is_saving_draft is true (`if ($is_saving_draft) {...} else {
 * $this->step++; }`), so a draft save never reaches "done" and
 * job_manager_job_submitted never fires — which is exactly why the
 * provider stayed on the same Add Listing page seeing WPJM's own inline
 * "Draft was saved" message, instead of landing on Edit like every other
 * path here does.
 *
 * Hooked to job_manager_update_job_data instead — already confirmed
 * reliable for drafts (the package-counting hook above depends on this
 * same firing correctly for drafts, and does). Checking
 * $_POST['save_draft'] directly is what WPJM core itself uses internally
 * to detect this exact case ($is_saving_draft in submit_handler()).
 *
 * Uses a different query flag (draft_saved, not welcome) so the Edit
 * screen can show wording that matches what actually happened — the
 * listing is a draft, not a finished/live submission — and a Preview
 * link instead of a live "View Listing" link, since a draft has no public
 * permalink yet.
 *
 * Originally gated to pd_is_listing_add_request() (the dashboard's own
 * embedded "+ Add new listing" flow only) — widened to fire on
 * $_POST['save_draft'] alone, since the SAME dead end existed on the
 * public /add-your-listing/ page (the entry point new sign-ups and
 * existing providers with an unused package actually use): saving a
 * draft there left WPJM's own generic "Draft was saved..." message on
 * screen, linking to the legacy unstyled job-dashboard page instead of
 * landing here. $_POST['save_draft'] alone is exactly what WPJM core
 * itself checks internally to detect this case ($is_saving_draft in
 * class-wp-job-manager-form-submit-job.php), so nothing new to validate.
 */
add_action( 'job_manager_update_job_data', function ( $job_id ) {
	if ( ! $job_id || empty( $_POST['save_draft'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WPJM's own form is nonce-protected on submit; this only redirects, no state change of its own.
		return;
	}
	wp_safe_redirect( add_query_arg( 'draft_saved', '1', home_url( '/provider-dashboard/listings/?action=edit&job_id=' . (int) $job_id ) ) );
	exit;
}, 100000, 1 ); // Deliberately the highest priority of any job_manager_update_job_data hook in this theme (see inc/wpjm-manual-field-save.php, priority 999) — this must be the LAST thing that runs, or exit() here would skip the field-saving logic entirely.

/**
 * "Save Draft" fix — confirmed from WP Job Manager core source
 * (class-wp-job-manager-form-submit-job.php → submit_handler()):
 *
 *   $is_saving_draft = $this->can_continue_later() && ! empty( $_POST['save_draft'] );
 *   ...
 *   if ( $is_saving_draft ) { $post_status = 'draft'; ... }
 *   ...
 *   if ( $is_saving_draft ) { $this->add_message( 'Draft was saved...' ); }
 *   else { $this->step++; }   // ← only advances the multi-step flow when NOT a draft
 *
 * Saving a draft intentionally never advances to the next step — by
 * design, so a provider can resume later. That means
 * wp-job-manager-listing-payments' own package-assignment step
 * ('wc-process-package', which only runs on a LATER step) never executes
 * for a draft, so a package chosen on the very first step never actually
 * gets attached.
 *
 * The selection isn't lost though — the moment a package is chosen, the
 * payments plugin records it in a cookie regardless of what happens next:
 *   wc_setcookie( 'chosen_package_id', ... );
 *   wc_setcookie( 'chosen_package_is_user_package', ... );
 * (class-job-manager-submit-form.php, Job_Manager_Submit_Form::init()).
 *
 * REVISED AGAIN — astoundify_wpjmlp_approve_listing_with_package() does
 * more than just increment the count: reading it in full (functions-package.php),
 * it ALSO unconditionally sets post_status to 'publish' (or 'pending' if
 * approval is required) via wp_update_post(). That's correct for a real
 * checkout completion, but wrong here: this hook fires on EVERY save,
 * including "Save Draft" and a full submit reaching this step BEFORE
 * WPJM's own moderation setting (job_manager_submission_requires_approval)
 * gets a chance to apply — so a listing was ending up 'publish' regardless
 * of the site's actual moderation/draft setting.
 *
 * Fix: stop calling the bundled approve function. Call the plugin's own
 * lower-level astoundify_wpjmlp_increase_package_count() directly instead
 * — the actual counting logic, with none of the status-forcing baked in
 * — plus set _user_package_id ourselves (the other genuinely-needed part
 * of what the bundled function did). Post status is no longer touched by
 * this hook at all; it stays exactly whatever WPJM core's own submit_handler()
 * decided (draft / pending / publish) based on what the provider clicked
 * and the site's own moderation setting.
 *
 * Deliberately limited to the ALREADY-OWNED package case only. A brand
 * new (unpaid) purchase must still go through checkout — this never
 * grants a paid package without payment.
 */
add_action( 'job_manager_update_job_data', function ( $job_id ) {
	if ( ! $job_id ) {
		return;
	}
	if ( get_post_meta( $job_id, '_ecm_package_counted', true ) ) {
		return; // Already counted for this listing — never double-count, regardless of how meta got set.
	}

	$is_user_package    = false;
	$user_package_row_id = 0;

	// Prefer whatever the plugin's own mechanism may have already
	// resolved onto the post (covers the case where its own
	// 'wc-process-package' step DID run and set _user_package_id, but its
	// own conditional count-increment didn't fire due to a status-timing
	// mismatch) — falls back to the cookie if nothing's on the post yet
	// (the draft case, where that step never runs at all).
	$existing_user_package_id = get_post_meta( $job_id, '_user_package_id', true );
	if ( $existing_user_package_id ) {
		$is_user_package     = true;
		$user_package_row_id = (int) $existing_user_package_id;
	} elseif ( ! empty( $_COOKIE['chosen_package_id'] ) && ! empty( $_COOKIE['chosen_package_is_user_package'] ) && '1' === (string) wp_unslash( $_COOKIE['chosen_package_is_user_package'] ) ) {
		$is_user_package     = true;
		$user_package_row_id = absint( wp_unslash( $_COOKIE['chosen_package_id'] ) );
	}

	if ( ! $is_user_package || ! $user_package_row_id ) {
		return; // Not an already-owned selection — nothing safe to do here.
	}

	$job = get_post( $job_id );
	if ( ! $job ) {
		return;
	}

	// Validate the same way the plugin's own approve function does, before
	// counting anything — makes sure this package genuinely belongs to
	// this user and still has room, without touching post_status at all.
	if ( function_exists( 'astoundify_wpjmlp_package_is_valid' ) && ! astoundify_wpjmlp_package_is_valid( (int) $job->post_author, $user_package_row_id ) ) {
		return;
	}

	update_post_meta( $job_id, '_user_package_id', $user_package_row_id );
	if ( function_exists( 'astoundify_wpjmlp_increase_package_count' ) ) {
		astoundify_wpjmlp_increase_package_count( (int) $job->post_author, $user_package_row_id );
	}
	update_post_meta( $job_id, '_ecm_package_counted', 1 );
}, 10, 1 );

/**
 * True when /provider-dashboard/billing/ is rendering an order, subscription,
 * or payment-methods detail view in-place (?view=order|subscription|payment-methods).
 * Uses WooCommerce's / WC Subscriptions' own template-rendering functions —
 * same ownership checks (current_user_can('view_order', $id)) WC itself uses
 * on its native account pages — just embedded in the dashboard's chrome
 * instead of linking out to a separate page.
 */
function pd_is_billing_detail_request() : bool {
	if ( ! pd_is_dashboard_request() || 'billing' !== pd_current_section() || ! isset( $_GET['view'] ) ) {
		return false;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only mode check; WC's own action forms are separately nonce-protected.
	$view = sanitize_key( $_GET['view'] );
	if ( 'payment-methods' === $view ) {
		return true;
	}
	return in_array( $view, [ 'order', 'subscription' ], true ) && isset( $_GET['id'] );
}

function pd_on_hold_pay_url( $subscription ) : string {
	if ( ! $subscription || ! $subscription->has_status( 'on-hold' ) ) {
		return '';
	}
	$last = $subscription->get_last_order( 'all', 'any' );
	return ( $last && $last->needs_payment() ) ? $last->get_checkout_payment_url() : '';
}

function pd_on_hold_pay_amount( $subscription ) : string {
	$due = function_exists( 'ecm_overdue_renewal_amount' ) ? ecm_overdue_renewal_amount( $subscription ) : null;
	return $due ? wp_strip_all_tags( wc_price( $due['amount'] ) ) : '';
}

function pd_on_hold_notice( $subscription, bool $in_list = false ) : string {
	$due = function_exists( 'ecm_overdue_renewal_amount' ) ? ecm_overdue_renewal_amount( $subscription ) : null;
	if ( pd_on_hold_pay_url( $subscription ) && $due ) {
		return sprintf(
			"This plan is on hold because its latest renewal payment wasn't completed. Pay %s now to reactivate it through %s; the regular %s/month resumes on %s.",
			wp_strip_all_tags( wc_price( $due['amount'] ) ),
			wp_date( 'F j', $due['next'] - DAY_IN_SECONDS ),
			wp_strip_all_tags( wc_price( $due['full'] ) ),
			wp_date( 'F j', $due['next'] )
		);
	}
	if ( pd_on_hold_pay_url( $subscription ) ) {
		return "This plan is on hold because its latest renewal payment wasn't completed. Pay the outstanding renewal to reactivate it.";
	}
	if ( $subscription->can_be_updated_to( 'active' ) && ! $subscription->needs_payment() ) {
		return $in_list ? 'This plan is on hold. Open Manage Subscription to reactivate it.' : 'This plan is on hold. Use Reactivate to resume it.';
	}
	return $in_list
		? "This plan is on hold and can't be reactivated here. Open Manage Subscription to see your options."
		: "This plan is on hold and can't be reactivated here. View plans to choose a new one.";
}

add_filter( 'woocommerce_get_formatted_order_total', function ( $formatted, $order ) {
	if ( ! pd_is_billing_detail_request() || ! function_exists( 'ecm_order_prorated_amount' ) ) {
		return $formatted;
	}
	$due = ecm_order_prorated_amount( $order );
	return $due ? '<del>' . $formatted . '</del> ' . wc_price( $due['amount'] ) : $formatted;
}, 10, 2 );

add_filter( 'woocommerce_order_formatted_line_subtotal', function ( $subtotal, $item, $order ) {
	if ( ! pd_is_billing_detail_request() || ! function_exists( 'ecm_prorated_line_total' ) ) {
		return $subtotal;
	}
	$line = ecm_prorated_line_total( $item, $order );
	return null === $line ? $subtotal : '<del>' . $subtotal . '</del> ' . wc_price( $line );
}, 10, 3 );

add_filter( 'woocommerce_order_subtotal_to_display', function ( $subtotal, $compound, $order ) {
	if ( ! pd_is_billing_detail_request() || ! function_exists( 'ecm_order_prorated_amount' ) ) {
		return $subtotal;
	}
	$due = ecm_order_prorated_amount( $order );
	return $due ? '<del>' . $subtotal . '</del> ' . wc_price( $due['amount'] ) : $subtotal;
}, 10, 3 );

add_filter( 'wcs_view_subscription_actions', function ( $actions, $subscription ) {
	if ( ! pd_is_billing_detail_request() || ! $subscription->has_status( 'on-hold' ) ) {
		return $actions;
	}
	$pay_url = pd_on_hold_pay_url( $subscription );
	if ( $pay_url ) {
		$amount = pd_on_hold_pay_amount( $subscription );
		return [ 'pay' => [ 'url' => $pay_url, 'name' => $amount ? "Pay $amount now to reactivate" : 'Pay now to reactivate' ] ] + $actions;
	}
	if ( ! isset( $actions['reactivate'] ) ) {
		return [ 'view_plans' => [ 'url' => home_url( '/for-providers/' ), 'name' => 'View plans' ] ] + $actions;
	}
	return $actions;
}, 20, 2 );

add_filter( 'woocommerce_get_endpoint_url', function ( $url, $endpoint, $value ) {
	if ( ! pd_is_billing_detail_request() ) {
		return $url;
	}
	$billing = home_url( '/provider-dashboard/billing/' );
	switch ( $endpoint ) {
		case 'payment-methods':
			return add_query_arg( 'view', 'payment-methods', $billing );
		case 'delete-payment-method':
		case 'set-default-payment-method':
			return add_query_arg( [ 'view' => 'payment-methods', $endpoint => $value ], $billing );
		case 'add-payment-method':
			return trailingslashit( wc_get_page_permalink( 'myaccount' ) ) . user_trailingslashit( $endpoint );
	}
	return $url;
}, 10, 3 );

/** True when the dashboard chrome (topbar/sidebar, stripped marketing assets) should apply. */
function pd_is_dashboard_like_request() : bool {
	return pd_is_dashboard_request();
}

/**
 * Every dashboard route renders per-visitor state (logged-in vs not, which
 * provider, which listings) — never safe for a page cache (browser, a
 * caching plugin, or the host's own edge cache) to store and replay to a
 * different visitor. Confirmed live: an incognito/logged-out visitor to
 * /provider-dashboard/ saw the "You're signed in..." notice meant for
 * whoever generated the cached copy.
 */
add_action( 'send_headers', function () {
	if ( pd_is_dashboard_request() ) {
		nocache_headers();
	}
} );

// ── Routing ───────────────────────────────────────────────────────────────────

add_action( 'init', function () {
	add_rewrite_rule( '^provider-dashboard/?$', 'index.php?pd_section=home', 'top' );
	add_rewrite_rule( '^provider-dashboard/([^/]+)/?$', 'index.php?pd_section=$matches[1]', 'top' );

	if ( PD_REWRITE_VERSION !== get_option( 'pd_rewrite_version' ) ) {
		flush_rewrite_rules( false );
		update_option( 'pd_rewrite_version', PD_REWRITE_VERSION );
	}
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'pd_section';
	return $vars;
} );

// Load the dashboard controller template for dashboard requests.
add_filter( 'template_include', function ( $template ) {
	if ( ! pd_is_dashboard_request() ) {
		return $template;
	}
	$controller = get_theme_file_path( 'provider-dashboard/controller.php' );
	return file_exists( $controller ) ? $controller : $template;
} );

/**
 * Make WooCommerce's / WC Subscriptions' own is_account_page()-gated assets
 * (select2, wcs-view-subscription.css, etc.) load on the billing detail view —
 * same extension point WPJM's frontend-style filter is for; see
 * pd_is_listing_edit_request() usage below for why this matters (lazily-gated
 * plugin CSS silently no-ops when the page isn't the one it expects).
 */
add_filter( 'woocommerce_is_account_page', function ( $is_account_page ) {
	return $is_account_page || pd_is_billing_detail_request();
} );

// ── Assets (only on the dashboard route) ──────────────────────────────────────

add_action( 'wp_enqueue_scripts', function () {
	if ( ! pd_is_dashboard_like_request() ) {
		return;
	}
	$tu    = get_template_directory_uri();
	$td    = get_template_directory();
	$css_v = (int) @filemtime( $td . '/assets/css/provider.css' ) ?: wp_get_theme()->get( 'Version' );
	$js_v  = (int) @filemtime( $td . '/assets/js/provider-script.js' ) ?: wp_get_theme()->get( 'Version' );

	wp_enqueue_style( 'ecm-google-fonts',
		'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap', [], null );
	wp_enqueue_style( 'ecm-provider', $tu . '/assets/css/provider.css', [ 'ecm-google-fonts' ], $css_v );

	wp_enqueue_script( 'ecm-provider', $tu . '/assets/js/provider-script.js', [], $js_v, true );

	wp_localize_script( 'ecm-provider', 'ECM_PD', [
		'root'  => esc_url_raw( rest_url( 'ecm/v1' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	] );
}, 20 );

/**
 * WPJM only enqueues its full form stylesheet (frontend.css, covering
 * .job-manager-form buttons/inputs/select2) when is_wpjm() recognizes the
 * current page — a WPJM-registered page or one whose post_content literally
 * contains a WPJM shortcode. Our dashboard page matches neither (we call the
 * form class directly in PHP), so it silently falls back to the sparse
 * job-listings.css instead, leaving the form unstyled. WPJM ships an official
 * filter for exactly this case (see its own doc comment in class-wp-job-manager.php).
 */
add_filter( 'job_manager_enqueue_frontend_style', function ( $enabled ) {
	return $enabled || pd_is_listing_edit_request() || pd_is_listing_add_request();
} );

/**
 * WPJM's edit-job / submit-job forms normally get their CSS/JS
 * ('wp-job-manager-job-submission') enqueued lazily, from inside the form
 * class's own render call — fine on WPJM's own page, since that happens via
 * the_content() same as here. But wp_head() has ALREADY printed the page's
 * <style> tags by the time our dashboard's <body> reaches listings.php and
 * calls the form — so those styles register but never get output, leaving
 * the form completely unstyled. Force-enqueue them here, early (before
 * wp_head), same handles WPJM itself uses.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! pd_is_listing_edit_request() && ! pd_is_listing_add_request() ) {
		return;
	}
	wp_enqueue_script( 'wp-job-manager-job-submission' );
	if ( class_exists( 'WP_Job_Manager' ) ) {
		WP_Job_Manager::register_style( 'wp-job-manager-job-submission', 'css/job-submission.css', [] );
	}
	wp_enqueue_style( 'wp-job-manager-job-submission' );
}, 15 );

/**
 * The dashboard is self-contained chrome. Strip conflicting stylesheets on
 * every dashboard route so they can't conflict with provider.css.
 *
 * Two-pass strategy:
 *  1. Always strip the marketing theme CSS (main.css and all dependents) —
 *     these are never needed on any dashboard page. main.css in particular
 *     contains a bare `nav {}` rule that overrides align-items/display on the
 *     sidebar nav, breaking item alignment on the Edit Listing page.
 *  2. On standard dashboard pages (no embedded WPJM/WC forms), do a full strip
 *     and keep only ecm-provider + Google Fonts.
 *     On edit/add-listing and billing-detail pages, skip the full strip so
 *     WPJM's own assets (select2, image-upload) and WC's checkout/Stripe
 *     assets remain available.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! pd_is_dashboard_request() ) {
		return;
	}

	// Step 1 — always strip ECM marketing CSS regardless of sub-page.
	$marketing_handles = [ 'ecm-main', 'ecm-homepage', 'ecm-inner', 'ecm-for-providers', 'ecm-intake-v2', 'ecm-provider-pages' ];
	foreach ( $marketing_handles as $handle ) {
		wp_dequeue_style( $handle );
	}

	// Step 2 — on form-heavy sub-pages keep WPJM/WC assets; elsewhere do a full strip.
	if ( pd_is_listing_edit_request() || pd_is_listing_add_request() || pd_is_billing_detail_request() ) {
		return;
	}
	$keep = [ 'ecm-provider', 'ecm-google-fonts' ];
	foreach ( (array) wp_styles()->queue as $handle ) {
		if ( ! in_array( $handle, $keep, true ) ) {
			wp_dequeue_style( $handle );
		}
	}
}, 100 );

// Render native (text) emoji on the dashboard — the prototype uses text emoji, and
// WordPress's twemoji conversion turns them into oversized <img class="emoji"> blocks.
add_action( 'template_redirect', function () {
	if ( ! pd_is_dashboard_like_request() ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content', 'convert_smilies' );
	add_filter( 'emoji_svg_url', '__return_false' );

	// Hide the WP admin toolbar — the dashboard is a standalone provider UI, and we
	// strip admin CSS on this route so the bar would otherwise render unstyled.
	add_filter( 'show_admin_bar', '__return_false' );
	remove_action( 'wp_head', '_admin_bar_bump_cb' );

	// Hide the "Shop as Customer" staff-impersonation widget (WooCommerce Shop as
	// Customer plugin) — an internal support tool, not part of the provider dashboard.
	if ( class_exists( 'WC_Shop_As_Customer' ) ) {
		$shop_as_customer = WC_Shop_As_Customer::get_instance();
		remove_action( 'wp_footer', [ $shop_as_customer, 'output_switch_interface' ] );
		remove_action( 'wp_footer', [ $shop_as_customer, 'output_switched_bar' ] );
	}
} );

// ── Auth / provider identity ──────────────────────────────────────────────────

/**
 * Is the given (or current) user a provider? Owns ≥1 job_listing of ANY
 * status (draft included — pd_get_my_listing_ids() already does; a fresh
 * "Save Draft" was bouncing straight back out here since this used to be
 * published-only) OR owns ≥1 package (chosen/bought, before any listing
 * post even exists yet). Same combined definition pd_post_login_redirect_url()
 * already uses. Admins are allowed through for preview/support.
 */
function pd_is_provider( ?int $uid = null ) : bool {
	$uid = $uid ?: get_current_user_id();
	if ( ! $uid ) {
		return false;
	}
	if ( user_can( $uid, 'manage_options' ) ) {
		return true;
	}
	if ( pd_get_my_listing_ids( $uid ) ) {
		return true;
	}
	if ( function_exists( 'astoundify_wpjmlp_get_user_packages' ) ) {
		return ! empty( astoundify_wpjmlp_get_user_packages( $uid, 'job_listing' ) );
	}
	return false;
}

function pd_post_login_redirect_url( WP_User $user ) : ?string {
	if ( user_can( $user, 'manage_options' ) ) {
		return null;
	}

	if ( count_user_posts( $user->ID, 'job_listing', true ) > 0 ) {
		return home_url( '/provider-dashboard/' );
	}
	if ( function_exists( 'astoundify_wpjmlp_get_user_packages' ) ) {
		$packages = astoundify_wpjmlp_get_user_packages( $user->ID, 'job_listing' );
		if ( ! empty( $packages ) ) {
			return home_url( '/provider-dashboard/' );
		}
	}
	return home_url( '/provider-dashboard/' );
}

/**
 * The "Provider Login" flow (provider-dashboard/partials/login.php) already
 * redirects straight to the dashboard via its own [custom_login_form]
 * redirect param — these two hooks only matter for someone who logs in
 * through the generic WooCommerce My Account form (or, as a safety net,
 * wp-login.php directly) and happens to also own a listing.
 */
add_filter( 'woocommerce_login_redirect', function ( $redirect, $user ) {
	if ( ! ( $user instanceof WP_User ) || ! $user->ID ) {
		return $redirect;
	}
	return pd_post_login_redirect_url( $user ) ?? $redirect;
}, 10, 2 );

add_filter( 'login_redirect', function ( $redirect_to, $requested_redirect_to, $user ) {
	if ( ! ( $user instanceof WP_User ) || ! $user->ID ) {
		return $redirect_to;
	}
	return pd_post_login_redirect_url( $user ) ?? $redirect_to;
}, 20, 3 );

// ── Read-only data helpers (scoped to the current user; transient-cached) ──────

/**
 * IDs of the current user's own job_listings (all relevant statuses).
 * Cached per-user; invalidated on save_post_job_listing (see below).
 */
function pd_get_my_listing_ids( int $uid ) : array {
	$key    = 'pd_listings_' . $uid;
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$ids = get_posts( [
		'post_type'      => 'job_listing',
		'author'         => $uid,
		'post_status'    => [ 'publish', 'expired', 'pending', 'pending_payment', 'draft' ],
		'posts_per_page' => 500,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );
	set_transient( $key, $ids, PD_CACHE_TTL );
	return $ids;
}

/**
 * Frontend edit URL for a job_listing — rendered in-place inside
 * /provider-dashboard/listings/ via WP Job Manager's own native edit form
 * (ownership check, nonce, moderation all handled by WPJM itself; see
 * WP_Job_Manager_Form_Edit_Job / pd_is_listing_edit_request()). Does NOT
 * link out to WPJM's separate Job Dashboard page.
 */
function pd_get_listing_edit_url( int $pid ) : string {
	return add_query_arg( [ 'action' => 'edit', 'job_id' => $pid ], home_url( '/provider-dashboard/listings/' ) );
}

/** Aggregate dashboard KPIs for the user (real data; cached). */
function pd_get_kpis( int $uid ) : array {
	$key    = 'pd_kpis_' . $uid;
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$ids    = pd_get_my_listing_ids( $uid );
	$active = 0;
	$views  = 0;
	foreach ( $ids as $id ) {
		if ( 'publish' === get_post_status( $id ) ) {
			$active++;
		}
		$v = (int) get_post_meta( $id, '_wpjms_visits_total', true );
		if ( ! $v ) {
			$v = (int) get_post_meta( $id, '_count-views_all', true ); // legacy fallback
		}
		$views += $v;
	}

	$kpis = [
		'total_listings' => count( $ids ),
		'active'         => $active,
		'views'          => $views,
		'spend'          => pd_get_monthly_spend( $uid ),
	];
	set_transient( $key, $kpis, PD_CACHE_TTL );
	return $kpis;
}

/** Sum of the user's WooCommerce order totals in the last 30 days (real billing). */
function pd_get_monthly_spend( int $uid ) : float {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return 0.0;
	}
	$orders = wc_get_orders( [
		'customer_id' => $uid,
		'limit'       => 100,
		'status'      => [ 'wc-completed', 'wc-processing' ],
		'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ),
		'return'      => 'objects',
	] );
	$total = 0.0;
	foreach ( $orders as $order ) {
		$total += (float) $order->get_total();
	}
	return $total;
}

/**
 * Real WC product(s) behind the user's active subscription(s) — used to show
 * genuine plan entitlements (featured status, listing limit, duration) in
 * place of the old disconnected "Lead Preferences" UI, which referenced a
 * scoring system (LQS/LIS) that doesn't exist anywhere in this install.
 */
function pd_get_active_plan_products( int $uid ) : array {
	if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return [];
	}
	$products = [];
	foreach ( wcs_get_users_subscriptions( $uid ) as $sub ) {
		if ( ! $sub->has_status( [ 'active', 'pending-cancel' ] ) ) {
			continue;
		}
		foreach ( $sub->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$products[ $product->get_id() ] = $product;
			}
		}
	}
	return $products;
}

// ── Cache invalidation ────────────────────────────────────────────────────────

/** The provider's "primary" listing = most recent published job_listing they own. */
function pd_get_primary_listing_id( int $uid ) : int {
	$ids = get_posts( [
		'post_type'      => 'job_listing',
		'author'         => $uid,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );
	return $ids ? (int) $ids[0] : 0;
}

// ── Secure form handlers (admin-post) ─────────────────────────────────────────

/** Save provider profile → writes to the provider's primary listing. */
add_action( 'admin_post_pd_save_profile', function () {
	if ( ! is_user_logged_in() || ! pd_is_provider() ) {
		wp_die( esc_html__( 'Permission denied.', 'eldercare-matters' ), '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'pd_save_profile' );

	$uid        = get_current_user_id();
	$listing_id = (int) ( $_POST['listing_id'] ?? 0 );
	$primary    = pd_get_primary_listing_id( $uid );

	// Ownership: the posted listing must be the user's own primary listing (no IDOR).
	if ( ! $listing_id || $listing_id !== $primary || (int) get_post_field( 'post_author', $listing_id ) !== $uid ) {
		wp_die( esc_html__( 'Invalid listing.', 'eldercare-matters' ), '', [ 'response' => 403 ] );
	}

	$title   = sanitize_text_field( wp_unslash( $_POST['biz_name'] ?? '' ) );
	$desc    = wp_kses_post( wp_unslash( $_POST['biz_desc'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['biz_phone'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['biz_email'] ?? '' ) );
	$website = esc_url_raw( wp_unslash( $_POST['biz_website'] ?? '' ) );

	if ( '' !== $title ) {
		wp_update_post( [ 'ID' => $listing_id, 'post_title' => $title, 'post_content' => $desc ] );
		update_post_meta( $listing_id, '_company_name', $title );
	}
	update_post_meta( $listing_id, '_phone', $phone );
	if ( $email ) {
		update_post_meta( $listing_id, '_application', $email );
	}
	update_post_meta( $listing_id, '_company_website', $website );

	pd_flush_user_cache( $uid );
	wp_safe_redirect( add_query_arg( 'pd_notice', 'profile_saved', home_url( '/provider-dashboard/profile/' ) ) );
	exit;
} );

/** Save account + notification settings → WP user + user meta. */
add_action( 'admin_post_pd_save_settings', function () {
	if ( ! is_user_logged_in() || ! pd_is_provider() ) {
		wp_die( esc_html__( 'Permission denied.', 'eldercare-matters' ), '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'pd_save_settings' );

	$uid = get_current_user_id();

	$display = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
	if ( '' !== $display ) {
		wp_update_user( [ 'ID' => $uid, 'display_name' => $display ] );
	}

	// Notification preferences — whitelist of known keys only.
	$prefs   = [];
	$toggles = [ 'notify_lead', 'notify_purchase', 'notify_message', 'notify_billing', 'notify_updates' ];
	foreach ( $toggles as $t ) {
		$prefs[ $t ] = ! empty( $_POST[ $t ] ) ? 1 : 0;
	}
	update_user_meta( $uid, 'pd_prefs', $prefs );

	// Optional password change — requires the CURRENT password (prevents a
	// hijacked/left-open session from silently taking over the account).
	$cur_pw = (string) ( $_POST['current_password'] ?? '' );
	$pw     = (string) ( $_POST['new_password'] ?? '' );
	$pw2    = (string) ( $_POST['confirm_password'] ?? '' );
	$notice = 'settings_saved';
	if ( '' !== $pw || '' !== $pw2 ) {
		$user = get_userdata( $uid );
		if ( ! $user || ! wp_check_password( $cur_pw, $user->user_pass, $uid ) ) {
			$notice = 'current_password_wrong';
		} elseif ( $pw === $pw2 && strlen( $pw ) >= 8 ) {
			wp_set_password( $pw, $uid );
			$notice = 'password_changed'; // forces re-login (WP destroys existing sessions)
		} else {
			$notice = 'password_error';
		}
	}

	wp_safe_redirect( add_query_arg( 'pd_notice', $notice, home_url( '/provider-dashboard/settings/' ) ) );
	exit;
} );

/** Public provider registration (creates a subscriber; emails a set-password link). */
function pd_handle_register() {
	$redirect = home_url( '/provider-dashboard/register/' );
	check_admin_referer( 'pd_register' );

	// Basic per-IP throttle.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	$key = 'pd_reg_' . md5( $ip );
	if ( (int) get_transient( $key ) >= 5 ) {
		wp_safe_redirect( add_query_arg( 'pd_err', 'rate', $redirect ) );
		exit;
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );

	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );

	if ( ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'pd_err', 'email', $redirect ) );
		exit;
	}
	if ( email_exists( $email ) ) {
		wp_safe_redirect( add_query_arg( 'pd_err', 'exists', $redirect ) );
		exit;
	}

	$username = sanitize_user( current( explode( '@', $email ) ), true );
	$base = $username ?: 'provider';
	$i = 1;
	while ( username_exists( $username ) ) {
		$username = $base . $i++;
	}

	$uid = wp_insert_user( [
		'user_login'   => $username,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 20 ),
		'display_name' => $name ?: $username,
		'role'         => 'subscriber',
	] );

	if ( is_wp_error( $uid ) ) {
		wp_safe_redirect( add_query_arg( 'pd_err', 'fail', $redirect ) );
		exit;
	}

	wp_new_user_notification( $uid, null, 'user' ); // emails the new user a set-password link
	wp_safe_redirect( add_query_arg( 'pd_done', '1', $redirect ) );
	exit;
}
add_action( 'admin_post_nopriv_pd_register', 'pd_handle_register' );
add_action( 'admin_post_pd_register', 'pd_handle_register' );

function pd_flush_user_cache( int $uid ) : void {
	delete_transient( 'pd_listings_' . $uid );
	delete_transient( 'pd_kpis_' . $uid );
}

add_action( 'save_post_job_listing', function ( $post_id, $post ) {
	pd_flush_user_cache( (int) $post->post_author );
}, 10, 2 );

add_action( 'woocommerce_order_status_changed', function ( $order_id ) {
	if ( function_exists( 'wc_get_order' ) && ( $order = wc_get_order( $order_id ) ) ) {
		pd_flush_user_cache( (int) $order->get_customer_id() );
	}
} );