<?php
/**
 * Plugin Name: Kingmaker Canvas
 * Plugin URI:  https://github.com/Kingmaker-Search/kingmaker-canvas
 * Description: Registers a "Kingmaker Canvas" post template for custom-designed article HTML produced by Kingmaker Search's content-engine. Canvas posts render the site header and footer while bypassing the normal single-post template chrome between them.
 * Version:     1.2.8
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
	define( 'KSE_CANVAS_DEFAULT_NEWSLETTER_TEMPLATE_ID', 0 );
}

if ( ! defined( 'KSE_CANVAS_PREVIEW_MARKER' ) ) {
	define( 'KSE_CANVAS_PREVIEW_MARKER', 'kse:canvas-preview' );
}

if ( ! defined( 'KSE_CANVAS_SETTINGS_OPTION' ) ) {
	define( 'KSE_CANVAS_SETTINGS_OPTION', 'kse_canvas_settings' );
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

/* ---------- Per-site settings ---------- */

function kse_canvas_parse_template_id_list( $value ) {
	if ( is_string( $value ) ) {
		$value = preg_split( '/[\s,]+/', $value );
	}

	if ( ! is_array( $value ) ) {
		return array();
	}

	$ids = array();

	foreach ( $value as $id ) {
		$id = absint( $id );

		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}

	return array_values( array_unique( $ids ) );
}

function kse_canvas_default_settings() {
	$allowed_after_article_template_ids = array();

	if ( defined( 'KSE_CANVAS_ALLOWED_AFTER_ARTICLE_TEMPLATE_IDS' ) ) {
		$allowed_after_article_template_ids = kse_canvas_parse_template_id_list( KSE_CANVAS_ALLOWED_AFTER_ARTICLE_TEMPLATE_IDS );
	}

	return array(
		'newsletter_template_id'              => absint( KSE_CANVAS_DEFAULT_NEWSLETTER_TEMPLATE_ID ),
		'allowed_after_article_template_ids' => $allowed_after_article_template_ids,
	);
}

function kse_canvas_sanitize_settings( $input ) {
	$defaults = kse_canvas_default_settings();

	if ( ! is_array( $input ) ) {
		return $defaults;
	}

	return array(
		'newsletter_template_id'              => isset( $input['newsletter_template_id'] ) ? absint( $input['newsletter_template_id'] ) : $defaults['newsletter_template_id'],
		'allowed_after_article_template_ids' => isset( $input['allowed_after_article_template_ids'] ) ? kse_canvas_parse_template_id_list( $input['allowed_after_article_template_ids'] ) : $defaults['allowed_after_article_template_ids'],
	);
}

function kse_canvas_get_settings() {
	$settings = get_option( KSE_CANVAS_SETTINGS_OPTION, array() );
	$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), kse_canvas_default_settings() );
	$settings = kse_canvas_sanitize_settings( $settings );

	return apply_filters( 'kse_canvas_settings', $settings );
}

function kse_canvas_register_settings() {
	register_setting(
		'kse_canvas_settings',
		KSE_CANVAS_SETTINGS_OPTION,
		array(
			'type'              => 'object',
			'sanitize_callback' => 'kse_canvas_sanitize_settings',
			'default'           => kse_canvas_default_settings(),
			'show_in_rest'      => array(
				'schema' => array(
					'type'       => 'object',
					'properties' => array(
						'newsletter_template_id'              => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'allowed_after_article_template_ids' => array(
							'type'    => 'array',
							'items'   => array(
								'type' => 'integer',
							),
							'default' => array(),
						),
					),
				),
			),
		)
	);
}
add_action( 'admin_init', 'kse_canvas_register_settings' );
add_action( 'rest_api_init', 'kse_canvas_register_settings' );

function kse_canvas_add_settings_page() {
	add_options_page(
		__( 'Kingmaker Canvas', 'kingmaker-canvas' ),
		__( 'Kingmaker Canvas', 'kingmaker-canvas' ),
		'manage_options',
		'kingmaker-canvas',
		'kse_canvas_render_settings_page'
	);
}
add_action( 'admin_menu', 'kse_canvas_add_settings_page' );

