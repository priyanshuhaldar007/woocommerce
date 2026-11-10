<?php
/**
 * DeletedAttributeCleanup class file.
 *
 * @package WooCommerce\Classes
 */

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\ProductAttributes;

use Automattic\WooCommerce\Internal\RegisterHooksInterface;

/**
 * Removes a deleted global attribute from the products that used it.
 *
 * Without this the entry stays in each product's attributes meta, hidden while the
 * taxonomy is gone, and shows up again if an attribute with the same slug is created.
 *
 * @internal
 * @since 11.4.0
 */
class DeletedAttributeCleanup implements RegisterHooksInterface {

	/**
	 * Action Scheduler hook that continues the cleanup in the background.
	 */
	private const CLEANUP_ACTION = 'woocommerce_cleanup_deleted_attribute';

	/**
	 * Products cleaned per run.
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Register hooks.
	 *
	 * @since 11.4.0
	 */
	public function register(): void {
		add_action( 'woocommerce_attribute_deleted', array( $this, 'handle_woocommerce_attribute_deleted' ), 10, 3 );
		add_action( self::CLEANUP_ACTION, array( $this, 'handle_woocommerce_cleanup_deleted_attribute' ), 10, 2 );
	}

	/**
	 * Handle the woocommerce_attribute_deleted hook.
	 *
	 * @internal
	 * @since 11.4.0
	 *
	 * @param mixed $id       Attribute ID.
	 * @param mixed $name     Attribute name.
	 * @param mixed $taxonomy Attribute taxonomy name.
	 */
	public function handle_woocommerce_attribute_deleted( $id, $name, $taxonomy ): void {
		$this->handle_woocommerce_cleanup_deleted_attribute( $taxonomy );
	}

	/**
	 * Handle the woocommerce_cleanup_deleted_attribute scheduled action.
	 *
	 * Cleans one batch of products, in product ID order, and schedules the next batch
	 * when this one was full.
	 *
	 * @internal
	 * @since 11.4.0
	 *
	 * @param mixed $taxonomy         Attribute taxonomy name, such as pa_color.
	 * @param mixed $after_product_id Only products with a higher ID are looked at.
	 */
	public function handle_woocommerce_cleanup_deleted_attribute( $taxonomy, $after_product_id = 0 ): void {
		global $wpdb;

		// An attribute created again under the same slug owns these entries now.
		if ( ! is_string( $taxonomy ) || 0 !== strpos( $taxonomy, 'pa_' ) || $this->attribute_exists( $taxonomy ) ) {
			return;
		}

		// The attributes meta is a serialized array keyed by taxonomy name, so this matches the serialized key.
		$serialized_key = sprintf( 's:%d:"%s";', strlen( $taxonomy ), $taxonomy );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One pass over a meta key that has no query API for matching inside the value.
		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_product_attributes' AND post_id > %d AND meta_value LIKE %s
				ORDER BY post_id ASC
				LIMIT %d",
				absint( $after_product_id ),
				'%' . $wpdb->esc_like( $serialized_key ) . '%',
				self::BATCH_SIZE
			)
		);

		foreach ( $product_ids as $product_id ) {
			$attributes = get_post_meta( (int) $product_id, '_product_attributes', true );
			if ( is_array( $attributes ) && isset( $attributes[ $taxonomy ] ) ) {
				unset( $attributes[ $taxonomy ] );
				update_post_meta( (int) $product_id, '_product_attributes', wp_slash( $attributes ) );
			}
		}

		if ( count( $product_ids ) === self::BATCH_SIZE && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 1, self::CLEANUP_ACTION, array( $taxonomy, (int) end( $product_ids ) ), 'woocommerce' );
		}
	}

	/**
	 * Whether a global attribute currently exists for a taxonomy name.
	 *
	 * Reads the table directly because the attribute cache is still stale while
	 * woocommerce_attribute_deleted runs.
	 *
	 * @param string $taxonomy Attribute taxonomy name, such as pa_color.
	 * @return bool
	 */
	private function attribute_exists( string $taxonomy ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The cached attribute list is stale at this point.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
				substr( $taxonomy, 3 )
			)
		);
	}
}
