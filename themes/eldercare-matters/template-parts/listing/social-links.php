<?php
/**
 * Social media icon links for provider hero.
 *
 * Expects $social (array) set by single-job_listing.php — keys: facebook,
 * instagram, linkedin, twitter. Only renders when at least one link exists.
 *
 * @package ElderCareMatters
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $social ) ) {
	return;
}

$icons = [
	'facebook'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
	'instagram' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
	'linkedin'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>',
	'twitter'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.835L1.254 2.25H8.08l4.253 5.622 5.911-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
];

$labels = [
	'facebook'  => 'Facebook',
	'instagram' => 'Instagram',
	'linkedin'  => 'LinkedIn',
	'twitter'   => 'X / Twitter',
];
?>
<div class="provider-social-links">
<?php foreach ( $icons as $key => $icon ) : ?>
	<?php if ( ! empty( $social[ $key ] ) ) : ?>
	<a href="<?php echo esc_url( $social[ $key ] ); ?>"
	   class="provider-social-link"
	   target="_blank" rel="noopener noreferrer"
	   title="<?php echo esc_attr( $labels[ $key ] ); ?>">
		<?php echo $icon; // already escaped SVG literal ?>
	</a>
	<?php endif; ?>
<?php endforeach; ?>
</div>
