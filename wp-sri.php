<?php
/**
 * Plugin Name: Subresource Integrity (SRI) Manager
 * Plugin URI: https://maymay.net/blog/projects/wp-sri/
 * Description: A utility to easily add SRI security checks to your generated WordPress pages. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WordPress%20Subresource%20Integrity%20Plugin&amp;item_number=wp-sri&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the maintainer">donate</a>. &hearts; Thank you!</strong>
 * Version: 0.1
 * Author: Meitar Moscovitz <meitar@maymay.net>
 * Author URI: https://maymay.net/
 * Text Domain: wp-sri
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) { exit; } // Disallow direct HTTP access.

class WP_SRI_Plugin {
    private $prefix = 'wp_sri_'; //< Prefix of plugin options, etc.

    public function __construct () {
        add_filter('style_loader_tag', array($this, 'filterTag'), 999999, 2);
        add_filter('script_loader_tag', array($this, 'filterTag'), 999999, 3);
    }

    /**
     * Checks a URL to determine whether or not the resource is "remote"
     * (served by a third-party) or whether the resource is local (and
     * is being served by the same webserver as this plugin is run on.)
     *
     * @param string $uri The URI of the resource to inspect.
     * @return bool True if the resource is local, false if the resource is remote.
     */
    public static function isLocalResource ($uri) {
        $rsrc_host = parse_url($uri, PHP_URL_HOST);
        $this_host = parse_url(get_site_url(), PHP_URL_HOST);
        return (0 === strpos($rsrc_host, $this_host)) ? true : false;
    }

    /**
     * Appends a proper SRI attribute to an element's attribute list.
     *
     * @param string $tag The HTML tag to add the attribute to.
     * @param string $url The URL of the resource to find the hash for.
     * @return string The HTML tag with an integrity attribute added.
     */
    public function addIntegrityAttribute ($tag, $url) {
        $known_hashes = get_option($this->prefix . 'known_hashes');
        $sri_att = ' integrity="sha256-' . $known_hashes[$url] . '"';
        $insertion_pos = strpos($tag, '>');
        // account for self-closing tags
        if (0 === strpos($tag, '<link ')) {
            $insertion_pos--; 
            $sri_att .= ' ';
        }
        return substr($tag, 0, $insertion_pos) . $sri_att . substr($tag, $insertion_pos);
    }

    public function fetchResource ($rsrc_url) {
        $url = (0 === strpos($rsrc_url, '//'))
            ? ((is_ssl()) ? "https:$rsrc_url" : "http:$rsrc_url")
            : $rsrc_url;
        return wp_remote_get($url);
    }

    public function hashResource ($content) {
        return base64_encode(hash('sha256', $content, true));
    }

    public function filterTag ($tag, $handle, $src = null) {
        $atts = wp_kses_hair($tag, array('', 'http', 'https'));
        switch ($atts['type']['value']) {
            case 'text/css':
                $url = $atts['href']['value'];
                break;
            case 'text/javascript':
                $url = $src;
                break;
        }

        // Only do the thing if it makes sense to do so.
        // (It doesn't make sense for non-ssl pages or local resources on live sites,
        // but it always makes sense to do so in debug mode.)
        if (!WP_DEBUG
            &&
            (!is_ssl() || $this->isLocalResource($url))
        ) { return $tag; }

        $known_hashes = get_option($this->prefix . 'known_hashes', array());
        if (empty($known_hashes[$url])) {
            $resp = $this->fetchResource($url);
            if (is_wp_error($resp)) {
                return $tag; // TODO: Handle this in some other way?
            } else {
                $known_hashes[$url] = $this->hashResource($resp['body']);
                update_option($this->prefix . 'known_hashes', $known_hashes);
            }
        }

        return $this->addIntegrityAttribute($tag, $url);
    }

}

$wp_sri_plugin = new WP_SRI_Plugin();
