<?php
/**
 * Test cases for the WP-SRI plugin AJAX functions
 *
 * @group ajax
 * @runTestsInSeparateProcesses
 */
class WP_SRI_AJAX_TEST extends WP_Ajax_UnitTestCase {

	public $excluded;
	public $url;

	public function setUp () {
		parent::setUp();

		$this->excluded = get_option( WP_SRI_Plugin::prefix.'excluded_hashes', array() );
		$this->url = esc_url( 'http://plugins.dev/wp-content/themes/digital-pro/js/my-ajax.js' );
		$this->excluded[] = $this->url;

	}

	public function teardown() {
		parent::teardown();
	}

	public function testExcludedUrlAdded() {
		$this->_setRole( 'administrator' );

		// Setup our POST vars
		$_POST['security'] = wp_create_nonce( 'sri-update-exclusion' );
		$_POST['url'] = 'http://plugins.dev/wp-content/plugins/wp-sri/js/test.js&test=this';
		$_POST['checked'] = 'true';

		try {
			// Kick off our AJAX
			$this->_handleAjax( 'update_sri_exclude' );
		} catch( WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response );
			$this->assertInternalType( 'object', $response );
			$this->assertObjectHasAttribute( 'success', $response );
			$this->assertTrue( $response->success );
			$this->assertObjectHasAttribute( 'data', $response );
			$this->assertEquals( 'done', $response->data );

			// Fetch our option after it's been updated by our PHP function
			$this->excluded = get_option( WP_SRI_Plugin::prefix.'excluded_hashes', array() );
			$expected_url = esc_url( $_POST['url'] );
			$result = array_search( $expected_url, $this->excluded );
			// Verify URL has been added
			$this->assertTrue( false !== $result );
			// Verify unescaped URL has not been added
			$this->assertFalse( array_search( $_POST['url'], $this->excluded ) );
		}
	}

	public function testExcludedUrlRemoved() {
		$this->_setRole( 'administrator' );

		// Verify URL was added in setup()
		$this->assertTrue( false !== array_search( $this->url, $this->excluded ) );

		// Setup our POST vars
		$_POST['security'] = wp_create_nonce( 'sri-update-exclusion' );
		$_POST['url'] = $this->url;
		$_POST['checked'] = 'false';

		try {
			// Kick off our AJAX
			$this->_handleAjax( 'update_sri_exclude' );
		} catch( WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response );
			$this->assertInternalType( 'object', $response );
			$this->assertObjectHasAttribute( 'success', $response );
			$this->assertTrue( $response->success );
			$this->assertObjectHasAttribute( 'data', $response );
			$this->assertEquals( 'done', $response->data );

			// Fetch our option after it's been updated by our PHP function
			$this->excluded = get_option( WP_SRI_Plugin::prefix.'excluded_hashes', array() );
			// Verify URL has been removed
			$this->assertFalse( array_search( $this->url, $this->excluded ) );
		}
	}


}
