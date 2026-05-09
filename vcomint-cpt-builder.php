<?php
/**
 * Plugin Name: CPT Builder
 * Description: Criador de Custom Post Types com metacampos, metaboxes e geração opcional de templates/assets no tema ativo.
 * Version: 1.1.0
 * Author: VComInt
 * Text Domain: vcomint-cpt-builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VCOMINT_CPT_BUILDER_VERSION', '1.1.0' );
define( 'VCOMINT_CPT_BUILDER_FILE', __FILE__ );
define( 'VCOMINT_CPT_BUILDER_DIR', plugin_dir_path( __FILE__ ) );
define( 'VCOMINT_CPT_BUILDER_URL', plugin_dir_url( __FILE__ ) );
define( 'VCOMINT_CPT_BUILDER_OPTION', 'vcomint_cpt_builder_items' );

require_once VCOMINT_CPT_BUILDER_DIR . 'includes/class-vcomint-cpt-templates.php';
require_once VCOMINT_CPT_BUILDER_DIR . 'includes/class-vcomint-cpt-admin.php';
require_once VCOMINT_CPT_BUILDER_DIR . 'includes/class-vcomint-cpt-builder.php';

function vcomint_cpt_builder_boot() {
	VComInt_CPT_Builder::instance();
}
add_action( 'plugins_loaded', 'vcomint_cpt_builder_boot' );

register_activation_hook( __FILE__, array( 'VComInt_CPT_Builder', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VComInt_CPT_Builder', 'deactivate' ) );
/**
 * VComInt CPT Builder — cria archive.css para CPTs existentes e futuros.
 *
 * Cria:
 * /wp-content/themes/tema-ativo/assets/css/cpt/{post_type}/archive.css
 *
 * Segurança:
 * - Não sobrescreve arquivo existente.
 * - Só roda para CPTs customizados.
 * - Usa slugs sanitizados.
 */

if ( ! function_exists( 'vcomint_cpt_builder_get_managed_post_types_for_archive_css' ) ) {
	function vcomint_cpt_builder_get_managed_post_types_for_archive_css() {
		$post_types = array();

		/**
		 * 1. CPTs registrados no WordPress.
		 */
		$registered = get_post_types(
			array(
				'_builtin' => false,
			),
			'names'
		);

		if ( is_array( $registered ) ) {
			$post_types = array_merge( $post_types, array_values( $registered ) );
		}

		/**
		 * 2. CPTs salvos pelo CPT Builder.
		 * Mantém compatibilidade com a option que usamos no plugin.
		 */
		$items = get_option( 'vcomint_cpt_builder_items', array() );

		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( is_array( $item ) && ! empty( $item['slug'] ) ) {
					$post_types[] = $item['slug'];
				} elseif ( is_string( $item ) ) {
					$post_types[] = $item;
				}
			}
		}

		/**
		 * 3. CPTs principais do projeto como fallback seguro.
		 */
		$post_types = array_merge(
			$post_types,
			array(
				'diretorio',
				'ferramentas',
				'dicionario',
				'noticias',
				'pesquisas',
			)
		);

		$post_types = array_map( 'sanitize_key', $post_types );
		$post_types = array_filter( array_unique( $post_types ) );

		/**
		 * Evita tipos nativos ou indesejados.
		 */
		$blocked = array(
			'post',
			'page',
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
		);

		$post_types = array_diff( $post_types, $blocked );

		return array_values( $post_types );
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_archive_css_default_content' ) ) {
	function vcomint_cpt_builder_archive_css_default_content( $post_type ) {
		$post_type = sanitize_key( $post_type );

		return "/* =========================================================
   Archive {$post_type} — VComInt
   Arquivo criado automaticamente pelo CPT Builder.
   Edite livremente no tema.
   ========================================================= */

.{$post_type}-archive {
	width: 100%;
	max-width: 1280px;
	margin: 0 auto;
	padding: 5rem 5vw;
	box-sizing: border-box;
}

.{$post_type}-archive *,
.{$post_type}-archive *::before,
.{$post_type}-archive *::after {
	box-sizing: border-box;
}

.{$post_type}-archive__header {
	margin-bottom: 2rem;
}

.{$post_type}-archive__title {
	margin: 0;
}

.{$post_type}-archive__grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 1rem;
}

