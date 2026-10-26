<?php
/**
 * ECM Search REST API
 *
 * Endpoints:
 *   GET /wp-json/ecm/v1/search          — provider search (Find Care page)
 *   GET /wp-json/ecm/v1/search/meta     — category metadata (for the chip row + category select)
 *   GET /wp-json/ecm/v1/search/cities   — searchable city picker (selectWoo AJAX source)
 *   GET /wp-json/ecm/v1/category        — category page: category meta + providers
 */

defined( 'ABSPATH' ) || exit;

// Lets a search like "pennsylvania" also match the state itself, not just
// cities whose name happens to contain those letters (see
// ecm_search_cities_endpoint()). Flip to false to turn it off without
// deleting the code.
if ( ! defined( 'ECM_SEARCH_CITIES_MATCH_STATES' ) ) {
	define( 'ECM_SEARCH_CITIES_MATCH_STATES', true );
}

add_action( 'rest_api_init', function () {

	register_rest_route( 'ecm/v1', '/search', [
		'methods'             => 'GET',
		'callback'            => 'ecm_search_endpoint',
		'permission_callback' => '__return_true',
		'args'                => [
			'q'           => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'category'    => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'subcategory' => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'city'        => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'state'       => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'featured'    => [ 'sanitize_callback' => 'absint',              'default' => 0 ],
			'sort'        => [ 'sanitize_callback' => 'sanitize_key',        'default' => 'recommended' ],
			'per_page'    => [ 'sanitize_callback' => 'absint',              'default' => 15 ],
			'page'        => [ 'sanitize_callback' => 'absint',              'default' => 1 ],
			'lat'         => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'lng'         => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
		],
	] );

	register_rest_route( 'ecm/v1', '/search/meta', [
		'methods'             => 'GET',
		'callback'            => 'ecm_search_meta_endpoint',
		'permission_callback' => '__return_true',
	] );

	// Searchable city picker (selectWoo AJAX source) — every city with a real
	// published listing, not just a fixed top-N, paginated + filtered by q.
	register_rest_route( 'ecm/v1', '/search/cities', [
		'methods'             => 'GET',
		'callback'            => 'ecm_search_cities_endpoint',
		'permission_callback' => '__return_true',
		'args'                => [
			'q'    => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'page' => [ 'sanitize_callback' => 'absint',              'default' => 1 ],
		],
	] );

	// Category page endpoint — full category meta + providers for a city.
	register_rest_route( 'ecm/v1', '/category', [
		'methods'             => 'GET',
		'callback'            => 'ecm_category_endpoint',
		'permission_callback' => '__return_true',
		'args'                => [
			'q'        => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'type'     => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'city'     => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'state'    => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'sort'     => [ 'sanitize_callback' => 'sanitize_key',        'default' => 'recommended' ],
			'per_page' => [ 'sanitize_callback' => 'absint',              'default' => 15 ],
			'page'     => [ 'sanitize_callback' => 'absint',              'default' => 1 ],
			'lat'      => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'lng'      => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
		],
	] );
} );

// ── Cache invalidation ───────────────────────────────────────────────────────
function ecm_cache_fingerprint() : string {
	static $fp = null;
	if ( null === $fp ) {
		$dir = get_template_directory() . '/inc/';
		$fp  = substr( md5( implode( '|', array_map( fn( $f ) => (int) @filemtime( $dir . $f ), [ 'search-api.php', 'lapsed-listings.php', 'package-capabilities.php' ] ) ) ), 0, 8 );
	}
	return $fp;
}

function ecm_cache_key( string $name ) : string {
	return 'ecm_' . $name . '_' . ecm_cache_fingerprint();
}

function ecm_search_cache_gen() : string {
	$inactive = function_exists( 'ecm_inactive_subscription_listing_ids' ) ? substr( md5( implode( ',', ecm_inactive_subscription_listing_ids() ) ), 0, 6 ) : '';
	return get_option( 'ecm_search_cache_gen', '0' ) . '-' . ecm_cache_fingerprint() . '-' . $inactive;
}

function ecm_warm_search_caches() : void {
	ecm_lapsed_listing_ids();
	ecm_inactive_subscription_listing_ids();
	ecm_featured_listing_ids_raw();
	ecm_get_city_index();
}
add_action( 'ecm_warm_search_caches', 'ecm_warm_search_caches' );

function ecm_clear_search_caches() : void {
	global $wpdb;
	if ( ! wp_next_scheduled( 'ecm_warm_search_caches' ) ) {
		wp_schedule_single_event( time() + 15, 'ecm_warm_search_caches' );
	}
	update_option( 'ecm_search_cache_gen', (string) microtime( true ), true );
	foreach ( [ 'cities', 'lapsed', 'inactive', 'featured' ] as $name ) {
		delete_transient( ecm_cache_key( $name ) );
	}
	delete_transient( 'ecm_search_meta_v7_main_categories' );
	foreach ( [ 'ecm_search_v1_', 'ecm_search_v2_', 'ecm_search_v3_', 'ecm_search_v4_', 'ecm_search_v5_', 'ecm_search_v6_', 'ecm_search_v7_', 'ecm_category_v1_', 'ecm_category_v2_', 'ecm_category_v3_', 'ecm_category_v4_', 'ecm_catgeo_', 'ecm_searchrank_', 'ecm_search_meta_', 'ecm_cities_', 'ecm_lapsed_', 'ecm_inactive_', 'ecm_featured_', 'ecm_citymatch_', 'ecm_badge_' ] as $prefix ) {
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_' . $prefix ) . '%',
			$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
		) );
	}
}

add_action( 'save_post_job_listing', function ( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	ecm_clear_search_caches();
} );

// Reviews are comments, not job_listing saves — need both hooks below:
// comment_post for a brand-new review landing straight on approved,
// transition_comment_status for one approved later from wp-admin.
add_action( 'comment_post', function ( $comment_id, $comment_approved, $commentdata ) {
	if ( 'approved' !== wp_get_comment_status( $comment_id ) ) {
		return;
	}
	if ( 'job_listing' !== get_post_type( $commentdata['comment_post_ID'] ?? 0 ) ) {
		return;
	}
	ecm_clear_search_caches();
}, 10, 3 );

add_action( 'transition_comment_status', function ( $new_status, $old_status, $comment ) {
	if ( $new_status === $old_status ) {
		return;
	}
	if ( 'approved' !== $new_status && 'approved' !== $old_status ) {
		return;
	}
	if ( 'job_listing' !== get_post_type( $comment->comment_post_ID ) ) {
		return;
	}
	ecm_clear_search_caches();
}, 10, 3 );

// ── /search ───────────────────────────────────────────────────────────────────

