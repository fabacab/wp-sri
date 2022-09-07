<?php
/**
 * Subresource Integrity (SRI) Manager  uninstaller
 *
 * @package plugin
 */

// Don't execute any uninstall code unless WordPress core requests it.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Make sure plugin class is usable.
if ( ! class_exists( 'WP_SRI_Plugin' ) ) {
	require_once __DIR__ . '/class-wp-sri.php';
}

delete_option( WP_SRI_Plugin::$prefix . 'known_hashes' );
delete_option( WP_SRI_Plugin::$prefix . 'excluded_hashes' );
delete_metadata( 'user', 0, WP_SRI_Plugin::$prefix . 'hashes_per_page', '', true );
delete_metadata( 'user', 0, 'managetools_page_' . WP_SRI_Plugin::$prefix . 'admincolumnshidden', '', true );
