<?php
/**
 * page.php — Default template for standard WordPress Pages.
 *
 * Used for any Page that does NOT have one of the custom
 * page templates (template-*.php) assigned to it, and that
 * is not the configured static front page (front-page.php
 * always wins for that one).
 *
 * Without this file, WordPress falls back to index.php,
 * which is built only for archive/search/404 fallbacks —
 * that's why pages were showing "Archives" and a post loop
 * instead of their own content.
 *
 * Content is wrapped in .ecm-page-container (defined in
 * inner.css) so third-party shortcode output — WooCommerce
 * checkout/cart, contact forms, etc. — gets a proper max-width,
 * side padding, and baseline styling instead of rendering
 * edge-to-edge with no theme styling at all.
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

        <?php if ( has_excerpt() ) : ?>
            <p class="inner-hero-sub"><?php echo esc_html( get_the_excerpt() ); ?></p>
        <?php endif; ?>
    </header>

    <!-- Page Content -->
    <div class="ecm-page-content">
        <?php
        the_content();

        wp_link_pages( [
            'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'eldercare-matters' ),
            'after'  => '</div>',
        ] );
        ?>
    </div>

    <?php
    // If comments are open on this page (rare for Pages, but respect it).
    if ( comments_open() || get_comments_number() ) {
        comments_template();
    }
    ?>

</div>

<?php get_footer(); ?>
