<?php
/**
 * Main plugin class.
 *
 * @package WP_SRI_Plugin
 */

// Disallow direct HTTP access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/class-wp-sri-known-hashes-list-table.php';

/**
 * Main plugin class.
 */
class WP_SRI_Plugin {

	/**
	 * Prefix of plugin options, etc.
	 *
	 * @var string
	 */
	public static $prefix;

	/**
	 * Options array of excluded asset URLs
	 *
	 * @var array
	 */
	private $sri_exclude;


	/**
	 * Used for admin update notice.
	 *
	 * @var int
	 */
	private $count = 1;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin text domain
	 *
	 * @var string
	 */
	public static $text_domain;

	/**
	 * Constructor for plugin class.
	 */
	public function __construct() {
		// Get plugin metadata.
		if( ! function_exists( 'get_plugin_data' ) ){
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin = get_plugin_data( __DIR__ . '/wp-sri.php', false, false );

		// Define our properties.
		$this->version     = $plugin['Version'];
		self::$prefix      = str_replace( '-', '_', $plugin['TextDomain'] ) . '_';
		self::$text_domain = $plugin['TextDomain'];

		// Grab our exclusion array from the options table.
		$this->sri_exclude = get_option( self::$prefix . 'excluded_hashes', array() );

		add_action( 'plugins_loaded', array( $this, 'register_l10n' ) );
		add_action( 'current_screen', array( $this, 'process_actions' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		add_filter( 'style_loader_tag', array( $this, 'filter_tag' ), 999999, 3 );
		add_filter( 'script_loader_tag', array( $this, 'filter_tag' ), 999999, 3 );
		add_filter( 'set-screen-option', array( $this, 'set_admin_screen_options' ), 10, 3 );

		add_action( 'admin_enqueue_scripts', array( $this, 'sri_admin_enqueue_scripts' ) );

		add_action( 'wp_ajax_update_sri_exclude', array( 'WP_SRI_Known_Hashes_List_Table', 'update_sri_exclude' ) );

		// Give themes a chance to hook into our exclude filter.
		add_action( 'after_setup_theme', array( $this, 'wp_sri_exclude_own' ) );
	}

	/**
	 * Was getting errors locally with the stylesheet.
	 */
	public function wp_sri_exclude_own() {
		// Return if current request is an AJAX request.
		if ( wp_doing_ajax() ) {
			return;
		}

		// Exclude our resources.
		$scripts = array(
			plugin_dir_url( __FILE__ ) . 'js/wp-sri.js?ver=' . $this->version,
			plugin_dir_url( __FILE__ ) . 'css/wp-sri.css?ver=' . $this->version,
		);

		/**
		 * Filters pre-excluded resources. Allows theme/plugin developers to pre-define their own resources to be excluded.
		 *
		 * @var string[] $scripts An array of pre-defined resources to be excluded.
		 */
		$scripts = apply_filters( WP_SRI_Plugin::$prefix . 'exclude_array', $scripts );

		// Exclude hashes.
		foreach ( $scripts as $script ) {
			$script = esc_url( $script );
			if ( false === array_search( $script, $this->sri_exclude, true ) ) {
				$this->sri_exclude[] = $script;
				update_option( self::$prefix . 'excluded_hashes', $this->sri_exclude );
			}
		}
	}

	/**
	 * Enqueue and localize our JS
	 */
	public function sri_admin_enqueue_scripts() {
		wp_enqueue_script( 'sri-exclude-js', plugin_dir_url( __FILE__ ) . 'js/wp-sri.js', array( 'jquery' ), $this->version, true );
		$nonce = wp_create_nonce( 'sri-update-exclusion' );
		wp_localize_script( 'sri-exclude-js', 'sriOptions', array( 'security' => $nonce ) );
	}

	/**
	 * Load the translations.
	 */
	public function register_l10n() {
		load_plugin_textdomain( 'wp-sri', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * Displays an appeal for donations.
	 */
	private function show_donation_appeal() {
		?>
		<div class="donation-appeal">
			<p style="text-align: center; font-style: italic; margin: 1em 3em;">
				<?php
				// translators: Placeholders are links.
				printf(
					esc_html__( 'WordPress Subresource Integrity Manager is provided as free software, but sadly grocery stores do not offer free food. If you like this plugin, please consider %1$s to its %2$s. &hearts; Thank you!', 'wp-sri' ),
					'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=TJLPJYXHSRBEE&lc=US&item_name=WordPress%20Subresource%20Integrity%20Plugin&item_number=wp-sri&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted">' . esc_html__( 'making a donation', 'wp-sri' ) . '</a>',
					'<a href="http://Cyberbusking.org/">' . esc_html__( 'houseless, jobless, nomadic developer', 'wp-sri' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Checks a URL to determine whether or not the resource is "remote"
	 * (served by a third-party) or whether the resource is local (and
	 * is being served by the same web server as this plugin is run on.)
	 *
	 * @param string $uri The URI of the resource to inspect.
	 * @return bool True if the resource is local, false if the resource is remote.
	 */
	public static function is_local_resource( $uri ) {
		$rsrc_host = wp_parse_url( $uri, PHP_URL_HOST );
		$this_host = wp_parse_url( get_site_url(), PHP_URL_HOST );
		return ( 0 === strpos( $rsrc_host, $this_host ) ) ? true : false;
	}

	/**
	 * Appends a proper SRI attribute to an element's attribute list.
	 *
	 * @param string $tag The HTML tag to add the attribute to.
	 * @param string $url The URL of the resource to find the hash for.
	 * @return string The HTML tag with an integrity attribute added.
	 */
	public function add_integrity_attribute( $tag, $url ) {
		// If $url is found in our excluded array, return $tag unchanged.
		if ( false !== array_search( esc_url( $url ), $this->sri_exclude, true ) ) {
			return $tag;
		}

		$known_hashes  = get_option( self::$prefix . 'known_hashes', array() );
		$sri_att       = ' crossorigin="anonymous" integrity="sha256-' . $known_hashes[ $url ] . '"';
		$insertion_pos = strpos( $tag, '>' );

		// Account for self-closing tags.
		if ( 0 === strpos( $tag, '<link ' ) ) {
			$insertion_pos--;
			$sri_att .= ' ';
		}

		return substr( $tag, 0, $insertion_pos ) . $sri_att . substr( $tag, $insertion_pos );
	}

	/**
	 * Retrieve the resource content.
	 *
	 * @param string $rsrc_url Resource URL.
	 * @return array|WP_Error Array containing 'headers', 'body', 'response', 'cookies', 'filename', or a WP_Error on failure.
	 */
	public function fetch_resource( $rsrc_url ) {
		$url = ( 0 === strpos( $rsrc_url, '//' ) )
			? ( ( is_ssl() ) ? "https:$rsrc_url" : "http:$rsrc_url" )
			: $rsrc_url;
		return wp_remote_get( $url );
	}

	/**
	 * Hashes the resource content.
	 *
	 * @param string $content The resource content to hash.
	 * @return string The hash value.
	 */
	public function hash_resource( $content ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( hash( 'sha256', $content, true ) );
	}

	/**
	 * Filters a given tag, possibly adding an `integrity` attribute.
	 *
	 * @param string $tag The link or script tag for the enqueued resource.
	 * @param string $handle The registered handle of the enqueued resource.
	 * @param string $url The source URL.
	 * @return string The original HTML tag or its augmented version.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/style_loader_tag/
	 * @see https://developer.wordpress.org/reference/hooks/script_loader_tag/
	 */
	public function filter_tag( $tag, $handle, $url ) {
		// Only do the thing if it makes sense to do so.
		// (It doesn't make sense for non-ssl pages or local resources on live sites,
		// but it always makes sense to do so in debug mode.)
		if ( ! WP_DEBUG
			&&
			( ! is_ssl() || $this->is_local_resource( $url ) )
		) { return $tag; }

		$known_hashes = get_option( self::$prefix . 'known_hashes', array() );
		if ( empty( $known_hashes[ $url ] ) ) {
			$resp = $this->fetch_resource( $url );
			if ( is_wp_error( $resp ) ) {
				return $tag; // TODO: Handle this in some other way?
			} else {
				$known_hashes[ $url ] = $this->hash_resource( $resp['body'] );
				update_option( self::$prefix . 'known_hashes', $known_hashes );
			}
		}

		return $this->add_integrity_attribute( $tag, $url );
	}

	/**
	 * Deletes a known hash from the database.
	 *
	 * @param string $url The URL of the URL/hash pair to remove.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function delete_known_hash( $url ) {
		$known_hashes = get_option( self::$prefix . 'known_hashes', array() );
		unset( $known_hashes[ esc_url_raw( $url ) ] );
		return update_option( self::$prefix . 'known_hashes', $known_hashes );
	}

	/**
	 * Update our exclude option based on user action
	 *
	 * @param string $url The URL of of the asset to update.
	 * @param bool   $exclude Whether to exclude this $url from SRI.
	 */
	public function update_excluded_url( $url, $exclude ) {
		$k = array_search( esc_url_raw( $url ), $this->sri_exclude, true );
		if ( false === $k && $exclude ) {
			array_push( $this->sri_exclude, esc_url_raw( $url ) );
		} elseif ( false !== $k && ! $exclude ) {
			unset( $this->sri_exclude[ $k ] );
		}
		update_option( self::$prefix . 'excluded_hashes', $this->sri_exclude );
	}

	/**
	 * Responds to administrator actions such as deleting hashes, etc.
	 */
	public function process_actions() {

		// Process bulk table action.
		if ( isset( $_POST[ '_' . self::$prefix . 'nonce' ] ) && wp_verify_nonce( $_POST[ '_' . self::$prefix . 'nonce' ], 'bulk_update_sri_hashes' ) && isset( $_POST['url'] ) ) {
			// Init list table.
			$wp_sri_hashes_table = new WP_SRI_Known_Hashes_List_Table();

			// Get current table action.
			$action = $wp_sri_hashes_table->current_action();

			// Get the url count to be used for admin notices.
			$this->count = count( $_POST['url'] );

			switch ($action) {
				case 'delete':
					foreach ( $_POST['url'] as $url ) {
						$this->delete_known_hash( esc_url_raw( $url ) );
					}
					add_action( 'admin_notices', array( $this, 'notice_hash_deleted' ) );
					break;

				case 'include':
					foreach ( $_POST['url'] as $url ) {
						$this->update_excluded_url( esc_url_raw( $url ), false );
					}
					add_action( 'admin_notices', array( $this, 'notice_url_included' ) );
					break;

				case 'exclude':
					foreach ( $_POST['url'] as $url ) {
						$this->update_excluded_url( esc_url_raw( $url ), true );
					}
					add_action( 'admin_notices', array( $this, 'notice_url_excluded' ) );
					break;
			}
		}

		// Process row action.
		if ( isset( $_GET['_' . self::$prefix . 'nonce'] ) && wp_verify_nonce( $_GET['_' . self::$prefix . 'nonce'], 'update_sri_hash' ) && isset( $_GET['url'] ) ) {
			$action = $_GET['action'];
			switch( $action ) {
				case 'delete':
					$this->delete_known_hash( esc_url_raw( $_GET['url'] ) );
					add_action( 'admin_notices', array( $this, 'notice_hash_deleted' ) );
					break;
				case 'include':
					$this->update_excluded_url( esc_url_raw( $_GET['url'] ), false );
					add_action( 'admin_notices', array( $this, 'notice_url_included' ) );
					break;
				case 'exclude':
					$this->update_excluded_url( esc_url_raw( $_GET['url'] ), true );
					add_action( 'admin_notices', array( $this, 'notice_url_excluded' ) );
					break;
				default:
					break;
			}
		}

		// Make sure our scripts are added back in case they were removed.
		$this->wp_sri_exclude_own();
	}

	/**
	 * Displays an admin notice when a hash has been deleted.
	 */
	public function notice_hash_deleted() {
		// translators: Placeholders are for the number of hashes that have been forgotten/removed.
		?>
		<div class="updated notice is-dismissible">
			<p><?php printf( esc_html( _n( '%s hash has been forgotten.', '%s hashes have been forgotten.', $this->count, 'wp-sri' ) ), $this->count ); ?></p>
		</div>
		<?php
	}

	/**
	 * Displays an admin notice when a resource is excluded.
	 */
	public function notice_url_excluded() {
		// translators: Placeholders are for the number of resources excluded.
		?>
		<div class="updated notice is-dismissible">
			<p><?php printf( esc_html( _n( '%s resource has been excluded.', '%s resources have been excluded.', $this->count, 'wp-sri' ) ), $this->count ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display an admin notice when a resource is included.
	 */
	public function notice_url_included() {
		// translators: Placeholders are for the number of resources included.
		?>
		<div class="updated notice is-dismissible">
			<p><?php printf( esc_html( _n( '%s resource has been included.', '%s resources have been included.', $this->count, 'wp-sri' ) ), $this->count ); ?></p>
		</div>
		<?php
	}

	/**
	 * Create the admin page listed under tools.
	 */
	public function register_admin_menu() {
		$hook = add_management_page(
			__( 'Subresource Integrity Manager', 'wp-sri' ),
			__( 'Subresource Integrity Manager', 'wp-sri' ),
			'manage_options',
			self::$prefix . 'admin',
			array( $this, 'render_tool_page' )
		);
		add_action( "load-$hook", array( $this, 'load_admin_page' ) );
		add_action( 'admin_print_styles-' . $hook, array( $this, 'add_admin_style' ) );
	}

	/**
	 * Load the admin page.
	 */
	public function load_admin_page() {
		global $wp_sri_hashes_table;

		// Init list table
		$wp_sri_hashes_table = new WP_SRI_Known_Hashes_List_Table();

		// Register screen options.
		add_screen_option(
			'per_page',
			array(
				'label'   => esc_html__( 'Hashes', 'wp-sri' ),
				'default' => 20,
				'option'  => self::$prefix . 'hashes_per_page',
			)
		);

		$screen  = get_current_screen();
		$content = '<p>';
		// translators: The placeholders are for open/close <code> tags.
		$content .= sprintf(
			esc_html__( 'This page lets you manage automatic integrity checks of subresources that pages on your site load. Subresources are assets that are referenced from within %1$sscript%2$s or %1$slink%2$s elements, such as JavaScript files or stylesheets. When your page loads such assets from servers other than your own, as is often done with Content Delivery Networks (CDNs), you can verify that the requested file contains exactly the code you expect it to by adding an integrity check.', 'wp-sri' ),
			'<code>',
			'</code>'
		);
		$content .= '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'The "URL" column shows you the Web address of the resource being loaded.', 'wp-sri' ) . '</li>';
		$content .= '<li>' . esc_html__( 'The "Hash" column shows you what WP-SRI thinks the cryptographic hash of the resource should be.', 'wp-sri' ) . '</li>';
		$content .= '<li>' . esc_html__( 'The "Exclude" column lets you tell WP-SRI not to add integrity-checking code to your pages for a given resource.', 'wp-sri' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p><strong>' . esc_html__( 'Tips', 'wp-sri' ) . '</strong></p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'If some pages are not loading correctly, use the developer tools in your Web browser to see if any assets are being blocked and need to be excluded. Excluding an asset means the resource will be added to your pages without the SRI attributes, but WP-SRI will still remember its hash.', 'wp-sri' ) . '</li>';
		$content .= '</ul>';
		// translators: The placeholders are open/close anchor tags for hyperlinking to the Mozilla documentation on Subresource Integrity, see https://developer.mozilla.org/en-US/docs/Web/Security/Subresource_Integrity.
		$content .= '<p>' . sprintf( esc_html__( 'Learn more about %1$sSubresource Integrity%2$s features.', 'wp-sri' ), '<a href="https://developer.mozilla.org/en-US/docs/Web/Security/Subresource_Integrity">', '</a>' ) . '</p>';
		$screen->add_help_tab(
			array(
				'id'      => self::$prefix . 'help_tab',
				'title'   => 'Managing Subresource Integrity',
				'content' => $content,
			)
		);
	}

	/**
	 * Enqueue admin page styles.
	 */
	public function add_admin_style() {
		wp_enqueue_style( 'wp-sri-style', plugin_dir_url( __FILE__ ) . 'css/wp-sri.css', array(), $this->version );
	}

	/**
	 * Set screen options for admin page.
	 */
	public function set_admin_screen_options( $status, $option, $value ) {
		if ( self::$prefix . 'hashes_per_page' === $option ) {
			return $value;
		}
		return $status;
	}

	/**
	 * Displays the options page under the tools menu.
	 */
	public function render_tool_page() {
		global $wp_sri_hashes_table;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-sri' ) );
		}
		$wp_sri_hashes_table->prepare_items();
		?>
		<div class="wrap">
		<h2><?php esc_html_e( 'Subresource Integrity Manager', 'wp-sri' );?></h2>
		<form action="<?php echo esc_url( admin_url( 'tools.php?page=' . self::$prefix . 'admin' ) ); ?>" method="post">
		<?php
			wp_nonce_field( 'bulk_update_sri_hashes', '_' . self::$prefix . 'nonce' );
			$wp_sri_hashes_table->search_box( esc_html__( 'Search', 'wp-sri' ), self::$prefix . 'search_hashes' );
			$wp_sri_hashes_table->display();
		?>
		</form>
		</div><!-- .wrap -->
		<?php
			$this->show_donation_appeal();
	}
}
