<?php
/**
 * Review submission support for job_listing single pages (WP Job Manager
 * Reviews add-on integration).
 *
 * WPJM Reviews stores each review as a plain top-level WP comment on the
 * job_listing post (no custom comment_type) plus comment meta
 * ('review_stars', 'review_average') — it hooks its star-rating fields onto
 * core's `comment_form_top` action, so calling WordPress's own
 * comment_form() on a job_listing singular is the supported way to get a
 * working submission form; ecm_render_review_form() below just themes that
 * call to match the site's form styling (see .form-field in
 * assets/css/homepagev3.css, loaded on every page).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register core "comments" support for job_listing (WPJM's own CPT
 * registration doesn't include it) so reviews behave like a first-class
 * comment type for admin screens / REST, matching how WPJM Reviews expects
 * to hook in.
 */
add_action( 'init', function () {
	add_post_type_support( 'job_listing', 'comments' );
}, 20 );

/**
 * Reviews should always be submittable on a listing, regardless of the
 * individual post's own comment_status. Most listings in this install were
 * bulk-imported with comment_status=closed (a leftover of the import, not a
 * deliberate "no reviews" choice), which would otherwise silently reject
 * every review submitted through the form below at the wp-comments-post.php
 * handler.
 */
add_filter( 'comments_open', function ( $open, $post_id ) {
	if ( 'job_listing' === get_post_type( $post_id ) ) {
		return true;
	}
	return $open;
}, 20, 2 );

/**
 * WP Job Manager Reviews has its own "allow guest reviews" setting
 * (wpjmr_allow_guests, on by default) — but the site-wide Discussion setting
 * "Users must be registered and logged in to comment" (comment_registration)
 * overrides it at the core wp-comments-post.php handler, blocking guest
 * review submission outright before WPJMR's own logic ever runs. Bypass the
 * global setting for job_listing submissions only, deferring to WPJMR's own
 * toggle; blog/page comments elsewhere on the site keep the site-wide rule.
 */
add_filter( 'pre_option_comment_registration', function ( $pre ) {
	if ( ! get_option( 'wpjmr_allow_guests', true ) ) {
		return $pre;
	}

	// Two legs need covering: the form being rendered (a GET request, on the
	// job_listing singular template — no $_POST yet) and the actual
	// wp-comments-post.php submission (no query context, but comment_post_ID
	// is posted).
	if ( ! empty( $_POST['comment_post_ID'] ) ) {
		$is_review = 'job_listing' === get_post_type( (int) $_POST['comment_post_ID'] );
	} else {
		$is_review = is_singular( 'job_listing' );
	}

	return $is_review ? '0' : $pre;
}, 20 );

/**
 * Mark each star-rating category label as required (Speed, Quality, Price,
 * Customer Service by default — see wpjmr_categories option). WPJMR's own
 * hidden rating inputs carry a `required` attribute, but browsers ignore
 * `required` on type="hidden" fields, so nothing stops an incomplete
 * submission from reaching the server — where it hits a raw wp_die() error
 * page ("Please select a rating for all categories"). The asterisk here plus
 * the JS validation below (ecm_render_review_form) are what actually catch
 * this before submission.
 */
add_filter( 'wpjmr_category_label', function ( $label ) {
	return $label . ' <span class="required-mark">*</span>';
} );

/**
 * Name/email fields for the review form, styled with the site's shared
 * .form-field markup (assets/css/homepagev3.css) instead of core's default
 * comment-form-* markup. No "Website" field — not useful on a review.
 *
 * @return string[] Field name => field HTML, keyed the way comment_form()
 *                   expects ('author', 'email').
 */
function ecm_review_form_fields() {
	$commenter = wp_get_current_commenter();
	$req       = (bool) get_option( 'require_name_email' );
	$required  = $req ? ' required' : '';
	$mark      = $req ? ' <span class="required-mark">*</span>' : '';

	return [
		'author' => '<div class="form-field form-field--name"><label for="author">' . esc_html__( 'Your name', 'eldercare-matters' ) . $mark . '</label>'
			. '<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" maxlength="245" autocomplete="name"' . $required . '></div>',
		'email'  => '<div class="form-field form-field--email"><label for="email">' . esc_html__( 'Email', 'eldercare-matters' ) . $mark . '</label>'
			. '<input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" maxlength="100" autocomplete="email"' . $required . '>'
			. '<p class="review-form-note">' . esc_html__( "We'll never publish your email address.", 'eldercare-matters' ) . '</p></div>',
	];
}

