=== Plugin Name ===
Contributors: meitar
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=TJLPJYXHSRBEE&lc=US&item_name=WordPress%20Subresource%20Integrity%20Plugin&item_number=wp-sri&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: security, subresource integrity, SRI, MITM, mitigation, DDoS prevention
Requires at least: 4.1
Tested up to: 4.4
Stable tag: trunk
License: GPLv3
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

== Change log ==

= Version 0.2.1 =

* Add the `crossorigin="anonymous"` attribute/value pair to modified elements to enable Firefox 43's handling of integrity checks.

= Version 0.2 =

* Feature: A simple administrative interface can be found under the "Subresource Integrity Manager" option in your WordPress Tools menu. This interface allows you to view the URL and hash pairs currently known by your site, and to delete them. Deleting a known hash will cause WordPress to refetch and rehash the resource when it is next requested.

= Version 0.1 =

* Initial release.

== Other notes ==

If you like this plugin, **please consider [making a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WordPress%20Subresource%20Integrity%20Plugin&amp;item_number=wp-sri&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted) for your use of the plugin**, [purchasing one of Meitar's web development books](http://www.amazon.com/gp/redirect.html?ie=UTF8&location=http%3A%2F%2Fwww.amazon.com%2Fs%3Fie%3DUTF8%26redirect%3Dtrue%26sort%3Drelevancerank%26search-type%3Dss%26index%3Dbooks%26ref%3Dntt%255Fathr%255Fdp%255Fsr%255F2%26field-author%3DMeitar%2520Moscovitz&tag=maymaydotnet-20&linkCode=ur2&camp=1789&creative=390957) or, better yet, contributing directly to [Meitar's Cyberbusking fund](http://Cyberbusking.org/). (Publishing royalties ain't exactly the lucrative income it used to be, y'know?) Your support is appreciated!

