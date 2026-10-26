<?php
/**
 * Provider CTA section — all content from ACF "Provider CTA" tab.
 */

$tag           = ecm_get_field( 'pcta_tag',            'For Providers' );
$title         = ecm_get_field( 'pcta_title',          'Are you an elder care provider?' );
$subtitle      = ecm_get_field( 'pcta_subtitle',       'Join 500+ verified providers already using ElderCareMatters to receive scored, geo-matched leads from families actively searching for your services. Measurable ROI. No guesswork.' );
$primary_text  = ecm_get_field( 'pcta_primary_text',   'See Plans & Pricing →' );
$primary_url   = ecm_resolve_url( ecm_get_field( 'pcta_primary_url',    home_url( '/plans/' ) ) );
$secondary_text = is_user_logged_in() ? 'Provider Dashboard' : ecm_get_field( 'pcta_secondary_text', 'Provider Login' );
$secondary_url  = ecm_resolve_url( ecm_get_field( 'pcta_secondary_url',  home_url( '/provider-dashboard/' ) ) );
$pcta_stats    = get_field( 'pcta_stats' );
?>

<!-- Provider CTA -->
<div class="provider-cta reveal" id="provider-cta">
    <div class="cta-left">
        <div class="section-tag"><?php echo esc_html( $tag ); ?></div>
        <h2 class="cta-title"><?php echo esc_html( $title ); ?></h2>
        <p class="cta-sub"><?php echo esc_html( $subtitle ); ?></p>
        <div class="cta-btns">
            <a href="<?php echo esc_url( $primary_url ); ?>" class="btn-dark">
                <?php echo esc_html( $primary_text ); ?>
            </a>
            <a href="<?php echo esc_url( $secondary_url ); ?>" class="btn-ghost">
                <?php echo esc_html( $secondary_text ); ?>
            </a>
        </div>
    </div>

    <div class="cta-right">
        <?php if ( $pcta_stats ) : ?>
            <?php
            // First two stats go side-by-side; remaining stats are standalone.
            $first  = $pcta_stats[0] ?? null;
            $second = $pcta_stats[1] ?? null;
            $rest   = array_slice( $pcta_stats, 2 );
            ?>
            <?php if ( $first ) : ?>
            <div class="cta-stats-row">
                <div class="cta-stat">
                    <div class="cta-stat-val"><?php echo esc_html( $first['value'] ?? '' ); ?></div>
                    <div class="cta-stat-label"><?php echo esc_html( $first['label'] ?? '' ); ?></div>
                </div>
                <?php if ( $second ) : ?>
                <div class="cta-stat">
                    <div class="cta-stat-val"><?php echo esc_html( $second['value'] ?? '' ); ?></div>
                    <div class="cta-stat-label"><?php echo esc_html( $second['label'] ?? '' ); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php foreach ( $rest as $s ) : ?>
            <div class="cta-stat cta-stat--full">
                <div class="cta-stat-val"><?php echo esc_html( $s['value'] ?? '' ); ?></div>
                <div class="cta-stat-label"><?php echo esc_html( $s['label'] ?? '' ); ?></div>
            </div>
            <?php endforeach; ?>

        <?php else : ?>
        <!-- Fallback stats — edit via ACF "Provider CTA" tab -->
        <div class="cta-stats-row">
            <div class="cta-stat">
                <div class="cta-stat-val">Families pick</div>
                <div class="cta-stat-label">who hears from them first</div>
            </div>
            <div class="cta-stat">
                <div class="cta-stat-val">24h</div>
                <div class="cta-stat-label">first access for Featured providers</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