function ecm_search_endpoint( WP_REST_Request $req ) : WP_REST_Response {
	$q           = $req->get_param( 'q' );
	$category    = $req->get_param( 'category' );
	$subcategory = $req->get_param( 'subcategory' );
	$city        = $req->get_param( 'city' );
	$state       = $req->get_param( 'state' );
	$featured    = (bool) $req->get_param( 'featured' );
	$sort        = $req->get_param( 'sort' );
	$per_page    = min( 100, max( 1, (int) $req->get_param( 'per_page' ) ) );
	$page        = max( 1, (int) $req->get_param( 'page' ) );
	$origin      = ecm_request_origin( $req, $sort );

	$gen       = ecm_search_cache_gen();
	$cache_key = 'ecm_search_v7_' . md5( wp_json_encode( compact( 'q', 'category', 'subcategory', 'city', 'state', 'featured', 'sort', 'per_page', 'page', 'gen' ) + ( $origin ? [ 'origin' => $origin ] : [] ) ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return new WP_REST_Response( $cached, 200 );
	}

	$args = [
		'post_type'      => 'job_listing',
		'post_status'    => [ 'publish', 'expired' ],
		'ecm_include_lapsed' => true,
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'no_found_rows'  => false,
	];

	$taxonomy_term = $subcategory ?: $category;

	// A category name (or part of one) typed into the free-text search box
	// (e.g. "Assisted Living", or just "Assisted") never matched anything:
	// WP_Query's native 's' only searches post title/content, never taxonomy
	// term names, so a correctly categorized listing with no literal
	// occurrence of that phrase in its text was silently excluded. When the
	// typed query matches one or more real categories by name (and no
	// category was already picked from the dropdowns), filter by those
	// categories instead of running it as a text search.
	$q_category_ids = [];
	if ( '' !== $q && '' === $taxonomy_term ) {
		$matches = get_terms( [
			'taxonomy'   => 'job_listing_category',
			'name__like' => $q,
			'hide_empty' => false,
			'fields'     => 'ids',
		] );
		if ( ! is_wp_error( $matches ) ) {
			$q_category_ids = $matches;
		}
	}

	if ( $q_category_ids ) {
		$args['tax_query'] = [ [
			'taxonomy'         => 'job_listing_category',
			'field'            => 'term_id',
			'terms'            => $q_category_ids,
			'include_children' => true,
		] ];
	} else {
		if ( '' !== $q ) $args['s'] = $q;
		if ( '' !== $taxonomy_term ) {
			$args['tax_query'] = [ [
				'taxonomy'         => 'job_listing_category',
				'field'            => 'slug',
				'terms'            => $taxonomy_term,
				'include_children' => true,
			] ];
		}
	}

	if ( '' !== $city ) {
		$args['post__in'] = ecm_city_matching_listing_ids( $city ) ?: [ 0 ];
	}
	if ( '' !== $state ) {
		$args['meta_query'] = [ [ 'key' => 'geolocation_state_short', 'value' => $state, 'compare' => '=' ] ];
	}

	// Featured Directory Listings page: for now, shows every listing on the
	// Featured package tier — no additional flag required, so a paying
	// Featured customer always appears on the page named after what they
	// paid for. Package tier is a derived value (resolved through
	// _package_id/_user_package_id, not a directly queryable meta value),
	// so it can't be added as a normal meta_query clause. The Featured IDs
	// come from ecm_featured_listing_ids() (cached) and are applied as
	// post__in, so the query cost follows the number of Featured listings,
	// not the total number of listings.
	//
	// An extra admin-curation gate on top of tier was considered (either
	// WPJM's own disconnected "_featured" checkbox, or the newer
	// _ecm_admin_verified toggle from inc/verified-badge.php that also
	// drives the "✓ Verified" badge) but deliberately left out for now —
	// _ecm_admin_verified in particular starts unset on every listing, so
	// requiring it here would show zero results until each one is
	// individually approved. Uncomment below to require it once that
	// approval step is actually in use:
	// $verified_clause = [ 'key' => '_ecm_admin_verified', 'value' => '1', 'compare' => '=' ];
	// $args['meta_query'] = isset( $args['meta_query'] )
	// 	? [ 'relation' => 'AND', $args['meta_query'], $verified_clause ]
	// 	: [ $verified_clause ];
	if ( $featured ) {
		$args['post_status'] = 'publish';
		unset( $args['ecm_include_lapsed'] );
		$featured_ids = ecm_featured_listing_ids() ?: [ 0 ];
		// A city filter above may have already set post__in — intersect rather
		// than overwrite, so both filters apply together instead of one
		// silently winning.
		$args['post__in'] = isset( $args['post__in'] )
			? ( array_values( array_intersect( $args['post__in'], $featured_ids ) ) ?: [ 0 ] )
			: $featured_ids;
	}

	switch ( $sort ) {
		case 'newest':
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			break;
		case 'rating':
			// Most listings never have '_average_rating' at all (WPJM Reviews
			// only writes it once a listing gets its first review) — a plain
			// 'meta_key' => '_average_rating' would INNER JOIN and silently
			// drop every unrated listing from the results. The EXISTS +
			// NOT EXISTS OR clause below forces a LEFT JOIN instead, so
			// unrated listings stay in the result set (sorting last).
			// Merged with (not replacing) any city/state/featured meta_query
			// already built above — this used to clobber those filters.
			$rating_clause = [
				'relation' => 'OR',
				[ 'key' => '_average_rating', 'compare' => 'EXISTS' ],
				[ 'key' => '_average_rating', 'compare' => 'NOT EXISTS' ],
			];
			$args['meta_query'] = isset( $args['meta_query'] )
				? [ 'relation' => 'AND', $args['meta_query'], $rating_clause ]
				: $rating_clause;
			$args['orderby'] = 'meta_value_num';
			$args['order']   = 'DESC';
			break;
		case 'reviews':
			// comment_count is a real, always-present wp_posts column (0 by
			// default), so this sorts correctly with no missing-data risk.
			$args['orderby'] = 'comment_count';
			$args['order']   = 'DESC';
			break;
		default:
			$rank_sort = true;
	}

	$args['update_post_meta_cache'] = true;
	$args['update_post_term_cache'] = true;

	if ( ! empty( $rank_sort ) ) {
		$cand_key   = 'ecm_searchrank_' . ecm_cache_fingerprint() . '_' . md5( wp_json_encode( compact( 'q', 'category', 'subcategory', 'city', 'state', 'featured', 'gen' ) ) );
		$candidates = ecm_ranked_geo_candidates( $args, $cand_key );
		$rows       = ecm_rank_and_sort_candidates( $candidates, $origin ? $origin[0] : null, $origin ? $origin[1] : null );
		$ranked_ids = array_column( $rows, 'id' );
		$total      = count( $ranked_ids );
		$pages      = (int) ceil( $total / $per_page );
		$page_ids   = array_slice( $ranked_ids, ( $page - 1 ) * $per_page, $per_page );
		$posts      = $page_ids ? ( new WP_Query( [
			'post_type'              => 'job_listing',
			'post_status'            => [ 'publish', 'expired' ],
			'post__in'               => $page_ids,
			'orderby'                => 'post__in',
			'posts_per_page'         => count( $page_ids ),
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		] ) )->posts : [];
	} else {
		$query = new WP_Query( $args );
		$posts = $query->posts;
		$total = (int) $query->found_posts;
		$pages = (int) $query->max_num_pages;

		if ( $featured && ! $posts && $page > 1 ) {
			$args['paged'] = 1;
			$first         = new WP_Query( $args );
			$total         = (int) $first->found_posts;
			$pages         = (int) $first->max_num_pages;
		}
	}

	$providers = [];
	foreach ( $posts as $post ) {
		$providers[] = ecm_format_listing( $post );
	}
	wp_reset_postdata();

	$result = [
		'total'     => $total,
		'pages'     => $pages,
		'providers' => $providers,
	];
	set_transient( $cache_key, $result, DAY_IN_SECONDS );

	return new WP_REST_Response( $result, 200 );
}

/**
 * Rounds a lat/lng pair to a ~3.5 mile grid cell so nearby visitors share
 * the same cached, distance-sorted results; only applied for the
 * rank-based "recommended" sort (never "newest").
 */
function ecm_request_origin( WP_REST_Request $req, string $sort ) : ?array {
	$lat = $req->get_param( 'lat' );
	$lng = $req->get_param( 'lng' );
	if ( 'newest' === $sort ) {
		return null;
	}
	if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) || abs( (float) $lat ) > 90 || abs( (float) $lng ) > 180 ) {
		// No coordinates sent (the visitor's location is only a city or state name):
		// measure "nearest" from the searched city, or from the state when no city is set.
		$city  = trim( (string) $req->get_param( 'city' ) );
		$state = strtoupper( trim( (string) $req->get_param( 'state' ) ) );
		return ( '' !== $city || preg_match( '/^[A-Z]{2}$/', $state ) ) ? ecm_fallback_origin( $city, $state ) : null;
	}
	return [ round( round( (float) $lat / 0.05 ) * 0.05, 2 ), round( round( (float) $lng / 0.05 ) * 0.05, 2 ) ];
}

