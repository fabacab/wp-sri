<?php
/**
 * Subresource Integrity (SRI) Manager  uninstaller
 *
 * @package plugin
 */

// Don't execute any uninstall code unless WordPress core requests it.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit(); }

delete_option('wp_sri_known_hashes');
