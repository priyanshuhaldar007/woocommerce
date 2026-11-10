<?php
/**
 * Deleted attribute cleanup tests.
 *
 * @package WooCommerce\Tests\Internal\ProductAttributes
 */

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\ProductAttributes;

use Automattic\WooCommerce\Internal\ProductAttributes\DeletedAttributeCleanup;
use WC_Helper_Product;
use WC_Unit_Test_Case;

/**
 * Tests for the DeletedAttributeCleanup class.
 */
class DeletedAttributeCleanupTest extends WC_Unit_Test_Case {

	/**
	 * The System Under Test.
	 *
	 * @var DeletedAttributeCleanup
	 */
	private $sut;

	/**
	 * Taxonomy of the attribute created for the current test.
	 *
	 * @var string
	 */
	private $taxonomy;

	/**
	 * ID of the attribute created for the current test.
	 *
	 * @var int
	 */
	private $attribute_id;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->sut = wc_get_container()->get( DeletedAttributeCleanup::class );

		$slug               = 'cleanup-' . strtolower( wp_generate_password( 6, false, false ) );
		$this->attribute_id = wc_create_attribute(
			array(
				'name' => 'Cleanup attribute',
				'slug' => $slug,
			)
		);
		$this->taxonomy     = wc_attribute_taxonomy_name( $slug );
		register_taxonomy( $this->taxonomy, array( 'product' ) );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tearDown(): void {
		try {
			// Attribute rows live in a custom table and the taxonomy in a global, neither of which the base class resets.
			wc_delete_attribute( $this->attribute_id );
			if ( taxonomy_exists( $this->taxonomy ) ) {
				unregister_taxonomy( $this->taxonomy );
			}
		} finally {
			parent::tearDown();
		}
	}

	/**
	 * @testdox Should remove a deleted attribute from product meta and keep the product's other attributes.
	 */
	public function test_deleting_attribute_removes_it_from_products(): void {
		$product_id = $this->create_product_with_attribute_meta();

		$this->assertTrue( wc_delete_attribute( $this->attribute_id ), 'The attribute should be deleted.' );

		$meta = get_post_meta( $product_id, '_product_attributes', true );
		$this->assertArrayNotHasKey( $this->taxonomy, $meta, 'The deleted attribute should be removed from the product meta.' );
		$this->assertSame( 'a \\ b', $meta['custom']['value'], 'Other attributes should be kept unchanged, including backslashes.' );
	}

	/**
	 * @testdox Should leave product meta alone when the attribute exists, so a re-created attribute is not stripped.
	 */
	public function test_cleanup_skips_an_existing_attribute(): void {
		$product_id = $this->create_product_with_attribute_meta();

		$this->sut->handle_woocommerce_cleanup_deleted_attribute( $this->taxonomy, 0 );

		$meta = get_post_meta( $product_id, '_product_attributes', true );
		$this->assertArrayHasKey( $this->taxonomy, $meta, 'An existing attribute should stay on the product.' );
	}

	/**
	 * @testdox Should only look at products after the given product ID, so a later batch does not redo earlier ones.
	 */
	public function test_cleanup_starts_after_the_given_product_id(): void {
		global $wpdb;

		$first_id  = $this->create_product_with_attribute_meta();
		$second_id = $this->create_product_with_attribute_meta();
		// Remove the attribute row only, so the delete hook does not clean the products first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test fixture; no API deletes the row without firing the hook.
		$wpdb->delete( $wpdb->prefix . 'woocommerce_attribute_taxonomies', array( 'attribute_id' => $this->attribute_id ) );

		$this->sut->handle_woocommerce_cleanup_deleted_attribute( $this->taxonomy, $first_id );

		$this->assertArrayHasKey( $this->taxonomy, get_post_meta( $first_id, '_product_attributes', true ), 'A product at or before the offset should not be touched.' );
		$this->assertArrayNotHasKey( $this->taxonomy, get_post_meta( $second_id, '_product_attributes', true ), 'A product after the offset should be cleaned.' );
	}

	/**
	 * Creates a product whose attributes meta holds the test attribute and one custom attribute.
	 *
	 * @return int Product ID.
	 */
	private function create_product_with_attribute_meta(): int {
		$product_id = WC_Helper_Product::create_simple_product()->get_id();

		update_post_meta(
			$product_id,
			'_product_attributes',
			wp_slash(
				array(
					$this->taxonomy => array(
						'name'         => $this->taxonomy,
						'value'        => '',
						'position'     => 0,
						'is_visible'   => 1,
						'is_variation' => 0,
						'is_taxonomy'  => 1,
					),
					'custom'        => array(
						'name'         => 'Custom',
						'value'        => 'a \\ b',
						'position'     => 1,
						'is_visible'   => 1,
						'is_variation' => 0,
						'is_taxonomy'  => 0,
					),
				)
			)
		);

		return $product_id;
	}
}
