<?php
/**
 * Template Name: ECM — Elder Care Matters Alliance
 *
 * Replaces the legacy page-templates/template-widgetized-seniorcareexperts.php,
 * which no longer exists in this theme — this page's own postmeta was still
 * pointing at it, so it was silently falling back to the default page
 * template (missing the Featured-tier grid and the pricing widget entirely,
 * with a stray WPJM [jobs] shortcode's default filter markup showing
 * instead). Rebuilt to match what's actually live on production
 * (https://eldercarematters.com/elder-care-senior-care-professionals/).
 *
 * The grid reuses Find Care / Featured Directory Listings' own search.js +
 * REST endpoint (scoped to featured=1 via ECM_SEARCH_FIXED_PARAMS) — just
 * without the search bar, since production shows a plain grid here, not a
 * search UI. Pagination is inherited from that same shared script.
 *
 * The Featured Listing popup reuses the exact same markup and data source
 * as the Featured Directory Listings page's own popup — ecm_get_plan_card()
 * (inc/plan-cards.php), reading the Plans & Pricing page's pl_cards ACF
 * field — so both popups always show the same price/features and can't
 * drift apart from each other or from Plans & Pricing itself.
 */

get_header();

$title = ecm_get_field( 'alc_title', 'The Elder Care Matters Alliance' );

// ── Alliance description content ────────────────────────────────────
// Remaining Pages Changes doc, Page 3: remove the unsubstantiated "TOP"
// superlative — ECM doesn't assess or rank member quality.
$c_heading  = ecm_get_field( 'alc_heading', 'A Network of Elder Care Professionals' );
$c_intro    = ecm_get_field( 'alc_intro', 'The ElderCare Matters Alliance is a national, multidisciplinary network of Elder Care Professionals who help American families plan for and deal with a wide range of Elder Care issues. This professional alliance consists of:' );
$c_items    = get_field( 'alc_items' );
if ( ! $c_items ) {
	$c_items = array_map( fn( $t ) => [ 'item' => $t ], [
		'Elder Law Attorneys', 'Estate Planning Attorneys', 'Home Care Providers', 'Probate Attorneys',
		'VA-Accredited Attorneys', 'Geriatric Care Managers', 'Real Estate Agents', 'Senior Living Communities',
		'And many other Elder Care Professionals and Elder Care Companies.',
	] );
}
// Remaining Pages Changes doc, Page 3: "TOP Elder Care Experts, with years
// of experience" and "hundreds of thousands of families" are both
// unsubstantiated — the second is a reach claim made to paying providers,
// which carries its own deception exposure since the complainant would be
// a customer. Neither is kept.
$c_body     = ecm_get_field( 'alc_body', "The members of the ElderCare Matters Alliance help families plan for and deal with their issues of aging.\n\nMembership in this Elder Care Alliance gives you the opportunity to have your articles about Elder Care issues published on ElderCareMatters.com – America’s National Directory of Elder Care Resources." );
$c_reminder = ecm_get_field( 'alc_reminder', 'REMINDER – Membership in the ElderCare Matters Alliance is provided FREE of Charge when you get a Featured Listing on ElderCareMatters.com (details below).' );

// ── Featured Listing popup — same source as Featured Directory Listings ──
$fc                   = ecm_get_plan_card( 'featured' ) ?? [];
$fc_name              = $fc['name'] ?? 'Featured';
$fc_tagline           = $fc['tagline'] ?? '';
$fc_ribbon            = $fc['ribbon'] ?? '';
$fc_price             = $fc['price'] ?? '299';
$fc_price_unit        = $fc['price_unit'] ?? '/ month';
$fc_features          = $fc['features'] ?? [];
$fc_cta_text          = $fc['btn_text'] ?? 'Get Started →';
$featured_product_id  = function_exists( 'ecm_package_product_ids' ) ? ( ecm_package_product_ids()['premium'] ?? 91 ) : 91;
$fc_cta_url           = add_query_arg( 'add-to-cart', $featured_product_id, home_url( '/cart/' ) );

$cta_title = ecm_get_field( 'alc_cta_title', 'Want to join the Alliance?' );
// "Featured" is banned as a public-facing description of paid placement
// (item 89) — the plan name itself ("Get a Featured Listing") is left as
// the real checkout product name pending the owner's naming decision on
// the Featured Directory Listings page; this sentence's own description
// of the benefit no longer repeats the word.
$cta_sub   = ecm_get_field( 'alc_cta_sub', 'Get a Featured Listing to unlock free Alliance membership, priority placement, and more.' );
?>

