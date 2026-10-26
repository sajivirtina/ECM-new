<?php
/**
 * Two-step "Category → Subcategory" cascading picker for WP Job Manager /
 * Field Editor taxonomy fields (e.g. job_category / job_listing_category).
 *
 * Field Editor's own "Taxonomy Multi-Select Dropdown" type renders every
 * term — parents AND children — flattened into one <select multiple>. This
 * template renders a hidden copy of THAT SAME NATIVE SELECT (same
 * name/id/options), so saving, validation, and edit-mode pre-fill keep
 * working exactly as Field Editor already built them — nothing about how
 * the value round-trips changes. The visible picker is two friendlier
 * controls instead: a single Category dropdown, then a Subcategory
 * <select multiple> listbox for that category's children.
 *
 * A plain <select multiple> normally needs Ctrl/Cmd held down to pick more
 * than one option — a plain click replaces the selection. The JS below
 * intercepts a plain click on each <option> (via `mousedown`, preventing
 * the browser's own single-pick default) and toggles that one option's
 * `selected` state itself — so a plain click ADDS to the selection instead
 * of replacing it, no modifier key needed.
 *
 * The tier limit itself is enforced in ONE place — a shared `change`
 * handler that runs after every selection change, however it happened
 * (our own click-toggle, or the browser's native Shift+Arrow / Shift+Click
 * range-select, which bypasses the click handler entirely since it's a
 * keyboard/native interaction, not a mousedown on an option). Whatever the
 * input method, if the result is over the cap, it's reverted to the last
 * valid selection right there — so there's no gap between input methods.
 * (Server-side, submit_job_form_validate_fields + job_manager_update_job_data
 * in inc/wpjm-cascading-category-field.php still hard-enforce the same cap
 * regardless of what the client did.)
 *
 * $field['subcategory_limit'] (int, set by inc/wpjm-cascading-category-field.php
 * from the provider's package tier — -1 means unlimited) caps how many
 * SUBcategory (child-term) options can be selected at once. If a Category
 * has no children, the Subcategory box simply stays hidden — no message, no
 * blocker; the Category pick alone is a valid, submittable selection.
 *
 * Registered as field type 'category_cascade' — see
 * inc/wpjm-cascading-category-field.php.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

$taxonomy = $field['taxonomy'] ?? 'job_listing_category';
$limit    = isset( $field['subcategory_limit'] ) ? (int) $field['subcategory_limit'] : -1; // -1 = unlimited.

$all_terms = get_terms( [
	'taxonomy'   => $taxonomy,
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
] );
if ( is_wp_error( $all_terms ) ) {
	$all_terms = [];
}

$by_parent  = [];
$term_by_id = [];
foreach ( $all_terms as $term ) {
	$by_parent[ (int) $term->parent ][] = $term;
	$term_by_id[ (int) $term->term_id ] = $term;
}
$top_level = $by_parent[0] ?? [];

// Existing value(s) — same shape Field Editor already hands this template.
$existing = $field['value'] ?? [];
$existing = is_array( $existing ) ? array_map( 'intval', $existing ) : array_filter( [ (int) $existing ] );

// Work out which top-level category the existing selection implies, so
// Edit Listing opens with both controls already set correctly.
$selected_parent_id = 0;
foreach ( $existing as $id ) {
	if ( ! isset( $term_by_id[ $id ] ) ) {
		continue;
	}
	$t = $term_by_id[ $id ];
	if ( 0 === (int) $t->parent ) {
		$selected_parent_id = (int) $t->term_id;
	} elseif ( ! $selected_parent_id ) {
		$selected_parent_id = (int) $t->parent;
	}
}

$field_id   = esc_attr( $key );
$field_name = esc_attr( $key ) . '[]'; // The hidden native select always posts as an array — same shape a multi-select field always used.

// parent_term_id => [ ['id'=>..,'name'=>..], ... ] — lets the JS rebuild the
// Subcategory box instantly on Category change, no AJAX round-trip.
$children_by_parent = [];
foreach ( $by_parent as $parent_id => $children ) {
	if ( 0 === (int) $parent_id ) {
		continue;
	}
	foreach ( $children as $child ) {
		$children_by_parent[ $parent_id ][] = [ 'id' => (string) $child->term_id, 'name' => $child->name ];
	}
}
?>
<div class="ecm-cascade" data-key="<?php echo $field_id; ?>" data-limit="<?php echo esc_attr( $limit ); ?>">

	<div class="ecm-cascade-col">
		<select class="ecm-cascade-parent" id="<?php echo $field_id; ?>_parent" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
			<option value=""><?php esc_html_e( '— Select a category —', 'eldercare-matters' ); ?></option>
			<?php foreach ( $top_level as $term ) : ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" data-slug="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_parent_id, (int) $term->term_id ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="ecm-cascade-col">
		<!-- Click-to-toggle multi-select listbox — populated/rebuilt entirely by JS (fillChildren()). -->
		<select class="ecm-cascade-child" id="<?php echo $field_id; ?>_child" multiple <?php echo $selected_parent_id ? '' : 'style="display:none;"'; ?>></select>

		<?php if ( $limit >= 0 ) : ?>
			<p class="ecm-cascade-limit-hint">
				<?php
				printf(
					/* translators: %d: number of subcategories allowed */
					esc_html( _n( 'Up to %d subcategory on your current plan — click to select/deselect, no Ctrl/Cmd needed.', 'Up to %d subcategories on your current plan — click to select/deselect, no Ctrl/Cmd needed.', $limit, 'eldercare-matters' ) ),
					(int) $limit
				);
				?>
			</p>
		<?php else : ?>
			<p class="ecm-cascade-limit-hint">
				<?php esc_html_e( 'Click to select/deselect subcategories — no Ctrl/Cmd needed.', 'eldercare-matters' ); ?>
			</p>
		<?php endif; ?>
		<p class="ecm-cascade-limit-msg" style="display:none;">
			<?php esc_html_e( "You've reached the subcategory limit for your current plan — upgrade to add more.", 'eldercare-matters' ); ?>
		</p>
	</div>

	<select name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo $field_id; ?>" class="ecm-cascade-native" multiple style="display:none;" aria-hidden="true" tabindex="-1">
		<?php foreach ( $all_terms as $term ) : ?>
			<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $existing, true ) ); ?>>
				<?php echo esc_html( $term->name ); ?>
			</option>
		<?php endforeach; ?>
	</select>

