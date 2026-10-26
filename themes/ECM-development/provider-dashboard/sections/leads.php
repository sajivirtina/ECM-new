<?php
/**
 * Lead Inbox — Privacy Compliance: Developer Requirements, doc section 5
 * ("Provider dashboard") and 10.5 ("Provider dashboard (5.1, 5.3)").
 *
 * Lists every request distributed to one of this provider's listings
 * (masked payload only — category/location/timing/care_summary, never
 * name/phone/email/raw notes), with a "Request Contact Details" button once
 * that listing's plan-tier window has opened [4.4]. Clicking it shows the
 * permitted-use text from 10.5 before the actual unmask call fires [5.1].
 *
 * The closed-lead state (5.3, items 44-45) is shown here once
 * inc/stop-contact.php marks a lead _ecm_closed — exact 10.5 text.
 *
 * NOT built here (separate, larger pieces — ask when you want them):
 * 6.2 (Help@ family-level stop across ALL of a family's requests) and
 * 6.3/6.4 (deletion, the Rights@ queue).
 */

defined( 'ABSPATH' ) || exit;

$uid   = get_current_user_id();
$leads = function_exists( 'ecm_leads_for_provider' ) ? ecm_leads_for_provider( $uid ) : [];
?>

<div class="greeting-bar">
	<div class="greeting-text">
		<h2>Leads</h2>
		<p><?php echo esc_html( sprintf( _n( '%s family request', '%s family requests', count( $leads ), 'eldercare-matters' ), number_format_i18n( count( $leads ) ) ) ); ?></p>
	</div>
</div>

<div class="dash-main__body">

<?php if ( ! $leads ) : ?>

	<div class="pd-empty-state">
		<div style="font-size:2rem;margin-bottom:10px;">📨</div>
		<h3>No requests yet</h3>
		<p>When a family picks one of your listings, their request will show up here.</p>
	</div>

<?php else : ?>

	<div class="pd-lead-list">
	<?php foreach ( $leads as $lead ) :
		$payload    = $lead['payload'];
		$card_id    = 'pd-lead-' . $lead['request_id'] . '-' . $lead['provider_id'];
	?>
		<div class="dash-card pd-lead-card" id="<?php echo esc_attr( $card_id ); ?>">
			<div class="dash-card__header">
				<div class="dash-card__title">
					<?php echo esc_html( $payload['category'] ?: 'Care request' ); ?>
					<?php if ( $payload['location'] ) : ?>
						<span style="font-weight:400;color:var(--gray-500,#6b7280);"> · <?php echo esc_html( $payload['location'] ); ?></span>
					<?php endif; ?>
				</div>
				<span class="pd-badge <?php echo esc_attr( $lead['closed'] ? 'pd-badge--red' : ( $lead['unmasked'] ? 'pd-badge--green' : ( $lead['window_open'] ? 'pd-badge--amber' : 'pd-badge--gray' ) ) ); ?>">
					<?php echo esc_html( $lead['closed'] ? 'Closed' : ( $lead['unmasked'] ? 'Contact revealed' : ( $lead['window_open'] ? 'Ready to unmask' : 'Window not open yet' ) ) ); ?>
				</span>
			</div>
			<div class="dash-card__body">
				<?php if ( $payload['timing'] ) : ?>
					<p style="margin:0 0 8px;"><strong>Timing:</strong> <?php echo esc_html( $payload['timing'] ); ?></p>
				<?php endif; ?>
				<?php if ( $payload['care_summary'] ) : ?>
					<p style="margin:0 0 14px;color:var(--gray-700,#374151);"><?php echo esc_html( $payload['care_summary'] ); ?></p>
				<?php endif; ?>

				<?php if ( $lead['unmasked'] && $lead['contact'] ) :
					$c = $lead['contact']; ?>
					<div class="pd-contact-block">
						<p style="margin:0 0 4px;"><strong><?php echo esc_html( trim( $c['first_name'] . ' ' . $c['last_name'] ) ); ?></strong></p>
						<?php if ( $c['email'] ) : ?><p style="margin:0 0 4px;">✉️ <a href="mailto:<?php echo esc_attr( $c['email'] ); ?>"><?php echo esc_html( $c['email'] ); ?></a></p><?php endif; ?>
						<?php if ( $c['phone'] ) : ?><p style="margin:0 0 4px;">📞 <a href="tel:<?php echo esc_attr( $c['phone'] ); ?>"><?php echo esc_html( $c['phone'] ); ?></a></p><?php endif; ?>
						<?php if ( $c['description'] ) : ?><p style="margin:8px 0 0;color:var(--gray-700,#374151);"><?php echo esc_html( $c['description'] ); ?></p><?php endif; ?>
					</div>
					<?php if ( ! $lead['closed'] ) : ?>
						<p class="pd-permitted-use-note">Use these details only to respond to this family's request. Do not add them to marketing lists, sell them or share them. See the Terms.</p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $lead['closed'] ) : ?>
					<p class="pd-closed-note">This family asked to stop contact. Do not contact them about this request.</p>

				<?php elseif ( $lead['unmasked'] ) : ?>
					<?php // Contact already rendered above; nothing further to show. ?>

				<?php elseif ( $lead['window_open'] ) : ?>
					<div class="pd-unmask-confirm" style="display:none;">
						<p class="pd-permitted-use-note">Use these details only to respond to this family's request. Do not add them to marketing lists, sell them or share them. See the Terms.</p>
						<button type="button" class="btn--primary btn--sm pd-unmask-confirm-btn">Confirm — Request Contact Details</button>
						<button type="button" class="btn--secondary btn--sm pd-unmask-cancel-btn">Cancel</button>
					</div>
					<button type="button"
						class="btn--primary btn--sm pd-unmask-btn"
						data-request-id="<?php echo esc_attr( $lead['request_id'] ); ?>"
						data-card="<?php echo esc_attr( $card_id ); ?>">
						Request Contact Details
					</button>

				<?php else : ?>
					<p class="pd-window-note">Your plan opens at hour <?php echo esc_html( $lead['window_start'] ); ?>. This request opens to you <?php echo esc_html( wp_date( 'M j, g:i a', $lead['opens_at'] ) ); ?>.</p>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
	</div>

<?php endif; ?>

</div><!-- /.dash-main__body -->
