<?php
/**
 * Single provider / job listing detail page — ROUTER.
 *
 * This file owns ALL shared data-fetching (post meta, tier resolution,
 * package-gated lp_* fields, reviews, cross-sell) computed exactly ONCE,
 * then dispatches to one of four tier-specific template-parts for the
 * actual markup:
 *
 *   template-parts/listing/free.php
 *   template-parts/listing/basic.php
 *   template-parts/listing/standard.php
 *   template-parts/listing/premium.php
 *
 * Rationale: tier designs diverge at the SECTION level, not just field
 * visibility (Basic has a locked "Services" teaser + gallery upsell
 * caption that Free doesn't; Free has no sidebar at all) — so one file per
 * tier is far easier for a designer to work in than a single file with
 * $tier checks threaded through every section. This router exists so that
 * shared data-fetching logic isn't duplicated (and doesn't drift) across
 * all four.
 *
 * A plain `require`, not get_template_part(), is used to load the
 * tier-specific file below — get_template_part() does NOT share the
 * calling file's local variables (it uses its own scope), which would
 * mean re-declaring every $variable in every template-part. `require`
 * shares this file's local scope directly, so every variable computed
 * below ($title, $tier, $lp_bio, etc.) is simply available as-is inside
 * whichever tier file gets loaded.
 *
 * STATUS: Free and Basic template-parts have been rebuilt against
 * confirmed reference designs. Standard and Premium are a faithful port
 * of the previous single-file version's content/structure — NOT yet
 * checked against a reference design the way Free/Basic were. Expect to
 * revisit those two the same way (About-as-its-own-section, any
 * upsell/locked messaging, etc.) once checked.
 */

get_header();

global $post;
the_post();

if ( function_exists( 'ecm_debug_panel' ) ) {
    ecm_debug_panel( $post->ID, 'Single Listing Page' );
}

// ── Core fields ──────────────────────────────────────────────────────────────
$title = get_the_title();

// Logo: company avatar meta (Field Editor's "Company Logo / Headshot" field)
// → coloured initials fallback. Deliberately NOT the post's featured image
// (WPJM's "Cover Image" / _featured_image is a different field — a wide
// banner photo, not a square logo/headshot — using it here was showing
// unrelated uploaded photos in the logo slot).
$logo_url = get_post_meta( $post->ID, '_company_avatar', true ) ?: '';
$initials = strtoupper( mb_substr( preg_replace( '/[^A-Za-z0-9 ]/', '', $title ), 0, 1 ) );
if ( preg_match( '/\b(\w)\w*\s+(\w)/u', $title, $m ) ) {
    $initials = strtoupper( $m[1] . $m[2] );
}
$palette = [ '#C4933A', '#4f6b53', '#7a5a1e', '#5b6864', '#A0703A', '#3f7a6a', '#8a6d3b', '#6a5acd' ];
$logo_bg = $palette[ abs( crc32( $title ) ) % count( $palette ) ];

// Location — try geolocation metas first (clean "City, ST" format); fall back
// to formatted address or raw _job_location, extracting city/state where possible.
$city  = get_post_meta( $post->ID, 'geolocation_city', true );
$state = get_post_meta( $post->ID, 'geolocation_state_short', true );
if ( $city ) {
    $location = $state ? $city . ', ' . $state : $city;
} else {
    $fmt_addr = get_post_meta( $post->ID, 'geolocation_formatted_address', true )
             ?: ( function_exists( 'get_the_job_location' ) ? get_the_job_location( false, $post ) : '' );
    if ( $fmt_addr && preg_match( '/,\s*([^,]+),\s*([A-Z]{2})\b/', $fmt_addr, $m ) ) {
        $location = trim( $m[1] ) . ', ' . trim( $m[2] );
    } else {
        $location = $fmt_addr;
    }
}

// Category terms.
$cats        = function_exists( 'wpjm_get_the_job_categories' ) ? wpjm_get_the_job_categories( $post ) : [];
$primary_cat = $cats ? $cats[0] : null;
$is_featured = function_exists( 'is_position_featured' ) && is_position_featured( $post ) && ! ecm_listing_subscription_lapsed( $post->ID );

// Company meta.
$tagline = get_post_meta( $post->ID, '_company_tagline', true );
$website = get_post_meta( $post->ID, '_company_website', true );
$phone   = get_post_meta( $post->ID, '_company_phone', true )
        ?: get_post_meta( $post->ID, '_phone', true );

// Years in service: from a founded year meta or listing date.
$founded = (int) get_post_meta( $post->ID, '_company_founded', true );
$years   = $founded > 1900
    ? ( (int) date( 'Y' ) - $founded ) . '+'
    : ( (int) date( 'Y' ) - (int) get_the_date( 'Y' ) ) . '+';

