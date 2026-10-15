<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_Elementor_Importer {

	public static function import_from_payload( $payload, $target_post_id = 0 ) {
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'invalid_payload', __( 'The uploaded file is not valid JSON.', 'bb-to-elementor-exporter-importer' ) );
		}

		if ( empty( $payload['page']['title'] ) ) {
			return new WP_Error( 'missing_title', __( 'The export file does not contain a page title.', 'bb-to-elementor-exporter-importer' ) );
		}

		$post_id = self::resolve_target_post_id( $payload, $target_post_id );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'missing_post', __( 'The target page does not exist.', 'bb-to-elementor-exporter-importer' ) );
		}

		$html = self::prepare_html( $payload['html'] ?? '' );
		if ( '' === $html ) {
			$html = self::prepare_html( self::render_payload_html( $payload ) );
		}

		$update = array(
			'ID'           => $post_id,
			'post_title'   => sanitize_text_field( $payload['page']['title'] ),
			'post_name'    => sanitize_title( $payload['page']['slug'] ?? $payload['page']['title'] ),
			'post_content' => $html,
		);

		$updated = wp_update_post( $update );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$elementor_data = self::build_elementor_data( $payload );
		if ( ! empty( $elementor_data ) ) {
			update_post_meta( $post_id, '_elementor_data', wp_json_encode( $elementor_data ) );
			update_post_meta( $post_id, '_elementor_version', '3.13.0' );
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		}

		if ( ! empty( $payload['css'] ) ) {
			update_post_meta( $post_id, '_elementor_css', wp_strip_all_tags( $payload['css'] ) );
		}

		update_post_meta( $post_id, '_bb_elementor_import_source', 'beaver-builder' );
		return $post_id;
	}

	private static function render_payload_html( $payload ) {
		if ( ! empty( $payload['html'] ) ) {
			return (string) $payload['html'];
		}

		if ( ! empty( $payload['elementor_data'] ) ) {
			return self::convert_elementor_to_html( $payload['elementor_data'] );
		}

		return '<div class="bb-imported-content"></div>';
	}

	private static function convert_elementor_to_html( $data ) {
		if ( ! is_array( $data ) ) {
			return '';
		}

		$items = array();
		foreach ( $data as $node ) {
			$items[] = self::flatten_elementor_node( $node );
		}
		return implode( '', array_filter( $items ) );
	}

	private static function flatten_elementor_node( $node ) {
		if ( ! is_array( $node ) ) {
			return '';
		}

		$html = '';
		if ( ! empty( $node['settings']['html'] ) ) {
			$html .= (string) $node['settings']['html'];
		}
		if ( ! empty( $node['settings']['editor'] ) ) {
			$html .= '<div>' . wp_kses_post( (string) $node['settings']['editor'] ) . '</div>';
		}
		if ( ! empty( $node['settings']['title'] ) ) {
			$html .= '<h2>' . esc_html( (string) $node['settings']['title'] ) . '</h2>';
		}
		if ( ! empty( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				$html .= self::flatten_elementor_node( $child );
			}
		}
		return $html;
	}

	private static function build_elementor_data( $payload ) {
		$html = '';
		if ( ! empty( $payload['html'] ) ) {
			$html = (string) $payload['html'];
		} elseif ( ! empty( $payload['elementor_data'] ) ) {
			$html = self::convert_elementor_to_html( $payload['elementor_data'] );
		}

		if ( '' === trim( $html ) ) {
			return array();
		}

		return array(
			array(
				'id' => 'bb-import-section-' . wp_unique_id(),
				'elType' => 'section',
				'settings' => array( 'content_width' => 'full' ),
				'elements' => array(
					array(
						'id' => 'bb-import-column-' . wp_unique_id(),
						'elType' => 'column',
						'settings' => array( '_column_size' => 100 ),
						'elements' => array(
							array(
								'id' => 'bb-import-widget-' . wp_unique_id(),
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

	private static function resolve_target_post_id( $payload, $target_post_id = 0 ) {
		$target_post_id = absint( $target_post_id );
		if ( $target_post_id > 0 ) {
			$post = get_post( $target_post_id );
			if ( $post ) {
				return $target_post_id;
			}
		}

		$title = sanitize_text_field( $payload['page']['title'] ?? '' );
		$slug  = sanitize_title( $payload['page']['slug'] ?? $title );

		if ( ! empty( $title ) ) {
			$post = get_page_by_title( $title, OBJECT, array( 'page', 'post' ) );
			if ( $post ) {
				return $post->ID;
			}
		}

		if ( ! empty( $slug ) ) {
			$post = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
			if ( $post ) {
				return $post->ID;
			}
		}

		return new WP_Error( 'page_not_found', __( 'No existing page was found to update. Please pick the destination page in the form.', 'bb-to-elementor-exporter-importer' ) );
	}

	private static function prepare_html( $html ) {
		return wp_kses_post( (string) $html );
	}
}
