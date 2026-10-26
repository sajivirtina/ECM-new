<?php
/**
 * Stats bar — all content from ACF "Stats Bar" tab.
 */

$stats = get_field( 'stats' );
?>

<!-- Stats Bar -->
<div class="stats-bar">
    <?php if ( $stats ) : ?>
        <?php foreach ( $stats as $stat ) : ?>
        <div class="stat-item">
            <div class="stat-number"><?php echo esc_html( $stat['number'] ?? '' ); ?></div>
            <div class="stat-label"><?php echo esc_html( $stat['label'] ?? '' ); ?></div>
        </div>
        <?php endforeach; ?>
    <?php else : ?>
    <!-- Fallback stats — edit via ACF "Stats Bar" tab -->
    <div class="stat-item"><div class="stat-number">12k+</div><div class="stat-label">Families matched</div></div>
    <div class="stat-item"><div class="stat-number">500+</div><div class="stat-label">Verified providers</div></div>
    <div class="stat-item"><div class="stat-number">4.9★</div><div class="stat-label">Average rating</div></div>
    <div class="stat-item"><div class="stat-number">&lt;2min</div><div class="stat-label">To get matched</div></div>
    <?php endif; ?>
</div>