// Response time badge — mirror prototype logic.
$response = $is_featured ? 'Within 1 hour' : 'Within 24 hours';

// ── Ratings & reviews ────────────────────────────────────────────────────────
// WP Job Manager Reviews add-on stores each review as a plain top-level WP
// comment on the job_listing post (no custom comment_type — identified by
// comment meta 'review_stars'/'review_average', set in
// WPJMR_Submit::save_comment_review()), and keeps the listing's rolling
// average in post meta '_average_rating' (wpjmr_update_reviews_average()).
$raw_reviews  = get_comments( [
    'post_id' => $post->ID,
    'status'  => 'approve',
    'parent'  => 0,
    'number'  => 6,
] );
$avg_rating   = function_exists( 'wpjmr_get_reviews_average' ) ? wpjmr_get_reviews_average( $post->ID ) : 0;
$review_count = function_exists( 'wpjmr_get_reviews_count' ) ? wpjmr_get_reviews_count( $post->ID ) : count( $raw_reviews );

// Per-review rating stored as comment meta '_rating'. Shared by all tier
// template-parts and the reviews section below.
if ( ! function_exists( 'ecm_review_stars' ) ) {
    function ecm_review_stars( $rating ) {
        $rating = min( 5, max( 1, (int) $rating ) );
        return str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
    }
}

// ── Cross-sell: other categories ─────────────────────────────────────────────
$cat_icon_map = [
    'home-care'        => '🏠', 'assisted-living'  => '🏡', 'memory-care'      => '🧠',
    'elder-law'        => '⚖️', 'care-management'  => '📋', 'hospice'           => '🤝',
    'grief-counselors' => '💜',
];
$exclude_ids = $primary_cat ? [ $primary_cat->term_id ] : [];
$cross_cats  = get_terms( [
    'taxonomy'   => 'job_listing_category',
    'exclude'    => $exclude_ids,
    'hide_empty' => false,
    'number'     => 3,
    'orderby'    => 'count',
    'order'      => 'DESC',
] );

// ── Content ───────────────────────────────────────────────────────────────────
$content = get_the_content();
$excerpt = get_the_excerpt();
if ( ! $tagline && $excerpt ) {
    $tagline = wp_trim_words( $excerpt, 40, '…' );
}

// ── Package-gated profile fields (inc/acf-provider-listing-fields.php) ────────
// Rendered based on the listing's package tier (inc/package-capabilities.php).
// Every array below is truncated to the CURRENT tier's limit right here —
// not just "does the field have any data" — so a downgrade (or old data
// saved under a different package) can never display more than the
// listing's current package actually allows.
$tier            = function_exists( 'ecm_get_listing_display_tier' ) ? ecm_get_listing_display_tier( $post->ID ) : 'free';
$limits          = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];
$plan_name       = function_exists( 'ecm_get_listing_display_plan_name' ) ? ecm_get_listing_display_plan_name( $post->ID ) : '';
$plan_badge_tier = $tier;

$lp_bio = ( $limits['bio_words'] ?? 0 ) ? get_post_meta( $post->ID, '_job_description', true ) : '';
if ( $lp_bio && ! empty( $limits['bio_words'] ) ) {
    $words = preg_split( '/\s+/', trim( wp_strip_all_tags( $lp_bio ) ) );
    if ( count( $words ) > $limits['bio_words'] ) {
        $lp_bio = implode( ' ', array_slice( $words, 0, $limits['bio_words'] ) ) . '…';
    }
}
if ( ! $lp_bio && 'free' === $tier ) {
    // Free tier has no custom description field at all (bio_words is 0) —
    // a short, templated line instead, built from the listing's own name,
    // primary category, and location ($title/$primary_cat/$location are
    // already computed above, before this point in the file).
    $category_label = ( is_object( $primary_cat ) && ! empty( $primary_cat->name ) ) ? strtolower( $primary_cat->name ) : 'care';
    $lp_bio = sprintf(
        '%s offers %s services to families in the %s area. Contact the provider directly to learn more about their services and availability.',
        $title,
        $category_label,
        $location ?: 'local'
    );
}
$lp_mission    = ! empty( $limits['has_mission'] ) ? get_field( 'lp_mission', $post->ID ) : '';
$lp_highlights = in_array( $tier, [ 'standard', 'premium' ], true ) ? ( get_field( 'lp_highlights', $post->ID ) ?: [] ) : [];
$lp_website2   = ! empty( $limits['has_website'] ) ? get_field( 'lp_website', $post->ID ) : ''; // core WPJM $website above still takes priority if set

