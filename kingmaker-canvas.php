<?php
/**
 * Plugin Name: Kingmaker Canvas
 * Plugin URI:  https://github.com/Kingmaker-Search/kingmaker-canvas
 * Description: Registers a "Kingmaker Canvas" page template that keeps the site's global header and footer but strips theme content-wrapper constraints (max-width, padding, typography, overflow, table styling) so custom-designed article HTML produced by Kingmaker Search's content-engine renders at full viewport width without theme interference. Also injects vanilla JS for sticky on-page TOC and FAQ accordion behavior.
 * Version:     1.1.0
 * Author:      Kingmaker Search
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'KSE_CANVAS_SLUG' ) ) {
	define( 'KSE_CANVAS_SLUG', 'kingmaker-canvas' );
}

/* ---------- Self-update checker (polls GitHub releases) ---------- */

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$kse_canvas_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/Kingmaker-Search/kingmaker-canvas/',
	__FILE__,
	'kingmaker-canvas'
);
$kse_canvas_update_checker->setBranch( 'main' );
/* Enable update detection from GitHub Releases (we tag v1.x.x and PUC sees them) */
$kse_canvas_update_checker->getVcsApi()->enableReleaseAssets();

/* ---------- Template registration ---------- */

function kse_canvas_register( $templates ) {
	$templates[ KSE_CANVAS_SLUG ] = __( 'Kingmaker Canvas', 'kingmaker-canvas' );
	return $templates;
}
add_filter( 'theme_page_templates', 'kse_canvas_register' );
add_filter( 'theme_post_templates', 'kse_canvas_register' );

function kse_canvas_body_class( $classes ) {
	global $post;
	if ( $post && get_page_template_slug( $post->ID ) === KSE_CANVAS_SLUG ) {
		$classes[] = 'kingmaker-canvas-active';
	}
	return $classes;
}
add_filter( 'body_class', 'kse_canvas_body_class' );

/* ---------- CSS overrides (wp_head, priority 999 to win cascade) ---------- */

