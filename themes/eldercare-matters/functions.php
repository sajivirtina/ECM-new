<?php
/**
 * ElderCareMatters Theme Functions
 *
 * Sets up theme, enqueues assets, loads ACF field definitions.
 */

defined( 'ABSPATH' ) || exit;

/**
 * TEMPORARY — package/tier debug panel toggle. Shows a small floating panel
 * on Add/Edit Listing (dashboard) and the single listing page, dumping raw
 * post meta + what the package-tier system detected from it — added to
 * diagnose why field gating isn't matching a listing's actual package.
 * Flip to false (or delete this block + the panel calls in listings.php /
 * single-job_listing.php) once package detection is confirmed working.
 */
define( 'ECM_PACKAGE_DEBUG', true );

add_action( 'template_redirect', function () {
    if ( is_admin() ) {
        return;
    }

    $path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

    $legacy_redirects = [
        'shop'                                  => '/plans/',
        'elder-care-senior-care-directory'      => '/plans/',
        'elder-care-directory'                  => '/plans/',
        'elder-care-senior-care-experts'        => '/elder-care-experts/',
    ];

    if ( ! isset( $legacy_redirects[ $path ] ) ) {
        return;
    }

    wp_safe_redirect( home_url( $legacy_redirects[ $path ] ), 301 );
    exit;
} );

// ── Theme Setup ───────────────────────────────────────────────────────────────

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'gallery', 'caption', 'style', 'script' ] );
    // Enables WP Job Manager front-end templates + makes job_listing taxonomies publicly queryable
    // (required for the job_listing_category archive pages to render).
    add_theme_support( 'job-manager-templates' );
    add_theme_support( 'custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    register_nav_menus( [
        'primary' => __( 'Primary Navigation', 'eldercare-matters' ),
        'footer'  => __( 'Footer Navigation', 'eldercare-matters' ),
    ] );
} );

// Show six rows of four posts on desktop blog listings.
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() || $query->is_feed() ) {
        return;
    }

    if ( $query->is_home() || $query->is_category() || $query->is_tag() || $query->is_author() || $query->is_search() ) {
        $query->set( 'posts_per_page', 24 );
        $query->set( 'orderby', [ 'date' => 'DESC', 'ID' => 'DESC' ] );
        $query->set( 'ignore_sticky_posts', true );
    }
} );

