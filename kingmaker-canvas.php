<?php
/**
 * Plugin Name: Kingmaker Canvas
 * Plugin URI:  https://github.com/Kingmaker-Search/kingmaker-canvas
 * Description: Registers a "Kingmaker Canvas" post template for custom-designed article HTML produced by Kingmaker Search's content-engine. Canvas posts render the site header and footer while bypassing the normal single-post template chrome between them.
 * Version:     1.2.2
 * Author:      Kingmaker Search
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'KSE_CANVAS_SLUG' ) ) {
	define( 'KSE_CANVAS_SLUG', 'kingmaker-canvas' );
}

if ( ! defined( 'KSE_CANVAS_TEMPLATE_PATH' ) ) {
	define( 'KSE_CANVAS_TEMPLATE_PATH', __DIR__ . '/templates/single-kingmaker-canvas.php' );
}

if ( ! defined( 'KSE_CANVAS_DEFAULT_NEWSLETTER_TEMPLATE_ID' ) ) {
	define( 'KSE_CANVAS_DEFAULT_NEWSLETTER_TEMPLATE_ID', 27302 );
}

if ( ! defined( 'KSE_CANVAS_PREVIEW_MARKER' ) ) {
	define( 'KSE_CANVAS_PREVIEW_MARKER', 'kse:canvas-preview' );
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

/* ---------- Template registration ---------- */

function kse_canvas_register( $templates ) {
	$templates[ KSE_CANVAS_SLUG ] = __( 'Kingmaker Canvas', 'kingmaker-canvas' );
	return $templates;
}
add_filter( 'theme_page_templates', 'kse_canvas_register' );
add_filter( 'theme_post_templates', 'kse_canvas_register' );

/* ---------- Preview helpers ---------- */

function kse_canvas_is_preview_request() {
	$preview = '';

	if ( isset( $_GET['preview'] ) && ! is_array( $_GET['preview'] ) ) {
		$preview = sanitize_text_field( wp_unslash( $_GET['preview'] ) );
	}

	return is_preview() || 'true' === $preview;
}

function kse_canvas_content_has_preview_marker( $content ) {
	return is_string( $content ) && false !== stripos( $content, KSE_CANVAS_PREVIEW_MARKER );
}

function kse_canvas_get_preview_autosave( $post_id ) {
	static $cache = array();

	$post_id = absint( $post_id );

	if ( $post_id <= 0 || ! kse_canvas_is_preview_request() ) {
		return false;
	}

	if ( array_key_exists( $post_id, $cache ) ) {
		return $cache[ $post_id ];
	}

	$autosave = false;
	$user_id  = get_current_user_id();

	if ( $user_id > 0 ) {
		$autosave = wp_get_post_autosave( $post_id, $user_id );
	}

	if ( ! $autosave ) {
		$autosave = wp_get_post_autosave( $post_id );
	}

	if ( ! $autosave ) {
		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled'  => false,
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		foreach ( $revisions as $revision ) {
			if ( false !== strpos( $revision->post_name, $post_id . '-autosave' ) ) {
				$autosave = $revision;
				break;
			}
		}
	}

	$cache[ $post_id ] = $autosave;
	return $autosave;
}

function kse_canvas_get_preview_autosave_content( $post_id ) {
	$autosave = kse_canvas_get_preview_autosave( $post_id );

	return ( $autosave && isset( $autosave->post_content ) ) ? (string) $autosave->post_content : '';
}

function kse_canvas_apply_preview_autosave_to_main_query( $posts, $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! kse_canvas_is_preview_request() || empty( $posts ) ) {
		return $posts;
	}

	$post_id  = isset( $posts[0]->ID ) ? absint( $posts[0]->ID ) : 0;
	$autosave = kse_canvas_get_preview_autosave( $post_id );

	if ( ! $autosave || ! kse_canvas_content_has_preview_marker( $autosave->post_content ) ) {
		return $posts;
	}

	$posts[0]->post_content = $autosave->post_content;

	if ( isset( $autosave->post_title ) && '' !== trim( $autosave->post_title ) ) {
		$posts[0]->post_title = $autosave->post_title;
	}

	return $posts;
}
add_filter( 'the_posts', 'kse_canvas_apply_preview_autosave_to_main_query', 10, 2 );

/* ---------- Helper: is the current request a canvas-template post? ---------- */

function kse_canvas_is_active() {
	if ( ! is_singular() ) {
		return false;
	}
	global $post;

	if ( ! $post ) {
		return false;
	}

	if ( get_page_template_slug( $post->ID ) === KSE_CANVAS_SLUG ) {
		return true;
	}

	return kse_canvas_content_has_preview_marker( kse_canvas_get_preview_autosave_content( $post->ID ) );
}

