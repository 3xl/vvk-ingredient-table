<?php
/**
 * Plugin Name:       VVK Ingredients Table
 * Plugin URI:        https://vasavasakitchen.com/
 * Description:       Structured ingredient tables for recipe posts: REST API, React admin UI, shortcode and automatic placement around the content.
 * Version:           2.2.2
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Claudio Geraci
 * Author URI:        https://3xlstudio.com
 * License:           GPL-2.0-or-later
 * Text Domain:       vvkit
 * Domain Path:       /languages
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VVKIT_VERSION', '2.2.2' );
define( 'VVKIT_PLUGIN_FILE', __FILE__ );
define( 'VVKIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VVKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once VVKIT_PLUGIN_DIR . 'includes/Autoloader.php';

VVKit\Autoloader::register();

register_activation_hook( __FILE__, [ VVKit\Install::class, 'activate' ] );

add_action( 'plugins_loaded', static function (): void {
	VVKit\Plugin::instance()->boot();
} );
