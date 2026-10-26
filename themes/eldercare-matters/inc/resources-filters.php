<?php
/**
 * Resources page search + category filters — Resources Changes doc,
 * checklist item 1: "Add search and the 8 category filters to Resources,
 * each with its own URL."
 *
 * /resources/ is the site's designated posts page (Settings > Reading),
 * which WordPress always renders through index.php's is_home() branch
 * regardless of the page's own assigned template — so template-blog.php's
 * (unused, ACF-driven) filter chips were never actually reachable. This
 * file builds the real thing into the template that's actually live:
 * a rewrite rule for /resources/{category-slug}/, matching the 8 clean
 * categories already used by job_listing_category (same slugs, so a
 * link or a category page can point at either taxonomy's archive with
 * the same slug), plus the query filtering and the chip/search markup
 * that index.php renders.
 *
 * Article retagging (item 7) is separate, ongoing work — these 8 terms
 * are created now, empty, so the URLs and chips are correct immediately;
 * each will simply show more as articles get retagged into it.
 */

defined( 'ABSPATH' ) || exit;

const ECM_RESOURCES_CATEGORIES = [
	'financial-planning-insurance'   => 'Financial Planning & Insurance',
	'health-medical-therapy'         => 'Health, Medical & Therapy',
	'in-home-daily-care'             => 'In-Home & Daily Care',
	'legal-estate-planning'          => 'Legal & Estate Planning',
	'litigation-advocacy'            => 'Litigation & Advocacy',
	'memory-special-needs-care'      => 'Memory & Special Needs Care',
	'move-organize-property'         => 'Move, Organize & Property',
	'senior-living-care-communities' => 'Senior Living & Care Communities',
];

/**
 * Resources Changes doc's own "Category tag mapping" table — the 3 pairs it
 * explicitly confirms (its 4th, Elder Care → "assign per article, too
 * broad," is deliberately excluded: the doc itself says that one needs a
 * real per-article look, not a blanket map). Until the full retag (item 7)
 * happens, a filter also matches the old slug its new category replaces,
 * so these 3 show real, already-correctly-categorized content today
 * instead of waiting on that separate pass.
 */
const ECM_RESOURCES_LEGACY_SLUGS = [
	'legal-estate-planning'  => [ 'elder-law' ],
	'health-medical-therapy' => [ 'health-wellness' ],
	'in-home-daily-care'     => [ 'care-management-2' ],
];

/** Creates the 8 terms if missing. Safe to run every time — term_exists() no-ops once they're there. */
function ecm_resources_ensure_category_terms() : void {
	foreach ( ECM_RESOURCES_CATEGORIES as $slug => $name ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			wp_insert_term( $name, 'category', [ 'slug' => $slug ] );
		}
	}
}
add_action( 'init', 'ecm_resources_ensure_category_terms', 5 );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'ecm_resources_cat';
	return $vars;
} );

add_action( 'init', function () {
	add_rewrite_rule(
		'^resources/([^/]+)/?$',
		'index.php?pagename=resources&ecm_resources_cat=$matches[1]',
		'top'
	);
} );

// One-time flush so the rule above takes effect, mirroring the transient-guarded
// pattern the other auto-create hooks in functions.php already use.
add_action( 'init', function () {
	if ( get_transient( 'ecm_resources_filters_rewrite_ok' ) ) return;
	flush_rewrite_rules( false );
	set_transient( 'ecm_resources_filters_rewrite_ok', 1, WEEK_IN_SECONDS );
}, 20 );

/** The active filter slug for the current request, or '' for "All". */
function ecm_resources_active_filter() : string {
	$slug = (string) get_query_var( 'ecm_resources_cat', '' );
	return isset( ECM_RESOURCES_CATEGORIES[ $slug ] ) ? $slug : '';
}

/** True when the current request is the Resources archive (any filter, with or without a search). */
function ecm_is_resources_page() : bool {
	return is_home() || 'resources' === (string) get_query_var( 'pagename' );
}

