<?php // phpcs:ignore Squiz.Commenting.FileComment.Missing
/**
 * Plugin Name: Subresource Integrity (SRI) Manager
 * Plugin URI: https://maymay.net/blog/projects/wp-sri/
 * Description: A utility to easily add SRI security checks to your generated WordPress pages. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WordPress%20Subresource%20Integrity%20Plugin&amp;item_number=wp-sri&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the maintainer">donate</a>. &hearts; Thank you!</strong>
 * Author: maymay
 * Author URI: https://maymay.net/
 * Version: 0.5.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-sri
 * Domain Path: /languages
 */

require_once dirname( __FILE__ ) . '/class-wp-sri-plugin.php';

new WP_SRI_Plugin();
