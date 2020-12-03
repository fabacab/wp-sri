<?php
/**
 * Plugin Name: Subresource Integrity (SRI) Manager
 * Plugin URI: https://maymay.net/blog/projects/wp-sri/
 * Description: A utility to easily add SRI security checks to your generated WordPress pages. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WordPress%20Subresource%20Integrity%20Plugin&amp;item_number=wp-sri&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the maintainer">donate</a>. &hearts; Thank you!</strong>
 * Version: 0.4.0
 * Text Domain: wp-sri
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) { exit; } // Disallow direct HTTP access.

require_once dirname(__FILE__) . '/wp-sri-admin.php';

/**
 * Main plugin class.
 */
class WP_SRI_Plugin {

    /**
     * Prefix of plugin options, etc.
     *
     * @var string
     */
    const prefix = 'wp_sri_';

    private $sri_exclude; // Options array of excluded asset URLs
    private $count = 1; // Used for admin update notice
    private $version = "0.3.0";

    public function __construct () {
        // Grab our exclusion array from the options table.
        $this->sri_exclude = get_option( self::prefix.'excluded_hashes', array() );

        add_action('plugins_loaded', array($this, 'registerL10n'));
        add_action('current_screen', array($this, 'processActions'));
        add_action('admin_menu', array($this, 'registerAdminMenu'));

        add_filter('style_loader_tag', array($this, 'filterTag'), 999999, 3);
        add_filter('script_loader_tag', array($this, 'filterTag'), 999999, 3);
        add_filter('set-screen-option', array($this, 'setAdminScreenOptions'), 10, 3);

        add_action( 'admin_enqueue_scripts', array( $this, 'sri_enqueue_scripts' ) );

        add_action( 'wp_ajax_update_sri_exclude', array( 'WP_SRI_Known_Hashes_List_Table', 'update_sri_exclude' ) );

        // Give themes a chance to hook into our exclude filter
        add_action( 'after_setup_theme', array( $this, 'sri_exclude_own' ) );

    }

    /**
     * Was getting errors locally with the stylesheet.
     */
    public function sri_exclude_own() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        $scripts = array(
            plugin_dir_url( __FILE__ ) . 'js/wp-sri.js' . '?ver=' . $this->version,
            plugin_dir_url( __FILE__ ) . 'css/wp-sri.css' . '?ver=' . $this->version
        );

