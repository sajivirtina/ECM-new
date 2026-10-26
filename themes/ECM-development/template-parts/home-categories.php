<?php
/**
 * Care categories section — all content from ACF "Care Categories" tab.
 */

$section_tag      = ecm_get_field( 'cats_section_tag',      'One need at a time' );
$section_title    = ecm_get_field( 'cats_section_title',    "What's going on with your loved one?" );
$section_subtitle = ecm_get_field( 'cats_section_subtitle', 'Select a category to see verified local providers. Free, no obligation.' );
$verified_strip   = ecm_get_field( 'cats_verified_strip',   'All providers ECM Verified — credential-reviewed & locally licensed since 2002' );
$categories       = get_field( 'categories' );
?>

<!-- Category cards -->
<section class="care-types" id="care-types">
    <div class="section-header reveal">
        <div class="care-types-header-main">
            <div class="section-tag"><?php echo esc_html( $section_tag ); ?></div>
            <h2 class="section-title">
                <?php echo esc_html( $section_title ); ?>
                <em class="js-location-city">your area</em><button type="button" class="location-edit-pin" data-open-location-modal>📍</button>?
            </h2>
            <p class="section-sub"><?php echo esc_html( $section_subtitle ); ?></p>
            <div class="section-divider"></div>
        </div>
    </div>

    <div class="care-grid">
        <?php if ( $categories ) : ?>
            <?php foreach ( $categories as $i => $cat ) :
                $name       = esc_html( $cat['name'] ?? '' );
                $slug       = sanitize_title( $cat['slug'] ?? '' );
                $icon       = $cat['icon'] ?? '';
                $img        = $cat['image'] ?? [];
                $img_url    = $img['url'] ?? '';
                $img_alt    = $img['alt'] ?? $name;
                $desc       = esc_html( $cat['description'] ?? '' );
                $cta_prefix = esc_html( $cat['cta_text'] ?? 'See providers in' );
                $link       = $cat['link_url'] ?: home_url( '/category/?type=' . $slug );
                $delay      = $i * 0.05;
            ?>
            <a class="care-card reveal js-category-card"
               data-category="<?php echo esc_attr( $slug ); ?>"
               href="<?php echo esc_url( $link ); ?>"
               <?php if ( $delay > 0 ) : ?>style="transition-delay:<?php echo esc_attr( $delay ); ?>s"<?php endif; ?>>
                <div class="care-card-img">
                    <?php if ( $icon ) : ?>
                    <span class="care-icon-chip"><?php echo esc_html( $icon ); ?></span>
                    <?php endif; ?>
                    <?php if ( $img_url ) : ?>
                    <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $img_alt ); ?>">
                    <?php endif; ?>
                </div>
                <div class="care-card-body">
                    <div class="care-name"><?php echo $name; ?></div>
                    <p class="care-desc"><?php echo $desc; ?></p>
                    <span class="care-cta">
                        <span class="care-cta-label"><?php echo $cta_prefix; ?> <span class="js-location-city">your area</span></span>
                        <span class="care-cta-arrow" aria-hidden="true">→</span>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>

        <?php else : ?>
        <!-- Fallback categories — add via ACF "Care Categories" tab -->
        <?php
        $fallback_cats = [
            [ 'home-care',        '🏠', 'Home Care',           'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=800&auto=format&fit=crop', 'Daily support — bathing, meals, medication reminders — from verified caregivers in', 'See providers in', 0 ],
            [ 'assisted-living',  '🏡', 'Assisted Living',     'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=800&auto=format&fit=crop', 'Community living with on-site staff for meals, activities, and medical support.', 'See providers in', 0.05 ],
            [ 'memory-care',      '🧠', 'Memory Care',         'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=800&auto=format&fit=crop', 'Specialized support for Alzheimer\'s, dementia, and other memory conditions.', 'See providers in', 0.1 ],
            [ 'elder-law',        '⚖️', 'Elder Law Attorney',  'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=800&auto=format&fit=crop', 'Guardianship, estate planning, Medicaid, and power of attorney — attorneys licensed locally.', 'See attorneys in', 0.15 ],
            [ 'care-management',  '📋', 'Care Management',     'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=800&auto=format&fit=crop', 'A care manager coordinates every service — one family point of contact.', 'See managers in', 0.2 ],
            [ 'hospice',          '🤝', 'Hospice',             'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?w=800&auto=format&fit=crop', 'Compassionate end-of-life care at home or in a dedicated facility, with family support.', 'See providers in', 0.25 ],
            [ 'grief-counselors', '💜', 'Grief Counselors',    'https://images.unsplash.com/photo-1573497491765-dccce02b29df?w=800&auto=format&fit=crop', 'Professional grief and bereavement support for families and seniors navigating loss.', 'See counselors in', 0.3 ],
        ];
        foreach ( $fallback_cats as [ $slug, $icon, $name, $img, $desc, $cta, $delay ] ) :
        ?>
        <a class="care-card reveal js-category-card"
           data-category="<?php echo esc_attr( $slug ); ?>"
           href="<?php echo esc_url( home_url( '/category/?type=' . $slug ) ); ?>"
           <?php if ( $delay > 0 ) : ?>style="transition-delay:<?php echo $delay; ?>s"<?php endif; ?>>
            <div class="care-card-img">
                <span class="care-icon-chip"><?php echo $icon; ?></span>
                <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $name ); ?>">
            </div>
            <div class="care-card-body">
                <div class="care-name"><?php echo esc_html( $name ); ?></div>
                <p class="care-desc"><?php echo esc_html( $desc ); ?></p>
                <span class="care-cta">
                    <span class="care-cta-label"><?php echo esc_html( $cta ); ?> <span class="js-location-city">your area</span></span>
                    <span class="care-cta-arrow" aria-hidden="true">→</span>
                </span>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /.care-grid -->

    <div class="verified-strip verified-strip--below">
        <span class="verified-strip-dot"></span>
        <?php echo esc_html( $verified_strip ); ?>
    </div>
</section>
