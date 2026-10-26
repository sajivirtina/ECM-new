<?php
/**
 * Template Name: ECM — Blog / Resources
 *
 * Resources & Guides listing. All content ACF-editable; falls back to the
 * prototype content when fields are empty.
 */

get_header();

$eyebrow   = ecm_get_field( 'blog_eyebrow', '📚 Elder Care Resources' );
$title     = ecm_get_field( 'blog_title', 'Resources &amp; <em>Guides</em>' );
$sub       = ecm_get_field( 'blog_sub', 'Expert-written articles to help families navigate every stage of elder care, from the first conversation to finding the right provider.' );
$stats     = get_field( 'blog_stats' );
$filters   = get_field( 'blog_filters' );
$cards     = get_field( 'blog_cards' );
$grid_sub  = ecm_get_field( 'blog_grid_sub', 'Practical articles written by elder care advisors and verified providers.' );
$cities    = get_field( 'blog_cities' );
$cities_sub = ecm_get_field( 'blog_cities_sub', 'Find elder care guides, provider directories, and local resources near you.' );

$detail_url = home_url( '/resources-guide/' );

// Featured
$f_icon    = ecm_get_field( 'blog_feat_icon', '🏠' );
$f_badge   = ecm_get_field( 'blog_feat_badge', "⭐ Editor's Pick" );
$f_title   = ecm_get_field( 'blog_feat_title', 'The Complete Guide to Home Care in 2026' );
$f_excerpt = ecm_get_field( 'blog_feat_excerpt', 'Everything a family needs to know before hiring a home care agency, from vetting credentials to understanding Medicare coverage and what to expect on day one.' );
$f_meta    = ecm_get_field( 'blog_feat_meta', 'March 15, 2026 · 8 min read · Home Care' );
$f_cta     = ecm_get_field( 'blog_feat_cta', 'Read the guide →' );
$f_link    = ecm_get_field( 'blog_feat_link', $detail_url );

// Newsletter
$n_title = ecm_get_field( 'blog_news_title', 'Get guides delivered to your <em>inbox</em>' );
$n_sub   = ecm_get_field( 'blog_news_sub', 'Join 12,000+ families receiving our free weekly elder care digest. No spam, unsubscribe any time.' );
$n_btn   = ecm_get_field( 'blog_news_btn', 'Subscribe Free' );
$n_fine  = ecm_get_field( 'blog_news_fine', 'We respect your privacy. Unsubscribe anytime.' );

// CTA band
$cb_title     = ecm_get_field( 'blog_cb_title', "Ready to find care? <em>We'll match you.</em>" );
$cb_sub       = ecm_get_field( 'blog_cb_sub', "Tell us what you need, we'll hand-pick up to 3 verified providers near you." );
$cb_primary   = ecm_get_field( 'blog_cb_primary', '📋 Start Your Free Match →' );
$cb_secondary = ecm_get_field( 'blog_cb_secondary', '💬 Chat with advisor' );

$em_kses = [ 'em' => [] ];

