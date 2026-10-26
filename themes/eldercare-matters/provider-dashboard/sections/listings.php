<?php
/**
 * M1 — My Listings. Real job_listing posts owned by the current user.
 *
 * Edit is rendered in-place here (?action=edit&job_id=X) using WP Job
 * Manager's own native edit form — ownership check (job_manager_user_can_edit_job),
 * nonce, sanitization/validation, and moderation-on-edit are all handled by
 * WPJM itself (see WP_Job_Manager_Form_Edit_Job). We only supply the chrome.
 */

defined( 'ABSPATH' ) || exit;

if ( pd_is_listing_add_request() ) {
	?>
	<div class="dash-main__body">
	<?php
	if ( function_exists( 'ecm_debug_panel' ) ) {
		// Shows diagnostics for whatever listing you most recently created
		// (last 10 min) — so after submitting, you can see directly whether
		// _package_id/_user_package_id actually got set, regardless of
		// which page the multi-step flow happens to land you on.
		$recent = get_posts( [
			'post_type'      => 'job_listing',
			'author'         => get_current_user_id(),
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'date_query'     => [ [ 'after' => '10 minutes ago' ] ],
			'post_status'    => 'any',
		] );
		$recent_id = $recent ? $recent[0]->ID : 0;
		ecm_debug_panel(
			$recent_id,
			$recent_id ? 'Add Listing — your most recent listing (#' . $recent_id . ', created in the last 10 min)' : 'Add Listing (no listing created in the last 10 min)'
		);
	}
	?>
	<?php
	// A package genuinely has been chosen once EITHER of these is present —
	// the cookie the payments plugin sets the instant a radio is submitted
	// on the "Choose a package" step, or the raw POSTed field on that exact
	// step's own submission. Before that, showing "Free Plan" / tier-based
	// field info is premature and confusing — nothing's been chosen yet.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only presence check, not reading a value for use.
	$package_chosen = ! empty( $_COOKIE['chosen_package_id'] ) || ! empty( $_POST['job_package'] );

	$add_tier         = $package_chosen && function_exists( 'ecm_resolve_current_submission_tier' ) ? ecm_resolve_current_submission_tier( 0 ) : '';
	$add_tier_labels  = [ 'free' => 'Free', 'basic' => 'Basic', 'standard' => 'Premium', 'premium' => 'Featured' ];
	$add_limits       = $add_tier && function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $add_tier ) : [];
	$tier_badge_style = $add_tier ? ecm_tier_badge_style( $add_tier ) : '';
	?>
	<div class="pd-section-header">
		<h1 class="pd-section-title">
			Add a Listing
			<?php if ( $package_chosen ) : ?>
			<span style="display:inline-block;margin-left:10px;padding:4px 12px;border-radius:999px;font-size:0.7rem;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;vertical-align:middle;<?php echo esc_attr( $tier_badge_style ); ?>">
				<?php echo esc_html( $add_tier_labels[ $add_tier ] ?? 'Free' ); ?> Plan
			</span>
			<?php endif; ?>
		</h1>
		<p class="pd-section-sub">
			<a href="<?php echo esc_url( home_url( '/provider-dashboard/listings/' ) ); ?>">← Back to My Listings</a>
			<span style="color:var(--muted, #9a9a9a);">&nbsp;·&nbsp;</span>
		</p>
	</div>
	<?php if ( $package_chosen ) : ?>
	<p class="pd-section-sub" style="margin: -8px 0 16px;">
		Available fields above (bio, gallery, FAQ, testimonials, team, credentials, coverage) are based on your current package.
		<a href="<?php echo esc_url( function_exists( 'ecm_get_upgrade_package_url' ) ? ecm_get_upgrade_package_url( 0 ) : home_url( '/plans/' ) ); ?>">Upgrade or downgrade your package →</a>
	</p>
	<?php if ( function_exists( 'ecm_print_sub_trace' ) ) { ecm_print_sub_trace(); } ?>
	<?php endif; ?>
	<div class="pd-card" style="padding:24px;">
		<?php echo $GLOBALS['job_manager']->forms->get_form( 'submit-job' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPJM's own form output, already escaped internally. ?>
	</div>
	<?php if ( $package_chosen ) : ?>
	<?php /* TEMPORARILY DISABLED for now, on all plans — re-enable by uncommenting.
	<div class="pd-card article-section" style="padding:24px; margin-top:20px;">
		<h2 class="pd-card-title" style="margin-bottom:4px;">Your Articles</h2>
		<p class="pd-section-sub">
			<?php if ( ( $add_limits['featured_posts'] ?? 0 ) > 0 ) : ?>
				Real blog posts on ElderCareMatters, linked to this listing. Available once your listing is created — manage them from the Edit Listing screen.
			<?php else : ?>
				Not available on your current package. <a href="<?php echo esc_url( pd_get_public_add_listing_url() ); ?>">Upgrade your package →</a>
			<?php endif; ?>
		</p>
	</div>
	*/ ?>
	<?php endif; ?>
	</div>
	<?php
	return;
}

