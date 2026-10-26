<?php
/**
 * Archive for a WP Job Manager category (taxonomy: job_listing_category).
 *
 * Renders the prototype search.html design (assets/css/inner.css) against real
 * job_listing posts in the current category. Server-rendered, paginated, with
 * keyword + location + sort filters (plain GET form). Requires the theme to
 * declare add_theme_support('job-manager-templates') — see functions.php.
 */

get_header();

$term = get_queried_object();

// ── Filters (GET) ─────────────────────────────────────────────────────────────
$q       = isset( $_GET['q'] )   ? sanitize_text_field( wp_unslash( $_GET['q'] ) )   : '';
$loc     = isset( $_GET['loc'] ) ? sanitize_text_field( wp_unslash( $_GET['loc'] ) ) : '';
$orderby = ( isset( $_GET['orderby'] ) && 'date' === $_GET['orderby'] ) ? 'date' : 'featured';
$paged   = isset( $_GET['pg'] ) ? max( 1, (int) $_GET['pg'] ) : 1;
$per_page = 12;

// ── Query ─────────────────────────────────────────────────────────────────────
// Direct WP_Query (the plugin's get_job_listings() is customized on this install and
// drops the location filter + forces its own ordering). Category via tax_query,
// location via a meta OR across the geocoded fields, keyword via core search, and
// "Featured first" via menu_order (featured listings carry menu_order = -1).
$query_args = [
    'post_type'      => 'job_listing',
    'post_status'    => [ 'publish', 'expired' ],
    'ecm_include_lapsed' => true,
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'tax_query'      => [
        [
            'taxonomy'         => 'job_listing_category',
            'field'            => 'term_id',
            'terms'            => $term->term_id,
            'include_children' => true,
        ],
    ],
];

if ( '' !== $loc ) {
    $query_args['meta_query'] = [
        [
            'relation' => 'OR',
            [ 'key' => 'geolocation_formatted_address', 'value' => $loc, 'compare' => 'LIKE' ],
            [ 'key' => 'geolocation_state_long',        'value' => $loc, 'compare' => 'LIKE' ],
            [ 'key' => 'geolocation_city',              'value' => $loc, 'compare' => 'LIKE' ],
            [ 'key' => '_job_location',                 'value' => $loc, 'compare' => 'LIKE' ],
        ],
    ];
}

if ( '' !== $q ) {
    $query_args['s'] = $q;
}

if ( 'date' === $orderby ) {
    $query_args['orderby'] = 'date';
    $query_args['order']   = 'DESC';
} else {
    // Featured first, then newest.
    $query_args['orderby'] = [ 'menu_order' => 'ASC', 'date' => 'DESC' ];
}

$jobs = new WP_Query( $query_args );

$total     = (int) $jobs->found_posts;
$max_pages = (int) $jobs->max_num_pages;
$term_name = single_term_title( '', false );
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    <span class="breadcrumb-sep">›</span>
    <a href="<?php echo esc_url( home_url( '/find-care/' ) ); ?>">Find Care</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current"><?php echo esc_html( $term_name ); ?></span>
</div>

<!-- Search Hero -->
<section class="search-hero">
    <h1>Find <?php echo esc_html( $term_name ); ?> providers in <span style="white-space:nowrap"><em class="js-location-city">your area</em><button type="button" class="location-edit-pin" data-open-location-modal>📍</button></span></h1>
    <?php if ( term_description() ) : ?>
    <p><?php echo wp_kses_post( wp_strip_all_tags( term_description() ) ); ?></p>
    <?php else : ?>
    <p>Browse verified <?php echo esc_html( strtolower( $term_name ) ); ?> providers. Filter by keyword or location.</p>
    <?php endif; ?>

    <form class="search-bar" method="get" action="<?php echo esc_url( get_term_link( $term ) ); ?>" role="search">
        <input type="text" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="Search by name or specialty…" autocomplete="off">
        <input type="text" name="loc" value="<?php echo esc_attr( $loc ); ?>" placeholder="City or state…" autocomplete="off">
        <select name="orderby">
            <option value="featured" <?php selected( $orderby, 'featured' ); ?>>Sort: Featured first</option>
            <option value="date" <?php selected( $orderby, 'date' ); ?>>Sort: Newest</option>
        </select>
        <button type="submit" class="search-bar-btn">Search</button>
    </form>
</section>

<!-- Results -->
<section class="inner-section inner-section--warm" style="padding-top:28px">
    <div class="search-meta">
        <div class="search-meta-count"><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> <?php echo esc_html( _n( 'provider', 'providers', $total, 'eldercare-matters' ) ); ?> in <?php echo esc_html( $term_name ); ?></div>
        <div class="search-meta-sort"><?php echo 'date' === $orderby ? 'Newest first' : 'Featured first'; ?></div>
    </div>

    <?php if ( $jobs->have_posts() ) : ?>
    <div class="provider-grid">
        <?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>
            <?php get_template_part( 'template-parts/job-card' ); ?>
        <?php endwhile; ?>
    </div>

    <?php if ( $max_pages > 1 ) :
        $links = paginate_links( [
            'base'      => esc_url_raw( add_query_arg( 'pg', '%#%' ) ),
            'format'    => '',
            'current'   => $paged,
            'total'     => $max_pages,
            'prev_text' => '‹ Prev',
            'next_text' => 'Next ›',
            'type'      => 'plain',
        ] );
        if ( $links ) : ?>
        <nav class="provider-pagination"><?php echo wp_kses_post( $links ); ?></nav>
        <?php endif; ?>
    <?php endif; ?>

    <?php else : ?>
    <div class="empty-state">
        <div class="empty-state-icon">🔍</div>
        <h3>No <?php echo esc_html( strtolower( $term_name ) ); ?> providers match that search</h3>
        <p>Try a different keyword or location, or send us your request and we will look for verified providers near you.</p>
        <a href="<?php echo esc_url( home_url( '/contact-elder-care-matters/' ) ); ?>" class="btn-primary-lg" style="text-decoration:none;">📋 Request Help</a>
    </div>
    <?php endif; ?>
    <?php wp_reset_postdata(); ?>
</section>

<!-- CTA band -->
<div class="cta-band">
    <div>
        <h2>Can't find the right fit? <em>We'll match you.</em></h2>
        <p>Tell us what you need, we'll hand-pick up to 3 verified providers.</p>
    </div>
    <div class="cta-band-actions">
        <a href="<?php echo esc_url( home_url( '/contact-elder-care-matters/' ) ); ?>" class="btn-primary-lg" style="text-decoration:none;">📋 Request Help →</a>
    </div>
</div>

<?php get_footer(); ?>