.{$post_type}-archive__card {
	min-width: 0;
}

@media (max-width: 980px) {
	.{$post_type}-archive__grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 640px) {
	.{$post_type}-archive {
		padding: 3rem 1rem;
	}

	.{$post_type}-archive__grid {
		grid-template-columns: 1fr;
	}
}
";
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_create_archive_css_for_post_type' ) ) {
	function vcomint_cpt_builder_create_archive_css_for_post_type( $post_type ) {
		$post_type = sanitize_key( $post_type );

		if ( empty( $post_type ) ) {
			return false;
		}

		$theme_dir = get_stylesheet_directory();

		if ( ! $theme_dir || ! is_dir( $theme_dir ) ) {
			return false;
		}

		$target_dir  = trailingslashit( $theme_dir ) . 'assets/css/cpt/' . $post_type;
		$target_file = trailingslashit( $target_dir ) . 'archive.css';

		/**
		 * Nunca sobrescreve arquivo existente.
		 */
		if ( file_exists( $target_file ) ) {
			return false;
		}

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return false;
		}

		$content = vcomint_cpt_builder_archive_css_default_content( $post_type );

		return false !== file_put_contents( $target_file, $content );
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_create_missing_archive_css_files' ) ) {
	function vcomint_cpt_builder_create_missing_archive_css_files() {
		$post_types = vcomint_cpt_builder_get_managed_post_types_for_archive_css();

		foreach ( $post_types as $post_type ) {
			vcomint_cpt_builder_create_archive_css_for_post_type( $post_type );
		}
	}
}

/**
 * Cria os archive.css dos CPTs já existentes.
 * Roda apenas no admin para evitar custo no frontend.
 */
add_action(
	'admin_init',
	function() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		vcomint_cpt_builder_create_missing_archive_css_files();
	}
);

/**
 * Quando a configuração do CPT Builder for atualizada,
 * cria archive.css para novos CPTs.
 */
add_action(
	'updated_option',
	function( $option_name, $old_value, $new_value ) {
		if ( 'vcomint_cpt_builder_items' !== $option_name ) {
			return;
		}

		vcomint_cpt_builder_create_missing_archive_css_files();
	},
	10,
	3
);

/**
 * Quando a configuração do CPT Builder for criada pela primeira vez.
 */
add_action(
	'added_option',
	function( $option_name, $value ) {
		if ( 'vcomint_cpt_builder_items' !== $option_name ) {
			return;
		}

		vcomint_cpt_builder_create_missing_archive_css_files();
	},
	10,
	2
);
/**
 * VComInt CPT Builder — New Theme File Structure Patch
 *
 * This patch forces CPT Builder files into the new theme structure:
 *
 * assets/php/cpt/{slug}/single.php
 * assets/php/cpt/{slug}/archive.php
 *
 * assets/css/cpt/{slug}/shared.css
 * assets/css/cpt/{slug}/single.css
 * assets/css/cpt/{slug}/archive.css
 *
 * assets/js/cpt/{slug}/shared.js
 * assets/js/cpt/{slug}/single.js
 * assets/js/cpt/{slug}/archive.js
 *
 * It also creates WordPress template bridge files:
 *
 * single-{slug}.php
 * archive-{slug}.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'vcomint_cpt_builder_next_get_theme_paths' ) ) {
	function vcomint_cpt_builder_next_get_theme_paths( $slug ) {
		$slug = sanitize_key( $slug );

		$theme_dir = get_stylesheet_directory();

		return array(
			'bridge_single'   => $theme_dir . '/single-' . $slug . '.php',
			'bridge_archive'  => $theme_dir . '/archive-' . $slug . '.php',

			'php_dir'         => $theme_dir . '/assets/php/cpt/' . $slug,
			'css_dir'         => $theme_dir . '/assets/css/cpt/' . $slug,
			'js_dir'          => $theme_dir . '/assets/js/cpt/' . $slug,

			'php_single'      => $theme_dir . '/assets/php/cpt/' . $slug . '/single.php',
			'php_archive'     => $theme_dir . '/assets/php/cpt/' . $slug . '/archive.php',

			'css_shared'      => $theme_dir . '/assets/css/cpt/' . $slug . '/shared.css',
			'css_single'      => $theme_dir . '/assets/css/cpt/' . $slug . '/single.css',
			'css_archive'     => $theme_dir . '/assets/css/cpt/' . $slug . '/archive.css',

			'js_shared'       => $theme_dir . '/assets/js/cpt/' . $slug . '/shared.js',
			'js_single'       => $theme_dir . '/assets/js/cpt/' . $slug . '/single.js',
			'js_archive'      => $theme_dir . '/assets/js/cpt/' . $slug . '/archive.js',
		);
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_mkdir' ) ) {
	function vcomint_cpt_builder_next_mkdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_write_file_once' ) ) {
	function vcomint_cpt_builder_next_write_file_once( $path, $content ) {
		if ( file_exists( $path ) ) {
			return false;
		}

		$dir = dirname( $path );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		return false !== file_put_contents( $path, $content );
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_bridge_single_content' ) ) {
	function vcomint_cpt_builder_next_bridge_single_content( $slug ) {
		$slug = sanitize_key( $slug );

		return "<?php
/**
 * Single — " . $slug . "
 * Bridge template generated by VComInt CPT Builder.
 */

