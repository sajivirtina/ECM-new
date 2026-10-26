<?php
/**
 * Provider-authored articles — REAL WordPress `post` entries linked to a
 * specific job_listing, not ACF repeater rows. This is deliberate: a real
 * post shows up in the site's normal blog too, can be commented on, has
 * its own permalink, etc. — an ACF sub-field never could.
 *
 * Linking mechanism: post meta `_ecm_listing_id` on the `post`, holding the
 * job_listing's post ID. `ecm_get_listing_articles()` queries by that meta.
 *
 * Replaces the old lp_own_articles (ACF repeater) + lp_featured_posts
 * (curated relationship field) — those are gone. Frontend display order:
 * this listing's own linked articles first, then the site's most recent
 * posts fill any remaining slots up to the tier's article limit (see
 * inc/package-capabilities.php → 'featured_posts').
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

const ECM_LISTING_ARTICLE_META_KEY = '_ecm_listing_id';

/** Real posts linked to this listing, newest first, capped at $limit. */
function ecm_get_listing_articles( int $listing_id, int $limit ) : array {
	if ( $limit <= 0 ) {
		return [];
	}
	return get_posts( [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_key'       => ECM_LISTING_ARTICLE_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'     => $listing_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] );
}

/** Recent global site posts to fill remaining article slots, excluding any already shown. */
function ecm_get_global_fill_posts( array $exclude_ids, int $limit ) : array {
	if ( $limit <= 0 ) {
		return [];
	}
	return get_posts( [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post__not_in'   => $exclude_ids,
		'meta_query'     => [ [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, infrequent query on the single-listing page.
			'key'     => ECM_LISTING_ARTICLE_META_KEY,
			'compare' => 'NOT EXISTS',
		] ],
	] );
}

/** Pick the first meaningful category for a blog card. */
function ecm_get_blog_card_primary_category( WP_Post $post ) : ?WP_Term {
	$cats = get_the_terms( $post->ID, 'category' );
	if ( ! $cats || is_wp_error( $cats ) ) {
		return null;
	}

	foreach ( $cats as $cat ) {
		if ( 'uncategorized' !== $cat->slug ) {
			return $cat;
		}
	}

	return $cats[0];
}

/** Match a real post category to the Resources listing card treatment. */
function ecm_get_blog_card_category_meta( ?WP_Term $term ) : array {
	if ( ! $term ) {
		return [ 'blog-cat--home-care', 'Elder Care', '📰', '#e6f4ea' ];
	}

	$slug = $term->slug;
	$name = strtolower( $term->name );
	$map  = [
		'home-care'       => [ 'blog-cat--home-care', '🏠', '#e6f4ea' ],
		'assisted-living' => [ 'blog-cat--assisted-living', '🏢', '#e8f0fe' ],
		'memory-care'     => [ 'blog-cat--memory-care', '🧠', '#fce8d5' ],
		'legal'           => [ 'blog-cat--legal', '⚖️', '#f3e8fd' ],
		'financial'       => [ 'blog-cat--legal', '⚖️', '#f3e8fd' ],
		'elder-law'       => [ 'blog-cat--legal', '⚖️', '#f3e8fd' ],
		'hospice'         => [ 'blog-cat--hospice', '🕊️', '#fde8ee' ],
		'palliative'      => [ 'blog-cat--hospice', '🕊️', '#fde8ee' ],
		'checklist'       => [ 'blog-cat--checklist', '✅', '#e8fdf5' ],
		'checklists'      => [ 'blog-cat--checklist', '✅', '#e8fdf5' ],
	];

	foreach ( $map as $key => $val ) {
		if ( str_contains( $slug, $key ) || str_contains( $name, $key ) ) {
			return [ $val[0], $term->name, $val[1], $val[2] ];
		}
	}

	return [ 'blog-cat--home-care', $term->name, '📰', '#e6f4ea' ];
}

/** Render the same real-post card used across the Resources/blog experience. */
function ecm_render_blog_post_card( WP_Post $post ) : void {
	$cat = ecm_get_blog_card_primary_category( $post );
	[ $cat_class, $cat_name, $cat_icon, $cat_bg ] = ecm_get_blog_card_category_meta( $cat );

	$excerpt   = $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 22, '…' );
	$words     = str_word_count( wp_strip_all_tags( $post->post_content ) );
	$read_mins = max( 1, (int) round( $words / 200 ) );
	$thumb     = get_the_post_thumbnail_url( $post->ID, 'medium_large' );
	?>
	<a class="blog-card" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
		<?php if ( $thumb ) : ?>
			<div class="blog-card-icon blog-card-icon--photo">
				<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title( $post ) ); ?>" loading="lazy">
			</div>
		<?php else : ?>
			<div class="blog-card-icon" style="background:<?php echo esc_attr( $cat_bg ); ?>"><?php echo esc_html( $cat_icon ); ?></div>
		<?php endif; ?>
		<div class="blog-card-body">
			<span class="blog-card-cat <?php echo esc_attr( $cat_class ); ?>"><?php echo esc_html( $cat_name ); ?></span>
			<div class="blog-card-title"><?php echo esc_html( get_the_title( $post ) ); ?></div>
			<p class="blog-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
		</div>
		<div class="blog-card-footer">
			<span><?php echo esc_html( get_the_date( 'M j, Y', $post ) . ' · ' . $read_mins . ' min read' ); ?></span>
			<span class="blog-card-footer-link">Read more →</span>
		</div>
	</a>
	<?php
}

