<?php
/**
 * Template Name: ECM — Sponsored Listings
 *
 * Remaining Pages Changes doc item 113: renamed from "Featured Directory
 * Listings" — "Featured" is banned as a public label for paid placement,
 * "Sponsored" is the approved word and is already what the shared card
 * renderer labels paid-tier cards with elsewhere on the site.
 *
 * Showcases listings on the Featured package tier, reusing the same search
 * UI/script as Find Care (template-search.php) via ECM_SEARCH_FIXED_PARAMS,
 * so every request on this page is scoped to featured=1 regardless of
 * whatever the visitor searches within it (see ecm_search_endpoint()).
 *
 * The CTA below opens a plan-details popup instead of linking straight out —
 * price/tagline/features come from ecm_get_plan_card('featured')
 * (inc/plan-cards.php), the same admin-controlled pl_cards ACF repeater the
 * Plans & Pricing page itself reads, so this can't drift out of sync with
 * it. The popup's own button matches that page's real, live "Get Started"
 * mechanism — a WooCommerce /cart/?add-to-cart={product_id} link — not the
 * older ?choose_package= link the public elder-care-experts widget uses.
 */

get_header();

$title       = ecm_get_field( 'fdl_title', 'Sponsored Listings' );
// Remaining Pages Changes doc, "two sentences to delete": the old copy
// merged "verified" with "paid placement" (16 CFR Part 465 exposure). This
// states plainly that the list is paid placement, nothing more.
$sub         = ecm_get_field( 'fdl_sub', 'Providers on this list have paid for top placement across ElderCareMatters.' );
$placeholder = ecm_get_field( 'fdl_placeholder', "Try 'memory care' or 'Sunrise'…" );
$cb_title    = ecm_get_field( 'fdl_cb_title', 'Want your business featured here?' );
// "Same-day" overstated the real window: Featured inquiry access is a fixed
// 0-24 hour window from submission, not literally the same calendar day.
$cb_sub      = ecm_get_field( 'fdl_cb_sub', 'A Featured listing puts you at the top of search and category pages, with inquiry access in the first 24 hours.' );
$cb_primary  = ecm_get_field( 'fdl_cb_primary', '⭐ Get a Featured Listing →' );

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
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current">Sponsored Listings</span>
</div>

<!-- Search Hero -->
<section class="search-hero">
    <h1><?php echo esc_html( $title ); ?></h1>
    <p><?php echo esc_html( $sub ); ?></p>
    <form class="search-bar" onsubmit="return false;" role="search">
        <div class="search-q-wrap">
            <input type="text" id="search-q" name="q" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off">
            <button type="button" id="search-q-clear" class="search-q-clear" aria-label="Clear search">&times;</button>
        </div>
        <select id="search-category" name="category">
            <option value="">All categories</option>
        </select>
        <select id="search-subcategory" name="subcategory">
            <option value="">All subcategories</option>
        </select>
        <select id="search-city" name="city">
            <option value="">All cities</option>
        </select>
        <button type="submit" class="search-bar-btn" id="search-btn">Search</button>
    </form>
</section>

<!-- Results -->
<section class="inner-section inner-section--warm" style="padding-top:28px">
    <div class="search-meta">
        <div class="search-meta-count" id="search-meta-count" style="visibility:hidden">
            <strong id="result-count">0</strong> sponsored providers
        </div>
        <div>
            <select id="sort-select" class="filter-chip" style="padding:6px 14px;background:var(--warm-white);appearance:none;background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%228%22 viewBox=%220 0 12 8%22%3E%3Cpath d=%22M1 1l5 5 5-5%22 stroke=%22%238A8A8A%22 stroke-width=%221.5%22 fill=%22none%22 stroke-linecap=%22round%22/%3E%3C/svg%3E');background-repeat:no-repeat;background-position:right 10px center;padding-right:28px;cursor:pointer">
                <!-- Remaining Pages Changes doc item 112: "Recommended" implies an
                     editorial endorsement of what are paid listings, and the
                     rating sorts have no rating data behind them yet. -->
                <option value="tier">Sort: Top placement</option>
            </select>
        </div>
    </div>

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
        <h3>No sponsored providers match that search<span id="empty-state-location"></span></h3>
        <p>Try a different category, or browse the full directory instead.</p>
        <a href="<?php echo esc_url( home_url( '/find-care/' ) ); ?>" class="btn-primary-lg">Browse all providers</a>
    </div>
</section>

<!-- CTA band -->
<div class="cta-band">
    <div>
        <h2><?php echo esc_html( $cb_title ); ?></h2>
        <p><?php echo esc_html( $cb_sub ); ?></p>
    </div>
    <div class="cta-band-actions">
        <button type="button" class="btn-primary-lg" data-open-plan-modal><?php echo esc_html( $cb_primary ); ?></button>
    </div>
</div>

<!-- Featured plan details popup -->
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
