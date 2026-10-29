<?php
/**
 * Template Name: ECM — Search / Find Care
 *
 * Global provider search. The search bar dropdowns, results grid, and result
 * count are rendered by search.js from data.js.
 */

get_header();

$title_prefix = ecm_get_field( 'srch_title_prefix', 'Find care for someone you love in' );
$sub          = ecm_get_field( 'srch_sub', 'Search verified providers by type of care, city, or name.' );
$placeholder  = ecm_get_field( 'srch_placeholder', "Try 'memory care' or 'Sunrise'…" );
$cb_title     = ecm_get_field( 'srch_cb_title', 'Not sure who to pick?' );
$cb_sub       = ecm_get_field( 'srch_cb_sub', "Answer a few questions and we'll show you verified providers near your loved one. You choose up to 10." );
$cb_primary   = ecm_get_field( 'srch_cb_primary', 'Find Care Now' );
$cb_secondary = ecm_get_field( 'srch_cb_secondary', '💬 Chat with advisor' );
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current">Find Care</span>
</div>

<!-- Search Hero -->
<section class="search-hero">
    <h1><?php echo esc_html( $title_prefix ); ?> <span style="white-space:nowrap"><em class="js-location-city">your area</em><button type="button" class="location-edit-pin" data-open-location-modal>📍</button></span></h1>
    <p><?php echo esc_html( $sub ); ?></p>
    <form class="search-bar" onsubmit="return false;" role="search">
        <div class="search-q-wrap">
            <input type="text" id="search-q" name="q" aria-label="Search providers by name or need" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off">
            <button type="button" id="search-q-clear" class="search-q-clear" aria-label="Clear search">&times;</button>
        </div>
        <select id="search-category" name="category" aria-label="Category">
            <option value="">All categories</option>
        </select>
        <select id="search-subcategory" name="subcategory" aria-label="Subcategory">
            <option value="">All subcategories</option>
        </select>
        <select id="search-city" name="city" aria-label="City">
            <option value="">All cities</option>
        </select>
        <button type="submit" class="search-bar-btn" id="search-btn">Search</button>
    </form>
</section>

<!-- Results -->
<section class="inner-section inner-section--warm" style="padding-top:28px">
    <p class="search-pick-intro">Choose up to 10 providers to hear from first. Your contact details stay hidden until a provider asks to help.</p>
    <div class="search-meta">
        <div class="search-meta-count" id="search-meta-count" hidden style="display:none">
            <strong id="result-count">0</strong> providers matching your search
        </div>
        <div>
            <select id="sort-select" class="sort-select" aria-label="Sort results">
                <option value="tier">Sort: Recommended</option>
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
        <div class="empty-state-icon">🔍</div>
        <h3>No providers match that search yet.</h3>
        <p>Tell us what you need and we'll find the right fit.</p>
        <button type="button" class="btn-primary-lg" data-open-form-modal>Find Care Now</button>
    </div>

    <!-- Picks bar sits below the results and the "no providers" message. -->
    <div class="ecm-picks-bar" id="ecm-picks-bar" hidden>
        <div class="ecm-picks-bar-inner">
            <div class="ecm-picks-summary" id="ecm-picks-summary"><strong id="ecm-picks-count">0</strong> of 10 selected</div>
            <div class="ecm-picks-chips" id="ecm-picks-chips"></div>
            <button type="button" class="btn-primary-lg ecm-picks-continue" id="ecm-picks-btn" data-open-form-modal>Request Info from 0 Providers</button>
        </div>
        <p class="ecm-picks-message" id="ecm-picks-message" role="alert" hidden></p>
    </div>

</section>

<!-- CTA band -->
<div class="cta-band">
    <div>
        <h2><?php echo wp_kses( $cb_title, [ 'em' => [] ] ); ?></h2>
        <p><?php echo esc_html( $cb_sub ); ?></p>
    </div>
    <div class="cta-band-actions">
        <button type="button" class="btn-primary-lg" data-open-form-modal><?php echo esc_html( $cb_primary ); ?></button>
    </div>
</div>

<?php get_footer(); ?>
