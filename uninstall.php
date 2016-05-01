<?php
/**
 * Subresource Integrity (SRI) Manager  uninstaller
 *
 * @package plugin
 */

// Don't execute any uninstall code unless WordPress core requests it.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit(); }

delete_option('wp_sri_known_hashes');
delete_option('wp_sri_excluded_hashes');
delete_metadata('user', 0, 'wp_sri_hashes_per_page', '', true);
delete_metadata('user', 0, 'managetools_page_wp_sri_admincolumnshidden', '', true);