if ( pd_is_listing_edit_request() ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ownership re-checked below; the form itself is nonce-protected on submit.
	$edit_job_id = (int) $_GET['job_id'];

	if ( ! job_manager_user_can_edit_job( $edit_job_id ) ) {
		?>
		<div class="dash-main__body">
			<div class="pd-empty-state">
				<div class="pd-empty-icon">🚫</div>
				<h3>You can't edit this listing</h3>
				<p>Either it isn't yours, or it's no longer editable.</p>
				<a href="<?php echo esc_url( home_url( '/provider-dashboard/listings/' ) ); ?>" class="pd-btn pd-btn--primary">← Back to My Listings</a>
			</div>
		</div>
		<?php
		return;
	}
	?>
	<?php
	$tier             = ecm_get_listing_tier( $edit_job_id );
	$tier_labels      = [ 'free' => 'Free', 'basic' => 'Basic', 'standard' => 'Premium', 'premium' => 'Featured' ];
	$limits           = ecm_get_listing_limits( $edit_job_id );
	$tier_badge_style = ecm_tier_badge_style( $tier );
	$is_live          = 'publish' === get_post_status( $edit_job_id );
	$is_draft         = 'draft' === get_post_status( $edit_job_id );
	$view_listing_url = get_permalink( $edit_job_id );
	$preview_url      = get_preview_post_link( $edit_job_id ); // Drafts have no public permalink yet — this is WP's own signed preview link, works while logged in as the owner.
	?>
	<div class="dash-main__body">
	<div class="pd-section-header">
		<h1 class="pd-section-title">
			Edit Listing
			<span style="display:inline-block;margin-left:10px;padding:4px 12px;border-radius:999px;font-size:0.7rem;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;vertical-align:middle;<?php echo esc_attr( $tier_badge_style ); ?>">
				<?php echo esc_html( $tier_labels[ $tier ] ?? 'Free' ); ?> Plan
			</span>
		</h1>
		<p class="pd-section-sub">
			<a href="<?php echo esc_url( home_url( '/provider-dashboard/listings/' ) ); ?>">← Back to My Listings</a>
			<?php if ( $is_live ) : ?>
				<span style="color:var(--muted, #9a9a9a);">&nbsp;·&nbsp;</span>
				<a href="<?php echo esc_url( $view_listing_url ); ?>" target="_blank" rel="noopener">View Listing →</a>
			<?php elseif ( $is_draft ) : ?>
				<span style="color:var(--muted, #9a9a9a);">&nbsp;·&nbsp;</span>
				<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">Preview Draft →</a>
			<?php endif; ?>
		</p>
	</div>
	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag, not a state change.
	if ( isset( $_GET['welcome'] ) ) : ?>
		<div class="pd-card" style="padding:16px 20px; margin-bottom:16px; background:var(--sage-pale); border:1px solid var(--sage);">
			🎉 Your listing was created! Add your photos, bio, and other details below to complete your profile.
			<?php if ( $is_live ) : ?>
				<a href="<?php echo esc_url( $view_listing_url ); ?>" target="_blank" rel="noopener" style="margin-left:8px; font-weight:600;">View Listing →</a>
			<?php endif; ?>
		</div>
	<?php endif;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag, not a state change.
	if ( isset( $_GET['draft_saved'] ) ) : ?>
		<div class="pd-card" style="padding:16px 20px; margin-bottom:16px; background:var(--sage-pale); border:1px solid var(--sage);">
			📝 Draft saved. Keep filling in your details, then click Save changes when you're ready. Your listing won't go live until an admin approves it.
			<?php if ( $is_draft ) : ?>
				<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener" style="margin-left:8px; font-weight:600;">Preview Draft →</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="pd-card" style="padding:24px;">
		<?php echo $GLOBALS['job_manager']->forms->get_form( 'edit-job' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPJM's own form output, already escaped internally. ?>
	</div>

	<?php
	// Debug panel still useful here — shows tier/limits resolution for the
	// native WPJM fields now, not just the old ACF ones.
	if ( function_exists( 'ecm_debug_panel' ) ) { ecm_debug_panel( $edit_job_id, 'Edit Listing #' . $edit_job_id ); }
	?>
	<p class="pd-section-sub" style="margin: 4px 0 20px;">
		Available fields above (bio, gallery, FAQ, testimonials, team, credentials, coverage) are based on your current package.
		<a href="<?php echo esc_url( function_exists( 'ecm_get_upgrade_package_url' ) ? ecm_get_upgrade_package_url( $edit_job_id ) : pd_get_public_add_listing_url() ); ?>">Upgrade or downgrade your package →</a>
	</p>
	<?php if ( function_exists( 'ecm_print_sub_trace' ) ) { ecm_print_sub_trace(); } ?>

	<?php
	// ── Articles (real linked posts — see inc/listing-articles.php) ──────────
	$article_cap   = $limits['featured_posts'] ?? 0;
	$my_articles   = $article_cap > 0 ? ecm_get_listing_articles( $edit_job_id, $article_cap ) : [];
	$edit_article  = null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: choosing which article to prefill for editing.
	if ( ! empty( $_GET['edit_article'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$candidate = get_post( (int) $_GET['edit_article'] );
		if ( $candidate && (int) get_post_meta( $candidate->ID, ECM_LISTING_ARTICLE_META_KEY, true ) === $edit_job_id ) {
			$edit_article = $candidate;
		}
	}
	?>
	<?php if ( false ) : // "Your Articles" — TEMPORARILY DISABLED for now, on all plans. Change to `true` (or remove this wrapper) to re-enable. ?>
	<div class="pd-card article-section" id="write-article" style="padding:24px; margin-top:20px;">
		<h2 class="pd-card-title" style="margin-bottom:4px;">Your Articles</h2>
		<p class="pd-section-sub" style="margin-bottom:20px;">
			<?php if ( 0 === $article_cap ) : ?>
				Not available on your current package. <a href="<?php echo esc_url( pd_get_public_add_listing_url() ); ?>">Upgrade your package →</a>
			<?php else : ?>
				Real blog posts on ElderCareMatters, linked to this listing. They show first on your listing page, up to <?php echo esc_html( $article_cap ); ?> at a time.
			<?php endif; ?>
		</p>

		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flags, not state changes.
		if ( isset( $_GET['article_saved'] ) ) : ?>
			<div class="pd-card" style="padding:12px 16px; margin-bottom:16px; background:var(--sage-pale); border:1px solid var(--sage);">✓ Article saved.</div>
		<?php endif;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['article_deleted'] ) ) : ?>
			<div class="pd-card" style="padding:12px 16px; margin-bottom:16px; background:var(--sage-pale); border:1px solid var(--sage);">✓ Article deleted.</div>
		<?php endif;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['article_error'] ) ) :
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$err = sanitize_text_field( wp_unslash( $_GET['article_error'] ) );
			$err_msg = [
				'title' => 'Please enter a title for your article.',
				'limit' => 'You\'ve reached your package\'s article limit.',
				'save'  => 'Something went wrong saving your article — please try again.',
			][ $err ] ?? 'Something went wrong.';
		?>
			<div class="pd-card" style="padding:12px 16px; margin-bottom:16px; background:#fdecea; border:1px solid #e6a19a; color:#8a2c22;"><?php echo esc_html( $err_msg ); ?></div>
		<?php endif; ?>

		<?php if ( $my_articles ) : ?>
		<div style="margin-bottom:24px;">
			<?php foreach ( $my_articles as $article ) : ?>
			<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border);">
				<div>
					<strong><?php echo esc_html( $article->post_title ); ?></strong>
					<a href="<?php echo esc_url( get_permalink( $article ) ); ?>" target="_blank" rel="noopener" style="margin-left:8px;font-size:0.85rem;color:var(--muted);">View →</a>
				</div>
				<div style="display:flex;gap:10px;">
					<a href="<?php echo esc_url( add_query_arg( 'edit_article', $article->ID ) ); ?>" class="pd-btn" style="padding:6px 14px;font-size:0.85rem;background:var(--warm-white,#f3f1ea);color:var(--charcoal,#2c2c2c);border:1px solid var(--border,#ddd);">Edit</a>
					<form method="post" style="display:inline;" onsubmit="return confirm('Delete this article? This cannot be undone.');">
						<?php wp_nonce_field( 'ecm_article_action', 'ecm_article_nonce' ); ?>
						<input type="hidden" name="ecm_article_action" value="delete">
						<input type="hidden" name="article_id" value="<?php echo esc_attr( $article->ID ); ?>">
						<button type="submit" class="pd-btn" style="padding:6px 14px;font-size:0.85rem;background:#fdecea;color:#b3413a;border:1px solid #e6a19a;">Delete</button>
					</form>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php if ( $article_cap > 0 && ( $edit_article || count( $my_articles ) < $article_cap ) ) : ?>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'ecm_article_action', 'ecm_article_nonce' ); ?>
			<input type="hidden" name="ecm_article_action" value="save">
			<?php if ( $edit_article ) : ?>
				<input type="hidden" name="article_id" value="<?php echo esc_attr( $edit_article->ID ); ?>">
			<?php endif; ?>
			<h3 style="font-size:1rem;margin-bottom:12px;"><?php echo $edit_article ? 'Edit Article' : 'Write a New Article'; ?></h3>
			<label style="display:block;font-weight:600;font-size:0.9rem;margin-bottom:4px;">Title</label>
			<input type="text" name="article_title" value="<?php echo esc_attr( $edit_article->post_title ?? '' ); ?>"
			       style="width:100%;padding:11px 14px;margin-bottom:14px;border:1px solid var(--border);border-radius:10px;">
			<label style="display:block;font-weight:600;font-size:0.9rem;margin-bottom:4px;">Content</label>
			<div style="margin-bottom:14px;">
				<?php
				wp_editor(
					$edit_article->post_content ?? '',
					'ecm_article_content',
					[
						'textarea_name' => 'article_content',
						'textarea_rows' => 12,
						'media_buttons' => false, // Keep it simple — featured image is the separate upload below.
						'teeny'         => true,  // Compact toolbar: bold/italic/lists/link — enough for a short article.
						'quicktags'     => true,
					]
				);
				?>
			</div>
			<label style="display:block;font-weight:600;font-size:0.9rem;margin-bottom:4px;">Featured Image (optional)</label>
			<input type="file" name="article_image" accept="image/*" style="margin-bottom:16px;">
			<div>
				<button type="submit" class="pd-btn pd-btn--primary"><?php echo $edit_article ? 'Save Changes' : 'Publish Article'; ?></button>
				<?php if ( $edit_article ) : ?>
					<a href="<?php echo esc_url( remove_query_arg( 'edit_article' ) ); ?>" class="pd-btn" style="background:var(--warm-white,#f3f1ea);color:var(--charcoal,#2c2c2c);border:1px solid var(--border,#ddd);">Cancel</a>
				<?php endif; ?>
			</div>
		</form>
		<?php elseif ( $article_cap > 0 ) : ?>
			<p style="color:var(--muted);">You've used all <?php echo esc_html( $article_cap ); ?> article slots on your package. Delete one above to add a new one.</p>
		<?php endif; ?>
	</div>
	<?php endif; // end "Your Articles" disabled-for-now wrapper ?>
	</div>
	<?php
	return;
}