/* ---------- Full-page canvas template ---------- */

function kse_canvas_template_include( $template ) {
	if ( kse_canvas_is_active() && file_exists( KSE_CANVAS_TEMPLATE_PATH ) ) {
		return KSE_CANVAS_TEMPLATE_PATH;
	}

	return $template;
}
add_filter( 'template_include', 'kse_canvas_template_include', 1000 );

function kse_canvas_render_elementor_location( $location ) {
	if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $location ) ) {
		return true;
	}

	/**
	 * Allows a host site without Elementor Theme Builder to provide a header or
	 * footer fallback without reintroducing the normal single-post template.
	 */
	do_action( 'kse_canvas_missing_' . sanitize_key( $location ) . '_location' );

	return false;
}

/* ---------- Body class flag ---------- */

function kse_canvas_body_class( $classes ) {
	if ( kse_canvas_is_active() ) {
		$classes[] = 'kingmaker-canvas-active';
	}
	return $classes;
}
add_filter( 'body_class', 'kse_canvas_body_class' );

/* ---------- Newsletter form handoff ---------- */

function kse_canvas_get_newsletter_template_id() {
	return (int) apply_filters( 'kse_canvas_newsletter_template_id', KSE_CANVAS_DEFAULT_NEWSLETTER_TEMPLATE_ID );
}

function kse_canvas_get_elementor_template_html( $template_id ) {
	static $rendering_templates = array();

	$template_id = absint( $template_id );

	if ( $template_id <= 0 || isset( $rendering_templates[ $template_id ] ) ) {
		return '';
	}

	if ( $template_id <= 0 || ! did_action( 'elementor/loaded' ) || ! class_exists( 'Elementor\Plugin' ) ) {
		return '';
	}

	$elementor = \Elementor\Plugin::instance();

	if (
		! $elementor
		|| empty( $elementor->frontend )
		|| ! method_exists( $elementor->frontend, 'get_builder_content_for_display' )
	) {
		return '';
	}

	$rendering_templates[ $template_id ] = true;
	$html = $elementor->frontend->get_builder_content_for_display( $template_id, true );
	unset( $rendering_templates[ $template_id ] );

	if ( ! is_string( $html ) || '' === trim( $html ) ) {
		return '';
	}

	return $html;
}

function kse_canvas_get_newsletter_form_html() {
	$html = kse_canvas_get_elementor_template_html( kse_canvas_get_newsletter_template_id() );

	return '' === $html ? '' : '<div class="kse-canvas-newsletter-form">' . $html . '</div>';
}

function kse_canvas_replace_static_newsletter_form( $content ) {
	$form_html = kse_canvas_get_newsletter_form_html();

	if ( '' === $form_html ) {
		return $content;
	}

	$static_form_markup = '<input type="email" placeholder="Email" class="w-full h-10 rounded-md bg-white text-foreground px-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-[color:var(--highlight)]"/><button class="mt-3 w-full h-10 rounded-md bg-[color:var(--highlight)] text-white text-sm font-semibold hover:opacity-90 transition">Subscribe to Textual Relations</button>';

	if ( false === strpos( $content, $static_form_markup ) ) {
		return $content;
	}

	$updated = preg_replace(
		'/' . preg_quote( $static_form_markup, '/' ) . '/',
		$form_html,
		$content,
		1
	);

	return is_string( $updated ) ? $updated : $content;
}

/* ---------- Optional pre-footer Elementor CTA ---------- */

function kse_canvas_get_control_comment_source() {
	global $post;

	if ( ! $post ) {
		return '';
	}

	$preview_content = kse_canvas_get_preview_autosave_content( $post->ID );

	if ( '' !== $preview_content ) {
		return $preview_content;
	}

	return (string) $post->post_content;
}

function kse_canvas_get_after_article_template_id() {
	$template_id = 0;
	$content     = kse_canvas_get_control_comment_source();

	if ( preg_match( '/<!--\s*kse:after-article-template\s*[:=]\s*(none|false|0|\d+)\s*-->/i', $content, $matches ) ) {
		$value       = strtolower( $matches[1] );
		$template_id = is_numeric( $value ) ? absint( $value ) : 0;
	}

	return (int) apply_filters( 'kse_canvas_after_article_template_id', $template_id );
}

function kse_canvas_render_after_article_template() {
	$template_id = kse_canvas_get_after_article_template_id();

	if ( $template_id <= 0 ) {
		return false;
	}

	$html = kse_canvas_get_elementor_template_html( $template_id );

	if ( '' === $html ) {
		return false;
	}

	echo '<section class="kse-canvas-after-article" data-kse-template-id="' . esc_attr( $template_id ) . '">' . $html . '</section>';
	return true;
}