// Resources Changes doc, "Copy" table — item 3.
const ECM_RESOURCES_PAGE_TITLE   = 'Elder Care Guides for Families | ElderCareMatters';
const ECM_RESOURCES_HEADING      = 'Guides for families caring for someone they love';
const ECM_RESOURCES_SUBHEAD      = 'Practical answers on care options, costs, legal planning and daily support.';
const ECM_RESOURCES_SEARCH_PLACEHOLDER = 'Search guides, like "memory care cost"';
const ECM_RESOURCES_NO_RESULTS   = 'No guides match that search. Try a broader word, or browse by category.';

// <title> tag — this is the doc's literal full value (it already includes
// " | ElderCareMatters"), not just the page-name part WP would otherwise
// suffix with the site name on top of.
add_filter( 'pre_get_document_title', function ( $title ) {
	return ecm_is_resources_page() ? ECM_RESOURCES_PAGE_TITLE : $title;
}, 20 );
add_filter( 'wpseo_title', function ( $title ) {
	return ecm_is_resources_page() ? ECM_RESOURCES_PAGE_TITLE : $title;
}, 20 );

add_action( 'pre_get_posts', function ( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$on_resources = $query->is_home() || 'resources' === (string) get_query_var( 'pagename' );
	if ( ! $on_resources ) {
		return;
	}

	// Item 8: provider-facing articles (e.g. pitches aimed at providers/
	// financial advisors, not families) are tagged "Provider Resources" and
	// never belong in this family-facing feed, regardless of which filter
	// or search is active.
	$tax_query = [
		'relation' => 'AND',
		[
			'taxonomy' => 'category',
			'field'    => 'slug',
			'terms'    => [ 'provider-resources' ],
			'operator' => 'NOT IN',
		],
	];

	$active = ecm_resources_active_filter();
	if ( $active ) {
		$slugs       = array_merge( [ $active ], ECM_RESOURCES_LEGACY_SLUGS[ $active ] ?? [] );
		$tax_query[] = [
			'taxonomy' => 'category',
			'field'    => 'slug',
			'terms'    => $slugs,
		];
	}

	$query->set( 'tax_query', $tax_query );

	// The search box posts to /resources/ (or the active filter's URL) with
	// ?s=…, which makes WP treat it as a site search. Scope that search to
	// just Posts, so it doesn't also surface pages/listings.
	if ( $query->is_search() && isset( $_GET['ecm_resources_search'] ) ) {
		$query->set( 'post_type', 'post' );
	}
}, 15 ); // After the existing posts-per-page/orderby hook (added without a priority, so default 10).

/** Renders the search box + filter chip row. Called from index.php. */
function ecm_resources_render_filters() : void {
	$active      = ecm_resources_active_filter();
	$base_url    = home_url( '/resources/' );
	$current_url = $active ? trailingslashit( $base_url . $active ) : $base_url;
	$query       = get_search_query();
	?>
	<div class="resources-filters">
		<form class="resources-search" method="get" action="<?php echo esc_url( $current_url ); ?>" role="search">
			<input type="hidden" name="ecm_resources_search" value="1">
			<input
				type="search"
				name="s"
				class="resources-search-input"
				placeholder="<?php echo esc_attr( ECM_RESOURCES_SEARCH_PLACEHOLDER ); ?>"
				value="<?php echo esc_attr( $query ); ?>"
			>
			<button type="submit" class="resources-search-btn">Search</button>
		</form>
		<div class="resources-filter-chips">
			<a class="resources-chip<?php echo '' === $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $base_url ); ?>">All</a>
			<?php foreach ( ECM_RESOURCES_CATEGORIES as $slug => $label ) : ?>
				<a
					class="resources-chip<?php echo $active === $slug ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( trailingslashit( $base_url . $slug ) ); ?>"
				><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
