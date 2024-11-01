<?php
/*
Plugin Name: StoreContrl WP Connection
Plugin URI:  http://www.arture.nl/storecontrl
Description: The Wordpress plugin for connecting Woocommerce with StoreContrl Cloud. With the synchronizing cronjobs your products will be automatically processed, images added, and the categories set. Every 5 minutes all stock changes are processed. We provide a up-to-date plugin, easy setup and always the best support.
Version:     4.1.0
Author:      Arture
Author URI:  https://arture.nl/
License:     Paid
Text Domain: storecontrl-wp-connection
Domain Path: /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

function storecontrl_plugin_activation() {

    // Deactivate basic version for conflicts
    deactivate_plugins('storecontrl-wp-connection/storecontrl-wp-connection.php');

	// Create storecontrl directories
	storecontrl_base_directory();
	storecontrl_log_directory();
	storecontrl_import_directory();
}
register_activation_hook(__FILE__, 'storecontrl_plugin_activation');

function storecontrl_plugin_deactivation() {

	$logging = new StoreContrl_WP_Connection_Logging();
	$logging->log_file_write( 'Notice | Plugin | Plugin deactivated' );
}
register_deactivation_hook(__FILE__, 'storecontrl_plugin_deactivation');

function storecontrl_base_directory() {

    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'];
    $upload_dir = $upload_dir . '/storecontrl';
    if (! is_dir($upload_dir)) {
       mkdir( $upload_dir, 0770 );
    }
}

function storecontrl_log_directory() {

    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'];
    $upload_dir = $upload_dir . '/storecontrl/logs';
    if (! is_dir($upload_dir)) {
       mkdir( $upload_dir, 0770 );
    }
}

function storecontrl_import_directory() {

    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'];
    $upload_dir = $upload_dir . '/storecontrl/imports';
    if (! is_dir($upload_dir)) {
       mkdir( $upload_dir, 0770 );
    }

	$upload = wp_upload_dir();
	$upload_dir = $upload['basedir'];
	$upload_dir = $upload_dir . '/storecontrl/imports/temp';
	if (! is_dir($upload_dir)) {
		mkdir( $upload_dir, 0770 );
	}
}

/* Set constant path to the plugin directory. */
define( 'STORECONTRL_WP_CONNECTION_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'STORECONTRL_WP_CONNECTION_PLUGIN_BASENAME', plugin_basename(__FILE__) );

function run_storecontrl_wp_connection() {

	require_once STORECONTRL_WP_CONNECTION_PLUGIN_DIR . 'includes/class-storecontrl-wp-connection.php';

	$storecontrl_wp_connection = new StoreContrl_WP_Connection();
	$storecontrl_wp_connection->run();
}

run_storecontrl_wp_connection();
