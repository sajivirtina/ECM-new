<?php
/**
 * ElderCareMatters Theme Functions
 *
 * Sets up theme, enqueues assets, loads ACF field definitions.
 */

defined( 'ABSPATH' ) || exit;

// ── Theme Setup ───────────────────────────────────────────────────────────────

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'gallery', 'caption', 'style', 'script' ] );
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

// ── Enqueue Styles & Scripts ──────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', function () {
    $v  = wp_get_theme()->get( 'Version' );
    $tu = get_template_directory_uri();

    // Google Fonts
    wp_enqueue_style(
        'ecm-google-fonts',
        'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500;600&display=swap',
        [],
        null
    );

    // Prototype CSS (global)
    wp_enqueue_style( 'ecm-main',      $tu . '/assets/css/main.css',      [ 'ecm-google-fonts' ], $v );
    wp_enqueue_style( 'ecm-homepage',  $tu . '/assets/css/homepagev3.css',  [ 'ecm-main' ], $v );
    wp_enqueue_style( 'ecm-intake-v2', $tu . '/assets/css/intake-v2.css', [ 'ecm-homepage' ], $v );

    // Inner-page CSS — loaded on category/search/blog/blog-detail templates
    if ( is_page_template( [ 'template-category.php', 'template-search.php', 'template-blog.php', 'template-blog-detail.php' ] ) ) {
        wp_enqueue_style( 'ecm-inner', $tu . '/assets/css/inner.css', [ 'ecm-homepage' ], $v );
    }

    // For-providers CSS — only on that template
    if ( is_page_template( 'template-for-providers.php' ) ) {
        wp_enqueue_style( 'ecm-for-providers', $tu . '/assets/css/for-providers.css', [ 'ecm-homepage' ], $v );
    }

    // Core scripts — load in footer, everywhere
    wp_enqueue_script( 'ecm-data',     $tu . '/assets/js/data.js',     [],           $v, true );
    wp_enqueue_script( 'ecm-location', $tu . '/assets/js/location.js', [ 'ecm-data' ], $v, true );
    wp_enqueue_script( 'ecm-intake',   $tu . '/assets/js/intake.js',   [ 'ecm-location' ], $v, true );
    wp_enqueue_script( 'ecm-main',     $tu . '/assets/js/main.js',     [ 'ecm-intake' ], $v, true );

    // Category page logic
    if ( is_page_template( 'template-category.php' ) ) {
        wp_enqueue_script( 'ecm-category', $tu . '/assets/js/category.js', [ 'ecm-main' ], $v, true );
    }
    // Search page logic
    if ( is_page_template( 'template-search.php' ) ) {
        wp_enqueue_script( 'ecm-search', $tu . '/assets/js/search.js', [ 'ecm-main' ], $v, true );
    }
} );

// ── ACF Field Groups ──────────────────────────────────────────────────────────

if ( function_exists( 'acf_add_local_field_group' ) ) {
    $inc = get_template_directory() . '/inc';
    require_once $inc . '/acf-fields.php';        // Homepage
    require_once $inc . '/acf-category.php';       // Category page
    require_once $inc . '/acf-search.php';         // Search page
    require_once $inc . '/acf-blog.php';           // Blog listing
    require_once $inc . '/acf-blog-detail.php';    // Blog detail
    require_once $inc . '/acf-for-providers.php';  // For providers
}

// One-time homepage content migration — seeds ACF values onto the front page.
// Idempotent + self-guarding (option flag); safe to keep loaded in production.
require_once get_template_directory() . '/inc/migrate-homepage-content.php';

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

// DIAG3: check if physical dir was created and confirm redirect destination
add_action( 'admin_notices', function() {
    $pd_dir  = '/srv/htdocs/provider-dashboard';
    $pd_file = $pd_dir . '/index.php';
    $transient = get_transient('pd_physical_dir_created');
    // Make a server-side HTTP request to see redirect headers
    $response = wp_remote_get( home_url('/provider-dashboard/'), [
        'redirection' => 0,
        'sslverify'   => false,
        'headers'     => ['Cache-Control' => 'no-cache'],
    ] );
    $code     = wp_remote_retrieve_response_code( $response );
    $location = wp_remote_retrieve_header( $response, 'location' );
    $server   = wp_remote_retrieve_header( $response, 'server' );
    echo '<div class="notice notice-info"><p><b>Diag3:</b><br>';
    echo 'pd_dir exists: '   . ( is_dir($pd_dir) ? 'YES' : 'NO' ) . '<br>';
    echo 'pd_file exists: '  . ( file_exists($pd_file) ? 'YES' : 'NO' ) . '<br>';
    echo 'transient: '       . esc_html( var_export($transient,true) ) . '<br>';
    echo 'HTTP code: '       . esc_html($code) . '<br>';
    echo 'Location header: ' . esc_html($location) . '<br>';
    echo 'Server header: '   . esc_html($server) . '<br>';
    echo '</p></div>';
} );

// ONE-TIME: create/update physical /srv/htdocs/provider-dashboard/ to bypass nginx redirect
add_action( 'init', function() {
    $pd_dir  = '/srv/htdocs/provider-dashboard';
    $pd_file = $pd_dir . '/index.php';
    if ( ! is_dir( $pd_dir ) ) {
        mkdir( $pd_dir, 0755, true );
    }
    // Always write/overwrite so we can update content via functions.php
    $lines = array(
        '<?php',
        '/** Provider Dashboard entry point.',
        ' * nginx serves this file directly; wp-blog-header runs full WP routing',
        ' * so rewrite rules set pd_section and template_include loads the controller.',
        ' */',
        'define( "WP_USE_THEMES", true );',
        'require_once "/wordpress/core/7.0/wp-blog-header.php";',
    );
    file_put_contents( $pd_file, implode( "\n", $lines ) . "\n" );
}, 1 );

// ONE-TIME: write correct bootstrap to /srv/htdocs/provider-dashboard/index.php
add_action( 'init', function() {
    $pd_dir  = '/srv/htdocs/provider-dashboard';
    $pd_file = $pd_dir . '/index.php';
    if ( ! is_dir( $pd_dir ) ) mkdir( $pd_dir, 0755, true );
    $php = '<?php' . "\n" .
           'define( "WP_USE_THEMES", true );' . "\n" .
           'require_once "/wordpress/core/7.0/wp-blog-header.php";' . "\n";
    file_put_contents( $pd_file, $php );
    if ( ! get_transient( 'pd_rewrites_v3' ) ) {
        flush_rewrite_rules( false );
        set_transient( 'pd_rewrites_v3', 1, HOUR_IN_SECONDS );
    }
}, 1 );

