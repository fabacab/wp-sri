<?php
/**
 * WordPress Subresource Integrity Manager Admin Interface
 *
 * @package plugin
 */

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for managing known resource hashes.
 */
class WP_SRI_Known_Hashes_List_Table extends WP_List_Table {

    /**
     * Options array of excluded asset URLs.
     *
     * @var array
     */
    protected $sri_exclude;

    /**
     * Constructor.
     */
    public function __construct () {

        $this->sri_exclude = get_option(WP_SRI_Plugin::prefix.'excluded_hashes', array()); // Get our excluded option array

        parent::__construct(array(
            'singular' => esc_html__('Known Hash', 'wp-sri'),
            'plural' => esc_html__('Known Hashes', 'wp-sri'),
            'ajax' => false,
            'screen' => get_current_screen(), // https://wordpress.org/support/topic/php-notice-because-constructor-for-class-wp_list_table?replies=1
        ));
    }

    public function no_items () {
        esc_html_e('No hashes known.', 'wp-sri');
    }

    public function get_columns () {
        return array(
            'cb' => '<input type="checkbox" />',
            'url' => esc_html__('URL', 'wp-sri'),
            'hash' => esc_html__('Hash', 'wp-sri'),
            'exclude' => esc_html__( 'Exclude', 'wp-sri' ),
        );
    }

    public function get_sortable_columns () {
        return array(
            'url' => array('url', false),
            'hash' => array('hash', false),
            'exclude' => array( 'exclude', true )
        );
    }

    public function get_bulk_actions () {
        return array(
            'delete' => esc_html__('Delete', 'wp-sri'),
            'exclude' => esc_html__( 'Exclude', 'wp-sri' ),
            'include' => esc_html__( 'Include', 'wp-wri' )
        );
    }

    public function column_cb ($item) {
        return sprintf(
            '<input type="checkbox" name="url[]" value="%s" />', rawurlencode($item['url'])
        );
    }

    private function usort_reorder ($a, $b) {
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'exclude';
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
        $result = strcmp($a[$orderby], $b[$orderby]);
        return ($order === 'asc') ? $result : -$result;
    }

    public function search ($value) {
        return (false !== strpos($value, $_POST['s']));
    }

    public function prepare_items () {
        $this->_column_headers = $this->get_column_info();

        $known_hashes = get_option(WP_SRI_Plugin::prefix.'known_hashes', array());
        if (!empty($_POST['s'])) {
            $known_hashes = array_flip(array_filter(array_flip($known_hashes), array($this, 'search')));
        }
        $total_hashes = count($known_hashes);
        $show_on_page = $this->get_items_per_page('wp_sri_hashes_per_page', 20);

        $this->set_pagination_args(array(
            'total_items' => $total_hashes,
            'total_pages' => ceil($total_hashes / $show_on_page),
            'per_page' => $show_on_page
        ));

        $items = array();
        foreach ($known_hashes as $url => $hash) {
            $exclude = ( false !== array_search( $url, $this->sri_exclude ) ) ? 'a' : 'b';
            $items[] = array(
                'url' => $url,
                'hash' => $hash,
                'exclude' => $exclude
            );
        }

        usort($items, array(&$this, 'usort_reorder'));
        $shown_hashes = array_slice($items, (($this->get_pagenum() - 1) * $show_on_page), $show_on_page);
        return $this->items = $shown_hashes;
    }

    /**
     * Create our output for the Excluded column.
     *
     * If the row's $url is in our excluded array, make sure box is checked.
     * We include a loading image which is hidden using CSS by default.
     * Checkboxes are disabled by default, enabled using JS if available.
     * 
     * @param $item
     *
     * @return string
     */
    protected function column_exclude( $item ) {
        $url  = esc_url( $item['url'] );
        $hash = $item['hash'];
        if ( false !== array_search( $url, $this->sri_exclude) ) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        $loading = plugin_dir_url( __FILE__ ) . 'css/working.gif'; // image shown during AJAX request to indicate something is happening.
       return sprintf('<input disabled="disabled" type="checkbox" class="sri-exclude" id="%s" %s><span class="sri-loading"><img src="%s" /> </span>', $url, $checked, $loading );
    }