/** How many articles this listing already has linked (for enforcing the tier cap on create). */
function ecm_count_listing_articles( int $listing_id ) : int {
	return count( get_posts( [
		'post_type'      => 'post',
		'post_status'    => [ 'publish', 'pending', 'draft' ],
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => ECM_LISTING_ARTICLE_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'     => $listing_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] ) );
}

/**
 * Handles the Add/Edit/Delete Article form on the dashboard's Profile
 * Details screen (provider-dashboard/sections/listings.php). Runs on
 * template_redirect — before any HTML output — since it can redirect
 * after a successful save (POST-redirect-GET, avoids resubmission).
 */
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'pd_is_listing_edit_request' ) || ! pd_is_listing_edit_request() ) {
		return;
	}
	if ( empty( $_POST['ecm_article_action'] ) || ! is_user_logged_in() ) {
		return;
	}
	if ( ! isset( $_POST['ecm_article_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ecm_article_nonce'] ) ), 'ecm_article_action' ) ) {
		wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'eldercare-matters' ) );
	}

	$listing_id = isset( $_GET['job_id'] ) ? (int) $_GET['job_id'] : 0;
	if ( ! $listing_id || (int) get_post( $listing_id )->post_author !== get_current_user_id() ) {
		wp_die( esc_html__( 'You do not have permission to edit this listing.', 'eldercare-matters' ) );
	}

	$action    = sanitize_text_field( wp_unslash( $_POST['ecm_article_action'] ) );
	$redirect  = remove_query_arg( [ 'article_saved', 'article_deleted' ], home_url( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( 'delete' === $action ) {
		$article_id = isset( $_POST['article_id'] ) ? (int) $_POST['article_id'] : 0;
		$article    = $article_id ? get_post( $article_id ) : null;
		if ( $article && (int) get_post_meta( $article_id, ECM_LISTING_ARTICLE_META_KEY, true ) === $listing_id ) {
			wp_delete_post( $article_id, true );
		}
		wp_safe_redirect( add_query_arg( 'article_deleted', '1', $redirect ) );
		exit;
	}

	if ( 'save' === $action ) {
		$article_id = isset( $_POST['article_id'] ) ? (int) $_POST['article_id'] : 0;
		$title      = isset( $_POST['article_title'] ) ? sanitize_text_field( wp_unslash( $_POST['article_title'] ) ) : '';
		$content    = isset( $_POST['article_content'] ) ? wp_kses_post( wp_unslash( $_POST['article_content'] ) ) : '';

		if ( '' === trim( $title ) ) {
			wp_safe_redirect( add_query_arg( 'article_error', 'title', $redirect ) );
			exit;
		}

		// Enforce the tier's article cap on NEW articles only (editing an existing one is always allowed).
		$limits = function_exists( 'ecm_get_listing_limits' ) ? ecm_get_listing_limits( $listing_id ) : [];
		$cap    = $limits['featured_posts'] ?? 0;
		$is_new = ! $article_id || (int) get_post_meta( $article_id, ECM_LISTING_ARTICLE_META_KEY, true ) !== $listing_id;

		if ( $is_new && ecm_count_listing_articles( $listing_id ) >= $cap ) {
			wp_safe_redirect( add_query_arg( 'article_error', 'limit', $redirect ) );
			exit;
		}

		$postarr = [
			'post_title'   => $title,
			'post_content' => $content,
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
		];

		if ( $article_id && ! $is_new ) {
			$postarr['ID'] = $article_id;
			$new_id        = wp_update_post( $postarr, true );
		} else {
			$new_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			wp_safe_redirect( add_query_arg( 'article_error', 'save', $redirect ) );
			exit;
		}

		update_post_meta( $new_id, ECM_LISTING_ARTICLE_META_KEY, $listing_id );

		// Featured image upload (optional).
		if ( ! empty( $_FILES['article_image']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$attachment_id = media_handle_upload( 'article_image', $new_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $new_id, $attachment_id );
			}
		}

		wp_safe_redirect( add_query_arg( 'article_saved', '1', $redirect ) );
		exit;
	}
} );
