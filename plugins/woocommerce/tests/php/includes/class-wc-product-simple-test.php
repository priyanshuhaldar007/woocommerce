<?php

/**
 * Tests for WC_Product_Simple.
 */
class WC_Product_Simple_Test extends \WC_Unit_Test_Case {
	/**
	 * @testdox 'add_to_cart_url' should preserve the add-to-cart parameter and remove other query parameters.
	 */
	public function test_add_to_cart_url_removes_unwanted_query_params() {
		$product = WC_Helper_Product::create_simple_product();

		// Simulate URL with various query parameters
		$_GET['add-to-cart'] = $product->get_id();
		$_GET['utm_source']  = 'email';
		$_GET['utm_medium']  = 'newsletter';
		$_GET['utm_campaign'] = 'spring_sale';

		$url = $product->add_to_cart_url();

		// Should contain the add-to-cart parameter
		$this->assertStringContainsString( 'add-to-cart=' . $product->get_id(), $url );

		// Should NOT contain unwanted tracking parameters
		$this->assertStringNotContainsString( 'utm_source', $url );
		$this->assertStringNotContainsString( 'utm_medium', $url );
		$this->assertStringNotContainsString( 'utm_campaign', $url );

		// Clean up
		unset( $_GET['add-to-cart'] );
		unset( $_GET['utm_source'] );
		unset( $_GET['utm_medium'] );
		unset( $_GET['utm_campaign'] );
	}

	/**
	 * @testdox 'add_to_cart_url' should return permalink when not purchasable.
	 */
	public function test_add_to_cart_url_returns_permalink_when_not_purchasable() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_purchasable( false );
		$product->save();

		$_GET['utm_source'] = 'email';

		$url = $product->add_to_cart_url();

		// Should return the permalink without any query parameters
		$this->assertEquals( $product->get_permalink(), $url );

		// Clean up
		unset( $_GET['utm_source'] );
	}

	/**
	 * @testdox 'add_to_cart_url' should return permalink when not in stock.
	 */
	public function test_add_to_cart_url_returns_permalink_when_not_in_stock() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_stock_status( 'outofstock' );
		$product->save();

		$_GET['utm_source'] = 'email';

		$url = $product->add_to_cart_url();

		// Should return the permalink without any query parameters
		$this->assertEquals( $product->get_permalink(), $url );

		// Clean up
		unset( $_GET['utm_source'] );
	}

	/**
	 * @testdox 'add_to_cart_url' should sanitize query parameter names to prevent injection attacks.
	 */
	public function test_add_to_cart_url_sanitizes_query_parameter_names() {
		$product = WC_Helper_Product::create_simple_product();

		// Simulate URL with parameter names that need sanitization
		$_GET['add-to-cart']              = $product->get_id();
		$_GET['<script>alert(1)</script>'] = 'value';
		$_GET['normal_param']             = 'value';

		$url = $product->add_to_cart_url();

		// Should contain the add-to-cart parameter
		$this->assertStringContainsString( 'add-to-cart=' . $product->get_id(), $url );

		// Malicious parameter should be removed
		$this->assertStringNotContainsString( 'script', $url );
		$this->assertStringNotContainsString( 'alert', $url );

		// Clean up
		unset( $_GET['add-to-cart'] );
		unset( $_GET['<script>alert(1)</script>'] );
		unset( $_GET['normal_param'] );
	}

	/**
	 * @testdox 'add_to_cart_url' should handle empty GET parameters gracefully.
	 */
	public function test_add_to_cart_url_handles_empty_get_parameters() {
		$product = WC_Helper_Product::create_simple_product();

		// Ensure $_GET is empty
		$_GET = array();

		$url = $product->add_to_cart_url();

		// Should return the base product URL with the add-to-cart parameter
		$this->assertStringContainsString( $product->get_id(), $url );
		$this->assertStringContainsString( 'add-to-cart', $url );
	}

	/**
	 * @testdox 'add_to_cart_url' should return permalink for feed pages.
	 */
	public function test_add_to_cart_url_returns_permalink_for_feed() {
		$product = WC_Helper_Product::create_simple_product();

		$_GET['utm_source'] = 'email';

		// Mock is_feed to return true
		add_filter( 'is_feed', '__return_true' );

		$url = $product->add_to_cart_url();

		// Should return the permalink without add-to-cart parameter for feed pages
		$this->assertEquals( $product->get_permalink(), $url );

		// Clean up
		remove_filter( 'is_feed', '__return_true' );
		unset( $_GET['utm_source'] );
	}

	/**
	 * @testdox 'add_to_cart_url' should return permalink for 404 pages.
	 */
	public function test_add_to_cart_url_returns_permalink_for_404() {
		$product = WC_Helper_Product::create_simple_product();

		$_GET['utm_source'] = 'email';

		// Mock is_404 to return true
		add_filter( 'is_404', '__return_true' );

		$url = $product->add_to_cart_url();

		// Should return the permalink without add-to-cart parameter for 404 pages
		$this->assertEquals( $product->get_permalink(), $url );

		// Clean up
		remove_filter( 'is_404', '__return_true' );
		unset( $_GET['utm_source'] );
	}
}