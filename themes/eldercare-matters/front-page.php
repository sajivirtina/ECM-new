<?php
/**
 * Front Page Template — ElderCareMatters Homepage
 *
 * Renders all homepage sections via template parts.
 * All content is managed through ACF fields on this page in the WP admin.
 *
 * Template hierarchy: front-page.php > home.php > index.php
 */

get_header();
?>

<?php get_template_part( 'template-parts/home', 'hero' ); ?>
<?php get_template_part( 'template-parts/home', 'stats' ); ?>
<?php get_template_part( 'template-parts/home', 'categories' ); ?>
<?php get_template_part( 'template-parts/home', 'how-it-works' ); ?>
<?php get_template_part( 'template-parts/home', 'why-ecm' ); ?>
<?php get_template_part( 'template-parts/home', 'testimonials' ); ?>
<?php get_template_part( 'template-parts/home', 'faq' ); ?>
<?php get_template_part( 'template-parts/home', 'provider-cta' ); ?>

<?php get_footer(); ?>
