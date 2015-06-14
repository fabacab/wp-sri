<?php
/**
 * WordPress Subresource Integrity Manager Admin Interface
 *
 * @package plugin
 */

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WP_SRI_Known_Hashes_List_Table extends WP_List_Table {

    public function __construct () {
        parent::__construct(array(
            'singular' => esc_html__('Known Hash', 'wp-sri'),
            'plural' => esc_html__('Known Hashes', 'wp-sri'),
            'ajax' => false
        ));
    }

    public function no_items () {
        esc_html_e('No hashes known.', 'wp-sri');
    }

    public function get_columns () {
        return array(
            'cb' => '<input type="checkbox" />',
            'url' => esc_html__('URL', 'wp-sri'),
            'hash' => esc_html__('Hash', 'wp-sri')
        );
    }

    public function get_sortable_columns () {
        return array(
            'url' => array('url', false),
            'hash' => array('hash', false),
        );
    }

    public function get_bulk_actions () {
        return array(
            'delete' => esc_html__('Delete', 'wp-sri')
        );
    }

    function column_cb ($item) {
        return sprintf(
            '<input type="checkbox" name="url[]" value="%s" />', rawurlencode($item['url'])
        );
    }

    private function usort_reorder ($a, $b) {
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'url';
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
        $result = strcmp($a[$orderby], $b[$orderby]);
        return ($order === 'asc') ? $result : -$result;
    }

    public function search ($value) {
        return (false !== strpos($value, $_POST['s']));
    }

    public function prepare_items () {
        global $wp_sri_plugin;
        $this->_column_headers = $this->get_column_info();

        $known_hashes = $wp_sri_plugin->getKnownHashes();
        if (!empty($_POST['s'])) {
            $known_hashes = array_flip(array_filter(array_flip($known_hashes), array($this, 'search')));
        }
        $total_hashes = count($known_hashes);
        $show_on_page = $this->get_items_per_page('wp_sri_hashes_per_page', 20);
        $shown_hashes = array_slice($known_hashes, (($this->get_pagenum() - 1) * $show_on_page), $show_on_page);
        $this->set_pagination_args(array(
            'total_items' => $total_hashes,
            'total_pages' => ceil($total_hashes / $show_on_page),
            'per_page' => $show_on_page
        ));

        $items = array();
        foreach ($shown_hashes as $url => $hash) {
            $items[] = array(
                'url' => $url,
                'hash' => $hash
            );
        }
        usort($items, array(&$this, 'usort_reorder'));
        return $this->items = $items;
    }

    protected function column_url ($item) {
        $actions = array(
            'delete' => sprintf(
                '<a href="?page=%s&amp;action=%s&amp;url=%s&amp;_wp_sri_nonce=%s&amp;orderby=%s&amp;order=%s" title="%s">%s</a>',
                $_REQUEST['page'], 'delete', rawurlencode($item['url']), wp_create_nonce('delete_sri_hash'),
                (!empty($_GET['orderby'])) ? $_GET['orderby'] : '', (!empty($_GET['order'])) ? $_GET['order'] : '',
                esc_html__('Remove this URL and hash pair.', 'wp-sri'), esc_html__('Delete', 'wp-sri')
            )
        );
        return sprintf('%1$s %2$s', $item['url'], $this->row_actions($actions));
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
}
