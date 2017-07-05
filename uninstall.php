<?php
/**
 * Subresource Integrity (SRI) Manager uninstaller.
 *
 * @package plugin
 */

// Don't execute any uninstall code unless WordPress core requests it.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit(); }

// Delete all plugin options.
delete_option( 'wp_sri_known_hashes' );
delete_option( 'wp_sri_excluded_hashes' );
//delete_option( 'wp_sri_fallback_by_hashes' );
delete_metadata( 'user', 0, 'wp_sri_hashes_per_page', '', true );
delete_metadata( 'user', 0, 'managetools_page_wp_sri_admincolumnshidden', '', true );

// Delete all cache files.
array_map( 'unlink', glob( WP_CONTENT_DIR . '/wp-sri-cache/*' ) );
unlink( WP_CONTENT_DIR . '/wp-sri-cache' );