/**
 * Centre point of a city's (or, with no city, a state's) published listings,
 * for distance sorting when the request has no coordinates. Cached; rounded
 * to the same ~3.5 mile grid as real origins.
 */
function ecm_fallback_origin( string $city, string $state ) : ?array {
	$key = 'ecm_origin_' . ecm_cache_fingerprint() . '_' . md5( strtolower( $city . '|' . $state ) );
	$hit = get_transient( $key );
	if ( is_array( $hit ) ) {
		return $hit ?: null;
	}
	global $wpdb;
	$sql = "SELECT AVG(la.meta_value + 0) AS lat, AVG(lo.meta_value + 0) AS lng, COUNT(*) AS n
		FROM {$wpdb->posts} p
		JOIN {$wpdb->postmeta} la ON la.post_id = p.ID AND la.meta_key = 'geolocation_lat'
		JOIN {$wpdb->postmeta} lo ON lo.post_id = p.ID AND lo.meta_key = 'geolocation_long'
		JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = 'geolocation_state_short'
		JOIN {$wpdb->postmeta} ci ON ci.post_id = p.ID AND ci.meta_key = 'geolocation_city'
		WHERE p.post_type = 'job_listing' AND p.post_status = 'publish'
		  AND la.meta_value <> '' AND lo.meta_value <> ''
		  AND NOT ( ABS( la.meta_value - 40.712784 ) < 0.0005 AND ABS( lo.meta_value + 74.005941 ) < 0.0005 AND st.meta_value <> 'NY' )";
	$args = [];
	if ( '' !== $city ) {
		$sql   .= ' AND ci.meta_value = %s';
		$args[] = $city;
	}
	if ( preg_match( '/^[A-Z]{2}$/', $state ) ) {
		$sql   .= ' AND st.meta_value = %s';
		$args[] = $state;
	}
	$row = $wpdb->get_row( $args ? $wpdb->prepare( $sql, $args ) : $sql );
	if ( $row && (int) $row->n > 0 && null !== $row->lat ) {
		$origin = [ round( round( (float) $row->lat / 0.05 ) * 0.05, 2 ), round( round( (float) $row->lng / 0.05 ) * 0.05, 2 ) ];
	} elseif ( '' !== $city && preg_match( '/^[A-Z]{2}$/', $state ) ) {
		$origin = ecm_fallback_origin( '', $state ) ?: [];   // city has no coordinates: use its state
	} else {
		$origin = [];
	}
	set_transient( $key, $origin, 12 * HOUR_IN_SECONDS );
	return $origin ?: null;
}

/** Bulk-fetched, NY-fallback-point-excluded coordinates for many listings at once. */
function ecm_geo_bulk_coords( array $ids ) : array {
	if ( ! $ids ) {
		return [];
	}
	global $wpdb;
	$in  = implode( ',', array_map( 'intval', $ids ) );
	$raw = [];
	foreach ( $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('geolocation_lat','geolocation_long','geolocation_state_short') AND post_id IN ($in)" ) as $row ) {
		$raw[ (int) $row->post_id ][ $row->meta_key ] = $row->meta_value;
	}
	$coords = [];
	foreach ( $ids as $id ) {
		$lat = $raw[ $id ]['geolocation_lat'] ?? '';
		$lng = $raw[ $id ]['geolocation_long'] ?? '';
		$usable = is_numeric( $lat ) && is_numeric( $lng )
			&& ! ( abs( (float) $lat - 40.712784 ) < 0.0005 && abs( (float) $lng + 74.005941 ) < 0.0005 && 'NY' !== strtoupper( (string) ( $raw[ $id ]['geolocation_state_short'] ?? '' ) ) );
		$coords[ $id ] = $usable ? [ (float) $lat, (float) $lng ] : null;
	}
	return $coords;
}

/**
 * Builds (or reuses) a cached candidate list — id, tier rank, coordinates —
 * for the given WP_Query filter args. Cached per filter combination only
 * (never per visitor location), so it is shared by every visitor and every
 * origin; distance is applied afterwards, cheaply, by
 * ecm_rank_and_sort_candidates().
 */
function ecm_ranked_geo_candidates( array $query_args, string $cache_key ) : array {
	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$id_args = array_merge( $query_args, [
		'fields'                 => 'ids',
		'posts_per_page'         => -1,
		'no_found_rows'          => true,
		'orderby'                => [ 'menu_order' => 'ASC', 'date' => 'DESC' ],
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	] );
	unset( $id_args['paged'] );

	$ids        = array_map( 'intval', ( new WP_Query( $id_args ) )->posts );
	$candidates = [];
	if ( $ids ) {
		$ranks  = ecm_listing_tier_ranks( $ids );
		$coords = ecm_geo_bulk_coords( $ids );
		foreach ( $ids as $id ) {
			$c            = $coords[ $id ] ?? null;
			$candidates[] = [ 'id' => $id, 'rank' => $ranks[ $id ] ?? 3, 'lat' => $c[0] ?? null, 'lng' => $c[1] ?? null ];
		}
	}

	set_transient( $cache_key, $candidates, DAY_IN_SECONDS );
	return $candidates;
}

