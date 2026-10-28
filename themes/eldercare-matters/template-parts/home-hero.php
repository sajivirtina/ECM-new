<?php
/**
 * Hero section — all content from ACF "Hero Section" tab.
 */

$stamp_top    = ecm_get_field( 'hero_stamp_arc_top',    '· ALL 50 STATES ·' );
$stamp_bottom = ecm_get_field( 'hero_stamp_arc_bottom', '· VERIFIED CARE MATCHING ·' );
$stamp_year   = ecm_get_field( 'hero_stamp_year',       '2002' );

$title_prefix     = ecm_get_field( 'hero_title_prefix',    'Find the right care for someone you love, even from miles away.' );
$subtitle         = ecm_get_field( 'hero_subtitle',        'Tell us what they need and where they live. Choose up to 10 verified local providers to hear from first. Free, with no obligation.' );

$cta1_icon  = ecm_get_field( 'hero_cta_primary_icon',    '📋' );
$cta1_text  = ecm_get_field( 'hero_cta_primary_text',    'Find Care Now' );
$cta2_icon  = ecm_get_field( 'hero_cta_secondary_icon',  '💬' );
$cta2_text  = ecm_get_field( 'hero_cta_secondary_text',  'Prefer to chat? Talk to Carrie' );

$badges           = get_field( 'hero_badges' );
$quick_cats_label = ecm_get_field( 'hero_quick_cats_label', 'Quick start:' );
$quick_cats       = get_field( 'hero_quick_cats' );

$trust_year = ecm_get_field( 'hero_trust_year', '2002' );
$trust_text = ecm_get_field( 'hero_trust_text', "Serving families since 2002 · Available in all 50 states" );

$hero_img    = get_field( 'hero_image' );
$hero_img_url = $hero_img['url'] ?? 'https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?w=1000&auto=format&fit=crop&q=80';
$hero_img_alt = $hero_img['alt'] ?? 'Compassionate caregiver with elderly patient';

$fc_score     = ecm_get_field( 'hero_fc_rating_score', 'Since 2002' );
$fc_label     = ecm_get_field( 'hero_fc_rating_label', 'Helping families find care' );
$fc_avatars   = get_field( 'hero_fc_avatars' );
$fc_count     = ecm_get_field( 'hero_fc_avatar_count', '' );

$fc_ver_title = ecm_get_field( 'hero_fc_verified_title', 'Providers across all 50 states' );
$fc_ver_sub   = ecm_get_field( 'hero_fc_verified_sub',   'Listed by location and specialty' );

$fc_time_val  = ecm_get_field( 'hero_fc_time_value', '~2 min' );
$fc_time_lbl  = ecm_get_field( 'hero_fc_time_label', 'to select providers · always free' );

$logo_icon_url = get_template_directory_uri() . '/assets/images/logo.png';
$footer_logo = get_field( 'footer_logo' );
if ( $footer_logo ) { $logo_icon_url = $footer_logo['url']; }
?>

