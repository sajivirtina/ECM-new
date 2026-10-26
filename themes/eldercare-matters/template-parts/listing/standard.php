<?php
/**
 * Standard tier listing — template-part.
 *
 * ⚠ UNVERIFIED — this is a faithful port of the content/structure from the
 * old single-file version, NOT yet checked against a confirmed reference
 * design the way Free/Basic were. Bio/mission/highlights are still packed
 * inside the hero card here (unlike Free/Basic's separate "About" section)
 * — revisit this once a Standard reference design is checked, likely
 * moving to the same About-as-its-own-section pattern.
 *
 * All variables come from the router (single-job_listing.php) via
 * `require` — this file adds no data-fetching of its own.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Provider Hero — Standard -->
<section class="provider-hero">
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
                    <?php if ( $avg_rating > 0 ) : ?>
                    <span class="provider-hero-meta-item">
                        <span style="color:var(--gold)">★</span>
                        <strong><?php echo number_format( $avg_rating, 1 ); ?></strong>
                        <?php if ( $review_count ) : ?>
                        <span style="color:var(--muted)">(<?php echo esc_html( $review_count ); ?> reviews)</span>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <?php if ( $location ) : ?>
                    <span class="provider-hero-meta-item">📍 <?php echo esc_html( $location ); ?></span>
                    <?php endif; ?>
                    <?php if ( $primary_cat ) : ?>
                    <span class="provider-hero-meta-item"><?php echo esc_html( $primary_cat->name ); ?></span>
                    <?php endif; ?>
                    <?php if ( $plan_name ) : ?>
                    <span class="provider-tier-badge provider-plan-badge provider-detail-plan-badge tier-<?php echo esc_attr( $plan_badge_tier ); ?>"><?php echo esc_html( $plan_name ); ?></span>
                    <?php endif; ?>
                </div>
                <?php require get_template_directory() . '/template-parts/listing/social-links.php'; ?>
            </div>
        </div><!-- /.provider-hero-top -->

        <?php if ( $tagline ) : ?>
        <p class="provider-hero-tagline"><?php echo esc_html( $tagline ); ?></p>
        <?php endif; ?>

        <?php
        $about_content = preg_replace( '/[^\n]*contact the provider directly[^\n]*/i', '', $content );
        $about_content = trim( $about_content );
        ?>
        <?php if ( $about_content ) : ?>
        <div class="provider-hero-about"><?php echo wp_kses_post( $about_content ); ?></div>
        <?php endif; ?>

        <?php if ( $lp_highlights ) : ?>
        <ul style="margin-top:14px;padding-left:20px;color:var(--mid);font-size:0.92rem;line-height:1.8;">
            <?php foreach ( $lp_highlights as $h ) : ?>
                <li><?php echo esc_html( $h['text'] ?? '' ); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if ( $website ) : ?>
        <div style="display:flex;gap:16px;margin-top:18px;flex-wrap:wrap">
            <button type="button" class="hero-text-link"
                data-open-form-modal
                data-redirect-url="<?php echo esc_url( $website ); ?>"
                title="Visit website"
                style="font-size:1.3rem;padding:6px 14px;background:none;border:none;cursor:pointer;line-height:1">
                🌐
            </button>
        </div>
        <?php endif; ?>

        <?php if ( $cats ) : ?>
        <div class="provider-specialties" style="margin-top:36px">
            <?php foreach ( $cats as $cat ) :
                $cat_display = $state !== 'CA' ? preg_replace( '/\s*\/\s*Medi-Cal\b/i', '', $cat->name ) : $cat->name;
            ?>
            <span class="specialty-chip"><?php echo esc_html( $cat_display ); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div><!-- /.provider-hero-main -->

    <div class="provider-side-card">
        <h4>Add <?php echo esc_html( $title ); ?> to your list.</h4>
        <p>Free, with no obligation. Your contact details stay hidden until they ask to help.</p>
        <button type="button" class="btn-primary-lg ecm-detail-select-btn" data-open-form-modal
                data-provider-id="<?php echo esc_attr( $post->ID ); ?>"
                data-provider-name="<?php echo esc_attr( $title ); ?>">
            Select This Provider
        </button>
        <div class="provider-side-stats">
            <div>
                <div class="provider-side-stat-val"><?php echo esc_html( $response ); ?></div>
                <div class="provider-side-stat-label">Response time</div>
            </div>
            <div>
                <div class="provider-side-stat-val"><?php echo esc_html( $years ); ?></div>
                <div class="provider-side-stat-label">Years serving families</div>
            </div>
        </div>
    </div><!-- /.provider-side-card -->
