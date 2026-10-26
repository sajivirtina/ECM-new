<?php
/**
 * Provider card for a WP Job Manager listing.
 *
 * Mirrors the prototype .provider-card design (assets/css/inner.css) but maps to
 * real job_listing fields. No rating/price (that data does not exist) — instead:
 * logo, company name, location, category chips, Featured badge, "View details".
 *
 * Expects the global $post to be the current job_listing in the loop.
 */

defined( 'ABSPATH' ) || exit;

global $post;

$title = get_the_title();

// Logo: featured image → _company_avatar URL → coloured initials fallback.
$logo_url = has_post_thumbnail() ? get_the_post_thumbnail_url( $post, 'thumbnail' ) : '';
if ( ! $logo_url ) {
    $avatar = get_post_meta( $post->ID, '_company_avatar', true );
    if ( $avatar ) {
        $logo_url = $avatar;
    }
}

// Initials + a stable colour from the title (used only when no logo image exists).
$initials = strtoupper( mb_substr( preg_replace( '/[^A-Za-z0-9 ]/', '', $title ), 0, 1 ) );
if ( preg_match( '/\b(\w)\w*\s+(\w)/u', $title, $m ) ) {
    $initials = strtoupper( $m[1] . $m[2] );
}
$palette = [ '#C4933A', '#4f6b53', '#7a5a1e', '#5b6864', '#A0703A', '#3f7a6a', '#8a6d3b', '#6a5acd' ];
$logo_bg = $palette[ abs( crc32( $title ) ) % count( $palette ) ];

// Location: prefer geocoded city/state, fall back to the raw job location string.
$city  = get_post_meta( $post->ID, 'geolocation_city', true );
$state = get_post_meta( $post->ID, 'geolocation_state_short', true );
if ( $city ) {
    $location = $state ? $city . ', ' . $state : $city;
} else {
    $location = function_exists( 'get_the_job_location' ) ? get_the_job_location( false, $post ) : '';
}

$is_featured = function_exists( 'is_position_featured' ) && is_position_featured( $post ) && ! ecm_listing_subscription_lapsed( $post->ID );

// Category terms → specialty chips (max 3).
$cats = function_exists( 'wpjm_get_the_job_categories' ) ? wpjm_get_the_job_categories( $post ) : [];
?>
<a class="provider-card<?php echo $is_featured ? ' provider-card--featured' : ''; ?>" href="<?php the_permalink(); ?>">
    <div class="provider-card-header">
        <?php if ( $logo_url ) : ?>
            <div class="provider-logo" style="background:#fff;overflow:hidden;padding:0">
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $title ); ?>"
                     style="width:100%;height:100%;object-fit:cover;border-radius:14px" loading="lazy">
            </div>
        <?php else : ?>
            <div class="provider-logo" style="background:<?php echo esc_attr( $logo_bg ); ?>;color:#fff;font-weight:700"><?php echo esc_html( $initials ); ?></div>
        <?php endif; ?>

        <div class="provider-heading">
            <div class="provider-card-name"><?php echo esc_html( $title ); ?></div>
            <?php if ( $location ) : ?>
            <div class="provider-card-city">📍 <?php echo esc_html( $location ); ?></div>
            <?php endif; ?>
        </div>

        <?php if ( $is_featured ) : ?>
        <span class="provider-tier-badge tier-featured">featured</span>
        <?php endif; ?>
    </div>

    <div class="provider-card-body">
        <?php if ( $cats ) : ?>
        <div class="provider-specialties">
            <?php foreach ( array_slice( $cats, 0, 3 ) as $cat ) : ?>
            <span class="specialty-chip"><?php echo esc_html( $cat->name ); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="provider-card-footer">
        <span class="provider-card-arrow">View details →</span>
    </div>
</a>
