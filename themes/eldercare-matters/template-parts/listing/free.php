<?php
/**
 * Free tier listing — template-part.
 *
 * Rebuilt against the confirmed reference design: dashed "FREE LISTING"
 * badge, no sidebar (full-width single column), a "Request Help"
 * button linked to the contact page, and a
 * separate "About [Name]" section below the compact hero — NOT bio/mission
 * crammed inside the hero card the way Standard/Premium still are.
 *
 * All variables ($title, $tier, $location, $cats, $content, etc.) come
 * from the router (single-job_listing.php) via `require` — this file adds
 * no data-fetching of its own.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Provider Hero — Free (no sidebar) -->
<section class="provider-hero provider-hero--no-sidebar">
    <div class="provider-hero-main">

        <div class="provider-hero-top">
            <?php if ( $logo_url ) : ?>
            <div class="provider-hero-logo" style="background:#fff;overflow:hidden;padding:6px">
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $title ); ?>"
                     style="width:100%;height:100%;object-fit:contain;border-radius:14px">
            </div>
            <?php else : ?>
            <div class="provider-hero-logo" style="background:<?php echo esc_attr( $logo_bg ); ?>;color:#fff;font-weight:700">
                <?php echo esc_html( $initials ); ?>
            </div>
            <?php endif; ?>

            <div>
                <h1 class="provider-hero-title"><?php echo esc_html( $title ); ?></h1>
                <div class="provider-hero-meta">
                    <?php // No rating badge on Free — star ratings/reviews start at Basic. ?>
                    <?php if ( $location ) : ?>
                    <span class="provider-hero-meta-item">📍 <?php echo esc_html( $location ); ?></span>
                    <?php endif; ?>
                    <?php if ( $plan_name ) : ?>
                    <span class="provider-tier-badge provider-plan-badge provider-detail-plan-badge tier-<?php echo esc_attr( $plan_badge_tier ); ?>"><?php echo esc_html( $plan_name ); ?></span>
                    <?php endif; ?>
                </div>
                <?php require get_template_directory() . '/template-parts/listing/social-links.php'; ?>
            </div>
        </div><!-- /.provider-hero-top -->

        <?php if ( $cats ) : ?>
        <div class="provider-specialties" style="margin-bottom:16px">
            <?php foreach ( $cats as $cat ) :
                $cat_display = $state !== 'CA' ? preg_replace( '/\s*\/\s*Medi-Cal\b/i', '', $cat->name ) : $cat->name;
            ?>
            <span class="specialty-chip"><?php echo esc_html( $cat_display ); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( $tagline ) : ?>
        <p class="provider-hero-tagline"><?php echo esc_html( $tagline ); ?></p>
        <?php endif; ?>

        <button type="button" class="btn-primary-lg ecm-detail-select-btn" data-open-form-modal
                data-provider-id="<?php echo esc_attr( $post->ID ); ?>"
                data-provider-name="<?php echo esc_attr( $title ); ?>"
                style="margin-top:6px;">
            Select This Provider
        </button>

    </div><!-- /.provider-hero-main -->
</section><!-- /.provider-hero -->

<?php
$about_content = preg_replace( '/[^\n]*contact the provider directly[^\n]*/i', '', $content );
$about_content = trim( $about_content );
?>
<?php if ( $about_content ) : ?>
<section class="inner-section">
    <div class="inner-section-header">
        <div><h2>About</h2></div>
    </div>
    <div class="provider-hero-about" style="max-width:900px;">
        <?php echo wp_kses_post( wpautop( $about_content ) ); ?>
    </div>
    <p class="provider-contact-note" style="font-size:0.9rem;color:var(--muted,#6b7280);margin-top:12px;">
        Select this provider to request information. Your contact details stay hidden until they ask to help.
    </p>
</section>
<?php endif; // $about_content ?>