// ── Enqueue Styles & Scripts ──────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', function () {
    $v  = wp_get_theme()->get( 'Version' );
    $tu = get_template_directory_uri();
    $data_version = filemtime( get_template_directory() . '/assets/js/data.js' );
    $intake_version = filemtime( get_template_directory() . '/assets/js/intake.js' );
    $location_version = filemtime( get_template_directory() . '/assets/js/location.js' ); // not the static theme version: the file is cached for a year, so edits must change the URL
    $intake_style_version = filemtime( get_template_directory() . '/assets/css/intake-v2.css' );
    $category_version = filemtime( get_template_directory() . '/assets/js/category.js' );
    $search_version = filemtime( get_template_directory() . '/assets/js/search.js' );
    // inner.css is shared across several page types below and gets edited
    // often (review UI, search loading state, etc.) — filemtime like the
    // JS files above, not the static theme $v, so a change here can't get
    // stuck behind a stale cached copy at the same versioned URL.
    $inner_version = filemtime( get_template_directory() . '/assets/css/inner.css' );

    // Google Fonts
    wp_enqueue_style(
        'ecm-google-fonts',
        'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500;600&display=swap',
        [],
        null
    );

    // Prototype CSS (global)
    $main_css_version = filemtime( get_template_directory() . '/assets/css/main.css' ); // not the static theme version, so edits bust caches
    wp_enqueue_style( 'ecm-main',      $tu . '/assets/css/main.css',      [ 'ecm-google-fonts' ], $main_css_version );
    wp_enqueue_style( 'ecm-homepage',  $tu . '/assets/css/homepagev3.css',  [ 'ecm-main' ], $v );
    wp_enqueue_style( 'ecm-intake-v2', $tu . '/assets/css/intake-v2.css', [ 'ecm-homepage' ], $intake_style_version );

    // Inner-page CSS — loaded on category/search/blog/blog-detail templates, single posts, and Job Manager category archive
    // Detect /category/?type=X — template_redirect bypasses is_page_template()
    $is_category_page = isset( $_GET['type'] ) &&
        'category' === trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

    if ( is_page_template( [ 'template-category.php', 'template-search.php', 'template-blog.php', 'template-blog-detail.php' ] )
        || $is_category_page
        || is_search()
        || is_singular( 'post' )
        || is_singular( 'job_listing' )
        || is_tax( 'job_listing_category' )
        || is_page()
        || is_singular( 'product' )
        || ( function_exists( 'is_shop' ) && is_shop() ) ) { // Standard Pages + WooCommerce products/shop archive
        wp_enqueue_style( 'ecm-inner', $tu . '/assets/css/inner.css', [ 'ecm-homepage' ], $inner_version );
    }

    if ( is_404() ) {
        wp_enqueue_style( 'ecm-404', $tu . '/assets/css/404.css', [ 'ecm-homepage' ], filemtime( get_template_directory() . '/assets/css/404.css' ) );
    }

    // Blog listings styled with the current theme's design tokens.
    if ( is_page_template( [ 'template-blog.php', 'template-category.php' ] ) || $is_category_page || is_home() || is_category()
        || is_tag() || is_author() || is_search() || is_post_type_archive( 'provider_spotlight' ) ) {
        wp_enqueue_style( 'ecm-inner', $tu . '/assets/css/inner.css', [ 'ecm-homepage' ], $inner_version );
        wp_enqueue_style(
            'ecm-blog-listing',
            $tu . '/assets/css/blog-listing.css',
            [ 'ecm-inner' ],
            filemtime( get_template_directory() . '/assets/css/blog-listing.css' )
        );
    }


    // Single listing page — reuses inner.css (breadcrumb, article header/body)
    // plus provider-pages.css (badges, contact/coverage components).
    if ( is_singular( 'job_listing' ) ) {
        wp_enqueue_style( 'ecm-inner', $tu . '/assets/css/inner.css', [ 'ecm-homepage' ], $inner_version );
        wp_enqueue_style( 'ecm-provider-pages', $tu . '/assets/css/provider-pages.css', [ 'ecm-inner' ], $v );
    }

    // For-providers CSS — only on that template
    if ( is_page_template( 'template-for-providers.php' ) ) {
        wp_enqueue_style( 'ecm-for-providers', $tu . '/assets/css/for-providers.css', [ 'ecm-homepage' ], $v );
    }

    // Newsletter signup page - dedicated page hierarchy template and form layout.
    if ( is_page( 'newsletter-signup' ) || is_page_template( 'page-newsletter-signup.php' ) ) {
        $newsletter_style = get_template_directory() . '/assets/css/newsletter-signup.css';
        wp_enqueue_style(
            'ecm-newsletter-signup',
            $tu . '/assets/css/newsletter-signup.css',
            [ 'ecm-inner' ],
            filemtime( $newsletter_style )
        );
    }

    // Plans & Pricing — reuses inner.css (breadcrumb) + provider-pages.css
    // (.plan-card, .plans-table, .plans-faq components). Featured Directory
    // Listings and the Elder Care Matters Alliance page reuse the same
    // .plan-card markup for their own Featured plan widgets.
    // The shop page is served the plans template by the template_include filter below,
    // so it is matched here by is_shop() as well as by the saved page template.
    if ( is_page_template( [ 'template-plans-pricing.php', 'template-featured-listings.php', 'template-elder-care-alliance.php' ] )
        || ( function_exists( 'is_shop' ) && is_shop() ) ) {
        wp_enqueue_style( 'ecm-inner', $tu . '/assets/css/inner.css', [ 'ecm-homepage' ], $inner_version );
        wp_enqueue_style( 'ecm-provider-pages', $tu . '/assets/css/provider-pages.css', [ 'ecm-inner' ], $v );
    }

    // Core scripts — load in footer, everywhere
    wp_enqueue_script( 'ecm-data',     $tu . '/assets/js/data.js',     [],           $data_version, true );
    wp_localize_script( 'ecm-data', 'ECM_CONFIG', [
        'gsv_key' => defined( 'ECM_GOOGLE_MAPS_KEY' ) ? ECM_GOOGLE_MAPS_KEY : get_option( 'ecm_google_maps_key', '' ),
    ] );
    wp_enqueue_script( 'ecm-location', $tu . '/assets/js/location.js', [ 'ecm-data' ], $location_version, true );
    wp_enqueue_script( 'ecm-intake',   $tu . '/assets/js/intake.js',   [ 'ecm-location' ], $intake_version, true );
    $main_care_terms = get_terms( [
        'taxonomy'   => 'job_listing_category',
        'parent'     => 0,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );
    $main_care_types = [];
    if ( ! is_wp_error( $main_care_terms ) ) {
        $main_care_order = [
            'home-care'        => 0,
            'assisted-living'  => 1,
            'memory-care'      => 2,
            'elder-law'        => 3,
            'care-management'  => 4,
            'hospice'          => 5,
            'grief-counselors' => 6,
        ];
        usort( $main_care_terms, function ( $a, $b ) use ( $main_care_order ) {
            $a_order = $main_care_order[ $a->slug ] ?? 99;
            $b_order = $main_care_order[ $b->slug ] ?? 99;
            if ( $a_order === $b_order ) {
                return strcasecmp( $a->name, $b->name );
            }
            return $a_order <=> $b_order;
        } );
        foreach ( $main_care_terms as $term ) {
            $main_care_types[] = [
                'key'   => $term->slug,
                'label' => wp_strip_all_tags( html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
            ];
        }
    }
    wp_localize_script( 'ecm-intake', 'ECM_INTAKE', [
        'careTypes' => $main_care_types,
        'root'      => esc_url_raw( rest_url( 'ecm/v1' ) ),
        'assentHtml' => function_exists( 'ecm_assent_sentence_html' ) ? ecm_assent_sentence_html() : '',
    ] );
    wp_enqueue_script( 'ecm-main',     $tu . '/assets/js/main.js',     [ 'ecm-intake' ], filemtime( get_template_directory() . '/assets/js/main.js' ), true );

    // Category page logic
    if ( is_page_template( 'template-category.php' ) || $is_category_page ) {
        wp_enqueue_script( 'ecm-category', $tu . '/assets/js/category.js', [ 'ecm-main' ], $category_version, true );
        wp_localize_script( 'ecm-category', 'ECM_SEARCH_API', [
            'root' => esc_url_raw( rest_url( 'ecm/v1' ) ),
            'home' => esc_url_raw( home_url( '/' ) ),
        ] );
    }
    // Search page logic — also the Featured Directory Listings page and the
    // Elder Care Matters Alliance page, which both reuse this same
    // search UI/script scoped to featured=1 (see each template's own
    // ECM_SEARCH_FIXED_PARAMS).
    if ( is_page_template( [ 'template-search.php', 'template-featured-listings.php', 'template-elder-care-alliance.php' ] ) ) {
        // selectWoo is WooCommerce's own Select2 fork, already registered
        // site-wide by WC core — reused here for the searchable city picker
        // instead of loading a second copy of the same library.
        wp_enqueue_style( 'select2' );
        wp_enqueue_script( 'ecm-search', $tu . '/assets/js/search.js', [ 'ecm-main', 'selectWoo' ], $search_version, true );
        wp_localize_script( 'ecm-search', 'ECM_SEARCH_API', [
            'root'     => esc_url_raw( rest_url( 'ecm/v1' ) ),
            'home'     => esc_url_raw( home_url( '/' ) ),
            'cityData' => ecm_get_city_picker_data(),
            // The category list is embedded (it is cached server-side) so the page can
            // apply the saved/URL category immediately instead of waiting for /search/meta.
            'meta'     => function_exists( 'ecm_search_meta_endpoint' ) ? ecm_search_meta_endpoint()->get_data() : null,
        ] );
    }

    // Single listing page — track "Request a match" / "Chat with advisor"
    // clicks via WP Job Manager Stats' existing AJAX endpoint (see the
    // script's own doc comment for why: this template's real contact
    // buttons aren't the WPJM Apply button WPJM Stats listens for).
    if ( is_singular( 'job_listing' ) ) {
        wp_enqueue_script( 'ecm-contact-tracking', $tu . '/assets/js/contact-tracking.js', [ 'wp-util', 'ecm-intake' ], $v, true );
    }
} );

