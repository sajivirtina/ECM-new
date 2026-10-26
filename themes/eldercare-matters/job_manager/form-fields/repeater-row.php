<?php
/**
 * Single row of a repeater field — included by repeater-field.php for
 * each existing row, and once more (with row_index = '__INDEX__') as the
 * <template> the JS clones for new rows.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ecm-repeater-row">
	<button type="button" class="ecm-repeater-remove">✕ Remove</button>
	<?php foreach ( $sub_fields as $sub_key => $sub ) :
		$input_name = esc_attr( $field_name . '[' . $row_index . '][' . $sub_key . ']' );
		$value      = $row[ $sub_key ] ?? '';
	?>
		<label><?php echo esc_html( $sub['label'] ?? $sub_key ); ?></label>
		<?php if ( 'star_rating' === ( $sub['type'] ?? 'text' ) ) : ?>
			<div class="ecm-star-rating" data-name="<?php echo $input_name; ?>">
				<?php for ( $star = 1; $star <= 5; $star++ ) : ?>
					<button type="button" class="ecm-star<?php echo (int) $value >= $star ? ' active' : ''; ?>" data-val="<?php echo $star; ?>" aria-label="<?php echo esc_attr( $star ); ?> star">★</button>
				<?php endfor; ?>
				<span class="ecm-star-label"><?php echo $value ? esc_html( $value ) . '/5' : ''; ?></span>
				<input type="hidden" name="<?php echo $input_name; ?>" value="<?php echo esc_attr( $value ?: '' ); ?>">
			</div>
		<?php elseif ( 'textarea' === ( $sub['type'] ?? 'text' ) ) : ?>
			<textarea name="<?php echo $input_name; ?>" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
		<?php elseif ( 'number' === ( $sub['type'] ?? 'text' ) ) : ?>
			<input type="number" name="<?php echo $input_name; ?>" value="<?php echo esc_attr( $value ); ?>"
			       <?php if ( isset( $sub['min'] ) ) : ?>min="<?php echo esc_attr( $sub['min'] ); ?>"<?php endif; ?>
			       <?php if ( isset( $sub['max'] ) ) : ?>max="<?php echo esc_attr( $sub['max'] ); ?>"<?php endif; ?>>
		<?php elseif ( 'checkbox' === ( $sub['type'] ?? 'text' ) ) : ?>
			<input type="hidden" name="<?php echo $input_name; ?>" value="0">
			<label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;">
				<input type="checkbox" name="<?php echo $input_name; ?>" value="1" <?php checked( ! empty( $value ) ); ?>>
				<?php echo esc_html( $sub['checkbox_label'] ?? 'Yes' ); ?>
			</label>
		<?php else : ?>
			<input type="text" name="<?php echo $input_name; ?>" value="<?php echo esc_attr( $value ); ?>">
		<?php endif; ?>
	<?php endforeach; ?>
</div>
