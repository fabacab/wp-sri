<?php
/**
 * Test cases for the WP-SRI plugin.
 *
 * @package plugin
 */
class WP_SRI_Plugin_Test extends WP_UnitTestCase {

    public function setUp () {
        parent::setUp();
        $this->plugin = new WP_SRI_Plugin();
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

    public function test_filterLinkTag () {
        // TODO: write a test with mock HTTP responses?
    }

}