    protected function column_url ($item) {
        $actions = array(
            'delete' => sprintf(
                '<a href="?page=%s&amp;action=%s&amp;url=%s&amp;_wp_sri_nonce=%s&amp;orderby=%s&amp;order=%s" title="%s">%s</a>',
                $_REQUEST['page'], 'delete', rawurlencode($item['url']), wp_create_nonce('update_sri_hash'),
                (!empty($_GET['orderby'])) ? $_GET['orderby'] : '', (!empty($_GET['order'])) ? $_GET['order'] : '',
                esc_html__('Remove this URL and hash pair.', 'wp-sri'), esc_html__('Delete', 'wp-sri')
            )

        );
        $this->get_exclude_actions( $item, $actions );
        return sprintf('%1$s %2$s', $item['url'], $this->row_actions($actions));
    }

    /**
     * Add proper output to $actions array depending on whether or not $item['url'] is being excluded.
     *
     * @param $item  array    Table row data
     * @param $actions  array ref  We're adding our action directoy to the array used in the above column_url() func
     */
    protected function get_exclude_actions( $item, &$actions ) {

        $url = esc_url( $item['url'] );
        if ( false === array_search( $url, $this->sri_exclude ) ) {
            $actions['exclude'] =  sprintf(
                '<a href="?page=%s&amp;action=%s&amp;url=%s&amp;_wp_sri_nonce=%s&amp;orderby=%s&amp;order=%s" title="%s">%s</a>',
                $_REQUEST['page'], 'exclude', rawurlencode($item['url']), wp_create_nonce('update_sri_hash'),
                (!empty($_GET['orderby'])) ? $_GET['orderby'] : '', (!empty($_GET['order'])) ? $_GET['order'] : '',
                esc_html__('Exclude this URL.', 'wp-sri'), esc_html__('Exclude', 'wp-sri')
            );
        } else {
            $actions['include'] =  sprintf(
                '<a href="?page=%s&amp;action=%s&amp;url=%s&amp;_wp_sri_nonce=%s&amp;orderby=%s&amp;order=%s" title="%s">%s</a>',
                $_REQUEST['page'], 'include', rawurlencode($item['url']), wp_create_nonce('update_sri_hash'),
                (!empty($_GET['orderby'])) ? $_GET['orderby'] : '', (!empty($_GET['order'])) ? $_GET['order'] : '',
                esc_html__('Include this URL.', 'wp-sri'), esc_html__('Include', 'wp-sri')
            );
        }
    }

    // TODO: implement hash editing interface
//    protected function column_hash ($item) {
//        $actions = array(
//            'edit_hash' => sprintf(
//                '<a href="?page=%s&action=%s&url=%s" title="%s">%s</a>',
//                $_REQUEST['page'], 'edit_hash', rawurlencode($item['url']),
//                esc_html__('Edit the hash for this URL.', 'wp-sri'), esc_html__('Edit Hash', 'wp-sri')
//            )
//        );
//        return sprintf('%1$s %2$s', $item['hash'], $this->row_actions($actions));
//    }

    protected function column_default ($item, $column_name) {
        return $item[$column_name];
    }

    public static function update_sri_exclude () {

        check_ajax_referer( 'sri-update-exclusion', 'security' );

        $update = false;

        $excluded = get_option(WP_SRI_Plugin::prefix.'excluded_hashes', array());
        $url = esc_url( $_POST['url'] );
        $checked = filter_var( $_POST['checked'], FILTER_VALIDATE_BOOLEAN );

        if ( $checked ) {
            // If checked, we add $url to our exclusion array.
            if ( ! in_array( $url, $excluded ) ) {
                $excluded[] = $url;
                $update = true;
            }
        } else {
            // If unchecked, we remove $url from our exclusion array.
            if ( false !== ($key = array_search( $url, $excluded)) ) {
                unset( $excluded[$key] );
                $update = true;
            }
        }

        if ( $update ) {
            update_option( WP_SRI_Plugin::prefix.'excluded_hashes', $excluded );
        }

        wp_send_json_success( 'done' );
    }
}