$uid  = get_current_user_id();
$ids  = pd_get_my_listing_ids( $uid );

// Status label map
$status_labels = [
    'publish'         => [ 'Active',          'pd-badge--green'  ],
    'expired'         => [ 'Expired',         'pd-badge--red'    ],
    'pending'         => [ 'Pending Review',  'pd-badge--amber'  ],
    'pending_payment' => [ 'Pending Payment', 'pd-badge--amber'  ],
    'draft'           => [ 'Draft',           'pd-badge--gray'   ],
];

// Pagination
$per_page    = 10;
$total       = count( $ids );
$total_pages = max( 1, (int) ceil( $total / $per_page ) );
$cur_page    = max( 1, min( $total_pages, (int) ( $_GET['lpg'] ?? 1 ) ) );
$offset      = ( $cur_page - 1 ) * $per_page;
$page_ids    = array_slice( $ids, $offset, $per_page );
?>

<div class="dash-main__body">
<div class="pd-section-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
	<div>
		<h1 class="pd-section-title">My Listings</h1>
		<p class="pd-section-sub"><?php echo esc_html( $total ); ?> listing<?php echo $total !== 1 ? 's' : ''; ?> found</p>
	</div>
	<a href="<?php echo esc_url( pd_get_add_listing_url() ); ?>" class="pd-btn pd-btn--primary" style="flex-shrink:0;">+ Add new listing</a>