/** Tier rank first, then distance from $lat/$lng within the same tier (0 when no origin), then original order. */
function ecm_rank_and_sort_candidates( array $candidates, ?float $lat, ?float $lng ) : array {
	$rows = [];
	foreach ( $candidates as $pos => $c ) {
		if ( null === $lat ) {
			$dist = 0.0; // No origin: distance plays no part, only tier + original order.
		} elseif ( null === $c['lat'] || null === $c['lng'] ) {
			$dist = INF; // Origin given but this listing's location is unknown: goes last within its tier.
		} else {
			$dist = ecm_geo_distance_miles( $lat, $lng, $c['lat'], $c['lng'] );
		}
		$rows[] = [
			'id'   => $c['id'],
			'rank' => $c['rank'],
			'dist' => $dist,
			'pos'  => $pos,
		];
	}
	usort( $rows, fn( $a, $b ) => [ $a['rank'], $a['dist'], $a['pos'] ] <=> [ $b['rank'], $b['dist'], $b['pos'] ] );
	return $rows;
}

function ecm_featured_listing_ids() : array {
	return array_values( array_diff( ecm_featured_listing_ids_raw(), ecm_inactive_subscription_listing_ids() ) );
}

function ecm_featured_listing_ids_raw() : array {
	$cached = get_transient( ecm_cache_key( 'featured' ) );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$premium = (int) ( ecm_package_product_ids()['premium'] ?? 0 );
	$ids     = [];
	if ( $premium ) {
		$pm       = $wpdb->postmeta;
		$sources  = [ '_package_id', 'package_id', '_job_package_id', '_wcpl_jmfe_product_id' ];
		$live     = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wcpl_user_packages WHERE product_id = %d", $premium ) ) );
		if ( $live ) {
			$ids = $wpdb->get_col( "SELECT post_id FROM {$pm} WHERE meta_key = '_user_package_id' AND meta_value IN (" . implode( ',', $live ) . ')' );
		}
		foreach ( $sources as $key ) {
			$ids = array_merge( $ids, $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$pm} WHERE meta_key = %s AND meta_value = %d", $key, $premium ) ) );
		}
		$orphans = $wpdb->get_col(
			"SELECT um.post_id FROM {$pm} um
			 LEFT JOIN {$wpdb->prefix}wcpl_user_packages up ON up.id = um.meta_value
			 WHERE um.meta_key = '_user_package_id' AND um.meta_value <> '' AND up.id IS NULL"
		);
		if ( $orphans ) {
			$orphans   = array_map( 'intval', $orphans );
			$has_other = $wpdb->get_col( "SELECT DISTINCT post_id FROM {$pm} WHERE meta_key IN ('" . implode( "','", $sources ) . "') AND meta_value <> '' AND post_id IN (" . implode( ',', $orphans ) . ')' );
			$ids       = array_merge( $ids, array_diff( $orphans, array_map( 'intval', $has_other ) ) );
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( $ids ) {
			$ids = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'job_listing' AND post_status = 'publish' AND ID IN (" . implode( ',', $ids ) . ')' ) );
			update_meta_cache( 'post', $ids );
		}
	}
	$ids = array_values( array_filter( $ids, fn( $id ) => 'premium' === ecm_get_listing_tier( $id ) ) );
	set_transient( ecm_cache_key( 'featured' ), $ids, 6 * HOUR_IN_SECONDS );
	return $ids;
}

// ── /search/meta ──────────────────────────────────────────────────────────────

function ecm_search_meta_endpoint() : WP_REST_Response {
	$cache_key = 'ecm_search_meta_v7_main_categories';
	$cached    = get_transient( $cache_key );
	if ( $cached ) return new WP_REST_Response( $cached, 200 );

	$terms = get_terms( [
		'taxonomy'   => 'job_listing_category',
		'parent'     => 0,
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	] );

	$categories = [];
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$children = get_terms( [
				'taxonomy'   => 'job_listing_category',
				'parent'     => $term->term_id,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			] );
			$subcategories = [];
			if ( ! is_wp_error( $children ) ) {
				foreach ( $children as $child ) {
					$subcategories[] = [
						'key'   => $child->slug,
						'name'  => ecm_decode_display_text( $child->name ),
						'count' => (int) $child->count,
					];
				}
			}

			$categories[] = [
				'key'           => $term->slug,
				'name'          => ecm_decode_display_text( $term->name ),
				'icon'          => ecm_category_icon( $term->slug, $term->name ),
				'blurb'         => $term->description ? ecm_decode_display_text( $term->description ) : ecm_category_blurb( $term->slug ),
				'count'         => (int) $term->count,
				'subcategories' => $subcategories,
				'cross_sell'    => ecm_cross_sell( $term->slug ),
			];
		}
	}

	$data = compact( 'categories' );
	set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
	return new WP_REST_Response( $data, 200 );
}

// ── /search/cities ───────────────────────────────────────────────────────────
// selectWoo's AJAX source for the city picker: q empty → top cities by
// listing count (a sane default list on first open); q set → every matching
// city, alphabetically, paginated. Every city returned has >=1 real published
// listing — no fixed top-N cap, so small-market cities aren't hidden.
//
// Two city sources are merged:
//   1. geolocation_city — set by WPJM's geocoder (most listings).
//   2. geolocation_formatted_address / _job_location — for the minority of
//      listings where geocoding ran but didn't save geolocation_city. City is
//      extracted in PHP via ecm_extract_city_from_address().
function ecm_get_city_index() : array {
	$cached = get_transient( ecm_cache_key( 'cities' ) );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;

	// ── Primary: listings with a geocoded city ────────────────────────────────
	$primary_rows = $wpdb->get_results(
		"SELECT pm.meta_value AS city, COUNT(*) AS cnt
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = 'geolocation_city' AND pm.meta_value != ''
		   AND p.post_type = 'job_listing' AND " . ecm_public_listing_status_sql( 'p' ) . "
		 GROUP BY pm.meta_value"
	);

	// ── Fallback: listings whose geolocation_city was never saved ────────────
	// Extract city in PHP from geolocation_formatted_address (preferred — it's
	// Google-structured) or _job_location (raw provider input) when available.
	$fallback_rows = $wpdb->get_results(
		"SELECT COALESCE(fa.meta_value, '') AS formatted_address,
		        COALESCE(loc.meta_value, '') AS raw_location
		 FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} fa  ON fa.post_id  = p.ID AND fa.meta_key  = 'geolocation_formatted_address'
		 LEFT JOIN {$wpdb->postmeta} loc ON loc.post_id = p.ID AND loc.meta_key = '_job_location'
		 LEFT JOIN {$wpdb->postmeta} gc  ON gc.post_id  = p.ID AND gc.meta_key  = 'geolocation_city' AND gc.meta_value != ''
		 WHERE p.post_type = 'job_listing' AND " . ecm_public_listing_status_sql( 'p' ) . "
		   AND gc.post_id IS NULL
		   AND (fa.meta_value IS NOT NULL OR loc.meta_value IS NOT NULL)"
	);

	$city_map = [];
	foreach ( $primary_rows as $row ) {
		$city_map[ $row->city ] = (int) $row->cnt;
	}
	foreach ( $fallback_rows as $post ) {
		$addr = $post->formatted_address ?: $post->raw_location;
		$city = ecm_extract_city_from_address( $addr );
		if ( '' === $city ) continue;
		$city_map[ $city ] = ( $city_map[ $city ] ?? 0 ) + 1;
	}
	arsort( $city_map );

	$real_states = $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = 'geolocation_state_short' AND pm.meta_value != ''
		   AND p.post_type = 'job_listing' AND " . ecm_public_listing_status_sql( 'p' )
	);
	$states = [];
	foreach ( ecm_us_state_names() as $abbr => $name ) {
		if ( in_array( $abbr, $real_states, true ) ) {
			$states[ $abbr ] = $name;
		}
	}

	$index = [ 'cities' => $city_map, 'states' => $states ];
	set_transient( ecm_cache_key( 'cities' ), $index, 6 * HOUR_IN_SECONDS );
	return $index;
}

