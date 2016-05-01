<?php
/**
 * Test cases for the WP-SRI plugin.
 *
 * @package plugin
 */
class WP_SRI_Plugin_Test extends WP_UnitTestCase {

    protected $plugin;
    protected $excluded;

    public function setUp () {
        parent::setUp();
        $this->plugin = new WP_SRI_Plugin();
        $this->excluded = get_option( WP_SRI_Plugin::prefix . 'excluded_hashes', array() );
    }

    public function test_localResourceIsSucessfullyDetected () {
        $url = trailingslashit(get_site_url()) . '/example.js';
        $this->assertTrue( $this->plugin->isLocalResource($url) );
    }

    public function test_remoteResourceIsSuccessfullyDetected () {
        $url = 'https://cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js';
        $this->assertFalse( $this->plugin->isLocalResource($url) );
    }

    public function test_hashResource () {
        $content = 'alert("Hello, world!");';
        $expected_hash = 'niqXkYYIkmWt0jYVFjVzcI+Q5nc3jzIdmbLXJqKD5A8=';
        $encoded_hash = $this->plugin->hashResource($content);
        $this->assertEquals( $expected_hash, $encoded_hash );
    }

    public function test_deleteKnownHash () {
        update_option('wp_sri_known_hashes', array(
            '//cdn.datatables.net/1.10.6/js/jquery.dataTables.min.js' => 'JOLmOuOEVbUWcM57vmy0F48W/2S7UCJB3USm7/Tu10U='
        ));
        $remaining_known_hashes = array();
        $this->plugin->deleteKnownHash('//cdn.datatables.net/1.10.6/js/jquery.dataTables.min.js');
        $this->assertEquals($remaining_known_hashes, get_option('wp_sri_known_hashes'));
    }

    public function test_filterLinkTag () {
        // TODO: write a test with mock HTTP responses?
    }

    public function testUpdateExcludedUrl() {
        $url = '//fonts.googleapis.com/css?family=Lato%3A300%2C400%2C700&ver=1.0.0';

        $this->assertCount( 2, $this->excluded );
        $this->assertFalse( array_search( esc_url( $url ), $this->excluded ) );
        $this->plugin->updateExcludedUrl( $url, true );
        $this->excluded = get_option( WP_SRI_Plugin::prefix.'excluded_hashes', array() );
        $this->assertTrue( false !== array_search( esc_url( $url ), $this->excluded ) );
    }

    public function testProcessActions() {
        $url = 'https://cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js';

        $this->assertFalse( array_search( esc_url( $url ), $this->excluded ) );

        // Set up our $_GET vars
        $_GET['_wp_sri_nonce'] = wp_create_nonce( 'update_sri_hash' );
        $_GET['url']           = rawurlencode( $url );
        $_GET['action']        = 'exclude';

        $this->plugin->processActions();

        // Grab our updated exclude array
        $this->excluded = get_option( WP_SRI_Plugin::prefix.'excluded_hashes', array() );

        // The plugin added our script and stylesheet so this should the 3rd
        $this->assertCount( 3, $this->excluded );
        $this->assertEquals( 2, array_search( rawurldecode( $url ), $this->excluded ) );

        $_GET['_wp_sri_nonce'] = wp_create_nonce( 'update_sri_hash' );
        $_GET['url']           = rawurlencode( $url );
        $_GET['action']        = 'include';

        $this->plugin->processActions();

        // Grab our updated exclude array
        $this->excluded = get_option( WP_SRI_Plugin::prefix.'excluded_hashes', array() );

        // Our array count should be one fewer now.
        $this->assertCount( 2, $this->excluded );
        // URL should no longer be found the array
        $this->assertEquals( false, array_search( rawurldecode( $url ), $this->excluded ) );
    }

}
