<?php
/**
 * Plugin Name: Kingmaker Canvas
 * Plugin URI:  https://github.com/Kingmaker-Search/kingmaker-canvas
 * Description: Registers a "Kingmaker Canvas" page template that injects scoped CSS + JS for custom-designed article HTML produced by Kingmaker Search's content-engine. Uses the_content filter (not template_redirect) so Elementor Theme Builder integration for header, sidebar, and footer is preserved. Strips theme content-wrapper constraints via the standard viewport-width break-out CSS pattern. Element resets are scoped to article body and wrapped in :where() for zero specificity so Tailwind utility classes always win.
 * Version:     1.1.1
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
$kse_canvas_update_checker->getVcsApi()->enableReleaseAssets();

/* ---------- Template registration (no render callback — theme + Elementor handle it) ---------- */

function kse_canvas_register( $templates ) {
	$templates[ KSE_CANVAS_SLUG ] = __( 'Kingmaker Canvas', 'kingmaker-canvas' );
	return $templates;
}
add_filter( 'theme_page_templates', 'kse_canvas_register' );
add_filter( 'theme_post_templates', 'kse_canvas_register' );

/* ---------- Helper: is the current request a canvas-template post? ---------- */

function kse_canvas_is_active() {
	if ( ! is_singular() ) {
		return false;
	}
	global $post;
	return $post && get_page_template_slug( $post->ID ) === KSE_CANVAS_SLUG;
}

/* ---------- Body class flag ---------- */

function kse_canvas_body_class( $classes ) {
	if ( kse_canvas_is_active() ) {
		$classes[] = 'kingmaker-canvas-active';
	}
	return $classes;
}
add_filter( 'body_class', 'kse_canvas_body_class' );

/* ---------- Wrap post content via the_content filter ---------- *
 * The theme template runs normally (single.php / Elementor Theme Builder).
 * We only wrap the inner post-content node in <div class="kse-article-body">.
 * Priority 99 = run after most other content filters so we wrap the final HTML.
 */

function kse_canvas_wrap_content( $content ) {
	if ( ! kse_canvas_is_active() ) {
		return $content;
	}
	if ( ! in_the_loop() ) {
		return $content;
	}
	return '<div class="kse-article-body">' . $content . '</div>';
}
add_filter( 'the_content', 'kse_canvas_wrap_content', 99 );

/* ---------- CSS injection (wp_head, priority 999 to win cascade order) ---------- */

function kse_canvas_inject_css() {
	if ( ! kse_canvas_is_active() ) {
		return;
	}
	?>
<style id="kse-canvas-styles">
/* ------------------------------------------------------------------
   Edge-to-edge break-out — article wrapper spans full viewport width
   regardless of any parent container's max-width. Standard
   "full-width content from inside a constrained container" pattern.
   ------------------------------------------------------------------ */
.kse-article-body {
	width: 100vw;
	position: relative;
	left: 50%;
	transform: translateX(-50%);
	overflow-x: hidden;
}

/* ------------------------------------------------------------------
   Font inheritance — override Tailwind's --font-sans/--font-mono so
   the article picks up the host site's typography instead of Inter.
   ------------------------------------------------------------------ */
.kse-article-body {
	--font-sans: inherit;
	--font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* ------------------------------------------------------------------
   Zero-specificity resets via :where(), scoped to article body only.
   The :where() pseudo-class makes these selectors specificity (0,0,0,1).
   Any Tailwind utility class on the same element wins (0,0,1,0).
   Tailwind v4, Open Props, and Andy Bell's CSS reset all use this pattern.
   ------------------------------------------------------------------ */
.kse-article-body :where(h1, h2, h3, h4, h5, h6) {
	margin: 0;
	color: inherit;
	font-weight: inherit;
	line-height: inherit;
}
.kse-article-body :where(p) {
	margin: 0;
}
.kse-article-body :where(ul, ol) {
	list-style: none;
	margin: 0;
	padding: 0;
}
.kse-article-body :where(a) {
	text-decoration: none;
	color: inherit;
}
.kse-article-body :where(img) {
	max-width: 100%;
	height: auto;
}

/* Tables — strip theme borders/backgrounds so our Tailwind utilities own the look */
.kse-article-body :where(table, thead, tbody, tr, th, td) {
	background: none;
	border: 0;
	box-shadow: none;
}
.kse-article-body :where(table) {
	border-collapse: collapse;
	width: 100%;
}

/* ------------------------------------------------------------------
   Deliberately NO :where(button) reset.
   Elementor's sidebar widgets ("Subscribe to TxtCart Relations" etc.)
   render outside .kse-article-body and keep their native styles.
   Article buttons inside .kse-article-body inherit visible Tailwind
   utilities from the post body's inline stylesheet.
   ------------------------------------------------------------------ */

/* ------------------------------------------------------------------
   FAQ accordion — animate open/closed via grid-template-rows.
   Targeted (not via :where) because this rule needs to win.
   ------------------------------------------------------------------ */
.kse-article-body dd.grid {
	display: grid;
	transition: grid-template-rows 300ms ease-out, opacity 300ms ease-out, padding 300ms ease-out;
}

/* ------------------------------------------------------------------
   Sticky TOC visibility — toggled by .kse-toc-visible JS class
   ------------------------------------------------------------------ */
.kse-article-body nav[aria-label="On this page"] {
	opacity: 0;
	pointer-events: none;
	transition: opacity 500ms;
}
.kse-article-body nav[aria-label="On this page"].kse-toc-visible {
	opacity: 1;
	pointer-events: auto;
}
</style>
	<?php
}
add_action( 'wp_head', 'kse_canvas_inject_css', 999 );

/* ---------- JS injection (wp_footer) ---------- */

function kse_canvas_inject_js() {
	if ( ! kse_canvas_is_active() ) {
		return;
	}
	?>
<script id="kse-canvas-behaviors">
(function () {
	var root = document.querySelector('.kse-article-body');
	if ( ! root ) return;

	/* ---- Sticky TOC: show after user scrolls past TLDR ---- */
	function initStickyToc() {
		var tldr = root.querySelector('#tldr');
		var nav = root.querySelector('nav[aria-label="On this page"]');
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

		/* Active section highlight in TOC */
		var items = Array.prototype.slice.call(nav.querySelectorAll('[data-toc-href]'));
		if ( items.length === 0 ) return;

		function updateActive() {
			var currentHref = '';
			for ( var i = 0; i < items.length; i++ ) {
				var href = items[i].getAttribute('data-toc-href');
				var targetId = href.slice(1);
				var el = root.querySelector('#' + targetId);
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

	/* ---- FAQ accordion: click toggles, max 2 open at once ---- */
	function initFaqAccordion() {
		var faqSection = root.querySelector('#faq');
		if ( ! faqSection ) return;
		var buttons = faqSection.querySelectorAll('button[aria-expanded]');
		if ( buttons.length === 0 ) return;

		var openOrder = [];

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
