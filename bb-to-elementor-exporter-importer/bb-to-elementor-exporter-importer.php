<?php
/**
 * Plugin Name: Beaver Builder to Elementor Exporter Importer
 * Description: Export pages built with Beaver Builder and import them as Elementor-compatible content on another WordPress site.
 * Version: 1.0.0
 * Author: OpenAI
 * Text Domain: bb-to-elementor-exporter-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-bb-elementor-exporter.php';
require_once __DIR__ . '/includes/class-bb-elementor-importer.php';
require_once __DIR__ . '/includes/class-bb-elementor-admin.php';

new BB_Elementor_Admin();
