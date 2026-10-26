<?php
/**
 * Generic repeater field renderer for WP Job Manager's native
 * submit-job / edit-job forms.
 *
 * Theme override of job_manager/form-fields/repeater-field.php — WPJM
 * core has no built-in "repeater" type; this is what makes 'type' =>
 * 'repeater' work at all when registered via the submit_job_form_fields
 * filter (see inc/wpjm-native-repeater-fields.php).
 *
 * Field config this expects (beyond WPJM's normal 'label'/'value'/etc.):
 *   'sub_fields' => [
 *       'sub_field_key' => [ 'type' => 'text|textarea|number|checkbox', 'label' => '...' ],
 *       ...
 *   ]
 *   'max' => int   // row limit — enforced here client-side (UI convenience
 *                  // only) AND server-side in submit_job_form_validate_fields.
 *
 * Values round-trip through WPJM's own generic field machinery with zero
 * custom save/load code needed: inputs are named "{$key}[{row}][{sub}]",
 * which WPJM's default get_posted_field() + sanitize_posted_field() already
 * parse and sanitize recursively for any nested array — confirmed from
 * WPJM core source (abstract-wp-job-manager-form.php). Saved automatically
 * as post meta under "_{$key}" (WPJM's own underscore-prefix convention),
 * and reloaded the same way when editing — see update_job_data() /
 * init_fields() in class-wp-job-manager-form-submit-job.php.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

$sub_fields = $field['sub_fields'] ?? [];
$existing   = is_array( $field['value'] ?? null ) ? array_values( $field['value'] ) : [];
$max        = isset( $field['max'] ) ? (int) $field['max'] : 0;
$field_name = esc_attr( $key );
?>
<div class="ecm-repeater" data-key="<?php echo $field_name; ?>" data-max="<?php echo esc_attr( $max ); ?>">
	<div class="ecm-repeater-rows">
		<?php foreach ( $existing as $row_index => $row ) : ?>
			<?php include __DIR__ . '/repeater-row.php'; ?>
		<?php endforeach; ?>
	</div>

	<template class="ecm-repeater-row-template">
		<?php $row_index = '__INDEX__'; $row = []; include __DIR__ . '/repeater-row.php'; ?>
	</template>

	<button type="button" class="ecm-repeater-add">+ Add</button>
	<p class="ecm-repeater-limit-msg" style="display:none;color:#b3413a;font-size:0.85rem;margin-top:6px;">
		You've reached the limit for your current package.
	</p>
</div>

<?php
// static does NOT persist across separate include() calls — use global instead.
global $ecm_repeater_js_printed;
if ( empty( $ecm_repeater_js_printed ) ) :
	$ecm_repeater_js_printed = true;
	?>
	<script>
	(function () {
		// JS-level guard: defensive fallback in case PHP emits the block twice.
		if ( window._ecmRepeaterInited ) { return; }
		window._ecmRepeaterInited = true;
		function renumber( repeater ) {
			var rows = repeater.querySelectorAll( '.ecm-repeater-rows > .ecm-repeater-row' );
			rows.forEach( function ( row, i ) {
				row.querySelectorAll( '[name]' ).forEach( function ( el ) {
					// Matches BOTH a real numeric index (existing rows, e.g. "[2]")
					// AND the template's literal placeholder ("[__INDEX__]") — the
					// original /\[\d+\]/-only version never matched the placeholder,
					// so every newly-added row kept the literal string "__INDEX__"
					// as its array key, and every such row collided under that same
					// key in $_POST — only the last one ever survived. This is why
					// nothing saved on a brand-new listing (every row there comes
					// from "+ Add", none pre-exist with a real numeric index).
					el.name = el.name.replace( /\[(\d+|__INDEX__)\]/, '[' + i + ']' );
				} );
			} );
			var addBtn   = repeater.querySelector( '.ecm-repeater-add' );
			var limitMsg = repeater.querySelector( '.ecm-repeater-limit-msg' );
			var max      = parseInt( repeater.dataset.max || '0', 10 );
			if ( max > 0 && rows.length >= max ) {
				addBtn.style.display = 'none';
				limitMsg.style.display = 'block';
			} else {
				addBtn.style.display = '';
				limitMsg.style.display = 'none';
			}
		}
		document.addEventListener( 'DOMContentLoaded', function () {
			document.querySelectorAll( '.ecm-repeater' ).forEach( function ( repeater ) {
				renumber( repeater );
				repeater.querySelector( '.ecm-repeater-add' ).addEventListener( 'click', function () {
					var rowsWrap = repeater.querySelector( '.ecm-repeater-rows' );
					var tpl      = repeater.querySelector( '.ecm-repeater-row-template' ).content.cloneNode( true );
					rowsWrap.appendChild( tpl );
					renumber( repeater );
				} );
				repeater.addEventListener( 'click', function ( e ) {
					if ( e.target.classList.contains( 'ecm-repeater-remove' ) ) {
						e.target.closest( '.ecm-repeater-row' ).remove();
						renumber( repeater );
					}
				} );
			} );

			// Star rating — delegate from document so newly-cloned rows work too
			document.addEventListener( 'click', function ( e ) {
				var star = e.target.closest( '.ecm-star' );
				if ( ! star ) { return; }
				var widget = star.closest( '.ecm-star-rating' );
				if ( ! widget ) { return; }
				var val    = parseInt( star.dataset.val, 10 );
				var hidden = widget.querySelector( 'input[type="hidden"]' );
				var label  = widget.querySelector( '.ecm-star-label' );
				if ( hidden ) { hidden.value = val; }
				if ( label )  { label.textContent = val + '/5'; }
				widget.querySelectorAll( '.ecm-star' ).forEach( function ( s ) {
					s.classList.toggle( 'active', parseInt( s.dataset.val, 10 ) <= val );
				} );
			} );

			// Star hover preview
			document.addEventListener( 'mouseover', function ( e ) {
				var star = e.target.closest( '.ecm-star' );
				if ( ! star ) { return; }
				var widget = star.closest( '.ecm-star-rating' );
				if ( ! widget ) { return; }
				var hval = parseInt( star.dataset.val, 10 );
				widget.querySelectorAll( '.ecm-star' ).forEach( function ( s ) {
					s.classList.toggle( 'hover', parseInt( s.dataset.val, 10 ) <= hval );
				} );
			} );
			document.addEventListener( 'mouseout', function ( e ) {
				var star = e.target.closest( '.ecm-star' );
				if ( ! star ) { return; }
				star.closest( '.ecm-star-rating' ).querySelectorAll( '.ecm-star' ).forEach( function ( s ) {
					s.classList.remove( 'hover' );
				} );
			} );
		} );
	})();
	</script>
	<style>
	/* Fully self-contained — deliberately doesn't depend on .btn-primary-lg
	   or any other theme class, since this template renders in multiple
	   contexts (provider dashboard, wp-admin) that don't all load the same
	   stylesheets. A missing class here would silently fall back to raw
	   browser-default button styling, which is very likely part of what
	   looked "broken" in the layout. */
	.ecm-repeater { margin-bottom: 4px; }
	.ecm-repeater-row { border: 1px solid var(--border, #ddd); border-radius: 10px; padding: 14px; margin-bottom: 12px; position: relative; box-sizing: border-box; }
	.ecm-repeater-row * { box-sizing: border-box; }
	.ecm-repeater-row label { display: block; font-size: 0.85rem; font-weight: 600; margin: 8px 0 4px; }
	.ecm-repeater-row input[type="text"], .ecm-repeater-row input[type="number"], .ecm-repeater-row textarea {
		width: 100%; padding: 9px 12px; border: 1px solid var(--border, #ddd); border-radius: 8px; font-family: inherit; font-size: 0.9rem;
	}
	.ecm-repeater-remove { position: absolute; top: 10px; right: 10px; background: none; border: none; color: #b3413a; cursor: pointer; font-size: 0.85rem; padding: 4px; }
	.ecm-repeater-add {
		display: inline-block; margin-top: 8px; padding: 10px 20px; border-radius: 8px; border: none;
		background: var(--charcoal, #2c2c2c); color: #fff; font-family: inherit; font-size: 0.9rem; font-weight: 600; cursor: pointer;
	}
	.ecm-repeater-add:hover { opacity: 0.9; }

	/* ── Star rating widget ── */
	.ecm-star-rating { display: flex; align-items: center; gap: 4px; margin: 4px 0 8px; }
	.ecm-star {
		background: none; border: none; padding: 2px; cursor: pointer;
		font-size: 1.7rem; line-height: 1; color: #d1d5db;
		transition: color 0.12s, transform 0.1s;
	}
	.ecm-star.active,
	.ecm-star.hover { color: #f59e0b; }
	.ecm-star:hover { transform: scale(1.15); }
	.ecm-star-label {
		margin-left: 8px; font-size: 0.8rem; font-weight: 600;
		color: var(--muted, #888); min-width: 28px;
	}
	</style>
<?php endif; ?>
