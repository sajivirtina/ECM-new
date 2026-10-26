<?php
/**
 * Why ECM section — all content from ACF "Why ECM" tab.
 */

$tag   = ecm_get_field( 'why_tag',   'Why Families Choose Us' );
$title = ecm_get_field( 'why_title', 'Here when you need us most.' );
$cards = get_field( 'why_cards' );
?>

<!-- Why ECM -->
<section class="why-ecm">
    <div class="section-header reveal section-header--centered section-header--why">
        <div class="section-tag section-tag--centered"><?php echo esc_html( $tag ); ?></div>
        <h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
        <div class="section-divider section-divider--centered"></div>
    </div>

    <div class="why-grid">
        <?php if ( $cards ) : ?>
            <?php foreach ( $cards as $card ) : ?>
            <div class="why-card reveal">
                <div class="why-icon"><?php echo esc_html( $card['icon'] ?? '' ); ?></div>
                <div class="why-title"><?php echo esc_html( $card['title'] ?? '' ); ?></div>
                <p class="why-desc"><?php echo esc_html( $card['description'] ?? '' ); ?></p>
            </div>
            <?php endforeach; ?>

        <?php else : ?>
        <!-- Fallback cards — edit via ACF "Why ECM" tab -->
        <div class="why-card reveal">
            <div class="why-icon">📍</div>
            <div class="why-title">Geo-aware matching</div>
            <p class="why-desc">Providers are matched to your exact city — not a generic state-wide list.</p>
        </div>
        <div class="why-card reveal">
            <div class="why-icon">✓</div>
            <div class="why-title">Verified providers</div>
            <p class="why-desc">Every provider is credential-reviewed and licensed locally before being listed.</p>
        </div>
        <div class="why-card reveal">
            <div class="why-icon">💚</div>
            <div class="why-title">Free for families</div>
            <p class="why-desc">You are never charged. Providers subscribe to the platform to reach you.</p>
        </div>
        <div class="why-card reveal">
            <div class="why-icon">🔒</div>
            <div class="why-title">Privacy-first</div>
            <p class="why-desc">Your phone and email stay hidden. A provider sees them only after asking to help with your request.</p>
        </div>
        <?php endif; ?>
    </div>
</section>
