<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BB_Elementor_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_init', array( $this, 'handle_export_request' ) );
		add_action( 'admin_init', array( $this, 'handle_import_request' ) );
	}

	public function register_admin_page() {
		add_management_page(
			__( 'Beaver Builder to Elementor', 'bb-to-elementor-exporter-importer' ),
			__( 'Beaver Builder to Elementor', 'bb-to-elementor-exporter-importer' ),
			'manage_options',
			'bb-to-elementor',
			array( $this, 'render_page' )
		);
	}

	public function handle_export_request() {
		if ( ! isset( $_GET['page'], $_GET['bb_export_page'] ) || 'bb-to-elementor' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'bb_export_page_' . (int) $_GET['bb_export_page'] ) ) {
			wp_die( __( 'Security check failed.', 'bb-to-elementor-exporter-importer' ) );
		}

		$post_id = absint( $_GET['bb_export_page'] );
		$payload = BB_Elementor_Exporter::export_page( $post_id );
		if ( empty( $payload ) ) {
			wp_die( __( 'This page does not contain Beaver Builder content.', 'bb-to-elementor-exporter-importer' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename="beaver-builder-export-' . $post_id . '.json"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
		exit;
	}

	public function handle_import_request() {
		if ( ! isset( $_POST['bb_elementor_import'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['bb_elementor_import_nonce'] ?? '', 'bb_elementor_import' ) ) {
			wp_die( __( 'Security check failed.', 'bb-to-elementor-exporter-importer' ) );
		}

		if ( empty( $_FILES['bb_elementor_import_file']['tmp_name'] ) ) {
			add_action( 'admin_notices', function () {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Please upload a JSON export file.', 'bb-to-elementor-exporter-importer' ) );
			} );
			return;
		}

		$file_contents = file_get_contents( $_FILES['bb_elementor_import_file']['tmp_name'] );
		if ( false === $file_contents ) {
			add_action( 'admin_notices', function () {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'The file could not be read.', 'bb-to-elementor-exporter-importer' ) );
			} );
			return;
		}

		$payload = json_decode( $file_contents, true );
		if ( ! is_array( $payload ) ) {
			add_action( 'admin_notices', function () {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'The JSON file is invalid.', 'bb-to-elementor-exporter-importer' ) );
			} );
			return;
		}

		$target_post_id = absint( $_POST['bb_target_page'] ?? 0 );
		$result = BB_Elementor_Importer::import_from_payload( $payload, $target_post_id );
		if ( is_wp_error( $result ) ) {
			add_action( 'admin_notices', function () use ( $result ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $result->get_error_message() ) );
			} );
			return;
		}

		$redirect_url = add_query_arg(
			array(
				'page'      => 'bb-to-elementor',
				'updated'   => 1,
				'post_id'   => (int) $result,
			),
			admin_url( 'tools.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function render_page() {
		$pages = BB_Elementor_Exporter::get_pages();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Beaver Builder to Elementor', 'bb-to-elementor-exporter-importer' ); ?></h1>

			<?php if ( ! empty( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'The existing page content was updated successfully.', 'bb-to-elementor-exporter-importer' ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Export Beaver Builder pages', 'bb-to-elementor-exporter-importer' ); ?></h2>
			<?php if ( empty( $pages ) ) : ?>
				<p><?php esc_html_e( 'No pages with Beaver Builder data were found.', 'bb-to-elementor-exporter-importer' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Page', 'bb-to-elementor-exporter-importer' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bb-to-elementor-exporter-importer' ); ?></th>
							<th><?php esc_html_e( 'Action', 'bb-to-elementor-exporter-importer' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $pages as $page ) : ?>
						<tr>
							<td><?php echo esc_html( get_the_title( $page ) ); ?></td>
							<td><?php echo esc_html( get_post_status( $page ) ); ?></td>
							<td>
								<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'tools.php?page=bb-to-elementor&bb_export_page=' . $page->ID ), 'bb_export_page_' . $page->ID ) ); ?>"><?php esc_html_e( 'Export JSON', 'bb-to-elementor-exporter-importer' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Import JSON into an existing page', 'bb-to-elementor-exporter-importer' ); ?></h2>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'bb_elementor_import', 'bb_elementor_import_nonce' ); ?>
				<input type="hidden" name="bb_elementor_import" value="1" />
				<p>
					<label for="bb_elementor_import_file"><?php esc_html_e( 'Choose exported JSON file', 'bb-to-elementor-exporter-importer' ); ?></label><br />
					<input type="file" name="bb_elementor_import_file" id="bb_elementor_import_file" accept="application/json,.json" required />
				</p>
				<p id="bb-target-page-wrapper" style="display: none;">
					<label for="bb_target_page"><?php esc_html_e( 'Select the destination page', 'bb-to-elementor-exporter-importer' ); ?></label><br />
					<select name="bb_target_page" id="bb_target_page" style="min-width: 320px;">
						<option value="0"><?php esc_html_e( 'Auto-match by title/slug', 'bb-to-elementor-exporter-importer' ); ?></option>
						<?php foreach ( get_pages( array( 'post_status' => 'any' ) ) as $page ) : ?>
							<option value="<?php echo esc_attr( $page->ID ); ?>"><?php echo esc_html( get_the_title( $page ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update existing page', 'bb-to-elementor-exporter-importer' ); ?>" />
				</p>
			</form>
			<script>
				(function () {
					var fileInput = document.getElementById('bb_elementor_import_file');
					var pageWrapper = document.getElementById('bb-target-page-wrapper');
					if (!fileInput || !pageWrapper) {
						return;
					}
					var toggle = function () {
						pageWrapper.style.display = fileInput.files && fileInput.files.length ? 'block' : 'none';
					};
					fileInput.addEventListener('change', toggle);
					toggle();
				})();
			</script>
		</div>
		<?php
	}
}