get_template_part( 'assets/php/cpt/" . $slug . "/single' );
";
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_bridge_archive_content' ) ) {
	function vcomint_cpt_builder_next_bridge_archive_content( $slug ) {
		$slug = sanitize_key( $slug );

		return "<?php
/**
 * Archive — " . $slug . "
 * Bridge template generated by VComInt CPT Builder.
 */

get_template_part( 'assets/php/cpt/" . $slug . "/archive' );
";
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_single_php_content' ) ) {
	function vcomint_cpt_builder_next_single_php_content( $slug ) {
		$slug = sanitize_key( $slug );

		return "<?php
/**
 * Single interno — " . $slug . "
 * Caminho: assets/php/cpt/" . $slug . "/single.php
 */

get_header();
?>

<main class=\"vcomint-cpt-single vcomint-cpt-single--" . $slug . "\">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'vcomint-cpt-single__article' ); ?>>
			<header class=\"vcomint-cpt-single__header\">
				<p class=\"vcomint-cpt-single__eyebrow\">" . esc_html( $slug ) . "</p>
				<h1><?php the_title(); ?></h1>
			</header>

			<section class=\"vcomint-cpt-single__content\">
				<?php the_content(); ?>
			</section>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
";
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_archive_php_content' ) ) {
	function vcomint_cpt_builder_next_archive_php_content( $slug ) {
		$slug = sanitize_key( $slug );

		return "<?php
/**
 * Archive interno — " . $slug . "
 * Caminho: assets/php/cpt/" . $slug . "/archive.php
 */

get_header();
?>

<main class=\"vcomint-cpt-archive vcomint-cpt-archive--" . $slug . "\">
	<header class=\"vcomint-cpt-archive__header\">
		<p class=\"vcomint-cpt-archive__eyebrow\">" . esc_html( $slug ) . "</p>
		<h1><?php post_type_archive_title(); ?></h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<section class=\"vcomint-cpt-archive__grid\">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'vcomint-cpt-card' ); ?>>
					<a href=\"<?php the_permalink(); ?>\">
						<h2><?php the_title(); ?></h2>
						<?php if ( has_excerpt() ) : ?>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php endif; ?>
					</a>
				</article>
			<?php endwhile; ?>
		</section>

		<nav class=\"vcomint-cpt-pagination\">
			<?php the_posts_pagination(); ?>
		</nav>
	<?php else : ?>
		<p class=\"vcomint-cpt-empty\">Nenhum item encontrado.</p>
	<?php endif; ?>
</main>

<?php
get_footer();
";
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_shared_css_content' ) ) {
	function vcomint_cpt_builder_next_shared_css_content( $slug ) {
		$slug = sanitize_key( $slug );

		return "/* Shared styles — " . $slug . " */

.vcomint-cpt-single--" . $slug . ",
.vcomint-cpt-archive--" . $slug . " {
	width: min(1280px, calc(100vw - 32px));
	margin: 0 auto;
	padding: clamp(72px, 9vw, 124px) 0 72px;
	color: var(--off-white, #f7f2ff);
}

.vcomint-cpt-single--" . $slug . " *,
.vcomint-cpt-archive--" . $slug . " * {
	box-sizing: border-box;
}
";
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_single_css_content' ) ) {
	function vcomint_cpt_builder_next_single_css_content( $slug ) {
		$slug = sanitize_key( $slug );

		return "/* Single styles — " . $slug . " */

.vcomint-cpt-single--" . $slug . " .vcomint-cpt-single__header {
	margin-bottom: 32px;
}

.vcomint-cpt-single--" . $slug . " .vcomint-cpt-single__eyebrow {
	color: var(--teal-light, #35bfba);
	font-family: var(--font-mono, ui-monospace, monospace);
	font-size: 0.68rem;
	font-weight: 800;
	letter-spacing: 0.22em;
	text-transform: uppercase;
}

.vcomint-cpt-single--" . $slug . " h1 {
	margin: 0;
	color: var(--off-white, #f7f2ff);
	font-family: var(--font-display, Georgia, serif);
	font-size: clamp(3rem, 7vw, 6rem);
	line-height: 0.95;
	letter-spacing: -0.055em;
}

.vcomint-cpt-single--" . $slug . " .vcomint-cpt-single__content {
	max-width: 860px;
	color: rgba(255,255,255,0.78);
	font-size: 1.08rem;
	line-height: 1.9;
}
";
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_archive_css_content' ) ) {
	function vcomint_cpt_builder_next_archive_css_content( $slug ) {
		$slug = sanitize_key( $slug );

		return "/* Archive styles — " . $slug . " */

.vcomint-cpt-archive--" . $slug . " .vcomint-cpt-archive__header {
	margin-bottom: 36px;
}

.vcomint-cpt-archive--" . $slug . " .vcomint-cpt-archive__eyebrow {
	color: var(--teal-light, #35bfba);
	font-family: var(--font-mono, ui-monospace, monospace);
	font-size: 0.68rem;
	font-weight: 800;
	letter-spacing: 0.22em;
	text-transform: uppercase;
}

.vcomint-cpt-archive--" . $slug . " h1 {
	margin: 0;
	color: var(--off-white, #f7f2ff);
	font-family: var(--font-display, Georgia, serif);
	font-size: clamp(3rem, 7vw, 6rem);
	line-height: 0.95;
	letter-spacing: -0.055em;
}

.vcomint-cpt-archive--" . $slug . " .vcomint-cpt-archive__grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 18px;
}

.vcomint-cpt-archive--" . $slug . " .vcomint-cpt-card a {
	display: block;
	min-height: 100%;
	padding: 22px;
	border: 1px solid rgba(255,255,255,0.1);
	background: rgba(255,255,255,0.04);
	color: var(--off-white, #f7f2ff);
	text-decoration: none;
}

.vcomint-cpt-archive--" . $slug . " .vcomint-cpt-card h2 {
	margin: 0 0 12px;
	color: var(--off-white, #f7f2ff);
	font-family: var(--font-display, Georgia, serif);
	font-size: clamp(1.5rem, 3vw, 2.3rem);
	line-height: 1.08;
}

.vcomint-cpt-archive--" . $slug . " .vcomint-cpt-card p {
	margin: 0;
	color: rgba(255,255,255,0.68);
	line-height: 1.7;
}

@media (max-width: 900px) {
	.vcomint-cpt-archive--" . $slug . " .vcomint-cpt-archive__grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 640px) {
	.vcomint-cpt-archive--" . $slug . " .vcomint-cpt-archive__grid {
		grid-template-columns: 1fr;
	}
}
";
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_js_content' ) ) {
	function vcomint_cpt_builder_next_js_content( $slug, $type ) {
		$slug = sanitize_key( $slug );
		$type = sanitize_key( $type );

		return "/* " . ucfirst( $type ) . " JS — " . $slug . " */

(function () {
	document.documentElement.classList.add('has-cpt-" . $slug . "-" . $type . "');
})();
";
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_ensure_files_for_slug' ) ) {
	function vcomint_cpt_builder_next_ensure_files_for_slug( $slug ) {
		$slug = sanitize_key( $slug );

		if ( ! $slug ) {
			return;
		}

		$paths = vcomint_cpt_builder_next_get_theme_paths( $slug );

		vcomint_cpt_builder_next_mkdir( $paths['php_dir'] );
		vcomint_cpt_builder_next_mkdir( $paths['css_dir'] );
		vcomint_cpt_builder_next_mkdir( $paths['js_dir'] );

		vcomint_cpt_builder_next_write_file_once(
			$paths['bridge_single'],
			vcomint_cpt_builder_next_bridge_single_content( $slug )
		);

		vcomint_cpt_builder_next_write_file_once(
			$paths['bridge_archive'],
			vcomint_cpt_builder_next_bridge_archive_content( $slug )
		);

		vcomint_cpt_builder_next_write_file_once(
			$paths['php_single'],
			vcomint_cpt_builder_next_single_php_content( $slug )
		);

		vcomint_cpt_builder_next_write_file_once(
			$paths['php_archive'],
			vcomint_cpt_builder_next_archive_php_content( $slug )
		);

		vcomint_cpt_builder_next_write_file_once(
			$paths['css_shared'],
			vcomint_cpt_builder_next_shared_css_content( $slug )
		);

		vcomint_cpt_builder_next_write_file_once(
			$paths['css_single'],
			vcomint_cpt_builder_next_single_css_content( $slug )
		);

		vcomint_cpt_builder_next_write_file_once(
			$paths['css_archive'],
			vcomint_cpt_builder_next_archive_css_content( $slug )
		);

		vcomint_cpt_builder_next_write_file_once(
			$paths['js_shared'],
			vcomint_cpt_builder_next_js_content( $slug, 'shared' )
		);

		vcomint_cpt_builder_next_write_file_once(
			$paths['js_single'],
			vcomint_cpt_builder_next_js_content( $slug, 'single' )
		);

		vcomint_cpt_builder_next_write_file_once(
			$paths['js_archive'],
			vcomint_cpt_builder_next_js_content( $slug, 'archive' )
		);
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_get_registered_builder_slugs' ) ) {
	function vcomint_cpt_builder_next_get_registered_builder_slugs() {
		$items = get_option( 'vcomint_cpt_builder_items', array() );
		$slugs = array();

		if ( ! is_array( $items ) ) {
			return $slugs;
		}

		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				if ( ! empty( $item['slug'] ) ) {
					$slugs[] = sanitize_key( $item['slug'] );
				} elseif ( ! empty( $item['post_type'] ) ) {
					$slugs[] = sanitize_key( $item['post_type'] );
				} elseif ( ! empty( $item['key'] ) ) {
					$slugs[] = sanitize_key( $item['key'] );
				}
			} elseif ( is_string( $item ) ) {
				$slugs[] = sanitize_key( $item );
			}
		}

		return array_values( array_unique( array_filter( $slugs ) ) );
	}
}

if ( ! function_exists( 'vcomint_cpt_builder_next_ensure_all_files' ) ) {
	function vcomint_cpt_builder_next_ensure_all_files() {
		$slugs = vcomint_cpt_builder_next_get_registered_builder_slugs();

		foreach ( $slugs as $slug ) {
			vcomint_cpt_builder_next_ensure_files_for_slug( $slug );
		}
	}
}

/**
 * Ensures structure after CPT Builder option changes.
 */
add_action(
	'updated_option',
	function ( $option_name ) {
		if ( 'vcomint_cpt_builder_items' !== $option_name ) {
			return;
		}

		vcomint_cpt_builder_next_ensure_all_files();

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	},
	10,
	1
);

/**
 * Ensures structure when the admin loads.
 * This is intentionally non-destructive and does not overwrite files.
 */
add_action(
	'admin_init',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_option( 'vcomint_cpt_builder_next_structure_checked' ) ) {
			vcomint_cpt_builder_next_ensure_all_files();
			update_option( 'vcomint_cpt_builder_next_structure_checked', time(), false );
		}
	}
);

/**
 * Manual repair action:
 * /wp-admin/admin.php?vcomint_repair_cpt_files=1
 */
add_action(
	'admin_init',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_GET['vcomint_repair_cpt_files'] ) ) {
			return;
		}

		vcomint_cpt_builder_next_ensure_all_files();
		flush_rewrite_rules( false );

		wp_safe_redirect( admin_url( 'edit.php?post_type=patentes' ) );
		exit;
	}
);