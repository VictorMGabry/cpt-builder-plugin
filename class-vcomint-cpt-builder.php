<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VComInt_CPT_Builder {
	private static $instance = null;
	private $items = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->items = self::get_items();
		add_action( 'init', array( $this, 'register_dynamic_post_types' ), 5 );
		add_action( 'init', array( $this, 'register_dynamic_meta' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

		if ( is_admin() ) {
			new VComInt_CPT_Admin( $this );
		}
	}

	public static function activate() {
		if ( false === get_option( VCOMINT_CPT_BUILDER_OPTION ) ) {
			add_option( VCOMINT_CPT_BUILDER_OPTION, array(), '', false );
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function get_items() {
		$items = get_option( VCOMINT_CPT_BUILDER_OPTION, array() );
		return is_array( $items ) ? $items : array();
	}

	public function refresh_items() {
		$this->items = self::get_items();
	}

	public function get_runtime_items() {
		return $this->items;
	}

	public function register_dynamic_post_types() {
		foreach ( $this->items as $slug => $config ) {
			$slug = sanitize_key( $slug );
			if ( empty( $slug ) || post_type_exists( $slug ) ) {
				continue;
			}

			$singular = ! empty( $config['singular'] ) ? sanitize_text_field( $config['singular'] ) : ucfirst( str_replace( array( '-', '_' ), ' ', $slug ) );
			$plural   = ! empty( $config['plural'] ) ? sanitize_text_field( $config['plural'] ) : $singular . 's';
			$icon     = ! empty( $config['menu_icon'] ) ? sanitize_text_field( $config['menu_icon'] ) : 'dashicons-admin-post';
			$supports = ! empty( $config['supports'] ) && is_array( $config['supports'] ) ? array_map( 'sanitize_key', $config['supports'] ) : array( 'title', 'editor', 'thumbnail', 'excerpt' );

			$args = array(
				'labels' => array(
					'name'               => $plural,
					'singular_name'      => $singular,
					'add_new'            => __( 'Adicionar novo', 'vcomint-cpt-builder' ),
					'add_new_item'       => sprintf( __( 'Adicionar %s', 'vcomint-cpt-builder' ), $singular ),
					'edit_item'          => sprintf( __( 'Editar %s', 'vcomint-cpt-builder' ), $singular ),
					'new_item'           => sprintf( __( 'Novo %s', 'vcomint-cpt-builder' ), $singular ),
					'view_item'          => sprintf( __( 'Ver %s', 'vcomint-cpt-builder' ), $singular ),
					'search_items'       => sprintf( __( 'Buscar %s', 'vcomint-cpt-builder' ), $plural ),
					'not_found'          => __( 'Nenhum item encontrado.', 'vcomint-cpt-builder' ),
					'not_found_in_trash' => __( 'Nenhum item encontrado na lixeira.', 'vcomint-cpt-builder' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => $slug ),
				'menu_icon'           => $icon,
				'supports'            => $supports,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
			);

			register_post_type( $slug, $args );
		}
	}

	public function register_dynamic_meta() {
		foreach ( $this->items as $post_type => $config ) {
			if ( empty( $config['metas'] ) || ! is_array( $config['metas'] ) ) {
				continue;
			}

			foreach ( $config['metas'] as $meta ) {
				$key = isset( $meta['key'] ) ? sanitize_key( $meta['key'] ) : '';
				if ( empty( $key ) ) {
					continue;
				}

				$type = $this->normalize_meta_type( isset( $meta['type'] ) ? $meta['type'] : 'text' );
				$args = array(
					'type'              => $this->wp_meta_schema_type( $type ),
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( $this, 'sanitize_registered_meta_value' ),
					'auth_callback'     => function() {
						return current_user_can( 'edit_posts' );
					},
				);

				if ( 'relationship' === $type ) {
					$args['show_in_rest'] = array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					);
				}

				register_post_meta( $post_type, $key, $args );
			}
		}
	}

	public function add_meta_boxes() {
		foreach ( $this->items as $post_type => $config ) {
			if ( empty( $config['metas'] ) || ! is_array( $config['metas'] ) ) {
				continue;
			}

			add_meta_box(
				'vcomint_cpt_meta_' . sanitize_key( $post_type ),
				__( 'Metacampos do CPT', 'vcomint-cpt-builder' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	public function render_meta_box( $post ) {
		$post_type = get_post_type( $post );
		$config    = isset( $this->items[ $post_type ] ) ? $this->items[ $post_type ] : array();
		$metas     = isset( $config['metas'] ) && is_array( $config['metas'] ) ? $config['metas'] : array();

		wp_nonce_field( 'vcomint_cpt_save_meta_' . $post_type, 'vcomint_cpt_meta_nonce' );
		echo '<div class="vcomint-cpt-meta-box">';

		foreach ( $metas as $meta ) {
			$key         = isset( $meta['key'] ) ? sanitize_key( $meta['key'] ) : '';
			$label       = ! empty( $meta['label'] ) ? sanitize_text_field( $meta['label'] ) : $key;
			$type        = $this->normalize_meta_type( isset( $meta['type'] ) ? $meta['type'] : 'text' );
			$description = ! empty( $meta['description'] ) ? sanitize_text_field( $meta['description'] ) : '';
			$options_raw = ! empty( $meta['options'] ) ? sanitize_textarea_field( $meta['options'] ) : '';
			$value       = get_post_meta( $post->ID, $key, true );

			if ( empty( $key ) ) {
				continue;
			}

			echo '<p class="vcomint-cpt-meta-field">';
			echo '<label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong><br><code>' . esc_html( $key ) . '</code></label><br>';

			switch ( $type ) {
				case 'textarea':
					echo '<textarea style="width:100%;min-height:90px;" id="' . esc_attr( $key ) . '" name="vcomint_cpt_meta[' . esc_attr( $key ) . ']">' . esc_textarea( $value ) . '</textarea>';
					break;
				case 'boolean':
					echo '<label><input type="checkbox" value="1" id="' . esc_attr( $key ) . '" name="vcomint_cpt_meta[' . esc_attr( $key ) . ']" ' . checked( (string) $value, '1', false ) . '> ' . esc_html__( 'Sim', 'vcomint-cpt-builder' ) . '</label>';
					break;
				case 'select':
					echo '<select style="width:100%;" id="' . esc_attr( $key ) . '" name="vcomint_cpt_meta[' . esc_attr( $key ) . ']">';
					echo '<option value="">' . esc_html__( 'Selecione', 'vcomint-cpt-builder' ) . '</option>';
					$options = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n|,/', $options_raw ) ) );
					foreach ( $options as $option ) {
						echo '<option value="' . esc_attr( $option ) . '" ' . selected( (string) $value, (string) $option, false ) . '>' . esc_html( $option ) . '</option>';
					}
					echo '</select>';
					break;
				case 'relationship':
					$this->render_relationship_field( $key, $meta, $value );
					break;
				case 'number':
					echo '<input style="width:100%;" type="number" step="any" id="' . esc_attr( $key ) . '" name="vcomint_cpt_meta[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
					break;
				case 'url':
					echo '<input style="width:100%;" type="url" id="' . esc_attr( $key ) . '" name="vcomint_cpt_meta[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
					break;
				case 'email':
					echo '<input style="width:100%;" type="email" id="' . esc_attr( $key ) . '" name="vcomint_cpt_meta[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
					break;
				case 'date':
					echo '<input style="width:100%;" type="date" id="' . esc_attr( $key ) . '" name="vcomint_cpt_meta[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
					break;
				default:
					echo '<input style="width:100%;" type="text" id="' . esc_attr( $key ) . '" name="vcomint_cpt_meta[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
					break;
			}

			if ( $description ) {
				echo '<br><span class="description">' . esc_html( $description ) . '</span>';
			}
			echo '</p>';
		}

		echo '</div>';
	}

	private function render_relationship_field( $key, $meta, $value ) {
		$related_post_type = ! empty( $meta['related_post_type'] ) ? sanitize_key( $meta['related_post_type'] ) : '';
		$multiple          = ! empty( $meta['multiple'] );
		$selected          = $this->normalize_relationship_ids( $value );

		if ( empty( $related_post_type ) || ! post_type_exists( $related_post_type ) ) {
			echo '<em>' . esc_html__( 'CPT relacionado não configurado ou não encontrado.', 'vcomint-cpt-builder' ) . '</em>';
			return;
		}

		$related_posts = get_posts(
			array(
				'post_type'      => $related_post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$name_attr = 'vcomint_cpt_meta[' . esc_attr( $key ) . ']' . ( $multiple ? '[]' : '' );
		echo '<div class="vcomint-cpt-relationship-field" data-vcomint-relationship-field>';
		echo '<input type="search" class="vcomint-cpt-relationship-search" placeholder="Buscar ' . esc_attr__( 'item relacionado', 'vcomint-cpt-builder' ) . '" aria-label="Buscar item relacionado">';
		echo '<div class="vcomint-cpt-relationship-options">';

		foreach ( $related_posts as $related_id ) {
			$title = get_the_title( $related_id );
			$type  = get_post_type( $related_id );
			$input_type = $multiple ? 'checkbox' : 'radio';
			echo '<label class="vcomint-cpt-relationship-option" data-vcomint-relationship-option data-title="' . esc_attr( strtolower( remove_accents( $title ) ) ) . '">';
			echo '<input type="' . esc_attr( $input_type ) . '" name="' . $name_attr . '" value="' . esc_attr( $related_id ) . '" ' . checked( in_array( (int) $related_id, $selected, true ), true, false ) . '> ';
			echo '<span>' . esc_html( $title ) . '</span> <small>#' . esc_html( $related_id ) . ' · ' . esc_html( $type ) . '</small>';
			echo '</label>';
		}

		if ( empty( $related_posts ) ) {
			echo '<em>' . esc_html__( 'Nenhum item encontrado no CPT relacionado.', 'vcomint-cpt-builder' ) . '</em>';
		}

		echo '</div></div>';
	}

	public function save_meta_boxes( $post_id, $post ) {
		$post_type = isset( $post->post_type ) ? $post->post_type : '';

		if ( empty( $this->items[ $post_type ] ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( empty( $_POST['vcomint_cpt_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vcomint_cpt_meta_nonce'] ) ), 'vcomint_cpt_save_meta_' . $post_type ) ) {
			return;
		}

		$incoming = isset( $_POST['vcomint_cpt_meta'] ) && is_array( $_POST['vcomint_cpt_meta'] ) ? wp_unslash( $_POST['vcomint_cpt_meta'] ) : array();
		$metas    = isset( $this->items[ $post_type ]['metas'] ) && is_array( $this->items[ $post_type ]['metas'] ) ? $this->items[ $post_type ]['metas'] : array();
		$needs_tool_usage_recount = false;

		foreach ( $metas as $meta ) {
			$key  = isset( $meta['key'] ) ? sanitize_key( $meta['key'] ) : '';
			$type = $this->normalize_meta_type( isset( $meta['type'] ) ? $meta['type'] : 'text' );
			if ( empty( $key ) ) {
				continue;
			}

			$value = isset( $incoming[ $key ] ) ? $incoming[ $key ] : '';
			if ( 'boolean' === $type ) {
				$value = isset( $incoming[ $key ] ) ? '1' : '0';
			}
			$value = $this->sanitize_meta_by_type( $value, $type );

			if ( '' === $value && 'boolean' !== $type && 'relationship' !== $type ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}

			if ( 'relationship' === $type && ! empty( $meta['related_post_type'] ) && 'ferramentas' === sanitize_key( $meta['related_post_type'] ) ) {
				$needs_tool_usage_recount = true;
			}
		}

		if ( $needs_tool_usage_recount ) {
			$this->recalculate_tool_usage_counts();
		}
	}

	public function enqueue_public_assets() {
		if ( ! is_singular() && ! is_post_type_archive() ) {
			return;
		}

		$post_type = '';
		if ( is_singular() ) {
			$post_type = get_post_type();
		} elseif ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		}

		$post_type = sanitize_key( $post_type );
		if ( empty( $post_type ) || empty( $this->items[ $post_type ] ) ) {
			return;
		}

		$theme_dir = trailingslashit( get_stylesheet_directory() );
		$theme_uri = trailingslashit( get_stylesheet_directory_uri() );
		$css_path  = $theme_dir . 'assets/cpt-builder/' . $post_type . '.css';
		$js_path   = $theme_dir . 'assets/cpt-builder/' . $post_type . '.js';

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'vcomint-cpt-' . $post_type, $theme_uri . 'assets/cpt-builder/' . $post_type . '.css', array(), filemtime( $css_path ) );
		}
		if ( file_exists( $js_path ) ) {
			wp_enqueue_script( 'vcomint-cpt-' . $post_type, $theme_uri . 'assets/cpt-builder/' . $post_type . '.js', array(), filemtime( $js_path ), true );
		}
	}

	public function sanitize_registered_meta_value( $value, $meta_key, $object_type ) {
		if ( is_array( $value ) ) {
			return $this->normalize_relationship_ids( $value );
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	public function sanitize_meta_by_type( $value, $type ) {
		$type = $this->normalize_meta_type( $type );
		switch ( $type ) {
			case 'relationship':
				return $this->normalize_relationship_ids( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'number':
				return is_numeric( $value ) ? (string) $value : '';
			case 'url':
				return esc_url_raw( $value );
			case 'email':
				return sanitize_email( $value );
			case 'date':
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ? (string) $value : '';
			case 'boolean':
				return '1' === (string) $value ? '1' : '0';
			case 'select':
			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	public function normalize_meta_type( $type ) {
		$allowed = array( 'text', 'textarea', 'number', 'url', 'email', 'date', 'boolean', 'select', 'relationship' );
		$type    = sanitize_key( $type );
		return in_array( $type, $allowed, true ) ? $type : 'text';
	}

	public function wp_meta_schema_type( $type ) {
		$type = $this->normalize_meta_type( $type );
		if ( 'relationship' === $type ) {
			return 'array';
		}
		if ( 'number' === $type ) {
			return 'number';
		}
		if ( 'boolean' === $type ) {
			return 'boolean';
		}
		return 'string';
	}

	public function normalize_relationship_ids( $value ) {
		if ( is_string( $value ) ) {
			$maybe = maybe_unserialize( $value );
			if ( is_array( $maybe ) ) {
				$value = $maybe;
			} else {
				$value = preg_split( '/\s*,\s*/', $value );
			}
		}
		$value = is_array( $value ) ? $value : array( $value );
		$ids   = array();
		foreach ( $value as $item ) {
			$id = absint( $item );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	public function recalculate_tool_usage_counts() {
		if ( empty( $this->items ) || ! post_type_exists( 'ferramentas' ) ) {
			return;
		}

		$relationship_fields = array();
		foreach ( $this->items as $source_post_type => $config ) {
			if ( empty( $config['metas'] ) || ! is_array( $config['metas'] ) ) {
				continue;
			}
			foreach ( $config['metas'] as $meta ) {
				$type = $this->normalize_meta_type( isset( $meta['type'] ) ? $meta['type'] : 'text' );
				$key  = isset( $meta['key'] ) ? sanitize_key( $meta['key'] ) : '';
				$related = isset( $meta['related_post_type'] ) ? sanitize_key( $meta['related_post_type'] ) : '';
				if ( 'relationship' === $type && $key && 'ferramentas' === $related ) {
					$relationship_fields[] = array(
						'post_type' => sanitize_key( $source_post_type ),
						'key'       => $key,
					);
				}
			}
		}

		if ( empty( $relationship_fields ) ) {
			return;
		}

		$counts = array();

		foreach ( $relationship_fields as $field ) {
			$posts = get_posts(
				array(
					'post_type'      => $field['post_type'],
					'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( $posts as $source_id ) {
				$tool_ids = $this->normalize_relationship_ids( get_post_meta( $source_id, $field['key'], true ) );
				if ( empty( $tool_ids ) ) {
					continue;
				}

				$profile_type = $this->normalize_profile_type( get_post_meta( $source_id, 'tipo', true ) );

				foreach ( $tool_ids as $tool_id ) {
					if ( 'ferramentas' !== get_post_type( $tool_id ) ) {
						continue;
					}
					if ( empty( $counts[ $tool_id ] ) ) {
						$counts[ $tool_id ] = array( 'total' => 0, 'empresa' => 0, 'freelancer' => 0 );
					}
					$counts[ $tool_id ]['total']++;
					if ( 'empresa' === $profile_type ) {
						$counts[ $tool_id ]['empresa']++;
					} elseif ( 'freelancer' === $profile_type ) {
						$counts[ $tool_id ]['freelancer']++;
					}
				}
			}
		}

		$all_tools = get_posts(
			array(
				'post_type'      => 'ferramentas',
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $all_tools as $tool_id ) {
			$total      = isset( $counts[ $tool_id ] ) ? (int) $counts[ $tool_id ]['total'] : 0;
			$empresa    = isset( $counts[ $tool_id ] ) ? (int) $counts[ $tool_id ]['empresa'] : 0;
			$freelancer = isset( $counts[ $tool_id ] ) ? (int) $counts[ $tool_id ]['freelancer'] : 0;

			update_post_meta( $tool_id, 'tool_usage_count', (string) $total );
			update_post_meta( $tool_id, 'tool_company_count', (string) $empresa );
			update_post_meta( $tool_id, 'tool_freelancer_count', (string) $freelancer );
		}
	}

	private function normalize_profile_type( $value ) {
		$value = strtolower( remove_accents( trim( (string) $value ) ) );
		if ( 'empresa' === $value ) {
			return 'empresa';
		}
		if ( 'freelancer' === $value ) {
			return 'freelancer';
		}
		return '';
	}
}
