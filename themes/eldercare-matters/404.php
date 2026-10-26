<?php
/**
 * Missing-page template.
 *
 * @package ElderCareMatters
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<main id="ecm-main" class="ecm-not-found">
    <div class="ecm-not-found__content">
        <p class="ecm-not-found__code" aria-hidden="true">404</p>
        <p class="ecm-not-found__eyebrow"><?php esc_html_e( 'Page not found', 'eldercare-matters' ); ?></p>
        <h1><?php esc_html_e( 'Let’s get you back on the right path.', 'eldercare-matters' ); ?></h1>
        <p class="ecm-not-found__description"><?php esc_html_e( 'The page you’re looking for may have moved or is no longer available. We’re here to help you find the care and resources you need.', 'eldercare-matters' ); ?></p>

        <div class="ecm-not-found__actions">
            <a class="ecm-not-found__button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to Home', 'eldercare-matters' ); ?></a>
            <a class="ecm-not-found__button ecm-not-found__button--secondary" href="<?php echo esc_url( home_url( '/find-care/' ) ); ?>"><?php esc_html_e( 'Find Care', 'eldercare-matters' ); ?> <span aria-hidden="true">→</span></a>
        </div>

        <form class="ecm-not-found__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <label for="ecm-404-search"><?php esc_html_e( 'Or search the site', 'eldercare-matters' ); ?></label>
            <div class="ecm-not-found__search-fields">
                <input id="ecm-404-search" type="search" name="s" placeholder="<?php esc_attr_e( 'What are you looking for?', 'eldercare-matters' ); ?>" required>
                <button type="submit"><?php esc_html_e( 'Search', 'eldercare-matters' ); ?></button>
            </div>
        </form>
    </div>
</main>

<?php get_footer(); ?>
