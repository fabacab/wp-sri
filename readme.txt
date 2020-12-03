=== Plugin Name ===
Contributors: maymay
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=TJLPJYXHSRBEE&lc=US&item_name=WordPress%20Subresource%20Integrity%20Plugin&item_number=wp-sri&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: security, subresource integrity, SRI, MITM, mitigation, DDoS prevention
Requires at least: 4.1
Tested up to: 5.6
Stable tag: trunk
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Adds Subresource Integrity (SRI) attributes to your page's elements for better protection against JavaScript DDoS attacks.

== Description ==

A WordPress plugin for easily adding a [Subresource Integrity (SRI)](//www.w3.org/TR/SRI/) declaration to any third-party content your pages load. The standards-based `integrity` attribute is a defense-in-depth best practice currently making its way into browsers. This plugin closely tracks the W3C draft.

Currently, the plugin automatically detects any third-party resources (like JavaScript libraries) and will make a SHA-256 hash of the content. It remembers this hash (until you uninstall the plugin or delete the hash from the admin interface), and modifies your page's `<script>` and `<link>` elements on-the-fly. This way, your visitor's Web browsers can automatically ensure that the specific library you're using is the one they're loading.

Using this plugin can dramatically reduce the liklihood that visitors to your site will be strong-armed into participating in an HTTP DDoS attack. For more information, see "[An introduction to JavaScript-based DDoS](https://blog.cloudflare.com/an-introduction-to-javascript-based-ddos/)" by Nick Sullivan.

Future versions of this plugin will also provide an easy-to-use interface for site administrators to maintain a customized list of resource hashes, and to trigger on-demand integrity checks of these resources.

This plugin is still somewhat skeletal. Feature requests and patches are welcome! Please provide a test case with your patch. See the `tests` subdirectory for unit tests.

== Installation ==

1. Upload the unzipped `wp-sri` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= WP-SRI breaks my plugin/theme. How can I prevent it from blocking my assets? =

If you're a site administrator, you can manually exclude specific resources by their URL from the Subresource Integrity Manager screen under Tools &rarr; Subresource Integrity Manager.

If you're a plugin or theme author, you can use the `option_wp_sri_excluded_hashes` filter hook to dynamically whitelist assets. Please only do this for assets that are truly personalized, that is, only for assets whose URL is always the same but whose content is different for each user or page load.

For example, to ensure that the URL at `https://example.com/personalized_content` is never checked for integrity with SRI attributes, use the following PHP code:

    function example_never_add_integrity_checking( $items ) {
        $items[] = 'https://example.com/personalized_content';
        return $items;
    }
    add_action( 'option_wp_sri_excluded_hashes', 'example_never_add_integrity_checking' );

Learn more [about this filter hook](https://developer.wordpress.org/reference/hooks/option_option/).

== Change log ==

= Version 0.4.0 =

* Stricter parsing for stylesheet tags; the `filterTag` function now requires a third parameter.

= Version 0.3.0 =

* [Feature](https://wordpress.org/support/topic/breaks-google-fonts?replies=2): Add ability to exclude URLs. Useful when SRI attributes block personalized assets.

= Version 0.2.2 =

* [Bugfix](https://github.com/fabacab/wp-sri/issues/1): Load plugin `textdomain` files to prepare for translation.

= Version 0.2.1 =

* Add the `crossorigin="anonymous"` attribute/value pair to modified elements to enable Firefox 43's handling of integrity checks.

= Version 0.2 =

* Feature: A simple administrative interface can be found under the "Subresource Integrity Manager" option in your WordPress Tools menu. This interface allows you to view the URL and hash pairs currently known by your site, and to delete them. Deleting a known hash will cause WordPress to refetch and rehash the resource when it is next requested.

= Version 0.1 =

* Initial release.

== Other notes ==

If you like this plugin, **please consider [making a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WordPress%20Subresource%20Integrity%20Plugin&amp;item_number=wp-sri&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted) for your use of the plugin**, or better yet, contributing directly to [my's Cyberbusking fund](http://Cyberbusking.org/). Your support is appreciated!