function ecm_get_city_picker_data() : array {
	$index  = ecm_get_city_index();
	$states = [];
	if ( ECM_SEARCH_CITIES_MATCH_STATES ) {
		foreach ( $index['states'] as $abbr => $name ) {
			$states[] = [ 'id' => 'state:' . $abbr, 'text' => $name ];
		}
	}
	return [
		'cities' => array_map( 'strval', array_keys( $index['cities'] ) ),
		'states' => $states,
	];
}

function ecm_search_cities_endpoint( WP_REST_Request $req ) : WP_REST_Response {
	$q        = trim( (string) $req->get_param( 'q' ) );
	$page     = max( 1, (int) $req->get_param( 'page' ) );
	$per_page = 20;
	$offset   = ( $page - 1 ) * $per_page;

	$index     = ecm_get_city_index();
	$all_names = array_map( 'strval', array_keys( $index['cities'] ) );

	$fold = function ( string $s ) : string {
		return strtolower( remove_accents( $s ) );
	};

	if ( '' !== $q ) {
		$needle    = $fold( $q );
		$all_names = array_values( array_filter( $all_names, function ( $c ) use ( $fold, $needle ) {
			return false !== strpos( $fold( $c ), $needle );
		} ) );
		sort( $all_names, SORT_STRING );
	}

	$has_more = count( $all_names ) > $offset + $per_page;
	$results  = array_map(
		fn( $c ) => [ 'id' => $c, 'text' => $c ],
		array_slice( $all_names, $offset, $per_page )
	);

	// State entries: injected on page 1 for both typed searches and the default
	// open — so "Pennsylvania" is visible without requiring the user to type it.
	// When searching: matching states prepended (most prominent). Default list:
	// states appended after the top cities.
	if ( ECM_SEARCH_CITIES_MATCH_STATES && 1 === $page ) {
		$state_matches = [];
		foreach ( $index['states'] as $abbr => $name ) {
			if ( '' !== $q && false === stripos( $name, $q ) ) continue;
			$state_matches[] = [ 'id' => 'state:' . $abbr, 'text' => $name ];
		}
		$results = '' !== $q
			? array_merge( $state_matches, $results )
			: array_merge( $results, $state_matches );
	}

	// Clearing back to "All cities" has no dedicated UI control (selectWoo's
	// own clear icon isn't used here — see search.js) — offer it as a real,
	// selectable first result instead, same as every other city. id can't be
	// '' — select2 treats an empty-string id as its own "nothing selected"
	// sentinel and won't render a real option for it — so this uses a
	// distinct sentinel value that search.js translates back to "no city".
	if ( '' === $q && 1 === $page ) {
		array_unshift( $results, [ 'id' => '__all__', 'text' => 'All cities' ] );
	}

	return new WP_REST_Response( [
		'results'    => $results,
		'pagination' => [ 'more' => $has_more ],
	], 200 );
}

/** US state/territory abbreviation → full name, for matching a typed state name in the city picker. */
function ecm_us_state_names() : array {
	return [
		'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
		'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
		'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
		'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
		'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
		'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
		'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
		'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
		'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
		'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
		'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
		'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
		'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
	];
}

/**
 * Extract a city name from an address string such as
 * "1234 Main St, Springfield, IL 62701, USA" or "City, State, USA".
 *
 * Strategy: split on comma, strip trailing country and bare ZIP, then take
 * the segment immediately before the state segment as the city. Returns ''
 * when the result looks like a street address (contains digits) or is too
 * short to be a real city name.
 */
function ecm_extract_city_from_address( string $addr ) : string {
	// Trim everything after the first semicolon — some entries append a second
	// address line: "Street, City, ST; P.O. Box ..."
	$addr  = trim( explode( ';', $addr )[0] );
	$parts = array_map( 'trim', explode( ',', $addr ) );
	$count = count( $parts );
	if ( $count < 2 ) return '';

	// Drop trailing country token when recognised.
	static $countries = [ 'USA', 'US', 'UK', 'UNITED STATES', 'UNITED KINGDOM', 'ENGLAND', 'CANADA' ];
	if ( in_array( strtoupper( $parts[ $count - 1 ] ), $countries, true ) ) {
		array_pop( $parts );
		$count = count( $parts );
	}
	// e.g. "California, USA" → only one part left → no distinct city.
	if ( $count < 2 ) return '';

	// Drop trailing bare ZIP segment (all digits, optionally ZIP+4).
	if ( preg_match( '/^\d{4,5}(-\d{4})?$/', $parts[ $count - 1 ] ) ) {
		array_pop( $parts );
		$count = count( $parts );
	}
	if ( $count < 2 ) return '';

	// City = segment immediately before the last (state / "State ZIP") segment.
	$candidate = $parts[ $count - 2 ];

	// Reject if it looks like a street address (contains any digit).
	if ( preg_match( '/\d/', $candidate ) ) return '';
	if ( strlen( $candidate ) < 3 ) return '';

	return $candidate;
}

// ── /category ─────────────────────────────────────────────────────────────────

