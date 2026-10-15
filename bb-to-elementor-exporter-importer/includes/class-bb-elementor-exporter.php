<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_Elementor_Exporter {

	public static function get_pages() {
		$builder_ids = self::get_builder_post_ids();
		if ( empty( $builder_ids ) ) {
			return array();
		}

		$builder_pages = array();
		foreach ( $builder_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post && in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
				$builder_pages[] = $post;
			}
		}

		usort(
			$builder_pages,
			static function ( $a, $b ) {
				return strcmp( get_the_title( $a ), get_the_title( $b ) );
			}
		);

		return $builder_pages;
	}

	private static function get_builder_post_ids() {
		global $wpdb;

		$query = "
			SELECT DISTINCT pm.post_id
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_status IS NOT NULL
			  AND (
				pm.meta_key LIKE '%fl_builder%'
				OR pm.meta_key LIKE '%builder%'
				OR pm.meta_key LIKE '%beaver%'
			  )
			  AND TRIM(COALESCE(pm.meta_value, '')) <> ''
		";

		$ids = $wpdb->get_col( $query );
		if ( is_array( $ids ) ) {
			return array_map( 'intval', $ids );
		}

		return array();
	}

	private static function has_builder_data( $post_id ) {
		return in_array( (int) $post_id, self::get_builder_post_ids(), true );
	}

	public static function export_page( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$data = self::get_builder_data( $post_id );
		if ( empty( $data ) ) {
			return false;
		}

		$html = self::get_rendered_builder_html( $post_id );
		if ( '' === trim( (string) $html ) ) {
			$html = self::render_fallback_html( $post_id, $data );
		}

		$elementor_data = self::build_html_section( $html );
		if ( empty( $elementor_data ) ) {
			$elementor_data = self::convert_builder_to_elementor( $data );
		}
		$css = self::get_builder_css( $post_id );

		$payload = array(
			'version'      => '1.0',
			'created_at'   => gmdate( 'c' ),
			'source'       => 'beaver-builder',
			'page'         => array(
				'id'    => (int) $post_id,
				'title' => $post->post_title,
				'slug'  => $post->post_name,
			),
			'elementor_data' => $elementor_data,
			'html'           => $html,
			'css'            => $css,
		);

		return $payload;
	}

	private static function get_builder_data( $post_id ) {
		$meta_keys = array(
			'_fl_builder_data',
			'_fl_builder_draft_data',
			'fl_builder_data',
			'fl_builder_draft_data',
			'_fl_builder_data_v2',
			'_fl_builder_data_legacy',
		);

		foreach ( $meta_keys as $meta_key ) {
			$data = get_post_meta( $post_id, $meta_key, true );
			$normalized = self::normalize_builder_value( $data );
			if ( ! empty( $normalized ) ) {
				return $normalized;
			}
		}

		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND ( meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s )",
			(int) $post_id,
			'%%%fl_builder%%',
			'%%%builder%%',
			'%%%beaver%%',
			'%%bb_%%'
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$normalized = self::normalize_builder_value( $row['meta_value'] ?? '' );
				if ( ! empty( $normalized ) ) {
					return $normalized;
				}
			}
		}

		if ( function_exists( 'FLBuilderModel' ) ) {
			$data = FLBuilderModel::get_post_data( $post_id );
			if ( is_array( $data ) && ! empty( $data ) ) {
				return $data;
			}
		}

		return array();
	}

	private static function normalize_builder_value( $value ) {
		if ( is_array( $value ) && ! empty( $value ) ) {
			return $value;
		}

		if ( is_object( $value ) ) {
			$decoded = json_decode( wp_json_encode( $value ), true );
			if ( is_array( $decoded ) && ! empty( $decoded ) ) {
				return $decoded;
			}
		}

		if ( ! is_string( $value ) ) {
			return array();
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) && ! empty( $decoded ) ) {
			return $decoded;
		}

		$unserialized = maybe_unserialize( $value );
		if ( is_array( $unserialized ) && ! empty( $unserialized ) ) {
			return $unserialized;
		}

		return array();
	}

	private static function get_builder_css( $post_id ) {
		$css = get_post_meta( $post_id, '_fl_builder_css', true );
		if ( ! empty( $css ) ) {
			return $css;
		}

		$css = get_post_meta( $post_id, '_fl_builder_css_print', true );
		if ( ! empty( $css ) ) {
			return $css;
		}

		return '';
	}

	public static function convert_builder_to_elementor( $data ) {
		$elements = array();
		if ( ! is_array( $data ) ) {
			return $elements;
		}

		foreach ( $data as $node ) {
			$converted = self::convert_node( $node );
			if ( ! empty( $converted ) ) {
				$elements[] = $converted;
			}
		}

		return $elements;
	}

	private static function convert_node( $node ) {
		if ( ! is_array( $node ) ) {
			return array();
		}

		$type     = isset( $node['type'] ) ? sanitize_key( (string) $node['type'] ) : '';
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();

		if ( 'row' === $type ) {
			$element = array(
				'id'      => 'bb-section-' . wp_unique_id(),
				'elType'  => 'section',
				'settings' => array(
					'content_width' => 'full',
					'gap'           => 'no',
					'padding'       => array(
						'top'    => '0',
						'right'  => '0',
						'bottom' => '0',
						'left'   => '0',
					),
				),
				'elements' => array(),
			);

			foreach ( $children as $child ) {
				$converted = self::convert_node( $child );
				if ( ! empty( $converted ) ) {
					$element['elements'][] = $converted;
				}
			}

			return $element;
		}

		if ( 'column' === $type ) {
			$column = array(
				'id'      => 'bb-column-' . wp_unique_id(),
				'elType'  => 'column',
				'settings' => array(
					'_column_size' => self::parse_column_size( $settings ),
				),
				'elements' => array(),
			);

			foreach ( $children as $child ) {
				$converted = self::convert_node( $child );
				if ( ! empty( $converted ) ) {
					$column['elements'][] = $converted;
				}
			}

			return $column;
		}

		if ( in_array( $type, array( 'heading', 'text', 'text-editor', 'button', 'photo', 'image', 'icon', 'separator', 'html', 'module' ), true ) ) {
			return self::convert_widget( $type, $settings, $children );
		}

		if ( ! empty( $children ) ) {
			$container = array(
				'id'      => 'bb-wrapper-' . wp_unique_id(),
				'elType'  => 'section',
				'settings' => array(),
				'elements' => array(),
			);

			foreach ( $children as $child ) {
				$converted = self::convert_node( $child );
				if ( ! empty( $converted ) ) {
					$container['elements'][] = $converted;
				}
			}

			return $container;
		}

		return array();
	}

	private static function convert_widget( $type, $settings, $children ) {
		$widget = array(
			'id' => 'bb-widget-' . wp_unique_id(),
			'elType' => 'widget',
			'widgetType' => 'html',
			'settings' => array(),
		);

		if ( 'heading' === $type ) {
			$widget['widgetType'] = 'heading';
			$widget['settings'] = array(
				'title' => self::clean_text( $settings['title'] ?? $settings['heading'] ?? '' ),
				'header_size' => self::normalize_tag( $settings['tag'] ?? 'h2' ),
				'align' => self::normalize_alignment( $settings['align'] ?? '' ),
			);
			return $widget;
		}

		if ( in_array( $type, array( 'text', 'text-editor' ), true ) ) {
			$widget['widgetType'] = 'text-editor';
			$widget['settings'] = array(
				'editor' => self::clean_html( $settings['text'] ?? $settings['content'] ?? '' ),
			);
			return $widget;
		}

		if ( 'button' === $type ) {
			$widget['widgetType'] = 'button';
			$link = self::normalize_url( $settings['link'] ?? $settings['url'] ?? '' );
			$widget['settings'] = array(
				'text' => self::clean_text( $settings['text'] ?? 'Read more' ),
				'link' => array(
					'url' => $link,
					'is_external' => false,
					'nofollow' => false,
				),
				'size' => 'sm',
				'icon_align' => 'left',
			);
			return $widget;
		}

		if ( in_array( $type, array( 'photo', 'image' ), true ) ) {
			$widget['widgetType'] = 'image';
			$image_url = self::normalize_url( $settings['src'] ?? $settings['url'] ?? '' );
			$widget['settings'] = array(
				'image' => array(
					'url' => $image_url,
				),
				'image_size' => 'full',
			);
			return $widget;
		}

		if ( 'icon' === $type ) {
			$widget['widgetType'] = 'icon';
			$widget['settings'] = array(
				'view' => 'default',
				'icon' => 'fa fa-star',
				'size' => 'lg',
			);
			return $widget;
		}

		if ( 'separator' === $type ) {
			$widget['widgetType'] = 'divider';
			$widget['settings'] = array(
				'style' => 'solid',
				'weight' => 'thin',
				'color' => '#ddd',
			);
			return $widget;
		}

		if ( 'html' === $type || 'module' === $type ) {
			$widget['widgetType'] = 'html';
			$widget['settings'] = array(
				'html' => self::render_module_html( $settings, $children ),
			);
			return $widget;
		}

		$widget['settings'] = array(
			'html' => self::render_module_html( $settings, $children ),
		);
		return $widget;
	}

	private static function get_rendered_builder_html( $post_id ) {
		if ( class_exists( 'FLBuilder' ) && method_exists( 'FLBuilder', 'render_content' ) ) {
			ob_start();
			FLBuilder::render_content( $post_id );
			$output = ob_get_clean();
			if ( is_string( $output ) && '' !== trim( $output ) ) {
				return $output;
			}
		}

		if ( function_exists( 'fl_render_builder_content' ) ) {
			ob_start();
			fl_render_builder_content( $post_id );
			$output = ob_get_clean();
			if ( is_string( $output ) && '' !== trim( $output ) ) {
				return $output;
			}
		}

		global $post;
		$saved_post = $post;
		$post = get_post( $post_id );
		if ( $post ) {
			$content = apply_filters( 'the_content', $post->post_content );
			$post = $saved_post;
			if ( is_string( $content ) && '' !== trim( $content ) ) {
				return $content;
			}
		}
		if ( $saved_post ) {
			$post = $saved_post;
		}

		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			return '';
		}

		$response = wp_remote_get( $permalink, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return '';
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $body );
		libxml_clear_errors();
		$cleaned_html = self::extract_main_content_html( $dom );
		if ( '' !== trim( $cleaned_html ) ) {
			return $cleaned_html;
		}

		return $body;
	}

	private static function extract_main_content_html( $dom ) {
		$xpath = new DOMXPath( $dom );
		$expression = "//*[self::main or self::article or self::div[ @id='content' or @id='main' or @id='main-content' ] or self::div[contains(concat(' ', normalize-space(@class), ' '), ' fl-builder-content ')] or self::div[contains(concat(' ', normalize-space(@class), ' '), ' entry-content ')] or self::div[contains(concat(' ', normalize-space(@class), ' '), ' site-content ')] or self::div[contains(concat(' ', normalize-space(@class), ' '), ' content-area ')] or self::div[contains(concat(' ', normalize-space(@class), ' '), ' page-content ')] ]";
		$nodes = $xpath->query( $expression );
		if ( ! $nodes || 0 === $nodes->length ) {
			return '';
		}

		foreach ( $nodes as $node ) {
			if ( self::is_header_footer_container( $node ) ) {
				continue;
			}

			$html = $dom->saveHTML( $node );
			if ( is_string( $html ) && '' !== trim( $html ) ) {
				return $html;
			}
		}

		return '';
	}

	private static function is_header_footer_container( $node ) {
		if ( ! $node instanceof DOMElement ) {
			return false;
		}

		$tag = strtolower( $node->tagName );
		if ( in_array( $tag, array( 'header', 'footer', 'nav' ), true ) ) {
			return true;
		}

		$class = strtolower( (string) $node->getAttribute( 'class' ) );
		foreach ( array( 'header', 'site-header', 'nav', 'navigation', 'footer', 'site-footer' ) as $token ) {
			if ( false !== strpos( $class, $token ) ) {
				return true;
			}
		}

		return false;
	}

	private static function build_html_section( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return array();
		}

		return array(
			array(
				'id' => 'bb-export-section-' . wp_unique_id(),
				'elType' => 'section',
				'settings' => array( 'content_width' => 'full' ),
				'elements' => array(
					array(
						'id' => 'bb-export-column-' . wp_unique_id(),
						'elType' => 'column',
						'settings' => array( '_column_size' => 100 ),
						'elements' => array(
							array(
								'id' => 'bb-export-html-' . wp_unique_id(),
								'elType' => 'widget',
								'widgetType' => 'html',
								'settings' => array(
									'html' => $html,
								),
							),
						),
					),
				),
			),
		);
	}

	private static function render_fallback_html( $post_id, $data ) {
		$post = get_post( $post_id );
		if ( $post && ! empty( $post->post_content ) ) {
			return (string) apply_filters( 'the_content', $post->post_content );
		}

		if ( ! is_array( $data ) ) {
			return '';
		}

		ob_start();
		foreach ( $data as $node ) {
			echo self::render_node_html( $node );
		}
		$output = ob_get_clean();
		if ( '' !== trim( (string) $output ) ) {
			return $output;
		}

		return '';
	}

	private static function render_node_html( $node ) {
		if ( ! is_array( $node ) ) {
			return '';
		}

		$type     = isset( $node['type'] ) ? sanitize_key( (string) $node['type'] ) : '';
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
		$output   = '';

		if ( 'row' === $type || 'column' === $type ) {
			foreach ( $children as $child ) {
				$output .= self::render_node_html( $child );
			}
			return $output;
		}

		if ( ! empty( $settings['text'] ) ) {
			return '<div class="bb-exported-text">' . self::clean_html( $settings['text'] ) . '</div>';
		}

		if ( ! empty( $settings['title'] ) ) {
			return '<h2>' . self::clean_html( $settings['title'] ) . '</h2>';
		}

		if ( ! empty( $settings['src'] ) ) {
			return '<img src="' . esc_url( self::normalize_url( $settings['src'] ) ) . '" alt="" />';
		}

		if ( ! empty( $children ) ) {
			foreach ( $children as $child ) {
				$output .= self::render_node_html( $child );
			}
			return $output;
		}

		return '';
	}

	private static function render_module_html( $settings, $children ) {
		$html = '';
		if ( ! empty( $settings['text'] ) ) {
			$html .= '<div class="bb-converted-module">' . self::clean_html( $settings['text'] ) . '</div>';
		}
		if ( ! empty( $settings['title'] ) ) {
			$html .= '<h2>' . self::clean_html( $settings['title'] ) . '</h2>';
		}
		if ( ! empty( $settings['src'] ) ) {
			$html .= '<img src="' . esc_url( self::normalize_url( $settings['src'] ) ) . '" alt="" />';
		}
		foreach ( $children as $child ) {
			$html .= self::render_node_html( $child );
		}
		return $html;
	}

	private static function parse_column_size( $settings ) {
		if ( ! empty( $settings['width'] ) ) {
			return (float) str_replace( '%', '', (string) $settings['width'] );
		}

		return 50;
	}

	private static function normalize_tag( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$allowed = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
		return in_array( $value, $allowed, true ) ? $value : 'h2';
	}

	private static function normalize_alignment( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$allowed = array( 'left', 'center', 'right' );
		return in_array( $value, $allowed, true ) ? $value : 'left';
	}

	private static function normalize_url( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', (string) $value ) ) {
			return esc_url_raw( $value );
		}
		if ( 0 === strpos( (string) $value, '/' ) ) {
			return esc_url_raw( home_url( $value ) );
		}
		return esc_url_raw( $value );
	}

	private static function clean_text( $value ) {
		return wp_strip_all_tags( (string) $value );
	}

	private static function clean_html( $value ) {
		return wp_kses_post( (string) $value );
	}
}