function kse_canvas_strip_control_comments( $content ) {
	$content = preg_replace( '/<!--\s*kse:canvas-preview\s*-->/i', '', $content );
	$content = preg_replace( '/<!--\s*kse:after-article-template\s*[:=]\s*(?:none|false|0|\d+)\s*-->/i', '', $content );

	return is_string( $content ) ? $content : '';
}

/* ---------- Wrap post content via the_content filter ---------- *
 * The plugin-owned template bypasses the normal single-post layout.
 * This filter wraps only the designed article HTML in <div class="kse-article-body">.
 * Priority 99 = run after most other content filters so we wrap the final HTML.
 */

function kse_canvas_wrap_content( $content ) {
	if ( ! kse_canvas_is_active() ) {
		return $content;
	}
	if ( ! in_the_loop() ) {
		return $content;
	}
	$content = kse_canvas_replace_static_newsletter_form( $content );
	$content = kse_canvas_strip_control_comments( $content );
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
   Canvas shell — the plugin template already owns the full area between
   header and footer, so no transform-based viewport breakout is needed.
   ------------------------------------------------------------------ */
body.kingmaker-canvas-active {
	overflow-x: clip;
}
.kse-canvas-page,
.kse-canvas-after-article,
.kse-article-body {
	width: 100%;
	margin: 0;
	padding: 0;
	position: relative;
}
.kse-article-body main > footer.border-t.border-border {
	display: none;
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
.kse-article-body :where(button) {
	font: inherit;
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

/* Higher-specificity article fixes for host-site global link/button rules. */
body.kingmaker-canvas-active .kse-article-body .text-white,
body.kingmaker-canvas-active .kse-article-body a.text-white,
body.kingmaker-canvas-active .kse-article-body button.text-white {
	color: var(--color-white, #fff);
}
body.kingmaker-canvas-active .kse-article-body a[class~="bg-[color:var(--highlight)]"],
body.kingmaker-canvas-active .kse-article-body button[class~="bg-[color:var(--highlight)]"] {
	background-color: var(--highlight);
}
body.kingmaker-canvas-active .kse-article-body a[class~="text-[color:var(--highlight)]"] {
	color: var(--highlight);
}
body.kingmaker-canvas-active .kse-article-body .text-foreground,
body.kingmaker-canvas-active .kse-article-body [class~="hover:text-foreground"]:hover {
	color: var(--foreground) !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-foreground/80"] {
	color: color-mix(in oklab, var(--foreground) 80%, transparent) !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-foreground/85"] {
	color: color-mix(in oklab, var(--foreground) 85%, transparent) !important;
}
body.kingmaker-canvas-active .kse-article-body .text-muted-foreground {
	color: var(--muted-foreground) !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-muted-foreground/60"] {
	color: color-mix(in oklab, var(--muted-foreground) 60%, transparent) !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-muted-foreground/70"] {
	color: color-mix(in oklab, var(--muted-foreground) 70%, transparent) !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-white/60"] {
	color: color-mix(in oklab, var(--color-white, #fff) 60%, transparent) !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-amber-800"] {
	color: #92400e !important;
}

/* ------------------------------------------------------------------
   FAQ accordion — animate open/closed via grid-template-rows.
   Targeted (not via :where) because this rule needs to win.
   ------------------------------------------------------------------ */
body.kingmaker-canvas-active .kse-article-body #faq button[aria-expanded] {
	background: transparent;
	border: 0;
	border-radius: 0;
	box-shadow: none;
	color: inherit;
	font: inherit;
	padding: 1rem 0;
	text-align: left;
	width: 100%;
}
body.kingmaker-canvas-active .kse-article-body #faq button[aria-expanded] > span:first-child {
	color: var(--foreground);
}
body.kingmaker-canvas-active .kse-article-body #faq button[aria-expanded]:hover > span:first-child {
	color: var(--highlight);
}
.kse-article-body dd.grid {
	display: grid;
	transition: grid-template-rows 300ms ease-out, opacity 300ms ease-out, padding 300ms ease-out;
}

/* ------------------------------------------------------------------
   Newsletter form — use the real Elementor form backend inside the
   Lovable hero card, while removing Elementor sidebar/template spacing.
   ------------------------------------------------------------------ */
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form,
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form .elementor,
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form .elementor-template,
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form .elementor-widget-container {
	margin: 0;
	padding: 0;
	width: 100%;
}
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form .elementor-form-fields-wrapper {
	display: block;
	margin: 0 !important;
}
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form .elementor-field-group {
	margin: 0 0 0.75rem !important;
	padding: 0 !important;
	width: 100%;
}
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form .elementor-field-group:last-child {
	margin-bottom: 0 !important;
}
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form input.elementor-field {
	background: #fff !important;
	border: 0 !important;
	border-radius: 0.375rem !important;
	box-shadow: none !important;
	color: var(--foreground) !important;
	font-size: 0.875rem !important;
	height: 2.5rem;
	line-height: 1.25rem;
	padding: 0 0.75rem !important;
	width: 100%;
}
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form button.elementor-button {
	align-items: center;
	background: var(--highlight) !important;
	border: 0 !important;
	border-radius: 0.375rem !important;
	box-shadow: none !important;
	color: #fff !important;
	display: inline-flex;
	font-size: 0.875rem !important;
	font-weight: 600 !important;
	height: 2.5rem;
	justify-content: center;
	line-height: 1.25rem;
	padding: 0.5rem 1rem !important;
	width: 100%;
}
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form button.elementor-button .elementor-button-text {
	color: #fff !important;
}
body.kingmaker-canvas-active .kse-article-body .kse-canvas-newsletter-form .elementor-message {
	font-size: 0.8125rem;
	margin: 0.5rem 0 0;
}

/* ------------------------------------------------------------------
   TLDR table — restore the Lovable table against Hello Elementor's
   default striped table backgrounds and cell borders.
   ------------------------------------------------------------------ */
body.kingmaker-canvas-active .kse-article-body #tldr table {
	background: transparent !important;
	border: 0 !important;
	border-collapse: collapse !important;
	border-spacing: 0 !important;
	font-size: 0.875rem !important;
	margin: 0 !important;
	width: 100% !important;
}
body.kingmaker-canvas-active .kse-article-body #tldr table thead,
body.kingmaker-canvas-active .kse-article-body #tldr table tbody,
body.kingmaker-canvas-active .kse-article-body #tldr table tr,
body.kingmaker-canvas-active .kse-article-body #tldr table th,
body.kingmaker-canvas-active .kse-article-body #tldr table td,
body.kingmaker-canvas-active .kse-article-body #tldr table tbody > tr:nth-child(odd) > td,
body.kingmaker-canvas-active .kse-article-body #tldr table tbody > tr:nth-child(odd) > th,
body.kingmaker-canvas-active .kse-article-body #tldr table tbody tr:hover > td,
body.kingmaker-canvas-active .kse-article-body #tldr table tbody tr:hover > th {
	background: transparent !important;
	box-shadow: none !important;
}
body.kingmaker-canvas-active .kse-article-body #tldr table th,
body.kingmaker-canvas-active .kse-article-body #tldr table td {
	border: 0 !important;
	line-height: 1.5 !important;
	padding-bottom: 0.75rem !important;
	padding-left: 0 !important;
	padding-top: 0.75rem !important;
	vertical-align: top !important;
}
body.kingmaker-canvas-active .kse-article-body #tldr table th:not(:last-child),
body.kingmaker-canvas-active .kse-article-body #tldr table td:not(:last-child) {
	padding-right: 1rem !important;
}
body.kingmaker-canvas-active .kse-article-body #tldr table tr {
	border-color: var(--border) !important;
}
body.kingmaker-canvas-active .kse-article-body #tldr table tbody tr {
	border-bottom-color: color-mix(in oklab, var(--border) 60%, transparent) !important;
}

