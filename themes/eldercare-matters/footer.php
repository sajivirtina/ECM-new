<?php
/**
 * Footer template — all content from ACF "Footer" tab.
 */

$homepage_id   = (int) get_option( 'page_on_front' );
$footer_logo   = get_field( 'footer_logo', $homepage_id ?: false );
$logo_url      = $footer_logo['url'] ?? get_template_directory_uri() . '/assets/images/logo.svg';
$logo_alt      = $footer_logo['alt'] ?? 'ElderCareMatters';
$tagline       = ecm_get_field( 'footer_tagline', 'Connecting families with elder care providers across the United States.', $homepage_id ?: false );
$trust_badges  = get_field( 'footer_trust_badges', $homepage_id ?: false );
$columns       = get_field( 'footer_columns', $homepage_id ?: false );
$copyright     = ecm_get_field( 'footer_copyright', '© ' . date( 'Y' ) . ' ElderCareMatters. All rights reserved.', $homepage_id ?: false );

// Dotiq widget
$dotiq_enabled = get_field( 'dotiq_enabled', $homepage_id ?: false );
$dotiq_app_id  = ecm_get_field( 'dotiq_app_id', 'ecm', $homepage_id ?: false );
$instagram_feed = shortcode_exists( 'inavii-feed' ) ? do_shortcode( '[inavii-feed id="283261"]' ) : '';
?>

<footer>
    <?php if ( $instagram_feed && false === strpos( $instagram_feed, '[inavii-feed' ) ) : ?>
    <div class="insta-wrapper">
        <?php echo $instagram_feed; ?>
    </div>
    <?php endif; ?>

    <div class="footer-top">
        <!-- Brand column -->
        <div class="footer-brand">
            <div class="footer-logo">
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" class="footer-logo-image">
            </div>
            <p class="footer-tagline"><?php echo esc_html( $tagline ); ?></p>

            <?php if ( $trust_badges ) : ?>
            <div class="footer-trust">
                <?php foreach ( $trust_badges as $badge ) : ?>
                <span class="trust-item">
                    <?php echo esc_html( $badge['icon'] ?? '' ); ?>
                    <?php echo esc_html( $badge['text'] ?? '' ); ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <!-- Fallback trust badges -->
            <div class="footer-trust">
                <span class="trust-item">⭐ Free for families</span>
                <span class="trust-item">🔒 Your details stay private until you choose</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Navigation columns -->
        <?php if ( $columns ) : ?>
        <?php foreach ( $columns as $col ) :
            $col_title = esc_html( $col['title'] ?? '' );
            $col_links = $col['links'] ?? [];
        ?>
        <div class="footer-col">
            <div class="footer-col-title"><?php echo $col_title; ?></div>
            <?php if ( $col_links ) : ?>
            <ul class="footer-links">
                <?php foreach ( $col_links as $lnk ) : ?>
                <li>
                    <a href="<?php echo esc_url( ecm_resolve_url( $lnk['url'] ?? '#' ) ); ?>">
                        <?php echo esc_html( $lnk['text'] ?? '' ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php else : ?>
        <!-- Fallback footer columns -->
        <div class="footer-col">
            <div class="footer-col-title">For Families</div>
            <ul class="footer-links">
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#care-types">Find Providers</a></li>
                <li><a href="<?php echo esc_url( home_url( '/resources/' ) ); ?>">Guides &amp; Resources</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <div class="footer-col-title">For Providers</div>
            <ul class="footer-links">
                <li><a href="<?php echo esc_url( home_url( '/for-providers/' ) ); ?>">Provider Overview</a></li>
                <li><a href="<?php echo esc_url( home_url( '/provider-plans/' ) ); ?>">Pricing &amp; Plans</a></li>
                <li><a href="<?php echo esc_url( home_url( '/provider-dashboard/' ) ); ?>"><?php echo is_user_logged_in() ? 'Dashboard' : 'Provider Login'; ?></a></li>
            </ul>
        </div>
        <div class="footer-col">
            <div class="footer-col-title">Browse Care</div>
            <ul class="footer-links">
                <li><a href="#care-types">All Categories</a></li>
                <li><a href="<?php echo esc_url( home_url( '/resources/' ) ); ?>">Elder Care Guides</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div><!-- /.footer-top -->

    <div class="footer-bottom">
        <span class="footer-copy"><?php echo esc_html( $copyright ); ?></span>
        <div class="footer-bottom-right">
            <?php
            // Checklist item 56 / Amendment 1 §2: exactly these six links, this
            // order, on every page — not ACF-editable. The previous version let
            // an ACF field override/append to this row, which is how a 7th link
            // ("Your Privacy Choices") that was never supposed to exist (see
            // Addendum 1 Part 3 #3 — it came from a different, superseded
            // document's "Fixes" table) ended up live, while "What ECM Verified
            // Means" and "Contact" were missing entirely. Remaining Pages
            // Changes doc, sitewide item 4: Contact was still missing from this
            // row (only in the Company column) — added here, Company column left
            // as-is.
            $ecm_footer_legal_links = [
                home_url( '/privacy-policy/' )               => 'Privacy Policy',
                home_url( '/terms/' )                         => 'Terms of Service',
                home_url( '/verified/' )                      => 'What ECM Verified Means',
                home_url( '/consumer-health-data-notice/' )   => 'Consumer Health Data Notice',
                home_url( '/do-not-sell/' )                   => 'Do Not Sell or Share My Personal Information',
                home_url( '/contact/' )                        => 'Contact',
            ];
            foreach ( $ecm_footer_legal_links as $url => $label ) : ?>
                <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</footer>

<script src="https://app.dotiq.io/widget/feedback-widget.js" data-app="ecm_staging"></script>

<?php wp_footer(); ?>
</body>
</html>