function ecm_category_endpoint( WP_REST_Request $req ) : WP_REST_Response {
	$q        = $req->get_param( 'q' );
	$type     = $req->get_param( 'type' );
	$city     = $req->get_param( 'city' );
	$state    = $req->get_param( 'state' );
	$sort     = $req->get_param( 'sort' );
	$per_page = min( 100, max( 1, (int) $req->get_param( 'per_page' ) ) );
	$page     = max( 1, (int) $req->get_param( 'page' ) );
	$rank_sort = ( 'newest' !== $sort );
	$origin    = ecm_request_origin( $req, $sort );

	// Same per-combination caching as ecm_search_endpoint() above.
	$gen       = ecm_search_cache_gen();
	$cache_key = 'ecm_category_v4_' . md5( wp_json_encode( compact( 'q', 'type', 'city', 'state', 'sort', 'per_page', 'page', 'gen' ) + ( $origin ? [ 'origin' => $origin ] : [] ) ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return new WP_REST_Response( $cached, 200 );
	}

	// Category metadata from taxonomy term.
	$term = $type ? get_term_by( 'slug', $type, 'job_listing_category' ) : null;
	$category = [
		'key'        => $type,
		'name'       => $term ? ecm_decode_display_text( $term->name ) : ucwords( str_replace( '-', ' ', $type ) ),
		'icon'       => ecm_category_icon( $type, $term ? $term->name : '' ),
		'blurb'      => ( $term && $term->description ) ? ecm_decode_display_text( $term->description ) : ecm_category_blurb( $type ),
		'count'      => $term ? (int) $term->count : 0,
		'cross_sell' => ecm_cross_sell( $type ),
	];

	if ( $rank_sort ) {
		$lat_arg = $origin ? $origin[0] : null;
		$lng_arg = $origin ? $origin[1] : null;
		[ $providers, $total, $pages ] = ecm_category_nearest_providers( $type, $city, $state, $lat_arg, $lng_arg, $q, $per_page, $page );
		$result = [
			'category'  => $category,
			'providers' => $providers,
			'total'     => $total,
			'pages'     => $pages,
		];
		set_transient( $cache_key, $result, DAY_IN_SECONDS );
		return new WP_REST_Response( $result, 200 );
	}

	// 'newest' — a plain, unranked, most-recent-first query.
	$args = [
		'post_type'              => 'job_listing',
		'post_status'            => [ 'publish', 'expired' ],
		'ecm_include_lapsed'     => true,
		'posts_per_page'         => $per_page,
		'paged'                  => $page,
		'no_found_rows'          => false,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
		'orderby'                => [ 'date' => 'DESC' ],
	] + ecm_category_filter_args( $type, $city, $state );

	// Search must run across the complete category/location result set before
	// pagination, otherwise the browser can only match providers on its current page.
	if ( $q ) {
		$args['posts_per_page'] = -1;
		$args['no_found_rows']  = true;
		unset( $args['paged'] );
	}

	$query     = new WP_Query( $args );
	$providers = [];
	foreach ( $query->posts as $post ) {
		$providers[] = ecm_format_listing( $post );
	}
	wp_reset_postdata();

	if ( $q ) {
		$providers = ecm_filter_providers_by_query( $providers, $q );
		$total     = count( $providers );
		$pages     = (int) ceil( $total / $per_page );
		$providers = array_slice( $providers, ( $page - 1 ) * $per_page, $per_page );
	} else {
		$total = (int) $query->found_posts;
		$pages = (int) $query->max_num_pages;
	}

	$result = [
		'category'  => $category,
		'providers' => $providers,
		'total'     => $total,
		'pages'     => $pages,
	];
	set_transient( $cache_key, $result, DAY_IN_SECONDS );

	return new WP_REST_Response( $result, 200 );
}

/**
 * Listing IDs matching a city — the listing's real geolocation_city first;
 * only falls back to a LIKE match against the full formatted address or the
 * free-text _job_location for listings that have no geolocation_city saved
 * at all. Matching those two fields unconditionally (the previous behaviour)
 * let a city name appearing anywhere in a street address or description —
 * e.g. "Tamiami Trail" containing "Miami" — wrongly surface a listing from a
 * different city; confirmed live with exactly that address on 4 real
 * listings. A meta_query expressing this (LIKE-or-(NOT EXISTS-and-LIKE))
 * makes WordPress build it as a correlated subquery that took ~180s; a flat
 * LEFT JOIN query computing the id list directly runs in well under 100ms,
 * cached per city so repeat requests don't pay even that.
 */
function ecm_city_matching_listing_ids( string $city ) : array {
	if ( '' === $city ) {
		return [];
	}
	$key    = 'ecm_citymatch_' . ecm_cache_fingerprint() . '_' . md5( $city );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $city ) . '%';
	$ids  = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT p.ID
		 FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} gc ON gc.post_id = p.ID AND gc.meta_key = 'geolocation_city'
		 LEFT JOIN {$wpdb->postmeta} fa ON fa.post_id = p.ID AND fa.meta_key = 'geolocation_formatted_address'
		 LEFT JOIN {$wpdb->postmeta} jl ON jl.post_id = p.ID AND jl.meta_key = '_job_location'
		 WHERE p.post_type = 'job_listing'
		   AND (
		       gc.meta_value LIKE %s
		       OR ( ( gc.meta_value IS NULL OR gc.meta_value = '' ) AND ( fa.meta_value LIKE %s OR jl.meta_value LIKE %s ) )
		   )",
		$like, $like, $like
	) ) );
	set_transient( $key, $ids, 6 * HOUR_IN_SECONDS );
	return $ids;
}

function ecm_category_filter_args( string $type, string $city, string $state ) : array {
	$args = [];

	if ( $type ) {
		$args['tax_query'] = [ [
			'taxonomy'         => 'job_listing_category',
			'field'            => 'slug',
			'terms'            => $type,
			'include_children' => true,
		] ];
	}

	if ( $city ) {
		$args['post__in'] = ecm_city_matching_listing_ids( $city ) ?: [ 0 ];
	}

	if ( $state ) {
		$args['meta_query'] = [ [ 'key' => 'geolocation_state_short', 'value' => $state, 'compare' => '=' ] ];
	}

	return $args;
}

function ecm_filter_providers_by_query( array $providers, string $q ) : array {
	$needle = mb_strtolower( $q );
	return array_values( array_filter( $providers, function ( array $provider ) use ( $needle ) : bool {
		$searchable = implode( ' ', [
			$provider['name'] ?? '',
			$provider['tagline'] ?? '',
			$provider['city_label'] ?? '',
			implode( ' ', $provider['specialties'] ?? [] ),
		] );
		return false !== mb_strpos( mb_strtolower( $searchable ), $needle );
	} ) );
}

function ecm_geo_distance_miles( float $lat1, float $lng1, float $lat2, float $lng2 ) : float {
	$d_lat = deg2rad( $lat2 - $lat1 );
	$d_lng = deg2rad( $lng2 - $lng1 );
	$a     = sin( $d_lat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lng / 2 ) ** 2;
	return 3958.8 * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
}

function ecm_category_geo_candidates( string $type, string $city, string $state ) : array {
	$key  = 'ecm_catgeo_' . ecm_cache_fingerprint() . '_' . md5( wp_json_encode( [ $type, $city, $state, ecm_search_cache_gen() ] ) );
	$args = [
		'post_type'          => 'job_listing',
		'post_status'        => [ 'publish', 'expired' ],
		'ecm_include_lapsed' => true,
	] + ecm_category_filter_args( $type, $city, $state );
	return ecm_ranked_geo_candidates( $args, $key );
}

function ecm_category_nearest( string $type, string $city, string $state, ?float $lat, ?float $lng ) : array {
	return ecm_rank_and_sort_candidates( ecm_category_geo_candidates( $type, $city, $state ), $lat, $lng );
}