</section><!-- /.provider-hero -->

<?php if ( $lp_testimonials ) : ?>
<section class="inner-section inner-section--warm">
    <div class="inner-section-header">
        <div><h2>What Families Are Saying</h2></div>
    </div>
    <div class="review-grid">
        <?php foreach ( $lp_testimonials as $t ) : ?>
        <div class="review-card">
            <div class="review-header">
                <span class="review-author"><?php echo esc_html( $t['name'] ?? '' ); ?></span>
                <span class="review-date"><?php echo esc_html( $t['relation'] ?? '' ); ?></span>
            </div>
            <?php if ( ! empty( $t['rating'] ) ) : ?>
                <div class="review-stars"><?php echo ecm_review_stars( (int) $t['rating'] ); ?></div>
            <?php endif; ?>
            <p class="review-text"><?php echo esc_html( $t['quote'] ?? '' ); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ( $lp_team ) : ?>
<section class="inner-section">
    <div class="inner-section-header">
        <div><h2>Our Team</h2></div>
    </div>
    <div class="review-grid">
        <?php foreach ( $lp_team as $member ) : ?>
        <div class="review-card">
            <div class="review-header">
                <span class="review-author"><?php echo esc_html( $member['name'] ?? '' ); ?></span>
            </div>
            <p class="review-text" style="color:var(--sage);font-weight:600;margin-bottom:4px;">
                <?php echo esc_html( $member['role'] ?? '' ); ?>
            </p>
            <p class="review-text"><?php echo esc_html( $member['bio'] ?? '' ); ?></p>
            <?php if ( ! empty( $member['credentials_line'] ) ) : ?>
                <p class="review-text" style="color:var(--muted);font-size:0.85rem;">
                    <?php echo esc_html( $member['credentials_line'] ); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ( $lp_credentials || $lp_cities ) : ?>
<section class="inner-section inner-section--warm">
    <div class="inner-section-header">
        <div><h2>Credentials &amp; Coverage</h2></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;max-width:900px;margin:0 auto;padding:0 5%;">
        <?php if ( $lp_credentials ) : ?>
        <div>
            <h3 style="font-size:1rem;margin-bottom:12px;">Credentials &amp; Certifications</h3>
            <ul style="padding-left:20px;color:var(--mid);font-size:0.92rem;line-height:1.9;">
                <?php foreach ( $lp_credentials as $c ) : ?>
                    <li><?php echo esc_html( $c['text'] ?? '' ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if ( $lp_cities ) : ?>
        <div>
            <h3 style="font-size:1rem;margin-bottom:12px;">Coverage Area</h3>
            <div style="display:flex;flex-wrap:nowrap;gap:8px;overflow-x:auto;">
                <?php foreach ( $lp_cities as $c ) : ?>
                    <span class="specialty-chip" style="white-space:nowrap;">📍 <?php echo esc_html( $c['city'] ?? '' ); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ( $lp_gallery ) :
    $gallery_count = count( $lp_gallery );
    $grid_class    = $gallery_count >= 5 ? 'gallery-grid--5' : ( $gallery_count >= 2 ? 'gallery-grid--2' : 'gallery-grid--1' );
?>
<section class="provider-gallery">
    <div class="inner-section-header">
        <div><h2>Photo Gallery</h2></div>
    </div>
    <div class="gallery-grid <?php echo esc_attr( $grid_class ); ?>">
        <?php foreach ( $lp_gallery as $img ) : ?>
        <div class="gallery-grid-item">
            <img src="<?php echo esc_url( $img['sizes']['medium_large'] ?? $img['url'] ); ?>" alt="<?php echo esc_attr( $img['alt'] ?? $title ); ?>">
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ( $my_articles || $fill_articles ) : ?>
<section class="inner-section">
    <div class="inner-section-header">
        <div><h2>Blog &amp; Resources</h2></div>
    </div>
    <div class="blog-grid">
        <?php foreach ( $my_articles as $article ) : ?>
            <?php ecm_render_blog_post_card( $article ); ?>
        <?php endforeach; ?>

        <?php foreach ( $fill_articles as $fp ) : ?>
            <?php ecm_render_blog_post_card( $fp ); ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