</div>
<script>
window.ECM_CASCADE_DATA = window.ECM_CASCADE_DATA || {};
window.ECM_CASCADE_DATA[<?php echo wp_json_encode( $field_id ); ?>] = <?php echo wp_json_encode( $children_by_parent ); ?>;
</script>

<?php
// JS/CSS printed once per page regardless of how many cascade fields render.
static $ecm_cascade_js_printed = false;
if ( ! $ecm_cascade_js_printed ) :
	$ecm_cascade_js_printed = true;
	?>
	<style>
	.ecm-cascade { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-start; }
	.ecm-cascade-col { flex: 1 1 220px; min-width: 220px; display: flex; flex-direction: column; }
	.ecm-cascade-parent, .ecm-cascade-child {
		width: 100%; padding: 9px 12px; box-sizing: border-box;
		border: 1px solid var(--border, #ddd); border-radius: 8px;
		font-family: inherit; font-size: 0.9rem; background: #fff;
	}
	.ecm-cascade-child { min-height: 110px; }
	.ecm-cascade-child option { padding: 4px 6px; cursor: pointer; }
	.ecm-cascade-limit-hint { color: #666; font-size: 0.82rem; margin: 4px 0 0; }
	.ecm-cascade-limit-msg { color: #b3413a; font-size: 0.82rem; margin: 4px 0 0; }
	</style>
	<script>
	(function () {
		function syncNative( wrap ) {
			var native = wrap.querySelector( '.ecm-cascade-native' );
			var parent = wrap.querySelector( '.ecm-cascade-parent' );
			var child  = wrap.querySelector( '.ecm-cascade-child' );
			var chosen = {};
			if ( parent.value ) { chosen[ parent.value ] = true; }
			Array.prototype.forEach.call( child.selectedOptions || [], function ( o ) {
				if ( o.value ) { chosen[ o.value ] = true; }
			} );
			Array.prototype.forEach.call( native.options, function ( o ) {
				o.selected = !! chosen[ o.value ];
			} );
		}

		function initCascade( wrap ) {
			var key          = wrap.dataset.key;
			var limit        = parseInt( wrap.dataset.limit, 10 ); // -1 = unlimited
			var parent       = wrap.querySelector( '.ecm-cascade-parent' );
			var child        = wrap.querySelector( '.ecm-cascade-child' );
			var native       = wrap.querySelector( '.ecm-cascade-native' );
			var limitMsg     = wrap.querySelector( '.ecm-cascade-limit-msg' );
			var treeData     = ( window.ECM_CASCADE_DATA && window.ECM_CASCADE_DATA[ key ] ) || {};
			var prevSelected = []; // Last known-valid (<= limit) set of selected values.

			function currentSelected() {
				return Array.prototype.map.call( child.selectedOptions || [], function ( o ) { return o.value; } );
			}

			// Single source of truth for the cap — runs after EVERY selection
			// change regardless of how it happened (our click-toggle below,
			// or the browser's own Shift+Arrow / Shift+Click range-select,
			// which never touches the mousedown handler at all). If the
			// result is over the cap, it's reverted here.
			function enforceLimit() {
				if ( limit < 0 ) {
					prevSelected = currentSelected();
					return;
				}
				var selected = currentSelected();
				if ( selected.length > limit ) {
					Array.prototype.forEach.call( child.options, function ( o ) {
						o.selected = prevSelected.indexOf( o.value ) !== -1;
					} );
					if ( limitMsg ) { limitMsg.style.display = ''; }
				} else {
					prevSelected = selected;
					if ( limitMsg ) { limitMsg.style.display = 'none'; }
				}
			}

			function fillChildren( parentId, preselect ) {
				preselect = preselect || [];
				child.innerHTML = '';
				var kids = treeData[ parentId ] || [];
				kids.forEach( function ( kid ) {
					var opt = document.createElement( 'option' );
					opt.value = kid.id;
					opt.textContent = kid.name;
					if ( preselect.indexOf( String( kid.id ) ) !== -1 ) {
						opt.selected = true;
					}
					child.appendChild( opt );
				} );
				// No subcategories under this category — hide the box entirely
				// and show no message; the Category pick alone is a complete,
				// submittable selection.
				child.style.display = kids.length ? '' : 'none';
				prevSelected = preselect.slice();
				if ( limitMsg ) { limitMsg.style.display = 'none'; }
			}

			// Click-to-toggle: a plain click on an <option> normally replaces
			// the whole selection in a native multi-select. Intercept it on
			// mousedown (before the browser applies its default), and flip
			// just that option's `selected` state ourselves instead. The cap
			// itself is NOT checked here — the shared 'change' handler below
			// enforces it uniformly for every input method.
			child.addEventListener( 'mousedown', function ( e ) {
				if ( e.target.tagName !== 'OPTION' ) { return; }
				e.preventDefault();
				e.target.selected = ! e.target.selected;
				child.focus();
				child.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );

			child.addEventListener( 'change', function () {
				enforceLimit();
				syncNative( wrap );
			} );

			// Edit mode: the hidden native select already carries the saved
			// term IDs as selected options — read those back to pre-fill.
			var initialSelected = Array.prototype.map.call( native.selectedOptions || [], function ( o ) { return o.value; } );
			if ( parent.value ) {
				fillChildren( parent.value, initialSelected );
			}

			parent.addEventListener( 'change', function () {
				fillChildren( parent.value, [] );
				syncNative( wrap );
			} );

			var form = wrap.closest( 'form' );
			if ( form ) {
				form.addEventListener( 'submit', function () { syncNative( wrap ); } );
			}

			syncNative( wrap );
		}

		document.addEventListener( 'DOMContentLoaded', function () {
			document.querySelectorAll( '.ecm-cascade' ).forEach( initCascade );
		} );
	})();
	</script>
<?php endif; ?>