function ecm_category_nearest_providers( string $type, string $city, string $state, ?float $lat, ?float $lng, string $q, int $per_page, int $page ) : array {
	$rows = ecm_category_nearest( $type, $city, $state, $lat, $lng );
	$ids  = array_column( $rows, 'id' );
	$take = $q ? $ids : array_slice( $ids, ( $page - 1 ) * $per_page, $per_page );

	$providers = [];
	if ( $take ) {
		$query = new WP_Query( [
			'post_type'              => 'job_listing',
			'post_status'            => [ 'publish', 'expired' ],
			'post__in'               => $take,
			'orderby'                => 'post__in',
			'posts_per_page'         => count( $take ),
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		] );
		foreach ( $query->posts as $post ) {
			$providers[] = ecm_format_listing( $post );
		}
		wp_reset_postdata();
	}

	if ( $q ) {
		$providers = ecm_filter_providers_by_query( $providers, $q );
		$total     = count( $providers );
		$providers = array_slice( $providers, ( $page - 1 ) * $per_page, $per_page );
	} else {
		$total = count( $ids );
	}

	return [ $providers, $total, (int) ceil( $total / $per_page ) ];
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Some listing titles/descriptions/category names were imported with HTML
 * entities already encoded in the stored text (e.g. post_title literally
 * containing the 5 characters "&amp;" instead of "&") — confirmed via a
 * direct DB check, not a guess. Left as-is, the client's own esc() then
 * encodes that stored "&amp;" a second time into "&amp;amp;", which the
 * browser renders as literal "&amp;" text. Decoding once here, before the
 * text is sent, means the client only ever escapes real characters.
 * Idempotent for text that was never encoded in the first place.
 */
function ecm_decode_display_text( string $text ) : string {
	return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Card-sized image URL. Listings often store the original upload (several
 * thousand pixels wide); decoding those for every card makes images flash
 * blank while scrolling. This resolves a stored value (attachment ID, ACF
 * image array or plain URL) to a registered smaller size when the file is a
 * media-library attachment, and falls back to the stored URL otherwise.
 */
function ecm_card_image_url( $value, string $size = 'medium_large' ) : string {
	$id = 0;
	if ( is_numeric( $value ) ) {
		$id = (int) $value;
	} elseif ( is_array( $value ) ) {
		if ( ! empty( $value['sizes'][ $size ] ) ) {
			return (string) $value['sizes'][ $size ];
		}
		$id    = ! empty( $value['id'] ) ? (int) $value['id'] : 0;
		$value = (string) ( $value['url'] ?? '' );
	}
	if ( ! $id && is_string( $value ) && '' !== $value ) {
		$id = (int) attachment_url_to_postid( $value );
	}
	if ( $id ) {
		$url = wp_get_attachment_image_url( $id, $size );
		if ( $url ) {
			return (string) $url;
		}
	}
	$url = is_string( $value ) ? $value : '';
	return '' === $url ? '' : ecm_card_resized_upload_url( $url, 768 );
}

/**
 * For an image file in the uploads folder that is not a media-library
 * attachment (company logos uploaded through the listing form): return the
 * URL of a "-card{$width}" copy when one exists. A missing copy is made later
 * by WP-Cron (ecm_card_resize_image), never inside the search request, and
 * the original URL is returned meanwhile.
 */
function ecm_card_resized_upload_url( string $url, int $width ) : string {
	$uploads = wp_get_upload_dir();
	$marker  = '/wp-content/uploads/';
	$pos     = strpos( $url, $marker );
	if ( empty( $uploads['baseurl'] ) || false === $pos ) {
		return $url;
	}
	// Stored URLs can carry another host (a copied database): map by the path below /uploads/.
	$rel  = substr( $url, $pos + strlen( $marker ) );
	$base = rtrim( str_replace( '\\', '/', $uploads['basedir'] ), '/' );
	$path = $base . '/' . $rel;
	if ( ! preg_match( '/\.(jpe?g|png)$/i', $path ) ) {
		return $url;
	}
	$small = preg_replace( '/\.(jpe?g|png)$/i', '-card' . $width . '.$1', $path );
	if ( file_exists( $small ) ) {
		return rtrim( $uploads['baseurl'], '/' ) . '/' . ltrim( substr( $small, strlen( $base ) ), '/' );
	}
	if ( is_readable( $path ) ) {
		$key = 'ecm_card_rs_' . md5( $path );
		if ( false === get_transient( $key ) ) {
			set_transient( $key, 1, DAY_IN_SECONDS ); // one attempt per image per day, success or not
			wp_schedule_single_event( time() + 10, 'ecm_card_resize_image', [ $path, $small, $width ] );
		}
	}
	return $url;
}

/** Background job: write the card-sized copy. Skips files that would not fit in memory. */
add_action( 'ecm_card_resize_image', function ( $path, $small, $width ) {
	if ( file_exists( $small ) || ! is_readable( $path ) ) {
		return;
	}
	$info = @getimagesize( $path );
	if ( ! $info || (int) $info[0] <= (int) $width + 100 ) {
		return;
	}
	// Decoding with GD needs roughly 5 bytes per pixel. Above ~30 megapixels only
	// Imagick is used, asking the JPEG decoder for a reduced-size read so the
	// full bitmap is never held in memory.
	if ( (int) $info[0] * (int) $info[1] > 30000000 ) {
		if ( ! class_exists( 'Imagick' ) || ! preg_match( '/\.jpe?g$/i', $path ) ) {
			return;
		}
		try {
			$im = new Imagick();
			$im->setOption( 'jpeg:size', ( (int) $width * 2 ) . 'x' . ( (int) $width * 2 ) );
			$im->readImage( $path );
			$im->thumbnailImage( (int) $width, 0 );
			$im->setImageCompressionQuality( 82 );
			$im->writeImage( $small );
			$im->clear();
		} catch ( Throwable $e ) {
			return;
		}
		return;
	}
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	$editor = wp_get_image_editor( $path );
	if ( is_wp_error( $editor ) ) {
		return;
	}
	$editor->resize( (int) $width, null, false );
	$editor->save( $small );
}, 10, 3 );

function ecm_format_listing( WP_Post $post ) : array {
	$title = ecm_decode_display_text( $post->post_title );

	preg_match_all( '/\b\w/u', $title, $m );
	$initials = strtoupper( implode( '', array_slice( $m[0], 0, 2 ) ) );
	if ( ! $initials ) $initials = strtoupper( mb_substr( $title, 0, 2 ) );

	$palette = [ '#C4933A', '#4f6b53', '#7a5a1e', '#5b6864', '#A0703A', '#3f7a6a', '#8a6d3b', '#6a5acd' ];
	$color   = $palette[ $post->ID % count( $palette ) ];

	$logo_url = get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '';
	if ( ! $logo_url ) $logo_url = ecm_card_image_url( get_post_meta( $post->ID, '_company_avatar', true ), 'medium' );

	// Card-sized (768px) hero, not the original upload.
	$hero_url = ecm_card_image_url( get_post_meta( $post->ID, '_featured_image', true ), 'medium_large' );

	$city  = (string) get_post_meta( $post->ID, 'geolocation_city',        true );
	$state = (string) get_post_meta( $post->ID, 'geolocation_state_short', true );
	if ( ! $city ) $city = (string) get_post_meta( $post->ID, '_job_location', true );
	$city_label = $city ? ( $state ? $city . ', ' . $state : $city ) : '';

	$terms       = get_the_terms( $post->ID, 'job_listing_category' ) ?: [];
	$specialties = array_map( fn( $t ) => ecm_decode_display_text( $t->name ), array_slice( $terms, 0, 3 ) );
	$cat_slug    = $terms ? $terms[0]->slug : '';
	$cat_name    = $terms ? ecm_decode_display_text( $terms[0]->name ) : '';

	$description_sources = [
		get_post_meta( $post->ID, '_company_tagline', true ),
		$post->post_excerpt,
		$post->post_content,
		get_post_meta( $post->ID, '_job_description', true ),
	];
	$tagline = '';
	foreach ( $description_sources as $description ) {
		$description = trim( wp_strip_all_tags( ecm_decode_display_text( (string) $description ) ) );
		if ( $description ) {
			$tagline = wp_trim_words( $description, 15, '…' );
			break;
		}
	}
	if ( ! $tagline ) {
		if ( $cat_name && $city_label ) {
			$tagline = sprintf( '%s provides %s services in %s.', $title, strtolower( $cat_name ), $city_label );
		} elseif ( $cat_name ) {
			$tagline = sprintf( '%s provides %s services for older adults and families.', $title, strtolower( $cat_name ) );
		} elseif ( $city_label ) {
			$tagline = sprintf( '%s serves older adults and families in %s.', $title, $city_label );
		} else {
			$tagline = sprintf( '%s supports older adults and families with trusted care services.', $title );
		}
	}

	// WP Job Manager Reviews stores the rolling average in '_average_rating'
	// post meta (real key) — but review COUNT has no post-meta key at all;
	// '_review_count' and '_job_rating' below were never written by the
	// add-on, so review counts silently stayed 0 on every provider card.
	// wpjmr_get_reviews_count() is the add-on's own counter (comments on
	// this post with review_stars/review_average meta), same source used on
	// the single listing page (single-job_listing.php).
	//
	// Star ratings/reviews are a Basic-and-up feature (not Free), based on
	// the authoritative package tier resolved from the listing's package ID.
	$package_tier = function_exists( 'ecm_get_listing_display_tier' ) ? ecm_get_listing_display_tier( $post->ID ) : 'free';
	$plan_name    = function_exists( 'ecm_get_listing_display_plan_name' ) ? ecm_get_listing_display_plan_name( $post->ID ) : '';
	$badge_tier   = $package_tier;
	if ( 'free' === $package_tier ) {
		$avg_rating   = 0.0;
		$review_count = 0;
	} else {
		$avg_rating   = (float) get_post_meta( $post->ID, '_average_rating', true );
		$review_count = function_exists( 'wpjmr_get_reviews_count' ) ? wpjmr_get_reviews_count( $post->ID ) : 0;
	}
	$avg_rating   = $avg_rating ? round( $avg_rating, 1 ) : 0.0;

	$verified_badge = ( function_exists( 'ecm_verification_cards_enabled' ) && ecm_verification_cards_enabled() ) ? ecm_verification_public_badge( $post->ID ) : null;

	return [
		'id'          => $post->ID,
		'name'        => $title,
		'initials'    => $initials,
		'color'       => $color,
		'logo_url'    => $logo_url,
		'hero_url'    => esc_url_raw( $hero_url ),
		'category'    => $cat_slug,
		'cat_name'    => $cat_name,
		'city'        => $city,
		'state'       => $state,
		'city_label'  => $city_label,
		'tier'        => $badge_tier,
		'plan_name'   => $plan_name,
		'tagline'     => $tagline,
		'specialties' => $specialties,
		'rating'      => $avg_rating,
		'reviews'     => $review_count,
		'nonprofit'   => (bool) get_post_meta( $post->ID, '_company_nonprofit', true ),
		'verified'    => $verified_badge ? ecm_verification_badge_text( $verified_badge ) : '',
		'verified_hint' => $verified_badge ? ecm_verification_badge_hover( $verified_badge ) : '',
		'permalink'   => get_permalink( $post ),
	];
}

function ecm_category_icon( string $slug, string $name ) : string {
	$map = [
		'home-care'       => '🏠',
		'assisted-living' => '🏡',
		'memory-care'     => '🧠',
		'elder-law'       => '⚖️',
		'care-management' => '📋',
		'hospice'         => '🤝',
		'grief'           => '💜',
		'nursing'         => '🏥',
		'adult-day'       => '☀️',
		'independent'     => '🏘️',
		'rehab'           => '💪',
		'dementia'        => '🧠',
		'transportation'  => '🚗',
	];
	foreach ( $map as $key => $icon ) {
		if ( str_contains( $slug, $key ) || str_contains( strtolower( $name ), $key ) ) return $icon;
	}
	return '🏥';
}

function ecm_category_blurb( string $slug ) : string {
	$map = [
		'home-care'       => 'Daily non-medical support delivered in the comfort of home — bathing, meals, medication reminders, companionship.',
		'assisted-living' => 'Community living with on-site staff, meals, activities, and medical support.',
		'memory-care'     => 'Specialized support for Alzheimer\'s, dementia, and other memory conditions in a secure setting.',
		'elder-law'       => 'Guardianship, estate planning, Medicaid, and power of attorney from locally licensed attorneys.',
		'care-management' => 'A dedicated care manager coordinates every service — one family point of contact for the whole plan.',
		'hospice'         => 'Compassionate end-of-life care at home or in a dedicated facility, with full family support.',
		'grief-counselors'=> 'Professional grief and bereavement support for families and seniors navigating loss.',
	];
	foreach ( $map as $key => $blurb ) {
		if ( str_contains( $slug, $key ) ) return $blurb;
	}
	return 'Find verified providers near you.';
}

function ecm_cross_sell( string $slug ) : array {
	$cross_map = [
		'home-care'       => [ 'care-management', 'elder-law' ],
		'assisted-living' => [ 'care-management', 'elder-law' ],
		'memory-care'     => [ 'home-care', 'hospice' ],
		'elder-law'       => [ 'home-care', 'care-management' ],
		'care-management' => [ 'home-care', 'elder-law' ],
		'hospice'         => [ 'grief-counselors', 'care-management' ],
		'grief-counselors'=> [ 'hospice', 'care-management' ],
	];
	$keys = $cross_map[ $slug ] ?? [];
	$result = [];
	foreach ( $keys as $key ) {
		$term = get_term_by( 'slug', $key, 'job_listing_category' );
		$result[] = [
			'key'  => $key,
			'name' => $term ? ecm_decode_display_text( $term->name ) : ucwords( str_replace( '-', ' ', $key ) ),
			'icon' => ecm_category_icon( $key, '' ),
		];
	}
	return $result;
}

add_action( 'created_job_listing_category', 'ecm_clear_search_caches' );
add_action( 'edited_job_listing_category',  'ecm_clear_search_caches' );
add_action( 'delete_job_listing_category',  'ecm_clear_search_caches' );
