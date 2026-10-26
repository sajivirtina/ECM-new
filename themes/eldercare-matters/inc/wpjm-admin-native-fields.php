<?php
/**
 * Makes the package-tier-gated native fields visible and editable on the
 * WP ADMIN job_listing edit screen too — not just the front-end
 * dashboard. This is a genuinely separate WPJM system from the front-end:
 *
 *   Front-end (submit/edit form): submit_job_form_fields filter,
 *     bare field keys ('gallery_images'), underscore added automatically
 *     on save (confirmed in class-wp-job-manager-form-submit-job.php).
 *
 *   Admin (wp-admin post edit screen): job_manager_job_listing_data_fields
 *     filter, keys are ALREADY underscore-prefixed ('_gallery_images') —
 *     confirmed in class-wp-job-manager-writepanels.php's job_listing_fields(),
 *     where core's own fields are registered as $fields['_job_expires'] etc.
 *     Field rendering for a custom type dispatches via an ACTION
 *     (do_action("job_manager_input_{$type}", $key, $field)) rather than a
 *     theme-overridable template file — this is the same mechanism Field
 *     Editor itself uses for its own custom admin field types.
 *     Saving is generic here too — any field with isset($_POST[$key])
 *     gets update_post_meta($post_id, $key, ...) automatically (confirmed
 *     in save_job_listing_data()), so — same as the front-end — no custom
 *     save code is needed for the repeater fields once they're registered.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register gallery/description tier info + the 5 repeater fields for the
 * admin metabox. Priority 200 mirrors the front-end file's convention
 * (Field Editor's own admin_fields() registration runs at 100).
 */
add_filter( 'job_manager_job_listing_data_fields', function ( $fields ) {
	global $post_id;
	$tier   = function_exists( 'ecm_resolve_current_submission_tier' ) ? ecm_resolve_current_submission_tier( (int) $post_id ) : 'free';
	$limits = function_exists( 'ecm_tier_limits' ) ? ecm_tier_limits( $tier ) : [];

	// Gallery + description: just layer a helpful note — admins are
	// trusted to override these when they have a good reason to, so this
	// is informational rather than a hard admin-side block.
	if ( isset( $fields['_gallery_images'] ) ) {
		$gallery_limit = $limits['gallery'] ?? 0;
		$fields['_gallery_images']['description'] = $gallery_limit > 0
			? sprintf( 'Provider\'s package (%s) allows up to %d photos.', ucfirst( $tier ), $gallery_limit )
			: sprintf( 'Provider\'s package (%s) does not include a gallery.', ucfirst( $tier ) );
	}
	if ( isset( $fields['_job_description'] ) ) {
		$word_limit = $limits['bio_words'] ?? 0;
		$fields['_job_description']['description'] = $word_limit > 0
			? sprintf( 'Provider\'s package (%s) allows up to %d words.', ucfirst( $tier ), $word_limit )
			: sprintf( 'Provider\'s package (%s) uses an auto-generated description.', ucfirst( $tier ) );
	}

	// The 5 repeater fields — same defs as the front-end, same tier gating.
	if ( function_exists( 'ecm_native_repeater_field_defs' ) ) {
		foreach ( ecm_native_repeater_field_defs() as $key => $def ) {
			$max = $limits[ $def['limit_key'] ] ?? 0;
			if ( 0 === $max ) {
				continue; // Not available at this tier.
			}
			$fields[ '_' . $key ] = [
				'label'      => $def['label'] . sprintf( ' (%s plan: up to %d)', ucfirst( $tier ), $max ),
				'type'       => 'repeater',
				'priority'   => 25,
				'sub_fields' => $def['sub_fields'],
				'max'        => $max,
			];
		}
	}

	return $fields;
}, 200 );

/**
 * Admin-side renderer for the 'repeater' field type — the action-based
 * dispatch WPJM admin uses for any type without a built-in method (see
 * job_listing_data() in class-wp-job-manager-writepanels.php:
 * do_action("job_manager_input_{$type}", $key, $field) when
 * has_action("job_manager_input_{$type}") is true).
 *
 * Reuses the EXACT SAME template as the front-end
 * (job_manager/form-fields/repeater-field.php) — it only depends on local
 * $key/$field variables (matches how WPJM's own get_job_manager_template()
 * exposes them) and ships its own inline <script>/<style>, so it renders
 * identically in wp-admin with no extra asset wiring needed.
 */
add_action( 'job_manager_input_repeater', function ( $key, $field ) {
	echo '<p class="form-field form-field-repeater"><label>' . esc_html( wp_strip_all_tags( $field['label'] ?? '' ) ) . '</label>';
	include get_theme_file_path( 'job_manager/form-fields/repeater-field.php' );
	echo '</p>';
}, 10, 2 );
