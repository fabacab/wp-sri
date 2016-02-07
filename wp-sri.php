<?php
/**
 * Plugin Name: Subresource Integrity (SRI) Manager
 * Plugin URI: https://maymay.net/blog/projects/wp-sri/
 * Description: A utility to easily add SRI security checks to your generated WordPress pages. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WordPress%20Subresource%20Integrity%20Plugin&amp;item_number=wp-sri&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the maintainer">donate</a>. &hearts; Thank you!</strong>
 * Version: 0.2.2
 * Author: Meitar Moscovitz <meitar@maymay.net>
 * Author URI: https://maymay.net/
 * Text Domain: wp-sri
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) { exit; } // Disallow direct HTTP access.

require_once dirname(__FILE__) . '/wp-sri-admin.php';

class WP_SRI_Plugin {
    private $prefix = 'wp_sri_'; //< Prefix of plugin options, etc.

    public function __construct () {
        add_action('plugins_loaded', array($this, 'registerL10n'));
        add_action('current_screen', array($this, 'processActions'));
        add_action('admin_menu', array($this, 'registerAdminMenu'));

        add_filter('style_loader_tag', array($this, 'filterTag'), 999999, 2);
        add_filter('script_loader_tag', array($this, 'filterTag'), 999999, 3);
        add_filter('set-screen-option', array($this, 'setAdminScreenOptions'), 10, 3);
    }

    public function registerL10n () {
        load_plugin_textdomain('wp-sri', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }


    private function showDonationAppeal () {
?>
<div class="donation-appeal">
    <p style="text-align: center; font-style: italic; margin: 1em 3em;"><?php print sprintf(
esc_html__('WordPress Subresource Integrity Manager is provided as free software, but sadly grocery stores do not offer free food. If you like this plugin, please consider %1$s to its %2$s. &hearts; Thank you!', 'wp-sri'),
'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=meitarm%40gmail%2ecom&lc=US&amp;item_name=Subresource%20Integrity%20Manager%20WordPress%20Plugin&amp;item_number=wp-sri&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted">' . esc_html__('making a donation', 'wp-sri') . '</a>',
'<a href="http://Cyberbusking.org/">' . esc_html__('houseless, jobless, nomadic developer', 'wp-sri') . '</a>'
);?></p>
</div>
<?php
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
        $known_hashes = $this->getKnownHashes();
        $sri_att = ' crossorigin="anonymous" integrity="sha256-' . $known_hashes[$url] . '"';
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

        $known_hashes = $this->getKnownHashes();
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

    public function getKnownHashes () {
        return get_option($this->prefix . 'known_hashes', array());
    }

    /**
     * Deletes a known hash from the database.
     *
     * @param string $url The URL of the URL/hash pair to remove.
     * @return bool True on success, false otherwise.
     */
    public function deleteKnownHash ($url) {
        $known_hashes = $this->getKnownHashes();
        unset($known_hashes[$url]);
        update_option($this->prefix . 'known_hashes', $known_hashes);
    }

    /**
     * Responds to administrator actions such as deleting hashes, etc.
     */
    public function processActions () {
        $wp_sri_hashes_table = new WP_SRI_Known_Hashes_List_Table();
        if ('delete' === $wp_sri_hashes_table->current_action()) {
            if (isset($_POST['_' . $this->prefix . 'nonce']) && wp_verify_nonce($_POST['_' . $this->prefix . 'nonce'], 'bulk_delete_sri_hashes')) {
                foreach ($_POST['url'] as $url) {
                    $this->deleteKnownHash(rawurldecode($url));
                }
                add_action('admin_notices', array($this, 'hashDeletedNotice'));
            }
        }

        if (isset($_GET['_' . $this->prefix . 'nonce']) && wp_verify_nonce($_GET['_' . $this->prefix . 'nonce'], 'delete_sri_hash')) {
            $this->deleteKnownHash(rawurldecode($_GET['url']));
            add_action('admin_notices', array($this, 'hashDeletedNotice'));
        }
    }

    public function hashDeletedNotice () {
?>
<div class="updated notice is-dismissible">
    <p><?php esc_html_e('Hash has been deleted.', 'wp-sri');?></p>
</div>
<?php
    }

    public function registerAdminMenu () {
        $hook = add_management_page(
            __('Subresource Integrity Manager', 'wp-sri'),
            __('Subresource Integrity Manager', 'wp-sri'),
            'manage_options',
            $this->prefix . 'admin',
            array($this, 'renderToolPage')
        );
        add_action("load-$hook", array($this, 'addAdminScreenOptions'));
    }

    public function addAdminScreenOptions () {
        global $wp_sri_hashes_table;
        add_screen_option('per_page', array(
            'label' => esc_html__('Hashes', 'wp-sri'),
            'default' => 20,
            'option' => $this->prefix . 'hashes_per_page'
        ));
        $wp_sri_hashes_table = new WP_SRI_Known_Hashes_List_Table();
    }

    public function setAdminScreenOptions ($status, $option, $value) {
        if ($this->prefix . 'hashes_per_page' === $option) {
            return $value;
        }
        return $status;
    }

    public function renderToolPage () {
        global $wp_sri_hashes_table;
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-sri'));
        }
        $wp_sri_hashes_table->prepare_items();
?>
<div class="wrap">
<h2><?php esc_html_e('Subresource Integrity Manager', 'wp-sri');?></h2>
<form action="<?php print admin_url('tools.php?page=' . $this->prefix . 'admin');?>" method="post">
<?php
        wp_nonce_field('bulk_delete_sri_hashes', '_' . $this->prefix . 'nonce');
        $wp_sri_hashes_table->search_box(esc_html__('Search', 'wp-sri'), $this->prefix . 'search_hashes');
        $wp_sri_hashes_table->display();
?>
</form>
</div><!-- .wrap -->
<?php
        $this->showDonationAppeal();
    }

}

$wp_sri_plugin = new WP_SRI_Plugin();