// Fallback data
if ( ! $stats ) {
    $stats = [
        [ 'value' => '50+', 'label' => 'Expert Guides' ],
        [ 'value' => 'Free', 'label' => 'Always Free' ],
        [ 'value' => '6', 'label' => 'Care Categories' ],
        [ 'value' => '50+', 'label' => 'Cities Covered' ],
    ];
}
if ( ! $filters ) {
    $filters = [
        [ 'label' => 'All Guides', 'slug' => 'all' ],
        [ 'label' => '🏠 Home Care', 'slug' => 'home-care' ],
        [ 'label' => '🏢 Assisted Living', 'slug' => 'assisted-living' ],
        [ 'label' => '🧠 Memory Care', 'slug' => 'memory-care' ],
        [ 'label' => '⚖️ Legal &amp; Financial', 'slug' => 'legal' ],
        [ 'label' => '🕊️ Hospice', 'slug' => 'hospice' ],
        [ 'label' => '✅ Checklists', 'slug' => 'checklist' ],
    ];
}
if ( ! $cards ) {
    $cards = [
        [ 'icon' => '🏠', 'icon_bg' => '#e6f4ea', 'category' => 'Home Care', 'cat_class' => 'blog-cat--home-care', 'title' => 'How to Choose a Home Care Agency', 'excerpt' => 'A step-by-step checklist for vetting agencies, asking the right questions, and avoiding common pitfalls families face.', 'meta' => 'Mar 10, 2026 · 6 min read', 'link' => $detail_url ],
        [ 'icon' => '🏢', 'icon_bg' => '#e8f0fe', 'category' => 'Assisted Living', 'cat_class' => 'blog-cat--assisted-living', 'title' => "Signs It's Time for Assisted Living", 'excerpt' => 'Recognizing the signs that a parent needs more support than home care can provide, and how to start that conversation.', 'meta' => 'Mar 8, 2026 · 5 min read', 'link' => $detail_url ],
        [ 'icon' => '🧠', 'icon_bg' => '#fce8d5', 'category' => 'Memory Care', 'cat_class' => 'blog-cat--memory-care', 'title' => 'Understanding Dementia Stages', 'excerpt' => 'A plain-language overview of the seven stages of dementia, what each looks like, and how care needs change over time.', 'meta' => 'Mar 5, 2026 · 7 min read', 'link' => $detail_url ],
        [ 'icon' => '⚖️', 'icon_bg' => '#f3e8fd', 'category' => 'Legal &amp; Financial', 'cat_class' => 'blog-cat--legal', 'title' => 'Medicare vs Medicaid Explained', 'excerpt' => 'The key differences between Medicare and Medicaid, what each covers for elder care, and how to apply for benefits.', 'meta' => 'Mar 3, 2026 · 8 min read', 'link' => $detail_url ],
        [ 'icon' => '🕊️', 'icon_bg' => '#fde8ee', 'category' => 'Hospice', 'cat_class' => 'blog-cat--hospice', 'title' => 'What Hospice Care Really Means', 'excerpt' => 'Dispelling common myths about hospice, when to consider it, and how it can improve quality of life for your loved one.', 'meta' => 'Feb 28, 2026 · 5 min read', 'link' => $detail_url ],
        [ 'icon' => '✅', 'icon_bg' => '#e8fdf5', 'category' => 'Checklist', 'cat_class' => 'blog-cat--checklist', 'title' => 'Moving Your Parent: The Ultimate Checklist', 'excerpt' => 'A 40-point checklist covering everything from sorting belongings to notifying Medicare when transitioning to a care facility.', 'meta' => 'Feb 24, 2026 · 4 min read', 'link' => $detail_url ],
        [ 'icon' => '🏠', 'icon_bg' => '#e6f4ea', 'category' => 'Home Care', 'cat_class' => 'blog-cat--home-care', 'title' => 'In-Home vs. Facility Care: Pros &amp; Cons', 'excerpt' => 'A balanced comparison to help families decide whether aging in place or transitioning to a facility is the right choice.', 'meta' => 'Feb 20, 2026 · 6 min read', 'link' => $detail_url ],
        [ 'icon' => '🧠', 'icon_bg' => '#fce8d5', 'category' => 'Memory Care', 'cat_class' => 'blog-cat--memory-care', 'title' => 'Talking to a Parent About Memory Loss', 'excerpt' => 'Scripts, timing, and framing strategies from care managers who have this difficult conversation every week.', 'meta' => 'Feb 17, 2026 · 5 min read', 'link' => $detail_url ],
        [ 'icon' => '⚖️', 'icon_bg' => '#f3e8fd', 'category' => 'Legal &amp; Financial', 'cat_class' => 'blog-cat--legal', 'title' => 'Power of Attorney: A Step-by-Step Guide', 'excerpt' => 'How to establish durable power of attorney for an aging parent, what documents you need, and common mistakes to avoid.', 'meta' => 'Feb 12, 2026 · 7 min read', 'link' => $detail_url ],
    ];
}
if ( ! $cities ) {
    $search_url = home_url( '/find-care/' );
    $cat_url = function( $city ) { return home_url( '/category/?type=home-care&city=' . $city ); };
    $cities = [
        [ 'label' => '📍 Dallas, TX', 'url' => $cat_url( 'dallas' ) ],
        [ 'label' => '📍 Fort Worth, TX', 'url' => $cat_url( 'fort-worth' ) ],
        [ 'label' => '📍 Plano, TX', 'url' => $cat_url( 'plano' ) ],
        [ 'label' => '📍 Arlington, TX', 'url' => $cat_url( 'arlington' ) ],
        [ 'label' => '📍 Austin, TX', 'url' => $cat_url( 'austin' ) ],
        [ 'label' => '📍 Houston, TX', 'url' => $cat_url( 'houston' ) ],
        [ 'label' => '📍 San Antonio, TX', 'url' => $search_url ],
        [ 'label' => '📍 Phoenix, AZ', 'url' => $search_url ],
        [ 'label' => '📍 Denver, CO', 'url' => $search_url ],
        [ 'label' => '📍 Chicago, IL', 'url' => $search_url ],
        [ 'label' => '📍 Miami, FL', 'url' => $search_url ],
        [ 'label' => '📍 Atlanta, GA', 'url' => $search_url ],
        [ 'label' => '📍 Seattle, WA', 'url' => $search_url ],
        [ 'label' => '📍 Boston, MA', 'url' => $search_url ],
        [ 'label' => '📍 Los Angeles, CA', 'url' => $search_url ],
        [ 'label' => '📍 New York, NY', 'url' => $search_url ],
    ];
}
?>