// ── ACF Field Groups ──────────────────────────────────────────────────────────

$inc = get_template_directory() . '/inc';

if ( function_exists( 'acf_add_local_field_group' ) ) {
    require_once $inc . '/acf-fields.php';        // Homepage
    require_once $inc . '/acf-category.php';       // Category page
    require_once $inc . '/acf-search.php';         // Search page
    require_once $inc . '/acf-blog.php';           // Blog listing
    require_once $inc . '/acf-blog-detail.php';    // Blog detail
    require_once $inc . '/acf-for-providers.php';  // For providers
    require_once $inc . '/acf-plans-pricing.php';  // Plans & pricing
    require_once $inc . '/acf-elder-care-alliance.php'; // Elder Care Matters Alliance page
    // acf-provider-listing-fields.php intentionally NOT loaded — superseded by
    // the native WPJM fields (inc/wpjm-native-fields.php + wpjm-native-repeater-fields.php).
    // Confirmed OK to remove: staging only, no live data on the old ACF fields.
}
require_once $inc . '/package-capabilities.php'; // Package tier config — needed outside the ACF guard too
require_once $inc . '/lapsed-listings.php'; // Display-only: listings whose subscription lapsed show as Free on the front end
require_once $inc . '/renewal-proration.php'; // Paying an overdue on-hold renewal charges the days left until the sync date, like a new sign-up
require_once $inc . '/plan-cards.php'; // Plans & Pricing's pl_cards, shared with the Featured Directory Listings popup
require_once $inc . '/listing-articles.php'; // Provider-authored articles linked to a listing (real posts, not ACF rows)
require_once $inc . '/wpjm-native-fields.php'; // Tier limits applied to WPJM/Field Editor's own native fields (gallery, description)
require_once $inc . '/wpjm-native-repeater-fields.php'; // FAQ/testimonials/team/credentials/cities as native WPJM repeater fields
require_once $inc . '/wpjm-admin-native-fields.php'; // Same native fields, visible/editable on the wp-admin job_listing edit screen too
require_once $inc . '/wpjm-cascading-category-field.php'; // Same native fields, visible/editable on the wp-admin job_listing edit screen too
require_once $inc . '/cart-recurring-tooltips.php';
require_once $inc . '/checkout-renewal-consent.php'; // 10.7: renewal consent checkbox, charge explanation, confirmation email
require_once $inc . '/listing-subscription-switch.php'; // Listing → WC Subscription lookup + native Upgrade/Downgrade (switch) URL

