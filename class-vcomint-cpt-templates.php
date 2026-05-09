<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VComInt_CPT_Templates {
	public static function create_files_for_cpt( $slug, $singular, $plural ) {
		$slug     = sanitize_key( $slug );
		$singular = sanitize_text_field( $singular );
		$plural   = sanitize_text_field( $plural );

		if ( empty( $slug ) ) {
			return new WP_Error( 'invalid_slug', __( 'Slug inválido.', 'vcomint-cpt-builder' ) );
		}

		$theme_dir = trailingslashit( get_stylesheet_directory() );
		$asset_dir = $theme_dir . 'assets/cpt-builder/';

		if ( ! wp_mkdir_p( $asset_dir ) ) {
			return new WP_Error( 'cannot_create_asset_dir', __( 'Não foi possível criar a pasta de assets no tema ativo.', 'vcomint-cpt-builder' ) );
		}

		$files = array(
			$theme_dir . 'single-' . $slug . '.php'      => self::single_template( $slug, $singular ),
			$theme_dir . 'archive-' . $slug . '.php'     => self::archive_template( $slug, $plural ),
			$asset_dir . $slug . '.css'                  => self::css_template( $slug ),
			$asset_dir . $slug . '.js'                   => self::js_template( $slug ),
		);

		$created = array();
		$skipped = array();
		$failed  = array();

		foreach ( $files as $path => $content ) {
			if ( file_exists( $path ) ) {
				$skipped[] = basename( $path );
				continue;
			}

			$result = file_put_contents( $path, $content );
			if ( false === $result ) {
				$failed[] = basename( $path );
			} else {
				$created[] = basename( $path );
			}
		}

		if ( ! empty( $failed ) ) {
			return new WP_Error(
				'files_failed',
				sprintf( __( 'Alguns arquivos não puderam ser criados: %s', 'vcomint-cpt-builder' ), implode( ', ', $failed ) ),
				array( 'created' => $created, 'skipped' => $skipped, 'failed' => $failed )
			);
		}

		return array(
			'created' => $created,
			'skipped' => $skipped,
			'failed'  => $failed,
		);
	}

	private static function single_template( $slug, $singular ) {
		$class = 'vcomint-single-' . $slug;
		return "<?php\n/**\n * Single template generated for CPT: {$slug}\n */\nif ( ! defined( 'ABSPATH' ) ) { exit; }\nget_header();\n?>\n<main id=\"primary\" class=\"site-main {$class}\">\n\t<?php while ( have_posts() ) : the_post(); ?>\n\t\t<article id=\"post-<?php the_ID(); ?>\" <?php post_class( 'vcomint-cpt-entry' ); ?>>\n\t\t\t<?php the_content(); ?>\n\t\t</article>\n\t<?php endwhile; ?>\n</main>\n<?php get_footer();\n";
	}

	private static function archive_template( $slug, $plural ) {
		$class = 'vcomint-archive-' . $slug;
		return "<?php\n/**\n * Archive template generated for CPT: {$slug}\n */\nif ( ! defined( 'ABSPATH' ) ) { exit; }\nget_header();\n?>\n<main id=\"primary\" class=\"site-main {$class}\">\n\t<?php if ( have_posts() ) : ?>\n\t\t<div class=\"vcomint-cpt-archive-list\">\n\t\t\t<?php while ( have_posts() ) : the_post(); ?>\n\t\t\t\t<article id=\"post-<?php the_ID(); ?>\" <?php post_class( 'vcomint-cpt-card' ); ?>>\n\t\t\t\t\t<a href=\"<?php the_permalink(); ?>\">\n\t\t\t\t\t\t<h2><?php the_title(); ?></h2>\n\t\t\t\t\t</a>\n\t\t\t\t\t<?php if ( has_excerpt() ) : ?>\n\t\t\t\t\t\t<div class=\"vcomint-cpt-excerpt\"><?php the_excerpt(); ?></div>\n\t\t\t\t\t<?php endif; ?>\n\t\t\t\t</article>\n\t\t\t<?php endwhile; ?>\n\t\t</div>\n\t\t<?php the_posts_pagination(); ?>\n\t<?php else : ?>\n\t\t<p><?php esc_html_e( 'Nenhum conteúdo encontrado.', 'vcomint-cpt-builder' ); ?></p>\n\t<?php endif; ?>\n</main>\n<?php get_footer();\n";
	}

	private static function css_template( $slug ) {
		return "/* Assets generated for CPT: {$slug}.\n   Use this file to style only this CPT. */\n\n.vcomint-single-{$slug},\n.vcomint-archive-{$slug} {\n\twidth: 100%;\n}\n\n.vcomint-archive-{$slug} .vcomint-cpt-archive-list {\n\tdisplay: grid;\n\tgap: 24px;\n}\n";
	}

	private static function js_template( $slug ) {
		return "/* JavaScript generated for CPT: {$slug}. */\n(function () {\n\t'use strict';\n\tdocument.documentElement.classList.add('has-cpt-{$slug}');\n})();\n";
	}
}