<!-- Hero -->
<section class="hero">
    <div class="hero-bg-shape"></div>

    <div class="hero-left">
        <!-- Stamp Badge -->
        <div class="badge-wrap">
            <svg class="stamp-svg" viewBox="0 0 220 220" width="120" height="120" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <path id="topArc2" d="M 17,110 A 93,93 0 0,0 203,110"/>
                    <path id="botArc2" d="M 27,110 A 83,83 0 0,1 193,110"/>
                </defs>
                <circle cx="110" cy="110" r="105" fill="none" stroke="#b8860b" stroke-width="3"/>
                <circle cx="110" cy="110" r="98"  fill="none" stroke="#b8860b" stroke-width="1.2"/>
                <circle cx="110" cy="110" r="65"  fill="none" stroke="#b8860b" stroke-width="2"/>
                <circle cx="110" cy="110" r="59"  fill="none" stroke="#b8860b" stroke-width="1"/>
                <text font-family="'DM Sans', Arial, sans-serif" font-size="10.5" font-weight="700"
                      letter-spacing="2.8" fill="#b8860b" text-anchor="middle">
                    <textPath href="#topArc2" startOffset="50%"><?php echo esc_html( $stamp_top ); ?></textPath>
                </text>
                <text font-family="'DM Sans', Arial, sans-serif" font-size="10.5" font-weight="700"
                      letter-spacing="3.5" fill="#b8860b" text-anchor="middle">
                    <textPath href="#botArc2" startOffset="50%"><?php echo esc_html( $stamp_bottom ); ?></textPath>
                </text>
                <text x="110" y="50" text-anchor="middle"
                      font-family="Arial" font-size="9" fill="#b8860b" letter-spacing="7">★  ★  ★</text>
                <text x="110" y="172" text-anchor="middle"
                      font-family="Arial" font-size="9" fill="#b8860b" letter-spacing="7">★  ★  ★</text>
                <line x1="73" y1="98" x2="147" y2="98" stroke="#b8860b" stroke-width="0.8" opacity="0.45"/>
                <line x1="73" y1="140" x2="147" y2="140" stroke="#b8860b" stroke-width="0.8" opacity="0.45"/>
                <text x="110" y="114" text-anchor="middle"
                      font-family="'DM Sans', Arial, sans-serif" font-size="9.5" font-weight="700"
                      letter-spacing="5" fill="#b8860b">SINCE</text>
                <text x="110" y="137" text-anchor="middle"
                      font-family="'Playfair Display', Georgia, serif" font-size="34" font-weight="700"
                      fill="#b8860b"><?php echo esc_html( $stamp_year ); ?></text>
            </svg>
        </div>

        <!-- Headline -->
        <h1 class="hero-title">
            <?php echo esc_html( $title_prefix ); ?><svg class="hero-heart" viewBox="0 0 40 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M1 30 C3 23 8 21 13 21 L18 21" fill="none" stroke="var(--charcoal,#1b2d45)" stroke-width="2.2" stroke-linecap="round"/><line x1="13" y1="21" x2="13" y2="16" stroke="var(--charcoal,#1b2d45)" stroke-width="2" stroke-linecap="round"/><line x1="16" y1="21" x2="16" y2="14" stroke="var(--charcoal,#1b2d45)" stroke-width="2" stroke-linecap="round"/><line x1="19" y1="21" x2="19" y2="15" stroke="var(--charcoal,#1b2d45)" stroke-width="2" stroke-linecap="round"/><path d="M39 30 C37 23 32 21 27 21 L22 21" fill="none" stroke="var(--charcoal,#1b2d45)" stroke-width="2.2" stroke-linecap="round"/><line x1="27" y1="21" x2="27" y2="16" stroke="var(--charcoal,#1b2d45)" stroke-width="2" stroke-linecap="round"/><line x1="24" y1="21" x2="24" y2="14" stroke="var(--charcoal,#1b2d45)" stroke-width="2" stroke-linecap="round"/><line x1="21" y1="21" x2="21" y2="15" stroke="var(--charcoal,#1b2d45)" stroke-width="2" stroke-linecap="round"/><path class="heart" d="M20 20 C20 20 11 14 11 8.5 C11 5.5 13.5 3.5 16.5 4.5 C18 5 19.2 6.2 20 7.5 C20.8 6.2 22 5 23.5 4.5 C26.5 3.5 29 5.5 29 8.5 C29 14 20 20 20 20 Z" fill="#d94f3d"/></svg>
        </h1>

        <!-- Location bar -->
        <div class="hero-location-bar">
            <span class="hero-location-label">Showing care near:</span>
            <strong class="js-location-full">Dallas, TX</strong>
            <button type="button" class="location-edit-pin" data-open-location-modal aria-label="Change location">
                📍 Change location
            </button>
        </div>

        <!-- Subtitle -->
        <p class="hero-sub"><?php echo esc_html( $subtitle ); ?></p>

        <!-- CTA Buttons -->
        <div class="hero-cta-row">
            <button type="button" class="btn-primary-lg" data-open-form-modal>
                <?php if ( $cta1_icon ) : ?><?php echo esc_html( $cta1_icon ); ?> <?php endif; ?>
                <?php echo esc_html( $cta1_text ); ?>
                <span class="btn-arrow">→</span>
            </button>
            <a class="btn-text" href="<?php echo esc_url( home_url( '/find-care/' ) ); ?>">
                🔎
                Search for a Provider
            </a>
        </div>

        <!-- Trust Badges -->
        <?php if ( $badges ) : ?>
        <div class="hero-badges">
            <?php foreach ( $badges as $badge ) : ?>
            <div class="hero-badge">
                <div class="hero-badge-dot"></div>
                <?php echo esc_html( $badge['text'] ?? '' ); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="hero-badges">
            <div class="hero-badge"><div class="hero-badge-dot"></div> Free for families</div>
            <div class="hero-badge"><div class="hero-badge-dot"></div> Providers pre-screened</div>
            <div class="hero-badge"><div class="hero-badge-dot"></div> No spam, ever</div>
        </div>
        <?php endif; ?>

    </div><!-- /.hero-left -->

    <!-- Hero right — photo + floating cards -->
    <div class="hero-right">
        <div class="hero-visual" aria-hidden="true">
            <div class="hero-photo">
                <img src="<?php echo esc_url( $hero_img_url ); ?>" alt="<?php echo esc_attr( $hero_img_alt ); ?>">
            </div>

            <!-- Floating card 1: Rating -->
            <div class="hero-float-card hero-float-card--rating">
                <div class="hero-float-stars">★★★★★</div>
                <div class="hero-float-title">
                    <strong><?php echo esc_html( $fc_score ); ?></strong>
                    · <?php echo esc_html( $fc_label ); ?>
                </div>
                <div class="hero-float-avatars">
                    <?php if ( $fc_avatars ) : ?>
                        <?php foreach ( $fc_avatars as $av ) :
                            $av_url = $av['image']['url'] ?? '';
                        ?>
                        <?php if ( $av_url ) : ?>
                        <span class="hero-float-avatar"
                              style="background-image:url('<?php echo esc_url( $av_url ); ?>')"></span>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <span class="hero-float-avatar" style="background-image:url('https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?w=80&auto=format&fit=crop')"></span>
                        <span class="hero-float-avatar" style="background-image:url('https://images.unsplash.com/photo-1552058544-f2b08422138a?w=80&auto=format&fit=crop')"></span>
                        <span class="hero-float-avatar" style="background-image:url('https://images.unsplash.com/photo-1607746882042-944635dfe10e?w=80&auto=format&fit=crop')"></span>
                    <?php endif; ?>
                    <span class="hero-float-avatar hero-float-avatar--more">
                        <?php echo esc_html( $fc_count ); ?>
                    </span>
                </div>
            </div>

            <!-- Floating card 2: Verified -->
            <div class="hero-float-card hero-float-card--verified">
                <div class="hero-float-badge-icon">✓</div>
                <div>
                    <div class="hero-float-badge-title"><?php echo esc_html( $fc_ver_title ); ?></div>
                    <div class="hero-float-badge-sub"><?php echo esc_html( $fc_ver_sub ); ?></div>
                </div>
            </div>

            <!-- Floating card 3: Time to match -->
            <div class="hero-float-card hero-float-card--matched">
                <div class="hero-float-stat">
                    <span class="hero-float-emoji">⏱</span>
                    <strong><?php echo esc_html( $fc_time_val ); ?></strong>
                </div>
                <div class="hero-float-stat-label"><?php echo esc_html( $fc_time_lbl ); ?></div>
            </div>
        </div>
    </div><!-- /.hero-right -->
</section>
