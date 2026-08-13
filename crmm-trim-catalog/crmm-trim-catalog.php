<?php
/**
 * Plugin Name: CRMM Trim Catalog
 * Description: Manages CRMM trim profiles, part-number assignment, catalog fields, and legacy imports.
 * Version: 0.1.0
 * Author: Cache River Mill & MetalWorks
 * Requires PHP: 8.1
 * Text Domain: crmm-trim-catalog
 */

defined( 'ABSPATH' ) || exit;

define( 'CRMM_TRIM_CATALOG_VERSION', '0.1.0' );
define( 'CRMM_TRIM_CATALOG_FILE', __FILE__ );
define( 'CRMM_TRIM_CATALOG_DIR', plugin_dir_path( __FILE__ ) );

require_once CRMM_TRIM_CATALOG_DIR . 'includes/class-crmm-trim-post-type.php';
require_once CRMM_TRIM_CATALOG_DIR . 'includes/class-crmm-trim-taxonomies.php';
require_once CRMM_TRIM_CATALOG_DIR . 'includes/class-crmm-trim-number-registry.php';
require_once CRMM_TRIM_CATALOG_DIR . 'includes/class-crmm-trim-fields.php';
require_once CRMM_TRIM_CATALOG_DIR . 'includes/class-crmm-trim-importer.php';
require_once CRMM_TRIM_CATALOG_DIR . 'includes/class-crmm-trim-admin.php';
require_once CRMM_TRIM_CATALOG_DIR . 'includes/class-crmm-trim-plugin.php';

register_activation_hook( __FILE__, array( 'CRMM_Trim_Plugin', 'activate' ) );

CRMM_Trim_Plugin::instance()->boot();