require_once $inc . '/ecm-verification.php'; // ECM Verified check-cycle data model + admin management screen (public badge rendering not wired up yet — see file docblock)
require_once $inc . '/wpjm-manual-field-save.php'; // Direct save fallback — WPJM's own generic save loop was confirmed (via debug panel) to silently skip these fields despite correct $_POST data
require_once $inc . '/job-listing-reviews.php'; // Review submission form (WP Job Manager Reviews add-on integration) for single-job_listing.php

// One-time homepage content migration — DISABLED: several of its seed
// strings are truncated mid-word (e.g. pcta_subtitle), and since it only
// checks whether a field exists (not whether the value is valid), a field
// it wrote to can't self-correct on a later run. Confirmed on the local DB
// copy that it already fired and wrote at least one truncated string.
// Re-enable only after the seed data in ecm_homepage_seed_data() is fixed.
// require_once get_template_directory() . '/inc/migrate-homepage-content.php';

// Provider Dashboard — custom modules over Job Manager + WooCommerce (read-only).
require_once get_template_directory() . '/inc/provider-dashboard.php';

/**
 * TinyMCE needs its assets enqueued early on the Edit Listing screen for
 * the Article content editor (Profile Details → Your Articles) to render.
 */
add_action( 'wp_enqueue_scripts', function () {
    if ( pd_is_listing_edit_request() ) {
        wp_enqueue_editor();
    }
} );

// Find Care search REST API — serves real WP Job Manager data to the search page.
require_once get_template_directory() . '/inc/search-api.php';
// Legal document versioning (Terms/Privacy) and the consent record data
// model — load before intake-api.php, which writes a consent record per submission.
require_once get_template_directory() . '/inc/legal-versions.php';
require_once get_template_directory() . '/inc/consent-record.php';
require_once get_template_directory() . '/inc/provider-signup-assent.php';
require_once get_template_directory() . '/inc/masked-payload.php';
require_once get_template_directory() . '/inc/distribution-log.php';
require_once get_template_directory() . '/inc/expedited-matching.php';
require_once get_template_directory() . '/inc/expedited-matching-admin-tool.php';
require_once get_template_directory() . '/inc/unmask-api.php';
require_once get_template_directory() . '/inc/stop-contact.php';
require_once get_template_directory() . '/inc/rights-queue.php';
// Intake form REST API — receives popup form submissions, saves leads, emails admin.
require_once get_template_directory() . '/inc/intake-api.php';

// Intake form REST API — receives popup form submissions, saves leads, emails admin.
require_once get_template_directory() . '/inc/intake-api.php';

// Email verification (6-digit code) in front of lead creation — items 14-16.
require_once get_template_directory() . '/inc/email-verification.php';

// Scopes the Google Ads conversion/remarketing pixel to provider-facing
// pages only — see file docblock.
require_once get_template_directory() . '/inc/ad-pixel-scope.php';

// Resources page search + 8 category filters (Resources Changes item 1).
require_once get_template_directory() . '/inc/resources-filters.php';

// Common ECM settings page (badge + email verification limits).
require_once get_template_directory() . '/inc/ecm-settings.php';
require_once get_template_directory() . '/inc/saved-category.php';
// Temporary content-change tool (/wp-admin/?ecm_content_changes=1). When the content is confirmed,
// delete this block and the content-migration/ folder. Safe if the folder is already gone.
if ( is_admin() && file_exists( get_template_directory() . '/content-migration/content-changes.php' ) ) {
	require_once get_template_directory() . '/content-migration/content-changes.php';
}

// ── ACF Options: make get_field() work for non-post contexts ─────────────────

add_action( 'acf/init', function () {
    // Uncomment if you want global Options Page for site-wide settings
    // acf_add_options_page( [ 'page_title' => 'Site Settings', 'menu_slug' => 'ecm-settings' ] );
} );

// ── Helper: safe field output ─────────────────────────────────────────────────