<!-- Blog Hero -->
<section class="blog-hero">
    <div class="blog-hero-eyebrow"><?php echo esc_html( $eyebrow ); ?></div>
    <h1 class="blog-hero-title"><?php echo wp_kses( $title, $em_kses ); ?></h1>
    <p class="blog-hero-sub"><?php echo esc_html( $sub ); ?></p>
    <div class="blog-stat-bar">
        <?php foreach ( $stats as $s ) : ?>
        <div class="blog-stat">
            <span class="blog-stat-val"><?php echo esc_html( $s['value'] ?? '' ); ?></span>
            <div class="blog-stat-label"><?php echo esc_html( $s['label'] ?? '' ); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Filter bar + Featured -->
<section class="inner-section inner-section--warm">
    <div class="blog-filter-bar" style="padding-left:0;padding-right:0">
        <?php foreach ( $filters as $i => $f ) : ?>
        <button class="filter-chip<?php echo $i === 0 ? ' active' : ''; ?>" data-blog-filter="<?php echo esc_attr( $f['slug'] ?? 'all' ); ?>">
            <?php echo wp_kses( $f['label'] ?? '', $em_kses ); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <a class="blog-featured" href="<?php echo esc_url( $f_link ); ?>">
        <div class="blog-featured-img"><?php echo esc_html( $f_icon ); ?></div>
        <div class="blog-featured-body">
            <div class="blog-featured-badge"><?php echo esc_html( $f_badge ); ?></div>
            <div class="blog-featured-title"><?php echo esc_html( $f_title ); ?></div>
            <p class="blog-featured-excerpt"><?php echo esc_html( $f_excerpt ); ?></p>
            <div class="blog-featured-meta">
                <?php
                $parts = array_map( 'trim', explode( '·', $f_meta ) );
                foreach ( $parts as $j => $p ) :
                    if ( $j > 0 ) echo '<span class="blog-featured-meta-sep">·</span>';
                    echo '<span>' . esc_html( $p ) . '</span>';
                endforeach;
                ?>
            </div>
            <div class="blog-featured-cta"><?php echo esc_html( $f_cta ); ?></div>
        </div>
    </a>
</section>

<!-- Blog Grid -->
<section class="inner-section inner-section--cream">
    <div class="inner-section-header">
        <div>
            <h2>Latest <em>Guides</em></h2>
            <p><?php echo esc_html( $grid_sub ); ?></p>
        </div>
        <div class="inner-section-aside"><a href="#">View all guides →</a></div>
    </div>
    <div class="blog-grid">
        <?php foreach ( $cards as $c ) :
            $c_link = $c['link'] ?: $detail_url;
        ?>
        <a class="blog-card" href="<?php echo esc_url( $c_link ); ?>">
            <div class="blog-card-icon" style="background:<?php echo esc_attr( $c['icon_bg'] ?? '#e6f4ea' ); ?>"><?php echo esc_html( $c['icon'] ?? '' ); ?></div>
            <div class="blog-card-body">
                <span class="blog-card-cat <?php echo esc_attr( $c['cat_class'] ?? 'blog-cat--home-care' ); ?>"><?php echo wp_kses( $c['category'] ?? '', $em_kses ); ?></span>
                <div class="blog-card-title"><?php echo esc_html( $c['title'] ?? '' ); ?></div>
                <p class="blog-card-excerpt"><?php echo esc_html( $c['excerpt'] ?? '' ); ?></p>
            </div>
            <div class="blog-card-footer">
                <span><?php echo esc_html( $c['meta'] ?? '' ); ?></span>
                <span class="blog-card-footer-link">Read more →</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Resources by City -->
<section class="inner-section inner-section--warm">
    <div class="inner-section-header">
        <div>
            <h2>Resources <em>by City</em></h2>
            <p><?php echo esc_html( $cities_sub ); ?></p>
        </div>
    </div>
    <div class="blog-city-grid">
        <?php foreach ( $cities as $city ) : ?>
        <a class="blog-city-link" href="<?php echo esc_url( $city['url'] ?? '#' ); ?>"><?php echo esc_html( $city['label'] ?? '' ); ?></a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Newsletter -->
<div class="blog-subscribe">
    <div>
        <h3><?php echo wp_kses( $n_title, $em_kses ); ?></h3>
        <p><?php echo esc_html( $n_sub ); ?></p>
    </div>
    <div>
        <form class="blog-subscribe-form" onsubmit="return false;">
            <input type="email" class="blog-subscribe-input" placeholder="Your email address…">
            <button type="submit" class="blog-subscribe-btn"><?php echo esc_html( $n_btn ); ?></button>
        </form>
        <p style="font-size:0.72rem;color:rgba(255,255,255,0.35);margin-top:10px"><?php echo esc_html( $n_fine ); ?></p>
    </div>
</div>

<!-- CTA band -->
<div class="cta-band">
    <div>
        <h2><?php echo wp_kses( $cb_title, $em_kses ); ?></h2>
        <p><?php echo esc_html( $cb_sub ); ?></p>
    </div>
    <div class="cta-band-actions">
        <button type="button" class="btn-primary-lg" data-open-form-modal><?php echo esc_html( $cb_primary ); ?></button>
    </div>
</div>

<?php get_footer(); ?>