/* ------------------------------------------------------------------
   Sticky TOC visibility — toggled by .kse-toc-visible JS class
   ------------------------------------------------------------------ */
.kse-article-body nav[aria-label="On this page"] {
	opacity: 0;
	pointer-events: none;
	transition: opacity 500ms;
	top: 9.5rem !important;
	max-height: calc(100vh - 11rem) !important;
	max-width: 16rem;
}
.kse-article-body nav[aria-label="On this page"].kse-toc-visible {
	opacity: 1;
	pointer-events: auto;
}
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] > div {
	color: color-mix(in oklab, var(--muted-foreground) 60%, transparent) !important;
	font-size: 0.6875rem;
	letter-spacing: 0.18em;
	margin-bottom: 1.15rem !important;
}
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] ul {
	font-size: 0.84375rem !important;
	line-height: 1.45 !important;
	max-height: 68vh !important;
}
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] li + li {
	margin-top: 0.82rem !important;
}
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] a {
	color: color-mix(in oklab, var(--muted-foreground) 70%, transparent) !important;
	font-weight: 400;
}
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] a.text-foreground,
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] a:hover {
	color: var(--foreground) !important;
}
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] a > span[aria-hidden] {
	background-color: color-mix(in oklab, var(--muted-foreground) 30%, transparent) !important;
}
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] a.text-foreground > span[aria-hidden],
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] a:hover > span[aria-hidden] {
	background-color: var(--foreground) !important;
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