function kse_canvas_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = kse_canvas_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Kingmaker Canvas', 'kingmaker-canvas' ); ?></h1>
		<p><?php esc_html_e( 'Configure site-specific Elementor templates used by canvas articles. Leave fields blank to disable that feature on this site.', 'kingmaker-canvas' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'kse_canvas_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="kse_canvas_newsletter_template_id"><?php esc_html_e( 'Newsletter template ID', 'kingmaker-canvas' ); ?></label>
					</th>
					<td>
						<input
							name="<?php echo esc_attr( KSE_CANVAS_SETTINGS_OPTION ); ?>[newsletter_template_id]"
							id="kse_canvas_newsletter_template_id"
							type="number"
							min="0"
							step="1"
							value="<?php echo esc_attr( $settings['newsletter_template_id'] ); ?>"
							class="regular-text"
						/>
						<p class="description"><?php esc_html_e( 'Elementor template ID to replace the static newsletter form inside article HTML.', 'kingmaker-canvas' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="kse_canvas_after_article_template_ids"><?php esc_html_e( 'Allowed pre-footer CTA template IDs', 'kingmaker-canvas' ); ?></label>
					</th>
					<td>
						<input
							name="<?php echo esc_attr( KSE_CANVAS_SETTINGS_OPTION ); ?>[allowed_after_article_template_ids]"
							id="kse_canvas_after_article_template_ids"
							type="text"
							value="<?php echo esc_attr( implode( ', ', $settings['allowed_after_article_template_ids'] ) ); ?>"
							class="regular-text"
						/>
						<p class="description"><?php esc_html_e( 'Comma-separated Elementor template IDs that article markers are allowed to render above the footer.', 'kingmaker-canvas' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

function kse_canvas_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=kingmaker-canvas' ) ),
		esc_html__( 'Settings', 'kingmaker-canvas' )
	);

	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'kse_canvas_plugin_action_links' );

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
	$settings = kse_canvas_get_settings();

	return (int) apply_filters( 'kse_canvas_newsletter_template_id', $settings['newsletter_template_id'] );
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
	$pattern   = '/<!--\s*kse:newsletter-form\s*-->.*?<!--\s*\/kse:newsletter-form\s*-->/is';

	if ( preg_match( $pattern, $content ) ) {
		$updated = preg_replace( $pattern, $form_html, $content, 1 );

		return is_string( $updated ) ? $updated : $content;
	}

	if ( false !== stripos( $content, '<!-- kse:newsletter-form -->' ) ) {
		$updated = preg_replace( '/<!--\s*kse:newsletter-form\s*-->/i', $form_html, $content, 1 );

		return is_string( $updated ) ? $updated : $content;
	}

	return $content;
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

	$template_id = (int) apply_filters( 'kse_canvas_after_article_template_id', $template_id );

	if ( $template_id <= 0 ) {
		return 0;
	}

	return kse_canvas_is_after_article_template_allowed( $template_id ) ? $template_id : 0;
}

function kse_canvas_get_allowed_after_article_template_ids() {
	$settings = kse_canvas_get_settings();
	$ids      = isset( $settings['allowed_after_article_template_ids'] ) ? $settings['allowed_after_article_template_ids'] : array();
	$ids      = kse_canvas_parse_template_id_list( $ids );

	return apply_filters( 'kse_canvas_allowed_after_article_template_ids', $ids );
}

