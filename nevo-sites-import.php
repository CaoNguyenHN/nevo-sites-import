<?php
/**
 * Plugin Name: Nevo Sites Import
 * Plugin URI: http://wordpress.org/plugins/nevo-sites-import
 * Description: Collection of ready-to-use templates (demo sites) built for Elementor and Gutenberg.
 * Version: 0.0.1
 * Author: Nevo WordPress Theme
 * Author URI: https://nevothemes.com/#about
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nevo-sites-import
 * Requires at least: 5.0
 * Tested up to: 7.1
 * Requires PHP: 5.6
 *
 * @package Nevo Sites Import
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NEVO_SITES_IMPORT_VERSION', '0.0.1' );

define( 'NEVO_SITES_IMPORT_DIR', plugin_dir_path( __FILE__ ) );

define( 'NEVO_SITES_IMPORT_URI', plugins_url( '/', __FILE__ ) );

define( 'NEVO_SITES_IMPORT_INCLUDES_DIR', trailingslashit( NEVO_SITES_IMPORT_DIR ) . 'includes' );

if ( is_admin() ) {
	require_once trailingslashit( NEVO_SITES_IMPORT_INCLUDES_DIR ) . 'class-nevo-sites-import.php';
}
