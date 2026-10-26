<?php
/**
 * page-listed-on-eldercarematters-com.php
 *
 * Dedicated layout for the "Listed on ElderCareMatters.com" provider badge
 * page. WordPress auto-selects this template for the
 * /listed-on-eldercarematters-com/ page slug (same convention as
 * page-newsletter-signup.php) — no template needs to be assigned in the
 * editor.
 *
 * The page's post_content in the DB is the original copy/pasted-from-production
 * text (a `[accordion-item]` shortcode that doesn't exist in this theme, and
 * an embed-code sample wptexturize corrupted into curly quotes). Rather than
 * editing that DB content, this template ignores the_content() and renders
 * the fixed markup directly, reusing the site's existing FAQ accordion
 * pattern (.faq-item/.faq-q/.faq-icon/.faq-a — see template-parts/home-faq.php)
 * and the .ecm-code-block / .ecm-media-row / .faq-item--tall helpers added
 * to inner.css for this page.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! have_posts() ) {
    get_footer();
    return;
}

the_post();

$badge_img = 'https://eldercarematters.com/wp-content/uploads/2019/03/membership-badge-eldercare-matters-edited.jpg';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    <span class="breadcrumb-sep">&rsaquo;</span>
    <span class="breadcrumb-current"><?php the_title(); ?></span>
</div>

<div class="ecm-page-container">

    <!-- Page Header -->
    <header class="ecm-page-header">
        <h1 class="inner-hero-title"><?php the_title(); ?></h1>
    </header>

    <!-- Page Content -->
    <div class="ecm-page-content">

        <h2 style="text-align: center;"><span style="color: #800000;"><em><strong>Welcome to ElderCareMatters.com</strong></em></span></h2>

        <p>Welcome and congratulations on your new listing as an Elder Care Provider on <strong><a href="https://eldercarematters.com">ElderCareMatters.com</a></strong> – America&rsquo;s Directory of Elder Care / Senior Care Resources.</p>

        <p>Below is the <strong><span style="color: #800000;">Listed on ElderCareMatters.com </span></strong>graphic that we ask for you to add to your company website in order to let families across America know that your company has a listing on <a href="https://eldercarematters.com/"><strong>ElderCareMatters.com</strong></a>.</p>

        <div class="faq-items">
            <div class="faq-item faq-item--tall">
                <div class="faq-q">
                    Listed on ElderCareMatters.com Graphic &ndash; For Use on the Web
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-a">
                    <p>To add the &ldquo;Listed on ElderCareMatters.com&rdquo; graphic to your website, please copy the code below and paste it within your site. If your IT staff has any issues, please let us know! We can help.</p>
                    <div class="ecm-code-block">
                        <code>&lt;a href="https://eldercarematters.com"&gt;&lt;img src="<?php echo esc_attr( $badge_img ); ?>" alt="ElderCareMatters.com &ndash; National Elder Care Directory" width="325" height="267" /&gt;&lt;/a&gt;</code>
                        <button type="button" class="ecm-code-copy-btn">Copy code</button>
                    </div>
                </div>
            </div>
            <div class="faq-item faq-item--tall">
                <div class="faq-q">
                    Listed on ElderCareMatters.com Graphic &ndash; For Use in Print
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-a">
                    <div class="ecm-media-row">
                        <img src="<?php echo esc_url( $badge_img ); ?>" alt="Listed on ElderCareMatters.com &ndash; National Elder Care Directory badge" width="240" height="198" />
                        <p>To download this graphic, right-click the graphic and then &ldquo;Save image as&hellip;&rdquo; to save the graphic to your computer or device.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<?php get_footer(); ?>