// Social media links — shown on all tiers when data is present.
$social = array_filter( [
    'facebook'  => get_field( 'lp_facebook',  $post->ID ) ?: '',
    'instagram' => get_field( 'lp_instagram', $post->ID ) ?: '',
    'linkedin'  => get_field( 'lp_linkedin',  $post->ID ) ?: '',
    'twitter'   => get_field( 'lp_twitter',   $post->ID ) ?: '',
] );

// The 5 repeating fields (testimonials/team/credentials/cities/faq) are now
// native WPJM fields (see inc/wpjm-native-repeater-fields.php), NOT ACF —
// saved as post meta under WPJM's own "_{fieldname}" convention (confirmed
// from core source), with the exact same row-shape as before (same
// sub-field key names), so nothing below this needs to change.
$lp_testimonials = array_slice( get_post_meta( $post->ID, '_lp_testimonials', true ) ?: [], 0, $limits['testimonials'] ?? 0 );
$lp_team         = array_slice( get_post_meta( $post->ID, '_lp_team', true ) ?: [], 0, $limits['team_members'] ?? 0 );
$lp_credentials  = array_slice( get_post_meta( $post->ID, '_lp_credentials', true ) ?: [], 0, $limits['credentials'] ?? 0 );
$lp_cities       = array_slice( get_post_meta( $post->ID, '_lp_cities', true ) ?: [], 0, $limits['cities'] ?? 0 );
$lp_faq          = array_slice( get_post_meta( $post->ID, '_lp_faq', true ) ?: [], 0, $limits['faq'] ?? 0 );

// Gallery is also now a native field (Field Editor's own 'gallery_images',
// tier-limited via inc/wpjm-native-fields.php) instead of ACF's lp_gallery.
// WPJM stores multi-file uploads as an array of raw URL strings (confirmed
// from core's uploaded-file-html.php) — normalized here into the same
// ['url' => ...] shape the template-parts already expect, so their
// rendering code ($img['sizes']['medium_large'] ?? $img['url']) needs no
// changes.
$lp_gallery_raw = get_post_meta( $post->ID, '_gallery_images', true ) ?: [];
$lp_gallery      = array_map( function ( $url ) { return [ 'url' => $url ]; }, array_slice( $lp_gallery_raw, 0, $limits['gallery'] ?? 0 ) );

// Articles — real linked posts (inc/listing-articles.php), not ACF rows.
// This listing's own articles show first; remaining slots (if any) are
// filled with the site's most recent global posts.
$article_cap     = $limits['featured_posts'] ?? 0;
$my_articles     = function_exists( 'ecm_get_listing_articles' ) ? ecm_get_listing_articles( $post->ID, $article_cap ) : [];
$remaining_slots = max( 0, $article_cap - count( $my_articles ) );
$fill_articles   = ( $remaining_slots > 0 && function_exists( 'ecm_get_global_fill_posts' ) )
    ? ecm_get_global_fill_posts( wp_list_pluck( $my_articles, 'ID' ), $remaining_slots )
    : [];

if ( $lp_bio ) {
    $content = '<p>' . nl2br( esc_html( $lp_bio ) ) . '</p>';
}
if ( ! $website && $lp_website2 ) {
    $website = $lp_website2;
}

// Breadcrumb category link.
$cat_url  = $primary_cat ? home_url( '/find-care/?category=' . $primary_cat->slug ) : home_url( '/find-care/' );
$cat_name = $primary_cat ? $primary_cat->name : 'Find Care';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    <span class="breadcrumb-sep">›</span>
    <a href="<?php echo esc_url( home_url( '/find-care/' ) ); ?>">Categories</a>
    <span class="breadcrumb-sep">›</span>
    <a href="<?php echo esc_url( $cat_url ); ?>"><?php echo esc_html( $cat_name ); ?></a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current"><?php echo esc_html( $title ); ?></span>
</div>

<?php
// ── Dispatch to the tier-specific template-part ────────────────────────────
$tier_template = get_template_directory() . '/template-parts/listing/' . $tier . '.php';
if ( ! file_exists( $tier_template ) ) {
    $tier_template = get_template_directory() . '/template-parts/listing/free.php'; // safe fallback
}
require $tier_template;
?>