function kse_canvas_inject_css() {
	global $post;
	if ( ! $post || get_page_template_slug( $post->ID ) !== KSE_CANVAS_SLUG ) {
		return;
	}
	?>
<style id="kse-canvas-overrides">
/* Inherit site font — override Tailwind's --font-sans/--font-mono from the post body's inline stylesheet */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper {
	--font-sans: inherit;
	--font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
/* Strip theme content-wrapper constraints */
body.kingmaker-canvas-active main,
body.kingmaker-canvas-active .site-main,
body.kingmaker-canvas-active .site-content,
body.kingmaker-canvas-active .content-area,
body.kingmaker-canvas-active .entry-content,
body.kingmaker-canvas-active article.post,
body.kingmaker-canvas-active .elementor-section-wrap,
body.kingmaker-canvas-active .elementor-section,
body.kingmaker-canvas-active .elementor-container,
body.kingmaker-canvas-active .e-con,
body.kingmaker-canvas-active .e-con-inner {
	max-width: none !important;
	width: 100% !important;
	padding: 0 !important;
	margin: 0 !important;
	overflow: visible !important;
}

body.kingmaker-canvas-active .kingmaker-canvas-wrapper {
	width: 100%;
	max-width: none;
	margin: 0;
	padding: 0;
	overflow: visible;
}

/* Strip theme table styling — let our Tailwind utilities define borders/padding */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper table,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper thead,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper tbody,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper tr,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper th,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper td {
	background: none !important;
	border: 0 !important;
	box-shadow: none !important;
}

/* Re-enable subtle borders that our Tailwind classes intend */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper tr.border-b {
	border-bottom: 1px solid var(--border, oklch(0.92 0.005 260)) !important;
}
body.kingmaker-canvas-active .kingmaker-canvas-wrapper tr.last\:border-0:last-child {
	border-bottom: 0 !important;
}

/* Strip theme list-style + reset margins/padding on lists inside the article */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper ul,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper ol {
	list-style: none;
	margin: 0;
	padding: 0;
}

/* Strip theme paragraph margins (Tailwind utility classes handle spacing) */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper p {
	margin: 0;
}

/* Strip theme heading margins/colors */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper h1,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper h2,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper h3,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper h4,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper h5,
body.kingmaker-canvas-active .kingmaker-canvas-wrapper h6 {
	margin: 0;
	color: inherit;
	font-weight: inherit;
}

/* Strip theme link styling — Tailwind utilities take over */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper a {
	text-decoration: none;
	color: inherit;
}

/* Strip theme button styling — our Tailwind .btn-* classes own the look */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper button {
	background: none;
	border: 0;
	padding: 0;
	font: inherit;
	cursor: pointer;
	color: inherit;
}

/* Strip theme img margins/border so our cards/thumbnails render clean */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper img {
	margin: 0;
	border: 0;
	max-width: 100%;
	height: auto;
}

/* FAQ accordion — animate open/closed via grid-template-rows */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper dd.grid {
	display: grid;
	transition: grid-template-rows 300ms ease-out, opacity 300ms ease-out, padding 300ms ease-out;
}

/* Sticky TOC visibility (controlled by .kse-toc-visible JS class) */
body.kingmaker-canvas-active .kingmaker-canvas-wrapper nav[aria-label="On this page"] {
	opacity: 0;
	pointer-events: none;
	transition: opacity 500ms;
}
body.kingmaker-canvas-active .kingmaker-canvas-wrapper nav[aria-label="On this page"].kse-toc-visible {
	opacity: 1;
	pointer-events: auto;
}
</style>
	<?php
}
add_action( 'wp_head', 'kse_canvas_inject_css', 999 );

/* ---------- JS for sticky TOC + FAQ accordion (wp_footer) ---------- */

function kse_canvas_inject_js() {
	global $post;
	if ( ! $post || get_page_template_slug( $post->ID ) !== KSE_CANVAS_SLUG ) {
		return;
	}
	?>
<script id="kse-canvas-behaviors">
(function () {
	if ( ! document.body.classList.contains('kingmaker-canvas-active') ) return;

	/* (Scroll progress bar deliberately omitted — host sites typically have their own
	   via Read Meter / similar plugins. Adding ours would create a duplicate bar.) */

	/* ---- Sticky TOC: show after user scrolls past TLDR ---- */
	function initStickyToc() {
		var tldr = document.getElementById('tldr');
		var nav = document.querySelector('nav[aria-label="On this page"]');
		if ( ! tldr || ! nav ) return;

		function updateVisibility() {
			var rect = tldr.getBoundingClientRect();
			if ( rect.bottom < 80 ) {
				nav.classList.add('kse-toc-visible');
			} else {
				nav.classList.remove('kse-toc-visible');
			}
		}

		updateVisibility();
		window.addEventListener('scroll', updateVisibility, { passive: true });
		window.addEventListener('resize', updateVisibility, { passive: true });

		/* ---- Active section highlighting in TOC ---- */
		var items = Array.prototype.slice.call(nav.querySelectorAll('[data-toc-href]'));
		if ( items.length === 0 ) return;

		function updateActive() {
			var currentHref = '';
			for ( var i = 0; i < items.length; i++ ) {
				var href = items[i].getAttribute('data-toc-href');
				var targetId = href.slice(1);
				var el = document.getElementById(targetId);
				if ( ! el ) continue;
				if ( el.getBoundingClientRect().top - 120 <= 0 ) {
					currentHref = href;
				}
			}
			items.forEach(function (li) {
				var a = li.querySelector('a');
				var dash = li.querySelector('a > span[aria-hidden]');
				var isActive = li.getAttribute('data-toc-href') === currentHref;
				if ( ! a ) return;
				if ( isActive ) {
					a.classList.add('text-foreground');
					a.classList.remove('text-muted-foreground/70');
					if ( dash ) {
						dash.classList.add('bg-foreground','w-5');
						dash.classList.remove('bg-muted-foreground/30','w-3');
					}
				} else {
					a.classList.remove('text-foreground');
					a.classList.add('text-muted-foreground/70');
					if ( dash ) {
						dash.classList.remove('bg-foreground','w-5');
						dash.classList.add('bg-muted-foreground/30','w-3');
					}
				}
			});
		}

		updateActive();
		window.addEventListener('scroll', updateActive, { passive: true });
	}

	/* ---- FAQ accordion: click to toggle, max 2 open at once ---- */
	function initFaqAccordion() {
		var faqSection = document.getElementById('faq');
		if ( ! faqSection ) return;
		var buttons = faqSection.querySelectorAll('button[aria-expanded]');
		if ( buttons.length === 0 ) return;

		var openOrder = []; /* tracks insertion order of currently-open buttons */

		function setOpen(btn, isOpen) {
			btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			var dt = btn.closest('dt');
			if ( ! dt ) return;
			var dd = dt.nextElementSibling;
			if ( ! dd || dd.tagName !== 'DD' ) return;
			var iconWrap = btn.querySelector('span[aria-hidden]');

			if ( isOpen ) {
				dd.classList.remove('grid-rows-[0fr]','opacity-0');
				dd.classList.add('grid-rows-[1fr]','opacity-100','pb-5');
				if ( iconWrap ) iconWrap.style.transform = 'rotate(45deg)';
			} else {
				dd.classList.remove('grid-rows-[1fr]','opacity-100','pb-5');
				dd.classList.add('grid-rows-[0fr]','opacity-0');
				if ( iconWrap ) iconWrap.style.transform = '';
			}
		}

		buttons.forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var idx = openOrder.indexOf(btn);
				if ( idx > -1 ) {
					setOpen(btn, false);
					openOrder.splice(idx, 1);
				} else {
					if ( openOrder.length >= 2 ) {
						var oldest = openOrder.shift();
						setOpen(oldest, false);
					}
					setOpen(btn, true);
					openOrder.push(btn);
				}
			});
		});
	}

	function init() {
		initStickyToc();
		initFaqAccordion();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
</script>
	<?php
}
add_action( 'wp_footer', 'kse_canvas_inject_js', 99 );

/* ---------- Template render: bypass theme single.php/page.php ---------- */

function kse_canvas_render() {
	if ( ! is_singular() ) {
		return;
	}
	global $post;
	if ( ! $post || get_page_template_slug( $post->ID ) !== KSE_CANVAS_SLUG ) {
		return;
	}
	get_header();
	?>
	<div class="kingmaker-canvas-wrapper">
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'kingmaker-canvas-article' ); ?>>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	</div>
	<?php
	get_footer();
	exit;
}
add_action( 'template_redirect', 'kse_canvas_render' );
