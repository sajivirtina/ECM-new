<?php
/**
 * My Content — provider articles. "Profile Views" is real, pulled from the
 * provider's WP Job Manager listing views (same figure shown on Home/Stats).
 * "Leads from Content" has no data source yet (no leads model exists — see
 * leads.php) and stays sample-badged. "Articles Published" is the provider's
 * real authored post count.
 */

defined( 'ABSPATH' ) || exit;

$uid  = get_current_user_id();
$kpis = pd_get_kpis( $uid );

// The real "New Article" flow lives inside a listing's Edit screen (articles
// are linked to a specific listing — see inc/listing-articles.php), not
// wp-admin: a provider account lacks edit_posts capability there, so the old
// admin_url('post-new.php') link just bounced them to wp-login.php.
$primary_listing_id = pd_get_primary_listing_id( $uid );
$new_article_url     = $primary_listing_id ? pd_get_listing_edit_url( $primary_listing_id ) . '#write-article' : pd_get_add_listing_url();

$my_posts = get_posts( [
	'post_type'      => 'post',
	'author'         => $uid,
	'post_status'    => [ 'publish', 'draft' ],
	'posts_per_page' => 20,
	'no_found_rows'  => true,
] );
$published = 0;
foreach ( $my_posts as $p ) {
	if ( 'publish' === $p->post_status ) {
		$published++;
	}
}
?>
<div class="dash-main__header">
	<div>
		<div class="dash-main__title">My Content</div>
		<div class="dash-main__sub">Articles you publish appear on ElderCareMatters and boost your visibility</div>
	</div>
</div>

<div class="dash-main__body">

	<div class="pd-notice pd-notice--info">
		"Leads from Content" (per-article attribution) is a later phase. "Articles Published", "Profile Views", and "Drafts" below are real.
	</div>

	<div class="content-stats">
		<div class="cstat">
			<div class="cstat__label">Articles Published</div>
			<div class="cstat__value"><?php echo esc_html( number_format_i18n( $published ) ); ?></div>
			<div class="cstat__delta">Your posts</div>
		</div>
		<div class="cstat">
			<div class="cstat__label">Profile Views</div>
			<div class="cstat__value"><?php echo esc_html( number_format_i18n( $kpis['views'] ) ); ?></div>
			<div class="cstat__delta">Across your listings</div>
		</div>
		<div class="cstat">
			<div class="cstat__label">Leads from Content <span class="pd-sample-badge">Sample</span></div>
			<div class="cstat__value">—</div>
			<div class="cstat__delta">Coming soon</div>
		</div>
		<div class="cstat">
			<div class="cstat__label">Drafts</div>
			<div class="cstat__value"><?php echo esc_html( number_format_i18n( max( 0, count( $my_posts ) - $published ) ) ); ?></div>
			<div class="cstat__delta">In progress</div>
		</div>
	</div>

	<div class="articles-card">
		<div class="articles-card__header">
			<span class="articles-card__title">Your Articles</span>
			<a class="btn--primary btn--sm" href="<?php echo esc_url( $new_article_url ); ?>">+ New Article</a>
		</div>

		<?php if ( $my_posts ) : foreach ( $my_posts as $p ) : ?>
			<div class="article-row">
				<div class="article-row__icon"><?php echo 'publish' === $p->post_status ? '📄' : '✏️'; ?></div>
				<div class="article-row__body">
					<div class="article-row__title"><?php echo esc_html( get_the_title( $p ) ); ?></div>
					<div class="article-row__meta">
						<?php echo 'publish' === $p->post_status
							? esc_html( 'Published ' . get_the_date( '', $p ) )
							: esc_html( 'Draft · last edited ' . get_the_modified_date( '', $p ) ); ?>
					</div>
				</div>
				<div class="article-row__status">
					<span class="status--<?php echo 'publish' === $p->post_status ? 'published' : 'draft'; ?>"><?php echo 'publish' === $p->post_status ? 'Published' : 'Draft'; ?></span>
					<a class="btn--secondary btn--sm" href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>">Edit</a>
				</div>
			</div>
		<?php endforeach; else : ?>
			<div class="article-row">
				<div class="article-row__body">
					<div class="article-row__title">No articles yet</div>
					<div class="article-row__meta">Publish helpful guides to grow your visibility with families.</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