        // Allow theme devs to get in on the fun
        foreach ( apply_filters( 'sri_exclude_array', $scripts ) as $script ) {
            $script = esc_url( $script );
            if ( false === array_search( $script, $this->sri_exclude ) ) {
                $this->sri_exclude[] = $script;
                update_option( self::prefix.'excluded_hashes', $this->sri_exclude );
            }
        }
    }

    /**
     * Enqueue and localize our JS
     */
    public function sri_enqueue_scripts() {
        wp_enqueue_script( 'sri-exclude-js', plugin_dir_url( __FILE__ ) . 'js/wp-sri.js', array( 'jquery' ), $this->version, true );
        $nonce = wp_create_nonce( 'sri-update-exclusion' );
        wp_localize_script( 'sri-exclude-js', 'options', array( 'security' => $nonce ) );
    }

    public function registerL10n () {
        load_plugin_textdomain('wp-sri', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }


    private function showDonationAppeal () {
?>
<div class="donation-appeal">
    <p style="text-align: center; font-style: italic; margin: 1em 3em;"><?php print sprintf(
esc_html__('WordPress Subresource Integrity Manager is provided as free software, but sadly grocery stores do not offer free food. If you like this plugin, please consider %1$s to its %2$s. &hearts; Thank you!', 'wp-sri'),
'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=TJLPJYXHSRBEE&lc=US&item_name=WordPress%20Subresource%20Integrity%20Plugin&item_number=wp-sri&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted">' . esc_html__('making a donation', 'wp-sri') . '</a>',
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
        // If $url is found in our excluded array, return $tag unchanged
        if ( false !== array_search( esc_url( $url ), $this->sri_exclude) ) {
            return $tag;
        }
        $known_hashes = get_option(self::prefix.'known_hashes', array());
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

    /**
     * Filters a given tag, possibly adding an `integrity` attribute.
     *
     * @see https://developer.wordpress.org/reference/hooks/style_loader_tag/
     * @see https://developer.wordpress.org/reference/hooks/script_loader_tag/
     *
     * @param string $tag
     * @param string $handle
     * @param string $url
     *
     * @return string The original HTML tag or its augmented version.
     */
    public function filterTag ( $tag, $handle, $url ) {
        // Only do the thing if it makes sense to do so.
        // (It doesn't make sense for non-ssl pages or local resources on live sites,
        // but it always makes sense to do so in debug mode.)
        if ( ! WP_DEBUG
            &&
            ( ! is_ssl() || $this->isLocalResource( $url ) )
        ) { return $tag; }

        $known_hashes = get_option( self::prefix.'known_hashes', array() );
        if ( empty( $known_hashes[ $url ] ) ) {
            $resp = $this->fetchResource( $url );
            if ( is_wp_error( $resp ) ) {
                return $tag; // TODO: Handle this in some other way?
            } else {
                $known_hashes[ $url ] = $this->hashResource( $resp['body'] );
                update_option( self::prefix . 'known_hashes', $known_hashes );
            }
        }

        return $this->addIntegrityAttribute( $tag, $url );
    }

    /**
     * Deletes a known hash from the database.
     *
     * @param string $url The URL of the URL/hash pair to remove.
     * @return bool True on success, false otherwise.
     */
    public function deleteKnownHash ($url) {
        $known_hashes = get_option(self::prefix . 'known_hashes', array());
        unset($known_hashes[$url]);
        update_option(self::prefix . 'known_hashes', $known_hashes);
    }

    /**
     * Update our exclude option based on user action
     *
     * @param $url string The URL of of the asset to update
     *
     * @param $exclude bool Are we excluding this $url from SRI?
     */
    public function updateExcludedUrl( $url, $exclude ) {
        if ( false === ( $k = array_search( esc_url( $url ), $this->sri_exclude ) ) && $exclude ) {
            array_push( $this->sri_exclude, esc_url( $url ) );
        } elseif( false !== $k && ! $exclude ) {
            unset( $this->sri_exclude[$k] );
        }
        update_option( self::prefix.'excluded_hashes', $this->sri_exclude );
    }

    /**
     * Responds to administrator actions such as deleting hashes, etc.
     */
    public function processActions () {
        if (isset($_POST['_' . self::prefix . 'nonce']) && wp_verify_nonce($_POST['_' . self::prefix . 'nonce'], 'bulk_update_sri_hashes')) {
            $wp_sri_hashes_table = new WP_SRI_Known_Hashes_List_Table();
            $action = $wp_sri_hashes_table->current_action();
            // So we can customize our admin update notice
            if ( isset( $_POST['url'] ) ) {
                $this->count = count( $_POST['url'] );
            }

            if ( 'delete' === $action ) {
                foreach ( $_POST['url'] as $url ) {
                    $this->deleteKnownHash( rawurldecode( $url ) );
                }
                add_action( 'admin_notices', array( $this, 'hashDeletedNotice' ) );
            } elseif ( 'include' === $action ) {
                foreach ( $_POST['url'] as $url ) {
                    $this->updateExcludedUrl( rawurldecode( $url ), false );
                }
                add_action( 'admin_notices', array( $this, 'includeUrlUpdatedNotice' ) );
            } elseif ( 'exclude' === $action ) {
                foreach ( $_POST['url'] as $url ) {
                    $this->updateExcludedUrl( rawurldecode( $url ), true );
                }
                add_action( 'admin_notices', array( $this, 'excludeUrlUpdatedNotice' ) );
            }
        }

        if (isset($_GET['_' . self::prefix . 'nonce']) && wp_verify_nonce($_GET['_' . self::prefix . 'nonce'], 'update_sri_hash')) {
            $action = $_GET['action'];
            switch( $action ) {
                case 'delete':
                    $this->deleteKnownHash(rawurldecode($_GET['url']));
                    add_action('admin_notices', array($this, 'hashDeletedNotice'));
                    break;
                case 'include':
                    $this->updateExcludedUrl(rawurldecode($_GET['url']), false );
                    add_action('admin_notices', array($this, 'includeUrlUpdatedNotice'));
                    break;
                case 'exclude':
                    $this->updateExcludedUrl(rawurldecode($_GET['url']), true );
                    add_action('admin_notices', array($this, 'excludeUrlUpdatedNotice'));
                    break;
                default:
                    break;
            }

        }

        // Make sure our scripts are added back in case they were removed.
        $this->sri_exclude_own();
    }

    public function hashDeletedNotice () {
?>
<div class="updated notice is-dismissible">
    <p><?php printf( esc_html( _n( 'Hash has been forgotten.', '%s hashes have been forgotten.', $this->count, 'wp-sri' ) ), $this->count );?></p>
</div>
<?php
    }

    public function excludeUrlUpdatedNotice() {
        ?>
        <div class="updated notice is-dismissible">
            <p><?php printf( esc_html( _n( 'Resource has been excluded.', '%s resources have been excluded.', $this->count, 'wp-sri' ) ), $this->count ); ?></p>
        </div>
        <?php
    }

    public function includeUrlUpdatedNotice() {
        ?>
        <div class="updated notice is-dismissible">
            <p><?php printf( esc_html( _n( 'Resource has been included.', '%s resources have been included.', $this->count, 'wp-sri' ) ), $this->count ); ?></p>
        </div>
        <?php
    }

    public function registerAdminMenu () {
        $hook = add_management_page(
            __('Subresource Integrity Manager', 'wp-sri'),
            __('Subresource Integrity Manager', 'wp-sri'),
            'manage_options',
            self::prefix . 'admin',
            array($this, 'renderToolPage')
        );
        add_action("load-$hook", array($this, 'addAdminScreenOptions'));
        add_action( 'admin_print_styles-' . $hook, array( $this, 'addAdminStyle' ) );
    }

    public function addAdminScreenOptions () {
        global $wp_sri_hashes_table;
        $wp_sri_hashes_table = new WP_SRI_Known_Hashes_List_Table();

        add_screen_option('per_page', array(
            'label' => esc_html__('Hashes', 'wp-sri'),
            'default' => 20,
            'option' => self::prefix . 'hashes_per_page'
        ));

        $screen = get_current_screen();
        $content = '<p>';
        $content .= sprintf(
            esc_html__('This page lets you manage automatic integrity checks of subresources that pages on your site load. Subresources are assets that are referenced from within %1$sscript%2$s or %1$slink%2$s elements, such as JavaScript files or stylesheets. When your page loads such assets from servers other than your own, as is often done with Content Delivery Networks (CDNs), you can verify that the requested file contains exactly the code you expect it to by adding an integrity check.', 'wp-sri'),
            '<code>', '</code>'
        );
        $content .= '</p>';
        $content .= '<ul>';
        $content .= '<li>' . esc_html__('The "URL" column shows you the Web address of the resource being loaded.', 'wp-sri') . '</li>';
        $content .= '<li>' . esc_html__('The "Hash" column shows you what WP-SRI thinks the cryptographic hash of the resource should be.', 'wp-sri') . '</li>';
        $content .= '<li>' . esc_html__('The "Exclude" column lets you tell WP-SRI not to add integrity-checking code to your pages for a given resource.', 'wp-sri') . '</li>';
        $content .= '</ul>';
        $content .= '<p><strong>' . esc_html__('Tips', 'wp-sri') . '</strong></p>';
        $content .= '<ul>';
        $content .= '<li>' . esc_html__('If some pages are not loading correctly, use the developer tools in your Web browser to see if any assets are being blocked and need to be excluded. Excluding an asset means the resource will be added to your pages without the SRI attributes, but WP-SRI will still remember its hash.', 'wp-sri') . '</li>';
        $content .= '</ul>';
        $content .= '<p>' . sprintf(esc_html__('Learn more about %sSubresource Integrity%s features.', 'wp-sri'), '<a href="https://developer.mozilla.org/en-US/docs/Web/Security/Subresource_Integrity">', '</a>') . '</p>';
        $screen->add_help_tab( array(
            'id' => self::prefix.'help_tab',
            'title' => 'Managing Subresource Integrity',
            'content' => $content
        ));
    }

    public function addAdminStyle() {
        wp_enqueue_style( 'wp-sri-style', plugin_dir_url( __FILE__ ) . 'css/wp-sri.css', array(), $this->version );
    }

    public function setAdminScreenOptions ($status, $option, $value) {
        if (self::prefix . 'hashes_per_page' === $option) {
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
<form action="<?php print admin_url('tools.php?page=' . self::prefix . 'admin');?>" method="post">
<?php
        wp_nonce_field('bulk_update_sri_hashes', '_' . self::prefix . 'nonce');
        $wp_sri_hashes_table->search_box(esc_html__('Search', 'wp-sri'), self::prefix . 'search_hashes');
        $wp_sri_hashes_table->display();
?>
</form>
</div><!-- .wrap -->
<?php
        $this->showDonationAppeal();
    }

}

new WP_SRI_Plugin();
