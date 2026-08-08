<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Core\Providers_Registry;
use MeuMouse\Hubgo\Core\Tracking_Manager;

use Automattic\WooCommerce\Utilities\OrderUtil;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Migrates order tracking data from WooCommerce Shipment Tracking into HubGo.
 *
 * Shipment Tracking stores its shipments in the `_wc_shipment_tracking_items`
 * order meta; HubGo uses `_hubgo_tracking_items` with different keys and a
 * different date format. This class converts one into the other, order by
 * order, and never touches the source meta: leaving it in place is what lets a
 * store roll back by simply re-activating the other plugin, and it keeps the
 * order set stable while the migration pages through it.
 *
 * The migrated orders are flagged with {@see self::MIGRATED_META_KEY} so a
 * re-run is a no-op even if the progress option is lost.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Woo_Shipment_Tracking_Migration extends Abstract_Migration {

    /**
     * Migration ID, also the slug of the matching integration card.
     *
     * @since 3.0.0
     * @var string
     */
    const MIGRATION_ID = 'woo_shipment_tracking';

    /**
     * Identifier stored on every imported tracking item.
     *
     * @since 3.0.0
     * @var string
     */
    const SOURCE_ID = 'woocommerce-shipment-tracking';

    /**
     * Order meta key written by WooCommerce Shipment Tracking.
     *
     * @since 3.0.0
     * @var string
     */
    const SOURCE_META_KEY = '_wc_shipment_tracking_items';

    /**
     * Order meta key flagging an order as already migrated.
     *
     * @since 3.0.0
     * @var string
     */
    const MIGRATED_META_KEY = '_hubgo_wcst_migrated_at';

    /**
     * Cached count of orders holding source data, per request.
     *
     * @since 3.0.0
     * @var int|null
     */
    protected $source_count = null;


    /**
     * @inheritDoc
     */
    public function get_id() {
        return self::MIGRATION_ID;
    }


    /**
     * @inheritDoc
     */
    public function get_title() {
        return esc_html__( 'Migrate WooCommerce Shipment Tracking data', 'hubgo' );
    }


    /**
     * @inheritDoc
     */
    public function get_description() {
        return esc_html__( 'Copies the tracking codes stored by Shipment Tracking into HubGo tracking. The original data is kept, so nothing is lost if you need to roll back.', 'hubgo' );
    }


    /**
     * There is something to migrate whenever an order still carries the source
     * meta — the other plugin does not need to be active for that.
     *
     * @inheritDoc
     */
    public function is_available() {
        return $this->count_source_orders() > 0;
    }


    /**
     * @inheritDoc
     */
    public function count_source_orders() {
        if ( null !== $this->source_count ) {
            return $this->source_count;
        }

        global $wpdb;

        $table = $this->get_meta_table();
        $column = $this->get_order_id_column();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column come from get_meta_table().
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT( DISTINCT {$column} ) FROM {$table} WHERE meta_key = %s",
            self::SOURCE_META_KEY
        ) );

        $this->source_count = (int) $count;

        return $this->source_count;
    }


    /**
     * @inheritDoc
     */
    public function get_source_order_ids( $limit, $offset ) {
        global $wpdb;

        $table = $this->get_meta_table();
        $column = $this->get_order_id_column();

        // Ordered by ID so paging stays stable between requests: the source
        // meta is never deleted, so the result set does not shift under us.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column come from get_meta_table().
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT {$column} FROM {$table} WHERE meta_key = %s ORDER BY {$column} ASC LIMIT %d OFFSET %d",
            self::SOURCE_META_KEY,
            max( 1, (int) $limit ),
            max( 0, (int) $offset )
        ) );

        return array_map( 'absint', (array) $ids );
    }


    /**
     * @inheritDoc
     */
    public function migrate_order( $order_id ) {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( absint( $order_id ) ) : false;

        if ( ! $order ) {
            return 0;
        }

        if ( '' !== (string) $order->get_meta( self::MIGRATED_META_KEY, true ) ) {
            return 0;
        }

        $items = self::convert_items( $order->get_meta( self::SOURCE_META_KEY, true ) );

        $manager = new Tracking_Manager();
        $imported = $manager->import_items( $order->get_id(), $items, self::SOURCE_ID );

        // Flag the order even when it imported nothing (an empty or duplicated
        // source list), or every run would keep re-reading the same orders.
        $order->update_meta_data( self::MIGRATED_META_KEY, current_time( 'mysql' ) );
        $order->save();

        return $imported;
    }


    /**
     * Convert a Shipment Tracking item list into the HubGo shape.
     *
     * Public and static because the integration bridge reuses it to display
     * the other plugin's shipments before the migration is run.
     *
     * @since 3.0.0
     * @param mixed $items | Raw `_wc_shipment_tracking_items` value.
     * @return array<int,array<string,mixed>>
     */
    public static function convert_items( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $converted = array();

        foreach ( $items as $item ) {
            $item = self::convert_item( $item );

            if ( null !== $item ) {
                $converted[] = $item;
            }
        }

        return $converted;
    }


    /**
     * Convert a single Shipment Tracking item.
     *
     * @since 3.0.0
     * @param mixed $item | Raw source item.
     * @return array<string,mixed>|null Null when the item carries no code.
     */
    public static function convert_item( $item ) {
        if ( ! is_array( $item ) || empty( $item['tracking_number'] ) ) {
            return null;
        }

        $carrier = self::resolve_carrier( $item );

        return array(
            'tracking_number'    => (string) $item['tracking_number'],
            'provider'           => $carrier['provider'],
            'custom_provider'    => $carrier['custom_provider'],
            'custom_url'         => (string) ( $item['custom_tracking_link'] ?? '' ),
            'ship_date'          => self::convert_date( $item['date_shipped'] ?? '' ),
            'source'             => self::SOURCE_ID,
            'source_tracking_id' => (string) ( $item['tracking_id'] ?? '' ),
        );
    }


    /**
     * Resolve the carrier of a source item into HubGo's two carrier fields.
     *
     * Shipment Tracking stores the catalog carrier either verbatim ("Correios",
     * written by its metabox) or slugified ("correios", written by its
     * `wc_st_add_tracking_number()` helper), so both spellings are matched
     * against the HubGo catalog. A carrier that is not in the catalog is kept
     * as a free-typed one instead of being dropped.
     *
     * @since 3.0.0
     * @param array $item | Raw source item.
     * @return array{provider:string,custom_provider:string}
     */
    protected static function resolve_carrier( $item ) {
        $custom_provider = trim( (string) ( $item['custom_tracking_provider'] ?? '' ) );

        if ( '' !== $custom_provider ) {
            return array(
                'provider'        => '',
                'custom_provider' => $custom_provider,
            );
        }

        $stored = trim( (string) ( $item['tracking_provider'] ?? '' ) );

        if ( '' === $stored ) {
            return array(
                'provider'        => '',
                'custom_provider' => '',
            );
        }

        $needles = self::get_provider_keys( $stored );

        foreach ( Providers_Registry::get_providers() as $providers ) {
            foreach ( array_keys( (array) $providers ) as $provider ) {
                if ( array_intersect( $needles, self::get_provider_keys( $provider ) ) ) {
                    return array(
                        'provider'        => (string) $provider,
                        'custom_provider' => '',
                    );
                }
            }
        }

        return array(
            'provider'        => '',
            'custom_provider' => $stored,
        );
    }


    /**
     * Comparable forms of a carrier name, ignoring case, spacing and accents.
     *
     * Two slugs are produced because Shipment Tracking writes both: its
     * metabox stores the label verbatim ("DHL US"), while
     * `wc_st_add_tracking_number()` stores `sanitize_title()` of it ("dhl-us")
     * after matching on a space-stripped variant ("dhlus"). Comparing the sets
     * matches every spelling the other plugin can leave behind.
     *
     * @since 3.0.0
     * @param string $provider | Carrier name or slug.
     * @return array<int,string>
     */
    protected static function get_provider_keys( $provider ) {
        $provider = (string) $provider;

        return array_values( array_unique( array_filter( array(
            sanitize_title( $provider ),
            sanitize_title( str_replace( ' ', '', $provider ) ),
        ) ) ) );
    }


    /**
     * Convert the source shipping date into HubGo's `Y-m-d` string.
     *
     * Shipment Tracking stores a Unix timestamp, but older records written
     * through its import compatibility layers can hold a date string.
     *
     * @since 3.0.0
     * @param mixed $date_shipped | Raw source date.
     * @return string
     */
    protected static function convert_date( $date_shipped ) {
        if ( is_numeric( $date_shipped ) && (int) $date_shipped > 0 ) {
            return gmdate( 'Y-m-d', (int) $date_shipped );
        }

        $timestamp = is_string( $date_shipped ) && '' !== $date_shipped ? strtotime( $date_shipped ) : false;

        return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
    }


    /**
     * Whether an order has already been migrated.
     *
     * @since 3.0.0
     * @param \WC_Order|\WC_Order_Refund $order | Order object.
     * @return bool
     */
    public static function is_order_migrated( $order ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
            return false;
        }

        return '' !== (string) $order->get_meta( self::MIGRATED_META_KEY, true );
    }


    /**
     * Meta table holding order meta under the active storage engine.
     *
     * `wc_get_orders()` cannot answer this question: the legacy post storage
     * silently drops a raw `meta_query` argument (see
     * WC_Data_Store_WP::get_wp_query_args()), which would turn the source
     * filter into "every order in the store".
     *
     * @since 3.0.0
     * @return string
     */
    protected function get_meta_table() {
        global $wpdb;

        return $this->is_hpos_enabled() ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
    }


    /**
     * Order ID column of the active meta table.
     *
     * @since 3.0.0
     * @return string
     */
    protected function get_order_id_column() {
        return $this->is_hpos_enabled() ? 'order_id' : 'post_id';
    }


    /**
     * Whether WooCommerce stores orders in the custom tables.
     *
     * @since 3.0.0
     * @return bool
     */
    protected function is_hpos_enabled() {
        if ( ! class_exists( OrderUtil::class ) || ! method_exists( OrderUtil::class, 'custom_orders_table_usage_is_enabled' ) ) {
            return false;
        }

        return OrderUtil::custom_orders_table_usage_is_enabled();
    }
}