</div>

<?php if ( ! $ids ) : ?>
	<div class="pd-empty-state">
		<div class="pd-empty-icon">🏢</div>
		<h3>No listings yet</h3>
		<p>You haven't created any provider listings. Get started by adding your first listing.</p>
		<a href="<?php echo esc_url( pd_get_add_listing_url() ); ?>" class="pd-btn pd-btn--primary">Add your listing →</a>
	</div>
<?php else : ?>

<div class="pd-listings-table-wrap">
	<table class="pd-listings-table">
		<thead>
			<tr>
				<th>Listing</th>
				<th>Location</th>
				<th>Category</th>
				<th>Views</th>
				<th>Status</th>
				<th>Expires</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $page_ids as $pid ) :
			$post     = get_post( $pid );
			$status   = get_post_status( $pid );
			$s_label  = $status_labels[ $status ] ?? [ ucfirst( $status ), 'pd-badge--gray' ];

			// Logo
			$logo_url = '';
			if ( has_post_thumbnail( $pid ) ) {
				$logo_url = get_the_post_thumbnail_url( $pid, [ 48, 48 ] );
			} elseif ( $av = get_post_meta( $pid, '_company_avatar', true ) ) {
				$logo_url = $av;
			}
			$initials = strtoupper( substr( $post->post_title, 0, 1 ) );

			// Location
			$city     = get_post_meta( $pid, 'geolocation_city', true );
			$state    = get_post_meta( $pid, 'geolocation_state_short', true );
			$location = $city ? trim( $city . ( $state ? ', ' . $state : '' ) ) : get_post_meta( $pid, '_job_location', true );

			// Category
			$cats     = get_the_terms( $pid, 'job_listing_category' );
			$cat_name = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '—';

			// Views (Stats add-on)
			$views    = (int) get_post_meta( $pid, '_wpjms_visits_total', true );
			if ( ! $views ) {
				$views = (int) get_post_meta( $pid, '_count-views_all', true );
			}

			// Expiry
			$expires  = get_post_meta( $pid, '_job_expires', true );
			$exp_str  = $expires ? date_i18n( 'M j, Y', strtotime( $expires ) ) : '—';
			$exp_cls  = ( $expires && strtotime( $expires ) < time() ) ? ' pd-expired-date' : '';

			// Edit URL (JM native)
			$edit_url = pd_get_listing_edit_url( $pid );
			$view_url = get_permalink( $pid );
		?>
			<tr>
				<td class="pd-listing-name-cell">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="pd-listing-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='';">
						<span class="pd-listing-initials" style="display:none"><?php echo esc_html( $initials ); ?></span>
					<?php else : ?>
						<span class="pd-listing-initials"><?php echo esc_html( $initials ); ?></span>
					<?php endif; ?>
					<span class="pd-listing-title"><?php echo esc_html( $post->post_title ); ?></span>
				</td>
				<td><?php echo esc_html( $location ?: '—' ); ?></td>
				<td><?php echo esc_html( $cat_name ); ?></td>
				<td><?php echo esc_html( number_format( $views ) ); ?></td>
				<td><span class="pd-badge <?php echo esc_attr( $s_label[1] ); ?>"><?php echo esc_html( $s_label[0] ); ?></span></td>
				<td class="<?php echo esc_attr( $exp_cls ); ?>"><?php echo esc_html( $exp_str ); ?></td>
				<td class="pd-listing-actions">
					<a href="<?php echo esc_url( $edit_url ); ?>" class="pd-action-link" title="Edit">✏️ Edit</a>
					<?php if ( $view_url && 'publish' === $status ) : ?>
					<a href="<?php echo esc_url( $view_url ); ?>" class="pd-action-link" target="_blank" title="View">👁 View</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php if ( $total_pages > 1 ) : ?>
<div class="pd-pagination">
	<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'lpg', $p ) ); ?>"
		   class="pd-page-link<?php echo $p === $cur_page ? ' active' : ''; ?>">
			<?php echo esc_html( $p ); ?>
		</a>
	<?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>
</div><!-- /.dash-main__body -->