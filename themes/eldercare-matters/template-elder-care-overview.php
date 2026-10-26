<?php
/**
 * Template Name: ECM - Elder Care Overview
 *
 * Informational elder care page with a dynamic care category directory.
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

$top_categories = get_terms( [
    'taxonomy'   => 'job_listing_category',
    'parent'     => 0,
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
] );

if ( ! is_wp_error( $top_categories ) && $top_categories ) {
    $priority = [
        'home-care'        => 0,
        'assisted-living'  => 1,
        'memory-care'      => 2,
        'elder-law'        => 3,
        'care-management'  => 4,
        'hospice'          => 5,
        'grief-counselors' => 6,
    ];

    usort( $top_categories, function ( $a, $b ) use ( $priority ) {
        $a_order = $priority[ $a->slug ] ?? 99;
        $b_order = $priority[ $b->slug ] ?? 99;

        if ( $a_order === $b_order ) {
            return strcasecmp( $a->name, $b->name );
        }

        return $a_order <=> $b_order;
    } );
}

if ( ! function_exists( 'ecm_overview_term_summary' ) ) {
    function ecm_overview_term_summary( WP_Term $term ) : string {
        $description = term_description( $term->term_id, 'job_listing_category' );
        $description = wp_strip_all_tags( $description );

        if ( '' === trim( $description ) ) {
            return sprintf(
                /* translators: %s: category name. */
                __( 'Browse trusted local providers and resources for %s.', 'eldercare-matters' ),
                strtolower( $term->name )
            );
        }

        return wp_trim_words( $description, 22, '...' );
    }
}
?>

<div class="breadcrumb breadcrumb--plain">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'eldercare-matters' ); ?></a>
    <span class="breadcrumb-sep">&rsaquo;</span>
    <span class="breadcrumb-current"><?php the_title(); ?></span>
</div>

<main class="ecm-overview-page">
    <section class="ecm-overview-hero">
        <div class="ecm-overview-hero__content">
            <h1>
                <?php esc_html_e( 'What is', 'eldercare-matters' ); ?>
                <em><?php esc_html_e( 'Elder Care?', 'eldercare-matters' ); ?></em>
            </h1>
            <p><?php esc_html_e( 'Elder Care, also called Senior Care or Elderly Care, describes services that help older people live comfortably and independently.', 'eldercare-matters' ); ?></p>
        </div>
    </section>

    <section class="ecm-overview-content">
        <div class="ecm-overview-content__article">
            <?php
            the_content();

            wp_link_pages( [
                'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'eldercare-matters' ),
                'after'  => '</div>',
            ] );
            ?>
        </div>
    </section>

    <section class="ecm-category-directory" aria-labelledby="ecm-category-directory-title">
        <div class="ecm-category-directory__header">
            <div>
                <span class="ecm-overview-eyebrow"><?php esc_html_e( 'Explore by need', 'eldercare-matters' ); ?></span>
                <h2 id="ecm-category-directory-title"><?php esc_html_e( 'Elder care categories and services', 'eldercare-matters' ); ?></h2>
                <p><?php esc_html_e( 'Main categories are grouped with their subcategories so families can quickly find the right kind of help.', 'eldercare-matters' ); ?></p>
            </div>
            <a class="ecm-directory-all-link" href="<?php echo esc_url( home_url( '/find-care/' ) ); ?>">
                <?php esc_html_e( 'Find care providers', 'eldercare-matters' ); ?>
            </a>
        </div>

        <?php if ( $top_categories && ! is_wp_error( $top_categories ) ) : ?>
            <div class="ecm-category-directory__grid">
                <?php foreach ( $top_categories as $category ) :
                    $category_link = get_term_link( $category );
                    if ( is_wp_error( $category_link ) ) {
                        continue;
                    }

                    $subcategories = get_terms( [
                        'taxonomy'   => 'job_listing_category',
                        'parent'     => $category->term_id,
                        'hide_empty' => false,
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                    ] );
                    ?>
                    <article class="ecm-category-group">
                        <div class="ecm-category-group__top">
                            <div class="ecm-category-group__mark" aria-hidden="true">
                                <?php echo esc_html( strtoupper( substr( $category->name, 0, 1 ) ) ); ?>
                            </div>
                            <div>
                                <h3><a href="<?php echo esc_url( $category_link ); ?>"><?php echo esc_html( $category->name ); ?></a></h3>
                                <p><?php echo esc_html( ecm_overview_term_summary( $category ) ); ?></p>
                            </div>
                        </div>

                        <?php if ( $subcategories && ! is_wp_error( $subcategories ) ) : ?>
                            <div class="ecm-subcategory-list" aria-label="<?php echo esc_attr( sprintf( __( '%s subcategories', 'eldercare-matters' ), $category->name ) ); ?>">
                                <?php foreach ( $subcategories as $subcategory ) :
                                    $subcategory_link = get_term_link( $subcategory );
                                    if ( is_wp_error( $subcategory_link ) ) {
                                        continue;
                                    }
                                    ?>
                                    <a href="<?php echo esc_url( $subcategory_link ); ?>">
                                        <?php echo esc_html( $subcategory->name ); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="ecm-subcategory-list ecm-subcategory-list--empty">
                                <a href="<?php echo esc_url( $category_link ); ?>">
                                    <?php esc_html_e( 'View providers', 'eldercare-matters' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="empty-state">
                <h3><?php esc_html_e( 'No care categories found yet', 'eldercare-matters' ); ?></h3>
                <p><?php esc_html_e( 'Add categories under Listings to populate this directory automatically.', 'eldercare-matters' ); ?></p>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php get_footer(); ?>