function kse_canvas_is_after_article_template_allowed( $template_id ) {
	$template_id = absint( $template_id );

	if ( $template_id <= 0 ) {
		return false;
	}

	return in_array( $template_id, kse_canvas_get_allowed_after_article_template_ids(), true );
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
	$content = preg_replace( '/<!--\s*kse:newsletter-form\s*-->/i', '', $content );
	$content = preg_replace( '/<!--\s*\/kse:newsletter-form\s*-->/i', '', $content );
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
   Low-priority article reset.
   Lovable/Tailwind v4 emits utilities inside CSS cascade layers. Broad,
   unlayered plugin resets beat those utilities, which removes bullets,
   margins, padding, and flex behavior. Keep broad resets in a named layer
   so the article's own base/components/utilities can win.
   ------------------------------------------------------------------ */
@layer kse-canvas-reset {
	:where(.kse-article-body) :where(h1, h2, h3, h4, h5, h6) {
		margin: 0;
		color: inherit;
		font-weight: inherit;
		line-height: inherit;
	}
	:where(.kse-article-body) :where(p) {
		margin: 0;
	}
	:where(.kse-article-body) :where(ul, ol) {
		list-style: none;
		margin: 0;
		padding: 0;
	}
	:where(.kse-article-body) :where(a) {
		text-decoration: none;
		color: inherit;
	}
	:where(.kse-article-body) :where(button) {
		font: inherit;
	}
	:where(.kse-article-body) :where(img) {
		max-width: 100%;
		height: auto;
	}
	:where(.kse-article-body) :where(table, thead, tbody, tr, th, td) {
		background: none;
		border: 0;
		box-shadow: none;
	}
	:where(.kse-article-body) :where(table) {
		border-collapse: collapse;
		width: 100%;
	}
}

/* Higher-specificity article fixes for host-site global link/button rules. */
body.kingmaker-canvas-active .kse-article-body,
body.kingmaker-canvas-active .kse-article-body .font-sans,
body.kingmaker-canvas-active .kse-article-body .font-display {
	font-family: inherit !important;
}
body.kingmaker-canvas-active .kse-article-body .font-mono {
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
}
body.kingmaker-canvas-active .kse-article-body a {
	color: inherit;
	text-decoration: none;
}
body.kingmaker-canvas-active .kse-article-body .prose-body a,
body.kingmaker-canvas-active .kse-article-body a.underline,
body.kingmaker-canvas-active .kse-article-body [class~="hover:underline"]:hover {
	text-decoration-line: underline;
	text-underline-offset: 0.25rem;
}
body.kingmaker-canvas-active .kse-article-body .list-disc {
	list-style-type: disc !important;
}
body.kingmaker-canvas-active .kse-article-body .list-decimal {
	list-style-type: decimal !important;
}
body.kingmaker-canvas-active .kse-article-body .list-none {
	list-style-type: none !important;
}
body.kingmaker-canvas-active .kse-article-body .pl-5 {
	padding-left: 1.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-1 {
	margin-top: 0.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="mt-1.5"] {
	margin-top: 0.375rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-2 {
	margin-top: 0.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-3 {
	margin-top: 0.75rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-4 {
	margin-top: 1rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-5 {
	margin-top: 1.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-6 {
	margin-top: 1.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-7 {
	margin-top: 1.75rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-8 {
	margin-top: 2rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-10 {
	margin-top: 2.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-12 {
	margin-top: 3rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-16 {
	margin-top: 4rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mt-20 {
	margin-top: 5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mb-2 {
	margin-bottom: 0.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mb-3 {
	margin-bottom: 0.75rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mb-4 {
	margin-bottom: 1rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mb-5 {
	margin-bottom: 1.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body .mb-6 {
	margin-bottom: 1.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .space-y-2 > :not(:last-child) {
	margin-bottom: 0.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="space-y-2.5"] > :not(:last-child) {
	margin-bottom: 0.625rem !important;
}
body.kingmaker-canvas-active .kse-article-body .space-y-3 > :not(:last-child) {
	margin-bottom: 0.75rem !important;
}
body.kingmaker-canvas-active .kse-article-body .space-y-4 > :not(:last-child) {
	margin-bottom: 1rem !important;
}
body.kingmaker-canvas-active .kse-article-body .space-y-5 > :not(:last-child) {
	margin-bottom: 1.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body .space-y-7 > :not(:last-child) {
	margin-bottom: 1.75rem !important;
}
body.kingmaker-canvas-active .kse-article-body .space-y-24 > :not(:last-child) {
	margin-bottom: 6rem !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="space-y-[1.15em]"] > :not(:last-child) {
	margin-bottom: 1.15em !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="space-y-[10px]"] > :not(:last-child) {
	margin-bottom: 10px !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="[&>p]:m-0"] > p {
	margin: 0 !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="[&>p+p]:mt-5"] > p + p {
	margin-top: 1.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body .flex {
	display: flex !important;
}
body.kingmaker-canvas-active .kse-article-body .inline-flex {
	display: inline-flex !important;
}
body.kingmaker-canvas-active .kse-article-body .grid {
	display: grid !important;
}
body.kingmaker-canvas-active .kse-article-body .block {
	display: block !important;
}
body.kingmaker-canvas-active .kse-article-body .table-cell {
	display: table-cell !important;
}
body.kingmaker-canvas-active .kse-article-body .hidden {
	display: none !important;
}
body.kingmaker-canvas-active .kse-article-body .flex-col {
	flex-direction: column !important;
}
body.kingmaker-canvas-active .kse-article-body .flex-wrap {
	flex-wrap: wrap !important;
}
body.kingmaker-canvas-active .kse-article-body .items-start {
	align-items: flex-start !important;
}
body.kingmaker-canvas-active .kse-article-body .items-center {
	align-items: center !important;
}
body.kingmaker-canvas-active .kse-article-body .items-baseline {
	align-items: baseline !important;
}
body.kingmaker-canvas-active .kse-article-body .justify-between {
	justify-content: space-between !important;
}
body.kingmaker-canvas-active .kse-article-body .justify-center {
	justify-content: center !important;
}
body.kingmaker-canvas-active .kse-article-body .gap-2 {
	gap: 0.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="gap-2.5"] {
	gap: 0.625rem !important;
}
body.kingmaker-canvas-active .kse-article-body .gap-3 {
	gap: 0.75rem !important;
}
body.kingmaker-canvas-active .kse-article-body .gap-4 {
	gap: 1rem !important;
}
body.kingmaker-canvas-active .kse-article-body .gap-5 {
	gap: 1.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body .gap-6 {
	gap: 1.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .gap-8 {
	gap: 2rem !important;
}
body.kingmaker-canvas-active .kse-article-body .gap-x-12 {
	column-gap: 3rem !important;
}
body.kingmaker-canvas-active .kse-article-body .gap-x-16 {
	column-gap: 4rem !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="gap-y-1.5"] {
	row-gap: 0.375rem !important;
}
body.kingmaker-canvas-active .kse-article-body .gap-y-10 {
	row-gap: 2.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .text-xs {
	font-size: 0.75rem !important;
	line-height: 1rem !important;
}
body.kingmaker-canvas-active .kse-article-body .text-sm {
	font-size: 0.875rem !important;
	line-height: 1.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body .text-base {
	font-size: 1rem !important;
	line-height: 1.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .text-lg {
	font-size: 1.125rem !important;
	line-height: 1.75rem !important;
}
body.kingmaker-canvas-active .kse-article-body .text-xl {
	font-size: 1.25rem !important;
	line-height: 1.75rem !important;
}
body.kingmaker-canvas-active .kse-article-body .text-2xl {
	font-size: 1.5rem !important;
	line-height: 2rem !important;
}
body.kingmaker-canvas-active .kse-article-body .text-3xl {
	font-size: 1.875rem !important;
	line-height: 1.2 !important;
}
body.kingmaker-canvas-active .kse-article-body .text-4xl {
	font-size: 2.25rem !important;
	line-height: 2.5rem !important;
}
body.kingmaker-canvas-active .kse-article-body .text-5xl {
	font-size: 3rem !important;
	line-height: 1 !important;
}
body.kingmaker-canvas-active .kse-article-body .text-6xl {
	font-size: 3.75rem !important;
	line-height: 1 !important;
}
body.kingmaker-canvas-active .kse-article-body .text-8xl {
	font-size: 6rem !important;
	line-height: 1 !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-[10px]"] {
	font-size: 10px !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-[11px]"] {
	font-size: 11px !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-[12.5px]"] {
	font-size: 12.5px !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-[13px]"] {
	font-size: 13px !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-[14px]"] {
	font-size: 14px !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-[15px]"] {
	font-size: 15px !important;
}
body.kingmaker-canvas-active .kse-article-body [class~="text-[120px]"] {
	font-size: 120px !important;
}
body.kingmaker-canvas-active .kse-article-body .font-medium {
	font-weight: 500 !important;
}
body.kingmaker-canvas-active .kse-article-body .font-semibold {
	font-weight: 600 !important;
}
body.kingmaker-canvas-active .kse-article-body .font-bold {
	font-weight: 700 !important;
}
body.kingmaker-canvas-active .kse-article-body .font-extrabold {
	font-weight: 800 !important;
}
body.kingmaker-canvas-active .kse-article-body .leading-none {
	line-height: 1 !important;
}
body.kingmaker-canvas-active .kse-article-body .leading-tight {
	line-height: 1.25 !important;
}
body.kingmaker-canvas-active .kse-article-body .leading-snug {
	line-height: 1.375 !important;
}
body.kingmaker-canvas-active .kse-article-body .leading-relaxed {
	line-height: 1.625 !important;
}
body.kingmaker-canvas-active .kse-article-body .display-xl {
	font-size: clamp(2.4rem, 5.5vw, 4rem);
	font-weight: 800;
	line-height: 1.02;
}
body.kingmaker-canvas-active .kse-article-body .display-lg {
	font-size: clamp(1.75rem, 3.2vw, 2.5rem);
	font-weight: 800;
	line-height: 1.1;
}
body.kingmaker-canvas-active .kse-article-body .eyebrow {
	color: var(--muted-foreground);
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
	font-size: 0.7rem;
	letter-spacing: 0.18em;
	text-transform: uppercase;
}
body.kingmaker-canvas-active .kse-article-body .prose-body {
	color: color-mix(in oklab, var(--ink, var(--foreground)) 85%, transparent);
	font-size: 1.0625rem;
	line-height: 1.75;
}
body.kingmaker-canvas-active .kse-article-body .prose-body a {
	color: var(--highlight);
	text-decoration-color: color-mix(in oklab, var(--highlight) 40%, transparent);
	text-decoration-thickness: 1px;
}
body.kingmaker-canvas-active .kse-article-body .prose-body a:hover {
	text-decoration-color: var(--highlight);
}
body.kingmaker-canvas-active .kse-article-body .num-rank {
	color: var(--highlight);
	font-size: clamp(3rem, 6vw, 4.5rem);
	font-variant-numeric: tabular-nums;
	font-weight: 800;
	line-height: 0.85;
}
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
	align-items: flex-start;
	background: transparent;
	border: 0;
	border-radius: 0;
	box-shadow: none;
	color: inherit;
	display: flex;
	font: inherit;
	gap: 1.5rem;
	justify-content: space-between;
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
body.kingmaker-canvas-active .kse-article-body #faq button[aria-expanded] > span[aria-hidden] {
	align-items: center;
	display: flex;
	flex-shrink: 0;
	height: 1.5rem;
	justify-content: center;
	margin-top: 0.25rem;
	width: 1.5rem;
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
	display: none !important;
	opacity: 0;
	pointer-events: none;
	transition: opacity 500ms;
	top: 9.5rem !important;
	max-height: calc(100vh - 11rem) !important;
	max-width: 16rem;
	visibility: hidden;
}
.kse-article-body nav[aria-label="On this page"].kse-toc-visible {
	opacity: 1 !important;
	pointer-events: auto !important;
	visibility: visible !important;
}
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] > div:first-child {
	color: color-mix(in oklab, var(--muted-foreground) 60%, transparent) !important;
	font-size: 0.6875rem;
	letter-spacing: 0.18em;
	margin-bottom: 1.15rem !important;
}
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] ul {
	font-size: 0.84375rem !important;
	line-height: 1.45 !important;
	max-height: min(54vh, 30rem) !important;
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
body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"] > div:last-child {
	border-top: 1px solid var(--border) !important;
	display: block !important;
	margin-top: 1.25rem !important;
	padding-top: 1.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body .fixed[class~="bottom-5"][class~="left-1/2"] > div {
	align-items: center !important;
	display: flex !important;
	gap: 1rem !important;
	justify-content: space-between !important;
	min-height: 3.25rem;
	padding: 0.75rem 1.25rem !important;
}
body.kingmaker-canvas-active .kse-article-body .fixed[class~="bottom-5"][class~="left-1/2"] p {
	line-height: 1.35 !important;
	margin: 0 !important;
}
body.kingmaker-canvas-active .kse-article-body .fixed[class~="bottom-5"][class~="left-1/2"] a {
	color: var(--color-white, #fff) !important;
	text-decoration: none !important;
	white-space: nowrap;
}
@media (max-width: 640px) {
	body.kingmaker-canvas-active .kse-article-body .fixed[class~="bottom-5"][class~="left-1/2"] > div {
		flex-wrap: wrap;
		justify-content: center !important;
		text-align: center;
	}
}
@media (min-width: 640px) {
	body.kingmaker-canvas-active .kse-article-body [class~="sm:inline-flex"] {
		display: inline-flex !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="sm:text-lg"] {
		font-size: 1.125rem !important;
		line-height: 1.75rem !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="sm:text-3xl"] {
		font-size: 1.875rem !important;
		line-height: 1.2 !important;
	}
}
@media (min-width: 768px) {
	body.kingmaker-canvas-active .kse-article-body [class~="md:text-xl"] {
		font-size: 1.25rem !important;
		line-height: 1.75rem !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="md:text-2xl"] {
		font-size: 1.5rem !important;
		line-height: 2rem !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="md:text-3xl"] {
		font-size: 1.875rem !important;
		line-height: 1.2 !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="md:text-4xl"] {
		font-size: 2.25rem !important;
		line-height: 2.5rem !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="md:grid-cols-2"] {
		grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="md:grid-cols-3"] {
		grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]"] {
		grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr) !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="md:col-span-2"] {
		grid-column: span 2 / span 2 !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="md:table-cell"],
	body.kingmaker-canvas-active .kse-article-body [class~="md:!table-cell"] {
		display: table-cell !important;
	}
}
@media (min-width: 1024px) {
	body.kingmaker-canvas-active .kse-article-body [class~="lg:grid-cols-[minmax(0,calc(100%-352px))_minmax(0,1fr)]"] {
		grid-template-columns: minmax(0, calc(100% - 352px)) minmax(0, 1fr) !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="lg:gap-0"] {
		gap: 0 !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="lg:block"] {
		display: block !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="lg:flex"] {
		display: flex !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="lg:grid"] {
		display: grid !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="lg:table-cell"],
	body.kingmaker-canvas-active .kse-article-body [class~="lg:!table-cell"] {
		display: table-cell !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="lg:grid-cols-3"] {
		grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
	}
	body.kingmaker-canvas-active .kse-article-body [class~="lg:pl-16"] {
		padding-left: 4rem !important;
	}
	body.kingmaker-canvas-active .kse-article-body nav[aria-label="On this page"].kse-toc-visible {
		display: flex !important;
	}
}
@media (min-width: 1280px) {
	body.kingmaker-canvas-active .kse-article-body [class~="xl:pl-24"] {
		padding-left: 6rem !important;
	}
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
		var showAfterY = 0;

		function recalcThreshold() {
			var rect = tldr.getBoundingClientRect();
			var threshold = Math.min(160, window.innerHeight * 0.25);
			showAfterY = rect.top + window.pageYOffset + tldr.offsetHeight - threshold;
		}

		function updateVisibility() {
			if ( window.pageYOffset >= showAfterY ) {
				nav.classList.add('kse-toc-visible');
				nav.classList.remove('opacity-0','pointer-events-none');
			} else {
				nav.classList.remove('kse-toc-visible');
			}
		}
		recalcThreshold();
		updateVisibility();
		window.addEventListener('scroll', updateVisibility, { passive: true });
		window.addEventListener('resize', function () {
			recalcThreshold();
			updateVisibility();
		}, { passive: true });
		window.addEventListener('load', function () {
			recalcThreshold();
			updateVisibility();
		}, { passive: true });
		window.setTimeout(function () {
			recalcThreshold();
			updateVisibility();
		}, 250);
		window.setTimeout(function () {
			recalcThreshold();
			updateVisibility();
		}, 1000);

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