/**
 * Render the "write a review" form for a job_listing post. Thin themed
 * wrapper around core's comment_form() — WPJMR's star-rating widget, owner/
 * guest/duplicate restriction notices, and the actual save-as-comment-meta
 * logic all come from the plugin hooking into comment_form()'s own actions
 * and filters (comment_form_top, comment_form_fields, comment_post, etc.),
 * not from anything here.
 *
 * @param int $post_id Listing ID.
 */
function ecm_render_review_form( $post_id ) {
	$post_id = (int) $post_id;
	if ( 'job_listing' !== get_post_type( $post_id ) ) {
		return;
	}

	$comment_required = get_option( 'wpjmr_allow_blank_comment' ) ? '' : ' required';

	comment_form(
		[
			'title_reply'          => __( 'Share your experience', 'eldercare-matters' ),
			'title_reply_before'   => '<h3 id="reply-title" class="review-form-title">',
			'title_reply_after'    => '</h3>',
			'comment_notes_before' => '',
			'comment_notes_after'  => '',
			'logged_in_as'         => '',
			'fields'               => ecm_review_form_fields(),
			'comment_field'        => '<div class="form-field form-field--review"><label for="comment">' . esc_html__( 'Your review', 'eldercare-matters' ) . '</label>'
				. '<textarea id="comment" name="comment" rows="5" maxlength="65525"' . $comment_required
				. ' placeholder="' . esc_attr__( "What was your experience like? What should other families know?", 'eldercare-matters' ) . '"></textarea></div>',
			'class_form'           => 'review-form',
			'class_submit'         => 'btn-primary-lg',
			'label_submit'         => __( 'Submit Review', 'eldercare-matters' ),
			'submit_field'         => '<p class="review-form-submit-row">%1$s %2$s</p>',
		],
		$post_id
	);

	ecm_review_form_validation_script();
}

/**
 * Block submission client-side when any star-rating category is still
 * unrated (see ecm_review_form_fields() docblock for why this can't just be
 * an HTML5 `required` attribute) and show an inline message instead of
 * letting the request reach WPJMR's wp_die() on the server.
 */
function ecm_review_form_validation_script() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	?>
	<script>
	( function () {
		var form = document.getElementById( 'commentform' );
		if ( ! form ) { return; }

		var ratingsBox = document.getElementById( 'wpjmr-submit-ratings' );
		var errorMsg   = <?php echo wp_json_encode( __( 'Please rate every category above before submitting your review.', 'eldercare-matters' ) ); ?>;

		function getError() {
			var el = form.querySelector( '.review-form-error' );
			if ( ! el ) {
				el = document.createElement( 'p' );
				el.className = 'review-form-error';
				if ( ratingsBox ) {
					ratingsBox.insertAdjacentElement( 'afterend', el );
				} else {
					form.insertBefore( el, form.firstChild );
				}
			}
			return el;
		}

		function unratedInputs() {
			var inputs = form.querySelectorAll( '.wpjmr-rating-input' );
			return Array.prototype.filter.call( inputs, function ( input ) {
				return ! input.value || '0' === input.value;
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			var missing = unratedInputs();
			if ( ! missing.length ) { return; }

			e.preventDefault();
			getError().innerHTML = '<strong>Rating required</strong>' + errorMsg;

			var row = missing[ 0 ].closest( '.rating-row' );
			( row || ratingsBox || form ).scrollIntoView( { behavior: 'smooth', block: 'center' } );
		} );

		if ( ratingsBox ) {
			ratingsBox.addEventListener( 'click', function () {
				if ( ! unratedInputs().length ) {
					var el = form.querySelector( '.review-form-error' );
					if ( el ) { el.remove(); }
				}
			} );
		}
	} )();
	</script>
	<?php
}
