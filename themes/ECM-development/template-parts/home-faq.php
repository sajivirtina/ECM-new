<?php
/**
 * FAQ section — all content from ACF "FAQ" tab.
 */

$tag       = ecm_get_field( 'faq_tag',       'Common Questions' );
$title     = ecm_get_field( 'faq_title',     'Frequently Asked Questions' );
$subtitle  = ecm_get_field( 'faq_subtitle',  'Everything you need to know before getting started — no pressure, no commitment.' );
$link_text = ecm_get_field( 'faq_link_text', 'Browse all FAQs →' );
$link_url  = ecm_resolve_url( ecm_get_field( 'faq_link_url',  home_url( '/resources/' ) ) );
$faq_items = get_field( 'faq_items' );
?>

<!-- FAQ -->
<section class="faq" id="faq">
    <div class="faq-grid">
        <!-- Left column: intro -->
        <div class="faq-intro reveal">
            <div class="section-tag"><?php echo esc_html( $tag ); ?></div>
            <h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
            <p class="section-sub"><?php echo esc_html( $subtitle ); ?></p>
            <?php if ( $link_text && $link_url ) : ?>
            <a href="<?php echo esc_url( $link_url ); ?>" class="faq-browse-link">
                <?php echo esc_html( $link_text ); ?>
            </a>
            <?php endif; ?>
        </div>

        <!-- Right column: accordion items -->
        <div class="faq-items">
            <?php if ( $faq_items ) : ?>
                <?php foreach ( $faq_items as $item ) : ?>
                <div class="faq-item">
                    <div class="faq-q">
                        <?php echo esc_html( $item['question'] ?? '' ); ?>
                        <span class="faq-icon">+</span>
                    </div>
                    <div class="faq-a"><?php echo esc_html( $item['answer'] ?? '' ); ?></div>
                </div>
                <?php endforeach; ?>

            <?php else : ?>
            <!-- Fallback FAQ items — add via ACF "FAQ" tab -->
            <div class="faq-item">
                <div class="faq-q">Is this service free for families? <span class="faq-icon">+</span></div>
                <div class="faq-a">Yes, completely free. ElderCareMatters is funded by the providers who subscribe to our platform. You will never be charged or asked for payment.</div>
            </div>
            <div class="faq-item">
                <div class="faq-q">How are providers verified? <span class="faq-icon">+</span></div>
                <div class="faq-a">Every provider undergoes a review of business credentials, licensing, and references before receiving the ECM Verified badge. We also monitor reviews to maintain quality standards.</div>
            </div>
            <div class="faq-item">
                <div class="faq-q">How does the match work? <span class="faq-icon">+</span></div>
                <div class="faq-a">Tell us what you need and where your loved one lives. Choose up to 10 verified local providers to hear from first — your contact details stay hidden until a provider asks to help.</div>
            </div>
            <div class="faq-item">
                <div class="faq-q">How quickly will providers respond? <span class="faq-icon">+</span></div>
                <div class="faq-a">Most families hear back within 1 to 3 days.</div>
            </div>
            <div class="faq-item">
                <div class="faq-q">Do elder care laws vary by state? <span class="faq-icon">+</span></div>
                <div class="faq-a">Yes — licensing requirements, guardianship rules, and Medicaid regulations differ significantly by state. Our matching system accounts for provider location-based licensing.</div>
            </div>
            <div class="faq-item">
                <div class="faq-q">Can I update or close my care request? <span class="faq-icon">+</span></div>
                <div class="faq-a">Yes. Log in to your consumer dashboard to edit your request details, track provider responses, message providers directly, or close the request when you've found the right fit.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
