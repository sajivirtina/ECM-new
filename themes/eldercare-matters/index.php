<?php
/**
 * index.php — Fallback template
 *
 * WordPress requires this file for theme activation.
 * ElderCareMatters uses named page templates (front-page.php, template-*.php)
 * for all real content. This file handles any request that doesn't match a
 * more specific template (archive, search, 404, etc.).
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="ecm-main" class="ecm-main ecm-main--fallback" role="main">
    <?php if ( is_home() || is_category() || is_tag() || is_author() || is_search() || is_post_type_archive( 'provider_spotlight' ) ) :
        $posts_page_id = (int) get_option( 'page_for_posts' );
        $blog_label = $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Blog', 'eldercare-matters' );
        $breadcrumb_title = is_home()
            ? $blog_label
            : ( is_search()
                ? sprintf( __( 'Search results for: %s', 'eldercare-matters' ), get_search_query() )
                : wp_strip_all_tags( get_the_archive_title() ) );
    ?>
        <div class="breadcrumb" role="navigation" aria-label="<?php esc_attr_e( 'Breadcrumb', 'eldercare-matters' ); ?>">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'eldercare-matters' ); ?></a>
            <span class="breadcrumb-sep" aria-hidden="true">&rsaquo;</span>
            <?php if ( ! is_home() && ! is_search() && ! is_post_type_archive( 'provider_spotlight' ) && $posts_page_id ) : ?>
                <a href="<?php echo esc_url( get_permalink( $posts_page_id ) ); ?>"><?php echo esc_html( $blog_label ); ?></a>
                <span class="breadcrumb-sep" aria-hidden="true">&rsaquo;</span>
            <?php endif; ?>
            <span class="breadcrumb-current" aria-current="page"><?php echo esc_html( $breadcrumb_title ); ?></span>
        </div>
    <?php endif; ?>
    <div class="ecm-container">

        <?php if ( have_posts() ) : ?>

            <header class="ecm-archive-header">
                <?php if ( function_exists( 'ecm_is_resources_page' ) && ecm_is_resources_page() ) : ?>
                    <h1 class="ecm-archive-title"><?php echo esc_html( ECM_RESOURCES_HEADING ); ?></h1>
                    <div class="ecm-archive-description"><?php echo esc_html( ECM_RESOURCES_SUBHEAD ); ?></div>
                    <?php ecm_resources_render_filters(); ?>
                <?php elseif ( is_home() ) : ?>
                    <h1 class="ecm-archive-title"><?php
                        $posts_page_id = (int) get_option( 'page_for_posts' );
                        echo esc_html( $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Blog', 'eldercare-matters' ) );
                    ?></h1>
                <?php elseif ( is_search() ) : ?>
                    <h1 class="ecm-archive-title">
                        <?php printf( esc_html__( 'Search results for: %s', 'eldercare-matters' ), esc_html( get_search_query() ) ); ?>
                    </h1>
                <?php else : ?>
                    <?php the_archive_title( '<h1 class="ecm-archive-title">', '</h1>' ); ?>
                    <?php the_archive_description( '<div class="ecm-archive-description">', '</div>' ); ?>
                <?php endif; ?>
            </header>

            <?php if ( is_search() ) : ?>
                <div class="blog-grid ecm-search-grid">
                    <?php while ( have_posts() ) : the_post();
                        $categories       = get_the_category();
                        $post_type_object = get_post_type_object( get_post_type() );
                        $category_name    = $categories
                            ? $categories[0]->name
                            : ( $post_type_object ? $post_type_object->labels->singular_name : __( 'Content', 'eldercare-matters' ) );
                    ?>
                        <a id="post-<?php the_ID(); ?>" class="blog-card" href="<?php the_permalink(); ?>">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <div class="blog-card-icon blog-card-icon--photo">
                                    <?php the_post_thumbnail( 'medium_large', [ 'alt' => get_the_title() ] ); ?>
                                </div>
                            <?php else : ?>
                                <div class="blog-card-icon" aria-hidden="true">&#128214;</div>
                            <?php endif; ?>

                            <div class="blog-card-body">
                                <span class="blog-card-cat blog-cat--home-care"><?php echo esc_html( $category_name ); ?></span>
                                <div class="blog-card-title"><?php the_title(); ?></div>
                                <p class="blog-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>
                            </div>

                            <div class="blog-card-footer">
                                <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                                <span class="blog-card-footer-link"><?php esc_html_e( 'Read more', 'eldercare-matters' ); ?> &rarr;</span>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <div class="ecm-posts-grid">
                    <?php while ( have_posts() ) : the_post(); ?>

                        <article id="post-<?php the_ID(); ?>" <?php post_class( 'ecm-post-card' ); ?>>

                            <?php if ( has_post_thumbnail() ) : ?>
                                <a class="ecm-post-card__thumb" href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail( 'medium_large', [ 'alt' => get_the_title() ] ); ?>
                                </a>
                            <?php else : ?>
                                <div class="ecm-post-card__thumb ecm-post-card__thumb--placeholder" aria-hidden="true"></div>
                            <?php endif; ?>

                            <div class="ecm-post-card__body">
                                <?php if ( 'post' === get_post_type() ) : ?>
                                    <div class="ecm-post-card__meta">
                                        <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                                    </div>
                                <?php endif; ?>
                                <h2 class="ecm-post-card__title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h2>
                                <div class="ecm-post-card__excerpt">
                                    <?php the_excerpt(); ?>
                                </div>
                                <a class="ecm-btn ecm-btn--outline" href="<?php the_permalink(); ?>">
                                    <?php esc_html_e( 'Read More', 'eldercare-matters' ); ?>
                                </a>
                            </div>

                        </article>

                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <nav class="ecm-pagination" aria-label="<?php esc_attr_e( 'Posts pagination', 'eldercare-matters' ); ?>">
                <?php
                the_posts_pagination( [
                    'mid_size'  => 2,
                    'prev_text' => '&larr; ' . __( 'Previous', 'eldercare-matters' ),
                    'next_text' => __( 'Next', 'eldercare-matters' ) . ' &rarr;',
                ] );
                ?>
            </nav>

        <?php else : ?>

            <?php $ecm_is_resources = function_exists( 'ecm_is_resources_page' ) && ecm_is_resources_page(); ?>

            <?php if ( $ecm_is_resources ) : ?>
                <header class="ecm-archive-header">
                    <h1 class="ecm-archive-title"><?php echo esc_html( ECM_RESOURCES_HEADING ); ?></h1>
                    <div class="ecm-archive-description"><?php echo esc_html( ECM_RESOURCES_SUBHEAD ); ?></div>
                    <?php ecm_resources_render_filters(); ?>
                </header>
            <?php elseif ( is_home() ) : ?>
                <header class="ecm-archive-header">
                    <h1 class="ecm-archive-title"><?php
                        $posts_page_id = (int) get_option( 'page_for_posts' );
                        echo esc_html( $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Blog', 'eldercare-matters' ) );
                    ?></h1>
                </header>
            <?php endif; ?>

            <section class="ecm-no-results">
                <h1 class="ecm-no-results__title">
                    <?php esc_html_e( 'Nothing found', 'eldercare-matters' ); ?>
                </h1>
                <p class="ecm-no-results__message">
                    <?php echo $ecm_is_resources
                        ? esc_html( ECM_RESOURCES_NO_RESULTS )
                        : esc_html__( 'It looks like nothing was found at this location. Try searching below.', 'eldercare-matters' );
                    ?>
                </p>
                <?php get_search_form(); ?>
            </section>

        <?php endif; ?>

    </div>
</main>

<?php
get_footer();
