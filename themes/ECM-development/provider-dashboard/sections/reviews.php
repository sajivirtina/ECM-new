<?php
/**
 * M5 — Reviews. Real review data from WP Job Manager Reviews add-on.
 * Reviews are stored as comments on the job_listing post (comment_type = 'job_listing_review'
 * or standard comment depending on add-on version).
 */

defined( 'ABSPATH' ) || exit;

$uid     = get_current_user_id();
$ids     = pd_get_my_listing_ids( $uid );
$reviews = [];

if ( $ids ) {
	// WP Job Manager Reviews stores reviews as comments with type 'job_listing_review'.
	// Try with comment_type first; fall back to all types for older add-on versions.
	$raw = get_comments( [
		'post__in'   => $ids,
		'status'     => 'approve',
		'type'       => 'job_listing_review',
		'number'     => 50,
		'orderby'    => 'comment_date',
		'order'      => 'DESC',
	] );

	if ( empty( $raw ) ) {
		// Fallback: older Reviews add-on may use standard comment type
		$raw = get_comments( [
			'post__in'   => $ids,
			'status'     => 'approve',
			'number'     => 50,
			'orderby'    => 'comment_date',
			'order'      => 'DESC',
		] );
	}

	foreach ( $raw as $c ) {
		$rating = (int) get_comment_meta( $c->comment_ID, 'rating', true );
		if ( ! $rating ) continue; // skip comments with no rating meta (not reviews)
		$reviews[] = [
			'id'         => $c->comment_ID,
			'post_id'    => $c->comment_post_ID,
			'post_title' => get_the_title( $c->comment_post_ID ),
			'author'     => $c->comment_author,
			'date'       => $c->comment_date,
			'content'    => $c->comment_content,
			'rating'     => max( 1, min( 5, $rating ) ),
		];
	}
}

// Aggregate
$total    = count( $reviews );
$avg      = $total ? round( array_sum( array_column( $reviews, 'rating' ) ) / $total, 1 ) : 0;
$dist     = array_fill( 1, 5, 0 );
foreach ( $reviews as $r ) {
	$star = max( 1, min( 5, $r['rating'] ) );
	$dist[ $star ]++;
}
?>

<div class="dash-main__body">
<div class="pd-section-header">
	<h1 class="pd-section-title">Reviews</h1>
	<p class="pd-section-sub">Ratings and feedback left on your listings.</p>
</div>

<?php if ( ! $reviews ) : ?>
	<div class="pd-empty-state">
		<div class="pd-empty-icon">⭐</div>
		<h3>No reviews yet</h3>
		<p>Reviews from families and clients will appear here once your listings receive feedback.</p>
	</div>
<?php else : ?>

<!-- Rating summary -->
<div class="pd-reviews-summary">
	<div class="pd-rating-big">
		<span class="pd-rating-num"><?php echo esc_html( $avg ); ?></span>
		<div class="pd-stars"><?php
			for ( $i = 1; $i <= 5; $i++ ) {
				echo '<span class="pd-star' . ( $i <= round( $avg ) ? ' filled' : '' ) . '">★</span>';
			}
		?></div>
		<span class="pd-rating-count"><?php echo esc_html( $total ); ?> review<?php echo $total !== 1 ? 's' : ''; ?></span>
	</div>
	<div class="pd-rating-bars">
		<?php for ( $s = 5; $s >= 1; $s-- ) :
			$pct = $total ? round( ( $dist[$s] / $total ) * 100 ) : 0;
		?>
		<div class="pd-dist-row">
			<span class="pd-dist-label"><?php echo $s; ?>★</span>
			<div class="pd-bar-track"><div class="pd-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
			<span class="pd-dist-count"><?php echo esc_html( $dist[$s] ); ?></span>
		</div>
		<?php endfor; ?>
	</div>
</div>

<!-- Review list -->
<div class="pd-review-list">
	<?php foreach ( $reviews as $rv ) : ?>
	<div class="pd-review-card">
		<div class="pd-review-header">
			<div class="pd-review-meta">
				<span class="pd-review-author"><?php echo esc_html( $rv['author'] ); ?></span>
				<span class="pd-review-listing">on <a href="<?php echo esc_url( pd_get_listing_edit_url( $rv['post_id'] ) ); ?>" class="pd-table-link"><?php echo esc_html( $rv['post_title'] ); ?></a></span>
			</div>
			<div class="pd-review-right">
				<div class="pd-stars pd-stars--sm">
					<?php for ( $i = 1; $i <= 5; $i++ ) {
						echo '<span class="pd-star' . ( $i <= $rv['rating'] ? ' filled' : '' ) . '">★</span>';
					} ?>
				</div>
				<span class="pd-review-date"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $rv['date'] ) ) ); ?></span>
			</div>
		</div>
		<div class="pd-review-body"><?php echo wp_kses_post( $rv['content'] ); ?></div>
	</div>
	<?php endforeach; ?>
</div>

<?php endif; ?>
</div><!-- /.dash-main__body -->