/**
 * Echo an ACF field with esc_html, or echo $fallback.
 */
function ecm_field( string $key, string $fallback = '', $post_id = false ): void {
    $val = get_field( $key, $post_id );
    echo $val ? esc_html( $val ) : esc_html( $fallback );
}

/**
 * Return an ACF field, or $fallback.
 */
function ecm_get_field( string $key, string $fallback = '', $post_id = false ): string {
    $val = get_field( $key, $post_id );
    return $val ?: $fallback;
}

/**
 * Resolve a URL stored in an ACF link field against the current site.
 *
 * Homepage nav/footer/CTA links are authored as root-relative paths
 * (e.g. "/for-providers/"), which only resolve correctly when WordPress
 * lives at the domain root. Anywhere this site is installed in a
 * subdirectory (a local dev checkout, a staging subfolder), a bare "/"
 * path resolves against the browser's origin and drops the subdirectory.
 * Route every such stored URL through here before output.
 */
function ecm_resolve_url( $url ) : string {
    $url = (string) ( $url ?? '' );
    if ( '' === $url || '#' === $url[0] || str_starts_with( $url, '//' ) ) {
        return $url;
    }
    if ( '/' === $url[0] ) {
        return home_url( $url );
    }
    return $url;
}

/**
 * Output an ACF image field as <img>.
 * $field_key: ACF image field (returns array with url, alt, etc.)
 * $fallback_url: URL to use if field is empty
 */
function ecm_img( string $key, string $fallback_url = '', string $fallback_alt = '', string $class = '', $post_id = false ): void {
    $img = get_field( $key, $post_id );
    $url = $img['url'] ?? $fallback_url;
    $alt = $img['alt'] ?? $fallback_alt;
    printf(
        '<img src="%s" alt="%s"%s>',
        esc_url( $url ),
        esc_attr( $alt ),
        $class ? ' class="' . esc_attr( $class ) . '"' : ''
    );
}

// ── Find Care page auto-create ────────────────────────────────────────────────
// Creates /find-care/ with the ECM Search template if it doesn't exist.
add_action( 'init', function () {
    if ( get_transient( 'ecm_findcare_page_ok' ) ) return;

    $existing = get_page_by_path( 'find-care', OBJECT, 'page' );
    if ( $existing && 'publish' === $existing->post_status ) {
        // Make sure the template is set correctly.
        if ( get_page_template_slug( $existing->ID ) !== 'template-search.php' ) {
            update_post_meta( $existing->ID, '_wp_page_template', 'template-search.php' );
        }
        set_transient( 'ecm_findcare_page_ok', 1, WEEK_IN_SECONDS );
        return;
    }

    $new_id = wp_insert_post( [
        'post_title'   => 'Find Care',
        'post_name'    => 'find-care',
        'post_content' => '',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => 1,
    ] );

    if ( $new_id && ! is_wp_error( $new_id ) ) {
        update_post_meta( $new_id, '_wp_page_template', 'template-search.php' );
        flush_rewrite_rules( false );
        set_transient( 'ecm_findcare_page_ok', 1, WEEK_IN_SECONDS );
    }
}, 20 );

// ── Resources page auto-create ────────────────────────────────────────────────
// Creates /resources/ with the ECM Blog template if it doesn't exist.
add_action( 'init', function () {
    if ( get_transient( 'ecm_resources_page_ok' ) ) return;

    $existing = get_page_by_path( 'resources', OBJECT, 'page' );
    if ( $existing && 'publish' === $existing->post_status ) {
        if ( get_page_template_slug( $existing->ID ) !== 'template-blog.php' ) {
            update_post_meta( $existing->ID, '_wp_page_template', 'template-blog.php' );
        }
        set_transient( 'ecm_resources_page_ok', 1, WEEK_IN_SECONDS );
        return;
    }

    $new_id = wp_insert_post( [
        'post_title'   => 'Resources & Guides',
        'post_name'    => 'resources',
        'post_content' => '',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => 1,
    ] );

    if ( $new_id && ! is_wp_error( $new_id ) ) {
        update_post_meta( $new_id, '_wp_page_template', 'template-blog.php' );
        flush_rewrite_rules( false );
        set_transient( 'ecm_resources_page_ok', 1, WEEK_IN_SECONDS );
    }
}, 20 );

// ── Provider Dashboard ────────────────────────────────────────────────────────

// Stop WordPress old-slug redirect hijacking /provider-dashboard/ after page rename.
add_filter( 'old_slug_redirect_url', function( $link ) {
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/provider-dashboard' ) !== false ) {
        return false;
    }
    return $link;
} );

add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
    if ( strpos( $requested_url, '/provider-dashboard' ) !== false ) {
        return false;
    }
    return $redirect_url;
}, 1, 2 );

