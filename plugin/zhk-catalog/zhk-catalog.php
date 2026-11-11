<?php
/**
 * Plugin Name: ZHK Catalog
 * Description: Каталог квартир жилого комплекса: квартиры с параметрами, фильтры через REST API, заявки, импорт CSV.
 * Version:     1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.4
 * Text Domain: zhk
 */

defined( 'ABSPATH' ) || exit;

define( 'ZHK_VERSION', '1.0.0' );
define( 'ZHK_DIR', __DIR__ );
define( 'ZHK_URL', plugin_dir_url( __FILE__ ) );

require ZHK_DIR . '/includes/class-apartment.php';
require ZHK_DIR . '/includes/class-rest.php';
require ZHK_DIR . '/includes/class-leads.php';
require ZHK_DIR . '/includes/class-admin.php';
require ZHK_DIR . '/includes/class-importer.php';
require ZHK_DIR . '/includes/demo.php';

add_action( 'init', array( 'ZHK_Apartment', 'register' ) );
add_action( 'init', array( 'ZHK_Leads', 'register' ) );
add_action( 'rest_api_init', array( 'ZHK_Rest', 'register_routes' ) );

if ( is_admin() ) {
	ZHK_Admin::init();
	ZHK_Importer::init();
}

register_activation_hook(
	__FILE__,
	static function () {
		ZHK_Apartment::register();
		ZHK_Leads::register();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
