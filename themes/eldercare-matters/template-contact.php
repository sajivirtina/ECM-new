<?php
/**
 * Template Name: ECM — Contact Us
 *
 * Contact page: four cards (Looking for care, Are you a provider, Everything
 * else, Privacy and your data) and an optional WPForms form below them.
 * Every block is an ACF field (inc/acf-contact.php); the arrays below are the
 * defaults used until a field is saved.
 *
 * The form stays empty until a WPForms form ID is entered in the page's
 * "Contact Form" tab, so nothing breaks while the form is being built.
 */

get_header();

$em = [ 'em' => [] ];

$title = ecm_get_field( 'ct_title', 'Contact <em>Us</em>' );
$sub   = ecm_get_field( 'ct_sub', 'Pick the option that fits and we will point you the right way.' );

$cards = get_field( 'ct_cards' );
if ( ! $cards ) {
	$cards = [
		[
			'icon'   => '🔍',
			'title'  => 'Looking for care?',
			'text'   => 'Tell us what you need and choose the providers you want to hear from. Free for families.',
			'action' => 'modal',
			'label'  => 'Find Care Now',
			'url'    => '',
			'email'  => '',
			'note'   => '',
		],
		[
			'icon'   => '🏢',
			'title'  => 'Are you a provider?',
			'text'   => 'See the plans and how families find and choose providers on ElderCareMatters.',
			'action' => 'link',
			'label'  => 'See Plans & Pricing',
			'url'    => '/plans/',
			'email'  => '',
			'note'   => '',
		],
		[
			'icon'   => '✉️',
			'title'  => 'Everything else',
			'text'   => 'Questions, feedback or anything not covered here. Email us and we will reply.',
			'action' => 'email',
			'label'  => '',
			'url'    => '',
			'email'  => 'Help@ElderCareMatters.com',
			'note'   => '',
		],
		[
			'icon'   => '🛡️',
			'title'  => 'Privacy and your data',
			'text'   => 'To see, correct or delete your data, or to ask a privacy question, email us.',
			'action' => 'email',
			'label'  => '',
			'url'    => '',
			'email'  => 'Rights@ElderCareMatters.com',
			'note'   => 'We reply within 45 days.',
		],
	];
}

$form_title = ecm_get_field( 'ct_form_title', 'Send us a message' );
$form_sub   = ecm_get_field( 'ct_form_sub', '' );
$form_id    = (int) get_field( 'ct_form_id' );
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current">Contact Us</span>
</div>

<!-- HERO -->
<section class="plans-hero contact-hero">
    <h1 class="plans-hero-title"><?php echo wp_kses( $title, $em ); ?></h1>
    <?php if ( $sub ) : ?>
        <p class="plans-hero-sub"><?php echo esc_html( $sub ); ?></p>
    <?php endif; ?>
</section>

<!-- CARDS -->
<section class="contact-cards-section">
    <div class="contact-cards">
        <?php foreach ( $cards as $card ) :
            $action = $card['action'] ?? 'link';
            $label  = $card['label'] ?? '';
            $url    = ecm_resolve_url( $card['url'] ?? '' );
            $email  = trim( (string) ( $card['email'] ?? '' ) );
            $note   = $card['note'] ?? '';
        ?>
        <div class="contact-card">
            <?php if ( ! empty( $card['icon'] ) ) : ?>
                <div class="contact-card-icon" aria-hidden="true"><?php echo esc_html( $card['icon'] ); ?></div>
            <?php endif; ?>
            <h2 class="contact-card-title"><?php echo esc_html( $card['title'] ?? '' ); ?></h2>
            <p class="contact-card-text"><?php echo esc_html( $card['text'] ?? '' ); ?></p>

            <?php if ( 'modal' === $action && $label ) : ?>
                <button type="button" class="btn-primary-lg" data-open-form-modal><?php echo esc_html( $label ); ?></button>
            <?php elseif ( 'link' === $action && $label && $url ) : ?>
                <a class="btn-primary-lg" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
            <?php elseif ( 'email' === $action && $email ) : ?>
                <a class="contact-card-email" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
            <?php endif; ?>

            <?php if ( $note ) : ?>
                <p class="contact-card-note"><?php echo esc_html( $note ); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- FORM (WPForms) — empty until a form ID is entered on the page -->
<?php if ( $form_id && shortcode_exists( 'wpforms' ) ) : ?>
<section class="contact-form-section">
    <div class="contact-form-inner">
        <h2 class="contact-form-title"><?php echo esc_html( $form_title ); ?></h2>
        <?php if ( $form_sub ) : ?>
            <p class="contact-form-sub"><?php echo esc_html( $form_sub ); ?></p>
        <?php endif; ?>
        <?php echo do_shortcode( '[wpforms id="' . $form_id . '" title="false" description="false"]' ); ?>
    </div>
</section>
<?php elseif ( current_user_can( 'edit_pages' ) ) : ?>
<section class="contact-form-section">
    <div class="contact-form-inner">
        <p class="contact-form-sub">Editors only: enter a WPForms form ID in this page's "Contact Form" tab to show the form here.</p>
    </div>
</section>
<?php endif; ?>

<?php get_footer(); ?>
