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
 * controls instead: a single Category dropdown, then a CHECKBOX list of
 * that category's children for Subcategory — always checkboxes,
 * unconditionally, regardless of whatever 'multiple' flag Field Editor's
 * own field config does or doesn't carry (a plain <select multiple>
 * requires holding Ctrl/Cmd to pick more than one option, which is the
 * exact problem this replaced — checkboxes need no modifier key and no
 * guessing about the source field's config). JS keeps the checkboxes in
 * sync with the real hidden select on every change and right before submit.
 *
 * $field['subcategory_limit'] (int, set by inc/wpjm-cascading-category-field.php
 * from the provider's package tier — -1 means unlimited) caps how many
 * SUBcategory (child-term) boxes can be checked at once. If a Category has
 * no children, the Subcategory list simply stays hidden — no message, no
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
// Subcategory list instantly on Category change, no AJAX round-trip.
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

	<select class="ecm-cascade-parent" id="<?php echo $field_id; ?>_parent" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
		<option value=""><?php esc_html_e( '— Select a category —', 'eldercare-matters' ); ?></option>
		<?php foreach ( $top_level as $term ) : ?>
			<option value="<?php echo esc_attr( $term->term_id ); ?>" data-slug="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_parent_id, (int) $term->term_id ); ?>>
				<?php echo esc_html( $term->name ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<!-- Checkbox list — populated/rebuilt entirely by JS (fillChildren()). -->
	<div class="ecm-cascade-child-checks" id="<?php echo $field_id; ?>_child" <?php echo $selected_parent_id ? '' : 'style="display:none;"'; ?>></div>

	<select name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo $field_id; ?>" class="ecm-cascade-native" multiple style="display:none;" aria-hidden="true" tabindex="-1">
		<?php foreach ( $all_terms as $term ) : ?>
			<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $existing, true ) ); ?>>
				<?php echo esc_html( $term->name ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<?php if ( $limit >= 0 ) : ?>
		<p class="ecm-cascade-limit-hint" style="width:100%;color:#666;font-size:0.82rem;margin:4px 0 0;">
			<?php
			printf(
				/* translators: %d: number of subcategories allowed */
				esc_html( _n( 'Up to %d subcategory on your current plan.', 'Up to %d subcategories on your current plan.', $limit, 'eldercare-matters' ) ),
				(int) $limit
			);
			?>
		</p>
	<?php endif; ?>
	<p class="ecm-cascade-limit-msg" style="display:none;width:100%;color:#b3413a;font-size:0.82rem;margin:4px 0 0;">
		<?php esc_html_e( "You've reached the subcategory limit for your current plan — upgrade to add more.", 'eldercare-matters' ); ?>
	</p>

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
	.ecm-cascade-parent {
		flex: 1 1 220px; min-width: 180px; padding: 9px 12px;
		border: 1px solid var(--border, #ddd); border-radius: 8px;
		font-family: inherit; font-size: 0.9rem; background: #fff;
	}
	.ecm-cascade-child-checks {
		flex: 1 1 220px; min-width: 220px; max-height: 160px; overflow-y: auto;
		border: 1px solid var(--border, #ddd); border-radius: 8px;
		padding: 8px 12px; background: #fff;
	}
	.ecm-cascade-child-checks label {
		display: flex; align-items: center; gap: 8px;
		font-size: 0.9rem; font-weight: normal; margin: 6px 0; cursor: pointer;
	}
	.ecm-cascade-child-checks input[type="checkbox"] { margin: 0; }
	</style>
	<script>
	(function () {
		function syncNative( wrap ) {
			var native = wrap.querySelector( '.ecm-cascade-native' );
			var parent = wrap.querySelector( '.ecm-cascade-parent' );
			var chosen = {};
			if ( parent.value ) { chosen[ parent.value ] = true; }
			wrap.querySelectorAll( '.ecm-cascade-child-checks input[type="checkbox"]:checked' ).forEach( function ( cb ) {
				chosen[ cb.value ] = true;
			} );
			Array.prototype.forEach.call( native.options, function ( o ) {
				o.selected = !! chosen[ o.value ];
			} );
		}

		function initCascade( wrap ) {
			var key      = wrap.dataset.key;
			var limit    = parseInt( wrap.dataset.limit, 10 ); // -1 = unlimited
			var parent   = wrap.querySelector( '.ecm-cascade-parent' );
			var native   = wrap.querySelector( '.ecm-cascade-native' );
			var limitMsg = wrap.querySelector( '.ecm-cascade-limit-msg' );
			var treeData = ( window.ECM_CASCADE_DATA && window.ECM_CASCADE_DATA[ key ] ) || {};
			var childBox = wrap.querySelector( '.ecm-cascade-child-checks' );

			function checkedCount() {
				return childBox.querySelectorAll( 'input[type="checkbox"]:checked' ).length;
			}

			function fillChildren( parentId, preselect ) {
				preselect = preselect || [];
				childBox.innerHTML = '';
				var kids = treeData[ parentId ] || [];
				kids.forEach( function ( kid ) {
					var label = document.createElement( 'label' );
					var cb    = document.createElement( 'input' );
					cb.type  = 'checkbox';
					cb.value = kid.id;
					if ( preselect.indexOf( String( kid.id ) ) !== -1 ) {
						cb.checked = true;
					}
					label.appendChild( cb );
					label.appendChild( document.createTextNode( kid.name ) );
					childBox.appendChild( label );
				} );
				// No subcategories under this category — hide the list entirely
				// and show no message; the Category pick alone is a complete,
				// submittable selection.
				childBox.style.display = kids.length ? '' : 'none';
				if ( limitMsg ) { limitMsg.style.display = 'none'; }
			}

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

			// Delegated listener — checkboxes are rebuilt on every Category
			// change, so a single listener on the container outlives them.
			childBox.addEventListener( 'change', function ( e ) {
				if ( e.target.type !== 'checkbox' ) { return; }
				if ( limit >= 0 && e.target.checked && checkedCount() > limit ) {
					e.target.checked = false; // Over the cap — refuse the check and warn.
					if ( limitMsg ) { limitMsg.style.display = ''; }
				} else if ( limitMsg ) {
					limitMsg.style.display = 'none';
				}
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