<!-- Hero -->
<section class="search-hero">
    <h1><?php echo esc_html( $title ); ?></h1>
</section>

<!-- Featured providers grid -->
<section class="inner-section inner-section--warm" style="padding-top:28px">
    <div class="search-loading-grid" id="search-loading-grid">
        <span class="search-spinner" aria-hidden="true"></span>
        <p>Loading providers…</p>
    </div>
    <div class="search-refresh-indicator" id="search-refresh-indicator" style="display:none">
        <span class="search-spinner" aria-hidden="true"></span>Updating results…
    </div>
    <div class="provider-grid" id="search-grid"></div>
    <div class="empty-state" id="empty-state" style="display:none">
        <div class="empty-state-icon">⭐</div>
        <h3>No featured providers to show<span id="empty-state-location"></span></h3>
    </div>
</section>

<!-- Alliance description — max-width overridden (scoped to this page only)
     so the text column matches the full width of the hero/grid sections
     above instead of blog-article-body's normal narrow reading column,
     which looked inset/misaligned next to them here. -->
<section class="blog-article-body" style="padding-top:16px;max-width:none">
    <h2><?php echo esc_html( $c_heading ); ?></h2>
    <p><?php echo esc_html( $c_intro ); ?></p>
    <ol>
        <?php foreach ( $c_items as $it ) : ?>
            <li><strong><?php echo esc_html( $it['item'] ?? '' ); ?></strong></li>
        <?php endforeach; ?>
    </ol>
    <?php foreach ( explode( "\n\n", $c_body ) as $para ) :
        if ( '' === trim( $para ) ) { continue; }
    ?>
        <p><?php echo esc_html( $para ); ?></p>
    <?php endforeach; ?>
    <p><strong><?php echo esc_html( $c_reminder ); ?></strong></p>
</section>

<!-- CTA band — opens the Featured Listing plan-details popup, same pattern
     as the Featured Directory Listings page (template-featured-listings.php),
     instead of showing the pricing card inline on the page. -->
<div class="cta-band">
    <div>
        <h2><?php echo esc_html( $cta_title ); ?></h2>
        <p><?php echo esc_html( $cta_sub ); ?></p>
    </div>
    <div class="cta-band-actions">
        <button type="button" class="btn-primary-lg" data-open-plan-modal>⭐ Get a Featured Listing →</button>
    </div>
</div>

<!-- Featured plan details popup — identical markup/data source to Featured
     Directory Listings' own popup (template-featured-listings.php). -->
<div class="modal-overlay" id="planModal">
    <div class="modal modal-lg">
        <button class="modal-close" type="button" data-close-plan-modal aria-label="Close">&times;</button>
        <div class="plan-card plan-card--featured" style="margin:0 auto;max-width:440px;border:none;box-shadow:none">
            <?php if ( $fc_ribbon ) : ?>
                <span class="plan-ribbon plan-ribbon--featured"><?php echo esc_html( $fc_ribbon ); ?></span>
            <?php endif; ?>
            <div class="plan-card-badge-row">
                <span class="plan-hero-badge plan-hero-badge--featured">Featured</span>
            </div>
            <div class="plan-card-name"><?php echo esc_html( $fc_name ); ?></div>
            <?php if ( $fc_tagline ) : ?>
                <div class="plan-card-tagline"><?php echo esc_html( $fc_tagline ); ?></div>
            <?php endif; ?>
            <div class="plan-card-price">
                <span class="plan-card-price-val"><?php echo esc_html( $fc_price ); ?></span>
                <span class="plan-card-price-unit"><?php echo esc_html( $fc_price_unit ); ?></span>
            </div>
            <ul class="plan-card-features">
                <?php foreach ( $fc_features as $feat ) : ?>
                    <li class="<?php echo ! empty( $feat['strong'] ) ? 'plan-card-feature--strong' : ''; ?>">
                        <?php echo esc_html( $feat['text'] ?? '' ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="plan-card-cta-btn" href="<?php echo esc_url( $fc_cta_url ); ?>">
                <?php echo esc_html( $fc_cta_text ); ?>
            </a>
        </div>
    </div>
</div>

<script>
window.ECM_SEARCH_FIXED_PARAMS = { featured: '1' };
(function () {
    var overlay = document.getElementById('planModal');
    if (!overlay) return;
    document.querySelectorAll('[data-open-plan-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () { overlay.classList.add('active'); });
    });
    overlay.querySelectorAll('[data-close-plan-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () { overlay.classList.remove('active'); });
    });
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) overlay.classList.remove('active');
    });
})();
</script>

<?php get_footer(); ?>
