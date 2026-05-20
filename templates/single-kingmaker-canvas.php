<?php
/**
 * Full-document template for Kingmaker Canvas posts.
 *
 * The normal single-post template is intentionally skipped so Elementor's
 * blog sidebar and post chrome cannot wrap the designed article.
 *
 * @package KingmakerCanvas
 */

defined( 'ABSPATH' ) || exit;

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
?>
<div id="page" class="site kse-canvas-site">
	<?php kse_canvas_render_elementor_location( 'header' ); ?>

	<main id="primary" class="kse-canvas-page">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</main>

	<?php kse_canvas_render_after_article_template(); ?>
	<?php kse_canvas_render_elementor_location( 'footer' ); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