<?php
// ── Reviews section (Basic tier and up only — ratings/reviews are not a
// Free-tier feature; see package-capabilities.php's tier gating elsewhere
// for the same Free-vs-rest split). The summary/grid only render once the
// listing has ≥1 approved review (no empty state, no zero rating) — but
// the write-a-review FORM always renders on Basic+ regardless of count.
// It has to: gating the form on review_count > 0 too (as an earlier pass
// of this did) means no listing could ever get ITS FIRST review through
// the site at all — the form is what produces that first review, not
// something to hide until one already exists. ─────────────────────────────
if ( 'free' !== $tier ) : ?>
<section class="inner-section inner-section--warm" id="reviews">
    <?php if ( $review_count > 0 ) : ?>
    <div class="inner-section-header">
        <div>
            <h2>What families are saying</h2>
            <p>
                <?php if ( $avg_rating > 0 ) : ?>
                <strong><?php echo number_format( $avg_rating, 1 ); ?></strong> average from
                <?php endif; ?>
                <strong><?php echo esc_html( $review_count ); ?></strong> verified <?php echo $review_count === 1 ? 'family' : 'families'; ?>.
            </p>
        </div>
        <div class="inner-section-aside">
            <a href="#write-a-review">Write a review →</a>
        </div>
    </div>
    <div class="review-grid">
        <?php foreach ( $raw_reviews as $review ) :
            $r_rating = (int) round( (float) get_comment_meta( $review->comment_ID, 'review_average', true ) );
            $r_date   = get_comment_date( 'M Y', $review );
        ?>
        <div class="review-card">
            <div class="review-header">
                <span class="review-author"><?php echo esc_html( $review->comment_author ); ?></span>
                <span class="review-date"><?php echo esc_html( $r_date ); ?></span>
            </div>
            <?php if ( $r_rating > 0 ) : ?>
            <div class="review-stars"><?php echo ecm_review_stars( $r_rating ); ?></div>
            <?php endif; ?>
            <p class="review-text"><?php echo wp_kses_post( $review->comment_content ); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else : ?>
    <div class="inner-section-header">
        <div>
            <h2>What families are saying</h2>
            <p>Be the first family to share your experience with <?php echo esc_html( $title ); ?>.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="review-form-wrap" id="write-a-review">
        <?php if ( function_exists( 'ecm_render_review_form' ) ) {
            ecm_render_review_form( $post->ID );
        } ?>
    </div>
</section>
<?php endif; ?>

<?php
// ── Cross-sell section (shared across tiers) ─────────────────────────────────
if ( ! empty( $cross_cats ) && ! is_wp_error( $cross_cats ) ) :
    $city_param = $city ? '&city=' . rawurlencode( $city ) : '';
?>
<div class="cross-sell" style="margin:3% 5% 64px">
    <div class="cross-sell-header">
        <div class="cross-sell-eyebrow">You may also need</div>
        <div class="cross-sell-title">Services that pair well with
            <em><?php echo esc_html( $cat_name ); ?></em>
        </div>
    </div>
    <div class="cross-sell-grid">
        <?php foreach ( $cross_cats as $cc ) :
            $icon   = $cat_icon_map[ $cc->slug ] ?? '🏥';
            $cc_url = home_url( '/category/?type=' . rawurlencode( $cc->slug ) . $city_param );
        ?>
        <a class="cross-sell-card" href="<?php echo esc_url( $cc_url ); ?>">
            <div class="cross-sell-icon"><?php echo esc_html( $icon ); ?></div>
            <div class="cross-sell-body">
                <div class="cross-sell-name"><?php echo esc_html( $cc->name ); ?></div>
                <div class="cross-sell-meta">
                    Explore <?php echo esc_html( strtolower( $cc->name ) ); ?><?php echo $city ? ' in ' . esc_html( $city ) : ' in your area'; ?>
                </div>
            </div>
            <span class="cross-sell-arrow">→</span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
// ── FAQ (shared markup — naturally empty for Free since $lp_faq is []) ───────
if ( $lp_faq ) : ?>
<section class="plans-faq">
    <div class="plans-faq-layout">
        <div class="plans-faq-left">
            <div class="section-tag">Common Questions</div>
            <h2>Frequently Asked Questions</h2>
            <p>Everything families commonly ask before getting started with <?php echo esc_html( $title ); ?>.</p>
        </div>
        <div class="faq-items">
            <?php foreach ( $lp_faq as $item ) : ?>
            <div class="faq-item">
                <div class="faq-q">
                    <?php echo esc_html( $item['question'] ?? '' ); ?>
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-a"><?php echo esc_html( $item['answer'] ?? '' ); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA band (shared across tiers) -->
<div class="cta-band">
    <div>
        <h2>Add <em><?php echo esc_html( $title ); ?></em> to your list.</h2>
        <p>Free, with no obligation.</p>
    </div>
    <div class="cta-band-actions">
        <button type="button" class="btn-primary-lg ecm-detail-select-btn" data-open-form-modal
                data-provider-id="<?php echo esc_attr( $post->ID ); ?>"
                data-provider-name="<?php echo esc_attr( $title ); ?>">
            Select This Provider
        </button>
    </div>
</div>

<?php get_footer(); ?>