// Load provider dashboard — guarded so it can only load once.
if ( ! defined( 'PD_REWRITE_VERSION' ) ) {
    require_once get_theme_file_path( 'inc/provider-dashboard.php' );
}

// ONE-TIME: create physical /srv/htdocs/provider-dashboard/index.php so nginx
// can serve it directly without redirect-looping. This entry point boots WP
// core via wp-load.php, then runs wp() + template-loader.php so rewrite rules
// still set pd_section and template_include still loads the controller —
// same effect as wp-blog-header.php, built from files that actually exist
// on this server.
//
// Guarded by a transient (like the other auto-create hooks below) so this
// only writes to disk once instead of on every single request.
add_action( 'init', function () {
    if ( get_transient( 'ecm_pd_entry_file_ok' ) ) return;

    $pd_dir  = '/srv/htdocs/provider-dashboard';
    $pd_file = $pd_dir . '/index.php';

    if ( ! is_dir( $pd_dir ) ) {
        mkdir( $pd_dir, 0755, true );
    }

    $lines = array(
        '<?php',
        '/** Provider Dashboard entry point.',
        ' * nginx serves this file directly, so we boot WP manually:',
        ' * wp-load.php boots core, wp() runs routing/rewrite rules,',
        ' * template-loader.php lets template_include load the controller.',
        ' */',
        'define( "WP_USE_THEMES", true );',
        'require_once "/srv/htdocs/wp-load.php";',
        'wp();',
        'require_once ABSPATH . WPINC . \'/template-loader.php\';',
    );
    file_put_contents( $pd_file, implode( "\n", $lines ) . "\n" );

    set_transient( 'ecm_pd_entry_file_ok', 1, WEEK_IN_SECONDS );
}, 1 );

// ── WooCommerce My Account page auto-create (local dev helper) ────────────────
add_action( 'init', function () {

    if ( get_transient( 'ecm_myaccount_page_ok' ) ) return;
    if ( ! function_exists( 'wc_get_page_id' ) ) return;

    $page_id = (int) get_option( 'woocommerce_myaccount_page_id' );
    if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
        set_transient( 'ecm_myaccount_page_ok', 1, WEEK_IN_SECONDS );
        return;
    }

    // Page missing or unpublished — create it
    $new_id = wp_insert_post( [
        'post_title'   => 'My Account',
        'post_name'    => 'myaccount',
        'post_content' => '[woocommerce_my_account]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => 1,
    ] );

    if ( $new_id && ! is_wp_error( $new_id ) ) {
        update_option( 'woocommerce_myaccount_page_id', $new_id );
        flush_rewrite_rules( false );
        set_transient( 'ecm_myaccount_page_ok', 1, WEEK_IN_SECONDS );
    }
}, 20 );

// ── Category page: intercept /category/?type=X ───────────────────────────────
// WordPress reserves /category/ as its taxonomy archive base, so a WP Page
// with slug "category" cannot exist. Instead we hook template_redirect early:
// when the path is /category/ AND ?type= is present we serve our template
// directly, bypassing the "nothing found" archive fallback.
add_action( 'template_redirect', function () {
    if ( ! isset( $_GET['type'] ) ) return;
    $path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( 'category' !== $path ) return;

    global $wp_query;
    if ( $wp_query ) {
        $wp_query->is_404 = false;
    }

    $type_slug = sanitize_key( wp_unslash( $_GET['type'] ) );
    $term      = get_term_by( 'slug', $type_slug, 'job_listing_category' );
    $category  = ( $term && ! is_wp_error( $term ) )
        ? $term->name
        : ucwords( str_replace( '-', ' ', $type_slug ) );
    $location  = isset( $_GET['city'] ) ? sanitize_text_field( wp_unslash( $_GET['city'] ) ) : '';
    if ( ! $location && isset( $_GET['state'] ) ) {
        $location = sanitize_text_field( wp_unslash( $_GET['state'] ) );
    }

    $category_document_title = function () use ( $category, $location ) {
        $title = $location
            ? sprintf( '%s in %s', $category, $location )
            : $category;
        return $title . ' - ' . get_bloginfo( 'name' );
    };

    add_filter( 'pre_get_document_title', $category_document_title, 999 );
    add_filter( 'wpseo_title', $category_document_title, 999 );
    add_filter( 'wpseo_opengraph_title', $category_document_title, 999 );

    status_header( 200 );
    include get_template_directory() . '/template-category.php';
    exit;
}, 1 );

// ── WooCommerce shop page hero ────────────────────────────────────────────────
// Inject the .ecm-shop-hero banner above the product grid on the shop archive.
add_action( 'woocommerce_before_shop_loop', function () {
    if ( ! function_exists( 'is_shop' ) || ! is_shop() ) return;
    ?>
    <div class="ecm-shop-hero">
        <div class="ecm-shop-hero-eyebrow">Provider Plans</div>
        <h1>Choose <em>Your Plan</em></h1>
        <p>Select the right listing package to connect with families seeking elder care in your area.</p>
    </div>
    <?php
}, 5 );

