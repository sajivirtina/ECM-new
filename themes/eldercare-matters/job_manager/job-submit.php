<?php
/**
 * Job submission form — ECM theme override.
 *
 * Button visibility:
 *   - Add: the normal submit button only shows when new submissions
 *     DON'T require approval (job_manager_submission_requires_approval
 *     option off) — otherwise only "Save Draft," since the listing needs
 *     admin review regardless of which button creates it.
 *   - Edit: the normal submit button always shows. No extra status logic
 *     needed here — confirmed from WPJM core's own save_job() (see
 *     class-wp-job-manager-form-submit-job.php): `if ($status) { ... }`
 *     means an empty $status (the normal case unless "published edits
 *     require moderation" is on) leaves post_status untouched entirely,
 *     so a published listing naturally stays published on save, and an
 *     unapproved one naturally stays unapproved — exactly the behavior
 *     wanted, already built into core.
 *
 * Originally this whole primary button was missing (confirmed by diffing
 * against WPJM core's own templates/job-submit.php) — meaning Edit had
 * NO buttons at all, and Add had only "Save Draft," unconditionally.
 *
 * Overrides: wp-job-manager/templates/job-submit.php
 *
 * @package ElderCareMatters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $job_manager;
$captcha_version = WP_Job_Manager\WP_Job_Manager_Recaptcha::instance()->get_recaptcha_version();
?>
<form action="<?php echo esc_url( $action ); ?>" method="post" id="submit-job-form" class="job-manager-form" enctype="multipart/form-data">

	<?php
	if ( isset( $resume_edit ) && $resume_edit ) {
		printf(
			'<p><strong>' . esc_html__( 'You are editing an existing listing. %s', 'wp-job-manager' ) . '</strong></p>',
			'<a href="?job_manager_form=submit-job&new=1&key=' . esc_attr( $resume_edit ) . '">' . esc_html__( 'Create A New Listing', 'wp-job-manager' ) . '</a>'
		);
	}
	?>

	<?php do_action( 'submit_job_form_start' ); ?>

	<?php if ( apply_filters( 'submit_job_form_show_signin', true ) ) : ?>
		<?php get_job_manager_template( 'account-signin.php' ); ?>
	<?php endif; ?>

	<?php if ( job_manager_user_can_post_job() || job_manager_user_can_edit_job( $job_id ) ) : ?>

		<?php do_action( 'submit_job_form_job_fields_start' ); ?>

		<?php foreach ( $job_fields as $key => $field ) : ?>
			<fieldset class="fieldset-<?php echo esc_attr( $key ); ?> fieldset-type-<?php echo esc_attr( $field['type'] ); ?>">
				<label for="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $field['label'] ) . wp_kses_post( apply_filters( 'submit_job_form_required_label', $field['required'] ? '' : ' <small>' . __( '(optional)', 'wp-job-manager' ) . '</small>', $field ) ); ?></label>
				<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
					<?php get_job_manager_template( 'form-fields/' . $field['type'] . '-field.php', [ 'key' => $key, 'field' => $field ] ); ?>
				</div>
			</fieldset>
		<?php endforeach; ?>

		<?php do_action( 'submit_job_form_job_fields_end' ); ?>

		<?php if ( $company_fields ) : ?>
			<h2><?php esc_html_e( 'Company Details', 'wp-job-manager' ); ?></h2>

			<?php do_action( 'submit_job_form_company_fields_start' ); ?>

			<?php foreach ( $company_fields as $key => $field ) : ?>
				<fieldset class="fieldset-<?php echo esc_attr( $key ); ?> fieldset-type-<?php echo esc_attr( $field['type'] ); ?>">
					<label for="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $field['label'] ) . wp_kses_post( apply_filters( 'submit_job_form_required_label', $field['required'] ? '' : ' <small>' . __( '(optional)', 'wp-job-manager' ) . '</small>', $field ) ); ?></label>
					<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
						<?php get_job_manager_template( 'form-fields/' . $field['type'] . '-field.php', [ 'key' => $key, 'field' => $field ] ); ?>
					</div>
				</fieldset>
			<?php endforeach; ?>

			<?php do_action( 'submit_job_form_company_fields_end' ); ?>
		<?php endif; ?>

		<?php do_action( 'submit_job_form_end' ); ?>

		<p>
			<input type="hidden" name="job_manager_form" value="<?php echo esc_attr( $form ); ?>" />
			<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>" />
			<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>" />
			<?php
			// Add: show the normal submit button only when new submissions
			// DON'T require approval (auto-publish allowed) — otherwise
			// "Save Draft" is the only action, since the listing will need
			// admin review either way. Edit: always the normal button —
			// WPJM's own save_job() (see docblock above) already leaves an
			// unchanged post_status alone when this option is off, so a
			// published listing naturally stays published on save, and an
			// unapproved one naturally stays unapproved.
			$new_submissions_need_approval = (bool) get_option( 'job_manager_submission_requires_approval' );
			$show_submit_button = ( 'edit-job' === $form ) || ! $new_submissions_need_approval;
			?>
			<?php if ( $show_submit_button ) : ?>
				<input type="submit" name="submit_job" class="button"
					<?php if ( 'v3' === $captcha_version ) echo 'onclick="jm_job_submit_click(event)"'; ?> value="<?php echo esc_attr( $submit_button_text ); ?>" />
			<?php endif; ?>
			<?php
			if ( isset( $can_continue_later ) && $can_continue_later ) {
				echo '<input type="submit" name="save_draft" class="button secondary save_draft" value="' . esc_attr__( 'Save Draft', 'wp-job-manager' ) . '" formnovalidate />';
			}
			?>
			<span class="spinner" style="background-image: url(<?php echo esc_url( includes_url( 'images/spinner.gif' ) ); ?>);"></span>
		</p>

	<?php else : ?>

		<?php do_action( 'submit_job_form_disabled' ); ?>

	<?php endif; ?>
</form>