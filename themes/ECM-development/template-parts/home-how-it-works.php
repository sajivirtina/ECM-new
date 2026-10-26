<?php
/**
 * How It Works section — all content from ACF "How It Works" tab.
 */

$tag      = ecm_get_field( 'hiw_tag',      'Simple Process' );
$title    = ecm_get_field( 'hiw_title',    'How It Works' );
$subtitle = ecm_get_field( 'hiw_subtitle', 'From your first question to a confirmed match — in under 2 minutes.' );
$steps    = get_field( 'hiw_steps' );
?>

<!-- How It Works -->
<section class="how-it-works" id="how-it-works">
    <div class="section-header reveal section-header--centered section-header--simple-process">
        <div class="section-tag section-tag--centered"><?php echo esc_html( $tag ); ?></div>
        <h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
        <p class="section-sub section-sub--full"><?php echo esc_html( $subtitle ); ?></p>
        <div class="section-divider section-divider--centered"></div>
    </div>

    <div class="steps-grid">
        <?php if ( $steps ) : ?>
            <?php foreach ( $steps as $step ) : ?>
            <div class="step-card reveal">
                <div class="step-number-wrap">
                    <div class="step-number"><?php echo esc_html( $step['number'] ?? '' ); ?></div>
                </div>
                <h3 class="step-title"><?php echo esc_html( $step['title'] ?? '' ); ?></h3>
                <p class="step-desc"><?php echo esc_html( $step['description'] ?? '' ); ?></p>
            </div>
            <?php endforeach; ?>

        <?php else : ?>
        <!-- Fallback steps — edit via ACF "How It Works" tab -->
        <div class="step-card reveal">
            <div class="step-number-wrap"><div class="step-number">1</div></div>
            <h3 class="step-title">Tell us what you need</h3>
            <p class="step-desc">One category. One location. Takes about a minute — chat or form, your call.</p>
        </div>
        <div class="step-card reveal">
            <div class="step-number-wrap"><div class="step-number">2</div></div>
            <h3 class="step-title">Get matched instantly</h3>
            <p class="step-desc">Our geo-aware system finds verified providers in your area that fit your exact need.</p>
        </div>
        <div class="step-card reveal">
            <div class="step-number-wrap"><div class="step-number">3</div></div>
            <h3 class="step-title">Connect &amp; choose</h3>
            <p class="step-desc">Providers reach out directly. Compare, message, and choose — always free for families.</p>
        </div>
        <?php endif; ?>
    </div>
</section>
