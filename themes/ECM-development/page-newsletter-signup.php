<?php
/**
 * Template Name: ECM - Newsletter Signup
 *
 * Dedicated layout for the newsletter signup page. WordPress also selects
 * this template automatically for the /newsletter-signup/ page slug.
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

$newsletter_intro = has_excerpt()
    ? get_the_excerpt()
    : __( 'Get practical elder care guidance, trusted resources, and thoughtful updates delivered to your inbox.', 'eldercare-matters' );
?>

<main class="newsletter-signup">
    <section class="newsletter-signup__section" aria-labelledby="newsletter-signup-title">
        <div class="newsletter-signup__intro">
            <span class="newsletter-signup__eyebrow"><?php esc_html_e( 'Stay Connected', 'eldercare-matters' ); ?></span>
            <h1 id="newsletter-signup-title" class="newsletter-signup__title"><?php the_title(); ?></h1>
            <p class="newsletter-signup__lead"><?php echo esc_html( $newsletter_intro ); ?></p>
        </div>

        <div class="newsletter-signup__panel">
            <div class="newsletter-signup__form">
                <?php
                the_content();

                wp_link_pages( [
                    'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'eldercare-matters' ),
                    'after'  => '</div>',
                ] );
                ?>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