// The Plans & Pricing page is also the WooCommerce Shop page. WooCommerce loads its
// product archive for the shop page and ignores the page template, so the plans design
// disappears (default template + product list) whenever the template meta or slug changes.
// Always use the plans template for the shop page, whatever its slug.
add_filter( 'template_include', function ( $template ) {
    if ( function_exists( 'is_shop' ) && is_shop() ) {
        $plans = locate_template( 'template-plans-pricing.php' );
        if ( $plans ) {
            return $plans;
        }
    }
    return $template;
}, 99 );

// Remove default WooCommerce breadcrumb on shop page (ECM uses its own).
add_action( 'init', function () {
    if ( function_exists( 'is_shop' ) ) {
        remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
    }
} );

// Add 'edit-listing' body class on the provider dashboard edit listing page.
add_filter( 'body_class', function ( $classes ) {
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['job_id'] ) ) {
        $classes[] = 'edit-listing';
    }
    return $classes;
} );

// Add 'subscription-plans' body class on the subscription plan product page.
add_filter( 'body_class', function ( $classes ) {
    if ( is_singular( 'product' ) && 283438 === get_the_ID() ) {
        $classes[] = 'subscription-plans';
    }
    return $classes;
} );

// Strip the <a> wrapper from plan names in the grouped product table so they
// render as plain text. Applies to all grouped products (only one on the site).
add_filter( 'woocommerce_grouped_product_list_column_label', function ( $value, $product ) {
    return preg_replace( '/<a\b[^>]*>(.*?)<\/a>/is', '$1', $value );
}, 10, 2 );

// Hide the "From: $0" price shown for the parent grouped product.
add_filter( 'woocommerce_get_price_html', function ( $price, $product ) {
    if ( $product->is_type( 'grouped' ) ) {
        return '';
    }
    return $price;
}, 10, 2 );

// Force the theme cart template even when WooCommerce Subscriptions is active.
// WCS hooks woocommerce_locate_template at priority 10 and replaces the theme's
// override; running at priority 20 lets us restore the theme path after WCS runs.
add_filter( 'woocommerce_locate_template', function ( $template, $template_name, $template_path ) {
    if ( 'cart/cart.php' === $template_name ) {
        $theme_template = get_stylesheet_directory() . '/woocommerce/cart/cart.php';
        if ( file_exists( $theme_template ) ) {
            return $theme_template;
        }
    }
    return $template;
}, 20, 3 );

/*
add_action( 'wp_loaded', 'ecm_buy_now_redirect', 1 );
function ecm_buy_now_redirect() {
    if ( ! isset( $_GET['buy-now'] ) ) {
        return;
    }

    $product_id = absint( $_GET['buy-now'] );
    if ( ! $product_id || ! WC()->cart ) {
        return;
    }

    WC()->cart->empty_cart();
    WC()->cart->add_to_cart( $product_id );

    wp_safe_redirect( wc_get_checkout_url() );
    exit;
}
    */

add_filter( 'woocommerce_return_to_shop_redirect', 'ecm_custom_return_to_shop_url' );
function ecm_custom_return_to_shop_url( $url ) {
    return home_url( '/plans/' );
}

add_filter( 'gettext', 'ecm_change_return_to_shop_text', 20, 3 );
function ecm_change_return_to_shop_text( $translated, $original, $domain ) {
    if ( 'Return to shop' === $original && 'woocommerce' === $domain ) {
        $translated = 'Return to plans';
    }
    return $translated;
}

add_action( 'wp_loaded', 'ecm_dedupe_wc_notices', 30 );
function ecm_dedupe_wc_notices() {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    $notices = WC()->session->get( 'wc_notices', array() );
    if ( empty( $notices ) ) {
        return;
    }

    foreach ( $notices as $type => $type_notices ) {
        $seen = array();
        foreach ( $type_notices as $key => $notice ) {
            $text = is_array( $notice ) ? $notice['notice'] : $notice;
            if ( in_array( $text, $seen, true ) ) {
                unset( $notices[ $type ][ $key ] );
            } else {
                $seen[] = $text;
            }
        }
        $notices[ $type ] = array_values( $notices[ $type ] );
    }

    WC()->session->set( 'wc_notices', $notices );
}


