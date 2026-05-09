<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VComInt_CPT_Admin {
	private $builder;
	private $types = array(
		'text'         => 'Texto curto',
		'textarea'     => 'Texto longo',
		'number'       => 'Número',
		'url'          => 'URL',
		'email'        => 'E-mail',
		'date'         => 'Data',
		'boolean'      => 'Booleano',
		'select'       => 'Dropdown / Select',
		'relationship' => 'Relação com CPT',
	);

	public function __construct( $builder ) {
		$this->builder = $builder;
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			'CPT Builder',
			'CPT Builder',
			'manage_options',
			'vcomint-cpt-builder',
			array( $this, 'render_page' ),
			'dashicons-index-card',
			58
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_vcomint-cpt-builder' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'vcomint-cpt-builder-admin', VCOMINT_CPT_BUILDER_URL . 'assets/admin/admin.css', array(), VCOMINT_CPT_BUILDER_VERSION );
		wp_enqueue_script( 'vcomint-cpt-builder-admin', VCOMINT_CPT_BUILDER_URL . 'assets/admin/admin.js', array(), VCOMINT_CPT_BUILDER_VERSION, true );
	}

	public function handle_actions() {
		if ( ! is_admin() || empty( $_POST['vcomint_cpt_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'vcomint-cpt-builder' ) );
		}
		check_admin_referer( 'vcomint_cpt_builder_save', 'vcomint_cpt_builder_nonce' );

		$action = sanitize_key( wp_unslash( $_POST['vcomint_cpt_action'] ) );
		$items  = VComInt_CPT_Builder::get_items();

		if ( 'save_cpt' === $action ) {
			$raw_slug = isset( $_POST['cpt_slug'] ) ? wp_unslash( $_POST['cpt_slug'] ) : '';
			$slug     = sanitize_key( str_replace( '-', '_', sanitize_title( $raw_slug ) ) );

			if ( empty( $slug ) ) {
				$this->redirect_with_message( 'invalid_slug' );
			}

			$singular  = isset( $_POST['cpt_singular'] ) ? sanitize_text_field( wp_unslash( $_POST['cpt_singular'] ) ) : ucfirst( $slug );
			$plural    = isset( $_POST['cpt_plural'] ) ? sanitize_text_field( wp_unslash( $_POST['cpt_plural'] ) ) : $singular . 's';
			$menu_icon = isset( $_POST['cpt_menu_icon'] ) ? sanitize_text_field( wp_unslash( $_POST['cpt_menu_icon'] ) ) : 'dashicons-admin-post';
			$supports  = isset( $_POST['supports'] ) && is_array( $_POST['supports'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['supports'] ) ) : array( 'title', 'editor', 'thumbnail', 'excerpt' );

			$metas = $this->sanitize_meta_rows( isset( $_POST['metas'] ) && is_array( $_POST['metas'] ) ? wp_unslash( $_POST['metas'] ) : array() );

			$items[ $slug ] = array(
				'slug'      => $slug,
				'singular'  => $singular,
				'plural'    => $plural,
				'menu_icon' => $menu_icon,
				'supports'  => $supports,
				'metas'     => $metas,
				'updated'   => current_time( 'mysql' ),
			);

			update_option( VCOMINT_CPT_BUILDER_OPTION, $items, false );
			$this->builder->refresh_items();

			if ( ! empty( $_POST['create_theme_files'] ) ) {
				$result = VComInt_CPT_Templates::create_files_for_cpt( $slug, $singular, $plural );
				flush_rewrite_rules();
				if ( is_wp_error( $result ) ) {
					$this->redirect_with_message( 'saved_file_error', $slug );
				}
			}

			$this->builder->recalculate_tool_usage_counts();
			flush_rewrite_rules();
			$this->redirect_with_message( 'saved', $slug );
		}

		if ( 'delete_cpt' === $action ) {
			$slug = isset( $_POST['delete_slug'] ) ? sanitize_key( wp_unslash( $_POST['delete_slug'] ) ) : '';
			if ( isset( $items[ $slug ] ) ) {
				unset( $items[ $slug ] );
				update_option( VCOMINT_CPT_BUILDER_OPTION, $items, false );
				flush_rewrite_rules();
			}
			$this->redirect_with_message( 'deleted' );
		}
	}

	private function sanitize_meta_rows( $rows ) {
		$clean = array();
		foreach ( $rows as $row ) {
			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
			if ( empty( $key ) ) {
				continue;
			}
			$type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : 'text';
			if ( ! array_key_exists( $type, $this->types ) ) {
				$type = 'text';
			}

			$meta = array(
				'key'         => $key,
				'label'       => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : $key,
				'type'        => $type,
				'description' => isset( $row['description'] ) ? sanitize_text_field( $row['description'] ) : '',
				'options'     => isset( $row['options'] ) ? sanitize_textarea_field( $row['options'] ) : '',
			);

			if ( 'relationship' === $type ) {
				$meta['related_post_type'] = isset( $row['related_post_type'] ) ? sanitize_key( $row['related_post_type'] ) : '';
				$meta['multiple']          = ! empty( $row['multiple'] ) ? 1 : 0;
			}

			$clean[] = $meta;
		}
		return $clean;
	}

	private function redirect_with_message( $message, $tab = '' ) {
		$url = add_query_arg(
			array_filter(
				array(
					'page'    => 'vcomint-cpt-builder',
					'message' => $message,
					'tab'     => $tab,
				),
				function( $value ) {
					return '' !== $value;
				}
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function render_page() {
		$items = VComInt_CPT_Builder::get_items();
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'new';
		if ( 'new' !== $tab && ! isset( $items[ $tab ] ) ) {
			$tab = 'new';
		}
		?>
		<div class="wrap vcomint-cpt-builder-wrap">
			<h1>CPT Builder</h1>
			<p>Crie CPTs e defina metacampos salvos em <code>postmeta</code>, prontos para uso em layouts, templates e schemas.</p>
			<?php $this->render_notice(); ?>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'new' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=vcomint-cpt-builder&tab=new' ) ); ?>">Novo CPT</a>
				<?php foreach ( $items as $slug => $config ) : ?>
					<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=vcomint-cpt-builder&tab=' . $slug ) ); ?>"><?php echo esc_html( $config['plural'] ?? $slug ); ?></a>
				<?php endforeach; ?>
			</h2>
			<?php
			if ( 'new' === $tab ) {
				$this->render_form();
			} else {
				$this->render_form( $items[ $tab ] );
			}
			?>
		</div>
		<?php
	}

	private function render_notice() {
		if ( empty( $_GET['message'] ) ) {
			return;
		}
		$message = sanitize_key( wp_unslash( $_GET['message'] ) );
		$map     = array(
			'saved'            => array( 'updated', 'CPT salvo com sucesso.' ),
			'saved_file_error' => array( 'error', 'CPT salvo, mas algum arquivo do tema não pôde ser criado. Verifique permissão de escrita no tema ativo.' ),
			'deleted'          => array( 'updated', 'CPT removido do registro do plugin. Conteúdos já criados e arquivos do tema não foram apagados.' ),
			'invalid_slug'     => array( 'error', 'Slug inválido.' ),
		);
		if ( empty( $map[ $message ] ) ) {
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $map[ $message ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $message ][1] ) . '</p></div>';
	}

	private function render_form( $config = array() ) {
		$is_edit  = ! empty( $config['slug'] );
		$slug     = $is_edit ? sanitize_key( $config['slug'] ) : '';
		$metas    = ! empty( $config['metas'] ) && is_array( $config['metas'] ) ? $config['metas'] : array();
		$supports = ! empty( $config['supports'] ) && is_array( $config['supports'] ) ? $config['supports'] : array( 'title', 'editor', 'thumbnail', 'excerpt' );
		?>
		<form method="post" class="vcomint-cpt-builder-form">
			<?php wp_nonce_field( 'vcomint_cpt_builder_save', 'vcomint_cpt_builder_nonce' ); ?>
			<input type="hidden" name="vcomint_cpt_action" value="save_cpt">

			<div class="vcomint-cpt-card">
				<h2><?php echo $is_edit ? 'Configurar CPT' : 'Criar novo CPT'; ?></h2>
				<div class="vcomint-cpt-grid">
					<label>Slug do CPT<br><input type="text" name="cpt_slug" value="<?php echo esc_attr( $slug ); ?>" <?php disabled( $is_edit ); ?> required placeholder="ex: noticias"></label>
					<?php if ( $is_edit ) : ?><input type="hidden" name="cpt_slug" value="<?php echo esc_attr( $slug ); ?>"><?php endif; ?>
					<label>Nome singular<br><input type="text" name="cpt_singular" value="<?php echo esc_attr( $config['singular'] ?? '' ); ?>" required placeholder="ex: Notícia"></label>
					<label>Nome plural<br><input type="text" name="cpt_plural" value="<?php echo esc_attr( $config['plural'] ?? '' ); ?>" required placeholder="ex: Notícias"></label>
					<label>Ícone do menu<br><input type="text" name="cpt_menu_icon" value="<?php echo esc_attr( $config['menu_icon'] ?? 'dashicons-admin-post' ); ?>" placeholder="dashicons-admin-post"></label>
				</div>
				<div class="vcomint-cpt-supports">
					<strong>Suportes do editor</strong><br>
					<?php foreach ( array( 'title' => 'Título', 'editor' => 'Editor', 'thumbnail' => 'Imagem destacada', 'excerpt' => 'Resumo', 'author' => 'Autor', 'revisions' => 'Revisões', 'custom-fields' => 'Custom Fields nativo' ) as $support => $label ) : ?>
						<label><input type="checkbox" name="supports[]" value="<?php echo esc_attr( $support ); ?>" <?php checked( in_array( $support, $supports, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="vcomint-cpt-card">
				<div class="vcomint-cpt-card-header">
					<h2>Metacampos</h2>
					<button type="button" class="button" id="vcomint-add-meta-row">Adicionar meta</button>
				</div>
				<p>Use chaves limpas, sem espaços e sem acento. Para relações automáticas, use o tipo <strong>Relação com CPT</strong>.</p>
				<table class="widefat striped" id="vcomint-meta-table">
					<thead><tr><th>Chave</th><th>Label</th><th>Tipo</th><th>Descrição</th><th>Opções / CPT relacionado</th><th>Múltipla</th><th></th></tr></thead>
					<tbody>
					<?php
					if ( empty( $metas ) ) {
						$metas = array( array( 'key' => '', 'label' => '', 'type' => 'text', 'description' => '', 'options' => '', 'related_post_type' => '', 'multiple' => 1 ) );
					}
					foreach ( array_values( $metas ) as $index => $meta ) {
						$this->render_meta_row( $index, $meta );
					}
					?>
					</tbody>
				</table>
			</div>

			<div class="vcomint-cpt-actions">
				<label><input type="checkbox" name="create_theme_files" value="1" <?php checked( ! $is_edit ); ?>> Criar arquivos no tema ativo: <code>single-<?php echo esc_html( $slug ?: '{slug}' ); ?>.php</code>, <code>archive-<?php echo esc_html( $slug ?: '{slug}' ); ?>.php</code>, CSS e JS.</label>
				<?php submit_button( $is_edit ? 'Salvar alterações' : 'Criar CPT', 'primary', 'submit', false ); ?>
			</div>
		</form>

		<?php if ( $is_edit ) : ?>
			<form method="post" class="vcomint-cpt-delete-form" onsubmit="return confirm('Remover este CPT do registro do plugin? Os posts e arquivos do tema não serão apagados.');">
				<?php wp_nonce_field( 'vcomint_cpt_builder_save', 'vcomint_cpt_builder_nonce' ); ?>
				<input type="hidden" name="vcomint_cpt_action" value="delete_cpt">
				<input type="hidden" name="delete_slug" value="<?php echo esc_attr( $slug ); ?>">
				<?php submit_button( 'Remover CPT do plugin', 'delete', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<script type="text/template" id="vcomint-meta-row-template">
			<?php $this->render_meta_row( '__INDEX__', array( 'key' => '', 'label' => '', 'type' => 'text', 'description' => '', 'options' => '', 'related_post_type' => '', 'multiple' => 1 ) ); ?>
		</script>
		<?php
	}

	private function render_meta_row( $index, $meta ) {
		$related_post_type = isset( $meta['related_post_type'] ) ? sanitize_key( $meta['related_post_type'] ) : '';
		?>
		<tr>
			<td><input type="text" name="metas[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $meta['key'] ?? '' ); ?>" placeholder="meta_key"></td>
			<td><input type="text" name="metas[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $meta['label'] ?? '' ); ?>" placeholder="Nome exibido"></td>
			<td>
				<select name="metas[<?php echo esc_attr( $index ); ?>][type]" class="vcomint-meta-type-select">
					<?php foreach ( $this->types as $type => $label ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $meta['type'] ?? 'text', $type ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="text" name="metas[<?php echo esc_attr( $index ); ?>][description]" value="<?php echo esc_attr( $meta['description'] ?? '' ); ?>" placeholder="Ajuda para o editor"></td>
			<td>
				<textarea name="metas[<?php echo esc_attr( $index ); ?>][options]" placeholder="Uma opção por linha para select"><?php echo esc_textarea( $meta['options'] ?? '' ); ?></textarea>
				<input type="text" name="metas[<?php echo esc_attr( $index ); ?>][related_post_type]" value="<?php echo esc_attr( $related_post_type ); ?>" placeholder="CPT relacionado. Ex: ferramentas" class="vcomint-related-post-type-input">
			</td>
			<td><label><input type="checkbox" name="metas[<?php echo esc_attr( $index ); ?>][multiple]" value="1" <?php checked( ! empty( $meta['multiple'] ) ); ?>> Sim</label></td>
			<td><button type="button" class="button-link-delete vcomint-remove-meta-row">Remover</button></td>
		</tr>
		<?php
	}
}