add_action( 'woocommerce_thankyou', 'ecm_add_provider_dashboard_links', 5 );
function ecm_add_provider_dashboard_links( $order_id ) {
    if ( ! $order_id ) {
        return;
    }

    $dashboard_url = home_url( '/provider-dashboard/' );
    $listings_url  = home_url( '/provider-dashboard/listings/' );
    ?>
    <div class="ecm-provider-links" style="background:#eef3ee; border:1px solid #dbe5db; border-radius:6px; padding:16px 20px; margin-bottom:24px;">
        <p style="margin:0;">
            You can manage your listing anytime from your
            <a href="<?php echo esc_url( $dashboard_url ); ?>"><strong>Provider Dashboard</strong></a>,
            or view and edit it directly under
            <a href="<?php echo esc_url( $listings_url ); ?>"><strong>My Listings</strong></a>.
        </p>
    </div>
    <?php
}

add_action( 'woocommerce_thankyou', function () {
    ob_start();
}, 1 );

add_action( 'woocommerce_thankyou', function () {
    $html = ob_get_clean();
    if ( ! $html ) {
        return;
    }

    $legacy_id = get_option( 'job_manager_job_dashboard_page_id' );
    $legacy_url = $legacy_id ? get_permalink( $legacy_id ) : '';
    if ( $legacy_url ) {
        $html = str_replace( $legacy_url, esc_url( home_url( '/provider-dashboard/' ) ), $html );
    }

    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- re-emitting already-escaped markup captured from other woocommerce_thankyou callbacks, only that one URL is swapped.
}, 999 );

add_filter( 'woocommerce_get_view_order_url', function ( $url, $order ) {
    if ( ! $order ) {
        return $url;
    }
    $view = ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order ) ) ? 'subscription' : 'order';
    return add_query_arg( [ 'view' => $view, 'id' => $order->get_id() ], home_url( '/provider-dashboard/billing/' ) );
}, 10, 2 );

add_filter( 'wcs_get_view_subscription_url', function ( $url, $subscription_id ) {
    return add_query_arg( [ 'view' => 'subscription', 'id' => $subscription_id ], home_url( '/provider-dashboard/billing/' ) );
}, 10, 2 );

// ── Subscription Plan product: rename "Add to cart" → "Switch Subscription" ──
$ecm_sub_btn = function ( $text, $product ) {
    if ( $product && 283438 === $product->get_id() ) {
        return 'Switch Subscription';
    }
    return $text;
};
add_filter( 'woocommerce_product_add_to_cart_text',        $ecm_sub_btn, 10, 2 ); // catalog/loop
add_filter( 'woocommerce_product_single_add_to_cart_text', $ecm_sub_btn, 10, 2 ); // single product page

// ── Subscription Plan product: inject product title inside entry-summary ──
// The blog-article-header (which normally shows the H1) is hidden on this page,
// so we re-add the title at the top of the WooCommerce product summary.
add_action( 'woocommerce_single_product_summary', function () {
    if ( 283438 === get_the_ID() ) {
        echo '<h2 class="ecm-sub-plan-title">' . esc_html( get_the_title() ) . '</h2>';
    }
}, 1 );

/**
 * Central control: return false to remove the link,
 * or return a custom URL string to replace it.
 */
function my_custom_product_link( $original_url, $product = null ) {
 
    // Option A: remove the link everywhere
    return false;
 
    // Option B: one custom URL for all products
    // return home_url( '/shop/' );
 
    // Option C: per-product custom link (e.g. from a custom field)
    // if ( $product && $custom = get_post_meta( $product->get_id(), '_custom_link', true ) ) {
    //     return esc_url( $custom );
    // }
    // return $original_url;
}
 
/* 1. Cart page + mini cart (thumbnail and name links) */
add_filter( 'woocommerce_cart_item_permalink', function ( $permalink, $cart_item, $cart_item_key ) {
    return my_custom_product_link( $permalink, $cart_item['data'] );
}, 10, 3 );
 
/* 2. Order details: thank-you page, My Account > View order */
add_filter( 'woocommerce_order_item_permalink', function ( $permalink, $item, $order ) {
    $product = is_callable( [ $item, 'get_product' ] ) ? $item->get_product() : null;
    return my_custom_product_link( $permalink, $product );
}, 10, 3 );
 
/* 3. Emails and any place that wraps the name in a link via this filter */
add_filter( 'woocommerce_order_item_name', function ( $name, $item, $is_visible = true ) {
    // Strip any <a> wrapper, keep the text
    $name = wp_strip_all_tags( $name );
 
    $product = is_callable( [ $item, 'get_product' ] ) ? $item->get_product() : null;
    $url     = my_custom_product_link( '', $product );
 
    return $url ? sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $name ) ) : esc_html( $name );
}, 20, 3 );
 
/* 4. Checkout order review table (names are plain text by default,
      but some themes/plugins add links) */
add_filter( 'woocommerce_cart_item_name', function ( $name, $cart_item, $cart_item_key ) {
    if ( is_checkout() ) {
        $name = wp_strip_all_tags( $name );
    }
    return $name;
}, 20, 3 );